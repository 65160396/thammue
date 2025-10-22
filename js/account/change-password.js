// /js/account/change-password.js
(function () {
  const step1 = document.getElementById('cp-step1');
  const step2 = document.getElementById('cp-step2');
  const step3 = document.getElementById('cp-step3');
  const btnNext = document.getElementById('cpNext');
  const btnBack = document.getElementById('cpBack');
  const form = document.getElementById('cpForm');
  const emailDisplay = document.getElementById('emailDisplay');

  // ------- helpers -------
  async function safeJson(res) {
    try { return await res.json(); }
    catch {
      const t = await res.text();
      console.error('RAW response:', t);
      throw new Error('bad_json');
    }
  }
  function alertThai(code) {
    const map = {
      missing_fields: 'กรุณากรอกข้อมูลให้ครบ',
      too_short: 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร',
      confirm_mismatch: 'รหัสผ่านใหม่ไม่ตรงกัน',
      current_incorrect: 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
      same_as_old: 'รหัสผ่านใหม่ต้องไม่ซ้ำรหัสเดิม',
      not_logged_in: 'กรุณาเข้าสู่ระบบก่อน',
      server_error: 'เซิร์ฟเวอร์ขัดข้อง กรุณาลองใหม่',
      network: 'เครือข่ายขัดข้อง ลองใหม่อีกครั้ง'
    };
    alert(map[code] || map.network);
  }

  // ------- โหลดอีเมลผู้ใช้ (พยายาม 2 endpoint เผื่อโปรเจกต์ใช้ตัวใดตัวหนึ่ง) -------
  (async () => {
    try {
      let res = await fetch('/page/backend/check_email.php', {credentials:'include'});
      if (!res.ok) throw new Error();
      let j = await safeJson(res);
      const email = j?.email || j?.data?.email || j?.user?.email;
      if (email) { emailDisplay.textContent = email; return; }
      throw new Error();
    } catch {
      try {
        let res = await fetch('/page/backend/me.php', {credentials:'include'});
        if (!res.ok) throw new Error();
        let j = await safeJson(res);
        const email = j?.email || j?.data?.user?.email || j?.user?.email;
        emailDisplay.textContent = email || '—';
      } catch {
        emailDisplay.textContent = '—';
      }
    }
  })();

  // ------- สลับขั้น -------
  btnNext?.addEventListener('click', () => {
    step1.hidden = true; step2.hidden = false; step3.hidden = true;
    document.getElementById('current_password')?.focus();
  });
  btnBack?.addEventListener('click', () => {
    step1.hidden = false; step2.hidden = true; step3.hidden = true;
  });

  // ------- ส่งเปลี่ยนรหัส -------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const curr = document.getElementById('current_password').value.trim();
    const neo  = document.getElementById('new_password').value.trim();
    const conf = document.getElementById('confirm_password').value.trim();

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      const res = await fetch('/page/backend/change_password.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  credentials: 'include',
  body: JSON.stringify({
    current_password: curr,
    new_password: neo,
    confirm_password: conf
  })
});

      const j = await safeJson(res);
      if (!j.ok) { submitBtn.disabled = false; return alertThai(j.error); }

      // สำเร็จ
      step1.hidden = true; step2.hidden = true; step3.hidden = false;
      form.reset();
    } catch (e2) {
      submitBtn.disabled = false;
      alertThai('network');
    }
  });
})();
