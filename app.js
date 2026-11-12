const STORAGE_KEY = "mini-network-monitor-demo-v1";

const initialData = {
  networks: [
    { id: "net-1", name: "เครือข่ายสำนักงาน", cidr: "192.168.10.0/24", gateway: "192.168.10.1", department: "สำนักงาน", active: true },
    { id: "net-2", name: "เครือข่ายห้องตรวจ", cidr: "192.168.20.0/24", gateway: "192.168.20.1", department: "ผู้ป่วยนอก", active: true },
    { id: "net-3", name: "เครือข่ายแม่ข่าย", cidr: "10.10.1.0/24", gateway: "10.10.1.1", department: "เทคโนโลยีสารสนเทศ", active: true }
  ],
  devices: [
    { id: "dev-1", name: "Core Switch ชั้น 1", ip: "192.168.10.2", type: "switch", networkId: "net-1", department: "เทคโนโลยีสารสนเทศ", location: "ห้อง Server", status: "online", response: 2.4, checkedAt: new Date(Date.now() - 38000).toISOString(), active: true },
    { id: "dev-2", name: "File Server", ip: "10.10.1.10", type: "server", networkId: "net-3", department: "เทคโนโลยีสารสนเทศ", location: "ห้อง Server", status: "online", response: 5.8, checkedAt: new Date(Date.now() - 52000).toISOString(), active: true },
    { id: "dev-3", name: "เครื่องพิมพ์เวชระเบียน", ip: "192.168.10.45", type: "printer", networkId: "net-1", department: "เวชระเบียน", location: "เคาน์เตอร์ 1", status: "offline", response: null, checkedAt: new Date(Date.now() - 210000).toISOString(), active: true },
    { id: "dev-4", name: "เครื่องตรวจ OPD 01", ip: "192.168.20.21", type: "computer", networkId: "net-2", department: "ผู้ป่วยนอก", location: "ห้องตรวจ 1", status: "online", response: 12.6, checkedAt: new Date(Date.now() - 41000).toISOString(), active: true },
    { id: "dev-5", name: "Access Point โถงกลาง", ip: "192.168.20.5", type: "router", networkId: "net-2", department: "เทคโนโลยีสารสนเทศ", location: "โถงกลาง", status: "unknown", response: null, checkedAt: null, active: true },
    { id: "dev-6", name: "เครื่องพิมพ์ห้องยา", ip: "192.168.20.60", type: "printer", networkId: "net-2", department: "เภสัชกรรม", location: "ห้องจ่ายยา", status: "online", response: 8.1, checkedAt: new Date(Date.now() - 67000).toISOString(), active: true },
    { id: "dev-7", name: "Application Server", ip: "10.10.1.11", type: "server", networkId: "net-3", department: "เทคโนโลยีสารสนเทศ", location: "ห้อง Server", status: "online", response: 3.2, checkedAt: new Date(Date.now() - 27000).toISOString(), active: true }
  ],
  history: [
    { id: "hist-1", deviceId: "dev-3", status: "offline", response: null, checkedAt: new Date(Date.now() - 210000).toISOString(), detail: "ไม่ตอบสนองหลังตรวจซ้ำ" },
    { id: "hist-2", deviceId: "dev-1", status: "online", response: 2.4, checkedAt: new Date(Date.now() - 38000).toISOString(), detail: "ตอบสนองปกติ" },
    { id: "hist-3", deviceId: "dev-2", status: "online", response: 5.8, checkedAt: new Date(Date.now() - 52000).toISOString(), detail: "ตอบสนองปกติ" },
    { id: "hist-4", deviceId: "dev-4", status: "online", response: 12.6, checkedAt: new Date(Date.now() - 41000).toISOString(), detail: "ตอบสนองปกติ" },
    { id: "hist-5", deviceId: "dev-6", status: "online", response: 8.1, checkedAt: new Date(Date.now() - 67000).toISOString(), detail: "ตอบสนองปกติ" },
    { id: "hist-6", deviceId: "dev-7", status: "online", response: 3.2, checkedAt: new Date(Date.now() - 27000).toISOString(), detail: "ตอบสนองปกติ" }
  ],
  alerts: [
    { id: "alert-1", deviceId: "dev-3", createdAt: new Date(Date.now() - 210000).toISOString(), acknowledged: false, acknowledgedAt: null }
  ],
  settings: { interval: 60, retries: 2 }
};

let data = loadData();
let activePage = "overview";
let editing = null;
let toastTimer;

const labels = {
  status: { online: "ออนไลน์", offline: "ออฟไลน์", unknown: "ไม่ทราบสถานะ" },
  type: { computer: "คอมพิวเตอร์", server: "เซิร์ฟเวอร์", printer: "เครื่องพิมพ์", router: "เราเตอร์", switch: "สวิตช์", other: "อื่น ๆ" }
};

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

function loadData() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return clone(initialData);
    const parsed = JSON.parse(saved);
    if (!Array.isArray(parsed.devices) || !Array.isArray(parsed.networks)) throw new Error("รูปแบบข้อมูลไม่ถูกต้อง");
    return { ...clone(initialData), ...parsed };
  } catch (error) {
    console.error("ไม่สามารถอ่านข้อมูลตัวอย่างจากเบราว์เซอร์ได้", error);
    return clone(initialData);
  }
}

function saveData() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    return true;
  } catch (error) {
    console.error("ไม่สามารถบันทึกข้อมูลตัวอย่างได้", error);
    showToast("บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบพื้นที่จัดเก็บของเบราว์เซอร์");
    return false;
  }
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char]));
}

function getNetwork(id) {
  return data.networks.find(network => network.id === id);
}

function getDevice(id) {
  return data.devices.find(device => device.id === id);
}

function statusPill(status) {
  return `<span class="status-pill ${status}">${labels.status[status] || labels.status.unknown}</span>`;
}

function deviceIcon(type) {
  const icons = { computer: "▣", server: "▤", printer: "▧", router: "⌁", switch: "⌘", other: "▦" };
  const category = ["router", "switch"].includes(type) ? "network" : type;
  return `<span class="device-icon ${category}">${icons[type] || icons.other}</span>`;
}

function relativeTime(value) {
  if (!value) return "ยังไม่เคยตรวจ";
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
  if (seconds < 60) return `${seconds} วินาทีที่แล้ว`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)} นาทีที่แล้ว`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} ชั่วโมงที่แล้ว`;
  return new Date(value).toLocaleDateString("th-TH", { day: "numeric", month: "short", year: "2-digit" });
}

function fullDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString("th-TH", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", second: "2-digit" });
}

function showToast(message) {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2800);
}

function render() {
  renderOverview();
  renderDevices();
  renderNetworks();
  renderHistory();
  renderAlerts();
  renderSettings();
  const deviceCount = document.getElementById("device-nav-count");
  if (deviceCount) deviceCount.textContent = data.devices.filter(device => device.active).length;
  const unacknowledged = data.alerts.filter(alert => !alert.acknowledged).length;
  const notificationCount = document.getElementById("notification-count");
  if (notificationCount) notificationCount.textContent = unacknowledged;
  const alertDot = document.getElementById("alert-nav-dot");
  if (alertDot) alertDot.style.display = unacknowledged ? "" : "none";
  const lastUpdated = document.getElementById("last-updated");
  if (lastUpdated) lastUpdated.textContent = new Date().toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" });
}

function renderOverview() {
  if (!document.getElementById("page-overview")) return;
  const devices = data.devices.filter(device => device.active);
  const counts = { online: 0, offline: 0, unknown: 0 };
  devices.forEach(device => counts[device.status]++);
  document.getElementById("stat-total").textContent = devices.length;
  document.getElementById("stat-online").textContent = counts.online;
  document.getElementById("stat-offline").textContent = counts.offline;
  document.getElementById("stat-unknown").textContent = counts.unknown;
  document.getElementById("donut-total").textContent = devices.length;
  document.getElementById("legend-online").textContent = counts.online;
  document.getElementById("legend-offline").textContent = counts.offline;
  document.getElementById("legend-unknown").textContent = counts.unknown;
  const total = Math.max(1, devices.length);
  const onlineEnd = counts.online / total * 100;
  const offlineEnd = onlineEnd + counts.offline / total * 100;
  document.getElementById("status-donut").style.background = `conic-gradient(#2cad82 0 ${onlineEnd}%,#e56b71 ${onlineEnd}% ${offlineEnd}%,#d1d6df ${offlineEnd}% 100%)`;

  const networkRows = data.networks.filter(network => network.active).map(network => {
    const members = devices.filter(device => device.networkId === network.id);
    if (!members.length) return "";
    const online = members.filter(device => device.status === "online").length;
    const offline = members.filter(device => device.status === "offline").length;
    const unknown = members.length - online - offline;
    return `<div class="network-row"><div class="network-name"><strong>${escapeHtml(network.name)}</strong><small>${escapeHtml(network.cidr)}</small></div><div class="bar-track"><i class="bar-segment online" style="width:${online / members.length * 100}%"></i><i class="bar-segment offline" style="width:${offline / members.length * 100}%"></i><i class="bar-segment unknown" style="width:${unknown / members.length * 100}%"></i></div><span class="network-total">${online}/${members.length} online</span><span class="network-flags"><i class="network-flag online"></i><i class="network-flag offline"></i><i class="network-flag unknown"></i></span></div>`;
  }).join("");
  document.getElementById("network-summary").innerHTML = networkRows || '<div class="empty-inline">ยังไม่มีเครือข่ายที่มีอุปกรณ์</div>';

  const attention = devices.filter(device => device.status !== "online").sort((a, b) => a.status === "offline" ? -1 : b.status === "offline" ? 1 : 0).slice(0, 5);
  document.getElementById("attention-table").innerHTML = attention.length ? attention.map(device => `<tr><td><div class="device-cell">${deviceIcon(device.type)}<span class="device-name"><strong>${escapeHtml(device.name)}</strong><small>${escapeHtml(labels.type[device.type] || labels.type.other)}</small></span></div></td><td><span class="ip-text">${escapeHtml(device.ip)}</span></td><td>${escapeHtml(getNetwork(device.networkId)?.name || "—")}</td><td>${statusPill(device.status)}</td><td><span class="response ${device.response == null ? "none" : ""}">${device.response == null ? "—" : `${device.response} ms`}</span></td><td><span class="time-cell">${escapeHtml(relativeTime(device.checkedAt))}</span></td><td><button class="row-menu" data-edit-device="${device.id}" aria-label="แก้ไขอุปกรณ์">···</button></td></tr>`).join("") : '<tr><td class="table-empty" colspan="7">ไม่มีอุปกรณ์ที่ต้องติดตาม</td></tr>';

  const recent = [...data.history].sort((a, b) => new Date(b.checkedAt) - new Date(a.checkedAt)).slice(0, 4);
  document.getElementById("activity-list").innerHTML = recent.length ? recent.map(record => {
    const device = getDevice(record.deviceId);
    const name = device?.name || "อุปกรณ์ที่ถูกลบ";
    return `<div class="activity-item"><span class="activity-mark ${record.status}">${record.status === "online" ? "✓" : record.status === "offline" ? "!" : "?"}</span><span class="activity-copy"><strong>${escapeHtml(name)} · ${labels.status[record.status]}</strong><small>${escapeHtml(device?.ip || "")}</small></span><span class="activity-time">${escapeHtml(relativeTime(record.checkedAt))}</span></div>`;
  }).join("") : '<div class="empty-inline">ยังไม่มีประวัติการตรวจ</div>';
}

function renderDevices() {
  const searchInput = document.getElementById("device-search");
  const networkSelect = document.getElementById("device-network-filter");
  if (!searchInput || !networkSelect) return;
  const search = searchInput.value.trim().toLowerCase();
  const status = document.getElementById("device-status-filter").value;
  const network = networkSelect.value;
  const currentNetwork = networkSelect.value;
  networkSelect.innerHTML = '<option value="">ทุกเครือข่าย</option>' + data.networks.map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.name)}</option>`).join("");
  if ([...networkSelect.options].some(option => option.value === currentNetwork)) networkSelect.value = currentNetwork;
  const filtered = data.devices.filter(device => device.active).filter(device => {
    const matchesSearch = `${device.name} ${device.ip} ${device.department} ${device.location}`.toLowerCase().includes(search);
    return matchesSearch && (!status || device.status === status) && (!network || device.networkId === network);
  });
  document.getElementById("device-result-count").textContent = `${filtered.length} รายการ`;
  document.getElementById("devices-table").innerHTML = filtered.length ? filtered.map(device => `<tr><td><div class="device-cell">${deviceIcon(device.type)}<span class="device-name"><strong>${escapeHtml(device.name)}</strong><small>${escapeHtml(device.department || "ไม่ระบุหน่วยงาน")}</small></span></div></td><td><span class="ip-text">${escapeHtml(device.ip)}</span></td><td>${escapeHtml(labels.type[device.type] || labels.type.other)}</td><td>${escapeHtml(getNetwork(device.networkId)?.name || "—")}</td><td>${escapeHtml([device.department, device.location].filter(Boolean).join(" · ") || "—")}</td><td>${statusPill(device.status)}</td><td><span class="response ${device.response == null ? "none" : device.response > 10 ? "slow" : ""}">${device.response == null ? "—" : `${device.response} ms`}</span></td><td><span class="time-cell">${escapeHtml(relativeTime(device.checkedAt))}</span></td><td><button class="row-menu" data-edit-device="${device.id}" aria-label="แก้ไขอุปกรณ์">···</button></td></tr>`).join("") : '<tr><td class="table-empty" colspan="9">ไม่พบอุปกรณ์ที่ตรงกับเงื่อนไข</td></tr>';
}

function renderNetworks() {
  const container = document.getElementById("networks-grid");
  if (!container) return;
  container.innerHTML = data.networks.length ? data.networks.map(network => {
    const count = data.devices.filter(device => device.active && device.networkId === network.id).length;
    return `<article class="panel network-card"><div class="network-card-head"><div class="network-card-title"><span class="network-symbol">⌘</span><span><h2>${escapeHtml(network.name)}</h2><small>${escapeHtml(network.department || "ไม่ระบุหน่วยงาน")}</small></span></div><button class="network-actions" data-edit-network="${network.id}" aria-label="แก้ไขเครือข่าย">···</button></div><div class="network-info"><span><label>NETWORK / CIDR</label><strong>${escapeHtml(network.cidr)}</strong></span><span><label>GATEWAY</label><strong>${escapeHtml(network.gateway || "—")}</strong></span></div><div class="network-card-foot"><span class="network-device-count"><strong>${count}</strong> อุปกรณ์</span><span class="network-active ${network.active ? "" : "inactive"}">${network.active ? "ใช้งาน" : "ปิดใช้งาน"}</span></div></article>`;
  }).join("") : '<div class="panel empty-inline">ยังไม่มีข้อมูลเครือข่าย</div>';
}

function renderHistory() {
  const searchInput = document.getElementById("history-search");
  if (!searchInput) return;
  const search = searchInput.value.trim().toLowerCase();
  const status = document.getElementById("history-status-filter").value;
  const date = document.getElementById("history-date-filter").value;
  const filtered = [...data.history].sort((a, b) => new Date(b.checkedAt) - new Date(a.checkedAt)).filter(record => {
    const device = getDevice(record.deviceId);
    const datePart = record.checkedAt ? new Date(record.checkedAt).toISOString().slice(0, 10) : "";
    return `${device?.name || ""} ${device?.ip || ""}`.toLowerCase().includes(search) && (!status || record.status === status) && (!date || datePart === date);
  });
  document.getElementById("history-result-count").textContent = `${filtered.length} รายการ`;
  document.getElementById("history-table").innerHTML = filtered.length ? filtered.map(record => {
    const device = getDevice(record.deviceId);
    return `<tr><td><div class="device-name"><strong>${escapeHtml(device?.name || "อุปกรณ์ที่ถูกลบ")}</strong><small>${escapeHtml(labels.type[device?.type] || labels.type.other)}</small></div></td><td><span class="ip-text">${escapeHtml(device?.ip || "—")}</span></td><td>${statusPill(record.status)}</td><td><span class="response ${record.response == null ? "none" : ""}">${record.response == null ? "—" : `${record.response} ms`}</span></td><td><span class="time-cell">${escapeHtml(fullDate(record.checkedAt))}</span></td><td>${escapeHtml(record.detail || "—")}</td></tr>`;
  }).join("") : '<tr><td class="table-empty" colspan="6">ไม่พบประวัติที่ตรงกับเงื่อนไข</td></tr>';
}

function renderAlerts() {
  const searchInput = document.getElementById("alert-search");
  if (!searchInput) return;
  const search = searchInput.value.trim().toLowerCase();
  const filter = document.getElementById("alert-filter").value;
  const unacknowledged = data.alerts.filter(alert => !alert.acknowledged).length;
  document.getElementById("alert-summary").innerHTML = `<strong>${unacknowledged}</strong> รายการที่ยังไม่รับทราบ`;
  const alerts = [...data.alerts].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).filter(alert => {
    const device = getDevice(alert.deviceId);
    return `${device?.name || ""} ${device?.ip || ""}`.toLowerCase().includes(search) && (!filter || (filter === "acknowledged" ? alert.acknowledged : !alert.acknowledged));
  });
  document.getElementById("alerts-list").innerHTML = alerts.length ? alerts.map(alert => {
    const device = getDevice(alert.deviceId);
    return `<div class="alert-item ${alert.acknowledged ? "acknowledged" : ""}"><span class="alert-symbol">${alert.acknowledged ? "✓" : "!"}</span><div class="alert-content"><strong>${escapeHtml(device?.name || "อุปกรณ์ที่ถูกลบ")} ไม่ตอบสนอง</strong><p>${escapeHtml(device?.ip || "—")} · ${escapeHtml(getNetwork(device?.networkId)?.name || "ไม่ระบุเครือข่าย")}</p><small>${escapeHtml(fullDate(alert.createdAt))}${alert.acknowledged ? ` · รับทราบโดย ${escapeHtml(alert.acknowledgedBy || "เจ้าหน้าที่ระบบ")} เมื่อ ${escapeHtml(fullDate(alert.acknowledgedAt))}` : ""}</small></div><div class="alert-actions">${alert.acknowledged ? '<span class="ack-label">รับทราบแล้ว</span>' : `<button class="ack-button" data-ack-alert="${alert.id}">ทำเครื่องหมายรับทราบ</button>`}</div></div>`;
  }).join("") : '<div class="empty-inline">ไม่มีรายการแจ้งเตือน</div>';
}

function renderSettings() {
  const interval = document.getElementById("interval-setting");
  const retries = document.getElementById("retries-setting");
  if (!interval || !retries) return;
  interval.value = data.settings.interval;
  retries.value = data.settings.retries;
}

function openDeviceForm(device = null) {
  editing = device ? { type: "device", id: device.id } : { type: "device", id: null };
  document.getElementById("modal-eyebrow").textContent = "DEVICE INVENTORY";
  document.getElementById("modal-title").textContent = device ? "แก้ไขอุปกรณ์" : "เพิ่มอุปกรณ์";
  const networks = data.networks.filter(network => network.active);
  document.getElementById("modal-fields").innerHTML = `<div class="modal-grid"><div class="full-field"><label class="form-label" for="field-name">ชื่ออุปกรณ์ *</label><input class="field-control" id="field-name" name="name" required maxlength="100" value="${escapeHtml(device?.name || "")}" placeholder="เช่น Core Switch ชั้น 1"></div><div><label class="form-label" for="field-ip">IP Address *</label><input class="field-control" id="field-ip" name="ip" required value="${escapeHtml(device?.ip || "")}" placeholder="192.168.1.10"></div><div><label class="form-label" for="field-type">ประเภทอุปกรณ์</label><select class="field-control" id="field-type" name="type">${Object.entries(labels.type).map(([key, text]) => `<option value="${key}" ${device?.type === key ? "selected" : ""}>${text}</option>`).join("")}</select></div><div><label class="form-label" for="field-network">เครือข่าย *</label><select class="field-control" id="field-network" name="networkId" required>${networks.map(network => `<option value="${escapeHtml(network.id)}" ${device?.networkId === network.id ? "selected" : ""}>${escapeHtml(network.name)}</option>`).join("")}</select></div><div><label class="form-label" for="field-department">หน่วยงาน</label><input class="field-control" id="field-department" name="department" maxlength="100" value="${escapeHtml(device?.department || "")}" placeholder="เช่น เทคโนโลยีสารสนเทศ"></div><div class="full-field"><label class="form-label" for="field-location">ตำแหน่งติดตั้ง</label><input class="field-control" id="field-location" name="location" maxlength="150" value="${escapeHtml(device?.location || "")}" placeholder="เช่น อาคาร / ชั้น / ห้อง"></div></div>`;
  document.getElementById("form-error").textContent = "";
  openModal();
}

function openNetworkForm(network = null) {
  editing = { type: "network", id: network?.id || null };
  document.getElementById("modal-eyebrow").textContent = "NETWORK CONFIGURATION";
  document.getElementById("modal-title").textContent = network ? "แก้ไขเครือข่าย" : "เพิ่มเครือข่าย";
  document.getElementById("modal-fields").innerHTML = `<div class="modal-grid"><div class="full-field"><label class="form-label" for="field-name">ชื่อเครือข่าย *</label><input class="field-control" id="field-name" name="name" required maxlength="100" value="${escapeHtml(network?.name || "")}" placeholder="เช่น เครือข่ายสำนักงาน"></div><div><label class="form-label" for="field-cidr">Network / CIDR *</label><input class="field-control" id="field-cidr" name="cidr" required value="${escapeHtml(network?.cidr || "")}" placeholder="192.168.1.0/24"></div><div><label class="form-label" for="field-gateway">Gateway</label><input class="field-control" id="field-gateway" name="gateway" value="${escapeHtml(network?.gateway || "")}" placeholder="192.168.1.1"></div><div class="full-field"><label class="form-label" for="field-department">หน่วยงาน</label><input class="field-control" id="field-department" name="department" maxlength="100" value="${escapeHtml(network?.department || "")}" placeholder="เช่น สำนักงาน"></div></div>`;
  document.getElementById("form-error").textContent = "";
  openModal();
}

function openModal() {
  const backdrop = document.getElementById("modal-backdrop");
  backdrop.classList.add("open");
  backdrop.setAttribute("aria-hidden", "false");
  setTimeout(() => document.getElementById("field-name")?.focus(), 30);
}

function closeModal() {
  document.getElementById("modal-backdrop").classList.remove("open");
  document.getElementById("modal-backdrop").setAttribute("aria-hidden", "true");
  editing = null;
}

function validIp(ip) {
  const parts = ip.trim().split(".");
  return parts.length === 4 && parts.every(part => /^\d{1,3}$/.test(part) && Number(part) >= 0 && Number(part) <= 255);
}

function validCidr(value) {
  const parts = value.trim().split("/");
  return parts.length === 2 && validIp(parts[0]) && /^\d{1,2}$/.test(parts[1]) && Number(parts[1]) <= 32;
}

function submitForm(event) {
  event.preventDefault();
  if (!editing) return;
  const isEditing = Boolean(editing.id);
  const form = new FormData(event.currentTarget);
  const values = Object.fromEntries(form.entries());
  const error = document.getElementById("form-error");
  if (editing.type === "device") {
    const duplicate = data.devices.some(device => device.ip === values.ip.trim() && device.id !== editing.id);
    if (!validIp(values.ip)) {
      error.textContent = "กรุณากรอก IP Address ให้ถูกต้อง เช่น 192.168.1.10";
      return;
    }
    if (duplicate) {
      error.textContent = "IP Address นี้มีอยู่ในรายการแล้ว";
      return;
    }
    if (!getNetwork(values.networkId)) {
      error.textContent = "กรุณาเลือกเครือข่ายที่ถูกต้อง";
      return;
    }
    if (editing.id) {
      const device = getDevice(editing.id);
      Object.assign(device, values, { ip: values.ip.trim() });
    } else {
      const device = { ...values, id: `dev-${crypto.randomUUID()}`, ip: values.ip.trim(), status: "unknown", response: null, checkedAt: null, active: true };
      data.devices.push(device);
    }
    if (!saveData()) return;
    closeModal();
    render();
    showToast(isEditing ? "บันทึกการแก้ไขอุปกรณ์แล้ว" : "เพิ่มอุปกรณ์แล้ว รอผลตรวจสอบ");
    return;
  }
  if (!validCidr(values.cidr)) {
    error.textContent = "กรุณากรอก Network/CIDR ให้ถูกต้อง เช่น 192.168.1.0/24";
    return;
  }
  if (values.gateway && !validIp(values.gateway)) {
    error.textContent = "กรุณากรอก Gateway เป็น IP Address ที่ถูกต้อง";
    return;
  }
  if (editing.id) Object.assign(data.networks.find(network => network.id === editing.id), values);
  else data.networks.push({ ...values, id: `net-${crypto.randomUUID()}`, active: true });
  if (!saveData()) return;
  closeModal();
  render();
  showToast("บันทึกข้อมูลเครือข่ายแล้ว");
}

function exportCsv(historyOnly = false) {
  const rows = historyOnly
    ? [["อุปกรณ์", "IP Address", "สถานะ", "เวลาตอบสนอง (ms)", "เวลาตรวจ", "รายละเอียด"], ...data.history.map(record => {
      const device = getDevice(record.deviceId);
      return [device?.name || "อุปกรณ์ที่ถูกลบ", device?.ip || "", labels.status[record.status], record.response ?? "", fullDate(record.checkedAt), record.detail || ""];
    })]
    : [["ชื่ออุปกรณ์", "IP Address", "ประเภท", "เครือข่าย", "หน่วยงาน", "ตำแหน่ง", "สถานะ", "Response Time (ms)", "ตรวจล่าสุด"], ...data.devices.filter(device => device.active).map(device => [device.name, device.ip, labels.type[device.type] || labels.type.other, getNetwork(device.networkId)?.name || "", device.department || "", device.location || "", labels.status[device.status], device.response ?? "", fullDate(device.checkedAt)])];
  const csv = "\uFEFF" + rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\r\n");
  const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = historyOnly ? "mini-network-monitor-history.csv" : "mini-network-monitor-devices.csv";
  link.click();
  URL.revokeObjectURL(url);
  showToast("ดาวน์โหลดรายงาน CSV แล้ว");
}

function onClick(event) {
  const action = event.target.closest("[data-action]");
  if (action) {
    if (action.dataset.action === "add-device") {
      if (!data.networks.some(network => network.active)) {
        showToast("เพิ่มเครือข่ายก่อนเพิ่มอุปกรณ์");
        window.location.href = "network.php";
      } else openDeviceForm();
    } else if (action.dataset.action === "add-network") openNetworkForm();
    else if (action.dataset.action === "export") exportCsv(false);
    else if (action.dataset.action === "export-history") exportCsv(true);
    return;
  }
  const editDevice = event.target.closest("[data-edit-device]");
  if (editDevice) {
    const device = getDevice(editDevice.dataset.editDevice);
    if (device) openDeviceForm(device);
    return;
  }
  const editNetwork = event.target.closest("[data-edit-network]");
  if (editNetwork) {
    const network = getNetwork(editNetwork.dataset.editNetwork);
    if (network) openNetworkForm(network);
    return;
  }
  const acknowledge = event.target.closest("[data-ack-alert]");
  if (acknowledge) {
    const alert = data.alerts.find(item => item.id === acknowledge.dataset.ackAlert);
    if (alert) {
      alert.acknowledged = true;
      alert.acknowledgedAt = new Date().toISOString();
      alert.acknowledgedBy = "เจ้าหน้าที่ระบบ";
      if (saveData()) {
        render();
        showToast("ทำเครื่องหมายรับทราบแล้ว");
      }
    }
  }
}

function bind(id, eventName, handler) {
  const element = document.getElementById(id);
  if (element) element.addEventListener(eventName, handler);
}

document.addEventListener("click", onClick);
bind("entity-form", "submit", submitForm);
bind("modal-close", "click", closeModal);
bind("cancel-modal", "click", closeModal);
bind("modal-backdrop", "click", event => {
  if (event.target.id === "modal-backdrop") closeModal();
});
document.addEventListener("keydown", event => {
  if (event.key === "Escape") closeModal();
});
["device-search", "device-status-filter", "device-network-filter"].forEach(id => bind(id, id.includes("search") ? "input" : "change", renderDevices));
["history-search", "history-status-filter", "history-date-filter"].forEach(id => bind(id, id.includes("search") ? "input" : "change", renderHistory));
["alert-search", "alert-filter"].forEach(id => bind(id, id.includes("search") ? "input" : "change", renderAlerts));
bind("mobile-menu", "click", () => document.getElementById("sidebar").classList.toggle("open"));
bind("banner-close", "click", () => document.querySelector(".demo-banner")?.remove());
bind("save-settings", "click", () => {
  const interval = Number(document.getElementById("interval-setting").value);
  const retries = Number(document.getElementById("retries-setting").value);
  if (!Number.isInteger(interval) || interval < 15 || interval > 3600 || !Number.isInteger(retries) || retries < 1 || retries > 10) {
    showToast("ค่ารอบตรวจต้องอยู่ระหว่าง 15–3600 วินาที และจำนวนครั้ง 1–10");
    return;
  }
  data.settings = { interval, retries };
  if (saveData()) showToast("บันทึกการตั้งค่าตัวอย่างแล้ว");
});
bind("reset-demo", "click", () => {
  if (!window.confirm("คืนค่าข้อมูลตัวอย่างและลบข้อมูลที่บันทึกในเบราว์เซอร์นี้หรือไม่?")) return;
  data = clone(initialData);
  if (saveData()) {
    render();
    showToast("คืนค่าข้อมูลตัวอย่างแล้ว");
  }
});

render();
