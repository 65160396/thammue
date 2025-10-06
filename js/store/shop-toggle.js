/**
 * shop-toggle.js
 * -----------------
 * หน้าที่: ตรวจสอบว่าผู้ใช้มีร้านค้าแล้วหรือยัง
 *   - ถ้ามีร้าน → เปลี่ยนปุ่ม "เปิดร้านค้า" ให้กลายเป็น "ร้านของฉัน"
 *   - ถ้ายังไม่มีร้าน → คงไว้เป็น "เปิดร้านค้า"
 *
 * ใช้ใน: main.html, storepage/indexstore.html หรือหน้าอื่นที่มี top-nav
 * พึ่งพา API: /page/backend/productsforsale/get_shop.php
 */

async function toggleOpenOrMyShop(linkId = 'openOrMyShop') {
  const link = document.getElementById(linkId);
  if (!link) return;

  try {
    const res = await fetch('/page/backend/productsforsale/get_shop.php', {
      credentials: 'include', cache: 'no-store'
    });
    const data = await res.json();

    if (data.ok && data.shop) {
      const sid = encodeURIComponent(data.shop.id);
      link.textContent = 'ร้านของฉัน';
      link.href = `/page/storepage/store.html?shop_id=${sid}`;
      return sid; // เผื่อหน้าไหนอยากนำไปใช้ต่อ
    } else {
      link.textContent = 'เปิดร้านค้า';
      link.href = '/page/storepage/open_shop.php';
      return null;
    }
  } catch (e) {
    console.error('shop-toggle:', e);
    // ล้มเหลว ให้คงเป็น “เปิดร้านค้า”
    link.textContent = 'เปิดร้านค้า';
    link.href = '/page/storepage/open_shop.php';
    return null;
  }
}
