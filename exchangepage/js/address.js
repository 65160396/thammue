// /js/address.js
(function () {
  const DATA_URL =
    'https://raw.githubusercontent.com/kongvut/thai-province-data/refs/heads/master/api/latest/province_with_district_and_sub_district.json';

  // ------- utility -------
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

  // ------- data loader (โหลดครั้งเดียวใช้ได้ทั้งหน้า) -------
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

  // ------- main initializer (รียูสได้หลายชุด) -------
  function initThaiAddress(cfg) {
    // cfg: { province, district, subdistrict, postcode? } เป็น CSS selector
    const $prov = document.querySelector(cfg.province);
    const $dist = document.querySelector(cfg.district);
    const $subd = document.querySelector(cfg.subdistrict);
    const $post = cfg.postcode ? document.querySelector(cfg.postcode) : null;

    if (!$prov || !$dist || !$subd) return;

    // ค่าเริ่มต้น
    resetSelect($prov,  '— เลือกจังหวัด —',   false);
    resetSelect($dist,  '— เลือกอำเภอ/เขต —', true);
    resetSelect($subd,  '— เลือกตำบล/แขวง —', true);
    if ($post) $post.value = '';

    // เมื่อข้อมูลพร้อม ให้เติมจังหวัด และ bind อีเวนต์
    ensureData().then((DATA) => {
      fillOptions($prov, DATA, 'name_th');

      // จังหวัด -> อำเภอ/เขต
      $prov.addEventListener('change', () => {
        const prov = DATA.find((p) => p.name_th === $prov.value);
        resetSelect($dist, '— เลือกอำเภอ/เขต —', false);
        resetSelect($subd, '— เลือกตำบล/แขวง —', true);
        if (!prov) return;
        fillOptions($dist, prov.districts ?? [], 'name_th');
        if ($post) $post.value = '';
      });

      // อำเภอ/เขต -> ตำบล/แขวง
      $dist.addEventListener('change', () => {
        const prov = DATA.find((p) => p.name_th === $prov.value);
        const dist = prov?.districts?.find((d) => d.name_th === $dist.value);
        resetSelect($subd, '— เลือกตำบล/แขวง —', false);
        if (!dist) return;
        // note: key ใน dataset นี้คือ sub_districts และมี zip_code
        fillOptions($subd, dist.sub_districts ?? [], (s) => s.name_th, (s) => s.name_th);
        if ($post) $post.value = '';
      });

      // ตำบล/แขวง -> เติมรหัสไปรษณีย์อัตโนมัติ
      $subd.addEventListener('change', () => {
        if (!$post) return;
        const prov = DATA.find((p) => p.name_th === $prov.value);
        const dist = prov?.districts?.find((d) => d.name_th === $dist.value);
        const subd = dist?.sub_districts?.find((s) => s.name_th === $subd.value);
        $post.value = subd?.zip_code || '';
      });
    });
  }

  // โยนออกเป็น global ให้หน้าอื่นเรียกใช้งานได้
  window.initThaiAddress = initThaiAddress;

  // Backward-compatible: ถ้าหน้าไหนยังใช้ id เดิม (province/district/subdistrict)
  // จะ auto-init ให้ เพื่อไม่ให้ของเก่าเสีย
  document.addEventListener('DOMContentLoaded', () => {
    const hasLegacy =
      document.getElementById('province') &&
      document.getElementById('district') &&
      document.getElementById('subdistrict');
    if (hasLegacy) {
      initThaiAddress({
        province: '#province',
        district: '#district',
        subdistrict: '#subdistrict',
        // ถ้าหน้าเก่ามี input postcode ให้ใส่ selector ตรงนี้เพิ่มได้
      });
    }
  });
})();