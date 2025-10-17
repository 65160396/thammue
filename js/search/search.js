// /js/search/search.js
(function (global) {
  const $ = (sel, root = document) => root.querySelector(sel);

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

  function buildUrl(endpoint, params) {
    const url = new URL(endpoint, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null) url.searchParams.set(k, v);
    });
    return url.toString();
  }

  /* ======= History storage ======= */
  const LS_KEY_LAST = "lastSearch";
  const LS_KEY_HIST = "searchHistory";
  const HIST_LIMIT  = 10;

  const loadHistory = () => {
    try { return JSON.parse(localStorage.getItem(LS_KEY_HIST) || "[]"); } catch { return []; }
  };
  const saveHistory = (list) => {
    try { localStorage.setItem(LS_KEY_HIST, JSON.stringify(list.slice(0, HIST_LIMIT))); } catch {}
  };
  const addHistory = (term) => {
    const t = (term || "").trim();
    if (!t) return;
    let hist = loadHistory().filter(x => x.toLowerCase() !== t.toLowerCase());
    hist.unshift(t);
    saveHistory(hist);
  };
  const clearHistory = () => saveHistory([]);
  const saveLast  = (q) => { try { localStorage.setItem(LS_KEY_LAST, q); } catch {} };
  const getLast   = ()  => { try { return localStorage.getItem(LS_KEY_LAST) || ""; } catch { return ""; } };
  const clearLast = ()  => { try { localStorage.removeItem(LS_KEY_LAST); } catch {} };

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

      prefillLastOnLoad = false,
      autoSearchOnLoad  = false,
      rememberLast      = true
    } = opts || {};

    const $input   = $(input);
    const $button  = $(button);
    const $results = $(results);
    const $section = $("#searchSection");
    const $count   = $("#searchCount");
    const $clear   = $("#clearSearch");

    if (!$input || !$button || !$results) return;

    const $anchor = $input.closest(".search-group") || $input.parentElement;

    /* ===== Suggest box (portal to <body>) ===== */
    let $sug = $("#qSuggest");
    if (!$sug) {
      $sug = document.createElement("div");
      $sug.id = "qSuggest";
      $sug.className = "search-suggest";
      document.body.appendChild($sug); // ⬅️ ย้ายไปไว้ body กัน overflow/z-index
    }

    // คำนวณตำแหน่งให้กล่องไปโผล่ใต้ช่องค้นหาแบบแนบพอดี
    function positionSuggest() {
      if (!$anchor) return;
      const rect = $anchor.getBoundingClientRect();
      Object.assign($sug.style, {
        position: "fixed",
        left: `${rect.left}px`,
        top: `${rect.bottom + 6}px`,
        width: `${rect.width}px`,
        maxWidth: `${rect.width}px`,
        zIndex: 99999
      });
    }

    /* ===== Enter/Exit search mode ===== */
    const enterSearchMode = () => {
      document.body.classList.add("search-active");
      if ($section) $section.hidden = false;
    };
    const exitSearchMode = () => {
      document.body.classList.remove("search-active");
      if ($section) $section.hidden = true;
      $results.innerHTML = "";
      if ($count) $count.textContent = "";
    };

    let timer = null;
    let activeIndex = -1; // สำหรับเลื่อนด้วยลูกศร

    function doFetch(page = 1) {
      const q = ($input.value || "").trim();
      if (q.length < minLength) {
        exitSearchMode();
        return;
      }
      enterSearchMode();

      const url = buildUrl(endpoint, { q, page, per, sort });

      fetch(url)
        .then(r => r.json())
        .then(res => {
          if (rememberLast) saveLast(q);
          addHistory(q);

          if (onBeforeRender) onBeforeRender(res);

          if (!res || res.ok === false) {
            $results.innerHTML = "เกิดข้อผิดพลาด";
            if ($count) $count.textContent = "";
            return;
          }
          if (!res.items || res.items.length === 0) {
            $results.innerHTML = "ไม่พบสินค้า";
            if ($count) $count.textContent = "(0 รายการ)";
            return;
          }

          $results.innerHTML = res.items.map(renderCard).join("");
          if ($count) $count.textContent = `(${res.total} รายการ)`;

          if (onAfterRender) onAfterRender(res);
        })
        .catch(() => {
          $results.innerHTML = "เครือข่ายผิดพลาด";
          if ($count) $count.textContent = "";
        });
    }

    const doSearch = (page = 1) => {
      if (debounceMs > 0) {
        clearTimeout(timer);
        timer = setTimeout(() => doFetch(page), debounceMs);
      } else {
        doFetch(page);
      }
    };

    /* ===== Suggest rendering ===== */
    function renderSuggest() {
      const q = ($input.value || "").trim().toLowerCase();
      let list = loadHistory();
      if (!list.length) {
  $sug.innerHTML = `
    <div class="sug-empty">ยังไม่มีประวัติการค้นหา</div>
  `;
  $sug.classList.add("show");
  $sug.removeAttribute("hidden");
  positionSuggest();
  $input.setAttribute("aria-expanded", "true");
  activeIndex = -1;
  return;
}



      const items = list.map(v => `
        <div class="sug-item" data-val="${encodeURIComponent(v)}" role="option">
          <span class="sug-text">${v}</span>
          <button class="sug-remove" title="ลบคำนี้" aria-label="ลบคำนี้">×</button>
        </div>
      `).join("");

      $sug.innerHTML = `
  ${items}
  <button type="button" class="sug-clear">ล้างประวัติการค้นหา</button>
`;


      $sug.classList.add("show");
      $sug.removeAttribute("hidden");
      positionSuggest(); // ⬅️ จัดตำแหน่งทุกครั้งที่โชว์
      $input.setAttribute("aria-expanded", "true");
      activeIndex = -1;
    }

    function hideSuggest() {
      $sug.classList.remove("show");
      $sug.setAttribute("hidden", ""); // เพื่อ AT; การมองเห็นคุมด้วย .show
      activeIndex = -1;
      $input.setAttribute("aria-expanded", "false");
    }

    function isSuggestOpen() {
      return $sug.classList.contains("show");
    }

    function moveActive(dir) {
      if (!isSuggestOpen()) return;
      const nodes = [...$sug.querySelectorAll(".sug-item")];
      if (!nodes.length) return;
      activeIndex = (activeIndex + dir + nodes.length) % nodes.length;
      nodes.forEach(n => n.classList.remove("is-active"));
      nodes[activeIndex].classList.add("is-active");
      nodes[activeIndex].scrollIntoView({ block: "nearest" });
    }
    function chooseActive() {
      if (!isSuggestOpen()) return false;
      const node = $sug.querySelector(".sug-item.is-active");
      if (!node) return false;
      const val = decodeURIComponent(node.dataset.val || "");
      if (!val) return false;
      $input.value = val;
      hideSuggest();
      doSearch(1);
      return true;
    }

    /* ===== Events ===== */
    $button.addEventListener("click", () => { hideSuggest(); doSearch(1); });

    $input.addEventListener("keydown", (e) => {
      if (!isSuggestOpen()) {
        if (e.key === "Enter") { e.preventDefault(); doSearch(1); }
        return;
      }
      if (e.key === "ArrowDown") { e.preventDefault(); moveActive(1); }
      else if (e.key === "ArrowUp") { e.preventDefault(); moveActive(-1); }
      else if (e.key === "Enter")   { e.preventDefault(); chooseActive() || doSearch(1); }
      else if (e.key === "Escape")  { hideSuggest(); }
    });

    // พิมพ์/โฟกัส = แสดงคำค้นล่าสุด + จัดตำแหน่ง
    $input.addEventListener("input", () => { renderSuggest(); positionSuggest(); });
    $input.addEventListener("focus", () => {
      renderSuggest();
      $sug.classList.add("show");
      $sug.removeAttribute("hidden");
      positionSuggest();
    });

    // กัน blur ตอนคลิกในดรอปดาวน์
    $sug.addEventListener("mousedown", (e) => e.preventDefault());

    // คลิกไอเท็ม / ปุ่ม X / ล้างทั้งหมด
    $sug.addEventListener("click", (e) => {
      if (e.target.classList.contains("sug-close")) { hideSuggest(); return; }
      if (e.target.classList.contains("sug-remove")) {
  e.preventDefault();
  e.stopPropagation(); // กันคลิกหลุดไปปิดกล่อง

  const row = e.target.closest(".sug-item");
  const val = decodeURIComponent(row?.dataset.val || "");

  // ลบจาก localStorage
  let hist = loadHistory().filter(x => x.toLowerCase() !== val.toLowerCase());
  saveHistory(hist);

  // จำ index เดิม เพื่อโฟกัสให้ต่อเนื่อง
  const nodes = [...$sug.querySelectorAll(".sug-item")];
  const oldIndex = Math.max(0, nodes.indexOf(row));

  // เรนเดอร์ใหม่แต่ "ไม่ปิดกล่อง"
  if (hist.length) {
    renderSuggest();
    positionSuggest();

    // ทำให้มีไฮไลต์อยู่ที่ตำแหน่งเดิม (หรืออันก่อนหน้า ถ้าเป็นตัวสุดท้าย)
    requestAnimationFrame(() => {
      const newNodes = [...$sug.querySelectorAll(".sug-item")];
      const nextIndex = Math.min(oldIndex, newNodes.length - 1);
      if (newNodes[nextIndex]) {
        newNodes.forEach(n => n.classList.remove("is-active"));
        newNodes[nextIndex].classList.add("is-active");
      }
    });
  } else {
    // ไม่มีอะไรเหลือแล้ว แต่คงกล่องไว้ด้วยสถานะว่าง
    $sug.innerHTML = `<div class="sug-empty">ยังไม่มีประวัติการค้นหา</div>`;
    $sug.classList.add("show");
    $sug.removeAttribute("hidden");
    positionSuggest();
  }
  return;
}

      if (e.target.classList.contains("sug-clear")) { clearHistory(); renderSuggest(); positionSuggest(); return; }
      const it = e.target.closest(".sug-item");
      if (it) {
        const val = decodeURIComponent(it.dataset.val || "");
        if (val) { $input.value = val; hideSuggest(); doSearch(1); }
      }
    });

    // คลิกนอก -> ปิดดรอปดาวน์
    document.addEventListener("click", (e) => {
      if (!$sug.contains(e.target) && !$input.contains(e.target)) hideSuggest();
    });

    // อัปเดตตำแหน่งเมื่อหน้าขยับ/ย่อขยาย/สกรอลล์
    window.addEventListener("resize", positionSuggest);
    window.addEventListener("scroll", positionSuggest, { passive: true });

    // ปุ่มล้างผลลัพธ์ (ถ้ามีใน UI)
    if ($clear) {
      $clear.addEventListener("click", (e) => {
        e.preventDefault();
        $input.value = "";
        exitSearchMode();
        $input.focus();
      });
    }

    // พรีฟิล (ถ้าตั้ง)
    const last = getLast();
    if (prefillLastOnLoad && last) {
      $input.value = last;
      if (autoSearchOnLoad) doSearch(1);
    }

    // ท้าย initSearch ก่อน return
    exitSearchMode();
    return { search: doSearch, reset: exitSearchMode };
  }

  global.Search = { init: initSearch };
})(window);
