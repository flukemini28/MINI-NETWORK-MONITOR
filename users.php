<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';

requireRole(['admin']);
$pdo = dbConnection();
$currentUser = currentUser();
$error = '';
$notice = isset($_GET['updated']) ? 'บันทึกข้อมูลผู้ใช้แล้ว' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง');
    }

    $action = (string) ($_POST['action'] ?? '');
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($targetId === false || $targetId === null || $targetId < 1) {
        $error = 'กรุณาเลือกบัญชีผู้ใช้ที่ถูกต้อง';
    } else {
        try {
            $pdo->beginTransaction();
            $targetStatement = $pdo->prepare(
                'SELECT id, username, role_id, is_active FROM users WHERE id = :id FOR UPDATE'
            );
            $targetStatement->execute(['id' => $targetId]);
            $target = $targetStatement->fetch();
            if (!$target) {
                throw new RuntimeException('ไม่พบบัญชีผู้ใช้ที่เลือก');
            }

            if ($action === 'update-user') {
                if ((int) $target['id'] === (int) $currentUser['id']) {
                    throw new RuntimeException('ไม่สามารถเปลี่ยนบทบาทหรือปิดบัญชีของตนเองได้');
                }

                $roleKey = (string) ($_POST['role'] ?? '');
                $roleStatement = $pdo->prepare(
                    'SELECT id FROM roles WHERE role_key = :role AND role_key IN (\'admin\', \'operator\', \'viewer\')'
                );
                $roleStatement->execute(['role' => $roleKey]);
                $roleId = $roleStatement->fetchColumn();
                if (!$roleId) {
                    throw new RuntimeException('บทบาทที่เลือกไม่ถูกต้อง');
                }

                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $currentRoleStatement = $pdo->prepare('SELECT role_key FROM roles WHERE id = :id');
                $currentRoleStatement->execute(['id' => $target['role_id']]);
                $currentRole = $currentRoleStatement->fetchColumn();
                if ($currentRole === 'admin' && (string) $roleKey !== 'admin' && (int) $target['is_active'] === 1) {
                    $admins = $pdo->query(
                        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE r.role_key = 'admin' AND u.is_active = 1 FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    if (count($admins) <= 1) {
                        throw new RuntimeException('ต้องมีบัญชีผู้ดูแลที่เปิดใช้งานอย่างน้อยหนึ่งบัญชี');
                    }
                }
                if ($currentRole === 'admin' && $isActive === 0 && (int) $target['is_active'] === 1) {
                    $admins = $pdo->query(
                        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE r.role_key = 'admin' AND u.is_active = 1 FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    if (count($admins) <= 1) {
                        throw new RuntimeException('ต้องมีบัญชีผู้ดูแลที่เปิดใช้งานอย่างน้อยหนึ่งบัญชี');
                    }
                }

                $update = $pdo->prepare(
                    'UPDATE users SET role_id = :role_id, is_active = :is_active, auth_version = auth_version + 1 WHERE id = :id'
                );
                $update->execute(['role_id' => $roleId, 'is_active' => $isActive, 'id' => $targetId]);
                $audit = $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, detail, ip_address)
                     VALUES (:user_id, \'account_updated\', \'user\', :entity_id, :detail, :ip_address)'
                );
                $audit->execute([
                    'user_id' => $currentUser['id'],
                    'entity_id' => $targetId,
                    'detail' => json_encode(['role' => $roleKey, 'is_active' => (bool) $isActive]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } elseif ($action === 'reset-password') {
                if ((int) $target['id'] === (int) $currentUser['id']) {
                    throw new RuntimeException('ใช้คำสั่งเปลี่ยนรหัสผ่านของบัญชีตนเองแทน');
                }

                $password = (string) ($_POST['password'] ?? '');
                $confirmation = (string) ($_POST['password_confirmation'] ?? '');
                if (strlen($password) < 12 || strlen($password) > 1024) {
                    throw new RuntimeException('รหัสผ่านใหม่ต้องมีความยาว 12–1024 ตัวอักษร');
                }
                if (!hash_equals($password, $confirmation)) {
                    throw new RuntimeException('รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
                }

                $update = $pdo->prepare(
                    'UPDATE users SET password_hash = :password_hash, auth_version = auth_version + 1 WHERE id = :id'
                );
                $update->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $targetId,
                ]);
                $audit = $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, detail, ip_address)
                     VALUES (:user_id, \'password_reset\', \'user\', :entity_id, NULL, :ip_address)'
                );
                $audit->execute([
                    'user_id' => $currentUser['id'],
                    'entity_id' => $targetId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } else {
                throw new RuntimeException('คำสั่งไม่ถูกต้อง');
            }

            $pdo->commit();
            header('Location: users.php?updated=1');
            exit;
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $error = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่';
        }
    }
}

$users = $pdo->query(
    'SELECT u.id, u.username, u.email, u.display_name, u.is_active, u.created_at, u.last_login_at, r.role_key
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC, u.id DESC'
)->fetchAll();
$currentPage = 'users';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-users">
          <div class="page-heading"><div><div class="eyebrow">ACCOUNT ADMINISTRATION</div><h1>จัดการผู้ใช้</h1><p>กำหนดบทบาท สถานะบัญชี และช่วยตั้งรหัสผ่านใหม่</p></div></div>
          <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <?php if ($notice !== ''): ?><div class="auth-notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <article class="panel table-panel account-panel">
            <div class="table-panel-heading panel-heading"><div><h2>บัญชีผู้ใช้</h2><p>ผู้ใช้ที่ปิดบัญชีจะไม่สามารถเข้าสู่ระบบหรือใช้ session เดิมได้</p></div><span class="result-count"><?= count($users) ?> บัญชี</span></div>
            <div class="table-scroll">
              <table class="account-table">
                <thead><tr><th>ชื่อ / บัญชี</th><th>อีเมล</th><th>บทบาทและสถานะ</th><th>เข้าสู่ระบบล่าสุด</th><th>ตั้งรหัสผ่านใหม่</th></tr></thead>
                <tbody>
                <?php foreach ($users as $account): ?>
                  <tr>
                    <td><div class="device-name"><strong><?= htmlspecialchars($account['display_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?><?= (int) $account['id'] === (int) $currentUser['id'] ? ' · บัญชีของคุณ' : '' ?></small></div></td>
                    <td><?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?php if ((int) $account['id'] === (int) $currentUser['id']): ?>
                        <span class="account-role"><?= htmlspecialchars($account['role_key'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="account-active">ใช้งาน</span>
                      <?php else: ?>
                        <form method="post" class="account-update-form">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="update-user">
                          <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                          <select name="role" aria-label="บทบาทของ <?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach (['admin' => 'ผู้ดูแลระบบ', 'operator' => 'เจ้าหน้าที่ IT', 'viewer' => 'ผู้ดูข้อมูล'] as $role => $label): ?>
                              <option value="<?= $role ?>"<?= $account['role_key'] === $role ? ' selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                          </select>
                          <label><input type="checkbox" name="is_active" value="1"<?= (int) $account['is_active'] === 1 ? ' checked' : '' ?>> ใช้งาน</label>
                          <button class="button button-secondary" type="submit">บันทึก</button>
                        </form>
                      <?php endif; ?>
                    </td>
                    <td><?= $account['last_login_at'] ? htmlspecialchars($account['last_login_at'], ENT_QUOTES, 'UTF-8') : 'ยังไม่เคยเข้าสู่ระบบ' ?></td>
                    <td>
                      <?php if ((int) $account['id'] !== (int) $currentUser['id']): ?>
                        <form method="post" class="account-reset-form">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="reset-password">
                          <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                          <input class="field-control" type="password" name="password" minlength="12" maxlength="1024" autocomplete="new-password" placeholder="รหัสใหม่ (12 ตัวขึ้นไป)" required>
                          <input class="field-control" type="password" name="password_confirmation" minlength="12" maxlength="1024" autocomplete="new-password" placeholder="ยืนยันรหัสใหม่" required>
                          <button class="button button-secondary" type="submit">ตั้งรหัสผ่าน</button>
                        </form>
                      <?php else: ?>
                        <span class="heading-meta">หากเข้าไม่ได้ ให้ผู้ดูแลอีกบัญชีหรือสร้าง admin สำรองผ่าน CLI</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
          <div class="settings-note">การตั้งรหัสผ่านโดยผู้ดูแลไม่ส่งอีเมล ระบบจะแสดงผลสำเร็จโดยไม่เปิดเผยรหัสผ่าน กรุณาแจ้งรหัสใหม่แก่ผู้ใช้ผ่านช่องทางที่ปลอดภัย และอย่าใช้รหัสเดียวกับบัญชีอื่น</div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
