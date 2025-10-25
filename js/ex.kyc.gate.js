// /js/ex.kyc.gate.js
(function(){
  async function kycStatus(){
    try{
      const r = await fetch('/page/backend/ex_kyc_status.php', {credentials:'include'});
      const j = await r.json(); return j.status || 'none';
    }catch(e){ return 'none'; }
  }

  async function ensureModal(){
    if (document.getElementById('exKycModal')) return;
    const res = await fetch('/page/partials/ex-kyc-modal.html', {cache:'no-store'});
    const html = await res.text();
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
    wireModal();
  }

  function wireModal(){
    const modal = document.getElementById('exKycModal');
    const form  = document.getElementById('exKycForm');
    const btnCancel = document.getElementById('exKycCancel');
    const msg   = document.getElementById('exKycMsg');

    btnCancel?.addEventListener('click', ()=> modal.hidden = true);

    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      msg.textContent = '';

      async function uploadOne(input){
        const f = input.files && input.files[0]; if (!f) throw new Error('กรุณาเลือกไฟล์ให้ครบ');
        const fd = new FormData(); fd.append('file', f);
        const r = await fetch('/page/backend/ex_kyc_upload.php', {method:'POST', body:fd, credentials:'include'});
        const j = await r.json(); if (!j.ok) throw new Error('อัปโหลดไฟล์ไม่สำเร็จ');
        return j.url;
      }

      try {
        const idFront = await uploadOne(form.querySelector('input[name=id_front]'));
        const idBack  = await uploadOne(form.querySelector('input[name=id_back]'));
        const selfie  = await uploadOne(form.querySelector('input[name=selfie]'));

        const payload = {
          full_name: form.full_name.value.trim(),
          national_id: form.national_id.value.trim(),
          dob: form.dob.value,
          address: form.address.value.trim(),
          id_front_url: idFront,
          id_back_url: idBack,
          selfie_url: selfie
        };
        const r2 = await fetch('/page/backend/ex_kyc_submit.php', {
          method:'POST', credentials:'include',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        const j2 = await r2.json();
        if (!j2.ok){ msg.textContent = j2.error || 'ส่งข้อมูลไม่สำเร็จ'; return; }

        modal.hidden = true;
        document.dispatchEvent(new CustomEvent('ex:kyc:submitted', {detail:{status:j2.status}}));
        const formItem = document.getElementById('exItemCreateForm');
        if (formItem && formItem.tagName === 'FORM'){ formItem.submit(); }
      } catch(err){
        msg.textContent = err.message || 'เกิดข้อผิดพลาด';
      }
    });
  }

  async function ensureKycBeforeUpload(){
    const st = await kycStatus();
    if (st === 'approved' || st === 'pending'){ return true; }
    await ensureModal();
    document.getElementById('exKycModal').hidden = false;
    return false;
  }

  const formItem = document.getElementById('exItemCreateForm');
  if (formItem && formItem.tagName === 'FORM'){
    formItem.addEventListener('submit', async (e)=>{
      const ok = await ensureKycBeforeUpload();
      if (!ok){ e.preventDefault(); }
    }, {capture:true});
  }
  const btn = document.getElementById('exUploadBtn');
  if (btn){
    btn.addEventListener('click', async (e)=>{
      const ok = await ensureKycBeforeUpload();
      if (!ok){ e.preventDefault(); e.stopPropagation(); }
    }, true);
  }
})();
