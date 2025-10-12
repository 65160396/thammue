/* =============================================
File: /js/user-menu.js
หน้าที่: แสดงชื่อผู้ใช้และเมนูดรอปดาวน์โปรไฟล์ (ใช้ข้อมูลจาก Me.get())
============================================= */
(function(){
var menu = document.getElementById('userMenu');
var btn = document.getElementById('userArea');
var chip = document.getElementById('userChip');
var dd = document.getElementById('userDropdown');
if (!menu || !btn || !chip || !dd) return;


var guestLinks = [
'<a href="/page/login.html" role="menuitem">เข้าสู่ระบบ</a>',
'<a href="/page/add_member.html" role="menuitem">สมัครสมาชิก</a>'
].join('');


var userLinks = [
'<a href="/page/profile.html" role="menuitem">บัญชีของฉัน</a>',
'<a href="/page/orders/index.php" role="menuitem">การซื้อของฉัน</a>',
'<a href="/page/change_password.html" role="menuitem">เปลี่ยนรหัสผ่าน</a>',
'<hr>',
'<a href="/page/backend/logout.php" role="menuitem" class="logout">ออกจากระบบ</a>'
].join('');


// เริ่มต้นเป็น guest
dd.innerHTML = guestLinks;
chip.hidden = true;


// ใช้ Me.get() เพื่ออัปเดตชื่อและเมนู
if (window.Me && typeof Me.get === 'function') {
Me.get().then(function(d){
if (d && d.ok) {
var name = (d.user.display_name || d.user.name || '').trim();
if (name) { chip.textContent = name; chip.hidden = false; }
dd.innerHTML = userLinks;
} else {
chip.hidden = true;
dd.innerHTML = guestLinks;
}
});
}


// เปิด/ปิดเมนู
btn.addEventListener('click', function(e){
e.preventDefault();
var open = menu.classList.toggle('open');
btn.setAttribute('aria-expanded', String(open));
});


// ปิดเมื่อคลิกข้างนอก
document.addEventListener('click', function(e){
if (!menu.contains(e.target)) {
menu.classList.remove('open');
btn.setAttribute('aria-expanded', 'false');
}
});


// ปิดด้วย Esc
document.addEventListener('keydown', function(e){
if (e.key === 'Escape') {
menu.classList.remove('open');
btn.setAttribute('aria-expanded', 'false');
btn.blur();
}
});
})();