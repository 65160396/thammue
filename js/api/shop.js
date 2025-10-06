// /js/api/shop.js
export async function fetchMyShop() {
  try {
    const res = await fetch('/page/backend/productsforsale/get_shop.php', {
      credentials: 'include', cache: 'no-store'
    });
    const data = await res.json();
    if (data.ok && data.shop) return data.shop;     // {id, name, status}
    return null;                                     // NO_SHOP / NOT_LOGIN
  } catch (e) {
    console.error('fetchMyShop error:', e);
    return null;
  }
}
