// /js/category/category-feed.js
(function () {
  const els = {
    buttons: document.getElementById('categoryButtons') || document,
    section: document.getElementById('catSection'),
    grid: document.getElementById('catGrid'),
    title: document.getElementById('catTitle'),
    clear: document.getElementById('clearCat'),
  };
  if (!els.grid) return;

  const state = { current: null, loading: false };

  function showSection(show = true) { if (els.section) els.section.hidden = !show; }
  function setActive(slug) {
    document.querySelectorAll('.category-button').forEach(a => {
      a.classList.toggle('active', slug && a.dataset.cat === slug);
    });
  }
  function skeleton(n = 8) {
    els.grid.innerHTML = Array.from({ length: n }, () => `
      <a class="product-card" aria-disabled="true">
        <div class="thumb" style="background:#eee"></div>
        <div class="card-body">
          <div style="height:14px;background:#eee;border-radius:6px;margin:6px 0 10px;"></div>
          <div style="height:12px;background:#f1f1f1;border-radius:6px;width:70%;"></div>
        </div>
      </a>`).join('');
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
    if (!window.API?.products?.byCategory) {
      console.warn('API.products.byCategory ไม่พร้อมใช้งาน');
      return;
    }
    state.loading = true;
    state.current = slug;
    setActive(slug);

    const label = document.querySelector(`.category-button[data-cat="${slug}"]`)?.textContent || 'สินค้า';
    if (els.title) els.title.textContent = `หมวด: ${label}`;

    showSection(true);
    skeleton();
    try {
      const { items } = await window.API.products.byCategory(slug, { limit: 24 });
      els.grid.innerHTML = items?.length
        ? items.map(card).join('')
        : `<p class="center muted" style="grid-column:1/-1">ยังไม่มีสินค้าในหมวดนี้</p>`;
      els.section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
      console.error(err);
      els.grid.innerHTML = `<p class="center" style="grid-column:1/-1;color:#b91c1c">
        โหลดข้อมูลไม่สำเร็จ ลองใหม่อีกครั้ง</p>`;
    } finally {
      state.loading = false;
    }
  }

  // export ไว้เทสใน Console
  window.loadCategory = load;

  // จับคลิกบนปุ่มหมวด
  els.buttons.addEventListener('click', e => {
    const a = e.target.closest('.category-button[data-cat]');
    if (!a) return;
    e.preventDefault();
    const slug = a.dataset.cat;
    if (slug && slug !== state.current) load(slug);
  });

  // ปุ่ม “กลับไปดูทั้งหมด”
  els.clear?.addEventListener('click', () => {
    state.current = null;
    setActive(null);
    showSection(false);
  });
})();
