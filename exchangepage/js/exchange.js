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
