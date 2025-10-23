/* /js/ex.index.page.js
 * หน้า Index: โหลดฟีด + การ์ด + เมนู 3 จุด (แก้ไข/ลบ/รีพอร์ต)
 * ต้องการ API:
 *  - /page/backend/ex_feed_recent.php?limit=&offset=
 *  - /page/backend/ex_feed_by_category.php?category_id=&limit=&offset=
 *  - /page/backend/ex_feed_nearby.php?province=&limit=&offset=
 *  - /page/backend/ex_item_delete.php   (POST: item_id)
 *  - /page/backend/ex_item_report.php   (POST: item_id, reason)
 */

/* ---------- 1) CSS เสริมสำหรับเมนู 3 จุดบนการ์ด (ฉีดเข้าเอกสารให้เลย) ---------- */
(function injectKebabCSS(){
  if (document.getElementById('ex-index-kebab-style')) return;
  const css = `
  .ex-card-x{ position:relative; }
  .ex-card-kebab{
    position:absolute; top:8px; right:8px; z-index:2;
    width:34px; height:34px; display:grid; place-items:center;
    border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer;
    box-shadow:0 2px 10px rgba(0,0,0,.06);
  }
  .ex-card-kebab:focus{ outline:3px solid rgba(14,165,233,.25); outline-offset:2px; }
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
  const el = document.createElement('style');
  el.id = 'ex-index-kebab-style';
  el.textContent = css;
  document.head.appendChild(el);
})();

/* ---------- 2) Utility: me (เอา id/province จาก global ต่าง ๆ ถ้ามี) ---------- */
const Me = {
  getId(){
    try{
      if (window.__ME && (__ME.id || __ME.user_id)) return __ME.id || __ME.user_id;
      if (window.ME && (ME.id || ME.user_id)) return ME.id || ME.user_id;
      if (window.me && (me.id || me.user_id)) return me.id || me.user_id;
    }catch(e){}
    return null;
  },
  getProvince(){
    try{
      const candidates = [window.__ME, window.ME, window.me];
      for (const m of candidates){
        if (!m) continue;
        if (m.province) return m.province;
        if (m.addr_province) return m.addr_province;
        if (m.address && m.address.province) return m.address.province;
      }
    }catch(e){}
    return null;
  }
};

/* ---------- 3) จุดเชื่อม API ---------- */
const API = {
  recent: (limit=20, offset=0) => `/page/backend/ex_feed_recent.php?limit=${limit}&offset=${offset}`,
  byCat: (catId, limit=20, offset=0) => `/page/backend/ex_feed_by_category.php?category_id=${encodeURIComponent(catId)}&limit=${limit}&offset=${offset}`,
  nearby: (province, limit=20, offset=0) => `/page/backend/ex_feed_nearby.php?province=${encodeURIComponent(province)}&limit=${limit}&offset=${offset}`,
  del:   `/page/backend/ex_item_delete.php`,
  report:`/page/backend/ex_item_report.php`,
  view:  (id) => `/page/ex_item_view.html?id=${encodeURIComponent(id)}`,
  edit:  (id) => `/page/ex_item_edit.html?id=${encodeURIComponent(id)}`
};

/* ---------- 4) โหลดข้อมูล (fetch + ปลอดแคช) ---------- */
async function apiGet(url){
  const r = await fetch(url, { credentials:'include', cache:'no-store' });
  const t = await r.text();
  try{
    return JSON.parse(t);
  }catch(e){
    console.error('Bad JSON from', url, t);
    return null;
  }
}

async function apiPost(url, formData){
  const r = await fetch(url, { method:'POST', body:formData, credentials:'include', cache:'no-store' });
  const t = await r.text();
  try{
    return JSON.parse(t);
  }catch(e){
    console.error('Bad JSON from POST', url, t);
    return null;
  }
}

/* ---------- 5) เรนเดอร์การ์ด + เมนู 3 จุด ---------- */
const CATEGORY_MAP = {
  1:'แฮนเมด', 2:'ของประดิษฐ์', 3:'ของใช้ทั่วไป', 4:'เสื้อผ้า', 5:'หนังสือ', 6:'ของสะสม'
};

function cardHTML(item){
  const img = item.thumbnail_url || '/img/sample/placeholder.png';
  const sub = [
    item.category_id ? `หมวด: ${CATEGORY_MAP[item.category_id] || 'ไม่ระบุ'}` : null,
    item.province || null
  ].filter(Boolean).join(' · ');

  return `
    <article class="ex-card-x" data-id="${item.id}" data-user="${item.user_id}">
      <a class="ex-card-thumb" href="${API.view(item.id)}" style="background-image:url('${img}')"
         aria-label="${escapeHtml(item.title || 'ดูรายละเอียด')}"></a>
      <div class="ex-card-body">
        <h3>${escapeHtml(item.title || '-')}</h3>
        <p class="ex-sub">${escapeHtml(sub || '')}</p>
        <a class="ex-btn tiny" href="${API.view(item.id)}">ดูรายละเอียด</a>
      </div>

      <button class="ex-card-kebab" type="button" aria-haspopup="menu" aria-expanded="false" title="ตัวเลือกเพิ่มเติม">
        ⋮
      </button>
      <div class="ex-card-menu" role="menu" aria-hidden="true"></div>
    </article>
  `;
}

function mountKebab(card){
  const btn  = card.querySelector('.ex-card-kebab');
  const menu = card.querySelector('.ex-card-menu');
  const itemId = card.getAttribute('data-id');
  const ownerId = card.getAttribute('data-user');
  const myId = String(Me.getId() ?? '');

  // เมนู: เจ้าของ = แก้ไข/ลบ, ผู้ชม = รีพอร์ต
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

  function closeAllMenus(){
    document.querySelectorAll('.ex-card-menu.open').forEach(m=>{
      m.classList.remove('open');
      const b = m.parentElement?.querySelector('.ex-card-kebab');
      b?.setAttribute('aria-expanded','false');
      m.setAttribute('aria-hidden','true');
    });
  }

  btn.addEventListener('click', (e)=>{
    e.stopPropagation();
    const isOpen = menu.classList.contains('open');
    closeAllMenus();
    if (!isOpen) {
      menu.classList.add('open');
      btn.setAttribute('aria-expanded','true');
      menu.setAttribute('aria-hidden','false');
    }
  });

  document.addEventListener('click', closeAllMenus);
  menu.addEventListener('click', async (e)=>{
    const b = e.target.closest('button[data-act]');
    if (!b) return;
    const act = b.getAttribute('data-act');

    if (act === 'edit') {
      location.href = API.edit(itemId);
      return;
    }

    if (act === 'delete') {
      if (!confirm('ยืนยันลบสินค้านี้?')) return;
      const fd = new FormData();
      fd.append('item_id', itemId);
      const res = await apiPost(API.del, fd);
      if (res?.ok) {
        // ลบการ์ดออกจาก DOM
        card.remove();
      } else {
        alert('ลบไม่สำเร็จ: ' + (res?.error || 'unknown'));
      }
      return;
    }

    if (act === 'report') {
      const reason = prompt('โปรดระบุสาเหตุที่รายงาน (เช่น สแปม, เนื้อหาไม่เหมาะสม ฯลฯ)');
      if (!reason) return;
      const fd = new FormData();
      fd.append('item_id', itemId);
      fd.append('reason', reason);
      const res = await apiPost(API.report, fd);
      if (res?.ok) {
        alert('ขอบคุณสำหรับการรายงาน เราได้รับข้อมูลแล้ว');
      } else {
        alert('รายงานไม่สำเร็จ: ' + (res?.error || 'unknown'));
      }
    }
  });
}

/* ---------- 6) เติมการ์ดลงกริด ---------- */
function renderInto(gridEl, items){
  if (!gridEl) return;
  if (!Array.isArray(items) || items.length === 0) {
    gridEl.innerHTML = `<div class="center muted tiny" style="padding:10px 0">ยังไม่มีรายการ</div>`;
    return;
  }
  gridEl.innerHTML = items.map(cardHTML).join('');
  gridEl.querySelectorAll('.ex-card-x').forEach(mountKebab);
}

/* ---------- 7) Escape HTML ---------- */
function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

/* ---------- 8) บูตหน้า: โหลดฟีดต่าง ๆ ---------- */
(async function initIndex(){
  // โหนดเป้าหมายในหน้า
  const recentGrid   = document.querySelector('.ex-section .ex-cards'); // บล็อกแรก "สินค้าที่คุณสนใจ"
  const booksGrid    = document.getElementById('booksGrid');   // หมวดหนังสือ
  const clothesGrid  = document.getElementById('clothesGrid'); // หมวดเสื้อผ้า

  // 8.1 ฟีดล่าสุด (ตัดสินใจว่า "คุณสนใจ" = ล่าสุดก่อน)
  try{
    const r = await apiGet(API.recent(20,0));
    if (r?.ok) renderInto(recentGrid, r.items);
  }catch(e){ console.error('recent feed error', e); }

  // 8.2 ฟีดตามหมวด: หนังสือ (5) / เสื้อผ้า (4)
  try{
    const r1 = await apiGet(API.byCat(5, 8, 0));
    if (r1?.ok) renderInto(booksGrid, r1.items);
  }catch(e){}
  try{
    const r2 = await apiGet(API.byCat(4, 8, 0));
    if (r2?.ok) renderInto(clothesGrid, r2.items);
  }catch(e){}

  // 8.3 ฟีด "ใกล้คุณ" (ถ้าต้องการ เพิ่มอีก Section ที่หน้า แล้วดึงจังหวัดผู้ใช้)
  // ตัวอย่าง (คุณสามารถเพิ่ม section และ grid เอง เช่น <div id="nearbyGrid"></div>)
  const province = Me.getProvince();
  const nearbyGrid = document.getElementById('nearbyGrid'); // สร้างใน HTML ถ้าจะใช้
  if (province && nearbyGrid) {
    try{
      const nr = await apiGet(API.nearby(province, 12, 0));
      if (nr?.ok) renderInto(nearbyGrid, nr.items);
    }catch(e){}
  }
})();
