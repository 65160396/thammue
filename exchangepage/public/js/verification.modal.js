/* /exchangepage/public/js/verification.modal.js */
(function () {
  // ====== Config ======
  const PARTIAL_URL = '../partials/verification.modal.html';
  const VAPI = {
    status: '/exchangepage/api/verification/status.php',
    save:   '/exchangepage/api/verification/save.php',
  };
  const DEFAULT_NEXT = '/exchangepage/public/upload.html';

  // ====== Utils ======
  const $ = (s, root = document) => root.querySelector(s);
  const show = (el) => { if (el) { el.hidden = false; el.setAttribute('aria-hidden', 'false'); } };
  const hide = (el) => { if (el) { el.hidden = true;  el.setAttribute('aria-hidden', 'true'); } };

  // โหลดสคริปต์ address.js (มี window.initThaiAddress)
  function loadAddressScript() {
    return new Promise((resolve) => {
      if (document.querySelector('script[data-addressjs]')) return resolve();
      const s = document.createElement('script');
      s.src = '/exchangepage/js/address.js';
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
      const r = await fetch(PARTIAL_URL, { cache: 'no-store' });
      if (!r.ok) return false;
      document.body.insertAdjacentHTML('beforeend', await r.text());
      return true;
    } catch { return false; }
  }

  async function isVerified() {
    try {
      const r = await fetch(VAPI.status, { credentials: 'include', cache: 'no-store' });
      const j = await r.json();
      return !!(j && j.ok && j.verified === true);
    } catch { return false; }
  }

  function initDobFields() {
    const d = $('#dob_d'), m = $('#dob_m'), y = $('#dob_y_be');
    if (d && d.options.length <= 1) for (let i=1;i<=31;i++) d.append(new Option(i, String(i).padStart(2,'0')));
    if (m && m.options.length <= 1) ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
      .forEach((mm,i)=> m.append(new Option(mm, String(i+1).padStart(2,'0'))));
    if (y && y.options.length <= 1) {
      const beNow = new Date().getFullYear() + 543;
      for (let yy = beNow - 18; yy >= beNow - 100; yy--) y.append(new Option(yy, yy));
    }
    $('#citizen_id')?.addEventListener('input', e => e.target.value = e.target.value.replace(/\D/g,'').slice(0,13));
    $('#postcode')?.addEventListener('input',  e => e.target.value = e.target.value.replace(/\D/g,'').slice(0,5));
  }

  async function bindModal(nextUrl) {
    const modal = $('#verifyModal');
    const form  = $('#verifyForm');
    const msg   = $('#vMsg');

    initDobFields();

    await loadAddressScript();
    if (window.initThaiAddress) {
      window.initThaiAddress({
        province:   '#province',
        district:   '#district',
        subdistrict:'#subdistrict',
        postcode:   '#postcode',
      });
    }

    modal?.addEventListener('click', (e) => {
      if (e.target.matches('[data-close], .v-backdrop')) hide(modal);
    });

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.hidden = true;
      if (!form.checkValidity()) { form.reportValidity(); return; }

      const fd = new FormData(form);
      const d = fd.get('dob_d'), m = fd.get('dob_m'), by = fd.get('dob_y_be');
      if (d && m && by) fd.append('dob_iso', `${parseInt(by,10) - 543}-${m}-${d}`);

      try {
        const r = await fetch(VAPI.save, { method: 'POST', body: fd, credentials: 'include' });
        const j = await r.json().catch(() => ({ ok: false }));
        if (j.ok) {
          msg.textContent = 'ส่งยืนยันตัวตนแล้ว กำลังไปหน้าอัปโหลด...';
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

  // ครอบทริกเกอร์ "อัปโหลดสินค้า" ให้ตรวจสถานะก่อน
  function ensureVerifiedBeforeUpload(opts = {}) {
    const triggerSel = opts.trigger || 'a[href$="upload.html"], .js-upload';
    const defaultNext = opts.next || DEFAULT_NEXT;

    document.addEventListener('click', async (e) => {
      const t = e.target.closest(triggerSel);
      if (!t) return;
      e.preventDefault();

      const hrefNext = t.getAttribute('href') || defaultNext;

      if (await isVerified()) {
        location.href = hrefNext;
        return;
      }

      const ok = await ensurePartialLoaded();
      if (!ok) { alert('ไม่สามารถโหลดหน้าต่างยืนยันตัวตนได้'); return; }
      await bindModal(hrefNext);
      show($('#verifyModal'));
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    ensureVerifiedBeforeUpload();
  });
})();
