// /js/nav/hamburger.js
(function () {
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('iconsDrawer');
  const closeBtn = document.getElementById('iconsClose');
  const backdrop = document.getElementById('iconsBackdrop');

  if (!btn || !drawer || !closeBtn || !backdrop) return;

  const body = document.body;

  function syncBadges() {
    // ดึงตัวเลขจาก badge เดสก์ท็อปไปแสดงใน drawer ด้วย (ถ้ามี)
    const map = [
      { src: '#favBadge',  id: 'favBadgeMobile'  },
      { src: '#cartBadge', id: 'cartBadgeMobile' },
      { src: '#chatBadge', id: 'chatBadgeMobile' },
    ];
    map.forEach(({ src, id }) => {
      const s = document.querySelector(src);
      let v = s && !s.hasAttribute('hidden') ? s.textContent.trim() : '';
      const slot = drawer.querySelector(`#${id}`);
      if (slot) {
        if (v && v !== '0') {
          slot.textContent = v;
          slot.removeAttribute('hidden');
        } else {
          slot.textContent = '0';
          slot.setAttribute('hidden','');
        }
      }
    });
  }

  function openDrawer() {
    syncBadges();
    drawer.hidden = false;
    backdrop.hidden = false;
    // ใช้คลาสแทน style เพื่อเลื่อนเข้า
    requestAnimationFrame(() => drawer.classList.add('open'));
    btn.setAttribute('aria-expanded', 'true');
    body.classList.add('body-lock');
    // โฟกัสปุ่มปิดเพื่อการเข้าถึง
    closeBtn.focus();
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
    body.classList.remove('body-lock');
    // รอแอนิเมชันแล้วค่อยซ่อน
    setTimeout(() => {
      drawer.hidden = true;
      backdrop.hidden = true;
      btn.focus();
    }, 250);
  }

  btn.addEventListener('click', openDrawer);
  closeBtn.addEventListener('click', closeDrawer);
  backdrop.addEventListener('click', closeDrawer);

  // ปิดด้วย ESC
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !drawer.hidden) closeDrawer();
  });

  // ปิดเมื่อคลิกลิงก์ใน drawer
  drawer.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (a) closeDrawer();
  });

  // เปิด/ปิดตามรีไซซ์ (ถ้ากลับเป็น desktop ให้ปิด drawer)
  const mq = window.matchMedia('(min-width: 993px)');
  mq.addEventListener('change', (e) => {
    if (e.matches) closeDrawer();
  });
})();
