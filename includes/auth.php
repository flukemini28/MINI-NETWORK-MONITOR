<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';

startAppSession();

function requireLogin(): void
{
    static $sessionChecked = false;
    if ($sessionChecked && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        return;
    }

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $requestedPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        $allowedReturnPages = ['index.php', 'devices.php', 'network.php', 'history.php', 'alerts.php', 'settings.php', 'users.php'];
        $returnPage = in_array($requestedPage, $allowedReturnPages, true) ? $requestedPage : 'index.php';
        header('Location: login.php?next=' . rawurlencode($returnPage));
        exit;
    }

    $userId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId === null) {
        unset($_SESSION['user']);
        header('Location: login.php');
        exit;
    }

    $statement = dbConnection()->prepare(
        'SELECT u.username, u.display_name, u.auth_version, r.role_key
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.is_active = 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        header('Location: login.php');
        exit;
    }
    if ((int) ($_SESSION['user']['auth_version'] ?? -1) !== (int) $user['auth_version']) {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        header('Location: login.php');
        exit;
    }

    $_SESSION['user'] = [
        'id' => $userId,
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role_key'],
        'auth_version' => (int) $user['auth_version'],
    ];
    $sessionChecked = true;
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    $role = $_SESSION['user']['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

function currentUser(): array
{
    requireLogin();
    return $_SESSION['user'];
}
