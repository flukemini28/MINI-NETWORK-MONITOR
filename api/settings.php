<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireLogin();
$method = apiRequireMethod('GET', 'PUT');

try {
    $pdo = dbConnection();
    if ($method === 'GET') {
        $settings = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
        $map = [];
        foreach ($settings as $setting) {
            $map[$setting['setting_key']] = $setting['setting_value'];
        }
        apiResponse([
            'interval' => (int) ($map['monitor_interval_seconds'] ?? 60),
            'retries' => (int) ($map['offline_after_failures'] ?? 2),
            'timeout' => (int) ($map['probe_timeout_ms'] ?? 1500),
        ]);
    }

    $user = apiRequireRole('admin', 'operator');
    $input = apiInput();
    $interval = filter_var($input['interval'] ?? null, FILTER_VALIDATE_INT);
    $retries = filter_var($input['retries'] ?? null, FILTER_VALIDATE_INT);
    if (!$interval || $interval < 15 || $interval > 3600 || !$retries || $retries < 1 || $retries > 10) {
        apiResponse(['error' => 'รอบตรวจต้องอยู่ระหว่าง 15–3600 วินาที และจำนวนครั้ง 1–10'], 422);
    }
    $statement = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value, updated_by)
         VALUES (:setting_key, :setting_value, :updated_by)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );
    foreach (['monitor_interval_seconds' => $interval, 'offline_after_failures' => $retries] as $key => $value) {
        $statement->execute(['setting_key' => $key, 'setting_value' => (string) $value, 'updated_by' => $user['id']]);
    }
    apiAudit($pdo, $user, 'update', 'settings', null, ['interval' => $interval, 'retries' => $retries]);
    apiResponse(['ok' => true]);
} catch (PDOException $exception) {
    apiDbFailure($exception);
}
