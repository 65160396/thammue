// /js/products.js
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
      // หน้า index อนุญาตไม่ล็อกอิน: ถ้า 401 ก็ไม่ทำอะไร
      if (!res.ok) return;

      const data = await res.json(); // { liked: boolean, count: number }
      const btn = document.querySelector(`.like-btn[data-id="${id}"]`);
      const num = document.querySelector(`.like-num[data-id="${id}"]`);
      if (btn) {
        btn.textContent = data.liked ? '❤️' : '🤍';
        btn.dataset.liked = data.liked ? '1' : '0';
      }
      if (num) num.textContent = data.count ?? 0;
    } catch (_) {}
  }

  // ---- main: เรนเดอร์สินค้า + ผูก event หัวใจ (ผูกครั้งเดียว/หนึ่ง grid) ----
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

    // ผูกตัวจัดการคลิกหัวใจ "ครั้งเดียว" ต่อหนึ่ง grid
    if (!grid._likeBound) {
      grid.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.like-btn');
        if (!btn) return;                  // คลิกที่อื่น ไม่ใช่ปุ่มหัวใจ
        ev.preventDefault();               // กันเปิดลิงก์ a
        ev.stopPropagation();

        const id = btn.dataset.id;
        const here = window.location.pathname + window.location.search;  // ไว้ส่งไป login แล้วเด้งกลับ

        try {
          const res = await fetch('/page/backend/likes_sale/toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ type: 'product', id })
          });

          if (res.status === 401) {
            // ยังไม่ล็อกอิน → พาไปหน้า login พร้อม next=กลับมาหน้านี้
            location.href = '/page/login.html?next=' + encodeURIComponent(here);
            return;
          }
          if (!res.ok) throw new Error('HTTP ' + res.status);

          const data = await res.json();   // { liked, count }
          btn.textContent = data.liked ? '❤️' : '🤍';
          btn.dataset.liked = data.liked ? '1' : '0';
          const num = grid.querySelector(`.like-num[data-id="${id}"]`);
          if (num) num.textContent = data.count ?? 0;
        } catch (e) {
          console.error(e);
        }
      });
      grid._likeBound = true;
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
        const a = document.createElement("a");
        a.className = "product-card";
        a.href = `/page/products/product_detail.php?id=${item.id}`;
        a.innerHTML = `
          <img src="${item.image}" alt="${esc(item.name)}">
          <h3>${esc(item.name)}</h3>
          <p>${fmtPrice(item.price)}</p>
          <span>จังหวัด${esc(item.province)}</span>
        `;
        grid.appendChild(a);

        // ดึงสถานะถูกใจ/ยอดรวม สำหรับการ์ดนี้
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
