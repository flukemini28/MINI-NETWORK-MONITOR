<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireLogin();
$method = apiRequireMethod('GET', 'POST', 'PUT', 'DELETE');

try {
    $pdo = dbConnection();
    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $statement = $pdo->prepare(
            'SELECT d.id, d.name, d.ip_address AS ip, d.device_type AS type, d.network_id AS networkId,
                    d.department, d.location, d.current_status AS status, d.response_time_ms AS response,
                    d.last_checked_at AS checkedAt, d.is_active AS active, n.name AS networkName
             FROM devices d JOIN networks n ON n.id = d.network_id
             WHERE (:id IS NULL OR d.id = :device_id) ORDER BY d.name'
        );
        $statement->execute(['id' => $id ?: null, 'device_id' => $id ?: null]);
        apiResponse(['devices' => $statement->fetchAll()]);
    }

    $user = apiRequireRole('admin', 'operator');
    $input = apiInput();
    if ($method === 'POST' || $method === 'PUT') {
        $id = $method === 'PUT' ? filter_var($input['id'] ?? null, FILTER_VALIDATE_INT) : null;
        $name = trim((string) ($input['name'] ?? ''));
        $ip = trim((string) ($input['ip'] ?? ''));
        $type = (string) ($input['type'] ?? 'other');
        $networkId = filter_var($input['networkId'] ?? null, FILTER_VALIDATE_INT);
        $department = trim((string) ($input['department'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        $validTypes = ['computer', 'server', 'printer', 'router', 'switch', 'other'];

        if (($method === 'PUT' && !$id) || $name === '' || strlen($name) > 120 || !filter_var($ip, FILTER_VALIDATE_IP) || !$networkId || !in_array($type, $validTypes, true)) {
            apiResponse(['error' => 'กรุณาตรวจสอบชื่อ, IP, ประเภท และเครือข่าย'], 422);
        }
        if (strlen($department) > 120 || strlen($location) > 180) {
            apiResponse(['error' => 'หน่วยงานหรือตำแหน่งยาวเกินกำหนด'], 422);
        }
        $network = $pdo->prepare('SELECT network_cidr FROM networks WHERE id = :id AND is_active = 1');
        $network->execute(['id' => $networkId]);
        $networkCidr = $network->fetchColumn();
        if (!$networkCidr) {
            apiResponse(['error' => 'เครือข่ายไม่มีอยู่หรือปิดใช้งานแล้ว'], 422);
        }
        if (!ipMatchesCidr($ip, $networkCidr)) {
            apiResponse(['error' => 'IP Address ต้องอยู่ภายใน CIDR ของเครือข่ายที่เลือก'], 422);
        }

        if ($method === 'POST') {
            $statement = $pdo->prepare(
                'INSERT INTO devices (network_id, name, ip_address, device_type, department, location, created_by)
                 VALUES (:network_id, :name, :ip, :type, :department, :location, :created_by)'
            );
            $statement->execute([
                'network_id' => $networkId, 'name' => $name, 'ip' => $ip, 'type' => $type,
                'department' => $department ?: null, 'location' => $location ?: null, 'created_by' => $user['id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            apiAudit($pdo, $user, 'create', 'device', $id, ['name' => $name, 'ip' => $ip]);
            apiResponse(['id' => $id], 201);
        }

        $statement = $pdo->prepare(
            'UPDATE devices SET network_id = :network_id, name = :name, ip_address = :ip,
                    device_type = :type, department = :department, location = :location
             WHERE id = :id AND is_active = 1'
        );
        $statement->execute([
            'network_id' => $networkId, 'name' => $name, 'ip' => $ip, 'type' => $type,
            'department' => $department ?: null, 'location' => $location ?: null, 'id' => $id,
        ]);
        if (!$statement->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM devices WHERE id = :id AND is_active = 1');
            $exists->execute(['id' => $id]);
            if (!$exists->fetchColumn()) {
                apiResponse(['error' => 'ไม่พบอุปกรณ์'], 404);
            }
        }
        apiAudit($pdo, $user, 'update', 'device', (int) $id, ['name' => $name, 'ip' => $ip]);
        apiResponse(['ok' => true]);
    }

    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        apiResponse(['error' => 'รหัสอุปกรณ์ไม่ถูกต้อง'], 422);
    }
    $statement = $pdo->prepare('UPDATE devices SET is_active = 0 WHERE id = :id AND is_active = 1');
    $statement->execute(['id' => $id]);
    if (!$statement->rowCount()) {
        apiResponse(['error' => 'ไม่พบอุปกรณ์ที่เปิดใช้งาน'], 404);
    }
    apiAudit($pdo, $user, 'deactivate', 'device', (int) $id);
    apiResponse(['ok' => true]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        apiResponse(['error' => 'IP ซ้ำในเครือข่ายนี้ หรือข้อมูลเครือข่ายไม่มีอยู่'], 409);
    }
    apiDbFailure($exception);
}
