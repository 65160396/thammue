/* /js/ex_index_header_page.js
 * ---------------------------------------
 * หน้าที่: โค้ดเฉพาะ "หน้า Index" เท่านั้น
 * - โหลดฟีดการ์ด (ล่าสุด, ตามหมวด, ใกล้คุณ)
 * - ปุ่ม 3 จุด (เจ้าของ = แก้ไข/ลบ, ผู้ชม = รายงาน)
 *
 * ข้อสำคัญ:
 * - ห้ามยุ่ง/ไม่เรียก exHeaderInit()
 * - ไม่แตะ dropdown/header/user menu ใด ๆ
 * - ถ้ามี /js/me.js อยู่แล้ว จะอ่าน user จาก Me.get() แบบ read-only
 * --------------------------------------- */

(function () {
  'use strict';

  /* ---------- Utilities: read-only current user ---------- */
  const MeReader = {
    async id() {
      try {
        if (window.Me && typeof Me.get === 'function') {
          const m = await Me.get();                   // no force; ไม่รีเฟรช session
          if (m?.ok && m.user?.id != null) return String(m.user.id);
        }
        if (window.CURRENT_USER?.id != null) return String(window.CURRENT_USER.id);
      } catch {}
      return null;
    },
    async province() {
      try {
        if (window.Me && typeof Me.get === 'function') {
          const m = await Me.get();
          const u = m?.user || {};
          return (
            u.province || u.addr_province ||
            (u.address && u.address.province) || null
          );
        }
      } catch {}
      return null;
    }
  };

  /* ---------- API endpoints (read-only) ---------- */
  const API = {
    recent  : (limit=20, offset=0) =>
      `/page/backend/ex_feed_recent.php?limit=${limit}&offset=${offset}`,
    byCat   : (catId, limit=20, offset=0) =>
      `/page/backend/ex_feed_by_category.php?category_id=${encodeURIComponent(catId)}&limit=${limit}&offset=${offset}`,
    nearby  : (province, limit=20, offset=0) =>
      `/page/backend/ex_feed_nearby.php?province=${encodeURIComponent(province)}&limit=${limit}&offset=${offset}`,
    del     : `/page/backend/ex_item_delete.php`,
    report  : `/page/backend/ex_item_report.php`,
    view    : (id) => `/page/ex_item_view.html?id=${encodeURIComponent(id)}`,
    edit    : (id) => `/page/ex_item_edit.html?id=${encodeURIComponent(id)}`,
  };

  async function apiGet(url){
    const r = await fetch(url, { credentials:'include', cache:'no-store' });
    const t = await r.text();
    try { return JSON.parse(t); }
    catch(e){ console.error('Bad JSON from', url, t); return null; }
  }
  async function apiPost(url, formData){
    const r = await fetch(url, { method:'POST', body:formData, credentials:'include', cache:'no-store' });
    const t = await r.text();
    try { return JSON.parse(t); }
    catch(e){ console.error('Bad JSON from POST', url, t); return null; }
  }

  /* ---------- render: card grid + kebab menu ---------- */
  const CATEGORY_MAP = {1:'แฮนเมด',2:'ของประดิษฐ์',3:'ของใช้ทั่วไป',4:'เสื้อผ้า',5:'หนังสือ',6:'ของสะสม'};

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  function cardHTML(item){
    const img = item.thumbnail_url || '/img/sample/placeholder.png';
    const meta = [
      item.category_id ? `หมวด: ${CATEGORY_MAP[item.category_id] || 'ไม่ระบุ'}` : null,
      item.province || item.addr_province || null
    ].filter(Boolean).join(' · ');
    return `
      <article class="ex-card-x" data-id="${item.id}" data-user="${item.user_id}">
        <a class="ex-card-thumb" href="${API.view(item.id)}" style="background-image:url('${img}')"
           aria-label="${escapeHtml(item.title || 'ดูรายละเอียด')}"></a>
        <div class="ex-card-body">
          <h3>${escapeHtml(item.title || '-')}</h3>
          <p class="ex-sub">${escapeHtml(meta)}</p>
          <a class="ex-btn tiny" href="${API.view(item.id)}">ดูรายละเอียด</a>
        </div>
        <button class="ex-card-kebab" type="button" aria-haspopup="menu" aria-expanded="false" title="ตัวเลือกเพิ่มเติม">⋮</button>
        <div class="ex-card-menu" role="menu" aria-hidden="true"></div>
      </article>
    `;
  }

  function mountKebab(card, myId){
    const btn  = card.querySelector('.ex-card-kebab');
    const menu = card.querySelector('.ex-card-menu');
    const itemId  = card.getAttribute('data-id');
    const ownerId = card.getAttribute('data-user');

    // เมนู: เจ้าของ = แก้ไข/ลบ, ผู้ชม = รายงาน
    if (myId && myId === String(ownerId)) {
      menu.innerHTML = `
        <button type="button" data-act="edit"><span>✏️</span> แก้ไขสินค้า</button>
        <button type="button" data-act="delete" class="ex-menu-danger"><span>🗑️</span> ลบสินค้า</button>
      `;
    } else {
      menu.innerHTML = `
        <button type="button" data-act="report" class="ex-menu-muted"><span>🚩</span> รายงานปัญหา</button>
      `;
    }

    function closeAll(){
      document.querySelectorAll('.ex-card-menu.open').forEach(m=>{
        m.classList.remove('open');
        const b = m.parentElement?.querySelector('.ex-card-kebab');
        b?.setAttribute('aria-expanded','false');
        m.setAttribute('aria-hidden','true');
      });
    }

    btn.addEventListener('click', (e)=>{
      e.stopPropagation();
      const opened = menu.classList.contains('open');
      closeAll();
      if (!opened) {
        menu.classList.add('open');
        btn.setAttribute('aria-expanded','true');
        menu.setAttribute('aria-hidden','false');
      }
    });
    document.addEventListener('click', closeAll);

    menu.addEventListener('click', async (e)=>{
      const b = e.target.closest('button[data-act]');
      if (!b) return;
      const act = b.getAttribute('data-act');

      if (act === 'edit') { location.href = API.edit(itemId); return; }

      if (act === 'delete') {
        if (!confirm('ยืนยันลบสินค้านี้?')) return;
        const fd = new FormData(); fd.append('item_id', itemId);
        const res = await apiPost(API.del, fd);
        if (res?.ok) card.remove();
        else alert('ลบไม่สำเร็จ: ' + (res?.error || 'unknown'));
        return;
      }

      if (act === 'report') {
        const reason = prompt('โปรดระบุสาเหตุที่รายงาน');
        if (!reason) return;
        const fd = new FormData(); fd.append('item_id', itemId); fd.append('reason', reason);
        const res = await apiPost(API.report, fd);
        alert(res?.ok ? 'ขอบคุณสำหรับการรายงาน' : 'รายงานไม่สำเร็จ');
      }
    });
  }

  function renderInto(gridEl, items, myId){
    if (!gridEl) return;
    if (!Array.isArray(items) || items.length === 0) {
      gridEl.innerHTML = `<div class="center muted tiny" style="padding:10px 0">ยังไม่มีรายการ</div>`;
      return;
    }
    gridEl.innerHTML = items.map(cardHTML).join('');
    gridEl.querySelectorAll('.ex-card-x').forEach(c => mountKebab(c, myId));
  }

  /* ---------- inject CSS สำหรับเมนู 3 จุด (ครั้งเดียว) ---------- */
  (function injectKebabCSS(){
    if (document.getElementById('ex-index-kebab-style')) return;
    const el = document.createElement('style');
    el.id = 'ex-index-kebab-style';
    el.textContent = `
      .ex-card-x{ position:relative; }
      .ex-card-kebab{
        position:absolute; top:8px; right:8px; z-index:2;
        width:34px; height:34px; display:grid; place-items:center;
        border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer;
        box-shadow:0 2px 10px rgba(0,0,0,.06);
      }
      .ex-card-menu{
        position:absolute; top:44px; right:8px; z-index:5; min-width:160px;
        border:1px solid #e5e7eb; background:#fff; border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,.12);
        padding:6px; display:none;
      }
      .ex-card-menu.open{ display:block; }
      .ex-card-menu button{
        display:flex; align-items:center; gap:8px; width:100%;
        padding:8px 10px; border:0; background:#fff; border-radius:8px; cursor:pointer; text-align:left;
      }
      .ex-card-menu button:hover{ background:#f8fafc; }
      .ex-menu-danger{ color:#b91c1c; }
      .ex-menu-muted{ color:#0f172a; opacity:.85; }
    `;
    document.head.appendChild(el);
  })();

  /* ---------- boot page (ไม่แตะ header) ---------- */
  document.addEventListener('DOMContentLoaded', async () => {
    const myId = await MeReader.id();             // read-only; ไม่รีเฟรช session
    const province = await MeReader.province();

    // โหนดเป้าหมาย
    const recentGrid  = document.querySelector('.ex-section .ex-cards'); // บล็อกแรก
    const booksGrid   = document.getElementById('booksGrid');
    const clothesGrid = document.getElementById('clothesGrid');
    const nearbyGrid  = document.getElementById('nearbyGrid');

    // 1) ล่าสุด
    try {
      const r = await apiGet(API.recent(20,0));
      if (r?.ok) renderInto(recentGrid, r.items, myId);
    } catch(e){ console.error('recent feed error', e); }

    // 2) ตามหมวด
    try {
      const r1 = await apiGet(API.byCat(5, 8, 0));   // หนังสือ
      if (r1?.ok) renderInto(booksGrid, r1.items, myId);
    } catch(e){}
    try {
      const r2 = await apiGet(API.byCat(4, 8, 0));   // เสื้อผ้า
      if (r2?.ok) renderInto(clothesGrid, r2.items, myId);
    } catch(e){}

    // 3) ใกล้คุณ (ถ้ามีจังหวัดผู้ใช้)
    if (province && nearbyGrid) {
      try {
        const nr = await apiGet(API.nearby(province, 12, 0));
        if (nr?.ok) renderInto(nearbyGrid, nr.items, myId);
      } catch(e){}
    }
  });
})();
