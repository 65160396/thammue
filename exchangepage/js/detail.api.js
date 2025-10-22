// /exchangepage/public/js/detail.api.js
// API layer สำหรับหน้า detail

const API_BASE = '/exchangepage/api';

export async function getItem(id) {
  const r = await fetch(`${API_BASE}/items/show.php?id=${encodeURIComponent(id)}`, {
    cache: 'no-store', credentials: 'include'
  });
  const j = await r.json();
  if (!r.ok || !j?.ok) throw new Error(j?.error || 'ไม่พบสินค้า');
  return (j.data ?? j); // รองรับ 2 รูปแบบ
}

export async function toggleFavorite(itemId) {
  const fd = new FormData();
  fd.append('item_id', itemId);
  fd.append('action', 'toggle');
  const r = await fetch(`${API_BASE}/favorites/toggle.php`, {
    method: 'POST', body: fd, credentials: 'include'
  });
  if (r.status === 401) throw new Error('AUTH');
  const j = await r.json();
  if (j?.ok === false) throw new Error(j.error || 'toggle failed');
  return j.status; // 'added' | 'removed'
}

// (ถ้าจะใช้เปิดห้องแชท แนะนำใช้พารามิเตอร์ไอเท็มที่ต้องการคุยกับเจ้าของ)
export async function openChatWithOwnerOf(itemId) {
  const r = await fetch(`${API_BASE}/chat/open.php?with_owner_of=${encodeURIComponent(itemId)}`, {
    cache: 'no-store', credentials: 'include'
  });
  if (r.status === 401) throw new Error('AUTH');
  const j = await r.json();
  if (!j.ok || !j.conv_id) throw new Error(j.error || 'open chat failed');
  return j.conv_id;
}

export async function listMyItems() {
  const r = await fetch(`${API_BASE}/items/list.php?mine=1&limit=50`, {
    cache: 'no-store', credentials: 'include'
  });
  if (r.status === 401) throw new Error('AUTH');
  const j = await r.json();
  return j?.items || j?.data?.items || [];
}

export async function createRequest({ itemId, offerItemId, message }) {
  const fd = new FormData();
  fd.append('item_id', String(itemId));
  if (offerItemId) fd.append('requester_item_id', String(offerItemId));
  if (message) fd.append('message', message);

  const r = await fetch(`${API_BASE}/requests/create.php`, {
    method: 'POST', body: fd, credentials: 'include'
  });
  if (r.status === 401) throw new Error('AUTH');
  const j = await r.json().catch(() => ({ ok: false }));
  if (!j.ok && !j.flash) throw new Error(j.error || 'ส่งคำขอไม่สำเร็จ');
  return true;
}
