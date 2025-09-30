(function () {
  const DATA_URL =
    'https://raw.githubusercontent.com/kongvut/thai-province-data/refs/heads/master/api/latest/province_with_district_and_sub_district.json';

  const provinceEl    = document.getElementById('province');
  const districtEl    = document.getElementById('district');
  const subdistrictEl = document.getElementById('subdistrict');

  const resetSelect = (el, ph, disabled = true) => {
    el.disabled = disabled;
    el.innerHTML = `<option value="" selected disabled>${ph}</option>`;
  };

  const fillOptions = (el, items, labelKey, valueKey = labelKey) => {
    const frag = document.createDocumentFragment();
    items.forEach(item => {
      const opt = document.createElement('option');
      const label = typeof labelKey === 'function' ? labelKey(item) : item[labelKey];
      const value = typeof valueKey === 'function' ? valueKey(item) : item[valueKey];
      opt.textContent = label; opt.value = value;
      frag.appendChild(opt);
    });
    el.appendChild(frag);
  };

  let DATA = [];
  fetch(DATA_URL, { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      DATA = json;
      fillOptions(provinceEl, DATA, 'name_th');
    })
    .catch(err => {
      console.error('โหลดข้อมูลจังหวัดไม่สำเร็จ:', err);
      alert('โหลดข้อมูลที่อยู่ไม่สำเร็จ กรุณารีเฟรชหน้าอีกครั้ง');
    });

  // จังหวัด -> อำเภอ/เขต
  provinceEl.addEventListener('change', () => {
    const prov = DATA.find(p => p.name_th === provinceEl.value);
    resetSelect(districtEl, '— เลือกอำเภอ/เขต —', false);
    resetSelect(subdistrictEl, '— เลือกตำบล/แขวง —', true);
    if (!prov) return;
    fillOptions(districtEl, prov.districts ?? [], 'name_th');
  });

  // อำเภอ/เขต -> ตำบล/แขวง
  districtEl.addEventListener('change', () => {
    const prov = DATA.find(p => p.name_th === provinceEl.value);
    const dist = prov?.districts?.find(d => d.name_th === districtEl.value);
    resetSelect(subdistrictEl, '— เลือกตำบล/แขวง —', false);
    if (!dist) return;
    fillOptions(subdistrictEl, dist.sub_districts ?? [], 'name_th');
  });
})();
