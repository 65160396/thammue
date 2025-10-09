// /js/products.js
(function () {
  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s ?? "";
    return d.innerHTML;
  }
  function fmtPrice(n) {
    return isFinite(n) ? "$" + Number(n).toLocaleString("en-US") : (n ?? "");
  }

  // ---- retry helper ----
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

  // ---- main render ----
  async function renderProducts({
    gridSelector = ".recommended-products .product-grid",
    endpoint = "/page/backend/productsforsale/get_products.php", // <<<< ปรับให้ตรงของคุณ
    cat = null,
    limit = null,
    page = null,
    sort = null,
    dir = null,
  } = {}) {
    const grid = document.querySelector(gridSelector);
    if (!grid) return;

    grid.innerHTML = '<div class="empty">กำลังโหลดสินค้า...</div>';

    const qs = new URLSearchParams();
    if (cat)  qs.set("cat",  cat);
    if (limit)qs.set("limit",limit);
    if (page) qs.set("page", page);
    if (sort) qs.set("sort", sort);
    if (dir)  qs.set("dir",  dir);
    qs.set("_ts", Date.now().toString()); // กันแคชทุกครั้ง

    const url = endpoint + (qs.toString() ? `?${qs}` : "");

    try {
      const data = await fetchWithRetry(url); // ใช้ no-store + retry
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
      });

      if (!data.items || !data.items.length) {
        grid.innerHTML = '<div class="empty">ยังไม่มีสินค้า</div>';
      }
    } catch (err) {
      console.error("โหลดสินค้าไม่สำเร็จ:", err);
      grid.innerHTML = '<div class="empty">โหลดรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</div>';
    }
  }

  // auto-init
  document.addEventListener("DOMContentLoaded", () => {
    const usp = new URLSearchParams(location.search);
    const cat = usp.get("cat");
    renderProducts({
      cat,
      endpoint: "/page/backend/productsforsale/get_products.php", // <<<< สำคัญ: ส่ง endpoint ตรงนี้ด้วย
    });
  });

  window.initProducts = renderProducts;
})();
