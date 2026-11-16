<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/login_rate_limit.php';

startAppSession();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$notice = isset($_GET['registered']) ? 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ' : '';
$next = $_GET['next'] ?? 'index.php';
$allowedReturnPages = ['index.php', 'devices.php', 'network.php', 'history.php', 'alerts.php', 'settings.php', 'users.php'];
if (!is_string($next) || !in_array($next, $allowedReturnPages, true)) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = $_POST['next'] ?? 'index.php';
    if (!is_string($next) || !in_array($next, $allowedReturnPages, true)) {
        $next = 'index.php';
    }

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = 'กรุณากรอกชื่อผู้ใช้หรืออีเมล และรหัสผ่าน';
        } else {
            try {
                $pdo = dbConnection();
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                if (loginRateLimitIsBlocked($pdo, $ipAddress, $identifier)) {
                    $error = 'เข้าสู่ระบบไม่สำเร็จหลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่';
                } else {
                    $statement = $pdo->prepare(
                        'SELECT u.id, u.username, u.display_name, u.password_hash, u.auth_version, u.is_active, r.role_key
                         FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE u.username = :username OR u.email = :email
                         LIMIT 1'
                    );
                    $statement->execute([
                        'username' => $identifier,
                        'email' => strtolower($identifier),
                    ]);
                    $user = $statement->fetch();

                    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
                        loginRateLimitRecordFailure($pdo, $ipAddress, $identifier);
                        $error = 'ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง';
                    } else {
                        loginRateLimitClearIdentity($pdo, $ipAddress, $identifier);
                        session_regenerate_id(true);
                        $_SESSION['user'] = [
                            'id' => (int) $user['id'],
                            'username' => $user['username'],
                            'display_name' => $user['display_name'],
                            'role' => $user['role_key'],
                            'auth_version' => (int) $user['auth_version'],
                        ];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                        $update->execute(['id' => $user['id']]);
                        header('Location: ' . $next);
                        exit;
                    }
                }
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบการตั้งค่าและลองใหม่';
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ | Mini Network Monitor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <a class="brand auth-brand" href="login.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17.5a11 11 0 0 1 16 0M7 14a7 7 0 0 1 10 0m-7-3.5a3 3 0 0 1 4 0M12 19h.01"/></svg></span>
      <span class="brand-copy"><strong>mini<span>monitor</span></strong><small>NETWORK OPERATIONS</small></span>
    </a>
    <div class="eyebrow">WELCOME BACK</div>
    <h1>เข้าสู่ระบบ</h1>
    <p class="auth-description">เข้าสู่ระบบเพื่อติดตามอุปกรณ์เครือข่าย</p>
    <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="auth-notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="login.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="identifier">ชื่อผู้ใช้หรืออีเมล</label>
      <input class="field-control" id="identifier" name="identifier" autocomplete="username" required value="<?= htmlspecialchars((string) ($_POST['identifier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="password">รหัสผ่าน</label>
      <input class="field-control" id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="button button-primary auth-submit" type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="auth-switch">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
  </main>
</body>
</html>
