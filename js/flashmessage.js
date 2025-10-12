document.addEventListener('DOMContentLoaded', () => {
  const p = new URLSearchParams(location.search);
  const msg  = p.get('msg');     // เช่น ?msg=เข้าสู่ระบบสำเร็จ!!
  const type = p.get('type');    // ok | err (ถ้ามี)

  if (!msg) return;

  const el = document.getElementById('flash');
  el.textContent = decodeURIComponent(msg || '');
  el.classList.add('show');
  if (type === 'ok')  el.classList.add('ok');
  if (type === 'err') el.classList.add('err');

  // ซ่อนอัตโนมัติ
  setTimeout(() => el.classList.remove('show'), 2200);
});
