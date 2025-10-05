/* /js/products/upload-product.js
 * หน้าที่: หน้าอัปโหลดสินค้า
 * - ใส่ค่า shop_id จาก URL ลง hidden
 * - โหลดหมวดหมู่จาก backend
 * - ตรวจไฟล์รูป (ชนิด/ขนาด) ก่อนส่ง
 * - (ออปชั่น) บังคับต้องล็อกอินก่อนใช้งาน ถ้ามี Me.get()
 */
(() => {
  // ---------- ใส่ shop_id จาก URL ----------
  const shopId = new URLSearchParams(location.search).get('shop_id');
  const shopIdHidden = document.getElementById('shopIdHidden');
  if (shopIdHidden && shopId) shopIdHidden.value = shopId;

  // ---------- (ออปชั่น) บังคับล็อกอินก่อนอัปโหลด ----------
  // ต้องมี /js/me.js ถูกโหลดก่อนถึงจะทำงาน
  if (window.Me && typeof Me.get === 'function') {
    Me.get().then(d => {
      if (!d || !d.ok) {
        const next = encodeURIComponent(location.pathname + location.search);
        location.href = `/page/login.html?next=${next}`;
      } else {
        const chip = document.getElementById('userChip');
        const name = (d.user.display_name || d.user.name || '').trim();
        if (chip && name) { chip.textContent = name; chip.hidden = false; }
      }
    }).catch(()=>{ /* fail open */ });
  }

  // ---------- โหลดหมวดหมู่ ----------
  (function loadCategories(){
    const sel = document.getElementById('category');
    if (!sel) return;

    fetch('/page/backend/productsforsale/categories.php', { cache: 'no-store' })
      .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
      .then(items => {
        if (!Array.isArray(items) || items.length === 0) return; // ใช้ fallback เดิมใน HTML
        sel.innerHTML = '<option value="" disabled selected>เลือกหมวดหมู่</option>';
        items.forEach(it => {
          const opt = document.createElement('option');
          opt.value = it.id;
          opt.textContent = it.name;
          sel.appendChild(opt);
        });
      })
      .catch(err => console.error('Load categories failed:', err));
  })();

  // ---------- ตรวจไฟล์ก่อน submit ----------
  const form = document.querySelector('form[action="/page/backend/productsforsale/upload_product.php"]') || document.querySelector('form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    const main = document.getElementById('image_main')?.files?.[0];
    if (!main) {
      alert('กรุณาเลือกรูปสินค้าหลัก'); e.preventDefault(); return;
    }
    if (!isValidImage(main)) {
      alert('ไฟล์รูปหลักต้องเป็น JPG/PNG/WebP และขนาดไม่เกิน 5MB');
      e.preventDefault(); return;
    }

    const extras = Array.from(document.querySelectorAll('input[name="image_extra[]"]'))
      .map(i => i.files?.[0]).filter(Boolean);
    for (const f of extras) {
      if (!isValidImage(f)) {
        alert('ไฟล์รูปเพิ่มเติมต้องเป็น JPG/PNG/WebP และขนาดไม่เกิน 5MB');
        e.preventDefault(); return;
      }
    }
  });

  function isValidImage(file) {
    const okTypes = ['image/jpeg','image/png','image/webp'];
    const max = 5 * 1024 * 1024; // 5MB
    return okTypes.includes(file.type) && file.size <= max;
  }
})();
