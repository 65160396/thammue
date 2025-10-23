// /js/address.js  (เดิมของคุณ)
(function () {
  const DATA_URL =
    'https://raw.githubusercontent.com/kongvut/thai-province-data/refs/heads/master/api/latest/province_with_district_and_sub_district.json';

  const resetSelect = (el, ph, disabled = true) => {
    if (!el) return;
    el.disabled = disabled;
    el.innerHTML = `<option value="" selected disabled>${ph}</option>`;
  };
  const fillOptions = (el, items, labelKey, valueKey = labelKey) => {
    if (!el) return;
    const frag = document.createDocumentFragment();
    items.forEach((item) => {
      const opt = document.createElement('option');
      const label = typeof labelKey === 'function' ? labelKey(item) : item[labelKey];
      const value = typeof valueKey === 'function' ? valueKey(item) : item[valueKey];
      opt.textContent = label;
      opt.value = value;
      frag.appendChild(opt);
    });
    el.appendChild(frag);
  };

  let DATA = null;
  let loading = null;
  const ensureData = () => {
    if (DATA) return Promise.resolve(DATA);
    if (loading) return loading;
    loading = fetch(DATA_URL, { cache: 'no-store' })
      .then((r) => r.json())
      .then((json) => (DATA = json))
      .catch((err) => {
        console.error('โหลดข้อมูลจังหวัดไม่สำเร็จ:', err);
        alert('โหลดข้อมูลที่อยู่ไม่สำเร็จ กรุณารีเฟรชหน้าอีกครั้ง');
        throw err;
      });
    return loading;
  };

  // ✅ รองรับ postcode (อ่านได้จาก cfg.postcode)
  function initThaiAddress(cfg) {
    // cfg: { province, district, subdistrict, postcode? }
    const $prov = document.querySelector(cfg.province);
    const $dist = document.querySelector(cfg.district);
    const $subd = document.querySelector(cfg.subdistrict);
    const $post = cfg.postcode ? document.querySelector(cfg.postcode) : null;

    if (!$prov || !$dist || !$subd) return;

    resetSelect($prov, '— เลือกจังหวัด —', false);
    resetSelect($dist, '— เลือกอำเภอ/เขต —', true);
    resetSelect($subd, '— เลือกตำบล/แขวง —', true);
    if ($post) {
      $post.value = '';
      $post.readOnly = true;     // ✅ postcode ให้ระบบเติมอัตโนมัติ
    }

    ensureData().then((DATA) => {
      fillOptions($prov, DATA, 'name_th');

      // จังหวัด -> อำเภอ/เขต
      $prov.addEventListener('change', () => {
        const prov = DATA.find((p) => p.name_th === $prov.value);
        resetSelect($dist, '— เลือกอำเภอ/เขต —', false);
        resetSelect($subd, '— เลือกตำบล/แขวง —', true);
        if (!prov) return;
        fillOptions($dist, prov.districts ?? [], 'name_th');
        if ($post) { $post.value = ''; } // เปลี่ยนจังหวัด เคลียร์ zip
      });

      // อำเภอ/เขต -> ตำบล/แขวง
      $dist.addEventListener('change', () => {
        const prov = DATA.find((p) => p.name_th === $prov.value);
        const dist = prov?.districts?.find((d) => d.name_th === $dist.value);
        resetSelect($subd, '— เลือกตำบล/แขวง —', false);
        if (!dist) return;
        // dataset นี้ใช้ key sub_districts และมี zip_code
        fillOptions($subd, dist.sub_districts ?? [], (s) => s.name_th, (s) => s.name_th);
        if ($post) { $post.value = ''; } // เปลี่ยนอำเภอ เคลียร์ zip
      });

      // ตำบล/แขวง -> เติมรหัสไปรษณีย์อัตโนมัติ
      $subd.addEventListener('change', () => {
        if (!$post) return;
        const prov = DATA.find((p) => p.name_th === $prov.value);
        const dist = prov?.districts?.find((d) => d.name_th === $dist.value);
        const subd = dist?.sub_districts?.find((s) => s.name_th === $subd.value);

        // ✅ เติมอัตโนมัติให้ตรง และคง readOnly (ผู้ใช้ยังแก้ได้ถ้าคุณอยากให้ก็ set readOnly=false)
        $post.value = subd?.zip_code || '';
        $post.readOnly = true;
      });
    });
  }

  window.initThaiAddress = initThaiAddress;

  // ✅ Auto-init: ถ้ามี id พื้นฐานอยู่แล้ว ให้ใส่ postcode ด้วย (#zipcode)
  document.addEventListener('DOMContentLoaded', () => {
    const hasLegacy =
      document.getElementById('province') &&
      document.getElementById('district') &&
      document.getElementById('subdistrict');
    if (hasLegacy) {
      const hasZip = !!document.getElementById('zipcode');
      initThaiAddress({
        province: '#province',
        district: '#district',
        subdistrict: '#subdistrict',
        postcode: hasZip ? '#zipcode' : undefined,   // ✅ เพิ่มให้ auto-fill
      });
    }
  });
})();
