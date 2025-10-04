/* =========================================================
 * Thammue - Exchange Form (Wizard + Single-pick Accumulate Uploader + Submit)
 * - เลือกไฟล์ครั้งละ 1 รูป (input ไม่มี multiple)
 * - คลิก/ลากวางหลายครั้งเพื่อสะสมรูปได้
 * - ลบรายรูปได้
 * - POST multipart/form-data ไปหลังบ้าน แล้ว redirect ไปหน้า success
 * ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
  // ---------- Config ----------
  const API_ENDPOINT = '/api/exchanges';           // <-- แก้ให้ตรงแบ็กเอนด์ของคุณ
  const SUCCESS_PAGE = '/exchangepage/success.html';

  // ---------- Grab Elements ----------
  const form   = document.getElementById('exchangeForm');
  const panels = [...form.querySelectorAll('.panel')];
  const fill   = document.querySelector('.stepper-fill');
  const dots   = [...document.querySelectorAll('.step .dot')];

  // ---------- Wizard ----------
  let step = 1;

  const showStep = (n) => {
    step = Math.max(1, Math.min(3, n));
    panels.forEach(p => p.hidden = Number(p.dataset.step) !== step);
    dots.forEach((d, i) => d.classList.toggle('active', i < step));
    if (fill) fill.style.width = ((step - 1) / (3 - 1)) * 100 + '%';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const firstInvalidIn = (el) => {
    const controls = el.querySelectorAll('input, select, textarea');
    for (const c of controls) if (!c.checkValidity()) return c;
    return null;
  };

  form.addEventListener('click', (e) => {
    const t = e.target;
    if (t.classList.contains('next')) {
      const current = form.querySelector(`.panel[data-step="${step}"]`);
      const invalid = firstInvalidIn(current);
      if (invalid) { invalid.reportValidity(); return; }
      showStep(step + 1);
    }
    if (t.classList.contains('back')) showStep(step - 1);
  });

  showStep(1);

  // ---------- Uploader: single-pick per add, accumulate many ----------
function bindUploader(inputId, thumbsId, opts = {}) {
  const input   = document.getElementById(inputId);
  const thumbs  = document.getElementById(thumbsId);
  const trigger = document.querySelector(`.upload-box[data-for="${inputId}"]`);
  if (!input || !thumbs || !trigger) return;

  // เลือกครั้งละ 1 รูป แต่สะสมได้หลายรูป
  input.removeAttribute('multiple');

  const MAX_FILES   = opts.maxFiles ?? 10;
  const MAX_MB      = opts.maxMB ?? 8;
  const ACCEPT_LIST = (input.accept || 'image/*').split(',').map(s => s.trim().toLowerCase());

  const isAccept = (file) => ACCEPT_LIST.some(acc =>
    acc === 'image/*' ? file.type.startsWith('image/') : file.type.toLowerCase() === acc
  );

  // เปิดไฟล์ด้วยคลิก/คีย์บอร์ด
  trigger.addEventListener('click', (e) => { e.preventDefault(); input.value = ''; input.click(); });
  trigger.setAttribute('tabindex', '0');
  trigger.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.value = ''; input.click(); }
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
    handleOneFile(list[0]); // รับไฟล์แรกต่อครั้ง
  });

  // file picker (ครั้งละ 1)
  input.addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    if (f) handleOneFile(f);
    input.value = ''; // เลือกไฟล์เดิมซ้ำได้
  });

  function handleOneFile(file) {
    if (!isAccept(file)) return alert('ไฟล์ไม่ใช่รูปภาพที่ระบบรองรับ');
    if (file.size > MAX_MB * 1024 * 1024) return alert(`ไฟล์ใหญ่เกิน ${MAX_MB} MB`);

    // รวมไฟล์เดิม + ไฟล์ใหม่ (ไม่เช็กรูปซ้ำ)
    const dt = new DataTransfer();
    Array.from(input.files).forEach(f => dt.items.add(f));
    if (dt.files.length >= MAX_FILES) return alert(`อัปโหลดได้สูงสุด ${MAX_FILES} รูป`);
    dt.items.add(file);
    input.files = dt.files;

    renderThumbs(); // พรีวิวใหม่ทุกครั้ง
  }

  function renderThumbs() {
    thumbs.innerHTML = '';
    if (!input.files.length) return;

    Array.from(input.files).forEach((file, idx) => {
      const item = document.createElement('div');
      item.className = 'thumb-item';
      // ใช้ FileReader เพื่อเลี่ยงปัญหา cache/reuse ของ Object URL
      const reader = new FileReader();
      reader.onload = () => {
        item.innerHTML = `
          <img src="${reader.result}" alt="preview ${idx + 1}">
          <button type="button" class="thumb-remove" aria-label="ลบรูป">&times;</button>
          <div style="position:absolute;left:6px;bottom:6px;background:#0008;color:#fff;padding:2px 6px;border-radius:6px;font-size:12px;max-width:88px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${file.name}">
            ${file.name}
          </div>
        `;
        item.querySelector('.thumb-remove').addEventListener('click', () => {
          const dt = new DataTransfer();
          Array.from(input.files).forEach((f, i) => { if (i !== idx) dt.items.add(f); });
          input.files = dt.files;
          renderThumbs();
        });
      };
      reader.readAsDataURL(file); // ✅ base64 พรีวิวเสถียร
      thumbs.appendChild(item);
    });
  }
}


  bindUploader('p_images', 'thumbs1', { maxFiles: 10, maxMB: 8 });
  bindUploader('w_images', 'thumbs2', { maxFiles: 10, maxMB: 8 });

  // ---------- Submit ----------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // validate all steps
    for (let s = 1; s <= 3; s++) {
      const panel = form.querySelector(`.panel[data-step="${s}"]`);
      const invalid = firstInvalidIn(panel);
      if (invalid) { showStep(s); invalid.reportValidity(); return; }
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'กำลังอัปโหลด...'; }

    try {
      const fd = new FormData(form);
      if (fd.get('p_name')) fd.set('p_name', String(fd.get('p_name')).trim());

      const token = document.querySelector('meta[name="csrf-token"]')?.content;

      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: token ? { 'X-CSRF-Token': token } : undefined,
        body: fd
      });

      if (!res.ok) {
        const txt = await res.text().catch(() => '');
        throw new Error(txt || `อัปโหลดไม่สำเร็จ (HTTP ${res.status})`);
      }

      const data = await res.json().catch(() => ({}));
      const ref  = data.reference || data.id || 'REF-LOCAL';
      const name = (form.querySelector('#p_name')?.value || '').trim();

      const url = new URL(SUCCESS_PAGE, location.origin);
      url.searchParams.set('ref', ref);
      url.searchParams.set('name', name);
      location.href = url.toString();

    } catch (err) {
      console.error(err);
      alert(`เกิดข้อผิดพลาดในการอัปโหลด:\n${err.message || err}`);
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'ยืนยันการอัพโหลดสินค้า'; }
    }
  });
});
