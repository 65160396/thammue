function setChatBadge(n) {
  const b = document.getElementById('chatBadge');
  if (!b) return;
  const c = Number(n) || 0;
  if (c <= 0) {
    b.setAttribute('hidden', '');
  } else {
    b.textContent = c > 99 ? '99+' : String(c);
    b.removeAttribute('hidden');
  }
}

async function refreshChatBadge() {
  try {
    const r = await fetch('/page/backend/notify/unread_count.php', {credentials:'include', cache:'no-store'});
    const d = await r.json();
    if (d && d.ok) setChatBadge(d.count || 0);
  } catch(e) {}
}

async function pollNoti() {
  try {
    const r = await fetch('/page/backend/notify/list.php', {cache:'no-store', credentials:'include'});
    const d = await r.json();
    if (!d.ok || !Array.isArray(d.items)) { await refreshChatBadge(); return; }

    // แสดง toast (ถ้าต้องการ) + mark_read แต่ไม่ลืมรีเฟรช badge
    for (const n of d.items) {
      const el = document.getElementById('flash');
      if (el) {
        el.textContent = n.title || 'มีข้อความใหม่จากแชท';
        el.classList.add('show');
        setTimeout(()=>el.classList.remove('show'), 2500);
        el.onclick = () => location.href = n.url;
      }
      const fd = new FormData(); fd.append('id', n.id);
      fetch('/page/backend/notify/mark_read.php', { method:'POST', body:fd, credentials:'include' });
    }

    await refreshChatBadge(); // ✅ อัปเดตตัวเลขบนไอคอน
  } catch (e) { /* ignore */ }
}

setInterval(pollNoti, 20000);
pollNoti();
refreshChatBadge(); // เรียกครั้งแรกตอนโหลดหน้า
