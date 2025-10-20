// file-input-enhance.js
// แปลง input[type=file] ทุกตัวใน .wizard-card ให้เป็นกล่อง "คลิกเพื่อเพิ่มรูปภาพ"
// และแสดงชื่อไฟล์เมื่อมีการเลือก
(function () {
 const inputs = document.querySelectorAll('input[type="file"]');

  if (!inputs.length) return;

  inputs.forEach((inp) => {
    // ห้าม wrap ซ้ำ
    if (inp.parentElement && inp.parentElement.classList.contains('file-wrap')) {
      return;
    }

    // สร้าง wrapper และ fake box
    const wrap = document.createElement('div');
    wrap.className = 'file-wrap';

    const fake = document.createElement('div');
    fake.className = 'fake-box';
    fake.textContent = 'คลิกเพื่อเพิ่มรูปภาพ';

    // สอด wrap ครอบ input (ไม่ต้องแก้ HTML เดิม)
    const field = inp.parentElement; // .field
    field.insertBefore(wrap, inp);
    wrap.appendChild(inp);
    wrap.appendChild(fake);

    // อัปเดตชื่อไฟล์
    const update = () => {
      if (inp.files && inp.files.length) {
        fake.textContent = inp.files.length === 1
          ? inp.files[0].name
          : `เลือกแล้ว ${inp.files.length} ไฟล์`;
      } else {
        fake.textContent = 'คลิกเพื่อเพิ่มรูปภาพ';
      }
    };

    // ตรวจ disabled ด้วย
const refreshDisabled = () => {
  if (inp.disabled) {
    wrap.classList.add('is-disabled');
  } else {
    wrap.classList.remove('is-disabled');
  }
};
update();
refreshDisabled();
const mo = new MutationObserver(refreshDisabled);
mo.observe(inp, { attributes: true, attributeFilter: ['disabled'] });


    // ฟังอีเวนต์
    inp.addEventListener('change', update);
    inp.addEventListener('focus', () => wrap.classList.add('is-focus'));
    inp.addEventListener('blur',  () => wrap.classList.remove('is-focus'));

    // ค่าเริ่มต้น
    update();
  });
})();
