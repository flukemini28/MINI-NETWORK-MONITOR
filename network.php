<?php
$currentPage = 'network';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-networks">
          <div class="page-heading"><div><div class="eyebrow">NETWORK CONFIGURATION</div><h1>เครือข่าย</h1><p>จัดการวง LAN, VLAN และข้อมูล Gateway</p></div><button class="button button-primary" data-action="add-network"><span class="button-plus">＋</span> เพิ่มเครือข่าย</button></div>
          <div class="network-card-grid" id="networks-grid"></div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
