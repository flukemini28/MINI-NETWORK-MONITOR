<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function workerResponse(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authorization === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authorization = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
if ($authorization === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) || strlen($matches[1]) < 32) {
    workerResponse(['error' => 'Unauthorized worker'], 401);
}

try {
    $pdo = dbConnection();
    $tokenStatement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'worker_token_hash'");
    $tokenStatement->execute();
    $storedTokenHash = (string) $tokenStatement->fetchColumn();
    if ($storedTokenHash === '' || !hash_equals($storedTokenHash, hash('sha256', $matches[1]))) {
        workerResponse(['error' => 'Unauthorized worker'], 401);
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'targets' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $devices = $pdo->query(
            "SELECT d.id, d.ip_address AS ip, n.network_cidr AS cidr, d.current_status AS currentStatus,
                    (SELECT COUNT(*) FROM probe_checks p
                     WHERE p.device_id = d.id AND p.status = 'unknown'
                       AND p.checked_at > COALESCE(
                         (SELECT MAX(p2.checked_at) FROM probe_checks p2
                          WHERE p2.device_id = d.id AND p2.status = 'online'),
                         '1970-01-01 00:00:00'
                       )) AS consecutiveFailures
             FROM devices d JOIN networks n ON n.id = d.network_id
             WHERE d.is_active = 1 AND n.is_active = 1
             ORDER BY d.id"
        )->fetchAll();
        $settings = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
        $map = [];
        foreach ($settings as $setting) {
            $map[$setting['setting_key']] = $setting['setting_value'];
        }
        workerResponse([
            'devices' => $devices,
            'interval' => max(15, (int) ($map['monitor_interval_seconds'] ?? 60)),
            'retries' => max(1, (int) ($map['offline_after_failures'] ?? 2)),
            'timeout' => max(250, (int) ($map['probe_timeout_ms'] ?? 1500)),
        ]);
    }

    if ($action === 'result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            workerResponse(['error' => 'Invalid JSON'], 400);
        }
        $deviceId = filter_var($input['device_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? '';
        $responseTime = $input['response_time_ms'] ?? null;
        $attempts = filter_var($input['attempts'] ?? 1, FILTER_VALIDATE_INT);
        $detail = trim((string) ($input['detail'] ?? ''));
        if (!$deviceId || !in_array($status, ['online', 'offline', 'unknown'], true)
            || ($responseTime !== null && (!is_numeric($responseTime) || (float) $responseTime < 0))
            || !$attempts || $attempts > 10 || strlen($detail) > 255) {
            workerResponse(['error' => 'Invalid probe result'], 422);
        }

        $pdo->beginTransaction();
        $deviceStatement = $pdo->prepare(
            'SELECT id, current_status FROM devices WHERE id = :id AND is_active = 1 FOR UPDATE'
        );
        $deviceStatement->execute(['id' => $deviceId]);
        $device = $deviceStatement->fetch();
        if (!$device) {
            $pdo->rollBack();
            workerResponse(['error' => 'Device is not registered or inactive'], 404);
        }
        $now = date('Y-m-d H:i:s');
        $checkStatement = $pdo->prepare(
            'INSERT INTO probe_checks (device_id, status, response_time_ms, checked_at, attempts, detail)
             VALUES (:device_id, :status, :response, :checked_at, :attempts, :detail)'
        );
        $checkStatement->execute([
            'device_id' => $deviceId, 'status' => $status, 'response' => $responseTime,
            'checked_at' => $now, 'attempts' => $attempts, 'detail' => $detail ?: null,
        ]);

        $previousStatus = $device['current_status'];
        $nextStatus = $status === 'unknown' ? $previousStatus : $status;
        if ($status === 'unknown') {
            $update = $pdo->prepare('UPDATE devices SET last_checked_at = :checked_at WHERE id = :id');
            $update->execute(['checked_at' => $now, 'id' => $deviceId]);
        } else {
            $update = $pdo->prepare(
                'UPDATE devices SET current_status = :status, response_time_ms = :response, last_checked_at = :checked_at
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $status, 'response' => $responseTime, 'checked_at' => $now, 'id' => $deviceId,
            ]);
        }
        if ($nextStatus !== $previousStatus) {
            $event = $pdo->prepare(
                'INSERT INTO status_events (device_id, previous_status, new_status, occurred_at, detail)
                 VALUES (:device_id, :previous_status, :new_status, :occurred_at, :detail)'
            );
            $event->execute([
                'device_id' => $deviceId, 'previous_status' => $previousStatus,
                'new_status' => $nextStatus, 'occurred_at' => $now, 'detail' => $detail ?: null,
            ]);
            if ($nextStatus === 'offline') {
                $alert = $pdo->prepare('INSERT INTO alerts (status_event_id) VALUES (:event_id)');
                $alert->execute(['event_id' => $pdo->lastInsertId()]);
            }
        }
        $pdo->commit();
        workerResponse(['ok' => true, 'status' => $nextStatus]);
    }

    if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        $status = is_array($input) ? ($input['status'] ?? '') : '';
        $detail = is_array($input) ? trim((string) ($input['detail'] ?? '')) : '';
        if (!in_array($status, ['running', 'error', 'stopped'], true) || strlen($detail) > 255) {
            workerResponse(['error' => 'Invalid heartbeat'], 422);
        }
        $statement = dbConnection()->prepare(
            'INSERT INTO worker_heartbeats (worker_name, last_seen_at, status, detail)
             VALUES (:worker_name, NOW(), :status, :detail)
             ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at), status = VALUES(status), detail = VALUES(detail)'
        );
        $statement->execute(['worker_name' => 'mini-network-monitor', 'status' => $status, 'detail' => $detail ?: null]);
        workerResponse(['ok' => true]);
    }

    workerResponse(['error' => 'Unknown action or method'], 404);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    workerResponse(['error' => 'Database operation failed'], 500);
}
