
/* สคริปต์เฉพาะหน้า ex_requests.html */
(function(){
  async function render(){
    const data = await Ex.exListMyRequests();
    const incoming = document.getElementById('incoming');
    const outgoing = document.getElementById('outgoing');

    function setStatusClass(badge, status){
      const map = {pending:'ex-req-status-pending',accepted:'ex-req-status-accepted',declined:'ex-req-status-declined',cancelled:'ex-req-status-cancelled'};
      const cls = map[status] || '';
      if (cls) badge.classList.add(cls);
    }

    function li(r, side){
      const li = document.createElement('li');
      li.className = 'ex-req-item';
      li.innerHTML = `
        <div>
          <div><b>#${r.id}</b> <span class="ex-badge">${r.status}</span></div>
          <div class="ex-req-meta">ขอ: ${r.requested_item_id} | เสนอ: ${r.offered_item_id}</div>
          <div class="ex-req-meta">${r.created_at}</div>
        </div>
        <div class="ex-actions">
          ${side==='incoming' && r.status==='pending' ? '<button class="ex-btn" data-act="accept">ยอมรับ</button><button class="ex-btn secondary" data-act="decline">ปฏิเสธ</button>' : ''}
          ${side==='outgoing' && r.status==='pending' ? '<button class="ex-btn secondary" data-act="cancel">ยกเลิก</button>' : ''}
        </div>
      `;
      setStatusClass(li.querySelector('.ex-badge'), r.status);
      li.addEventListener('click', async (ev)=>{
        const btn = ev.target.closest('button'); if(!btn) return;
        try{
          if(btn.dataset.act==='accept'){ await Ex.exAccept(r.id); }
          if(btn.dataset.act==='decline'){ await Ex.exDecline(r.id); }
          if(btn.dataset.act==='cancel'){ await Ex.exCancel(r.id); }
          location.reload();
        }catch(e){ alert('ผิดพลาด: '+e.message); }
      });
      return li;
    }

    (data.incoming||[]).forEach(r=>incoming.appendChild(li(r,'incoming')));
    (data.outgoing||[]).forEach(r=>outgoing.appendChild(li(r,'outgoing')));
    if(!incoming.children.length) incoming.innerHTML = '<li class="ex-muted">ว่าง</li>';
    if(!outgoing.children.length) outgoing.innerHTML = '<li class="ex-muted">ว่าง</li>';
  }
  window.addEventListener('DOMContentLoaded', ()=>render().catch(e=>alert(e.message)));
})();
