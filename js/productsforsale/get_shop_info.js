document.addEventListener('DOMContentLoaded', async () => {
  console.log('[get_shop_info] script loaded'); // >>> ต้องเห็นใน Console

  const nameEl   = document.getElementById('shopName');
  const idEl     = document.getElementById('ids');         // ให้ตรงกับ HTML
  const statusEl = document.getElementById('shopStatus');  // ถ้ามี

  try {
    // ✅ path ต้องตรงกับไฟล์ PHP จริง
    const res = await fetch('/page/backend/productsforsale/get_shop.php', {
      credentials: 'include',
      cache: 'no-store'
    });

    if (!res.ok) {
      console.warn('HTTP error', res.status);
      nameEl.textContent = 'โหลดข้อมูลไม่สำเร็จ';
      return;
    }

    const data = await res.json();
    console.log('[get_shop_info] response:', data); // >>> ดูใน Console

    if (!data.ok) {
      nameEl.textContent = 'กรุณาเข้าสู่ระบบ';
      return;
    }
    if (!data.shop) {
      nameEl.textContent = 'ไม่พบข้อมูลร้าน';
      if (idEl) idEl.textContent = '—';
      return;
    }

    const s = data.shop || {};
    const name = (s.name || s.shop_name || '').trim();

    nameEl.textContent = name || '(ไม่มีชื่อร้าน)';
    if (idEl)     idEl.textContent     = (s.id ?? '-');
    if (statusEl) statusEl.textContent = (s.status ?? '-');

  } catch (err) {
    console.error(err);
    nameEl.textContent = 'โหลดข้อมูลไม่สำเร็จ';
  }
});
