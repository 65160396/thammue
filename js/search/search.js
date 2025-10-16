// /js/search/search.js
(function (global) {
  function $(sel, root = document) { return root.querySelector(sel); }

  function defaultRenderCard(it) {
    const price = Number(it.price ?? 0).toLocaleString();
    const img = (it.main_image || "/img/placeholder.png").replace(/\\/g, "/");
    return `
      <a class="card" href="/page/product_detail.php?id=${it.id || ""}">
        <img src="${img}" alt="">
        <div class="name">${it.name || "-"}</div>
        <div class="price">฿${price}</div>
      </a>`;
  }

  // ✅ บังคับให้ endpoint เป็น absolute เสมอ + ใส่พารามิเตอร์ถูกต้อง
  function buildUrl(endpoint, params) {
  // ถ้า endpoint ไม่ใช่ http(s) ให้ยึด origin ปัจจุบัน
  const url = new URL(endpoint, window.location.origin);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null) url.searchParams.set(k, v);
  });
  console.log("[search] GET:", url.toString()); // ช่วย debug ดูว่าไปโดน URL ไหน
  return url.toString();
}


  function initSearch(opts) {
    const {
      input = "#q",
      button = "#btnSearch",
      results = "#results",
      endpoint = "/page/backend/search_products.php",
      per = 24,
      sort = "relevance",
      renderCard = defaultRenderCard,
      onBeforeRender,
      onAfterRender,
      minLength = 1,
      debounceMs = 0,
    } = opts || {};

    const $input   = $(input);
    const $button  = $(button);
    const $results = $(results);

    if (!$input || !$button || !$results) {
      console.error("[search.js] Missing selector(s).", { input, button, results });
      return;
    }

    let timer = null;

    function doFetch(page = 1) {
      const q = ($input.value || "").trim();
      if (q.length < minLength) {
        $results.innerHTML = "";
        return;
      }

      // ✅ ใช้ absolute URL เสมอ
      const url = buildUrl(endpoint, { q, page, per, sort });

      fetch(url)
  .then(r => r.json())
  .then((res) => {
    if (onBeforeRender) onBeforeRender(res);

    // อัพเดตเฉพาะกล่องผลการค้นหา
    if (!res || res.ok === false) {
      $results.innerHTML = "เกิดข้อผิดพลาด";
      return;
    }
    if (!res.items || res.items.length === 0) {
      $results.innerHTML = "ไม่พบสินค้า";
      return;
    }

    // วาดการ์ดสินค้า
    $results.innerHTML = res.items.map(renderCard).join("");

    if (onAfterRender) onAfterRender(res);
  })
  .catch(() => {
    $results.innerHTML = "เครือข่ายผิดพลาด";
  });
    }

    function doSearch(page = 1) {
      if (debounceMs > 0) {
        clearTimeout(timer);
        timer = setTimeout(() => doFetch(page), debounceMs);
      } else {
        doFetch(page);
      }
    }

    $button.addEventListener("click", () => doSearch(1));
    $input.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); doSearch(1); } });

    // เผื่ออยากเรียกจากที่อื่น
    return { search: doSearch };
  }

  global.Search = { init: initSearch };
})(window);
