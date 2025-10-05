// /js/open-shop/wizard.js
(() => {
  // ---------- state & nav ----------
  const steps  = Array.from(document.querySelectorAll('.step'));
  const panels = ['step1','step2','step3'].map(id => document.getElementById(id));
  const total  = panels.length;
  let current  = 1;

  function goto(n){
    current = Math.max(1, Math.min(total, n));
    panels.forEach((p,i)=> p.hidden = (i !== current-1));
    steps.forEach((s,i)=>{
      s.classList.toggle('is-active', i === current-1);
      s.classList.toggle('is-done',   i <  current-1);
    });
  }

  document.querySelectorAll('[data-next]').forEach(b=>b.addEventListener('click', ()=>{
    if (validateStep(current)) goto(current+1);
  }));
  document.querySelectorAll('[data-prev]').forEach(b=>b.addEventListener('click', ()=> goto(current-1)));

  // ---------- input mask ----------
  const phoneInput = document.getElementById('phone');
  phoneInput?.addEventListener('input', ()=> phoneInput.value = phoneInput.value.replace(/\D/g,'').slice(0,10));

  const citizenIdInput = document.getElementById('citizen_id');
  citizenIdInput?.addEventListener('input', ()=> citizenIdInput.value = citizenIdInput.value.replace(/\D/g,'').slice(0,13));

  const postcodeInput = document.getElementById('postcode');
  postcodeInput?.addEventListener('input', ()=> postcodeInput.value = postcodeInput.value.replace(/\D/g,'').slice(0,5));

  const pickupPC = document.getElementById('pickup_postcode');
  pickupPC?.addEventListener('input', ()=> pickupPC.value = pickupPC.value.replace(/\D/g,'').slice(0,5));

  // ---------- DOB ----------
  const $dobDay   = document.getElementById('dobDay');
  const $dobMonth = document.getElementById('dobMonth');
  const $dobYearB = document.getElementById('dobYearBE');
  const $dobIso   = document.getElementById('dobIso');
  const $dobHint  = document.getElementById('dobHint');

  function fillDays(){
    if(!$dobDay) return;
    $dobDay.innerHTML='<option value="">วัน</option>';
    for(let d=1; d<=31; d++){
      const o=document.createElement('option');
      o.value=String(d).padStart(2,'0'); o.textContent=d; $dobDay.appendChild(o);
    }
  }
  function fillMonths(){
    if(!$dobMonth) return;
    const m=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $dobMonth.innerHTML='<option value="">เดือน</option>';
    m.forEach((t,i)=>{ const o=document.createElement('option'); o.value=String(i+1).padStart(2,'0'); o.textContent=t; $dobMonth.appendChild(o); });
  }
  function fillYearsBE(){
    if(!$dobYearB) return;
    const now=new Date(), be=now.getFullYear()+543, max=be-18, min=be-100;
    $dobYearB.innerHTML='<option value="">พ.ศ.</option>';
    for(let y=max; y>=min; y--){ const o=document.createElement('option'); o.value=String(y); o.textContent=y; $dobYearB.appendChild(o); }
  }
  function updateDobIsoAndValidate(){
    if(!$dobDay) return true;
    const d=$dobDay.value, m=$dobMonth.value, yB=$dobYearB.value;
    if ($dobHint) $dobHint.hidden=true;
    if(!d||!m||!yB){ if($dobIso)$dobIso.value=''; return false; }
    const yC=parseInt(yB,10)-543, mm=parseInt(m,10), dd=parseInt(d,10);
    const dt=new Date(yC, mm-1, dd);
    const valid=(dt.getFullYear()===yC && dt.getMonth()===(mm-1) && dt.getDate()===dd);
    if(!valid){ if($dobIso)$dobIso.value=''; if($dobHint) $dobHint.hidden=false; return false; }
    const today=new Date();
    let age=today.getFullYear()-yC;
    const beforeBD=(today.getMonth()+1<mm)||(today.getMonth()+1===mm&&today.getDate()<dd);
    if (beforeBD) age--;
    if (age<18){
      if($dobIso)$dobIso.value='';
      if($dobHint){$dobHint.textContent='ผู้สมัครต้องมีอายุอย่างน้อย 18 ปีบริบูรณ์'; $dobHint.hidden=false;}
      return false;
    }
    if($dobIso) $dobIso.value=`${yC}-${String(mm).padStart(2,'0')}-${String(dd).padStart(2,'0')}`;
    return true;
  }
  fillDays(); fillMonths(); fillYearsBE();
  [$dobDay,$dobMonth,$dobYearB].forEach(el=> el && el.addEventListener('change', updateDobIsoAndValidate));

  // ---------- toggle person/company ----------
  const personGroup  = document.getElementById('personFields');
  const companyGroup = document.getElementById('companyFields');
  function toggleGroup(g, enable){
    g.hidden = !enable;
    g.querySelectorAll('input,textarea,select').forEach(el=>{
      el.disabled = !enable;
      el.required = enable && el.hasAttribute('required');
    });
  }
  function onTypeChange(){
    const val = document.querySelector('input[name="seller_type"]:checked')?.value;
    if (val === 'person'){ toggleGroup(personGroup, true);  toggleGroup(companyGroup, false); }
    else if (val === 'company'){ toggleGroup(personGroup, false); toggleGroup(companyGroup, true); }
    updateDobIsoAndValidate();
  }
  document.querySelectorAll('input[name="seller_type"]').forEach(r=>r.addEventListener('change', onTypeChange));
  onTypeChange();

  // ---------- validate ----------
  function validateStep(idx){
    const $ = id => document.getElementById(id);

    if (idx === 1){
      const must = ['shop_name','pickup_province','pickup_district','pickup_subdistrict','pickup_postcode','pickup_addr_line','email','phone'];
      for (const id of must){
        const el = $(id);
        if (el && !el.disabled && !(el.value||'').trim()){
          alert('กรอกให้ครบ: ' + id); el.focus(); return false;
        }
      }
      const phone = $('phone')?.value.trim()||'';
      if (!/^\d{9,10}$/.test(phone)){ alert('เบอร์โทรต้องเป็นตัวเลข 9–10 หลัก'); $('phone')?.focus(); return false; }

      // รวม address → hidden pickup_addr
      const addr = [
        $('pickup_addr_line')?.value,
        $('pickup_subdistrict')?.value,
        $('pickup_district')?.value,
        $('pickup_province')?.value,
        $('pickup_postcode')?.value
      ].filter(Boolean).join(' ');
      const hd = $('pickup_addr'); if (hd) hd.value = addr.trim();
      return true;
    }

    if (idx === 2){
      const type = document.querySelector('input[name="seller_type"]:checked')?.value;
      const typeHint = document.getElementById('typeHint');
      if (!type){ if(typeHint) typeHint.hidden=false; alert('กรุณาเลือกประเภทผู้ขาย'); return false; }

      if (!updateDobIsoAndValidate()){ alert('กรุณาเลือกวันเดือนปีเกิดให้ถูกต้อง (อายุ 18+)'); return false; }

      if (type === 'person'){
        const need = ['citizen_name','citizen_id','cid_province','cid_district','cid_subdistrict','postcode','addr_line'];
        for (const id of need){
          const el = $(id);
          if (!el || el.disabled) continue;
          if (!(el.value||'').trim()){ alert('กรอกให้ครบ: ' + id); el.focus(); return false; }
        }
        if (!/^\d{13}$/.test($('citizen_id').value.trim())){ alert('เลขบัตรประชาชนต้อง 13 หลัก'); $('citizen_id').focus(); return false; }
        const pcOK = /^\d{5}$/.test(($('postcode')?.value||'').trim());
        const pcHint = $('postcodeHint'); if (pcHint) pcHint.hidden = pcOK;
        if (!pcOK){ alert('รหัสไปรษณีย์ต้อง 5 หลัก'); $('postcode').focus(); return false; }

        const f = $('id_front')?.files?.[0];
        if (!f){ alert('กรุณาแนบไฟล์บัตรประชาชนด้านหน้า'); $('id_front')?.focus(); return false; }
        if (f.size > 5*1024*1024){ alert('ไฟล์เกิน 5MB'); return false; }
        if (!['image/jpeg','image/png','application/pdf'].includes(f.type)){ alert('ชนิดไฟล์ต้องเป็น JPG/PNG/PDF'); return false; }
        return true;

      } else {
        if (!($('company_name')?.value||'').trim()){ alert('กรอกชื่อบริษัท'); $('company_name')?.focus(); return false; }
        const tax = ($('tax_id')?.value||'').trim();
        if (!/^\d{10,13}$/.test(tax)){ alert('เลขผู้เสียภาษี 10–13 หลัก'); $('tax_id')?.focus(); return false; }
        const okFile = input => { const f=input?.files?.[0]; return f && f.size<=5*1024*1024 && ['image/jpeg','image/png','application/pdf'].includes(f.type); };
        if (!okFile($('reg_doc')) || !okFile($('id_rep'))){ alert('แนบไฟล์บริษัท (PDF/JPG/PNG ≤ 5MB)'); return false; }
        return true;
      }
    }
    return true;
  }

  // ---------- โหลดตัวเลือกที่อยู่ ----------
  if (typeof initThaiAddress === 'function'){
    initThaiAddress({ province:'#pickup_province', district:'#pickup_district', subdistrict:'#pickup_subdistrict', postcode:'#pickup_postcode' });
    initThaiAddress({ province:'#cid_province',    district:'#cid_district',    subdistrict:'#cid_subdistrict',    postcode:'#postcode' });
  }

  // mark fields with required
  document.querySelectorAll('.field').forEach(f=>{
    if (f.querySelector('input[required], select[required], textarea[required]')) f.classList.add('required');
  });

  goto(1);
})();
