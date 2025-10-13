// /page/js/cart.js
(function () {
  const setBadge = (n) =>
    window.dispatchEvent(new CustomEvent('cart:set', { detail: { count: Number(n) || 0 } }));

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
        location.href = '/page/login.html?next=' + encodeURIComponent(location.pathname + location.search);
        return;
      }

      const data = await res.json(); // { ok, in_cart, was_new, cart_unique }
      if (!res.ok || !data.ok) throw new Error('HTTP ' + res.status);

      // ปรับปุ่ม
      if (data.in_cart) {
        btn.textContent = 'อยู่ในตะกร้า';
        btn.classList.add('is-in-cart');
        btn.setAttribute('aria-pressed', 'true');
      }

      // อัปเดต badge ด้วย "จำนวนชนิดสินค้า"
      setBadge(data.cart_unique);   // ← ไม่เพิ่มเมื่อเป็นสินค้าเดิม
    } catch (err) {
      console.error(err);
      alert('เพิ่มใส่ตะกร้าไม่สำเร็จ');
    }
  });
})();
