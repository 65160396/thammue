document.addEventListener('DOMContentLoaded', async () => {
  const nameEl = document.getElementById('shopName');
  const idEl   = document.getElementById('shopId');
  const stEl   = document.getElementById('shopStatus');

  try {
    const res = await fetch('/productsforsale/get_shop.php', { credentials: 'include' });
    const data = await res.json();

    if (!data.ok) {
      nameEl.textContent = 'กรุณาเข้าสู่ระบบ';
      return;
    }

    if (!data.shop) {
      nameEl.textContent = 'ไม่พบข้อมูลร้าน';
      idEl.textContent = '-';
      stEl.textContent = '-';
      return;
    }

    // ✅ มีร้านแล้ว
    nameEl.textContent = data.shop.shop_name || '(ไม่มีชื่อร้าน)';
    idEl.textContent   = data.shop.id;
    stEl.textContent   = data.shop.status;
  } catch (err) {
    console.error(err);
    nameEl.textContent = 'โหลดข้อมูลไม่สำเร็จ';
  }
});
