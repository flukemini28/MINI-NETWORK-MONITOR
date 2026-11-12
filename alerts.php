<?php
$currentPage = 'alerts';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-alerts">
          <div class="page-heading"><div><div class="eyebrow">INCIDENT CENTER</div><h1>การแจ้งเตือน</h1><p>ติดตามเหตุการณ์และทำเครื่องหมายรับทราบ</p></div><div class="heading-meta" id="alert-summary"></div></div>
          <div class="panel list-panel"><div class="list-toolbar"><div class="search-box"><span>⌕</span><input id="alert-search" type="search" placeholder="ค้นหาอุปกรณ์ หรือ IP Address"></div><select id="alert-filter" class="select-control"><option value="">ทุกเหตุการณ์</option><option value="unacknowledged">ยังไม่รับทราบ</option><option value="acknowledged">รับทราบแล้ว</option></select></div><div class="alert-list" id="alerts-list"></div></div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
