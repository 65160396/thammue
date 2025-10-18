// /js/category/category-search.js

document.addEventListener('DOMContentLoaded', () => {
  // ให้ suggest โชว์ทันทีเมื่อโฟกัส (เหมือนหน้าแรก/แฮนเมด)
  const qInput = document.getElementById('q');
  if (qInput) {
    qInput.addEventListener('focus', () => {
      const box = document.getElementById('qSuggest');
      if (box) {
        box.classList.add('show');
        box.removeAttribute('hidden');
      }
    });
  }

  // เติม history เริ่มต้น (ถ้ายังไม่มี)
  if (!localStorage.getItem('searchHistory')) {
    localStorage.setItem('searchHistory', JSON.stringify(['ทำมือ', 'สร้อยผ้า', 'งานประดิษฐ์']));
  }

  // อ่าน slug จาก <body data-slug="...">
  const slug = document.body.dataset.slug || '';

  // เรียกใช้ระบบค้นหา (ส่ง cat_slug ให้ backend เสมอ)
  if (window.Search) {
    Search.init({
      input: "#q",
      button: "#btnSearch",
      results: "#results",
      endpoint: "/page/backend/search_products.php",
      per: 24,
      sort: "relevance",
      minLength: 1,
      debounceMs: 300,
      prefillLastOnLoad: false,
      rememberLast: true,
      extraParams: () => ({ cat_slug: slug }) // ✅ สำคัญ
      // อยากให้โหลดสินค้าทันทีเมื่อเข้าเพจ:
      // minLength: 0, autoSearchOnLoad: true
    });
  }
});
