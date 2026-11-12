<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php <username> <email>\n");
    exit(1);
}

[$script, $username, $email] = $argv;
$displayName = trim((string) readline('Admin display name: '));
$password = (string) readline('Admin password (minimum 12 characters): ');

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Username must be 3-50 characters: letters, numbers, dot, underscore, or hyphen.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    fwrite(STDERR, "A valid email address is required.\n");
    exit(1);
}
if ($displayName === '' || strlen($displayName) > 120 || strlen($password) < 12) {
    fwrite(STDERR, "Display name is required and password must contain at least 12 characters.\n");
    exit(1);
}

try {
    $pdo = dbConnection();
    $roleStatement = $pdo->prepare("SELECT id FROM roles WHERE role_key = 'admin'");
    $roleStatement->execute();
    $roleId = $roleStatement->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException('Admin role is missing. Import database/schema.sql first.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO users (role_id, username, email, display_name, password_hash) VALUES (:role_id, :username, :email, :display_name, :password_hash)'
    );
    $statement->execute([
        'role_id' => $roleId,
        'username' => $username,
        'email' => strtolower($email),
        'display_name' => $displayName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Administrator account created.\n");
} catch (PDOException $exception) {
    fwrite(STDERR, "Could not create administrator. Username or email may already exist, or the database is unavailable.\n");
    error_log($exception->getMessage());
    exit(1);
}
