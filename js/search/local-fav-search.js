// /js/search/local-fav-search.js
(function (global) {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  function buildSearchIndex(gridEl) {
    const cards = $$('.product-card', gridEl);
    cards.forEach(card => {
      const title = card.querySelector('.title, .product-title')?.textContent?.trim() || '';
      const meta  = card.querySelector('.meta-inline, .product-province')?.textContent?.trim() || '';
      card.dataset.search = (title + ' ' + meta).toLowerCase();
    });
    return cards;
  }

  function init(opts = {}) {
    const {
      inputSelector  = '#q',
      buttonSelector = '#btnSearch',
      sectionSelector= '#searchSection',
      resultsSelector= '#results',
      countSelector  = '#searchCount',
      clearSelector  = '#clearSearch',
      grid           = '#favGrid',
      wrapSelector   = '#favWrap',
      headSelector   = '.fav-header',
      emptyText      = 'ไม่พบสินค้าในรายการนี้'
    } = opts;

    const $input   = $(inputSelector);
    const $button  = $(buttonSelector);
    const $section = $(sectionSelector);
    const $results = $(resultsSelector);
    const $count   = $(countSelector);
    const $clear   = $(clearSelector);
    const $wrap    = $(wrapSelector);
    const $head    = $(headSelector);
    const $grid    = $(grid);

    if (!$input || !$button || !$results || !$grid) return;

    // ดัชนีเริ่มต้น
    let cards = buildSearchIndex($grid);

    function showResults(hits) {
      if (!hits.length) {
        $results.innerHTML = `<div class="empty" style="grid-column:1 / -1;">${emptyText}</div>`;
        if ($count) $count.textContent = '(0 รายการ)';
      } else {
        const html = hits.map(c => c.outerHTML).join('');
        $results.innerHTML = html;
        if ($count) $count.textContent = `(${hits.length} รายการ)`;

        if (typeof global.wireCardMenus === 'function') {
          global.wireCardMenus($results);
        }
      }
      $section.hidden = false;
      if ($wrap) $wrap.style.display = 'none';
      if ($head) $head.style.display = 'none';
    }

    function resetResults() {
      $section.hidden = true;
      $results.innerHTML = '';
      if ($count) $count.textContent = '';
      if ($wrap) $wrap.style.display = '';
      if ($head) $head.style.display = '';
    }

    // ✅ ปรับให้รับ qOverride
    function doSearch(qOverride) {
      const q = (qOverride ?? $input.value ?? '').trim().toLowerCase();
      if (!q) { resetResults(); return; }
      const hits = cards.filter(c => (c.dataset.search || '').includes(q));
      showResults(hits);
    }

    // events: ปุ่ม/Enter
    $button.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
    $input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });

    // ล้างผล
    if ($clear) {
      $clear.addEventListener('click', (e) => {
        e.preventDefault();
        $input.value = '';
        resetResults();
      });
    }

    // พิมพ์แล้วเคลียร์เป็นว่าง → กลับหน้าเดิม
    $input.addEventListener('input', () => {
      if (!$input.value.trim()) resetResults();
    });

    // ✅ รับคำค้นจากกล่อง Suggestion กลาง
    window.addEventListener('local-fav:search', (e) => {
      const q = (e.detail?.q || '').trim();
      if (q) $input.value = q;
      doSearch(q);
    });

    // ✅ รีบิวด์ดัชนีเมื่อมีการเปลี่ยนแปลงรายการโปรด
    // 1) ถ้ามีอีเวนต์ส่วนกลางจากการลบ/เพิ่ม
    window.addEventListener('favorites:changed', () => {
      cards = buildSearchIndex($grid);
      // ถ้าตอนนี้มีผลค้นหาเปิดอยู่ ให้ค้นหาใหม่ด้วยค่าปัจจุบัน
      if (!$section.hidden) doSearch();
    });
    // 2) กันเหนียว: เฝ้าดู DOM ของกริด
    const mo = new MutationObserver(() => {
      cards = buildSearchIndex($grid);
      if (!$section.hidden) doSearch();
    });
    mo.observe($grid, { childList: true, subtree: false });

    // Public API
    const api = { search: doSearch, reset: resetResults, rebuild: () => { cards = buildSearchIndex($grid); } };
    global.LocalSearch = { ...(global.LocalSearch || {}), init: () => api, ...api };
    return api;
  }

  // เก็บ init ไว้บน namespace
  (global.LocalSearch || (global.LocalSearch = {})).init = init;

})(window);
