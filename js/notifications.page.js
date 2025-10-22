// /page/js/notifications.page.js
;(() => {
  function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}
  function fmt(s){try{const d=new Date(String(s).replace(' ','T'));return d.toLocaleString('th-TH',{dateStyle:'medium',timeStyle:'short'});}catch{return s;}}

  async function markRead(apiBase, ids){
    try{
      await fetch(`${apiBase}/notifications/mark_read.php`,{
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ids:Array.isArray(ids)?ids:[]})
      });
    }catch{}
    window.dispatchEvent(new CustomEvent('thammue:notifications:changed'));
  }

  function optimisticDecreaseBadge(badgeEl){
    if (!badgeEl || badgeEl.hidden) return;
    const n = parseInt((badgeEl.textContent||'0').replace('+',''))||0;
    const next = Math.max(0, n-1);
    badgeEl.textContent = next>99 ? '99+' : String(next);
    badgeEl.hidden = next===0;
  }

  function renderItem(container, item, apiBase, badgeSelector){
    const el = document.createElement('div');
    el.className = 'noti-item' + (item.is_read ? '' : ' unread');
    el.dataset.id = item.id;
    el.innerHTML = `
      <h3 class="noti-title">${esc(item.title)}</h3>
      ${item.body ? `<p class="noti-body">${esc(item.body)}</p>` : ''}
      <div class="noti-meta">${fmt(item.created_at)}</div>
    `;
    el.addEventListener('click', async () => {
      if (!item.is_read) {
        el.classList.remove('unread');
        const badge = document.querySelector(badgeSelector || '#notiBadge');
        optimisticDecreaseBadge(badge);
        await markRead(apiBase, [item.id]);
        item.is_read = true;
      }
      // ถ้าต้องการ redirect ไปหน้าอื่น ให้ใส่ที่หน้านั้นเอง
      // location.href = `/page/detailreq.html?nid=${encodeURIComponent(item.id)}`;
    });
    container.appendChild(el);
  }

  async function loadList({apiBase, limit, offset}){
    const r = await fetch(`${apiBase}/notifications/list.php?limit=${limit}&offset=${offset}`, {credentials:'include'});
    if (!r.ok) throw new Error(r.status);
    const j = await r.json();
    return Array.isArray(j.items) ? j.items : [];
  }

  // public API
  const NotificationsPage = {
    /**
     * init({
     *   apiBase: '/page/backend',
     *   list: '#notiList',
     *   empty: '#emptyState',
     *   loader: '#loader',
     *   btnAll: '#btnMarkAll',
     *   sentinel: '#sentinel',
     *   badge: '#notiBadge',
     *   limit: 20
     * })
     */
    init(opts = {}){
      const apiBase  = opts.apiBase || '/page/backend';
      const listEl   = document.querySelector(opts.list     || '#notiList');
      const emptyEl  = document.querySelector(opts.empty    || '#emptyState');
      const loaderEl = document.querySelector(opts.loader   || '#loader');
      const btnAllEl = document.querySelector(opts.btnAll   || '#btnMarkAll');
      const sentEl   = document.querySelector(opts.sentinel || '#sentinel');
      const badgeSel = opts.badge || '#notiBadge';
      const limit    = Math.max(1, Math.min(50, opts.limit || 20));

      if (!listEl || !sentEl) return;

      let offset = 0, loading = false, noMore = false;

      async function loadMore(){
        if (loading || noMore) return;
        loading = true; if (loaderEl) loaderEl.hidden = false;
        try{
          const items = await loadList({apiBase, limit, offset});
          if (offset === 0 && items.length === 0) { if (emptyEl) emptyEl.hidden = false; }
          else { if (emptyEl) emptyEl.hidden = true; }
          items.forEach(it => renderItem(listEl, it, apiBase, badgeSel));
          offset += items.length;
          if (items.length < limit) noMore = true;
        }catch(e){
          console.error('notifications list failed:', e);
        }finally{
          if (loaderEl) loaderEl.hidden = true;
          loading = false;
        }
      }

      if (btnAllEl){
        btnAllEl.addEventListener('click', async () => {
          await markRead(apiBase, []); // ว่าง = ทั้งหมด
          listEl.querySelectorAll('.noti-item.unread').forEach(x => x.classList.remove('unread'));
          const badge = document.querySelector(badgeSel);
          if (badge) badge.hidden = true;
        });
      }

      const io = new IntersectionObserver((entries)=>{
        for (const e of entries){ if (e.isIntersecting) loadMore(); }
      }, {rootMargin:'800px 0px 0px 0px'});
      io.observe(sentEl);

      loadMore();
    }
  };

  // export to window
  window.NotificationsPage = NotificationsPage;
})();
