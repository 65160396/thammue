// /exchangepage/public/js/dot/list.page.js
import { Kebab } from '../cards.kebab.js';

const API_BASE = '/exchangepage/api';

function getQS() {
  const u = new URL(location.href);
  return {
    interest: u.searchParams.get('interest'),
    category_id: u.searchParams.get('category_id'),
    q: u.searchParams.get('q'),
  };
}

function cardHtml(it) {
  const img = it.cover || (it.images && it.images[0]) || '/exchangepage/public/img/placeholder.png';
  const title = it.title || '';
  const id = it.id;
  const province = it.province || '';
  const catName = it.category_name || it.category || '';
  const href = `/exchangepage/public/detail.html?id=${encodeURIComponent(id)}&view=public`;
  const isOwner = it.is_owner === true;

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

    <div class="thumb">
      ${img ? `<img src="${img}" alt="" style="width:100%;height:180px;object-fit:cover;border-radius:12px 12px 0 0;">` : ''}
    </div>
    <div class="card-body">
      <h3>${title}</h3>
      <p class="muted">หมวด: ${catName || '-'} ${province ? ' · ' + province : ''}</p>
      <a class="btn btn-sm" href="${href}">ดูรายละเอียด</a>
    </div>
  </article>`;
}

// paging state (client)
let LIMIT = 24;
let loaded = 0;
let isLoading = false;
let lastBatchCount = 0;

function buildQuery(params) {
  const sp = new URLSearchParams();
  sp.set('limit', LIMIT);
  if (params.interest) sp.set('interest', params.interest);
  if (params.category_id) sp.set('category_id', params.category_id);
  if (params.q) sp.set('q', params.q);
  if (loaded > 0) sp.set('offset', loaded);
  return `?${sp.toString()}`;
}

async function fetchAndRender(append = false) {
  if (isLoading) return;
  isLoading = true;

  const host = document.getElementById('listGrid');
  const btn = document.getElementById('loadMore');
  const qs = getQS();

  const t = document.getElementById('listTitle');
  if (t) t.textContent = qs.interest
    ? 'ทั้งหมดจากความสนใจของคุณ'
    : qs.category_id ? 'สินค้าตามหมวดหมู่' : 'รายการทั้งหมด';

  try {
    const res = await fetch(`${API_BASE}/items/list.php${buildQuery(qs)}`, { cache: 'no-store' });
    const data = await res.json();

    if (data?.ok && Array.isArray(data.items) && data.items.length) {
      const html = data.items.map(cardHtml).join('');
      host.innerHTML = append ? (host.innerHTML + html) : html;

      // สำคัญ: bind เมนู 3 จุดหลัง render
      Kebab.bind('#listGrid');

      lastBatchCount = data.items.length;
      loaded += data.items.length;
      btn.hidden = !(lastBatchCount === LIMIT);
    } else {
      if (!append) host.innerHTML = '<p class="muted">ยังไม่มีรายการในเงื่อนไขนี้</p>';
      btn.hidden = true;
    }
  } catch {
    if (!append) host.innerHTML = '<p class="muted">โหลดข้อมูลไม่สำเร็จ ลองใหม่อีกครั้ง</p>';
    btn.hidden = true;
  } finally {
    isLoading = false;
  }
}

// เริ่มต้น
fetchAndRender(false);

// โหลดเพิ่ม
document.getElementById('loadMore')?.addEventListener('click', () => fetchAndRender(true));

// จัดการ action edit/delete/report ด้วย event delegation
document.getElementById('listGrid')?.addEventListener('click', async (e) => {
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
      const d = await r.json().catch(()=> ({}));
      if (d && d.ok) {
        document.querySelector(`.card[data-card-id="${itemId}"]`)?.remove();
      } else {
        alert('ลบไม่สำเร็จ: ' + (d?.error || 'UNKNOWN'));
      }
    } catch {
      alert('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ');
    } finally {
      Kebab.closeAll(document.getElementById('listGrid'));
    }
    return;
  }

  if (action === 'report') {
    const reason = prompt('ระบุเหตุผลในการรายงานสินค้า (ตัวอย่าง: สแปม, ของผิดกฎหมาย, ไม่เหมาะสม ฯลฯ)');
    if (!reason) { Kebab.closeAll(document.getElementById('listGrid')); return; }
    try {
      const r = await fetch(`${API_BASE}/items/report.php`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: new URLSearchParams({ id: String(itemId), reason })
      });
      const d = await r.json().catch(()=> ({}));
      if (d && d.ok) { alert('ส่งรายงานเรียบร้อย ขอบคุณที่ช่วยดูแลชุมชน'); }
      else { alert('รายงานไม่สำเร็จ: ' + (d?.error || 'UNKNOWN')); }
    } catch {
      alert('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ');
    } finally {
      Kebab.closeAll(document.getElementById('listGrid'));
    }
  }
});
