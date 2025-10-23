/* /js/ex.index.feed.js */
(function () {
  'use strict';

  const MeReader = {
    async id() {
      try { if (window.Me?.get) { const m = await Me.get(); if (m?.ok && m.user?.id != null) return String(m.user.id); } }
      catch {}
      return null;
    },
    async province() {
      try {
        if (window.Me?.get) {
          const m = await Me.get(); const u = m?.user || {};
          return u.province || u.addr_province || u.address?.province || null;
        }
      } catch {}
      return null;
    }
  };

  const API = {
    recent : (limit=20, offset=0) => `/page/backend/ex_feed_recent.php?limit=${limit}&offset=${offset}`,
    byCat  : (catId, limit=20, offset=0) => `/page/backend/ex_feed_by_category.php?category_id=${encodeURIComponent(catId)}&limit=${limit}&offset=${offset}`,
    nearby : (province, limit=20, offset=0) => `/page/backend/ex_feed_nearby.php?province=${encodeURIComponent(province)}&limit=${limit}&offset=${offset}`,
    del    : `/page/backend/ex_item_delete.php`,
    report : `/page/backend/ex_item_report.php`,
    view   : (id) => `/page/ex_item_view.html?id=${encodeURIComponent(id)}`,
    edit   : (id) => `/page/ex_item_edit.html?id=${encodeURIComponent(id)}`
  };

  async function apiGet(url){
    const r = await fetch(url, { credentials:'include', cache:'no-store' });
    const t = await r.text();
    try { return JSON.parse(t); } catch { console.error('Bad JSON from', url, t); return null; }
  }
  async function apiPost(url, fd){
    const r = await fetch(url, { method:'POST', body:fd, credentials:'include', cache:'no-store' });
    const t = await r.text();
    try { return JSON.parse(t); } catch { console.error('Bad JSON from POST', url, t); return null; }
  }

  const CATEGORY_MAP = {1:'แฮนเมด',2:'ของประดิษฐ์',3:'ของใช้ทั่วไป',4:'เสื้อผ้า',5:'หนังสือ',6:'ของสะสม'};
  const ph = '/img/sample/placeholder.png';
  const esc = s => String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const filterOthers = (items, myId) => !myId ? items : items.filter(x => String(x.user_id) !== String(myId));

  function cardHTML(it){
    const img = it.thumbnail_url || ph;
    const meta = [
      it.category_id ? `หมวด: ${CATEGORY_MAP[it.category_id] || 'ไม่ระบุ'}` : null,
      it.province || null
    ].filter(Boolean).join(' · ');
    return `
      <article class="ex-card-x" data-id="${it.id}" data-user="${it.user_id}">
        <a class="ex-card-thumb" href="${API.view(it.id)}" style="background-image:url('${img}')" aria-label="${esc(it.title || 'ดูรายละเอียด')}"></a>
        <div class="ex-card-body">
          <h3>${esc(it.title || '-')}</h3>
          <p class="ex-sub">${esc(meta)}</p>
          <a class="ex-btn tiny" href="${API.view(it.id)}">ดูรายละเอียด</a>
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

    if (myId && myId === String(ownerId)) {
      menu.innerHTML = `
        <button type="button" data-act="edit">✏️ แก้ไขสินค้า</button>
        <button type="button" data-act="delete" class="ex-menu-danger">🗑️ ลบสินค้า</button>
      `;
    } else {
      menu.innerHTML = `<button type="button" data-act="report" class="ex-menu-muted">🚩 รายงานปัญหา</button>`;
    }

    function closeAll(){
      document.querySelectorAll('.ex-card-menu.open').forEach(m=>{
        m.classList.remove('open');
        m.parentElement?.querySelector('.ex-card-kebab')?.setAttribute('aria-expanded','false');
        m.setAttribute('aria-hidden','true');
      });
    }
    btn.addEventListener('click', (e)=>{
      e.stopPropagation();
      const open = menu.classList.contains('open');
      closeAll();
      if (!open){ menu.classList.add('open'); btn.setAttribute('aria-expanded','true'); menu.setAttribute('aria-hidden','false'); }
    });
    document.addEventListener('click', closeAll);

    menu.addEventListener('click', async (e)=>{
      const b = e.target.closest('button[data-act]'); if (!b) return;
      const act = b.getAttribute('data-act');
      if (act === 'edit')  { location.href = API.edit(itemId); return; }
      if (act === 'delete'){ if(!confirm('ยืนยันลบสินค้านี้?'))return; const fd=new FormData(); fd.append('item_id',itemId); const res=await apiPost(API.del,fd); res?.ok?card.remove():alert('ลบไม่สำเร็จ'); return; }
      if (act === 'report'){ const reason=prompt('โปรดระบุเหตุผลที่รายงาน'); if(!reason)return; const fd=new FormData(); fd.append('item_id',itemId); fd.append('reason',reason); const res=await apiPost(API.report,fd); alert(res?.ok?'ขอบคุณสำหรับการรายงาน':'รายงานไม่สำเร็จ'); }
    });
  }

  function renderInto(grid, items, myId){
    if (!grid) return;
    items = Array.isArray(items) ? items : [];
    const list = filterOthers(items, myId);
    if (list.length === 0) {
      grid.innerHTML = `<div class="center muted tiny" style="padding:10px 0">ยังไม่มีรายการ</div>`;
      return;
    }
    grid.innerHTML = list.map(cardHTML).join('');
    grid.querySelectorAll('.ex-card-x').forEach(c => mountKebab(c, myId));
  }

  (function injectCSS(){
    if (document.getElementById('ex-index-kebab-style')) return;
    const el = document.createElement('style'); el.id='ex-index-kebab-style';
    el.textContent = `
      .ex-card-x{ position:relative; }
      .ex-card-kebab{ position:absolute; top:8px; right:8px; z-index:2; width:34px; height:34px; display:grid; place-items:center; border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; box-shadow:0 2px 10px rgba(0,0,0,.06); }
      .ex-card-menu{ position:absolute; top:44px; right:8px; z-index:5; min-width:160px; border:1px solid #e5e7eb; background:#fff; border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,.12); padding:6px; display:none; }
      .ex-card-menu.open{ display:block; }
      .ex-card-menu button{ display:flex; align-items:center; gap:8px; width:100%; padding:8px 10px; border:0; background:#fff; border-radius:8px; cursor:pointer; text-align:left; }
      .ex-card-menu button:hover{ background:#f8fafc; }
      .ex-menu-danger{ color:#b91c1c; }
      .ex-menu-muted{ color:#0f172a; opacity:.85; }
    `;
    document.head.appendChild(el);
  })();

  document.addEventListener('DOMContentLoaded', async () => {
    const myId     = await MeReader.id();
    const province = await MeReader.province();

    const nearbyGrid  = document.getElementById('nearbyGrid');
    const booksGrid   = document.getElementById('booksGrid');
    const clothesGrid = document.getElementById('clothesGrid');

    // 1) ใกล้คุณ (แทนบล็อกแรก)
    if (province) {
      try { const nr = await apiGet(API.nearby(province, 20, 0)); if (nr?.ok) renderInto(nearbyGrid, nr.items, myId); }
      catch(e){ console.error(e); }
    } else {
      // ถ้าไม่รู้จังหวัด ให้ fallback เป็น recent แต่จะกรองของตัวเองออก
      try { const r = await apiGet(API.recent(20,0)); if (r?.ok) renderInto(nearbyGrid, r.items, myId); }
      catch(e){ console.error(e); }
    }

    // 2) หมวด: หนังสือ (5) / เสื้อผ้า (4) — กรอง “ของตัวเอง” ออก
    try { const r1 = await apiGet(API.byCat(5, 8, 0)); if (r1?.ok) renderInto(booksGrid, r1.items, myId); } catch(e){}
    try { const r2 = await apiGet(API.byCat(4, 8, 0)); if (r2?.ok) renderInto(clothesGrid, r2.items, myId); } catch(e){}
  });
})();
