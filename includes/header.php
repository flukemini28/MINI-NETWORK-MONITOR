<?php
$pages = [
    'index' => ['title' => 'ภาพรวมระบบ', 'file' => 'index.php', 'icon' => '◫'],
    'devices' => ['title' => 'อุปกรณ์', 'file' => 'devices.php', 'icon' => '▦'],
    'network' => ['title' => 'เครือข่าย', 'file' => 'network.php', 'icon' => '⌘'],
    'history' => ['title' => 'ประวัติการตรวจ', 'file' => 'history.php', 'icon' => '◷'],
    'alerts' => ['title' => 'การแจ้งเตือน', 'file' => 'alerts.php', 'icon' => '♧'],
];
$title = $pages[$currentPage]['title'] ?? 'ภาพรวมระบบ';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f5f7fb">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Mini Network Monitor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="index.php" aria-label="Mini Network Monitor">
        <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17.5a11 11 0 0 1 16 0M7 14a7 7 0 0 1 10 0m-7-3.5a3 3 0 0 1 4 0M12 19h.01"/></svg></span>
        <span class="brand-copy"><strong>mini<span>monitor</span></strong><small>NETWORK OPERATIONS</small></span>
      </a>
      <div class="workspace-label">WORKSPACE</div>
      <nav class="main-nav" aria-label="เมนูหลัก">
        <?php foreach ($pages as $key => $page): ?>
          <a class="nav-link<?= $currentPage === $key ? ' active' : '' ?>" href="<?= htmlspecialchars($page['file'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="nav-icon"><?= htmlspecialchars($page['icon'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($key === 'devices'): ?><span class="nav-count" id="device-nav-count">0</span><?php endif; ?>
            <?php if ($key === 'alerts'): ?><span class="nav-dot" id="alert-nav-dot"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-bottom">
        <div class="worker-card">
          <div class="worker-top"><span class="worker-indicator"></span><span>โหมดตัวอย่าง</span><span class="worker-tag">DEMO</span></div>
          <p>ข้อมูลตัวอย่างในเบราว์เซอร์<br>ยังไม่ได้เชื่อมต่อระบบ Ping</p>
        </div>
        <a class="nav-link settings-link<?= $currentPage === 'settings' ? ' active' : '' ?>" href="settings.php"><span class="nav-icon">⚙</span><span>การตั้งค่า</span></a>
        <div class="user-card"><div class="avatar">IT</div><div class="user-info"><strong>เจ้าหน้าที่ระบบ</strong><small>ผู้ดูแลระบบ</small></div><span class="user-menu">•••</span></div>
      </div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <button class="mobile-menu" id="mobile-menu" aria-label="เปิดเมนู">☰</button>
        <div class="breadcrumbs"><span>Network</span><span class="crumb-slash">/</span><strong id="breadcrumb-current"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="topbar-right">
          <span class="last-updated"><span class="live-dot"></span>อัปเดตล่าสุด <strong id="last-updated">—</strong></span>
          <a class="icon-button notification-button" href="alerts.php" aria-label="การแจ้งเตือน">♧<span class="notification-badge" id="notification-count">0</span></a>
          <div class="top-avatar">IT</div>
        </div>
      </header>
      <div class="page-wrap">
