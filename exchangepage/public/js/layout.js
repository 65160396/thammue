// /exchangepage/public/js/layout.js
(async function () {
  // ใส่ header / footer (อิงจากตำแหน่งเพจปัจจุบันใน /exchangepage/public/)
  async function inject(sel, url) {
    const host = document.querySelector(sel);
    if (!host) return;
    try {
      const r = await fetch(url, { cache: 'no-store' });
      if (!r.ok) return;
      host.innerHTML = await r.text();
    } catch (_) {}
  }
  await inject('#site-header', 'partials/header.html');
  await inject('#site-footer', 'partials/footer.html');

  // เมนูมือถือ
  const drawer   = document.getElementById('mobileIcons');
  const backdrop = document.querySelector('.icons-backdrop');
  const ham      = document.querySelector('.hamburger');
  const closeBtn = document.querySelector('.icons-drawer__close');

  const open = () => {
    if (!drawer) return;
    drawer.hidden = false;
    if (backdrop) backdrop.hidden = false;
    requestAnimationFrame(() => drawer.classList.add('open'));
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    if (!drawer) return;
    drawer.classList.remove('open');
    document.body.style.overflow = '';
    if (backdrop) backdrop.hidden = true;
    setTimeout(() => {
      if (!drawer.classList.contains('open')) drawer.hidden = true;
    }, 250);
  };

  ham?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  // เติมชื่อผู้ใช้ที่ chip
  try {
    const r = await fetch('../api/me.php', { cache: 'no-store', credentials: 'include' });
    const d = r.ok ? await r.json() : null;
const chip = document.getElementById('userChip');
const userArea = document.getElementById('userArea');

getMe().then(user => {
  if (!chip || !userArea) return;
  if (!user) { chip.hidden = true; userArea.href = '/thammue/page/login.html'; return; }
  const name = (user.display_name || user.name || '').trim();
  if (name) { chip.textContent = name; chip.hidden = false; }
    })
  } catch (_) {}
})();
