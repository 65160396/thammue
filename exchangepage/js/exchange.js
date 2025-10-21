// /js/exchange.js
/* =========================================================
 * Thammue - Exchange Form (Wizard + Single-pick Accumulate Uploader)
 * - เลือกไฟล์ครั้งละ 1 รูป (input ไม่มี multiple)
 * - กด/ลากวางซ้ำเพื่อสะสมรูปได้
 * - ลบรายรูปได้
 * - ไม่ตรวจรูปซ้ำ (ตามที่ต้องการ)
 * - ปล่อยให้ form submit ไป PHP ตาม action ได้จริง (no preventDefault เว้นแต่ invalid)
 * ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
  // ---------- Grab Elements ----------
  const form   = document.getElementById('exchangeForm');
  const panels = Array.from(document.querySelectorAll('.panel'));
  const fill   = document.querySelector('.stepper-fill');
  const dots   = Array.from(document.querySelectorAll('.step .dot'));

  // ---------- Wizard ----------
  let step = 0;
  const showStep = (i) => {
    step = Math.max(0, Math.min(panels.length - 1, i));
    panels.forEach((p, idx) => (p.hidden = idx !== step));
    dots.forEach((d, iDot) => d.classList.toggle('active', iDot <= step));
    if (fill) fill.style.width = (step / (panels.length - 1)) * 100 + '%';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // แยก phase = 'nav' (ตอน next/back) กับ 'submit' (ตอนส่งฟอร์ม)
  const firstInvalidIn = (el, opts = {}) => {
    const phase = opts.phase || 'nav';
    const controls = el.querySelectorAll('input, select, textarea');
    for (const c of controls) {
      if (phase === 'nav' && c.type === 'file') continue; // ข้ามไฟล์ตอน next/back
      if (!c.checkValidity()) return c;
    }
    return null;
  };

  document.querySelectorAll('.next').forEach(b => b.addEventListener('click', () => {
    const current = panels[step];
    const invalid = firstInvalidIn(current, { phase: 'nav' });
    if (invalid) { invalid.reportValidity(); return; }
    showStep(step + 1);
  }));

  document.querySelectorAll('.back').forEach(b => b.addEventListener('click', () => {
    showStep(step - 1);
  }));

  showStep(0);

  // ---------- Uploader (single-pick per add, accumulate many) ----------
  function bindUploader(inputId, thumbsId, opts = {}) {
    const input   = document.getElementById(inputId);
    const thumbs  = document.getElementById(thumbsId);
    const trigger = document.querySelector(`.upload-box[data-for="${inputId}"]`);
    if (!input || !thumbs || !trigger) return;

    // กัน bind ซ้ำ
    if (input.dataset.bound === '1') return;
    input.dataset.bound = '1';

    // ตั้งค่า
    input.removeAttribute('multiple'); // เลือกครั้งละ 1 รูป
    const MAX_FILES   = opts.maxFiles ?? 10;
    const MAX_MB      = opts.maxMB ?? 8;
    const ACCEPT_LIST = (input.accept || 'image/*').split(',').map(s => s.trim().toLowerCase());

    /** @type {File[]} */
    const store = []; // แหล่งความจริง

    const isAccept = (file) =>
      ACCEPT_LIST.some(acc => acc === 'image/*' ? file.type.startsWith('image/') : file.type.toLowerCase() === acc);

    // เปิดไฟล์ด้วยคลิก/คีย์บอร์ด
    const openPicker = () => { input.value = ''; input.click(); };
    trigger.addEventListener('click', (e) => { e.preventDefault(); openPicker(); });
    trigger.tabIndex = 0;
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
    });

    // drag & drop
    ['dragenter','dragover'].forEach(evt =>
      trigger.addEventListener(evt, e => { e.preventDefault(); trigger.classList.add('drag'); })
    );
    ['dragleave','drop'].forEach(evt =>
      trigger.addEventListener(evt, e => { e.preventDefault(); trigger.classList.remove('drag'); })
    );
    trigger.addEventListener('drop', (e) => {
      const list = e.dataTransfer?.files;
      if (!list || !list.length) return;
      addOne(list[0]); // รับไฟล์แรกต่อครั้ง
    });

    // file picker (ครั้งละ 1)
    input.addEventListener('change', (e) => {
      const f = e.target.files?.[0];
      if (f) addOne(f);
      input.value = ''; // ให้เลือกไฟล์เดิมซ้ำได้
    });

    function addOne(file) {
      if (!isAccept(file)) return alert('ไฟล์ไม่ใช่รูปภาพที่ระบบรองรับ');
      if (file.size > MAX_MB * 1024 * 1024) return alert(`ไฟล์ใหญ่เกิน ${MAX_MB} MB`);
      if (store.length >= MAX_FILES) return alert(`อัปโหลดได้สูงสุด ${MAX_FILES} รูป`);
      store.push(file);
      syncInputFromStore();
      renderThumbs();
    }

    function removeAt(index) {
      store.splice(index, 1);
      syncInputFromStore();
      renderThumbs();
    }

    function syncInputFromStore() {
      const dt = new DataTransfer();
      store.forEach(f => dt.items.add(f));
      input.files = dt.files;
    }

    function renderThumbs() {
      thumbs.innerHTML = '';
      store.forEach((file, idx) => {
        const item = document.createElement('div');
        item.className = 'thumb-item';
        const reader = new FileReader();
        reader.onload = () => {
          item.innerHTML = `
            <img src="${reader.result}" alt="preview ${idx + 1}">
            <button type="button" class="thumb-remove" aria-label="ลบรูป">&times;</button>
            <div class="thumb-caption" title="${file.name}">${file.name}</div>
          `;
          item.querySelector('.thumb-remove').addEventListener('click', () => removeAt(idx));
        };
        reader.readAsDataURL(file);
        thumbs.appendChild(item);
      });
    }
  }

  // ให้ตรงกับ HTML ปัจจุบัน
  bindUploader('images',      'thumbs1', { maxFiles: 10, maxMB: 8 });
  bindUploader('want_images', 'thumbs2', { maxFiles: 10, maxMB: 8 });

  // ---------- Form Submit ----------
  // ---------- Form Submit ----------
form?.addEventListener('submit', (e) => {
  // ตรวจทุกสเต็ป…
  for (let s = 0; s < panels.length; s++) {
    const invalid = firstInvalidIn(panels[s], { phase: 'submit' });
    if (invalid) { 
      e.preventDefault();
      showStep(s);
      invalid.reportValidity();
      return;
    }
  }

  // ต้องมีรูปอย่างน้อย 1 รูป (เพราะเราเอา required ออกจาก input ที่ซ่อน)
  const imgInput = document.getElementById('images');
  if (!imgInput || imgInput.files.length === 0) {
    e.preventDefault();
    showStep(0);
    alert('กรุณาอัปโหลดรูปสินค้าอย่างน้อย 1 รูป');
    return;
  }

  // ปล่อยให้เบราว์เซอร์ submit ต่อไปตามปกติ (อย่าเรียก preventDefault อีก)
});


});

/* แนะนำ CSS เพิ่ม (ถ้ายังไม่มี)
.thumb-item{position:relative;display:inline-block;margin:6px}
.thumb-item img{width:110px;height:110px;object-fit:cover;border-radius:10px;display:block}
.thumb-item .thumb-remove{position:absolute;right:6px;top:6px;border:none;background:#000a;color:#fff;
  width:24px;height:24px;border-radius:50%;cursor:pointer}
.thumb-caption{position:absolute;left:6px;bottom:6px;background:#0008;color:#fff;padding:2px 6px;
  border-radius:6px;font-size:12px;max-width:96px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.upload-box.drag{outline:2px dashed #888; outline-offset:4px}
*/