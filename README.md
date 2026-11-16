# Mini Network Monitor

## โครงสร้าง

- `index.php`, `devices.php`, `network.php`, `history.php`, `alerts.php`, `settings.php` — หน้าระบบหลังเข้าสู่ระบบ
- `login.php`, `register.php`, `logout.php` — ระบบบัญชีผู้ใช้
- `database/schema.sql` — MariaDB/MySQL schema
- `database/migrations/` — schema updates for existing installations
- `api/` — API สำหรับข้อมูล Dashboard, เครือข่าย, อุปกรณ์, ประวัติ, alerts และ Worker
- `includes/` — PDO, session, CSRF และการตรวจสิทธิ์
- `worker/network_monitor.py` — ตรวจ ICMP เฉพาะอุปกรณ์ที่ลงทะเบียน
- `users.php` — หน้าจัดการผู้ใช้สำหรับ admin
- `scripts/` — สร้าง admin/token และตั้ง Worker ให้ทำงานผ่าน Windows Task Scheduler

## เปิดใช้บน XAMPP

1. เริ่ม Apache และ MySQL ใน XAMPP Control Panel
2. Import `database/schema.sql` ใน phpMyAdmin (ฐานข้อมูล `mini_network_monitor`)
3. ถ้าเป็นฐานข้อมูลเดิม ให้ Import migration ทั้งสองไฟล์ใน `database/migrations/` เพื่อเพิ่ม login throttling และ version สำหรับเพิกถอน session หลังเปลี่ยนรหัสผ่าน/role
4. ตั้งค่าการเชื่อมต่อผ่าน environment variables `MNM_DB_HOST`, `MNM_DB_PORT`, `MNM_DB_NAME`, `MNM_DB_USER`, `MNM_DB_PASSWORD`; ค่าเริ่มต้นสำหรับ XAMPP ในเครื่องคือ `127.0.0.1:3306`, `mini_network_monitor`, `root` และรหัสผ่านว่าง
5. วางโครงการไว้ใน `C:\xampp\htdocs\mini-network-monitor` แล้วเปิด `http://localhost/mini-network-monitor/`

ไม่ควรใช้ MySQL `root` หรือรหัสผ่านว่างนอกเครื่องพัฒนา

## บัญชีและบทบาท

- สมัครผ่าน `register.php`; ผู้สมัครใหม่ได้ role `viewer` (อ่านข้อมูลเท่านั้น)
- `operator` และ `admin` จัดการเครือข่าย/อุปกรณ์ ตั้งค่าการตรวจ และรับทราบ alert ได้
- `admin` ใช้หน้า `users.php` เพื่อเปลี่ยน role, เปิด/ปิดบัญชี และตั้งรหัสผ่านใหม่ให้ผู้ใช้อื่น ระบบป้องกันไม่ให้ admin ปิดบัญชีตนเองหรือเอาสิทธิ์จาก admin คนสุดท้าย
- การปิดบัญชีหรือเปลี่ยน role/รหัสผ่านจะเพิกถอน session เดิมของผู้ใช้นั้นในคำขอถัดไป
- สร้าง admin จาก PHP CLI:

```powershell
C:\xampp\php\php.exe scripts\create_admin.php admin admin@example.local
```

สคริปต์จะถามชื่อแสดงและรหัสผ่าน รหัสผ่านเก็บด้วย `password_hash()`, session ใช้ cookie แบบ HttpOnly/SameSite และคำขอเขียนข้อมูลตรวจ CSRF token

รหัสผ่านสมัครสมาชิกและรหัสผ่านที่ admin ตั้งใหม่ต้องยาวอย่างน้อย 12 ตัวอักษร หากผู้ใช้ลืมรหัสผ่าน ให้ admin ตั้งรหัสใหม่จาก `users.php` และแจ้งให้ผู้ใช้ผ่านช่องทางที่ปลอดภัย (ยังไม่มีอีเมลกู้คืนรหัสผ่านอัตโนมัติ)

## API และการตรวจสิทธิ์

- `GET api/data.php` — โหลดข้อมูลระบบสำหรับหน้าเว็บ
- `api/networks.php`, `api/devices.php`, `api/alerts.php`, `api/settings.php` — อ่าน/เขียนข้อมูล; งานเขียนต้องใช้ CSRF token และ role `operator` หรือ `admin`
- ผู้ใช้ทุกบทบาทที่เข้าสู่ระบบอ่านข้อมูลได้
- หน้า login จำกัดความถี่โดย IP และบัญชีที่ระบุ: ถึง 10 ครั้งต่อ 15 นาทีต่อ IP/บัญชี หรือ 30 ครั้งต่อ 15 นาทีต่อ IP จะพักการเข้าสู่ระบบ 15 นาที
- อุปกรณ์จะถูกตรวจเฉพาะเมื่อเปิดใช้งาน และ IP ต้องอยู่ใน CIDR ของเครือข่ายที่เลือก
- การปิดอุปกรณ์/เครือข่ายเป็น soft deactivate เพื่อเก็บประวัติ

ข้อมูลอุปกรณ์ เครือข่าย ประวัติผล Ping, สถานะเหตุการณ์ และการรับทราบ alert อ่าน/เขียนผ่าน MariaDB ไม่ใช้ Local Storage แล้ว

## เปิด Monitor Worker

1. สร้าง token แบบสุ่ม บันทึกเฉพาะ hash ในฐานข้อมูล:

```powershell
C:\xampp\php\php.exe scripts\create_worker_token.php
```

2. คัดลอก token ที่แสดงครั้งเดียว จากนั้นเปิด PowerShell แล้วตั้ง token และเริ่ม Worker:

```powershell
$env:MNM_WORKER_TOKEN = '<ใส่ token ที่สร้าง>'
$env:MNM_API_URL = 'http://127.0.0.1/mini-network-monitor/api/worker.php'
python worker\network_monitor.py
```

ต้องติดตั้ง Python 3.10 ขึ้นไปและให้คำสั่ง `python` เรียกใช้งานได้จาก PowerShell Worker ใช้เฉพาะ IP/CIDR ที่ API ส่งกลับจากรายการอุปกรณ์ที่เปิดใช้งาน จำกัดการทำงานพร้อมกัน และใช้ HTTPS เมื่อเชื่อม API ผ่านเครื่องอื่น หากต้องการทดสอบหนึ่งรอบให้เติม `--once` ท้ายคำสั่ง หลังเพิ่มอุปกรณ์ที่ได้รับอนุญาตแล้ว

หากต้องการเปิด Worker อัตโนมัติเมื่อผู้ใช้ Windows ลงชื่อเข้าใช้ ให้เปิด PowerShell ในโฟลเดอร์โครงการและรัน:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_worker_task.ps1
```

สคริปต์ถาม API URL และ token แบบซ่อนระหว่างพิมพ์ จากนั้นสร้าง Scheduled Task สำหรับบัญชี Windows ปัจจุบันโดยไม่ใส่ token ใน command line; token เข้ารหัสด้วย DPAPI สำหรับผู้ใช้ Windows ปัจจุบันและเก็บใน `%LOCALAPPDATA%\MiniNetworkMonitor` ที่จำกัด ACL ไว้ให้ผู้ใช้นั้น Task ทำงานหลังผู้ใช้ลงชื่อเข้าใช้และหยุดเมื่อออกจากระบบ ต้องสร้าง token ใหม่หากลบหรือย้าย profile ไปเครื่องอื่น

ผลตอบกลับที่ล้มเหลวครั้งแรกบันทึกเป็น `unknown`; เปลี่ยนเป็น `offline` เมื่อไม่ตอบครบจำนวนรอบที่ตั้งไว้ หากตอบได้จะกลับเป็น `online` พร้อมบันทึกประวัติและเหตุการณ์เปลี่ยนสถานะ

## การตรวจสอบ

รันทดสอบ Worker ด้วย:

```powershell
python -m unittest discover -s tests -v
```

ทดสอบ login rate limit บนฐานข้อมูลที่นำเข้า schema/migration แล้ว:

```powershell
C:\xampp\php\php.exe tests\test_login_rate_limit.php
```

การทดสอบ integration ใช้บัญชีทดสอบชั่วคราวและ loopback (`127.0.0.1`) โดยลบข้อมูลชั่วคราวหลังทดสอบ ไม่ควรใช้ Worker ตรวจ IP/เครือข่ายที่ไม่ได้รับอนุญาต

## บันทึกการดำเนินงาน

อัปเดตล่าสุด: 16 พฤศจิกายน 2026

### ทำแล้ว

1. **เตรียมฐานข้อมูล** — เพิ่ม schema สำหรับบัญชีและบทบาท เครือข่าย อุปกรณ์ ผลตรวจ สถานะเหตุการณ์ การแจ้งเตือน การตั้งค่า Worker และ audit log เพื่อเก็บข้อมูลใน MariaDB/MySQL แทนข้อมูลจำลองในเบราว์เซอร์
2. **เพิ่มระบบบัญชี** — สมัคร เข้าสู่ระบบ และออกจากระบบได้ รหัสผ่านจัดเก็บด้วย `password_hash()`; สมาชิกใหม่เป็น `viewer` และต้องมีสิทธิ์ `operator` หรือ `admin` จึงจัดการข้อมูลได้
3. **เชื่อมหน้าเว็บกับ API** — Dashboard, อุปกรณ์, เครือข่าย, ประวัติ, การแจ้งเตือน และการตั้งค่าโหลดข้อมูลจาก API ที่ตรวจ session, CSRF และสิทธิ์ก่อนรับคำสั่งเปลี่ยนแปลง
4. **เพิ่มการตรวจสอบเครือข่าย** — Worker Python รับเฉพาะอุปกรณ์ที่เปิดใช้งานจาก API ตรวจว่า IP อยู่ใน CIDR ที่กำหนดก่อน Ping และส่งผลกลับเพื่อบันทึกประวัติและอัปเดตสถานะ
5. **จัดเตรียมการตั้งค่า Worker** — เพิ่มคำสั่งสร้าง token แบบสุ่ม โดยจัดเก็บเฉพาะ hash ในฐานข้อมูล และมี heartbeat แสดงการทำงานล่าสุด
6. **ทดสอบบน XAMPP** — ทดสอบสมัครและเข้าสู่ระบบจริง ทดสอบว่า `viewer` เขียนข้อมูลไม่ได้ และทดสอบ `operator` เพิ่มเครือข่ายกับอุปกรณ์ผ่านหน้าเว็บได้
7. **จัดการบัญชีผู้ใช้** — เพิ่มหน้า admin สำหรับกำหนด role, ปิด/เปิดบัญชี และตั้งรหัสผ่านใหม่ให้ผู้ใช้อื่น; ป้องกันการปิด/ลดสิทธิ์ admin คนสุดท้าย และโหลด role/สถานะล่าสุดจากฐานข้อมูลเมื่อใช้ session
8. **ป้องกันเดารหัสผ่าน** — จำกัดการลอง login ตาม IP และ IP/บัญชี พร้อมพัก 15 นาทีเมื่อเกินจำนวนครั้ง และกำหนดรหัสผ่านใหม่ขั้นต่ำ 12 ตัวอักษร
9. **เตรียม Worker อัตโนมัติ** — เพิ่มสคริปต์ติดตั้ง Windows Scheduled Task และ runner ที่ใช้ token ซึ่งเข้ารหัสด้วย DPAPI แทนการฝัง token ในคำสั่ง task

### ผลตรวจล่าสุด

- ตรวจ PHP syntax ล่าสุดผ่าน 26 ไฟล์
- Python unit tests ผ่าน 4 รายการ รวมกรณี IP อยู่นอก CIDR, ผล Ping สำเร็จ, การบังคับ HTTPS สำหรับ API ระยะไกล และการรอผลล้มเหลวครบจำนวนครั้งก่อนเปลี่ยนเป็น `offline`
- ทดสอบ login rate limit ครบเกณฑ์ 10 ครั้ง และทดสอบ migration กับฐานข้อมูล XAMPP แล้ว
- ทดสอบหน้า admin จัดการผู้ใช้จริงบน XAMPP: เปลี่ยน role, เพิกถอน session เดิม, ตั้งรหัสผ่านใหม่ และเข้าสู่ระบบด้วยรหัสใหม่ได้; ลบบัญชีทดสอบแล้ว
- ตรวจ PowerShell syntax ของสคริปต์ Task Scheduler และทดสอบ migration ซ้ำได้; ยังไม่ได้สร้าง Scheduled Task จริงหรือใช้ Worker token จริง
- เปิดตรวจหน้า Dashboard, อุปกรณ์, เครือข่าย, ประวัติ, การแจ้งเตือน และการตั้งค่าได้ครบ โดยไม่พบ JavaScript error
- ทดสอบ Worker Ping ไปยัง `127.0.0.1` และยืนยันการบันทึกผล/heartbeat; ลบข้อมูลบัญชีและอุปกรณ์ทดสอบแล้ว

### ยังต้องทำก่อนใช้งานจริง

- ผู้ดูแลต้องสร้างบัญชี `admin` และตั้งรหัสผ่านเองด้วย `scripts/create_admin.php`
- ต้องสร้าง Worker token และตั้งค่า `MNM_WORKER_TOKEN` ก่อนเริ่ม Worker; ปัจจุบันยังไม่ได้ตั้ง token ใช้งาน
- ต้องเพิ่มเฉพาะอุปกรณ์และเครือข่ายที่ได้รับอนุญาต แล้วทดสอบกับอุปกรณ์จริงในเครือข่ายของผู้ใช้
- สคริปต์ Task Scheduler เพิ่มแล้ว แต่ยังต้องให้ผู้ดูแลรัน `install_worker_task.ps1` ด้วยตนเอง แล้วตรวจสถานะ task และ heartbeat บนเครื่องใช้งาน
- ยังไม่มีอีเมลกู้คืนรหัสผ่านอัตโนมัติ; admin ต้องตั้งรหัสผ่านใหม่ให้ผู้ใช้
- การทดสอบนี้ไม่ได้ตรวจความพร้อมระดับ production เช่น การกู้คืนรหัสผ่านทางอีเมลและการทดสอบโหลด/ติดตั้งในสภาพแวดล้อมจริง

id users (เครื่องกู)
    Admin    : admin@example.local / password : 12345
    operator : operator@example.local / password : 12345
    viewer   : users@example.local / password : 12345
