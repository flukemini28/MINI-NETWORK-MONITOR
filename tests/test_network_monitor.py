import importlib.util
import pathlib
import unittest
from unittest.mock import patch


WORKER_PATH = pathlib.Path(__file__).parents[1] / "worker" / "network_monitor.py"
SPEC = importlib.util.spec_from_file_location("network_monitor", WORKER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Could not load the monitor worker module.")
worker = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(worker)


class MonitorWorkerTests(unittest.TestCase):
    def test_refuses_to_ping_an_ip_outside_its_registered_network(self):
        device = {"id": 42, "ip": "192.0.2.10", "cidr": "10.0.0.0/24"}

        with patch.object(worker, "ping_device") as ping, patch.object(worker, "api_request") as api:
            with self.assertRaises(worker.MonitorError):
                worker.check_one("http://127.0.0.1/api/worker.php", "x" * 64, device, 1000, 2)

        ping.assert_not_called()
        api.assert_not_called()

    def test_sends_only_registered_target_after_successful_ping(self):
        device = {
            "id": 7,
            "ip": "10.0.0.8",
            "cidr": "10.0.0.0/24",
            "currentStatus": "unknown",
            "consecutiveFailures": 0,
        }

        with patch.object(worker, "ping_device", return_value=(True, 1.25, "ICMP reply")):
            with patch.object(worker, "api_request", return_value={"ok": True}) as api:
                status = worker.check_one("http://127.0.0.1/api/worker.php", "x" * 64, device, 1000, 2)

        self.assertEqual(status, "10.0.0.8: online")
        payload = api.call_args.args[3]
        self.assertEqual(payload["device_id"], 7)
        self.assertEqual(payload["status"], "online")
        self.assertEqual(payload["response_time_ms"], 1.25)

    def test_waits_for_configured_consecutive_failures_before_offline(self):
        device = {
            "id": 8,
            "ip": "10.0.0.9",
            "cidr": "10.0.0.0/24",
            "currentStatus": "online",
            "consecutiveFailures": 0,
        }

        with patch.object(worker, "ping_device", return_value=(False, None, "No ICMP reply")):
            with patch.object(worker, "api_request", return_value={"ok": True}) as api:
                first_status = worker.check_one("http://127.0.0.1/api/worker.php", "x" * 64, device, 1000, 2)
                device["consecutiveFailures"] = 1
                second_status = worker.check_one("http://127.0.0.1/api/worker.php", "x" * 64, device, 1000, 2)

        self.assertEqual(first_status, "10.0.0.9: unknown")
        self.assertEqual(second_status, "10.0.0.9: offline")
        self.assertEqual(api.call_args_list[0].args[3]["status"], "unknown")
        self.assertEqual(api.call_args_list[1].args[3]["status"], "offline")

    def test_requires_https_for_remote_worker_api(self):
        with self.assertRaises(worker.MonitorError):
            worker.validate_api_url("http://monitor.example.internal/api/worker.php")

        self.assertEqual(
            worker.validate_api_url("https://monitor.example.internal/api/worker.php"),
            "https://monitor.example.internal/api/worker.php",
        )


if __name__ == "__main__":
    unittest.main()
