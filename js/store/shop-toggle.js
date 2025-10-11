// /js/store/shop-toggle.js

const OPEN_SHOP_URL = '/page/open_a_shop.html'; // ← หน้าแบบฟอร์มที่คุณมีจริง
const MY_STORE_URL  = (id) => `/page/storepage/store.html?shop_id=${encodeURIComponent(id)}`; // ← ชื่อไฟล์จริง

async function toggleOpenOrMyShop(linkId = 'openOrMyShop') {
  const link = document.getElementById(linkId);
  if (!link) return;

  try {
    const res = await fetch('/page/backend/productsforsale/get_shop.php', {
      credentials: 'include',
      cache: 'no-store'
    });
    const data = await res.json();

    if (data.ok && data.shop) {
      link.textContent = 'ร้านของฉัน';
      link.href = MY_STORE_URL(data.shop.id);     // << เปลี่ยนมาใช้ indexstore.html
    } else if (data.code === 'NOT_LOGIN') {
      link.textContent = 'เข้าสู่ระบบ';
      link.href = '/page/login.php';              // ถ้าคุณใช้ .php
    } else { // NO_SHOP
      link.textContent = 'เปิดร้านค้า';
      link.href = OPEN_SHOP_URL;                  // << เปลี่ยนมาใช้ open_a_shop.html
    }
  } catch (e) {
    console.error('shop-toggle:', e);
    link.textContent = 'เปิดร้านค้า';
    link.href = OPEN_SHOP_URL;                    // เผื่อ API ล่ม
  }
}
