<!-- /page/partials/site-header.php -->

<ul class="top-nav">
    <li><a href="/exchangepage/index.html">แลกเปลี่ยนสินค้า</a></li>
    <li><span class="top-divider">|</span></li>
    <li><a id="openOrMyShop" href="/page/storepage/open_shop.php">เปิดร้านค้า</a></li>

    <li class="right"><a href="#notification">แจ้งเตือน</a></li>
    <li><span class="top-divider">|</span></li>
    <li><a href="#help">ช่วยเหลือ</a></li>
    <li><span class="top-divider">|</span></li>
    <li><a href="#lang">ไทย</a></li>
</ul>

<div class="header-wrapper">
    <div class="logo-container">
        <a href="/page/main.html" class="brand-text">THAMMUE</a>
    </div>

    <div class="search-container">
        <div class="search-group">
            <input type="text" class="search-input" placeholder="ค้นหาสินค้า" />
            <button class="search-button" aria-label="ค้นหา">
                <img src="/img/Icon/search.png" alt="ค้นหา" />
            </button>
        </div>


        <div class="icon-buttons">
            <a class="action-button" href="/page/favorites/index.php" aria-label="รายการโปรด">
                <img src="/img/Icon/heart.png" alt="รายการโปรด">
                <span id="favBadge" class="icon-badge" hidden>0</span>
            </a>


            <a class="action-button" href="/page/cart/index.php" aria-label="ตะกร้า" style="position:relative">
                <img src="/img/Icon/shopping-cart.png" alt="ตะกร้า">
                <span id="cartBadge" class="icon-badge" hidden>0</span>
            </a>

            <button class="action-button"><img src="/img/Icon/chat.png" alt="แชท" /></button>

            <div class="user-menu" id="userMenu">
                <button class="user-area" id="userArea" aria-haspopup="true" aria-expanded="false">
                    <img src="/img/Icon/user.png" alt="โปรไฟล์" />
                    <span class="user-chip" id="userChip" hidden></span>
                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M5.5 7.5l4.5 4 4.5-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="user-dropdown" id="userDropdown" role="menu"></div>
            </div>
        </div>
    </div>
</div>

<!-- เอาอันนี้ด้วยถ้าอยากโชว์แถบหมวดหมู่ -->
<div class="category-buttons" id="categoryButtons">
    <a href="/page/category/handmade.html" class="category-button" data-cat="handmade">สินค้าแฮนเมด</a>
    <a href="/page/category/craft.html" class="category-button" data-cat="craft">งานประดิษฐ์</a>
    <a href="/page/category/local_products.html" class="category-button" data-cat="local_products">สินค้าท้องถิ่น</a>
    <a href="/page/category/second_hand.html" class="category-button" data-cat="second_hand">สินค้ามือสอง</a>
</div>


<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const b = document.getElementById('favBadge');
        if (!b) return;

        try {
            const res = await fetch('/page/backend/likes_sale/stats.php?summary=favorites&type=product', {
                credentials: 'include',
                cache: 'no-store'
            });
            if (!res.ok) return;
            const data = await res.json(); // { total_favorites: N }

            if (typeof data.total_favorites !== 'undefined') {
                b.textContent = data.total_favorites;
                b.hidden = data.total_favorites <= 0;
            }
        } catch (e) {}
    });
</script>
<script src="/js/fav-badge.js" defer></script>

<script>
    function setCartBadge(n) {
        const el = document.getElementById('cartBadge');
        if (!el) return;
        n = parseInt(n || 0, 10);
        if (n > 0) {
            el.textContent = n;
            el.style.display = 'inline-flex';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    (async () => {
        try {
            const res = await fetch('/page/cart/get_cart.php', {
                credentials: 'include',
                cache: 'no-store'
            });
            if (!res.ok) return;
            const data = await res.json(); // ให้ get_cart.php ตอบ {count: ...}
            setCartBadge(data.count || 0);
        } catch (e) {
            console.error(e);
        }
    })();

    window.addEventListener('cart:set', e => setCartBadge(e.detail?.count ?? 0));
</script>