/* /js/store/shop-hub.js
 * สำหรับหน้า: ร้านค้าของฉัน (store hub)
 * - ดึงข้อมูลร้านของผู้ใช้
 * - อัปเดตชื่อ/รหัสร้าน
 * - ผูกลิงก์: ข้อมูลร้าน, อัปโหลดสินค้า, จัดการสินค้า (แนบ shop_id)
 */

(() => {
  const API = '/page/backend/get_shop.php';  // ใช้ให้คงที่ทั้งโปรเจกต์

  const $name  = document.getElementById('shopName');
  const $ids   = document.getElementById('ids');
  const $info  = document.getElementById('infoLink');
  const $upload= document.getElementById('uploadLink');
  const $manage= document.getElementById('manageProductsLink');
  if (!$name || !$info || !$upload || !$manage) return;

  const goLogin = () => {
    const next = encodeURIComponent(location.pathname + location.search);
    location.href = `/page/login.html?next=${next}`;
  };

  fetch(API, { credentials: 'include', cache: 'no-store' })
    .then(r => {
      if (r.status === 401) { goLogin(); return Promise.reject(); }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(d => {
      // คาดหวัง: { ok:true, user_id, shop_id, shop_name }
      if (!d || !d.shop_id) {
        // ยังไม่มีร้าน → พาไปเปิดร้าน
        location.href = '/page/open-shop.html';
        return;
      }

      const sid = encodeURIComponent(d.shop_id);
      $name.textContent = d.shop_name || 'ร้านค้าของฉัน';
      if ($ids) $ids.textContent = `รหัสร้าน #${d.shop_id} • ผู้ใช้ #${d.user_id || '-'}`;

      // ปลายทางให้คง naming ภายใต้ /page/storepage/
      $info.href   = `/page/storepage/store-info.php?shop_id=${sid}`;
      $upload.href = `/page/storepage/upload_product.html?shop_id=${sid}`;
      $manage.href = `/page/storepage/store-products.php?shop_id=${sid}`;
    })
    .catch(() => {
      if ($name) $name.textContent = 'ไม่พบข้อมูลร้าน';
      if ($ids)  $ids.textContent  = '—';
    });
})();
