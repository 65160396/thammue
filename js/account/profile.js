/* /js/account/profile.js
 * หน้าที่: หน้าโปรไฟล์ผู้ใช้
 * - ไฮไลต์เมนูซ้าย (account-common ทำให้แล้ว)
 * - เติมวัน/เดือน/ปี (account-common ทำให้แล้ว)
 * - อัปเดต dobIso (YYYY-MM-DD)
 * - จำกัด phone/postcode (account-common ทำให้แล้ว)
 * - initThaiAddress (account-common ทำให้แล้ว)
 * - เติมข้อมูลจากผู้ใช้ โดยไม่ทับค่าที่กำลังพิมพ์ (try Me.get(), fallback me_profile.php)
 */

(() => {
  // ========= DOB ISO =========
  const $d = document.getElementById('dobDay');
  const $m = document.getElementById('dobMonth');
  const $y = document.getElementById('dobYear');

  function updateDobIso() {
    const d = $d?.value || '';
    const m = $m?.value || '';
    const y = $y?.value || '';
    const h = document.getElementById('dobIso');
    if (!h) return;
    h.value = (d && m && y) ? `${y}-${m}-${d}` : '';
  }
  [$d, $m, $y].forEach(el => el && el.addEventListener('change', updateDobIso));
  document.getElementById('profileForm')?.addEventListener('submit', updateDobIso);

  // ========= helper: รอจน select มี option ค่านั้นก่อนค่อยเซ็ต =========
  function setSelectValue(sel, val, timeoutMs = 3000) {
    if (!sel || !val) return;
    const start = Date.now();
    const t = setInterval(() => {
      const found = Array.from(sel.options).some(o => o.value == val);
      if (found) { sel.value = val; sel.dispatchEvent(new Event('change')); clearInterval(t); }
      if (Date.now() - start > timeoutMs) clearInterval(t);
    }, 120);
  }

  // ========= เติมข้อมูลผู้ใช้ =========
  async function loadProfile() {
    let u = null;

    // 1) ลองใช้ Me.get() (ข้อมูลแคชกลางของทั้งเว็บ)
    if (window.Me && typeof Me.get === 'function') {
      try {
        const d = await Me.get();
        if (d && d.ok) u = d.user || null;
      } catch {}
    }

    // 2) ถ้ายังไม่พอ (เช่นรายละเอียดที่อยู่/เพศ/DOB) ลอง me_profile.php
    if (!u || (!u.addr_province && !u.gender && !u.dob)) {
      try {
        const r = await fetch('/page/backend/me_profile.php', { cache: 'no-store' });
        const d2 = r.ok ? await r.json() : { ok:false };
        if (d2 && d2.ok) u = Object.assign({}, u || {}, d2.user || {});
      } catch {}
    }

    if (!u) return;

    const set = (id, v) => {
      const el = document.getElementById(id);
      if (el && !el.value && v != null && v !== '') el.value = v;
    };

    // ชื่อ–นามสกุล
    set('first_name', u.first_name || (u.name || '').split(' ')[0]);
    set('last_name',  u.last_name);

    // เบอร์/ที่อยู่บรรทัด
    set('phone',      u.phone);
    set('addr_line',  u.addr_line);
    set('addr_postcode', u.addr_postcode);

    // เพศ
    if (u.gender) {
      const g = document.getElementById('gender');
      if (g && !g.value) g.value = u.gender;
    }

    // DOB (YYYY-MM-DD)
    if (u.dob) {
      const [yy, mm, dd] = String(u.dob).split('-');
      if ($y && $m && $d && yy && mm && dd) {
        $y.value = yy; $m.value = mm; $d.value = dd;
        updateDobIso();
      }
    } else {
      updateDobIso();
    }

    // จังหวัด/อำเภอ/ตำบล: ต้องรอ options โหลดจาก address.js → ใช้ setSelectValue
    const $prov = document.getElementById('addr_province');
    const $dist = document.getElementById('addr_district');
    const $subd = document.getElementById('addr_subdistrict');
    setSelectValue($prov, u.addr_province);
    setTimeout(() => setSelectValue($dist, u.addr_district), 250);
    setTimeout(() => setSelectValue($subd, u.addr_subdistrict), 500);
  }

  // เริ่มทำงาน
  loadProfile();
})();
