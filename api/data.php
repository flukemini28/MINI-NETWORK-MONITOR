<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireLogin();
apiRequireMethod('GET');

try {
    $pdo = dbConnection();
    $networks = $pdo->query(
        'SELECT id, name, network_cidr AS cidr, gateway_ip AS gateway, department, is_active AS active
         FROM networks ORDER BY name, id'
    )->fetchAll();
    $devices = $pdo->query(
        'SELECT d.id, d.name, d.ip_address AS ip, d.device_type AS type, d.network_id AS networkId,
                d.department, d.location, d.current_status AS status, d.response_time_ms AS response,
                d.last_checked_at AS checkedAt, d.is_active AS active, n.name AS networkName
         FROM devices d JOIN networks n ON n.id = d.network_id
         ORDER BY d.name, d.id'
    )->fetchAll();
    $history = $pdo->query(
        'SELECT p.id, p.device_id AS deviceId, p.status, p.response_time_ms AS response,
                p.checked_at AS checkedAt, p.detail
         FROM probe_checks p ORDER BY p.checked_at DESC, p.id DESC LIMIT 500'
    )->fetchAll();
    $alerts = $pdo->query(
        'SELECT a.id, e.device_id AS deviceId, a.created_at AS createdAt,
                a.acknowledged_at AS acknowledgedAt, a.acknowledged_by AS acknowledgedById,
                u.display_name AS acknowledgedBy
         FROM alerts a
         JOIN status_events e ON e.id = a.status_event_id
         LEFT JOIN users u ON u.id = a.acknowledged_by
         ORDER BY a.created_at DESC LIMIT 200'
    )->fetchAll();
    $settings = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
    $heartbeat = $pdo->query(
        "SELECT last_seen_at, status FROM worker_heartbeats WHERE worker_name = 'mini-network-monitor'"
    )->fetch() ?: ['last_seen_at' => null, 'status' => 'stopped'];
} catch (PDOException $exception) {
    apiDbFailure($exception);
}

foreach ($networks as &$network) {
    $network['id'] = (int) $network['id'];
    $network['active'] = (bool) $network['active'];
}
unset($network);

foreach ($devices as &$device) {
    $device['id'] = (int) $device['id'];
    $device['networkId'] = (int) $device['networkId'];
    $device['active'] = (bool) $device['active'];
    $device['response'] = $device['response'] === null ? null : (float) $device['response'];
}
unset($device);

foreach ($history as &$check) {
    $check['id'] = (int) $check['id'];
    $check['deviceId'] = (int) $check['deviceId'];
    $check['response'] = $check['response'] === null ? null : (float) $check['response'];
}
unset($check);

foreach ($alerts as &$alert) {
    $alert['id'] = (int) $alert['id'];
    $alert['deviceId'] = (int) $alert['deviceId'];
    $alert['acknowledged'] = $alert['acknowledgedAt'] !== null;
    unset($alert['acknowledgedById']);
}
unset($alert);

$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting['setting_value'];
}

apiResponse([
    'networks' => $networks,
    'devices' => $devices,
    'history' => $history,
    'alerts' => $alerts,
    'settings' => [
        'interval' => (int) ($settingsMap['monitor_interval_seconds'] ?? 60),
        'retries' => (int) ($settingsMap['offline_after_failures'] ?? 2),
        'timeout' => (int) ($settingsMap['probe_timeout_ms'] ?? 1500),
    ],
    'worker' => $heartbeat,
]);
