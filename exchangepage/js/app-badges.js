// /thammue/js/app-badges.js

/* ---------- Utils ---------- */
const num = v => {
  const n = Number(v);
  return Number.isFinite(n) && n >= 0 ? n : null;
};
const getEl = id => document.getElementById(id);
const getCount = el => num(el?.dataset?.count) ?? 0;
const setBadge = (el, n) => {
  if (!el) return;
  const v = Math.max(0, n | 0);
  el.dataset.count = String(v);
  el.textContent = v > 99 ? '99+' : String(v);
  el.hidden = v <= 0;
};
const addBadge = (el, delta) => setBadge(el, getCount(el) + (delta | 0));

async function j(url) {
  try {
    const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
    if (!res.ok) { console.warn('[badge] HTTP', res.status, url); return null; }
    return await res.json();
  } catch (e) { console.warn('[badge] fetch', url, e); return null; }
}
const arrLen = p =>
  Array.isArray(p) ? p.length :
    (p && Array.isArray(p.items)) ? p.items.length :
      (p && Array.isArray(p.data)) ? p.data.length : null;

/* ---------- Favorites ---------- */
/* /api/favorites/list.php → array หรือ {items:[]} หรือ {total/count} */
async function refreshFavBadge() {
  const el = getEl('favBadge'); if (!el) return;
  const data = await j('../api/favorites/list.php');
  if (!data) return;
  const len = arrLen(data);
  if (len !== null) return setBadge(el, len);
  const v = num(data.total) ?? num(data.count);
  if (v !== null) return setBadge(el, v);
  console.warn('[badge] favorites payload not recognized', data);
}

/* ---------- Requests (incoming pending) ---------- */
/* /api/requests/list_incoming.php → array ของคำขอ (มักมี status) */
/* ---------- Requests (pending only) ---------- */
/* ใช้ total ที่เซิร์ฟเวอร์คำนวณให้เลย เพื่อตรง/เร็ว/ไม่พังกับรูปแบบ payload */
async function refreshReqBadge() {
  const el = document.getElementById('reqBadge'); if (!el) return;
  try {
    const r = await fetch('../api/requests/list_incoming.php?status=pending&limit=1', { credentials: 'include', cache: 'no-store' });
    if (!r.ok) return;
    const d = await r.json();
    const total = Number((d?.data?.total) ?? d?.total ?? 0) || 0;
    el.dataset.count = String(total);
    el.textContent = total > 99 ? '99+' : String(total);
    el.hidden = total <= 0;
  } catch (_) { }
}



/* ---------- Chat (unread) ---------- */
async function refreshChatBadge() {
  const el = document.getElementById('chatBadge'); if (!el) return;
  const data = await fetch('../api/chat/unread_count.php', { credentials: 'include', cache: 'no-store' }).then(r => r.ok ? r.json() : null).catch(() => null);
  if (data && data.ok && typeof data.unread !== 'undefined') {
    const n = Number(data.unread) || 0;
    el.dataset.count = String(n);
    el.textContent = n > 99 ? '99+' : String(n);
    el.hidden = n <= 0;
  }
}

/* ---------- Boot & Poll ---------- */
async function refreshAll() { await Promise.all([refreshFavBadge(), refreshReqBadge(), refreshChatBadge()]); }
document.addEventListener('DOMContentLoaded', refreshAll);

let pollId = null;
function startPolling() { if (!pollId) pollId = setInterval(() => { if (!document.hidden) refreshAll(); }, 30000); }
function stopPolling() { if (pollId) { clearInterval(pollId); pollId = null; } }
document.addEventListener('visibilitychange', () => { if (document.hidden) stopPolling(); else { refreshAll(); startPolling(); } });
startPolling();

/* ---------- Global Events สำหรับอัปเดตแบบทันที ---------- */
/* เพิ่ม/ลดโดยไม่ต้องรอ API:  window.dispatchEvent(new CustomEvent('badge:delta',{detail:{id:'reqBadge',delta:-1}})) */
window.addEventListener('badge:delta', e => {
  const { id, delta } = e.detail || {};
  const el = id && getEl(id);
  if (el && typeof delta === 'number') addBadge(el, delta);
});
/* เซ็ตค่าตรง ๆ: window.dispatchEvent(new CustomEvent('badge:set',{detail:{id:'chatBadge',value:0}})) */
window.addEventListener('badge:set', e => {
  const { id, value } = e.detail || {};
  const el = id && getEl(id);
  if (el && typeof value !== 'undefined' && value !== null && !Number.isNaN(Number(value)))
    setBadge(el, Number(value));
});
/* รีเฟรชจากเซิร์ฟเวอร์เฉพาะชิ้น: window.dispatchEvent(new CustomEvent('badge:refresh',{detail:'chat'})) */
window.addEventListener('badge:refresh', e => {
  const which = e.detail;
  if (which === 'fav') refreshFavBadge();
  if (which === 'req') refreshReqBadge();
  if (which === 'chat') refreshChatBadge();
});
