/* ============================================================
4) /js/change-password.js — หน้าจัดสเตป + POST เปลี่ยนรหัสผ่าน
============================================================ */
(function(){
var emailEl = document.getElementById('emailDisplay');
var s1 = document.getElementById('cp-step1');
var s2 = document.getElementById('cp-step2');
var s3 = document.getElementById('cp-step3');
var nextBtn = document.getElementById('cpNext');
var backBtn = document.getElementById('cpBack');
var form = document.getElementById('cpForm');


// ดึงอีเมลจาก Me
if (emailEl && window.Me && typeof Me.get === 'function') {
Me.get().then(function(d){
if (!d || !d.ok) { emailEl.textContent = 'ยังไม่ได้เข้าสู่ระบบ'; return; }
emailEl.textContent = (d.user && d.user.email) ? d.user.email : 'ไม่พบอีเมลในบัญชี';
}).catch(function(){ emailEl.textContent = 'โหลดอีเมลไม่สำเร็จ'; });
}


function show(step){
if (!s1 || !s2) return;
s1.hidden = step !== 1;
s2.hidden = step !== 2;
if (s3) s3.hidden = step !== 3;
var url = new URL(location.href);
url.searchParams.set('step', step);
history.replaceState(null, '', url);
}


var initStep = parseInt(new URLSearchParams(location.search).get('step') || '1', 10);
show([1,2,3].includes(initStep) ? initStep : 1);


if (nextBtn) nextBtn.addEventListener('click', function(){ show(2); });
if (backBtn) backBtn.addEventListener('click', function(){ show(1); });


if (form) form.addEventListener('submit', function(e){
if (s2 && s2.hidden) return; // ส่งเฉพาะตอนอยู่ step 2
e.preventDefault();


var current = document.getElementById('current_password').value.trim();
var npw = document.getElementById('new_password').value.trim();
var cf = document.getElementById('confirm_password').value.trim();


if (!current || !npw || !cf) { alert('กรอกข้อมูลให้ครบ'); return; }
if (npw.length < 8) { alert('รหัสผ่านใหม่อย่างน้อย 8 ตัวอักษร'); return; }
if (npw !== cf) { alert('รหัสผ่านใหม่และยืนยันไม่ตรงกัน'); return; }


fetch('/page/backend/change_password.php', {
method: 'POST',
headers: { 'Content-Type':'application/x-www-form-urlencoded' },
body: new URLSearchParams({ current_password: current, new_password: npw })
})
.then(function(res){ return Promise.all([res.ok, res.json().catch(function(){return {ok:false};})]); })
.then(function(tuple){
var ok = tuple[0];
var data = tuple[1];
if (ok && data.ok) { show(3); }
else { alert((data && data.message) || 'เปลี่ยนรหัสผ่านไม่สำเร็จ'); }
})
.catch(function(){ alert('เปลี่ยนรหัสผ่านไม่สำเร็จ (เครือข่าย)'); });
});
})();