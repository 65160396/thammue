/* /js/store/shop-page.js
 * หน้าที่: หน้า “ร้านค้า” (store.html)
 * - ดึงข้อมูลร้านของผู้ใช้
 * - อัปเดตชื่อร้าน/เมตา
 * - เซ็ตลิงก์ปุ่ม "ข้อมูลร้านค้า" และ "อัพโหลดสินค้า" พร้อม shop_id
 */

(() => {
  const ENDPOINT = '/page/backend/get_shop.php'; // ปรับให้ตรง backend
  const UPLOAD_PAGE = '/page/storepage/upload_product.html'; // ให้ตรงกับหน้าที่คุณใช้จริง
  const STORE_INFO_PAGE = '/page/storepage/store-info.php';

  const $name = document.getElementById('shopName');
  const $meta = document.getElementById('shopMeta');
  const $btnInfo = document.getElementById('btnStoreInfo');
  const $btnUpload = document.getElementById('btnUpload');

  if (!$name || !$btnInfo || !$btnUpload) return;

  const goLogin = () => {
    const next = encodeURIComponent(location.pathname + location.search);
    location.href = `/page/login.html?next=${next}`;
  };

  fetch(ENDPOINT, { credentials: 'include', cache: 'no-store' })
    .then(r => {
      if (r.status === 401) { goLogin(); return Promise.reject(); }
      return r.json();
    })
    .then(d => {
      // คาดหวัง: { ok:true, user_id: 2, shop_id: 1, shop_name: "..." }
      if (!d || !d.shop_id) {
        // ยังไม่มีร้าน → พาไปหน้าเปิดร้าน
        location.href = '/page/open-shop.html';
        return;
      }

      $name.textContent = d.shop_name || 'ร้านค้าของฉัน';
      if ($meta) $meta.textContent = `รหัสร้าน #${d.shop_id} • ผู้ใช้ #${d.user_id || '-'}`;

      // ผูกลิงก์
      const sid = encodeURIComponent(d.shop_id);
      $btnInfo.href   = `${STORE_INFO_PAGE}?shop_id=${sid}`;
      $btnUpload.href = `${UPLOAD_PAGE}?shop_id=${sid}`;
    })
    .catch(() => {
      if ($name) $name.textContent = 'ไม่พบข้อมูลร้าน';
    });
})();
