const API_BASE = '/thammue/api';
async function refreshNotiBadge() {
  const el = document.getElementById('notiBadge');
  if (!el) return;
  try{
    const res = await fetch(`${API_BASE}/notifications/count.php`, {credentials:'include', cache:'no-store'});
    if(!res.ok) return;
    const {unread=0} = await res.json();
    el.textContent = unread;
    el.hidden = unread <= 0;
  }catch(e){}
}
// โหลดครั้งแรก + อัปเดตทุก 30 วินาที
document.addEventListener('DOMContentLoaded', () => {
  refreshNotiBadge();
  setInterval(refreshNotiBadge, 30000);

  // ให้หน้าอื่น ๆ สั่งรีเฟรชได้ (หลัง mark_read)
  window.addEventListener('thammue:notifications:changed', refreshNotiBadge);
});
