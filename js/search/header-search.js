// /js/search/header-search.js
(function () {
  const input   = document.getElementById('q');
  const button  = document.getElementById('btnSearch');
  const suggest = document.getElementById('qSuggest');
  if (!input || !button || !suggest) return;

  // ---------- History ----------
  const KEY = 'searchHistory';
  const MAX = 8;

  function ensureDefaultHistory() {
    // ถ้าคีย์ยังไม่มี หรือมีแต่เป็น [] ให้ใส่ตัวอย่างเริ่มต้น
    let raw = localStorage.getItem(KEY);
    let arr;
    try { arr = raw ? JSON.parse(raw) : null; } catch { arr = null; }
    if (!Array.isArray(arr) || arr.length === 0) {
      localStorage.setItem(KEY, JSON.stringify(['ทำมือ','สร้อยผ้า','งานประดิษฐ์']));
    }
  }
  function getHist() {
    ensureDefaultHistory();
    try { return JSON.parse(localStorage.getItem(KEY)) || []; }
    catch { return []; }
  }
  function saveTerm(term) {
    let h = getHist().filter(t => t !== term);
    h.unshift(term);
    if (h.length > MAX) h = h.slice(0, MAX);
    localStorage.setItem(KEY, JSON.stringify(h));
  }

  // ---------- UI ----------
  function renderSuggest() {
    const q = (input.value || '').trim().toLowerCase();
    const list = getHist().filter(t => !q || t.toLowerCase().includes(q));
    if (!list.length) {
      suggest.innerHTML = '';
      suggest.classList.remove('show');
      return;
    }
    suggest.innerHTML = `
      <ul role="listbox">
        ${list.map(t => `<li role="option" class="item" data-term="${t}">${t}</li>`).join('')}
      </ul>
      <div class="suggest-footer">
        <button type="button" id="clearHist" class="linklike">ล้างประวัติการค้นหา</button>
      </div>
    `;
    suggest.classList.add('show');
    suggest.removeAttribute('hidden');
  }
  function hideSuggest() { suggest.classList.remove('show'); }

  // เลือกจากรายการ / ล้างประวัติ
  suggest.addEventListener('click', (e) => {
    const li = e.target.closest('.item');
    if (li) {
      input.value = li.dataset.term;
      doSearch();
      return;
    }
    if (e.target.id === 'clearHist') {
      localStorage.setItem(KEY, '[]'); // เคลียร์ให้หมด
      renderSuggest();                 // จะเติมค่า default ให้อีกครั้งเมื่อ focus ใหม่
    }
  });

  // ---------- Redirect ----------
  function doSearch() {
    const q = (input.value || '').trim();
    if (!q) return;
    saveTerm(q);
    location.href = `/page/main.html?q=${encodeURIComponent(q)}#searchSection`;
  }

  // ---------- Bind ----------
  input.addEventListener('focus', renderSuggest);
  input.addEventListener('input', renderSuggest);
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-group')) hideSuggest();
  });
  button.addEventListener('click', doSearch);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
  });
})();
