
/* /js/ex.verify.kyc.page.js */
(function(){
  const Q = sel => document.querySelector(sel);
  const statusEl = Q('#kycStatus');

  function setStatus(txt, ok){
    statusEl.textContent = txt;
    statusEl.classList.remove('ex-kyc-ok','ex-kyc-danger');
    if (ok === true) statusEl.classList.add('ex-kyc-ok');
    if (ok === false) statusEl.classList.add('ex-kyc-danger');
  }

  async function loadStatus(){
    const res = await fetch('/page/backend/ex_kyc_status.php', {credentials:'include'}).then(r=>r.json());
    if(!res.ok){ setStatus(res.error||'โหลดสถานะไม่ได้', false); return; }
    if(res.status==='approved'){ setStatus('สถานะ: ยืนยันแล้ว (approved)', true); }
    else if(res.status==='pending'){ setStatus('สถานะ: รอตรวจสอบ (pending)', false); }
    else if(res.status==='rejected'){ setStatus('สถานะ: ถูกปฏิเสธ (rejected) — กรุณายื่นใหม่', false); }
    else { setStatus('ยังไม่เคยยืนยัน', false); }
  }

  async function upload(which){
    const input = Q('#img_'+which);
    const f = input && input.files && input.files[0];
    if(!f){ alert('ยังไม่ได้เลือกไฟล์'); return null; }
    const fd = new FormData();
    fd.append('image', f);
    fd.append('kind', which);
    const res = await fetch('/page/backend/ex_kyc_upload.php', {method:'POST', credentials:'include', body: fd}).then(r=>r.json());
    if(!res.ok){ alert(res.error||'อัปโหลดไม่สำเร็จ'); return null; }
    Q('#url_'+which).textContent = res.url || '';
    Q('#img_'+which+'_preview').src = res.url || '';
    return res.url || '';
  }

  async function submit(){
    const payload = {
      full_name: Q('#full_name').value.trim(),
      national_id: Q('#national_id').value.trim(),
      dob: Q('#dob').value,
      address: Q('#address').value.trim(),
      id_front_url: Q('#url_front').textContent.trim() || '',
      id_back_url: Q('#url_back').textContent.trim() || '',
      selfie_url: Q('#url_selfie').textContent.trim() || ''
    };
    if(!payload.full_name || !payload.national_id || !payload.dob || !payload.address){
      alert('กรอกข้อมูลให้ครบ'); return;
    }
    if(!payload.id_front_url || !payload.selfie_url){
      alert('ต้องมีรูปบัตรด้านหน้า และเซลฟีถือบัตรอย่างน้อย'); return;
    }
    const res = await fetch('/page/backend/ex_kyc_submit.php', {
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(payload)
    }).then(r=>r.json());
    if(!res.ok){ alert(res.error||'ส่งข้อมูลไม่สำเร็จ'); return; }
    alert('ส่งข้อมูลแล้ว');
    location.reload();
  }

  window.addEventListener('DOMContentLoaded', ()=>{
    Q('#btnUploadFront').addEventListener('click', ()=>upload('front'));
    Q('#btnUploadBack').addEventListener('click', ()=>upload('back'));
    Q('#btnUploadSelfie').addEventListener('click', ()=>upload('selfie'));
    Q('#btnSubmit').addEventListener('click', submit);
    loadStatus();
  });
})();
