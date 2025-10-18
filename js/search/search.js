// /js/search/search.js
(function (global) {
  const $ = (sel, root = document) => root.querySelector(sel);

  /* ---------- การเรนเดอร์การ์ดมาตรฐาน (180×320) + ปุ่มจุดสามจุด ---------- */
  function defaultRenderCard(it) {
    const price = Number(it.price ?? 0).toLocaleString();
    const img   = (it.main_image || "/img/placeholder.png").replace(/\\/g, "/");
    const name  = (it.name || "-");
    const prov  = it.province ? `จังหวัด${it.province}` : "ไม่ระบุจังหวัด";
    const href  = `/page/products/product_detail.php?id=${it.id || ""}`;

    // ✅ owner check
    const ownerId = Number(
      it.product_owner_id ??
      it.owner_id ??
      it.user_id ??
      it.shop_user_id ??
      it.seller_id ??
      NaN
    );
    const currentUserId = Number(window?.me?.id ?? NaN);
    const isOwner = !Number.isNaN(ownerId) && ownerId === currentUserId;

    const menuHtml = isOwner
      ? `<button class="edit">✏️ แก้ไขสินค้า</button>
         <button class="delete">🗑️ ลบสินค้า</button>`
      : `<button class="report">⚑ Report</button>`;

    return `
      <div class="product-card">
        <button class="card-menu" type="button" aria-haspopup="true" aria-expanded="false">⋮</button>
        <div class="card-popup" hidden>${menuHtml}</div>

        <a class="card-link" href="${href}">
          <div class="thumb"><img src="${img}" alt="${name}"></div>
          <div class="card-body">
            <h3 class="title">${name}</h3>
            <div class="meta-inline"><span>${prov}</span></div>
            <div class="price-strong">฿${price}</div>
            <span class="btn-detail">ดูรายละเอียด</span>
          </div>
        </a>
      </div>
    `;
  }

  /* ---------- เมนูจุดสามจุด: delegation ---------- */
  function wireCardMenus(container) {
    if (!container) return;

    const closeAll = () => {
      container.querySelectorAll('.product-card .card-popup').forEach(p => {
        p.hidden = true;
        p.parentElement.querySelector('.card-menu')?.setAttribute('aria-expanded','false');
      });
    };

    container.removeEventListener('click', container.__menuHandler__, true);
    document.removeEventListener('click', container.__docCloser__, true);
    container.removeEventListener('keydown', container.__escCloser__, true);

    const menuHandler = (e) => {
      const btn = e.target.closest('.card-menu');
      if (btn && container.contains(btn)) {
        e.preventDefault(); e.stopPropagation();
        const card = btn.closest('.product-card');
        const pop  = card.querySelector('.card-popup');
        const willOpen = pop.hidden;
        closeAll();
        pop.hidden = !willOpen ? true : false;
        btn.setAttribute('aria-expanded', String(willOpen));
        return;
      }

      const pop = e.target.closest('.card-popup');
      if (pop && container.contains(pop)) {
        e.stopPropagation();
        const card = pop.closest('.product-card');
        const id   = new URL(card.querySelector('.card-link').href).searchParams.get('id');

        if (e.target.classList.contains('report')) { alert('ขอบคุณสำหรับการรายงาน'); closeAll(); return; }
        if (e.target.classList.contains('edit'))   { location.href = `/page/products/edit.php?id=${id}`; return; }
        if (e.target.classList.contains('delete')) {
          if (confirm('ลบสินค้านี้?')) card.remove(); // TODO: เรียก API ลบ
          closeAll(); return;
        }
      }
    };

    const docCloser = (ev) => { if (!container.contains(ev.target)) closeAll(); };
    const escCloser = (ev) => { if (ev.key === 'Escape') closeAll(); };

    container.addEventListener('click', menuHandler, true);
    document.addEventListener('click', docCloser, true);
    container.addEventListener('keydown', escCloser, true);

    container.__menuHandler__ = menuHandler;
    container.__docCloser__   = docCloser;
    container.__escCloser__   = escCloser;
  }

  /* ---------- Utils ---------- */
  function buildUrl(endpoint, params) {
    const url = new URL(endpoint, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, v);
    });
    return url.toString();
  }

  /* ---------- localStorage: last + history ---------- */
  const LS_KEY_LAST = "lastSearch";
  const LS_KEY_HIST = "searchHistory";
  const HIST_LIMIT  = 10;

  const loadHistory = () => { try { return JSON.parse(localStorage.getItem(LS_KEY_HIST) || "[]"); } catch { return []; } };
  const saveHistory = (list) => { try { localStorage.setItem(LS_KEY_HIST, JSON.stringify(list.slice(0, HIST_LIMIT))); } catch {} };
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

  /* ---------- Main init ---------- */
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
      rememberLast      = true,
      // ✅ ใหม่: ใช้ส่งพารามิเตอร์เสริม (เช่น cat_slug ของหน้านั้น)
      extraParams       = null
    } = opts || {};

    const $input   = $(input);
    const $button  = $(button);
    const $results = $(results);
    const $section = $("#searchSection");
    const $count   = $("#searchCount");
    const $clear   = $("#clearSearch");
    if (!$input || !$button || !$results) return;

    const $anchor = $input.closest(".search-group") || $input.parentElement;

    /* ===== Suggest box ===== */
    let $sug = $("#qSuggest");
    if (!$sug) {
      $sug = document.createElement("div");
      $sug.id = "qSuggest";
      $sug.className = "search-suggest";
      document.body.appendChild($sug);
    }

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
    let activeIndex = -1;

    function doFetch(page = 1) {
      const q = ($input.value || "").trim();
      if (q.length < minLength) { exitSearchMode(); return; }
      enterSearchMode();

      // ✅ รวมพารามิเตอร์เสริม (เช่น { cat_slug: 'handmade' })
      const extra = (typeof extraParams === 'function') ? (extraParams() || {}) : (extraParams || {});
      const url = buildUrl(endpoint, { q, page, per, sort, ...extra });

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

          wireCardMenus($results);
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
        $sug.innerHTML = `<div class="sug-empty">ยังไม่มีประวัติการค้นหา</div>`;
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

      $sug.innerHTML = `${items}
        <button type="button" class="sug-clear">ล้างประวัติการค้นหา</button>`;

      $sug.classList.add("show");
      $sug.removeAttribute("hidden");
      positionSuggest();
      $input.setAttribute("aria-expanded", "true");
      activeIndex = -1;
    }

    function hideSuggest() {
      $sug.classList.remove("show");
      $sug.setAttribute("hidden", "");
      activeIndex = -1;
      $input.setAttribute("aria-expanded", "false");
    }
    const isSuggestOpen = () => $sug.classList.contains("show");

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
      else if (e.key === "Enter")  { e.preventDefault(); chooseActive() || doSearch(1); }
      else if (e.key === "Escape") { hideSuggest(); }
    });

    $input.addEventListener("input", () => { renderSuggest(); positionSuggest(); });
    $input.addEventListener("focus", () => {
      renderSuggest();
      $sug.classList.add("show");
      $sug.removeAttribute("hidden");
      positionSuggest();
    });

    $sug.addEventListener("mousedown", (e) => e.preventDefault());

    $sug.addEventListener("click", (e) => {
      if (e.target.classList.contains("sug-close")) { hideSuggest(); return; }
      if (e.target.classList.contains("sug-remove")) {
        e.preventDefault(); e.stopPropagation();
        const row = e.target.closest(".sug-item");
        const val = decodeURIComponent(row?.dataset.val || "");
        let hist = loadHistory().filter(x => x.toLowerCase() !== val.toLowerCase());
        saveHistory(hist);

        const nodes = [...$sug.querySelectorAll(".sug-item")];
        const oldIndex = Math.max(0, nodes.indexOf(row));

        if (hist.length) {
          renderSuggest(); positionSuggest();
          requestAnimationFrame(() => {
            const newNodes = [...$sug.querySelectorAll(".sug-item")];
            const nextIndex = Math.min(oldIndex, newNodes.length - 1);
            if (newNodes[nextIndex]) {
              newNodes.forEach(n => n.classList.remove("is-active"));
              newNodes[nextIndex].classList.add("is-active");
            }
          });
        } else {
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

    document.addEventListener("click", (e) => {
      if (!$sug.contains(e.target) && !$input.contains(e.target)) hideSuggest();
    });
    window.addEventListener("resize", positionSuggest);
    window.addEventListener("scroll", positionSuggest, { passive: true });

    if ($clear) {
      $clear.addEventListener("click", (e) => {
        e.preventDefault();
        $input.value = "";
        exitSearchMode();
        $input.focus();
      });
    }

    const last = getLast();
    if (prefillLastOnLoad && last) {
      $input.value = last;
      if (autoSearchOnLoad) doSearch(1);
    }

    exitSearchMode();
    return { search: doSearch, reset: exitSearchMode };
  }

  global.Search = { init: initSearch };
})(window);
