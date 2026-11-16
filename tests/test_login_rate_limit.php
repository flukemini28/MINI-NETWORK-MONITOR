<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this test from PHP CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/login_rate_limit.php';

$pdo = dbConnection();
$ipAddress = '192.0.2.254';
$identifier = 'rate-limit-test-' . bin2hex(random_bytes(8));
$buckets = loginRateLimitBuckets($ipAddress, $identifier);
$cleanBuckets = [];

try {
    if (loginRateLimitIsBlocked($pdo, $ipAddress, $identifier)) {
        throw new RuntimeException('A new identity was unexpectedly blocked.');
    }

    for ($attempt = 0; $attempt < 9; $attempt++) {
        loginRateLimitRecordFailure($pdo, $ipAddress, $identifier);
    }
    if (loginRateLimitIsBlocked($pdo, $ipAddress, $identifier)) {
        throw new RuntimeException('The identity was blocked before reaching the configured limit.');
    }

    loginRateLimitRecordFailure($pdo, $ipAddress, $identifier);
    if (!loginRateLimitIsBlocked($pdo, $ipAddress, $identifier)) {
        throw new RuntimeException('The identity was not blocked at the configured limit.');
    }

    $expire = $pdo->prepare(
        'UPDATE login_rate_limits
         SET window_started_at = DATE_SUB(NOW(), INTERVAL 16 MINUTE), locked_until = NULL
         WHERE bucket_hash = :bucket_hash'
    );
    $expire->execute(['bucket_hash' => $buckets[1]['hash']]);
    if (loginRateLimitIsBlocked($pdo, $ipAddress, $identifier)) {
        throw new RuntimeException('An expired identity lock was not cleared.');
    }

    $cleanIp = '192.0.2.253';
    $cleanIdentifier = $identifier . '-success';
    $cleanBuckets = loginRateLimitBuckets($cleanIp, $cleanIdentifier);
    loginRateLimitIsBlocked($pdo, $cleanIp, $cleanIdentifier);
    loginRateLimitClearIdentity($pdo, $cleanIp, $cleanIdentifier);
    $check = $pdo->prepare('SELECT COUNT(*) FROM login_rate_limits WHERE bucket_hash IN (:ip_hash, :identity_hash)');
    $check->execute([
        'ip_hash' => $cleanBuckets[0]['hash'],
        'identity_hash' => $cleanBuckets[1]['hash'],
    ]);
    if ((int) $check->fetchColumn() !== 0) {
        throw new RuntimeException('Successful login left unused rate-limit buckets behind.');
    }

    fwrite(STDOUT, "Login rate-limit threshold and expiry tests passed.\n");
} finally {
    $delete = $pdo->prepare('DELETE FROM login_rate_limits WHERE bucket_hash = :bucket_hash');
    foreach (array_merge($buckets, $cleanBuckets) as $bucket) {
        $delete->execute(['bucket_hash' => $bucket['hash']]);
    }
}
