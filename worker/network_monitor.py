from __future__ import annotations

import concurrent.futures
import ipaddress
import json
import os
import platform
import re
import sys
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any


DEFAULT_API_URL = "http://127.0.0.1/mini-network-monitor/api/worker.php"
PING_TIME_PATTERN = re.compile(r"time([=<])\s*([\d.,]+)\s*ms", re.IGNORECASE)


class MonitorError(RuntimeError):
    pass


def api_request(base_url: str, token: str, action: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
    url = f"{base_url}?action={urllib.parse.quote(action)}"
    body = None if payload is None else json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=body,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {token}",
            **({"Content-Type": "application/json"} if body is not None else {}),
        },
        method="GET" if body is None else "POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            result = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        message = error.read().decode("utf-8", errors="replace")
        raise MonitorError(f"Worker API returned HTTP {error.code}: {message[:300]}") from error
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as error:
        raise MonitorError(f"Could not communicate with Worker API: {error}") from error

    if not isinstance(result, dict):
        raise MonitorError("Worker API returned an unexpected response.")
    return result


def ping_device(address: str, timeout_ms: int) -> tuple[bool, float | None, str]:
    try:
        ipaddress.ip_address(address)
    except ValueError as error:
        raise MonitorError(f"API returned an invalid IP address: {address}") from error

    timeout_ms = max(250, min(timeout_ms, 10_000))
    if platform.system().lower() == "windows":
        command = ["ping", "-n", "1", "-w", str(timeout_ms), address]
    else:
        command = ["ping", "-n", "-c", "1", "-W", str(max(1, (timeout_ms + 999) // 1000)), address]

    started = time.perf_counter()
    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            errors="replace",
            timeout=(timeout_ms / 1000) + 2,
            check=False,
            shell=False,
        )
    except FileNotFoundError as error:
        raise MonitorError("The system ping command is not available on PATH.") from error
    except subprocess.TimeoutExpired:
        return False, None, f"Ping process exceeded {timeout_ms} ms timeout"
    except OSError as error:
        raise MonitorError(f"Could not run ping: {error}") from error

    output = f"{result.stdout}\n{result.stderr}"
    if result.returncode != 0:
        return False, None, "No ICMP reply"

    match = PING_TIME_PATTERN.search(output)
    if match:
        response = float(match.group(2).replace(",", "."))
        if match.group(1) == "<":
            response = min(response, 0.9)
    else:
        response = round((time.perf_counter() - started) * 1000, 3)
    return True, response, "ICMP reply"


def check_one(
    base_url: str,
    token: str,
    device: dict[str, Any],
    timeout_ms: int,
    offline_after_failures: int,
) -> str:
    address = str(device.get("ip", ""))
    device_id = int(device["id"])
    network_cidr = str(device.get("cidr", ""))
    try:
        parsed_address = ipaddress.ip_address(address)
        network = ipaddress.ip_network(network_cidr, strict=False)
    except ValueError as error:
        raise MonitorError(f"Invalid registered IP/CIDR for device {device_id}.") from error
    if parsed_address not in network:
        raise MonitorError(f"Device {device_id} IP does not belong to its registered network; ping skipped.")

    online, response_ms, detail = ping_device(address, timeout_ms)
    if online:
        status = "online"
    else:
        failures = int(device.get("consecutiveFailures", 0)) + 1
        already_offline = device.get("currentStatus") == "offline"
        status = "offline" if already_offline or failures >= offline_after_failures else "unknown"
        detail = f"{detail}; consecutive failures: {failures}"

    api_request(
        base_url,
        token,
        "result",
        {
            "device_id": device_id,
            "status": status,
            "response_time_ms": response_ms,
            "attempts": 1,
            "detail": detail,
        },
    )
    return f"{address}: {status}"


def validate_api_url(api_url: str) -> str:
    parsed = urllib.parse.urlparse(api_url)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname:
        raise MonitorError("MNM_API_URL must be an absolute HTTP(S) URL.")
    if parsed.scheme != "https" and parsed.hostname not in {"localhost", "127.0.0.1", "::1"}:
        raise MonitorError("Use HTTPS when the Worker API is not on this computer.")
    return api_url.rstrip("?")


def run_once(base_url: str, token: str, concurrency: int) -> int:
    config = api_request(base_url, token, "targets")
    timeout_ms = int(config.get("timeout", 1500))
    retries = max(1, int(config.get("retries", 2)))
    devices = config.get("devices")
    if not isinstance(devices, list):
        raise MonitorError("Worker API did not return a device list.")

    api_request(base_url, token, "heartbeat", {"status": "running", "detail": f"checking {len(devices)} registered devices"})
    errors = 0
    if devices:
        with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as executor:
            futures = [
                executor.submit(check_one, base_url, token, device, timeout_ms, retries)
                for device in devices
            ]
            for future in concurrent.futures.as_completed(futures):
                try:
                    print(future.result(), flush=True)
                except MonitorError as error:
                    errors += 1
                    print(str(error), file=sys.stderr, flush=True)
    if errors:
        api_request(base_url, token, "heartbeat", {"status": "error", "detail": f"{errors} registered device(s) could not be checked"})

    return max(15, int(config.get("interval", 60)))


def main() -> int:
    token = os.environ.get("MNM_WORKER_TOKEN", "")
    if len(token) < 32:
        print("Set MNM_WORKER_TOKEN before starting the monitor worker.", file=sys.stderr)
        return 2
    base_url: str | None = None
    try:
        base_url = validate_api_url(os.environ.get("MNM_API_URL", DEFAULT_API_URL))
        concurrency = max(1, min(int(os.environ.get("MNM_WORKER_CONCURRENCY", "10")), 32))
        once = "--once" in sys.argv[1:]
        while True:
            started = time.monotonic()
            interval = run_once(base_url, token, concurrency)
            if once:
                api_request(base_url, token, "heartbeat", {"status": "stopped", "detail": "one-shot run completed"})
                return 0
            time.sleep(max(1, interval - (time.monotonic() - started)))
    except MonitorError as error:
        print(str(error), file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        if base_url:
            try:
                api_request(base_url, token, "heartbeat", {"status": "stopped", "detail": "worker stopped"})
            except MonitorError as error:
                print(str(error), file=sys.stderr)
        return 130
    except (ValueError, KeyError) as error:
        print(f"Invalid worker configuration or API data: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
