// /js/nav/drawer-sync.js
(function () {
  // กันผูกซ้ำถ้าถูก include หลายรอบ
  if (window.__drawerSyncInit) return;
  window.__drawerSyncInit = true;

  function mirrorBadge(desktopId, mobileId) {
    const d = document.getElementById(desktopId);
    const m = document.getElementById(mobileId);
    if (!m) return;

    function apply() {
      if (!d) return;
      m.textContent = d.textContent || '0';
      m.hidden = d.hasAttribute('hidden') || Number(d.textContent || 0) <= 0;
    }

    apply();

    if (d) {
      new MutationObserver(apply).observe(d, {
        childList: true,
        attributes: true,
        characterData: true,
        subtree: true,
      });
    }

    window.addEventListener('favorites:changed', (e) => {
      const v = Math.max(0, Number(m.textContent || 0) + (e.detail?.delta || 0));
      m.textContent = v;
      m.hidden = v <= 0;
    });

    window.addEventListener('cart:set', (e) => {
      const v = Math.max(0, Number(e.detail?.count || 0));
      m.textContent = v;
      m.hidden = v <= 0;
    });
  }

  function initDrawerSync() {
    const toggle = document.getElementById('mobileProfileToggle');
    const menu   = document.getElementById('mobileAccountMenu');
    const dd     = document.getElementById('userDropdown');
    if (!toggle || !menu) return; // ไม่มี drawer ก็ไม่ทำอะไร

    function syncMenu() {
      if (!dd) return false;
      const items = dd.querySelectorAll('a, hr');
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
      menu.innerHTML = '';
      menu.appendChild(fr);
      return true;
    }

    function fallback() {
      menu.innerHTML = `
        <a href="/page/profile.html">บัญชีของฉัน</a>
        <a href="/page/orders/index.php">การซื้อของฉัน</a>
        <a href="/page/change_password.html">เปลี่ยนรหัสผ่าน</a>
        <hr>
        <a class="logout" href="/page/logout.php">ออกจากระบบ</a>
      `;
    }

    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      if (!expanded && !syncMenu()) fallback();
      toggle.setAttribute('aria-expanded', String(!expanded));
      menu.hidden = expanded;
    });

    if (dd) {
      new MutationObserver(() => {
        if (!menu.hidden) syncMenu();
      }).observe(dd, { childList: true, subtree: true });
    }

    // sync badge
    mirrorBadge('favBadge',  'favBadgeMobile');
    mirrorBadge('cartBadge', 'cartBadgeMobile');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDrawerSync);
  } else {
    initDrawerSync();
  }
})();
