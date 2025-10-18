// /js/productsforsale/products-list.js
(function () {
  // --- utility: escape text ปลอดภัย ---
  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s ?? "";
    return d.innerHTML;
  }

  // --- utility: ฟอร์แมตราคา ---
  function fmtPrice(n) {
    return isFinite(n) ? "$" + Number(n).toLocaleString("en-US") : (n ?? "");
  }

  // --- utility: fetch ที่มี retry และปิด cache ---
  async function fetchWithRetry(url, opts = {}, tries = 2, delayMs = 400) {
    for (let i = 0; i < tries; i++) {
      try {
        const res = await fetch(url, { cache: "no-store", credentials: "same-origin", ...opts });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
      } catch (e) {
        if (i === tries - 1) throw e;
        await new Promise(r => setTimeout(r, delayMs));
      }
    }
  }

  // ---- ดึงสถิติถูกใจของสินค้าหนึ่งชิ้น แล้วอัปเดตปุ่ม/ตัวเลขบนการ์ด ----
  async function fetchLikeStatsFor(id) {
    try {
      const res = await fetch(`/page/backend/likes_sale/stats.php?type=product&id=${encodeURIComponent(id)}`, {
        credentials: 'include',
        cache: 'no-store'
      });
      if (!res.ok) return;
      const data = await res.json(); // { liked, count }
      const btn = document.querySelector(`.like-btn[data-id="${id}"]`);
      const num = document.querySelector(`.like-num[data-id="${id}"]`);
      if (btn) {
        btn.textContent = data.liked ? '❤️' : '🤍';
        btn.dataset.liked = data.liked ? '1' : '0';
      }
      if (num) num.textContent = data.count ?? 0;
    } catch (_) {}
  }

  // ---- main: เรนเดอร์สินค้า + ผูก event หัวใจ/เมนู⋮/ปุ่มรายละเอียด (ครั้งเดียว/หนึ่ง grid) ----
  async function renderProducts({
    gridSelector = ".recommended-products .product-grid",
    endpoint = "/page/backend/productsforsale/get_products.php",
    cat = null,
    limit = null,
    page = null,
    sort = null,
    dir = null,
  } = {}) {

    const grid = document.querySelector(gridSelector);
    if (!grid) return;

    // ผูกตัวจัดการคลิก (ครั้งเดียว) ต่อหนึ่ง grid
    if (!grid._bound) {
      grid.addEventListener('click', async (ev) => {
        // 0) ปุ่ม ⋮ (ต้องจับก่อนสุด กันวิ่งไปลิงก์)
        const menuBtn = ev.target.closest('.card-menu');
        if (menuBtn) {
          ev.preventDefault(); ev.stopPropagation();

          // ปิด popup เดิม
          document.querySelectorAll('.card-popup').forEach(p => p.remove());

          const id = menuBtn.dataset.id;
          const isOwner = menuBtn.dataset.owner === "1";

          const popup = document.createElement('div');
          popup.className = 'card-popup';
          popup.innerHTML = isOwner
            ? `
              <button class="edit" data-action="edit">✏️ แก้ไขสินค้า</button>
              <button class="delete" data-action="delete">🗑️ ลบสินค้า</button>
            `
            : `
              <button class="report" data-action="report">🚩 Report</button>
            `;

          // วาง popup ในการ์ด (ปุ่ม ⋮ อยู่ใน a ก็ได้ แต่เราหยุด propagation แล้ว)
          menuBtn.parentElement.appendChild(popup);

          const closePopup = (e) => {
            if (!popup.contains(e.target) && e.target !== menuBtn) {
              popup.remove();
              document.removeEventListener('click', closePopup);
            }
          };
          setTimeout(() => document.addEventListener('click', closePopup), 0);

          popup.addEventListener('click', (e) => {
            const action = e.target.closest('button')?.dataset.action;
            if (!action) return;
            if (action === 'edit') {
              location.href = `/page/products/edit_product.php?id=${id}`;
            } else if (action === 'delete') {
              if (confirm('คุณแน่ใจว่าจะลบสินค้านี้?')) {
                fetch(`/page/backend/productsforsale/delete.php?id=${id}`, { method: 'POST' })
                  .then(r => { if (r.ok) popup.remove(); });
              }
            } else if (action === 'report') {
              alert('รายงานสินค้านี้เรียบร้อย');
              popup.remove();
            }
          });

          return; // สำคัญ: ไม่ให้ตกไป handler อื่น/เปิดลิงก์
        }

        // 1) ปุ่มหัวใจ
        const likeBtn = ev.target.closest('.like-btn');
        if (likeBtn) {
          ev.preventDefault(); ev.stopPropagation();

          const id = likeBtn.dataset.id;
          const here = window.location.pathname + window.location.search;

          try {
            const res = await fetch('/page/backend/likes_sale/toggle.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({ type: 'product', id })
            });
            if (res.status === 401) {
              location.href = '/page/login.html?next=' + encodeURIComponent(here);
              return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();   // { liked, count }
            likeBtn.textContent = data.liked ? '❤️' : '🤍';
            likeBtn.dataset.liked = data.liked ? '1' : '0';
            const num = grid.querySelector(`.like-num[data-id="${id}"]`);
            if (num) num.textContent = data.count ?? 0;
          } catch (e) {
            console.error(e);
          }
          return;
        }

        // 2) ปุ่ม "ดูรายละเอียด" (อยู่ใน a.product-card)
        const detailBtn = ev.target.closest('.btn-detail');
        if (detailBtn) {
          ev.preventDefault(); ev.stopPropagation();
          const card = detailBtn.closest('a.product-card');
          if (card?.href) location.href = card.href;
          return;
        }
      });
      grid._bound = true;
    }

    // เรนเดอร์รายการ
    grid.innerHTML = '<div class="empty">กำลังโหลดสินค้า...</div>';

    const qs = new URLSearchParams();
    if (cat)  qs.set("cat",  cat);
    if (limit)qs.set("limit",limit);
    if (page) qs.set("page", page);
    if (sort) qs.set("sort", sort);
    if (dir)  qs.set("dir",  dir);
    qs.set("_ts", Date.now().toString()); // กันแคช

    const url = endpoint + (qs.toString() ? `?${qs}` : "");

    try {
      const data = await fetchWithRetry(url);
      grid.innerHTML = "";

      (data.items || []).forEach((item) => {
        // ใช้ <a class="product-card"> เหมือนเดิม (คงสไตล์เดิม)
        const a = document.createElement("a");
        a.className = "product-card";
        a.href = `/page/products/product_detail.php?id=${item.id}`;

        const img   = (item.image || item.main_image || "/img/placeholder.png").replace(/\\/g,"/");
        const name  = esc(item.name);
        const catTx = esc(item.category_name || item.category || "หมวด");
        const price = isFinite(item.price) ? "$" + Number(item.price).toLocaleString("en-US") : "ราคา";
        const prov  = esc(item.province || "จังหวัด");

        a.innerHTML = `
          <button class="card-menu" data-id="${item.id}" data-owner="${item.is_owner ? 1 : 0}" type="button">⋮</button>
          <div class="thumb"><img src="${img}" alt="${name}"></div>
          <div class="card-body">
            <h3 class="title">${name}</h3>
            <div class="meta-inline" title="หมวด: ${catTx} · ${prov}">
              <span class="label">หมวด:</span>
              <span class="cat">${catTx}</span>
              <span class="dot">·</span>
              <span class="prov">${prov}</span>
            </div>
            <div class="price-strong">${price}</div>
            <button class="btn-detail" type="button">ดูรายละเอียด</button>
          </div>
        `;

        grid.appendChild(a);
        fetchLikeStatsFor(item.id);
      });

      if (!data.items || !data.items.length) {
        grid.innerHTML = '<div class="empty">ยังไม่มีสินค้า</div>';
      }
    } catch (err) {
      console.error("โหลดสินค้าไม่สำเร็จ:", err);
      grid.innerHTML = '<div class="empty">โหลดรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</div>';
    }
  }

  // auto-init เมื่อโหลดหน้าเสร็จ
  document.addEventListener("DOMContentLoaded", () => {
    const usp = new URLSearchParams(location.search);
    const cat = usp.get("cat");
    renderProducts({
      cat,
      endpoint: "/page/backend/productsforsale/get_products.php",
    });
  });

  // เผื่อเรียกเองจากที่อื่น
  window.initProducts = renderProducts;
})();
