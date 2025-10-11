document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.add-cart, .add-cart-bottom');
  if (!btn) return;

  e.preventDefault();
  const id = btn.dataset.id;

  try {
    const res = await fetch('/page/cart/add_to_cart.php', {
      method: 'POST', headers: { 'Content-Type':'application/json' },
      credentials:'include', body: JSON.stringify({ id })
    });
    if (res.status === 401) {
      location.href = '/page/login.html?next=' + encodeURIComponent(location.pathname + location.search);
      return;
    }
    const data = await res.json();
    if (!res.ok) throw new Error('HTTP ' + res.status);

    // อัปเดตปุ่ม
    if (data.in_cart){ btn.textContent = 'อยู่ในตะกร้า'; btn.classList.add('is-in-cart'); }
    else { btn.textContent = 'เพิ่มใส่ตะกร้า'; btn.classList.remove('is-in-cart'); }

    // อัปเดต badge ที่ header ตาม count ล่าสุดจาก API
    window.dispatchEvent(new CustomEvent('cart:set', { detail: { count: data.cart_count || 0 }}));
  } catch (err) {
    console.error(err);
    alert('เพิ่ม/ลบจากตะกร้าไม่สำเร็จ');
  }
});
