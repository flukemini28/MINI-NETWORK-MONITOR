<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireLogin();
$method = apiRequireMethod('GET', 'POST', 'PUT', 'DELETE');

try {
    $pdo = dbConnection();
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT n.id, n.name, n.network_cidr AS cidr, n.gateway_ip AS gateway, n.department,
                    n.is_active AS active, COUNT(d.id) AS device_count
             FROM networks n LEFT JOIN devices d ON d.network_id = n.id AND d.is_active = 1
             GROUP BY n.id ORDER BY n.name'
        );
        apiResponse(['networks' => $statement->fetchAll()]);
    }

    $user = apiRequireRole('admin', 'operator');
    $input = apiInput();
    if ($method === 'POST' || $method === 'PUT') {
        $id = $method === 'PUT' ? filter_var($input['id'] ?? null, FILTER_VALIDATE_INT) : null;
        $name = trim((string) ($input['name'] ?? ''));
        $cidr = trim((string) ($input['cidr'] ?? ''));
        $gateway = trim((string) ($input['gateway'] ?? ''));
        $department = trim((string) ($input['department'] ?? ''));
        $cidrParts = explode('/', $cidr);
        $validCidr = count($cidrParts) === 2
            && filter_var($cidrParts[0], FILTER_VALIDATE_IP) !== false
            && ctype_digit($cidrParts[1])
            && (int) $cidrParts[1] <= (str_contains($cidrParts[0], ':') ? 128 : 32);

        if (($method === 'PUT' && !$id) || $name === '' || strlen($name) > 100 || !$validCidr) {
            apiResponse(['error' => 'กรุณากรอกชื่อเครือข่ายและ Network/CIDR ให้ถูกต้อง'], 422);
        }
        if ($gateway !== '' && filter_var($gateway, FILTER_VALIDATE_IP) === false) {
            apiResponse(['error' => 'Gateway ต้องเป็น IP Address ที่ถูกต้อง'], 422);
        }
        if (strlen($department) > 120) {
            apiResponse(['error' => 'ชื่อหน่วยงานยาวเกินกำหนด'], 422);
        }

        if ($method === 'POST') {
            $statement = $pdo->prepare(
                'INSERT INTO networks (name, network_cidr, gateway_ip, department, created_by)
                 VALUES (:name, :cidr, :gateway, :department, :created_by)'
            );
            $statement->execute([
                'name' => $name, 'cidr' => $cidr, 'gateway' => $gateway ?: null,
                'department' => $department ?: null, 'created_by' => $user['id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            apiAudit($pdo, $user, 'create', 'network', $id, ['name' => $name, 'cidr' => $cidr]);
            apiResponse(['id' => $id], 201);
        }

        $statement = $pdo->prepare(
            'UPDATE networks SET name = :name, network_cidr = :cidr, gateway_ip = :gateway, department = :department
             WHERE id = :id AND is_active = 1'
        );
        $statement->execute([
            'name' => $name, 'cidr' => $cidr, 'gateway' => $gateway ?: null,
            'department' => $department ?: null, 'id' => $id,
        ]);
        if (!$statement->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM networks WHERE id = :id AND is_active = 1');
            $exists->execute(['id' => $id]);
            if (!$exists->fetchColumn()) {
                apiResponse(['error' => 'ไม่พบเครือข่าย'], 404);
            }
        }
        apiAudit($pdo, $user, 'update', 'network', (int) $id, ['name' => $name, 'cidr' => $cidr]);
        apiResponse(['ok' => true]);
    }

    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        apiResponse(['error' => 'รหัสเครือข่ายไม่ถูกต้อง'], 422);
    }
    $statement = $pdo->prepare('UPDATE networks SET is_active = 0 WHERE id = :id AND is_active = 1');
    $deviceCheck = $pdo->prepare('SELECT COUNT(*) FROM devices WHERE network_id = :id AND is_active = 1');
    $deviceCheck->execute(['id' => $id]);
    if ((int) $deviceCheck->fetchColumn() > 0) {
        apiResponse(['error' => 'กรุณาปิดใช้งานอุปกรณ์ในเครือข่ายนี้ก่อน'], 409);
    }
    $statement->execute(['id' => $id]);
    if (!$statement->rowCount()) {
        apiResponse(['error' => 'ไม่พบเครือข่ายที่เปิดใช้งาน'], 404);
    }
    apiAudit($pdo, $user, 'deactivate', 'network', (int) $id);
    apiResponse(['ok' => true]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        apiResponse(['error' => 'ชื่อและ CIDR นี้ซ้ำ หรือมีอุปกรณ์อ้างอิงเครือข่ายนี้'], 409);
    }
    apiDbFailure($exception);
}
