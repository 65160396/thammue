// /exchangepage/public/js/index.page.js
// ใช้กับหน้า public/index.html
const API_BASE = '/exchangepage/api';
let __ME_ID = 0;

async function fetchMe() {
  try {
    // อยู่ใต้ /exchangepage/public → ../api = /exchangepage/api
    const r = await fetch('../api/me.php', { cache: 'no-store' });
    const d = await r.json().catch(() => ({}));
    if (d && d.user && Number.isInteger(d.user.id)) __ME_ID = d.user.id;
  } catch (_) {}
}

// ======= UI helpers (kebab) =======
function closeAllKebabs() {
  document.querySelectorAll('.card-kebab__menu').forEach(m => m.hidden = true);
  document
    .querySelectorAll('.card-kebab__btn[aria-expanded="true"]')
    .forEach(b => b.setAttribute('aria-expanded', 'false'));
}
function toggleKebab(btn) {
  const menuId = btn.getAttribute('aria-controls');
  const menu = document.getElementById(menuId);
  const expanded = btn.getAttribute('aria-expanded') === 'true';
  closeAllKebabs();
  if (!expanded && menu) {
    btn.setAttribute('aria-expanded', 'true');
    menu.hidden = false;
    menu.querySelector('.card-kebab__item')?.focus();
  }
}

// ======= Card renderer =======
function computeIsOwner(it) {
  if (window.DEBUG_IS_OWNER) return true;
  if (it.is_owner === true) return true;
  if (Number.isInteger(it.user_id) && it.user_id === __ME_ID) return true;
  return false;
}

function cardHtml(it) {
  const img = it.cover || (it.images && it.images[0]) || '/exchangepage/public/img/placeholder.png';
  const title = it.title || '';
  const id = it.id;
  const province = it.province || '';
  const catName = it.category_name || it.category || '';
  const href = `/exchangepage/public/detail.html?id=${encodeURIComponent(id)}&view=public`;
  const isOwner = computeIsOwner(it);

  const menuHtml = isOwner
    ? `
      <button class="card-kebab__item" role="menuitem" data-action="edit" data-id="${id}">✏️ แก้ไขสินค้า</button>
      <button class="card-kebab__item card-kebab__item--danger" role="menuitem" data-action="delete" data-id="${id}">🗑️ ลบสินค้า</button>
    `
    : `
      <button class="card-kebab__item" role="menuitem" data-action="report" data-id="${id}">🚩 Report</button>
    `;

  return `
  <article class="card" data-card-id="${id}">
    <a class="stretched-link" href="${href}" aria-label="ดูรายละเอียด: ${title}"></a>
    <div class="card-kebab" data-id="${id}">
      <button class="card-kebab__btn" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-${id}" title="เพิ่มเติม">
        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
      </button>
      <div class="card-kebab__menu" id="menu-${id}" role="menu" hidden>
        ${menuHtml}
      </div>
    </div>
    <div class="thumb" role="img" aria-label="${title}">
      ${img ? `<img class="thumb-img" src="${img}" alt="">` : ''}
    </div>
    <div class="card-body">
      <h3>${title}</h3>
      <p class="muted">หมวด: ${catName || '-'} ${province ? ' · ' + province : ''}</p>
      <a class="btn btn-sm" href="/exchangepage/public/detail.html?id=${id}&view=public">ดูรายละเอียด</a>
    </div>
  </article>`;
}

// ======= helpers =======
function buildQuery(qsObj = {}) {
  const sp = new URLSearchParams();
  Object.entries(qsObj).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') sp.set(k, String(v));
  });
  return `?${sp.toString()}`;
}

async function fetchList({ sort = 'new', limit = 12, offset = 0, category_id } = {}) {
  const qs = buildQuery({ sort, limit, offset, category_id });
  const res = await fetch(`${API_BASE}/items/list.php${qs}`, { cache: 'no-store' });
  if (!res.ok) return { items: [], total: 0 };
  const data = await res.json().catch(() => ({}));
  const items = Array.isArray(data.items) ? data.items : [];
  const total = Number.isFinite(data.total) ? Number(data.total) : 0;
  return { items, total };
}

// ======= Data loader (ทั่วไป) =======
async function loadSection({ el, qs = '' }) {
  const host = document.getElementById(el);
  if (!host) return;
  closeAllKebabs();

  // อ่านพารามิเตอร์จาก qs (เช่น ?category_id=5&limit=8&sort=new)
  const sp = new URLSearchParams(qs.replace(/^\?/, ''));
  const limit = Number(host.dataset.limit) || Number(sp.get('limit')) || 12;
  const sort = sp.get('sort') || 'new';
  const category_id = sp.get('category_id') || undefined;

  // skeleton
  host.innerHTML = Array.from({ length: limit })
    .map(() => `<article class="card"><div class="thumb"></div><div class="card-body"><h3>&nbsp;</h3></div></article>`)
    .join('');

  try {
    const { items } = await fetchList({ sort, limit, offset: 0, category_id });
    host.innerHTML = (items.length ? items : []).map(cardHtml).join('') || `
      <article class="card">
        <div class="thumb"></div>
        <div class="card-body">
          <h3>ยังไม่มีรายการ</h3>
          <p class="muted">อัปโหลดของชิ้นแรกเพื่อเริ่มแลกเปลี่ยนกันเลย</p>
          <a class="btn btn-sm" href="/exchangepage/public/upload.html">อัปโหลดสินค้า</a>
        </div>
      </article>`;
  } catch (err) {
    console.error('loadSection failed', err);
    host.innerHTML = '<div class="muted">โหลดรายการไม่สำเร็จ</div>';
  }
}

// ======= Data loader เฉพาะ latestGrid: เอา “ของคนอื่นเท่านั้น” =======
async function loadLatestOthers({ el }) {
  const host = document.getElementById(el);
  if (!host) return;
  closeAllKebabs();

  const limit = Number(host.dataset.limit) || 4;       // ต้องการกี่ชิ้นบนหน้าแรก
  const pageSize = Math.max(12, limit);                // ขนาดดึงต่อรอบ
  let offset = 0;
  let picked = [];

  // skeleton
  host.innerHTML = Array.from({ length: limit })
    .map(() => `<article class="card"><div class="thumb"></div><div class="card-body"><h3>&nbsp;</h3></div></article>`)
    .join('');

  try {
    while (picked.length < limit) {
      const { items } = await fetchList({ sort: 'new', limit: pageSize, offset });
      if (!items.length) break;

      for (const it of items) {
        if (!computeIsOwner(it)) picked.push(it);
        if (picked.length >= limit) break;
      }
      if (items.length < pageSize) break;
      offset += pageSize;
    }

    if (!picked.length) {
      host.innerHTML = `
        <article class="card">
          <div class="thumb"></div>
          <div class="card-body">
            <h3>ยังไม่มีรายการจากผู้ใช้อื่น</h3>
            <p class="muted">ลองกลับมาดูใหม่อีกครั้ง หรือไปที่ <a class="link" href="/exchangepage/public/list.html">ดูทั้งหมด</a></p>
          </div>
        </article>`;
      return;
    }
    host.innerHTML = picked.slice(0, limit).map(cardHtml).join('');
  } catch (err) {
    console.error('loadLatestOthers failed', err);
    host.innerHTML = '<div class="muted">โหลดรายการไม่สำเร็จ</div>';
  }
}

// ======= Bind kebab actions =======
function bindGridActions(gridId) {
  const host = document.getElementById(gridId);
  if (!host) return;

  host.addEventListener('click', async (e) => {
    const kebabBtn = e.target.closest('.card-kebab__btn');
    if (kebabBtn) {
      e.preventDefault();
      toggleKebab(kebabBtn);
      return;
    }

    const itemBtn = e.target.closest('.card-kebab__item');
    if (!itemBtn) return;
    e.preventDefault();

    const action = itemBtn.dataset.action;
    const itemId = itemBtn.dataset.id;

    if (action === 'edit') {
      location.href = `/exchangepage/public/edit.html?id=${encodeURIComponent(itemId)}`;
      return;
    }

    if (action === 'delete') {
      if (!confirm('ต้องการลบสินค้านี้หรือไม่?')) return;
      try {
        const r = await fetch(`${API_BASE}/items/delete.php`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: new URLSearchParams({ id: String(itemId) })
        });
        const d = await r.json().catch(() => ({}));
        if (d && d.ok) {
          document.querySelector(`.card[data-card-id="${itemId}"]`)?.remove();
        } else {
          alert('ลบไม่สำเร็จ: ' + (d?.error || 'UNKNOWN'));
        }
      } catch {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ');
      } finally {
        closeAllKebabs();
      }
      return;
    }

    if (action === 'report') {
      const reason = prompt('ระบุเหตุผลในการรายงานสินค้า (ตัวอย่าง: สแปม, ของผิดกฎหมาย, ไม่เหมาะสม ฯลฯ)');
      if (!reason) { closeAllKebabs(); return; }
      try {
        const r = await fetch(`${API_BASE}/items/report.php`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: new URLSearchParams({ id: String(itemId), reason })
        });
        const d = await r.json().catch(() => ({}));
        if (d && d.ok) alert('ส่งรายงานเรียบร้อย ขอบคุณที่ช่วยดูแลชุมชน');
        else alert('รายงานไม่สำเร็จ: ' + (d?.error || 'UNKNOWN'));
      } catch {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ');
      } finally {
        closeAllKebabs();
      }
    }
  });
}

// ปิดเมื่อคลิกนอก / กด Esc
document.addEventListener('click', (e) => {
  if (!e.target.closest('.card-kebab')) closeAllKebabs();
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAllKebabs();
});

// ======= Boot =======
(async function boot() {
  await fetchMe();
  ['latestGrid', 'booksGrid', 'clothesGrid'].forEach(bindGridActions);

  // “สินค้าที่คุณสนใจ” — แสดงเฉพาะของคนอื่น
  await loadLatestOthers({ el: 'latestGrid' });

  // หมวดหนังสือ/เสื้อผ้า — ตามปกติ
  loadSection({ el: 'booksGrid', qs: '?category_id=5&limit=8&sort=new' });
  loadSection({ el: 'clothesGrid', qs: '?category_id=4&limit=8&sort=new' });
})();
