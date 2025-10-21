// /thammue/public/js/cards.kebab.js
// โมดูลจัดการปุ่ม 3 จุดบนการ์ด (toggle/close) ด้วย event delegation

function closeAll(scope = document) {
  scope.querySelectorAll('.card-kebab__menu').forEach(m => (m.hidden = true));
  scope
    .querySelectorAll('.card-kebab__btn[aria-expanded="true"]')
    .forEach(b => b.setAttribute('aria-expanded', 'false'));
}

function toggle(btn) {
  const menuId = btn.getAttribute('aria-controls');
  const menu = document.getElementById(menuId);
  const expanded = btn.getAttribute('aria-expanded') === 'true';
  closeAll();
  if (!expanded) {
    btn.setAttribute('aria-expanded', 'true');
    if (menu) {
      menu.hidden = false;
      menu.querySelector('.card-kebab__item')?.focus();
    }
  }
}

// ป้องกัน bind ซ้ำ: เก็บ reference ไว้สำหรับถอน event ได้ถ้าต้องการ
const _bound = new WeakSet();

function bind(rootSelectorOrEl = document) {
  const root = typeof rootSelectorOrEl === 'string'
    ? document.querySelector(rootSelectorOrEl)
    : rootSelectorOrEl;
  if (!root || _bound.has(root)) return;
  _bound.add(root);

  // คลิกที่ปุ่ม 3 จุด → toggle เมนู
  root.addEventListener('click', (e) => {
    const kebabBtn = e.target.closest('.card-kebab__btn');
    if (!kebabBtn) return;
    // ต้องให้ปุ่มอยู่ภายใต้ root เท่านั้น
    if (!root.contains(kebabBtn)) return;
    e.preventDefault();
    e.stopPropagation(); // กันคลิกหลุดไปปิดเองจาก listener ด้านนอก
    toggle(kebabBtn);
  });

  // คลิกนอกกรอบเมนู → ปิด
  document.addEventListener('click', (e) => {
    // ถ้าคลิกนอก .card-kebab ทั้งหมด ให้ปิดทุกเมนู
    if (!e.target.closest('.card-kebab')) closeAll();
  });

  // กด Esc → ปิด
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll();
  });
}

export const Kebab = { bind, closeAll };
export default Kebab;
