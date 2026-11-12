# Mini Network Monitor

## โครงสร้างหน้าเว็บ

- `index.php` — Dashboard
- `devices.php` — จัดการอุปกรณ์
- `network.php` — จัดการเครือข่าย
- `history.php` — ประวัติการตรวจ
- `alerts.php` — การแจ้งเตือน
- `settings.php` — การตั้งค่า
- `includes/header.php` และ `includes/footer.php` — โครงหน้าร่วม
- `styles.css` และ `app.js` — สไตล์และพฤติกรรมหน้าเว็บ

แต่ละหน้ามีไฟล์ PHP แยกกันและใช้ Header/Footer ร่วม เพื่อให้เมนูและรูปแบบสอดคล้องกัน ข้อมูลต้นแบบยังเก็บไว้ใน Local Storage ของเบราว์เซอร์

## เปิดใช้งาน

วางโฟลเดอร์โครงการไว้ใน `C:\xampp\htdocs\mini-network-monitor` แล้วเปิด XAMPP Control Panel และเริ่ม Apache จากนั้นเข้า <http://localhost/mini-network-monitor/>  
ต้องใช้ Apache/PHP เพื่อประมวลผลไฟล์ `.php`; Python SimpleHTTPServer ไม่สามารถรันหน้า PHP ได้

## ข้อจำกัดของต้นแบบ

หน้า PHP เหล่านี้เป็นการแยกหน้า UI เท่านั้น ขณะนี้ยังไม่มีระบบบัญชี ฐานข้อมูล PHP API หรือการตรวจ ICMP Ping จริง สถานะอุปกรณ์เป็นข้อมูลตัวอย่าง และการแก้ไขข้อมูลจะถูกเก็บใน Local Storage ของเบราว์เซอร์แต่ละเครื่อง
