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
    const r = await fetch('/page/backend/notify/unread_count.php', {cache:'no-store', credentials:'include'});
    const d = await r.json();
    if (!d.ok) return;
    setChatBadge(d.count || 0);
  } catch(e) {}
}

function setChatBadge(n) {
  const badge = document.getElementById('chatBadge');
  if (!badge) return;
  if (!n || n <= 0) {
    badge.setAttribute('hidden', '');
  } else {
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.removeAttribute('hidden');
  }
}

setInterval(pollNoti, 15000);
pollNoti();


setInterval(pollNoti, 20000);
pollNoti();
refreshChatBadge(); // เรียกครั้งแรกตอนโหลดหน้า
