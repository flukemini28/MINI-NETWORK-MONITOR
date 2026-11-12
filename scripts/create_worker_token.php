<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

try {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $statement = dbConnection()->prepare(
        "INSERT INTO app_settings (setting_key, setting_value)
         VALUES ('worker_token_hash', :token_hash)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $statement->execute(['token_hash' => $hash]);
    fwrite(STDOUT, "Worker token (shown once):\n{$token}\n");
    fwrite(STDOUT, "Set MNM_WORKER_TOKEN to this value in the worker process.\n");
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    fwrite(STDERR, "Could not create worker token. Check the database connection.\n");
    exit(1);
}
