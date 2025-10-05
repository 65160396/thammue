/* ============================================================
3) /js/account-common.js — ฟังก์ชันร่วมของหน้าบัญชี
- ไฮไลต์เมนูซ้ายตาม path
- เติมดาว * ให้ field ที่ required
- เติมวัน/เดือน/ปี (ถ้ามี #dobDay/#dobMonth/#dobYear)
- จำกัดรูปแบบ phone/postcode
- (ถ้ามี) initThaiAddress แล้วเติมค่าเริ่มจาก Me.get() โดยไม่ทับสิ่งที่ผู้ใช้พิมพ์
============================================================ */
(function(){
// ไฮไลต์เมนูซ้าย
(function(){
var path = location.pathname.split('/').pop();
document.querySelectorAll('.account-nav a').forEach(function(a){
if (a.dataset.path === path) a.classList.add('is-active');
});
})();


// เติม * ให้ field ที่ required
(function(){
document.querySelectorAll('.field').forEach(function(f){
if (f.querySelector('input[required],select[required],textarea[required]')) {
f.classList.add('required');
}
});
})();


// DOB (ถ้ามี)
(function(){
var $d = document.getElementById('dobDay');
var $m = document.getElementById('dobMonth');
var $y = document.getElementById('dobYear');
if(!$d||!$m||!$y) return;


$d.innerHTML = '<option value="">วัน</option>';
for(var i=1;i<=31;i++) $d.insertAdjacentHTML('beforeend', '<option value="'+String(i).padStart(2,'0')+'">'+i+'</option>');


var months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$m.innerHTML = '<option value="">เดือน</option>';
months.forEach(function(t,i){ $m.insertAdjacentHTML('beforeend', '<option value="'+String(i+1).padStart(2,'0')+'">'+t+'</option>'); });


var now = new Date().getFullYear();
$y.innerHTML = '<option value="">ปี (ค.ศ.)</option>';
for(var yy=now-16; yy>=now-100; yy--) $y.insertAdjacentHTML('beforeend', '<option value="'+yy+'">'+yy+'</option>');
})();


// จำกัดรูปแบบ phone/postcode
(function(){
var phone = document.getElementById('phone');
var post = document.getElementById('addr_postcode');
if (phone) phone.addEventListener('input', function(){ phone.value = phone.value.replace(/\D/g,'').slice(0,10); });
if (post) post .addEventListener('input', function(){ post .value = post .value.replace(/\D/g,'').slice(0,5); });
})();


// initThaiAddress ถ้ามี
(function(){
if (typeof initThaiAddress === 'function') {
initThaiAddress({
province: '#addr_province',
district: '#addr_district',
subdistrict: '#addr_subdistrict',
postcode: '#addr_postcode'
});
}
})();
})();