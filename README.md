# Mini Network Monitor

## โครงสร้าง

- `index.php`, `devices.php`, `network.php`, `history.php`, `alerts.php`, `settings.php` — หน้าระบบหลังเข้าสู่ระบบ
- `login.php`, `register.php`, `logout.php` — ระบบบัญชีผู้ใช้
- `database/schema.sql` — MariaDB/MySQL schema
- `api/` — API สำหรับข้อมูล Dashboard, เครือข่าย, อุปกรณ์, ประวัติ, alerts และ Worker
- `includes/` — PDO, session, CSRF และการตรวจสิทธิ์
- `worker/network_monitor.py` — ตรวจ ICMP เฉพาะอุปกรณ์ที่ลงทะเบียน
- `scripts/create_admin.php` และ `scripts/create_worker_token.php` — ตั้งค่าผู้ดูแลและ token สำหรับ Worker (CLI เท่านั้น)

## เปิดใช้บน XAMPP

1. เริ่ม Apache และ MySQL ใน XAMPP Control Panel
2. Import `database/schema.sql` ใน phpMyAdmin (ฐานข้อมูล `mini_network_monitor`)
3. ตั้งค่าการเชื่อมต่อผ่าน environment variables `MNM_DB_HOST`, `MNM_DB_PORT`, `MNM_DB_NAME`, `MNM_DB_USER`, `MNM_DB_PASSWORD`; ค่าเริ่มต้นสำหรับ XAMPP ในเครื่องคือ `127.0.0.1:3306`, `mini_network_monitor`, `root` และรหัสผ่านว่าง
4. วางโครงการไว้ใน `C:\xampp\htdocs\mini-network-monitor` แล้วเปิด `http://localhost/mini-network-monitor/`

ไม่ควรใช้ MySQL `root` หรือรหัสผ่านว่างนอกเครื่องพัฒนา

## บัญชีและบทบาท

- สมัครผ่าน `register.php`; ผู้สมัครใหม่ได้ role `viewer` (อ่านข้อมูลเท่านั้น)
- `operator` และ `admin` จัดการเครือข่าย/อุปกรณ์ ตั้งค่าการตรวจ และรับทราบ alert ได้
- สร้าง admin จาก PHP CLI:

```powershell
C:\xampp\php\php.exe scripts\create_admin.php admin admin@example.local
```

สคริปต์จะถามชื่อแสดงและรหัสผ่าน รหัสผ่านเก็บด้วย `password_hash()`, session ใช้ cookie แบบ HttpOnly/SameSite และคำขอเขียนข้อมูลตรวจ CSRF token

## API และการตรวจสิทธิ์

- `GET api/data.php` — โหลดข้อมูลระบบสำหรับหน้าเว็บ
- `api/networks.php`, `api/devices.php`, `api/alerts.php`, `api/settings.php` — อ่าน/เขียนข้อมูล; งานเขียนต้องใช้ CSRF token และ role `operator` หรือ `admin`
- ผู้ใช้ทุกบทบาทที่เข้าสู่ระบบอ่านข้อมูลได้
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

ผลตอบกลับที่ล้มเหลวครั้งแรกบันทึกเป็น `unknown`; เปลี่ยนเป็น `offline` เมื่อไม่ตอบครบจำนวนรอบที่ตั้งไว้ หากตอบได้จะกลับเป็น `online` พร้อมบันทึกประวัติและเหตุการณ์เปลี่ยนสถานะ

## การตรวจสอบ

รันทดสอบ Worker ด้วย:

```powershell
python -m unittest discover -s tests -v
```

การทดสอบ integration ใช้บัญชีทดสอบชั่วคราวและ loopback (`127.0.0.1`) โดยลบข้อมูลชั่วคราวหลังทดสอบ ไม่ควรใช้ Worker ตรวจ IP/เครือข่ายที่ไม่ได้รับอนุญาต

## บันทึกการดำเนินงาน

อัปเดตล่าสุด: 12 พฤศจิกายน 2026

### ทำแล้ว

1. **เตรียมฐานข้อมูล** — เพิ่ม schema สำหรับบัญชีและบทบาท เครือข่าย อุปกรณ์ ผลตรวจ สถานะเหตุการณ์ การแจ้งเตือน การตั้งค่า Worker และ audit log เพื่อเก็บข้อมูลใน MariaDB/MySQL แทนข้อมูลจำลองในเบราว์เซอร์
2. **เพิ่มระบบบัญชี** — สมัคร เข้าสู่ระบบ และออกจากระบบได้ รหัสผ่านจัดเก็บด้วย `password_hash()`; สมาชิกใหม่เป็น `viewer` และต้องมีสิทธิ์ `operator` หรือ `admin` จึงจัดการข้อมูลได้
3. **เชื่อมหน้าเว็บกับ API** — Dashboard, อุปกรณ์, เครือข่าย, ประวัติ, การแจ้งเตือน และการตั้งค่าโหลดข้อมูลจาก API ที่ตรวจ session, CSRF และสิทธิ์ก่อนรับคำสั่งเปลี่ยนแปลง
4. **เพิ่มการตรวจสอบเครือข่าย** — Worker Python รับเฉพาะอุปกรณ์ที่เปิดใช้งานจาก API ตรวจว่า IP อยู่ใน CIDR ที่กำหนดก่อน Ping และส่งผลกลับเพื่อบันทึกประวัติและอัปเดตสถานะ
5. **จัดเตรียมการตั้งค่า Worker** — เพิ่มคำสั่งสร้าง token แบบสุ่ม โดยจัดเก็บเฉพาะ hash ในฐานข้อมูล และมี heartbeat แสดงการทำงานล่าสุด
6. **ทดสอบบน XAMPP** — ทดสอบสมัครและเข้าสู่ระบบจริง ทดสอบว่า `viewer` เขียนข้อมูลไม่ได้ และทดสอบ `operator` เพิ่มเครือข่ายกับอุปกรณ์ผ่านหน้าเว็บได้

### ผลตรวจล่าสุด

- ตรวจ PHP syntax ผ่าน 23 ไฟล์
- Python unit tests ผ่าน 4 รายการ รวมกรณี IP อยู่นอก CIDR, ผล Ping สำเร็จ, การบังคับ HTTPS สำหรับ API ระยะไกล และการรอผลล้มเหลวครบจำนวนครั้งก่อนเปลี่ยนเป็น `offline`
- เปิดตรวจหน้า Dashboard, อุปกรณ์, เครือข่าย, ประวัติ, การแจ้งเตือน และการตั้งค่าได้ครบ โดยไม่พบ JavaScript error
- ทดสอบ Worker Ping ไปยัง `127.0.0.1` และยืนยันการบันทึกผล/heartbeat; ลบข้อมูลบัญชีและอุปกรณ์ทดสอบแล้ว

### ยังต้องทำก่อนใช้งานจริง

- ผู้ดูแลต้องสร้างบัญชี `admin` และตั้งรหัสผ่านเองด้วย `scripts/create_admin.php`
- ต้องสร้าง Worker token และตั้งค่า `MNM_WORKER_TOKEN` ก่อนเริ่ม Worker; ปัจจุบันยังไม่ได้ตั้ง token ใช้งาน
- ต้องเพิ่มเฉพาะอุปกรณ์และเครือข่ายที่ได้รับอนุญาต แล้วทดสอบกับอุปกรณ์จริงในเครือข่ายของผู้ใช้
- หากต้องการตรวจต่อเนื่อง ต้องตั้ง Worker ให้เริ่มทำงานอัตโนมัติผ่าน Task Scheduler หรือวิธีจัดการ service ที่เหมาะสม
- การทดสอบนี้ไม่ได้ตรวจความพร้อมระดับ production เช่น rate limiting, การกู้คืนรหัสผ่าน และหน้าจัดการบัญชี/เปลี่ยนบทบาทผู้ใช้
