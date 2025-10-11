// /page/js/cart.js
(function () {
  // อัปเดต badge ที่ header
  const setBadge = (n) =>
    window.dispatchEvent(new CustomEvent('cart:set', { detail: { count: n } }));

  // รองรับคลิกปุ่มเพิ่ม/ลบตะกร้า ทั้งบนการ์ดสินค้าและปุ่มล่างหน้า detail
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.add-cart, .add-cart-bottom');
    if (!btn) return;

    e.preventDefault();
    const id = btn.dataset.id;
    if (!id) return;

    try {
      const res = await fetch('/page/cart/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id })
      });

      if (res.status === 401) {
        // ถ้ายังไม่ล็อกอิน ส่งไปหน้า login แล้วกลับมาหน้าเดิม
        location.href = '/page/login.html?next=' +
          encodeURIComponent(location.pathname + location.search);
        return;
      }

      const data = await res.json(); // { in_cart: true/false, cart_count: number }
      if (!res.ok) throw new Error('HTTP ' + res.status);

      // อัปเดตปุ่มตามสถานะล่าสุด
      if (data.in_cart) {
        btn.textContent = 'อยู่ในตะกร้า';
        btn.classList.add('is-in-cart');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.textContent = 'เพิ่มใส่ตะกร้า';
        btn.classList.remove('is-in-cart');
        btn.setAttribute('aria-pressed', 'false');
      }

      // อัปเดต badge ตะกร้า
      setBadge(data.cart_count || 0);
    } catch (err) {
      console.error(err);
      alert('เพิ่ม/ลบจากตะกร้าไม่สำเร็จ');
    }
  });
})();
