
/* ex.verify.modal.js */
(function(){
  // Public API
  async function requireEmailVerificationBeforeUpload(){
    const st = await fetch('/page/backend/ex_verify_status.php', {credentials:'include'}).then(r=>r.json());
    if(!st.ok){ throw new Error(st.error||'ตรวจสอบสถานะยืนยันตัวตนไม่ได้'); }
    if (st.verified) return true; // already verified
    openModal(st.email || '');
    return false;
  }

  // Modal elements
  let backdrop, modal, emailEl, codeEl, sendBtn, verifyBtn, cancelBtn, statusEl, resendBtn, stepSend, stepVerify;

  function ensureDom(){
    if (modal) return;
    backdrop = document.createElement('div');
    backdrop.className = 'ex-modal-backdrop';
    modal = document.createElement('div');
    modal.className = 'ex-modal';
    modal.innerHTML = `
      <div class="ex-sheet">
        <h3>ยืนยันตัวตนด้วยอีเมล</h3>
        <p>เพื่อความปลอดภัย กรุณายืนยันอีเมลของคุณก่อนอัปโหลดสินค้า</p>
        <div id="exStepSend">
          <div class="row">
            <input id="exEmail" type="text" placeholder="อีเมลของคุณ">
            <button class="ex-btn" id="exSendCode">ส่งรหัส</button>
          </div>
          <p class="ex-muted" style="font-size:12px">* เราจะส่งรหัส 6 หลักไปยังอีเมลของคุณ</p>
        </div>
        <div id="exStepVerify" class="ex-hide">
          <div class="row">
            <input id="exCode" type="text" maxlength="6" placeholder="กรอกรหัส 6 หลัก">
            <button class="ex-btn" id="exVerifyCode">ยืนยัน</button>
          </div>
          <div class="ex-actions">
            <button class="ex-btn secondary" id="exResend">ส่งรหัสอีกครั้ง</button>
          </div>
        </div>
        <div id="exStatus" class="ex-muted" style="margin-top:8px"></div>
        <div class="ex-actions">
          <button class="ex-btn secondary" id="exCancel">ปิด</button>
        </div>
      </div>
    `;
    document.body.appendChild(backdrop);
    document.body.appendChild(modal);
    emailEl = modal.querySelector('#exEmail');
    codeEl = modal.querySelector('#exCode');
    sendBtn = modal.querySelector('#exSendCode');
    verifyBtn = modal.querySelector('#exVerifyCode');
    cancelBtn = modal.querySelector('#exCancel');
    statusEl = modal.querySelector('#exStatus');
    stepSend = modal.querySelector('#exStepSend');
    stepVerify = modal.querySelector('#exStepVerify');
    resendBtn = modal.querySelector('#exResend');

    backdrop.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    sendBtn.addEventListener('click', initSend);
    verifyBtn.addEventListener('click', doVerify);
    resendBtn.addEventListener('click', doResend);
  }

  function openModal(prefillEmail){
    ensureDom();
    emailEl.value = prefillEmail || '';
    statusEl.textContent = '';
    stepSend.classList.remove('ex-hide');
    stepVerify.classList.add('ex-hide');
    backdrop.style.display = 'block';
    modal.style.display = 'flex';
  }
  function closeModal(){
    backdrop.style.display = 'none';
    modal.style.display = 'none';
  }

  async function initSend(){
    try{
      const email = emailEl.value.trim();
      if (!email) { statusEl.textContent = 'กรุณากรอกอีเมล'; return; }
      const res = await fetch('/page/backend/ex_verify_init.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include',
        body: JSON.stringify({email})
      }).then(r=>r.json());
      if(!res.ok){ statusEl.textContent = res.error || 'ส่งรหัสไม่สำเร็จ'; return; }
      statusEl.textContent = 'ส่งรหัสแล้ว กรุณาตรวจสอบอีเมลของคุณ';
      stepSend.classList.add('ex-hide');
      stepVerify.classList.remove('ex-hide');
      codeEl.focus();
    }catch(e){ statusEl.textContent = e.message; }
  }

  async function doVerify(){
    try{
      const code = codeEl.value.trim();
      if(!code || code.length!==6){ statusEl.textContent = 'กรุณากรอกรหัส 6 หลัก'; return; }
      const res = await fetch('/page/backend/ex_verify_check.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include',
        body: JSON.stringify({code})
      }).then(r=>r.json());
      if(!res.ok){ statusEl.textContent = res.error || 'ยืนยันไม่สำเร็จ'; return; }
      statusEl.textContent = 'ยืนยันสำเร็จ — ปิดหน้าต่างนี้ได้เลย';
      setTimeout(()=>{ location.reload(); }, 800);
    }catch(e){ statusEl.textContent = e.message; }
  }

  async function doResend(){
    try{
      const res = await fetch('/page/backend/ex_verify_resend.php', {method:'POST', credentials:'include'}).then(r=>r.json());
      if(!res.ok){ statusEl.textContent = res.error || 'ส่งซ้ำไม่สำเร็จ'; return; }
      statusEl.textContent = 'ส่งรหัสอีกครั้งแล้ว';
    }catch(e){ statusEl.textContent = e.message; }
  }

  // Expose globally
  window.ExVerify = { requireEmailVerificationBeforeUpload, openModal };
})();
