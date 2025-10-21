/* /thammue/public/js/verification.modal.js */
(function () {
  // ====== Config ======
   const PARTIAL_URL = 'verification.modal.html';
  const VAPI = {
    status: '/thammue/api/verification/status.php',
    save:   '/thammue/api/verification/save.php',
  };
  const DEFAULT_NEXT = '/thammue/public/upload.html';

  // ====== Utils ======
  function $(s, root = document) { return root.querySelector(s); }
  function show(el) { if (!el) return; el.hidden = false; el.setAttribute('aria-hidden', 'false'); }
  function hide(el) { if (!el) return; el.hidden = true; el.setAttribute('aria-hidden', 'true'); }

  // โหลดสคริปต์ address.js แบบ non-module (กันพลาด)
  function loadAddressScript() {
  return new Promise((resolve) => {
    if (document.querySelector('script[data-addressjs]')) return resolve();
    const s = document.createElement('script');
    s.src = '/thammue/js/address.js';   // ✅ พาธถูกแล้ว
    s.defer = true;
    s.async = true;
    s.setAttribute('data-addressjs', '');
    s.onload = resolve;
    s.onerror = resolve;
    document.head.appendChild(s);
  });
}

  async function ensurePartialLoaded() {
    if ($('#verifyModal')) return true;
    try {
      const r = await fetch(PARTIAL_URL, { credentials: 'omit', cache: 'no-store' });
      if (!r.ok) return false;
      const html = await r.text();
      document.body.insertAdjacentHTML('beforeend', html);
      return true;
    } catch {
      return false;
    }
  }

  async function isVerified() {
    try {
      const r = await fetch(VAPI.status, { credentials: 'include', cache: 'no-store' });
      const j = await r.json();
      return !!(j && j.ok && j.verified === true);
    } catch {
      // ถ้าเรียก API พลาด ให้ถือว่ายังไม่ผ่าน เพื่อบังคับเด้งโมดัล
      return false;
    }
  }

  function initDobMasks() {
    const d = $('#dob_d'), m = $('#dob_m'), y = $('#dob_y_be');
    if (!d || d.options.length > 1) return;
    for (let i = 1; i <= 31; i++) d.append(new Option(i, i.toString().padStart(2, '0')));
    ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']
      .forEach((mm, i) => m.append(new Option(mm, (i + 1).toString().padStart(2, '0'))));
    const now = new Date(); const be = now.getFullYear() + 543;
    for (let yy = be - 18; yy >= be - 100; yy--) y.append(new Option(yy, yy));
    $('#citizen_id')?.addEventListener('input', e => e.target.value = e.target.value.replace(/\D/g, '').slice(0, 13));
    $('#postcode')?.addEventListener('input', e => e.target.value = e.target.value.replace(/\D/g, '').slice(0, 5));
  }

  async function bindModal(nextUrl){
  const modal = $('#verifyModal');
  const form  = $('#verifyForm');
  const msg   = $('#vMsg');

  initDobMasks();

  // โหลดสคริปต์ที่ประกาศ window.initThaiAddress แล้วสั่ง init ให้โมดัล
  await loadAddressScript();
  if (window.initThaiAddress) {
    window.initThaiAddress({
      province:    '#province',
      district:    '#district',
      subdistrict: '#subdistrict',
      postcode:    '#postcode',
    });
  }

  modal?.addEventListener('click',(e)=>{ if (e.target.matches('[data-close], .v-backdrop')) hide(modal); });

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.hidden = true;
      if (!form.checkValidity()) { form.reportValidity(); return; }

      const fd = new FormData(form);
      const d = fd.get('dob_d'), m = fd.get('dob_m'), by = fd.get('dob_y_be');
      if (d && m && by) fd.append('dob_iso', `${parseInt(by, 10) - 543}-${m}-${d}`);

      try {
        const r = await fetch(VAPI.save, { method: 'POST', body: fd, credentials: 'include' });
        const j = await r.json().catch(() => ({ ok: false }));
        if (j.ok) {
          msg.textContent = 'ส่งยืนยันตัวตนแล้ว กำลังไปหน้าอัพโหลด...';
          msg.hidden = false;
          setTimeout(() => location.href = nextUrl || DEFAULT_NEXT, 400);
        } else {
          msg.textContent = j.error || (j.extra && j.extra.msg) || 'เกิดข้อผิดพลาด โปรดลองอีกครั้ง';
          msg.hidden = false;
        }
      } catch {
        msg.textContent = 'ไม่สามารถติดต่อเซิร์ฟเวอร์';
        msg.hidden = false;
      }
    });
  }

  // ดักคลิกปุ่มอัพโหลด แล้วเช็คสถานะก่อน
  function ensureVerifiedBeforeUpload(opts = {}) {
    const triggerSel = opts.trigger || 'a[href$="upload.html"], .js-upload';
    const defaultNext = opts.next || DEFAULT_NEXT;

    document.addEventListener('click', async (e) => {
      const t = e.target.closest(triggerSel);
      if (!t) return;
      e.preventDefault();

      const hrefNext = t.getAttribute('href') || defaultNext;

      if (await isVerified()) {
        // ผ่านแล้ว → ไปต่อเลย
        location.href = hrefNext;
        return;
      }

      // ยังไม่ผ่าน → โหลด partial + bind + แสดง
      const ok = await ensurePartialLoaded();
      if (!ok) { alert('ไม่สามารถโหลดหน้าต่างยืนยันตัวตนได้'); return; }
      await bindModal(hrefNext);
      show($('#verifyModal'));
    });
  }

  // auto-enable เมื่อ DOM พร้อม
  document.addEventListener('DOMContentLoaded', () => {
    ensureVerifiedBeforeUpload();
  });
})();
