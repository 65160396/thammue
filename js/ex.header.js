/* /js/ex.header.js
   - เรียกใช้งานหลัง header ถูก include เสร็จ โดย ex.include.js จะเรียก window.exHeaderInit()
   - ผูก hamburger/drawer + dropdown โปรไฟล์
   - ดึงชื่อผู้ใช้/เมนูจาก /page/backend/me.php ผ่าน Me.get()
   - ดึง badge จาก /page/backend/ex_badge_counts.php มาอัปเดตเลขแจ้งเตือน
*/
(function () {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  const GUEST_MENU = [
    '<a href="/page/login.html" role="menuitem">เข้าสู่ระบบ</a>',
    '<a href="/page/register.html" role="menuitem">สมัครสมาชิก</a>',
  ].join('');

  const USER_MENU = [
    '<a href="/page/profile.html" role="menuitem">บัญชีของฉัน</a>',
    '<a href="/page/orders/index.php" role="menuitem">การซื้อของฉัน</a>',
    '<a href="/page/change_password.html" role="menuitem">เปลี่ยนรหัสผ่าน</a>',
    '<hr>',
    '<a href="/page/backend/logout.php" role="menuitem" class="logout">ออกจากระบบ</a>',
  ].join('');

  window.exHeaderInit = function exHeaderInit() {
    const drawer          = $('#iconsDrawer');
    const backdrop        = $('#iconsBackdrop');
    const hamBtn          = $('#hamburgerBtn');
    const closeBtn        = $('#iconsClose');

    const userMenu        = $('#userMenu');
    const userArea        = $('#userArea');
    const userChip        = $('#userChip');
    const userDropdown    = $('#userDropdown');

    const mobileAccBtn    = $('#mobileProfileToggle');
    const mobileAccMenu   = $('#mobileAccountMenu');
    const mobileUserChip  = $('#mobileUserChip');

    if (!userDropdown) {
      requestAnimationFrame(() => window.exHeaderInit && window.exHeaderInit());
      return;
    }

    // ---------- Drawer ----------
    const lock   = () => document.body.classList.add('body-lock');
    const unlock = () => document.body.classList.remove('body-lock');

    function openDrawer() {
      if (!drawer || !backdrop) return;
      drawer.hidden = false;
      backdrop.hidden = false;
      requestAnimationFrame(() => {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        hamBtn?.setAttribute('aria-expanded', 'true');
        lock();
      });
    }
    function closeDrawer() {
      if (!drawer || !backdrop) return;
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden', 'true');
      hamBtn?.setAttribute('aria-expanded', 'false');
      unlock();
      setTimeout(() => { drawer.hidden = true; backdrop.hidden = true; }, 250);
    }
    hamBtn?.addEventListener('click', openDrawer);
    closeBtn?.addEventListener('click', closeDrawer);
    backdrop?.addEventListener('click', closeDrawer);
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });

    // ---------- Desktop: dropdown โปรไฟล์ ----------
    if (userArea && userMenu) {
      userArea.addEventListener('click', (e) => {
        e.preventDefault();
        const open = userMenu.classList.toggle('open');
        userArea.setAttribute('aria-expanded', String(open));
      });
      document.addEventListener('click', (e) => {
        if (!userMenu.contains(e.target) && e.target !== userArea) {
          userMenu.classList.remove('open');
          userArea.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          userMenu.classList.remove('open');
          userArea.setAttribute('aria-expanded', 'false');
          userArea.blur();
        }
      });
    }

    // ---------- เติมชื่อผู้ใช้ + เมนู ----------
    async function renderUserUI() {
      try {
        const me = (window.Me && typeof Me.get === 'function') ? await Me.get() : { ok: false };
        if (me && me.ok && me.user) {
          const name = (me.user.display_name || me.user.name || '').trim();
          if (name && userChip) {
            userChip.textContent = name;
            userChip.hidden = false;
          } else if (userChip) {
            userChip.hidden = true;
          }
          userDropdown.innerHTML = USER_MENU;
          if (mobileUserChip && name) mobileUserChip.textContent = `โปรไฟล์ · ${name}`;
          else if (mobileUserChip)    mobileUserChip.textContent = 'โปรไฟล์';
        } else {
          if (userChip) userChip.hidden = true;
          userDropdown.innerHTML = GUEST_MENU;
          if (mobileUserChip) mobileUserChip.textContent = 'โปรไฟล์';
        }
      } catch {
        if (userChip) userChip.hidden = true;
        userDropdown.innerHTML = GUEST_MENU;
        if (mobileUserChip) mobileUserChip.textContent = 'โปรไฟล์';
      }
    }

    function syncMenuToDrawer() {
      if (!mobileAccMenu) return false;
      const items = $$('#userDropdown a, #userDropdown hr');
      if (!items.length) return false;
      const fr = document.createDocumentFragment();
      items.forEach((el) => {
        const clone = el.cloneNode(true);
        if (clone.tagName === 'A') {
          clone.removeAttribute('role');
          clone.style.color = 'inherit';
        }
        fr.appendChild(clone);
      });
      mobileAccMenu.innerHTML = '';
      mobileAccMenu.appendChild(fr);
      return true;
    }

    mobileAccBtn?.addEventListener('click', () => {
      const expanded = mobileAccBtn.getAttribute('aria-expanded') === 'true';
      if (!expanded) {
        const ok = syncMenuToDrawer();
        if (!ok && mobileAccMenu) mobileAccMenu.innerHTML = GUEST_MENU;
      }
      mobileAccBtn.setAttribute('aria-expanded', String(!expanded));
      if (mobileAccMenu) mobileAccMenu.hidden = expanded;
    });

    if (userDropdown) {
      const mo = new MutationObserver(() => {
        if (mobileAccMenu && !mobileAccMenu.hidden) syncMenuToDrawer();
      });
      mo.observe(userDropdown, { childList: true, subtree: true });
    }

    // ---------------- Badge ----------------
    function setBadge(id, n){
      const el = document.getElementById(id);
      if(!el) return;
      const v = Number(n||0);
      if(v>0){ el.textContent = v>99 ? '99+' : String(v); el.hidden = false; }
      else { el.hidden = true; }
    }
    function setBoth(a,b,n){ setBadge(a,n); setBadge(b,n); }
    function hideAllBadges(){
      [
        'reqBadge','favBadge','chatBadge','notiBadge',
        'reqBadgeMobile','favBadgeMobile','chatBadgeMobile','notiBadgeMobile'
      ].forEach(id => { const el = document.getElementById(id); if(el) el.hidden = true; });
    }

    async function refreshExBadges(){
      try{
        const r = await fetch('/page/backend/ex_badge_counts.php', {credentials:'include', cache:'no-store'});
        if(!r.ok){ hideAllBadges(); return; }
        const d = await r.json().catch(()=>null);
        if(!d?.ok){ hideAllBadges(); return; }

        // รองรับทั้งคีย์เก่า/ใหม่
        const req  = Number(d.incoming_requests    ?? d.pending_requests      ?? 0);
        const fav  = Number(d.favorites            ?? 0);
        const chat = Number(d.unread_messages      ?? 0);
        const noti = Number(d.notifications_unread ?? d.unread_notifications  ?? 0);

        setBoth('reqBadge','reqBadgeMobile', req);
        setBoth('favBadge','favBadgeMobile', fav);
        setBoth('chatBadge','chatBadgeMobile', chat);
        // แสดงเฉพาะจำนวนแจ้งเตือนจากตาราง ex_notifications
        setBoth('notiBadge','notiBadgeMobile', noti);
      }catch{
        hideAllBadges();
      }
    }
    window.refreshExBadges = refreshExBadges;

    // ยิงครั้งแรก + ตั้ง interval
    renderUserUI();
    refreshExBadges();
    setInterval(refreshExBadges, 15000);
  };

  if (document.querySelector('.header-wrapper') && window.exHeaderInit) {
    window.exHeaderInit();
  }
})();
