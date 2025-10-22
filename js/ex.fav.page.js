
/* /js/ex.fav.page.js */
(async function(){
  const grid = document.getElementById('favGrid');

  async function loadFavs(){
    const res = await fetch('/page/backend/ex_fav_list.php', {credentials:'include'}).then(r=>r.json());
    if(!res.ok){ throw new Error(res.error||'โหลดรายการโปรดไม่ได้'); }
    grid.innerHTML = '';
    (res.items||[]).forEach(it=>{
      const card = document.createElement('div');
      card.className = 'ex-fav-card';
      card.innerHTML = `
        ${it.thumb ? '<img src="'+it.thumb+'" alt="">' : ''}
        <div style="margin-top:8px"><b>#${it.id}</b> ${it.title||'-'}</div>
        <div class="ex-actions" style="margin-top:8px">
          <a class="ex-btn" href="/page/ex_detail.html?id=${it.id}">ดูสินค้า</a>
          <button class="ex-btn secondary" data-id="${it.id}" data-act="unfav">เอาออก</button>
        </div>
      `;
      grid.appendChild(card);
    });
    if(!grid.children.length){
      grid.innerHTML = '<div class="ex-muted">ยังไม่มีสินค้าในรายการโปรด</div>';
    }
  }

  grid.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('button[data-act="unfav"]'); if(!btn) return;
    const id = parseInt(btn.getAttribute('data-id'),10);
    const res = await fetch('/page/backend/ex_fav_toggle.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'include',
      body: JSON.stringify({item_id: id, action: 'remove'})
    }).then(r=>r.json());
    if(!res.ok){ alert(res.error||'เอาออกไม่สำเร็จ'); return; }
    await loadFavs();
  });

  try{ await loadFavs(); }catch(e){ alert(e.message); }
})();
