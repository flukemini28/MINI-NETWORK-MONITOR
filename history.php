<?php
$currentPage = 'history';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-history">
          <div class="page-heading"><div><div class="eyebrow">MONITORING LOGS</div><h1>ประวัติการตรวจ</h1><p>ค้นหาผลตรวจและเหตุการณ์ย้อนหลัง</p></div><button class="button button-secondary" data-action="export-history">↓ <span>ส่งออกรายงาน</span></button></div>
          <div class="panel list-panel"><div class="list-toolbar"><div class="search-box"><span>⌕</span><input id="history-search" type="search" placeholder="ค้นหาชื่ออุปกรณ์ หรือ IP Address"></div><select id="history-status-filter" class="select-control"><option value="">ทุกสถานะ</option><option value="online">ออนไลน์</option><option value="offline">ออฟไลน์</option><option value="unknown">ไม่ทราบสถานะ</option></select><input id="history-date-filter" class="select-control date-control" type="date"><span class="result-count" id="history-result-count"></span></div><div class="table-scroll"><table><thead><tr><th>อุปกรณ์</th><th>IP ADDRESS</th><th>ผลตรวจ</th><th>เวลาตอบสนอง</th><th>วันและเวลาที่ตรวจ</th><th>รายละเอียด</th></tr></thead><tbody id="history-table"></tbody></table></div></div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
