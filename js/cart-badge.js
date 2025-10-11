function setCartBadge(n){
  const el = document.getElementById('cartBadge');
  if(!el) return;
  n = parseInt(n || 0, 10);
  if(n > 0){ el.textContent = n; el.hidden = false; }
  else { el.textContent = ''; el.hidden = true; }
}

// โหลดจำนวนเริ่มต้นทุกครั้งที่เปิดหน้า
(async () => {
  try{
    const res = await fetch('/page/cart/get_cart_count.php', {credentials:'include', cache:'no-store'});
    if(!res.ok) return;
    const data = await res.json(); // {count: N}
    setCartBadge(data.count || 0);
  }catch(e){ console.error('cart-badge init error', e); }
})();

// อัปเดตทันทีหลังเพิ่ม/ลบ ตะกร้า
window.addEventListener('cart:set', e => setCartBadge(e.detail?.count ?? 0));
