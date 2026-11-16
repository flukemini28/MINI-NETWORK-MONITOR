<?php
declare(strict_types=1);

function loginRateLimitBuckets(string $ipAddress, string $identifier): array
{
    $ipHash = hash('sha256', "ip\0" . $ipAddress);
    $identityHash = hash('sha256', "identity\0" . $ipAddress . "\0" . strtolower(trim($identifier)));

    return [
        ['hash' => $ipHash, 'limit' => 30],
        ['hash' => $identityHash, 'limit' => 10],
    ];
}

function loginRateLimitIsBlocked(PDO $pdo, string $ipAddress, string $identifier): bool
{
    $buckets = loginRateLimitBuckets($ipAddress, $identifier);
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO login_rate_limits (bucket_hash, failed_attempts, window_started_at)
             VALUES (:bucket_hash, 0, NOW())'
        );
        foreach ($buckets as $bucket) {
            $insert->execute(['bucket_hash' => $bucket['hash']]);
        }

        $select = $pdo->prepare(
            'SELECT bucket_hash, locked_until > NOW() AS is_locked,
                    window_started_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AS window_expired
             FROM login_rate_limits WHERE bucket_hash = :bucket_hash FOR UPDATE'
        );
        $reset = $pdo->prepare(
            'UPDATE login_rate_limits SET failed_attempts = 0, window_started_at = NOW(), locked_until = NULL
             WHERE bucket_hash = :bucket_hash'
        );
        $blocked = false;
        foreach ($buckets as $bucket) {
            $select->execute(['bucket_hash' => $bucket['hash']]);
            $row = $select->fetch();
            if ($row && (int) $row['is_locked'] === 1) {
                $blocked = true;
            } elseif ($row && (int) $row['window_expired'] === 1) {
                $reset->execute(['bucket_hash' => $bucket['hash']]);
            }
        }
        $pdo->commit();
        return $blocked;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function loginRateLimitRecordFailure(PDO $pdo, string $ipAddress, string $identifier): void
{
    $buckets = loginRateLimitBuckets($ipAddress, $identifier);
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO login_rate_limits (bucket_hash, failed_attempts, window_started_at)
             VALUES (:bucket_hash, 0, NOW())'
        );
        foreach ($buckets as $bucket) {
            $insert->execute(['bucket_hash' => $bucket['hash']]);
        }

        $select = $pdo->prepare(
            'SELECT failed_attempts, window_started_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AS window_expired
             FROM login_rate_limits WHERE bucket_hash = :bucket_hash FOR UPDATE'
        );
        $update = $pdo->prepare(
            'UPDATE login_rate_limits
             SET failed_attempts = :attempts,
                 window_started_at = IF(:expired = 1, NOW(), window_started_at),
                 locked_until = IF(:locked = 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
             WHERE bucket_hash = :bucket_hash'
        );
        foreach ($buckets as $bucket) {
            $select->execute(['bucket_hash' => $bucket['hash']]);
            $row = $select->fetch();
            $expired = !$row || (int) $row['window_expired'] === 1;
            $attempts = $expired ? 1 : (int) $row['failed_attempts'] + 1;
            $update->execute([
                'attempts' => $attempts,
                'expired' => $expired ? 1 : 0,
                'locked' => $attempts >= $bucket['limit'] ? 1 : 0,
                'bucket_hash' => $bucket['hash'],
            ]);
        }
        $pdo->exec('DELETE FROM login_rate_limits WHERE window_started_at < DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 100');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function loginRateLimitClearIdentity(PDO $pdo, string $ipAddress, string $identifier): void
{
    $buckets = loginRateLimitBuckets($ipAddress, $identifier);
    $statement = $pdo->prepare('DELETE FROM login_rate_limits WHERE bucket_hash = :bucket_hash');
    $statement->execute(['bucket_hash' => $buckets[1]['hash']]);

    $clearUnusedIp = $pdo->prepare(
        'DELETE FROM login_rate_limits WHERE bucket_hash = :bucket_hash AND failed_attempts = 0'
    );
    $clearUnusedIp->execute(['bucket_hash' => $buckets[0]['hash']]);
}
