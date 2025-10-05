/* =====================================================
ไฟล์: /js/login.js
หน้าที่:
1. สลับการแสดง / ซ่อนรหัสผ่าน (togglePassword)
2. แสดง flash message (เช่น ข้อความ error/success หลัง login หรือสมัครสมาชิก)
===================================================== */

// -------------------------
// 1. ฟังก์ชันโชว์/ซ่อนรหัสผ่าน
// -------------------------
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const icon  = document.getElementById('togglePasswordIcon');

  const showIcon = '/img/Icon/show.png';
  const hideIcon = '/img/Icon/hidden (1).png';

  if (!input || !icon) return;

  if (input.type === 'password') {
    input.type = 'text';
    icon.src = hideIcon;
    icon.alt = 'ซ่อนรหัสผ่าน';
  } else {
    input.type = 'password';
    icon.src = showIcon;
    icon.alt = 'แสดงรหัสผ่าน';
  }
}

// -------------------------
// 2. flash message จาก URL
// -------------------------
(function () {
  const sp   = new URLSearchParams(location.search);
  const type = sp.get('type');
  const msg  = sp.get('msg');

  if (!msg) return;

  const box = document.getElementById('flash');
  if (!box) return;

  box.textContent = decodeURIComponent(msg);
  box.classList.add('show');

  if (type === 'success') box.classList.add('is-success');
  else box.classList.add('is-error');

  // ล้าง query เพื่อไม่ให้ขึ้นซ้ำตอนรีเฟรช
  history.replaceState({}, '', location.pathname);
})();
