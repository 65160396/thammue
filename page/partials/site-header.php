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
            <input type="text" placeholder="ค้นหาสินค้า" />
            <button class="search-button" aria-label="ค้นหา">
                <img src="/img/Icon/search.png" alt="ค้นหา" />
            </button>
        </div>

        <div class="icon-buttons">
            <button class="action-button"><img src="/img/Icon/heart.png" alt="รายการโปรด" /></button>
            <button class="action-button"><img src="/img/Icon/shopping-cart.png" alt="ตะกร้า" /></button>
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
<div class="category-buttons">
    <a href="/page/category/handmade.html" class="category-button">สินค้าแฮนเมด</a>
    <a href="/page/category/jewelry.html" class="category-button">เครื่องประดับ</a>
    <a href="/page/category/local_products.html" class="category-button">สินค้าท้องถิ่น</a>
    <a href="/page/category/second_hand.html" class="category-button">สินค้ามือสอง</a>
</div>