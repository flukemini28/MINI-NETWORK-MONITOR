<?php
$currentPage = 'devices';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-devices">
          <div class="page-heading"><div><div class="eyebrow">INVENTORY</div><h1>อุปกรณ์</h1><p>จัดการและค้นหารายการอุปกรณ์ที่ต้องการตรวจสอบ</p></div><button class="button button-primary" data-action="add-device"><span class="button-plus">＋</span> เพิ่มอุปกรณ์</button></div>
          <div class="panel list-panel"><div class="list-toolbar"><div class="search-box"><span>⌕</span><input id="device-search" type="search" placeholder="ค้นหาชื่ออุปกรณ์ หรือ IP Address"></div><select id="device-status-filter" class="select-control"><option value="">ทุกสถานะ</option><option value="online">ออนไลน์</option><option value="offline">ออฟไลน์</option><option value="unknown">ไม่ทราบสถานะ</option></select><select id="device-network-filter" class="select-control"><option value="">ทุกเครือข่าย</option></select><span class="result-count" id="device-result-count"></span></div><div class="table-scroll"><table><thead><tr><th>อุปกรณ์</th><th>IP ADDRESS</th><th>ประเภท</th><th>เครือข่าย</th><th>หน่วยงาน / ตำแหน่ง</th><th>สถานะ</th><th>ตอบสนอง</th><th>ตรวจล่าสุด</th><th></th></tr></thead><tbody id="devices-table"></tbody></table></div></div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
