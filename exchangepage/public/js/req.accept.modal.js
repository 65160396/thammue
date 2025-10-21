// /thammue/public/js/req.accept.modal.js
(function () {
  const API_BASE = '/thammue/api';

  function inject() {
    if (document.getElementById('acceptModal')) return;
    const el = document.createElement('div');
    el.innerHTML = `
<div id="acceptModal" class="ramodal" aria-hidden="true" role="dialog">
  <div class="ra-backdrop"></div>
  <div class="ra-frame" role="document" aria-labelledby="raTitle">
    <button class="ra-x" title="ปิด" type="button">×</button>
    <h3 id="raTitle">ยืนยันการยอมรับคำขอ</h3>

    <div class="ra-body">
      <label class="ra-label">วัน-เวลา นัดหมาย
        <input id="raWhen" type="datetime-local" required>
        <small class="hint">โปรดเลือกเวลาในอนาคต</small>
      </label>
      <label class="ra-label">โน้ตเพิ่มเติม
        <textarea id="raNote" rows="3" placeholder="จุดนัด/แลนด์มาร์ก"></textarea>
      </label>

      <!-- ✅ สรุปข้อมูลผู้ขอแลก (ข้อความล้วน) -->
      <div class="ra-summary">
        <div class="ra-sum-title">ข้อมูลผู้ขอแลก</div>
        <div class="ra-sum-row"><span>สินค้า (ผู้ขอ):</span> <strong id="sumItem">-</strong></div>
        <div class="ra-sum-row"><span>สถานที่ผู้ขอ:</span> <span id="sumPlace">จ.- อ.- ต.-</span></div>
        <div class="ra-sum-row"><span>โน้ตผู้ขอ:</span> <span id="sumNote">-</span></div>
      </div>
    </div>

    <div class="ra-actions">
      <button class="ra-primary" id="raSubmit" type="button">ยืนยัน</button>
    </div>
  </div>
</div>`;
    document.body.appendChild(el);

    // bind
    const modal = document.getElementById('acceptModal');
    const x = modal.querySelector('.ra-x');
    const backdrop = modal.querySelector('.ra-backdrop');
    const submit = modal.querySelector('#raSubmit');

    const close = () => {
      modal.setAttribute('aria-hidden', 'true');
      modal.dataset.reqId = '';
      document.body.classList.remove('no-scroll');
    };
    x.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    modal.querySelector('.ra-frame').addEventListener('click', e => e.stopPropagation());

    submit.addEventListener('click', async () => {
      const reqId = Number(modal.dataset.reqId || 0);
      const when  = modal.querySelector('#raWhen')?.value || '';
      const note  = modal.querySelector('#raNote')?.value || '';
      if (!reqId) { alert('ไม่พบรหัสคำขอ'); return; }
      if (!when)  { alert('โปรดเลือกวัน-เวลา'); return; }
      submit.disabled = true; submit.textContent = 'กำลังยืนยัน...';
      try {
        const fd = new FormData();
        fd.append('id', String(reqId));
        fd.append('meeting_at', when);
        fd.append('my_note', note);

        const res = await fetch(`${API_BASE}/requests/accept_with_meeting.php`, {
          method: 'POST', body: fd, credentials: 'include'
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || `HTTP ${res.status}`);

        window.location.href = j.chat_url || `/thammue/public/chat.html?c=${j.conv_id}`;
      } catch (e) {
        alert('ผิดพลาด: ' + (e.message || e));
      } finally {
        submit.disabled = false; submit.textContent = 'ยืนยัน';
      }
    });
  }

  // โหลดสรุปผู้ขอ + ใส่ลงในโมดัล
  async function fillSummary(reqId) {
    const modal = document.getElementById('acceptModal');
    const sumItem  = modal.querySelector('#sumItem');
    const sumPlace = modal.querySelector('#sumPlace');
    const sumNote  = modal.querySelector('#sumNote');

    sumItem.textContent  = 'กำลังโหลด...';
    sumPlace.textContent = 'กำลังโหลด...';
    sumNote.textContent  = 'กำลังโหลด...';

    try {
      const r = await fetch(`${API_BASE}/requests/detail_for_modal.php?id=${encodeURIComponent(reqId)}`, {
        cache: 'no-store', credentials: 'include'
      });
      const d = await r.json().catch(() => ({ ok:false }));
      if (!d.ok) throw new Error(d.error || 'load_fail');

      const loc = d.requester_location || {};
      const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
      sumItem.textContent = d.requester_item_title || '-';

      const province    = loc.province    || '-';
      const district    = loc.district    || '-';
      const subdistrict = loc.subdistrict || '-';
      const detail      = loc.place_detail ? ` (${loc.place_detail})` : '';
      sumPlace.textContent = `จ.${province} อ.${district} ต.${subdistrict}${detail}`;

      sumNote.textContent = d.requester_note ? d.requester_note : 'ไม่มีโน้ต';
    } catch (e) {
      sumItem.textContent  = '-';
      sumPlace.textContent = 'จ.- อ.- ต.-';
      sumNote.textContent  = 'ไม่มีโน้ต';
    }
  }

  function openModal(id) {
    inject();
    const modal = document.getElementById('acceptModal');
    modal.dataset.reqId = String(id);
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');

    const dt = modal.querySelector('#raWhen');
    const t  = new Date(Date.now() + 5 * 60 * 1000);
    dt.min   = t.toISOString().slice(0, 16);
    dt.value = dt.min;
    dt.focus();

    fillSummary(id);
  }

  // Intercept ปุ่มยอมรับ (capture)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-act="accept"][data-id]');
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    e.stopPropagation();
    openModal(btn.getAttribute('data-id'));
  }, true);
})();
