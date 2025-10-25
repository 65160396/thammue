// /js/ex.home.nearby.js  (v1)
(function () {
  const API_NEARBY = '/page/backend/ex_items_nearby.php'; // ปรับให้ตรง endpoint จริงถ้าชื่อไม่ตรง
  const grid = document.getElementById('nearbyGrid');
  if (!grid) return;

  fetch(API_NEARBY, { credentials:'include', cache:'no-store' })
    .then(r => r.text())
    .then(txt => {
      let j = null; try{ j = JSON.parse(txt); }catch{}
      const items = Array.isArray(j?.items) ? j.items
                  : Array.isArray(j?.data)  ? j.data
                  : Array.isArray(j)        ? j
                  : [];
      renderNearby(items.slice(0, 4));
    })
    .catch(()=>{ /* เงียบไว้ก็พอ */ });

  function renderNearby(arr){
    grid.innerHTML = '';
    const frag = document.createDocumentFragment();
    arr.forEach(it => {
      const a = document.createElement('a');
      a.className = 'ex-card';
      a.href = `/page/ex_item_view.html?id=${encodeURIComponent(it.id)}`;
      const img = (it.thumbnail_url || (it.images && it.images[0]) || '');
      a.innerHTML = `
        <div class="ex-card-thumb" style="background-image:url('${img}')"></div>
        <div class="ex-card-body">
          <div class="ex-card-title">${escapeHtml(it.title||'-')}</div>
          <div class="ex-card-meta">หมวด: ${escapeHtml(it.category_name||'-')}</div>
        </div>`;
      frag.appendChild(a);
    });
    grid.appendChild(frag);
  }
  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
})();
