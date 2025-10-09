// /js/products.js
(function () {
  /** แปลงข้อความให้ปลอดภัย */
  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s ?? "";
    return d.innerHTML;
  }
  /** ฟอร์แมตราคา */
  function fmtPrice(n) {
    return isFinite(n) ? "$" + Number(n).toLocaleString("en-US") : (n ?? "");
  }

  /** ดึงสินค้าแล้วเรนเดอร์ลงกริด */
  async function renderProducts({
    gridSelector = ".recommended-products .product-grid",
    endpoint = "/page/backend/public/api/products/get_products.php",
    cat = null,
    limit = null,
    page = null,
    sort = null,
    dir = null,
  } = {}) {
    const grid = document.querySelector(gridSelector);
    if (!grid) return;

    grid.innerHTML = '<div class="empty">กำลังโหลดสินค้า...</div>';

    // สร้าง query string
    const qs = new URLSearchParams();
    if (cat) qs.set("cat", cat);
    if (limit) qs.set("limit", limit);
    if (page) qs.set("page", page);
    if (sort) qs.set("sort", sort);
    if (dir) qs.set("dir", dir);

    const url = endpoint + (qs.toString() ? `?${qs}` : "");

    try {
      const res = await fetch(url, { credentials: "same-origin" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      grid.innerHTML = "";

      (data.items || []).forEach((item) => {
        const a = document.createElement("a");
        a.className = "product-card";
        a.href = `/page/product_detail.php?id=${item.id}`;
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
      grid.innerHTML =
        '<div class="empty">โหลดรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</div>';
    }
  }

  /** auto-init: อ่านค่า cat จาก URL แล้วเรียก render */
  document.addEventListener("DOMContentLoaded", () => {
    const usp = new URLSearchParams(location.search);
    const cat = usp.get("cat"); // ถ้ามี ?cat= จะกรองให้
    renderProducts({ cat });
  });

  // เผื่ออยากเรียกเองภายหลัง (เช่นเปลี่ยนหมวดแบบไม่รีเฟรชหน้า)
  window.initProducts = renderProducts;
})();
