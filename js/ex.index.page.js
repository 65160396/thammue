/* โหลด badge แจ้งเตือน + สถานะ KYC ให้ pill บนหัวเว็บ */
(async function(){
  try{
    // แจ้งเตือน (ถ้ามี API นี้อยู่ในแพ็กคุณ)
    const noti = await fetch('/page/backend/ex_list_notifications.php', {credentials:'include'}).then(r=>r.json());
    const badge = document.getElementById('exNotiBadge');
    if (noti && noti.ok && Array.isArray(noti.items)) {
      const unread = noti.items.filter(x => !x.is_read).length;
      if (badge) badge.textContent = unread>0 ? unread : '';
    }

    // KYC pill
    const kycRes = await fetch('/page/backend/ex_kyc_status.php', {credentials:'include'}).then(r=>r.json());
    const pill = document.getElementById('kycPill');
    if (pill && kycRes && kycRes.ok) {
      if (kycRes.status === 'approved') {
        pill.textContent = 'KYC ผ่านแล้ว';
        pill.classList.remove('ex-pill-muted');
      } else if (kycRes.status === 'pending') {
        pill.textContent = 'KYC รอตรวจสอบ';
      } else if (kycRes.status === 'rejected') {
        pill.textContent = 'KYC ถูกปฏิเสธ';
      } else {
        pill.textContent = 'KYC';
      }
    }
  }catch(e){ /* เงียบไว้บนหน้าแรก */ }
})();
