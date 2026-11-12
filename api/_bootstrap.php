<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function apiResponse(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiInput(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        apiResponse(['error' => 'รูปแบบ JSON ไม่ถูกต้อง'], 400);
    }

    return $decoded;
}

function apiRequireMethod(string ...$methods): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        apiResponse(['error' => 'ไม่รองรับคำขอประเภทนี้'], 405);
    }

    if ($method !== 'GET' && $method !== 'HEAD') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!verifyCsrfToken($token)) {
            apiResponse(['error' => 'CSRF token ไม่ถูกต้องหรือหมดอายุ'], 403);
        }
    }

    return $method;
}

function apiRequireRole(string ...$roles): array
{
    $user = currentUser();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        apiResponse(['error' => 'ไม่มีสิทธิ์ดำเนินการนี้'], 403);
    }

    return $user;
}

function apiAudit(PDO $pdo, array $user, string $action, string $entityType, ?int $entityId, array $detail = []): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, detail, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :detail, :ip_address)'
    );
    $statement->execute([
        'user_id' => $user['id'],
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function ipMatchesCidr(string $address, string $cidr): bool
{
    $parts = explode('/', $cidr);
    if (count($parts) !== 2 || !ctype_digit($parts[1])) {
        return false;
    }

    $ip = inet_pton($address);
    $network = inet_pton($parts[0]);
    if ($ip === false || $network === false || strlen($ip) !== strlen($network)) {
        return false;
    }

    $prefix = (int) $parts[1];
    $maxPrefix = strlen($ip) * 8;
    if ($prefix > $maxPrefix) {
        return false;
    }

    $wholeBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if (substr($ip, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($ip[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
}

function apiDbFailure(PDOException $exception): never
{
    error_log($exception->getMessage());
    apiResponse(['error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], 500);
}
