<?php
$currentPage = 'index';
require __DIR__ . '/includes/header.php';
?>
        <section class="page-section active" id="page-overview">
          <div class="page-heading">
            <div><div class="eyebrow">NETWORK OVERVIEW</div><h1>ภาพรวมระบบ</h1><p>ติดตามสถานะอุปกรณ์เครือข่ายของคุณได้ในที่เดียว</p></div>
            <button class="button button-primary" data-action="add-device"><span class="button-plus">＋</span> เพิ่มอุปกรณ์</button>
          </div>
          <div class="demo-banner"><span class="banner-icon">ⓘ</span><span><strong>นี่คือข้อมูลตัวอย่าง</strong> สถานะที่แสดงยังไม่ได้ตรวจด้วย ICMP Ping จริง เชื่อมต่อ Monitor Worker เพื่อใช้งานจริง</span><button class="banner-close" id="banner-close" aria-label="ปิด">×</button></div>
          <div class="stat-grid">
            <article class="stat-card"><div class="stat-top"><span>อุปกรณ์ทั้งหมด</span><span class="stat-icon total-icon">▦</span></div><div class="stat-value" id="stat-total">0</div><div class="stat-foot"><span class="stat-caption">รายการที่ลงทะเบียน</span></div></article>
            <article class="stat-card"><div class="stat-top"><span>ออนไลน์</span><span class="stat-icon online-icon">↗</span></div><div class="stat-value" id="stat-online">0</div><div class="stat-foot"><span class="status-dot online"></span><span class="stat-caption">ตอบสนองปกติ</span></div></article>
            <article class="stat-card"><div class="stat-top"><span>ออฟไลน์</span><span class="stat-icon offline-icon">↘</span></div><div class="stat-value" id="stat-offline">0</div><div class="stat-foot"><span class="status-dot offline"></span><span class="stat-caption">ไม่ตอบสนอง</span></div></article>
            <article class="stat-card"><div class="stat-top"><span>ไม่ทราบสถานะ</span><span class="stat-icon unknown-icon">?</span></div><div class="stat-value" id="stat-unknown">0</div><div class="stat-foot"><span class="status-dot unknown"></span><span class="stat-caption">รอผลตรวจสอบ</span></div></article>
          </div>
          <div class="dashboard-grid">
            <article class="panel status-panel">
              <div class="panel-heading"><div><h2>สถานะตามเครือข่าย</h2><p>สรุปสถานะอุปกรณ์แยกตามวงเครือข่าย</p></div><a class="text-button" href="network.php">จัดการเครือข่าย <span>→</span></a></div>
              <div class="network-summary" id="network-summary"></div>
            </article>
            <article class="panel availability-panel">
              <div class="panel-heading"><div><h2>สัดส่วนสถานะ</h2><p>จากอุปกรณ์ที่ลงทะเบียนทั้งหมด</p></div><span class="subtle-period">ขณะนี้</span></div>
              <div class="donut-wrap"><div class="donut" id="status-donut"><div class="donut-center"><strong id="donut-total">0</strong><span>อุปกรณ์</span></div></div></div>
              <div class="donut-legend"><span><i class="legend-dot green"></i>ออนไลน์ <b id="legend-online">0</b></span><span><i class="legend-dot red"></i>ออฟไลน์ <b id="legend-offline">0</b></span><span><i class="legend-dot gray"></i>ไม่ทราบ <b id="legend-unknown">0</b></span></div>
            </article>
          </div>
          <article class="panel table-panel">
            <div class="panel-heading table-panel-heading"><div><h2>อุปกรณ์ที่ต้องติดตาม</h2><p>รายการอุปกรณ์ที่ไม่ออนไลน์หรือยังไม่มีผลตรวจ</p></div><a class="text-button" href="devices.php">ดูอุปกรณ์ทั้งหมด <span>→</span></a></div>
            <div class="table-scroll"><table><thead><tr><th>อุปกรณ์</th><th>IP ADDRESS</th><th>เครือข่าย</th><th>สถานะ</th><th>ตอบสนอง</th><th>ตรวจล่าสุด</th><th></th></tr></thead><tbody id="attention-table"></tbody></table></div>
          </article>
          <div class="dashboard-bottom">
            <article class="panel activity-panel"><div class="panel-heading"><div><h2>เหตุการณ์ล่าสุด</h2><p>การเปลี่ยนแปลงสถานะของอุปกรณ์</p></div><a class="text-button" href="history.php">ดูประวัติ <span>→</span></a></div><div class="activity-list" id="activity-list"></div></article>
            <article class="panel quick-panel"><div class="panel-heading"><div><h2>ทางลัด</h2><p>จัดการระบบอย่างรวดเร็ว</p></div></div><button class="quick-link" data-action="add-device"><span class="quick-icon blue">＋</span><span><strong>เพิ่มอุปกรณ์ใหม่</strong><small>เพิ่ม IP Address ที่ต้องการตรวจสอบ</small></span><b>→</b></button><a class="quick-link" href="network.php"><span class="quick-icon purple">⌘</span><span><strong>จัดการเครือข่าย</strong><small>ตั้งค่า LAN และ VLAN</small></span><b>→</b></a><button class="quick-link" data-action="export"><span class="quick-icon orange">↓</span><span><strong>ส่งออกรายงาน</strong><small>ดาวน์โหลดรายการอุปกรณ์เป็น CSV</small></span><b>→</b></button></article>
          </div>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
