// ======= Wizard Logic =======
const form = document.getElementById('exchangeForm');
const panels = [...form.querySelectorAll('.panel')];
const fill = document.querySelector('.stepper-fill');
const dots = [...document.querySelectorAll('.step .dot')];
let step = 1;

const showStep = (n) => {
  step = Math.max(1, Math.min(3, n));
  panels.forEach(p => p.hidden = Number(p.dataset.step) !== step);
  dots.forEach((d, i) => d.classList.toggle('active', i < step));
  fill.style.width = ((step - 1) / (3 - 1)) * 100 + '%';
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const firstInvalidIn = (el) => {
  const controls = el.querySelectorAll('input, select, textarea');
  for (const c of controls) {
    if (!c.checkValidity()) return c;
  }
  return null;
};

form.addEventListener('click', (e) => {
  if (e.target.classList.contains('next')) {
    const current = form.querySelector(`.panel[data-step="${step}"]`);
    const invalid = firstInvalidIn(current);
    if (invalid) { invalid.reportValidity(); return; }
    showStep(step + 1);
  }
  if (e.target.classList.contains('back')) {
    showStep(step - 1);
  }
});

// ======= Upload + Thumbs =======
const previews = {
  p_images: document.getElementById('thumbs1'),
  w_images: document.getElementById('thumbs2')
};

document.querySelectorAll('.upload-box').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('data-for');
    document.getElementById(id).click();
  });
});

const renderThumbs = (input, box) => {
  box.innerHTML = '';
  [...input.files].slice(0, 6).forEach(file => {
    const url = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = url;
    img.alt = file.name;
    img.className = 'thumb';
    box.appendChild(img);
  });
};

Object.keys(previews).forEach(id => {
  const input = document.getElementById(id);
  input.addEventListener('change', () => renderThumbs(input, previews[id]));
});

// init
showStep(1);
// ===== Multi-image uploader (ทั่วไป ใช้ซ้ำได้) =====
function bindUploader(inputId, thumbsId, opts = {}) {
  const input = document.getElementById(inputId);
  const thumbs = document.getElementById(thumbsId);
  const trigger = document.querySelector(`.upload-box[data-for="${inputId}"]`);

  if (!input || !thumbs || !trigger) return;

  // ตั้งค่า
  const MAX_FILES = opts.maxFiles ?? 10;      // จำนวนรูปสูงสุด
  const MAX_MB = opts.maxMB ?? 5;          // ขนาด/รูป (MB)
  const ACCEPT = (input.accept || 'image/*')
    .split(',').map(s => s.trim().toLowerCase());

  // เปิดไฟล์จากปุ่ม +
  trigger.addEventListener('click', () => input.click());

  // รองรับลากวาง
  ['dragenter', 'dragover'].forEach(evt =>
    trigger.addEventListener(evt, e => { e.preventDefault(); trigger.classList.add('drag'); })
  );
  ['dragleave', 'drop'].forEach(evt =>
    trigger.addEventListener(evt, e => { e.preventDefault(); trigger.classList.remove('drag'); })
  );
  trigger.addEventListener('drop', e => handleFiles(e.dataTransfer.files));

  // เปิดจาก file picker
  input.addEventListener('change', e => handleFiles(e.target.files));

  function handleFiles(fileList) {
    if (!fileList || !fileList.length) return;

    // รวมไฟล์เดิม + ใหม่ ด้วย DataTransfer (เพราะ FileList แก้ตรง ๆ ไม่ได้)
    const dt = new DataTransfer();
    // เก็บไฟล์เดิมก่อน
    Array.from(input.files).forEach(f => dt.items.add(f));

    // เติมไฟล์ใหม่ (ตรวจเงื่อนไข)
    for (const file of fileList) {
      const isTypeOk = ACCEPT.some(acc => acc === 'image/*' ? file.type.startsWith('image/') : file.type.toLowerCase() === acc);
      const isSizeOk = file.size <= MAX_MB * 1024 * 1024;
      if (!isTypeOk || !isSizeOk) continue;
      if (dt.files.length >= MAX_FILES) break;

      dt.items.add(file);
    }

    // อัปเดตกลับเข้า input
    input.files = dt.files;

    // วาดตัวอย่างใหม่
    renderThumbs();
  }

  function renderThumbs() {
    thumbs.innerHTML = '';
    if (!input.files.length) return;

    Array.from(input.files).forEach((file, idx) => {
      const url = URL.createObjectURL(file);
      const item = document.createElement('div');
      item.className = 'thumb-item';
      item.innerHTML = `
        <img src="${url}" alt="preview ${idx + 1}">
        <button type="button" class="thumb-remove" aria-label="ลบรูป">&times;</button>
      `;
      // ลบรูปนี้
      item.querySelector('.thumb-remove').addEventListener('click', () => {
        const dt = new DataTransfer();
        Array.from(input.files).forEach((f, i) => { if (i !== idx) dt.items.add(f); });
        input.files = dt.files;
        renderThumbs();
      });
      thumbs.appendChild(item);
    });
  }
}

// ผูกกับฟิลด์ทั้งสองชุด
document.addEventListener('DOMContentLoaded', () => {
  bindUploader('p_images', 'thumbs1', { maxFiles: 10, maxMB: 8 });
  bindUploader('w_images', 'thumbs2', { maxFiles: 10, maxMB: 8 });
});

