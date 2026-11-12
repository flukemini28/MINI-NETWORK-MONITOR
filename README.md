# Mini Network Monitor

## โครงสร้าง

- `index.php`, `devices.php`, `network.php`, `history.php`, `alerts.php`, `settings.php` — หน้าระบบ (ต้องเข้าสู่ระบบ)
- `login.php`, `register.php`, `logout.php` — เข้าสู่ระบบ สมัครสมาชิก และออกจากระบบ
- `database/schema.sql` — สร้างฐานข้อมูลและตาราง
- `includes/database.php`, `includes/session.php`, `includes/auth.php` — PDO, session และการยืนยันตัวตน
- `scripts/create_admin.php` — สร้างบัญชีผู้ดูแลจาก PHP CLI เท่านั้น

## ตั้งค่าฐานข้อมูลบน XAMPP

1. เริ่ม Apache และ MySQL ใน XAMPP Control Panel
2. เปิด phpMyAdmin แล้ว Import ไฟล์ `database/schema.sql`
3. ค่าตั้งต้นสำหรับ XAMPP ในเครื่องคือ host `127.0.0.1`, port `3306`, database `mini_network_monitor`, user `root` และรหัสผ่านว่าง
4. ถ้าใช้ค่าอื่น ให้กำหนด environment variables `MNM_DB_HOST`, `MNM_DB_PORT`, `MNM_DB_NAME`, `MNM_DB_USER`, `MNM_DB_PASSWORD` ให้ Apache/PHP เห็นก่อนเริ่มบริการ
5. วางโปรเจกต์ใน `C:\xampp\htdocs\mini-network-monitor` แล้วเข้า `http://localhost/mini-network-monitor/`

## สร้างบัญชีผู้ดูแล

เปิด PowerShell ที่โฟลเดอร์โปรเจกต์ แล้วรัน:

```powershell
C:\xampp\php\php.exe scripts\create_admin.php admin admin@example.local
```

สคริปต์จะถามชื่อที่แสดงและรหัสผ่าน และทำงานจาก CLI เท่านั้น อย่าเปิดรับระบบจากเครือข่ายก่อนตั้งค่าฐานข้อมูลและสร้างผู้ดูแลเรียบร้อย

## สมัครสมาชิกและบทบาท

ผู้ใช้สมัครผ่าน `register.php` ได้ โดยบัญชีที่สมัครใหม่จะได้บทบาท `viewer` เท่านั้น การยกระดับเป็น `operator` หรือ `admin` ต้องทำโดยผู้ดูแลผ่านฐานข้อมูล/เครื่องมือจัดการบัญชี ไม่มีการรับ role จากฟอร์มสมัครสมาชิก รหัสผ่านเก็บด้วย `password_hash()` และหน้าแบบฟอร์มใช้ CSRF token

บทบาทใน schema: `admin` (ผู้ดูแลระบบ), `operator` (เจ้าหน้าที่ IT), `viewer` (ผู้ดูข้อมูล)

## แบบจำลองข้อมูล

- `roles` และ `users` — บทบาทและบัญชีผู้ใช้
- `networks` — CIDR, Gateway และหน่วยงาน
- `devices` — IP, ประเภท, เครือข่าย และสถานะล่าสุด
- `probe_checks` — ผล Ping แต่ละครั้ง
- `status_events` และ `alerts` — การเปลี่ยนสถานะและการรับทราบเหตุการณ์
- `app_settings` และ `worker_heartbeats` — ค่าระบบและสถานะ Worker
- `audit_logs` — บันทึกการกระทำในระบบ

## ข้อจำกัดปัจจุบัน

ฐานข้อมูลและระบบบัญชีถูกเพิ่มแล้ว แต่หน้าจัดการอุปกรณ์/เครือข่าย/ประวัติยังใช้ข้อมูลตัวอย่างใน Local Storage ไม่ได้อ่านหรือเขียนตารางระบบ ส่วนตรวจสิทธิ์ตาม role สำหรับการแก้ไขข้อมูลจะสมบูรณ์เมื่อย้ายการทำงานเหล่านั้นไปใช้ PHP API/ฐานข้อมูล การตรวจ ICMP Ping จริงยังไม่เชื่อมต่อ
