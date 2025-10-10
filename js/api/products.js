(function () {
  const els = {
    buttons: document.querySelector('#categoryButtons') || document,
    grid: document.querySelector('#productGrid'),
    title: document.querySelector('#catTitle')
  };
  if (!els.grid) return;

  const state = { current: null, loading: false };

  function setActive(slug) {
    document.querySelectorAll('.category-button').forEach(a => {
      a.classList.toggle('active', a.dataset.cat === slug);
    });
  }

  function skeleton(n = 8) {
    const blocks = Array.from({ length: n }, () => (
      `<a class="product-card" aria-disabled="true">
         <div class="thumb" style="background:#eee"></div>
         <div class="card-body">
           <div style="height:14px;background:#eee;border-radius:6px;margin:6px 0 10px;"></div>
           <div style="height:12px;background:#f1f1f1;border-radius:6px;width:70%;"></div>
         </div>
       </a>`
    )).join('');
    els.grid.innerHTML = blocks;
  }

  function card(item) {
    const url = `/page/product/detail.php?id=${encodeURIComponent(item.id)}`;
    const img = item.cover_url || '/img/placeholder.png';
    const price = (item.price ?? 0).toLocaleString('th-TH', { style: 'currency', currency: 'THB' })
                 .replace('THB','฿').trim();
    return `
      <a href="${url}" class="product-card">
        <img class="thumb" src="${img}" alt="${item.title || 'สินค้า'}" loading="lazy">
        <div class="card-body">
          <h3>${item.title || 'สินค้าไม่มีชื่อ'}</h3>
          <div class="meta">
            ${item.location ? `<span>${item.location}</span>` : ''}
            <span>${price}</span>
          </div>
        </div>
      </a>`;
  }

  async function load(slug) {
    if (state.loading || !slug) return;
    state.loading = true;
    state.current = slug;
    setActive(slug);
    if (els.title) els.title.textContent = (document.querySelector(`.category-button[data-cat="${slug}"]`)?.textContent || 'สินค้า');

    skeleton();
    try {
      const { items } = await window.API.products.byCategory(slug, { limit: 24 });
      els.grid.innerHTML = items.length ? items.map(card).join('') :
        `<p class="center muted" style="grid-column:1/-1">ยังไม่มีสินค้าในหมวดนี้</p>`;
    } catch (err) {
      els.grid.innerHTML = `<p class="center" style="grid-column:1/-1;color:#b91c1c">
        โหลดข้อมูลไม่สำเร็จ ลองใหม่อีกครั้ง</p>`;
      console.error(err);
    } finally {
      state.loading = false;
    }
  }

  // คลิกหมวดหมู่
  els.buttons.addEventListener('click', e => {
    const a = e.target.closest('.category-button[data-cat]');
    if (!a) return;
    e.preventDefault();
    const slug = a.dataset.cat;
    if (slug && slug !== state.current) load(slug);
  });

  // โหลดครั้งแรก (เอาหมวดแรก หรือหมวดที่ .active)
  const firstActive = document.querySelector('.category-button.active[data-cat]');
  const first = firstActive?.dataset.cat || document.querySelector('.category-button[data-cat]')?.dataset.cat;
  if (first) load(first);
})();

function renderProducts(opts = {}) {
  const container =
    document.querySelector(opts.container || '.product-grid'); // เพิ่มบรรทัดนี้
  // ... ที่เหลือคงเดิม
}

window.API = window.API || {};
API.products = API.products || {};

API.products.byCategory = async (slug, { limit = 24 } = {}) => {
  const res = await fetch(
    `/page/backend/productsforsale/get_by_category.php?slug=${encodeURIComponent(slug)}&limit=${limit}`,
    { credentials: 'include', cache: 'no-store' }
  );
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return await res.json(); // => { items: [...] }
};



