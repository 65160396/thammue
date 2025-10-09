(function () {
  async function refreshFavBadge() {
    const b = document.getElementById('favBadge');
    if (!b) return;
    try {
      const res = await fetch('/page/backend/likes_sale/stats.php?summary=favorites&type=product', {
        credentials: 'include',
        cache: 'no-store'
      });
      if (!res.ok) return;
      const { total_favorites = 0 } = await res.json();
      b.textContent = total_favorites;
      b.hidden = total_favorites <= 0;
    } catch (e) {}
  }

  // โหลดหน้าไหนก็อัปเดต
  document.addEventListener('DOMContentLoaded', refreshFavBadge);

  // ให้หน้าต่าง ๆ แจ้งว่า count เปลี่ยน (ไม่ต้องยิง API ซ้ำ)
  window.addEventListener('favorites:changed', (e) => {
    const b = document.getElementById('favBadge');
    if (!b) return;
    const cur = parseInt(b.textContent || '0', 10) || 0;
    let n = cur;

    if (typeof e.detail?.delta === 'number') {
      n = Math.max(0, cur + e.detail.delta);
    } else if (typeof e.detail?.liked === 'boolean') {
      n = Math.max(0, cur + (e.detail.liked ? 1 : -1));
    } else {
      return refreshFavBadge();
    }
    b.textContent = n;
    b.hidden = n <= 0;
  });

  // เผื่ออยากเรียกเอง
  window.refreshFavBadge = refreshFavBadge;
})();
