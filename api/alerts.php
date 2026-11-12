<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireLogin();
$method = apiRequireMethod('GET', 'PATCH');

try {
    $pdo = dbConnection();
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT a.id, e.device_id AS deviceId, a.created_at AS createdAt,
                    a.acknowledged_at AS acknowledgedAt, u.display_name AS acknowledgedBy
             FROM alerts a
             JOIN status_events e ON e.id = a.status_event_id
             LEFT JOIN users u ON u.id = a.acknowledged_by
             ORDER BY a.created_at DESC LIMIT 500'
        );
        $alerts = $statement->fetchAll();
        foreach ($alerts as &$alert) {
            $alert['id'] = (int) $alert['id'];
            $alert['deviceId'] = (int) $alert['deviceId'];
            $alert['acknowledged'] = $alert['acknowledgedAt'] !== null;
        }
        unset($alert);
        apiResponse(['alerts' => $alerts]);
    }

    $user = apiRequireRole('admin', 'operator');
    $input = apiInput();
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        apiResponse(['error' => 'รหัสแจ้งเตือนไม่ถูกต้อง'], 422);
    }
    $statement = $pdo->prepare(
        'UPDATE alerts SET acknowledged_by = :user_id, acknowledged_at = NOW()
         WHERE id = :id AND acknowledged_at IS NULL'
    );
    $statement->execute(['user_id' => $user['id'], 'id' => $id]);
    if (!$statement->rowCount()) {
        $exists = $pdo->prepare('SELECT id FROM alerts WHERE id = :id');
        $exists->execute(['id' => $id]);
        if (!$exists->fetchColumn()) {
            apiResponse(['error' => 'ไม่พบการแจ้งเตือน'], 404);
        }
    }
    apiAudit($pdo, $user, 'acknowledge', 'alert', (int) $id);
    apiResponse(['ok' => true]);
} catch (PDOException $exception) {
    apiDbFailure($exception);
}
