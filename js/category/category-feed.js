// /js/category/category-feed.js
document.addEventListener('DOMContentLoaded', () => {
  const elTitle = document.getElementById('catTitle');
  const elGrid  = document.getElementById('catGrid');
  if (!elTitle || !elGrid) return;

  // 1) set active tab
  const current = document.body.getAttribute('data-slug');
  document.querySelectorAll('.category-buttons a.category-button').forEach(a => {
    if (a.getAttribute('data-slug') === current) {
      a.classList.add('active');
      a.setAttribute('aria-current', 'page');
    }
  });

  // 2) map slug -> cat id + title
  const MAP = {
    handmade:       { id: 1, title: "สินค้าแฮนเมด" },
    craft:          { id: 2, title: "งานประดิษฐ์" },
    local_products: { id: 3, title: "สินค้าท้องถิ่น" },
    second_hand:    { id: 4, title: "สินค้ามือสอง" }
  };
  const info = MAP[current];
  if (!info) { elTitle.textContent = "ไม่พบหมวดหมู่"; return; }
  elTitle.textContent = info.title;

  // 3) fetch ข้อมูล
  const endpoint = `/page/backend/productsforsale/get_products.php?cat=${info.id}&limit=40&page=1&sort=created&dir=desc`;
  fetch(endpoint)
    .then(async r => {
      if (!r.ok) {
        const text = await r.text().catch(() => '');
        throw new Error(`HTTP ${r.status} ${r.statusText} – ${text.slice(0,200)}`);
      }
      return r.json();
    })
    .then(({ items }) => {
      if (!items || !items.length) {
        elGrid.innerHTML = `<div class="empty">ยังไม่มีสินค้าในหมวดนี้</div>`;
        return;
      }

      const esc = (s) => {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
      };
      const fmtPrice = (n) => '$' + Number(n || 0).toLocaleString('en-US');

      elGrid.innerHTML = items.map(p => {
        const img = String(p.image || '').replaceAll('\\','/');
        const name = esc(p.name || '');
        const price = fmtPrice(p.price);
        const catName = esc(p.category_name || info.title);
        const province = p.province ? `จังหวัด${esc(p.province)}` : 'ไม่ระบุจังหวัด';

        return `
          <a class="product-card" href="/page/products/product_detail.php?id=${p.id}">
            <button class="card-menu" type="button" aria-label="เมนูการ์ด">⋯</button>

            <div class="thumb">
              <img src="${img || '/img/placeholder.png'}" alt="${name}">
            </div>

            <div class="card-body">
              <h3 class="title">${name}</h3>

              <div class="meta-inline">
                <span class="label">หมวด:</span><span>${catName}</span>
                <span class="dot">·</span>
                <span>${province}</span>
              </div>

              <div class="price-strong">${price}</div>
              <button class="btn-detail" type="button">ดูรายละเอียด</button>
            </div>
          </a>
        `;
      }).join('');
    })
    .catch(err => {
      console.error('[category-feed] fetch failed:', err);
      elGrid.innerHTML = `<p class="error">โหลดข้อมูลไม่สำเร็จ</p>`;
    });
});
