<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

startAppSession();

function requireLogin(): void
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $requestedPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        $allowedReturnPages = ['index.php', 'devices.php', 'network.php', 'history.php', 'alerts.php', 'settings.php'];
        $returnPage = in_array($requestedPage, $allowedReturnPages, true) ? $requestedPage : 'index.php';
        header('Location: login.php?next=' . rawurlencode($returnPage));
        exit;
    }
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
