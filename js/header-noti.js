// /page/js/header-noti.js
document.addEventListener('DOMContentLoaded', () => {
  const API_BASE = '/page/backend';  // << ปรับตามโครงจริงของคุณ
  const badge = document.querySelector('#notiBadge');
  if (!badge) return;

  async function refreshCount() {
    try {
      const r = await fetch(`${API_BASE}/notifications/count.php`, { credentials: 'include' });
      const j = await r.json();
      const n = Number(j.unread || 0);
      if (n > 0) {
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.hidden = false;
      } else {
        badge.hidden = true;
      }
    } catch (err) {
      // เงียบๆไป ไม่ทำให้หน้าแตก
    }
  }

  // ครั้งแรก + ทุก 30 วินาที
  refreshCount();
  setInterval(refreshCount, 30_000);

  // ให้หน้า notifications ยิง event นี้หลัง mark_read เสร็จ
  window.addEventListener('thammue:notifications:changed', refreshCount);
});
