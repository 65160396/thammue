/* เมนูสามจุดสำหรับการ์ดสินค้า—รียูสได้ทุกหน้า
   การ์ดต้องมี: <article class="ex-card-x" data-id="ITEM_ID" data-user="OWNER_ID">…</article>
   ต้องมี /js/me.js ก่อนไฟล์นี้ (ใช้ Me.get())
*/
(function () {
  const API = {
    del   : '/page/backend/ex_item_delete.php',
    report: '/page/backend/ex_item_report.php',
    edit  : id => `/page/ex_item_edit.html?id=${encodeURIComponent(id)}`,
  };

  function q(sel, root=document){ return root.querySelector(sel); }
  function qq(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

  async function getMyId() {
    try{
      const me = (window.Me && typeof Me.get==='function') ? await Me.get() : null;
      return me?.ok ? (me.user?.id ?? null) : null;
    }catch{ return null; }
  }

  function ensureKebab(card){
    if (!card || card.querySelector('.ex-card-kebab')) return; // มีแล้วข้าม
    const btn  = document.createElement('button');
    btn.className = 'ex-card-kebab'; btn.type='button'; btn.title='ตัวเลือกเพิ่มเติม'; btn.textContent='⋮';
    const menu = document.createElement('div'); menu.className='ex-card-menu'; menu.setAttribute('role','menu'); menu.setAttribute('aria-hidden','true');
    card.appendChild(btn); card.appendChild(menu);
    mount(card, btn, menu);
  }

  function closeAll(){
    qq('.ex-card-menu.open').forEach(m=>{
      m.classList.remove('open'); m.setAttribute('aria-hidden','true');
      m.parentElement?.querySelector('.ex-card-kebab')?.setAttribute('aria-expanded','false');
    });
  }

  function mount(card, btn, menu){
    const itemId  = card.getAttribute('data-id');
    const ownerId = String(card.getAttribute('data-user') || card.getAttribute('data-owner') || '');

    // ใส่เมนูตามสถานะเจ้าของ (จะอัปเดทอีกทีหลังรู้ myId)
    menu.innerHTML = `<button type="button" data-act="report" class="ex-menu-muted"><span>🚩</span> รายงานปัญหา</button>`;

    // เปิด/ปิด
    btn.addEventListener('click', (e)=>{
      e.stopPropagation();
      const open = !menu.classList.contains('open');
      closeAll();
      if (open){ menu.classList.add('open'); btn.setAttribute('aria-expanded','true'); menu.setAttribute('aria-hidden','false'); }
    });
    document.addEventListener('click', closeAll);

    // คลิกเมนู
    menu.addEventListener('click', async (e)=>{
      const b = e.target.closest('button[data-act]'); if (!b) return;
      const act=b.dataset.act;

      if (act==='edit'){ location.href = API.edit(itemId); return; }

      if (act==='delete'){
        if (!confirm('ยืนยันลบสินค้านี้?')) return;
        const fd=new FormData(); fd.append('item_id', itemId);
        const r = await fetch(API.del, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
        const t = await r.text(); let d=null; try{ d=JSON.parse(t);}catch(e){ alert('ลบไม่สำเร็จ: ' + t.slice(0,100)); return; }
        if (d?.ok){ card.remove(); } else { alert('ลบไม่สำเร็จ: ' + (d?.error||'unknown')); }
        return;
      }

      if (act==='report'){
        const reason = prompt('โปรดระบุสาเหตุที่รายงาน');
        if (!reason) return;
        const fd=new FormData(); fd.append('item_id', itemId); fd.append('reason', reason);
        const r = await fetch(API.report, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
        const t = await r.text(); let d=null; try{ d=JSON.parse(t);}catch(e){ alert('รายงานไม่สำเร็จ: ' + t.slice(0,100)); return; }
        alert(d?.ok ? 'ขอบคุณสำหรับการรายงาน' : ('รายงานไม่สำเร็จ: ' + (d?.error||'unknown')));
      }
    });

    // ปรับเมนูเมื่อรู้ว่าเป็นเจ้าของ
    (async ()=>{
      const myId = await getMyId();
      if (myId && String(myId) === ownerId) {
        menu.innerHTML = `
          <button type="button" data-act="edit"><span>✏️</span> แก้ไขสินค้า</button>
          <button type="button" data-act="delete" class="ex-menu-danger"><span>🗑️</span> ลบสินค้า</button>
        `;
      }
    })();
  }

  // สแกนและติดตั้งให้ทุกการ์ด
  function initKebab(root=document){
    qq('.ex-card-x[data-id]', root).forEach(ensureKebab);
  }

  // เผยฟังก์ชันให้เรียกตอนที่เพิ่งเติมการ์ดแบบ infinite scroll
  window.ExCardsKebab = { init: initKebab };

  // เริ่มทำงานอัตโนมัติเมื่อ DOM พร้อม
  if (document.readyState !== 'loading') initKebab();
  else document.addEventListener('DOMContentLoaded', initKebab);

  // เฝ้าดู DOM ถ้ามีการ์ดถูกเติมภายหลัง
  const mo = new MutationObserver(ms=>{
    for (const m of ms){
      m.addedNodes?.forEach(n=>{
        if (n.nodeType===1) {
          if (n.matches?.('.ex-card-x[data-id]')) ensureKebab(n);
          else initKebab(n);
        }
      });
    }
  });
  mo.observe(document.documentElement, {childList:true, subtree:true});
})();
