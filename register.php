<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/database.php';

startAppSession();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$displayName = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif ($displayName === '' || strlen($displayName) > 120) {
        $error = 'กรุณากรอกชื่อที่แสดง (ไม่เกิน 120 ตัวอักษร)';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $error = 'ชื่อผู้ใช้ต้องยาว 3–50 ตัว และใช้ a-z, 0-9, จุด, ขีดกลาง หรือขีดล่าง';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
    } elseif (strlen($password) < 5 || strlen($password) > 1024) {
        $error = 'รหัสผ่านต้องมีความยาว 5 ตัวอักษร';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        try {
            $pdo = dbConnection();
            $roleStatement = $pdo->prepare("SELECT id FROM roles WHERE role_key = 'viewer'");
            $roleStatement->execute();
            $roleId = $roleStatement->fetchColumn();
            if (!$roleId) {
                throw new RuntimeException('Viewer role is missing. Import database/schema.sql first.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO users (role_id, username, email, display_name, password_hash)
                 VALUES (:role_id, :username, :email, :display_name, :password_hash)'
            );
            $statement->execute([
                'role_id' => $roleId,
                'username' => $username,
                'email' => $email,
                'display_name' => $displayName,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            if ($exception->getCode() === '23000') {
                $error = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
            } else {
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
  <title>สมัครสมาชิก | Mini Network Monitor</title>
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
    <div class="eyebrow">CREATE ACCOUNT</div>
    <h1>สมัครสมาชิก</h1>
    <p class="auth-description">บัญชีใหม่จะได้รับสิทธิ์ผู้ดูข้อมูล</p>
    <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="register.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="display_name">ชื่อที่แสดง</label>
      <input class="field-control" id="display_name" name="display_name" maxlength="120" autocomplete="name" required value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="username">ชื่อผู้ใช้</label>
      <input class="field-control" id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="email">อีเมล</label>
      <input class="field-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
      <label class="form-label" for="password">รหัสผ่าน (5 ตัวอักษร)</label>
      <input class="field-control" id="password" name="password" type="password" minlength="5"autocomplete="new-password" required>
      <label class="form-label" for="password_confirmation">ยืนยันรหัสผ่าน</label>
      <input class="field-control" id="password_confirmation" name="password_confirmation" type="password" minlength="5"autocomplete="new-password" required>
      <button class="button button-primary auth-submit" type="submit">สร้างบัญชี</button>
    </form>
    <p class="auth-switch">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </main>
</body>
</html>
