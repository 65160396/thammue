// /js/ex.kyc.gate.js  — hardened + programmatic submit guard
(function () {
  const PARTIAL_URL = '/page/partials/ex-kyc-modal.html';
  const STATUS_URL  = '/page/backend/ex_kyc_status.php';
  const UPLOAD_URL  = '/page/backend/ex_kyc_upload.php';
  const SUBMIT_URL  = '/page/backend/ex_kyc_submit.php';

  let ALLOW_SUBMIT = false; // จะเป็น true หลังผ่าน/ส่ง KYC

  // ---------- helpers ----------
  async function kycStatus(){
    try{
      const r = await fetch(STATUS_URL, { credentials:'include', cache:'no-store' });
      const j = await r.json();
      return j.status || 'none';
    }catch(_){ return 'none'; }
  }

  async function ensureModal(){
    if (document.getElementById('exKycModal')) return true;

    try{
      const res  = await fetch(PARTIAL_URL, { cache:'no-store' });
      if (!res.ok) throw new Error('partial_not_found');
      const html = await res.text();
      const wrap = document.createElement('div');
      wrap.innerHTML = html;

      // ดึง stylesheet เข้า <head> ด้วย (ถ้ามี)
      const link = wrap.querySelector('link[rel="stylesheet"]');
      if (link) document.head.appendChild(link);

      // ดึงตัว modal (#exKycModal) เข้า <body>
      const modalEl = wrap.querySelector('#exKycModal');
      if (!modalEl) throw new Error('modal_not_in_partial');
      document.body.appendChild(modalEl);
      wireModal();
      return true;
    }catch(_){
      // fallback inline
      const fallback = document.createElement('div');
      fallback.innerHTML = `
        <div class="ex-kyc-modal" id="exKycModal" hidden>
          <div class="ex-kyc-dialog">
            <h3>ยืนยันตัวตนเพื่อใช้งานการแลกเปลี่ยน</h3>
            <p class="muted">กรอกข้อมูลครั้งเดียว ระบบจะส่งให้แอดมินตรวจสอบ</p>
            <form id="exKycForm">
              <label>ชื่อ-นามสกุล <input name="full_name" required></label>
              <label>เลขบัตรประชาชน <input name="national_id" inputmode="numeric" required></label>
              <div class="row">
                <label>วันเกิด <input type="date" name="dob" required></label>
              </div>
              <label>ที่อยู่ตามบัตร <textarea name="address" required></textarea></label>
              <div class="row">
                <label>รูปบัตรด้านหน้า <input type="file" name="id_front" accept="image/*" required></label>
                <label>รูปบัตรด้านหลัง <input type="file" name="id_back"  accept="image/*" required></label>
                <label>เซลฟี่คู่บัตร  <input type="file" name="selfie"   accept="image/*" required></label>
              </div>
              <div class="actions">
                <button type="button" id="exKycCancel">ยกเลิก</button>
                <button type="submit"  id="exKycSubmit">ส่งยืนยันตัวตน</button>
              </div>
              <div class="msg" id="exKycMsg"></div>
            </form>
          </div>
        </div>
        <style>
          .ex-kyc-modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:grid;place-items:center;z-index:9999}
          .ex-kyc-dialog{background:#fff;border-radius:14px;padding:16px;max-width:560px;width:92%}
          .ex-kyc-modal .row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
          .ex-kyc-modal label{display:block;margin:8px 0}
          .ex-kyc-modal input,.ex-kyc-modal textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:10px}
          .ex-kyc-modal .actions{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}
          .ex-kyc-modal[hidden]{display:none}
          .muted{color:#6b7280;font-size:14px}
          .msg{margin-top:8px;color:#b91c1c}
        </style>
      `;
      document.body.appendChild(fallback);
      wireModal();
      return true;
    }
  }

  function wireModal(){
    const modal = document.getElementById('exKycModal');
    const form  = document.getElementById('exKycForm');
    const msg   = document.getElementById('exKycMsg');
    const btnCancel = document.getElementById('exKycCancel');

    btnCancel?.addEventListener('click', ()=> { modal.hidden = true; });

    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      msg.textContent = '';

      async function uploadOne(input){
        const f = input?.files?.[0];
        if (!f) throw new Error('กรุณาเลือกไฟล์ให้ครบ');
        const fd = new FormData(); fd.append('file', f);
        const r  = await fetch(UPLOAD_URL, { method:'POST', body:fd, credentials:'include' });
        const j  = await r.json();
        if (!j.ok || !j.url) throw new Error('อัปโหลดไฟล์ไม่สำเร็จ');
        return j.url;
      }

      try{
        const idFront = await uploadOne(form.querySelector('input[name=id_front]'));
        const idBack  = await uploadOne(form.querySelector('input[name=id_back]'));
        const selfie  = await uploadOne(form.querySelector('input[name=selfie]'));

        const payload = {
          full_name:   form.full_name.value.trim(),
          national_id: form.national_id.value.trim(),
          dob:         form.dob.value,
          address:     form.address.value.trim(),
          id_front_url: idFront,
          id_back_url:  idBack,
          selfie_url:   selfie
        };

        const r2 = await fetch(SUBMIT_URL, {
          method:'POST', credentials:'include',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        const j2 = await r2.json();
        if (!j2.ok){ msg.textContent = j2.error || 'ส่งข้อมูลไม่สำเร็จ'; return; }

        ALLOW_SUBMIT = true;        // ผ่าน KYC แล้ว
        modal.hidden = true;
        const f = findForm();       // ส่งฟอร์มเดิมต่อ
        if (f) nativeSubmit.call(f);
      }catch(err){
        msg.textContent = err?.message || 'เกิดข้อผิดพลาด';
      }
    });
  }

  function findForm(){
    return document.getElementById('exItemCreateForm')
        || document.getElementById('exchangeForm')
        || document.querySelector('form#exItemCreateForm, form#exchangeForm')
        || document.querySelector('form[action*="ex_item_create"]')
        || document.querySelector('main form');
  }
  function findSubmitButtons(form){
    const explicit = document.getElementById('exUploadBtn');
    if (explicit) return [explicit];
    return [...form.querySelectorAll('button[type=submit], input[type=submit]')];
  }

  async function ensureKycBeforeUpload(){
    const st = await kycStatus();
    if (st === 'approved' || st === 'pending') { ALLOW_SUBMIT = true; return true; }
    const ok = await ensureModal();
    if (!ok) return false;
    const modal = document.getElementById('exKycModal');
    if (!modal){ alert('เปิดหน้าต่างยืนยันตัวตนไม่สำเร็จ'); return false; }
    modal.hidden = false;
    return false;
  }

  // ---------- กัน submit แบบโปรแกรมด้วย ----------
  const nativeSubmit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function(){
    try{
      if (this.matches('#exItemCreateForm, #exchangeForm') && !ALLOW_SUBMIT) {
        ensureKycBeforeUpload().then(ok=>{
          if (ok) { ALLOW_SUBMIT = true; nativeSubmit.call(this); }
        });
        return;
      }
    }catch(_){}
    return nativeSubmit.call(this);
  };

  function attach(){
    const form = findForm();
    if (form){
      form.addEventListener('submit', async (e)=>{
        if (ALLOW_SUBMIT) return;
        const ok = await ensureKycBeforeUpload();
        if (!ok){ e.preventDefault(); e.stopImmediatePropagation(); }
      }, { capture:true });

      const btns = findSubmitButtons(form);
      btns.forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          if (ALLOW_SUBMIT) return;
          const ok = await ensureKycBeforeUpload();
          if (!ok){ e.preventDefault(); e.stopImmediatePropagation(); }
        }, true);
      });
    }

    // fallback เผื่อฟอร์มถูกแทนที่ภายหลัง
    document.addEventListener('submit', async (e)=>{
      const t = e.target;
      if (!(t instanceof HTMLFormElement)) return;
      if (!t.matches('#exItemCreateForm, #exchangeForm')) return;
      if (ALLOW_SUBMIT) return;
      const ok = await ensureKycBeforeUpload();
      if (!ok){ e.preventDefault(); e.stopImmediatePropagation(); }
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attach);
  } else {
    attach();
  }

  // debug helper
  window.exKycDebug = async ()=>{
    console.log('[ex.kyc] status =', await kycStatus());
    console.log('[ex.kyc] modal exists =', !!document.getElementById('exKycModal'));
    console.log('[ex.kyc] form =', findForm());
    console.log('[ex.kyc] allowSubmit =', ALLOW_SUBMIT);
  };
})();
