// /exchangepage/public/js/notifications.badge.js
// ใช้ตัวแปร global ถ้ามีอยู่แล้วจะไม่ทับ (กันประกาศซ้ำ)
window.API_BASE = window.API_BASE || '/exchangepage/api';

async function refreshNotiBadge() {
  const el = document.getElementById('notiBadge');
  if (!el) return;
  try {
    const res = await fetch(`${window.API_BASE}/notifications/count.php`, {
      credentials: 'include', cache: 'no-store'
    });
    if (!res.ok) return;
    const { unread = 0 } = await res.json();
    el.textContent = unread;
    el.hidden = unread <= 0;
  } catch (e) {}
}

// โหลดครั้งแรก + อัปเดตทุก 30 วินาที
document.addEventListener('DOMContentLoaded', () => {
  refreshNotiBadge();
  setInterval(refreshNotiBadge, 30000);
  window.addEventListener('thammue:notifications:changed', refreshNotiBadge);
});
