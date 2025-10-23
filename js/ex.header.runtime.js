
/* ex.header.runtime.js */
document.addEventListener('DOMContentLoaded', () => {
  // ====== Search ======
  const q = document.getElementById('q');
  const qSuggest = document.getElementById('qSuggest');
  const btnSearch = document.getElementById('btnSearch');

  // แสดง suggestion เมื่อโฟกัส (กันธีมอื่นซ่อน)
  if (q && qSuggest) {
    q.addEventListener('focus', () => {
      qSuggest.classList.add('show');
      qSuggest.hidden = false;
      q.setAttribute('aria-expanded','true');
    });
    q.addEventListener('blur', () => {
      setTimeout(() => { qSuggest.hidden = true; q.setAttribute('aria-expanded','false'); }, 120);
    });
  }

  // ปุ่มค้นหา (ถ้างานของคุณต้อง redirect แบบ JS)
  if (btnSearch && q) {
    btnSearch.addEventListener('click', (e) => {
      // ถ้า form มี action แล้ว ปล่อย submit ตามปกติ
      const form = btnSearch.closest('form');
      if (form) return; 
      // fallback
      e.preventDefault();
      const s = (q.value || '').trim();
      if (s) location.href = '/page/ex_requests.html?q=' + encodeURIComponent(s);
    });
  }

  // ====== User menu (desktop) ======
  const userMenu = document.getElementById('userMenu');
  const userBtn  = document.getElementById('userArea');
  const userDd   = document.getElementById('userDropdown');

  function closeUserMenu(){ userMenu?.classList.remove('open'); userBtn?.setAttribute('aria-expanded','false'); }
  userBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const opened = userMenu?.classList.toggle('open');
    userBtn.setAttribute('aria-expanded', opened ? 'true':'false');
  });
  document.addEventListener('click', closeUserMenu);
  userDd?.addEventListener('click', (e)=> e.stopPropagation());

  // ====== Drawer (mobile) ======
  const drawer   = document.getElementById('iconsDrawer');
  const backdrop = document.getElementById('iconsBackdrop');
  const hamb     = document.getElementById('hamburgerBtn');
  const closeBtn = document.getElementById('iconsClose');

  function openDrawer(){
    if(!drawer) return;
    drawer.hidden = false;
    requestAnimationFrame(()=> drawer.classList.add('open'));
    backdrop.hidden = false;
    document.body.classList.add('body-lock');
    hamb?.setAttribute('aria-expanded','true');
  }
  function closeDrawer(){
    if(!drawer) return;
    drawer.classList.remove('open');
    setTimeout(()=> { drawer.hidden = true; }, 200);
    backdrop.hidden = true;
    document.body.classList.remove('body-lock');
    hamb?.setAttribute('aria-expanded','false');
  }
  hamb?.addEventListener('click', openDrawer);
  closeBtn?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);
  window.addEventListener('keydown', (e)=> { if(e.key==='Escape') closeDrawer(); });

  // ====== Sync เมนูโปรไฟล์เข้า Drawer ======
  const mobileToggle = document.getElementById('mobileProfileToggle');
  const mobileMenu   = document.getElementById('mobileAccountMenu');

  function syncDesktopMenuToMobile() {
    if (!userDd || !mobileMenu) return false;
    const fr = document.createDocumentFragment();
    const items = userDd.querySelectorAll('a, hr');
    if (!items.length) return false;
    items.forEach(el => {
      const clone = el.cloneNode(true);
      if (clone.tagName === 'A') clone.style.color = 'inherit';
      fr.appendChild(clone);
    });
    mobileMenu.innerHTML = '';
    mobileMenu.appendChild(fr);
    return true;
  }

  function renderMobileFallback() {
    if (!mobileMenu) return;
    mobileMenu.innerHTML = `
      <a href="/page/profile.html">โปรไฟล์ของฉัน</a>
      <a href="/page/ex_my_items.html">สินค้าของฉัน</a>
      <a href="/page/ex_requests.html">การแลกเปลี่ยนของฉัน</a>
      <hr>
      <a class="logout" href="/page/logout.php">ออกจากระบบ</a>
    `;
  }

  mobileToggle?.addEventListener('click', () => {
    const expanded = mobileToggle.getAttribute('aria-expanded') === 'true';
    if (!expanded) {
      const ok = syncDesktopMenuToMobile();
      if (!ok) renderMobileFallback();
    }
    mobileToggle.setAttribute('aria-expanded', String(!expanded));
    mobileMenu.hidden = expanded;
  });

  // ถ้าเดสก์ท็อปเรนเดอร์เมนูล่าช้า ให้ sync อัตโนมัติเมื่อมีการเปลี่ยน
  if (userDd) {
    const mo = new MutationObserver(() => { if (!mobileMenu?.hidden) syncDesktopMenuToMobile(); });
    mo.observe(userDd, { childList: true, subtree: true });
  }
});

