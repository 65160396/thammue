import { listMyItems, createRequest } from './detail.api.js';

export function initOfferModal({ itemId, onAuthRedirect }) {
  const modal     = document.getElementById('offerModal');
  const grid      = document.getElementById('myItems');
  const msgEl     = document.getElementById('offerMessage');
  const closeBtn  = document.getElementById('offerClose');
  const cancelBtn = document.getElementById('offerCancel');
  const submitBtn = document.getElementById('offerSubmit');
  let selectedOfferId = 0;

  function open()  { modal.hidden = false; document.body.style.overflow = 'hidden'; loadMyItems(); }
  function close() { modal.hidden = true; document.body.style.overflow = ''; selectedOfferId = 0; msgEl.value=''; grid.innerHTML=''; }

  async function loadMyItems() {
    grid.innerHTML = `<div class="offer-card" style="padding:12px;text-align:center">กำลังโหลดของของฉัน…</div>`;
    try {
      const items = await listMyItems();
      if (!items.length) { grid.innerHTML = `<div class="offer-card" style="padding:12px;text-align:center">คุณยังไม่มีสินค้าที่อัปโหลด</div>`; return; }
      grid.innerHTML = items.map(it => {
        const src = it.cover || (it.images && it.images[0]) || '/thammue/img/placeholder.png';
        return `
          <label class="offer-card" data-id="${it.id}">
            <img class="offer-thumb" src="${src}" alt="">
            <div class="offer-body2">
              <div class="offer-title">${(it.title || '').replace(/</g,'&lt;')}</div>
              <div class="offer-meta">${it.category_name || ''} ${it.province ? '· ' + it.province : ''}</div>
            </div>
          </label>`;
      }).join('');
    } catch (e) {
      if (e.message === 'AUTH') onAuthRedirect?.();
      grid.innerHTML = `<div class="offer-card" style="padding:12px;text-align:center">โหลดข้อมูลไม่สำเร็จ</div>`;
    }
  }

  grid?.addEventListener('click', (e) => {
    const card = e.target.closest('.offer-card'); if (!card) return;
    grid.querySelectorAll('.offer-card').forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    selectedOfferId = parseInt(card.dataset.id, 10);
  });

  submitBtn?.addEventListener('click', async () => {
    if (!itemId) { alert('ลิงก์ไม่ถูกต้อง'); return; }
    if (!selectedOfferId) { alert('กรุณาเลือกสินค้าของคุณก่อน'); return; }
    try {
      submitBtn.disabled = true;
      await createRequest({ itemId, offerItemId: selectedOfferId, message: msgEl.value || '' });
      close();
      alert('ส่งคำขอแลกเรียบร้อย');
      location.href = '/thammue/public/request.html';
    } catch (e) {
      if (e.message === 'AUTH') onAuthRedirect?.();
      else alert(e.message || 'ส่งคำขอไม่สำเร็จ');
    } finally {
      submitBtn.disabled = false;
    }
  });

  closeBtn?.addEventListener('click', close);
  cancelBtn?.addEventListener('click', close);
  modal?.querySelector('.offer-backdrop')?.addEventListener('click', close);

  return { open, close };
}
