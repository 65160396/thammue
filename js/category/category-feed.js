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
  console.log('[category-feed] GET:', endpoint);

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
    elGrid.innerHTML = `<p class="muted">ยังไม่มีสินค้าในหมวดนี้</p>`;
    return;
  }

  // helper: escape ตัวอักษร
  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  // ฟอร์แมตราคาเป็น $ + จำนวน
  const fmtPrice = (n) => '$' + Number(n || 0).toLocaleString('en-US');

  elGrid.innerHTML = items.map(p => {
    const img = String(p.image).replaceAll('\\','/');
    const province = p.province ? `จังหวัด${p.province}` : 'จังหวัดไม่ระบุจังหวัด';

    return `
      <a class="product-card" href="/page/products/product_detail.php?id=${p.id}">
        <img class="product-media" src="${img}" alt="${esc(p.name)}">
        <div class="product-title" 
             style="margin-top:10px; margin-bottom:10px, font-weight:600;line-height:1.4;">${esc(p.name)}</div>

        <p class="product-price"
           style="margin:10px 0 20px; font-size:15px; font-weight:600; color:#333; line-height:1.6;">
           ${fmtPrice(p.price)}
        </p>

        <span class="product-province"
              style="display:block;color:#666;font-size:12px;line-height:1.4;">
              ${esc(province)}
        </span>
      </a>
    `;
  }).join('');
})
    .catch(err => {
      console.error('[category-feed] fetch failed:', err);
      elGrid.innerHTML = `<p class="error">โหลดข้อมูลไม่สำเร็จ</p>`;
    });
});
