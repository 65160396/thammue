// /js/nav/hamburger.js
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('iconsDrawer');
  const closeBtn = document.getElementById('iconsClose');
  const backdrop = document.getElementById('iconsBackdrop');

  if (!btn || !drawer) return; // ไม่มี element ไม่ต้องทำงาน

  function openDrawer() {
    drawer.hidden = false;
    drawer.classList.add('open');
    backdrop.hidden = false;
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    setTimeout(() => {
      drawer.hidden = true;
    }, 250);
    backdrop.hidden = true;
  }

  btn.addEventListener('click', openDrawer);
  closeBtn?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);
});
