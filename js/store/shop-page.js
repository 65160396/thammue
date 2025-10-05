document.addEventListener('DOMContentLoaded', async () => {
  const nameEl = document.getElementById('shopName');
  const idEl   = document.getElementById('shopId');
  const stEl   = document.getElementById('shopStatus');

  try {
    const res = await fetch('/page/backend/productsforsale/get_shop.php', { 
      credentials: 'include', 
      cache: 'no-store' 
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (!data.ok) {
      nameEl.textContent = 'กรุณาเข้าสู่ระบบ';
      idEl.textContent = '—';
      stEl.textContent = '—';
      return;
    }

    if (!data.shop) {
      nameEl.textContent = 'ไม่พบข้อมูลร้าน';
      idEl.textContent = '—';
      stEl.textContent = '—';
      return;
    }

    // ✅ มีร้านแล้ว
    const s = data.shop;
    nameEl.textContent = s.shop_name || '(ไม่มีชื่อร้าน)';
    idEl.textContent   = s.tracking ?? s.id ?? '—';
    stEl.textContent   = s.status ?? '—';

    // ✅ เพิ่มส่วนนี้ — ตั้งลิงก์ไปหน้าต่าง ๆ พร้อม shop_id
    const sid = encodeURIComponent(s.id);
    document.getElementById('infoLink').href   = `/page/storepage/store-info.html?shop_id=${sid}`;
    document.getElementById('uploadLink').href = `/page/storepage/upload_product.html?shop_id=${sid}`;
    document.getElementById('manageProductsLink').href = `/page/storepage/store-products.html?shop_id=${sid}`;

  } catch (e) {
    console.error(e);
    nameEl.textContent = 'โหลดข้อมูลไม่สำเร็จ';
    idEl.textContent = stEl.textContent = '—';
  }
});
