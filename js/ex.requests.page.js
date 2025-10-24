
(function () {
  const API = {
    list:   '/page/backend/ex_requests_list.php',
    decide: '/page/backend/ex_request_decide.php'
  };

  // ปุ่มใน card จะเรียกใช้ openModal(r.id) ที่คุณมีอยู่แล้ว
  function card(r){
    const c = document.createElement('div');
    c.className = 'card';
    const placeLine = r.preferred_place ? `<div class="muted" style="margin-top:4px">สถานที่ที่ผู้ขอเสนอ: ${r.preferred_place}</div>` : '';
    const noteLine  = r.note ? `<div class="muted" style="margin-top:2px">โน้ตจากผู้ขอ: ${r.note}</div>` : '';

    c.innerHTML = `
      <div class="row">
        <img class="thumb" src="${r.off_thumb||''}" alt="">
        <div>
          <div><b>${r.off_title||'-'}</b> <span class="muted">ขอแลกกับ</span></div>
          <div class="muted">${r.req_title||'-'}</div>
          <div class="muted" style="font-size:12px">เมื่อ: ${r.created_at}</div>
          <div class="muted" style="font-size:12px">สถานะ: ${r.status}</div>
          ${placeLine}
          ${noteLine}
        </div>
      </div>
    `;

    if (r.status === 'pending'){
      const act = document.createElement('div');
      act.className = 'actions';
      const ok = Object.assign(document.createElement('button'), {className:'btn primary', type:'button', textContent:'ยอมรับ'});
      const no = Object.assign(document.createElement('button'), {className:'btn', type:'button', textContent:'ปฏิเสธ'});

      ok.onclick = () => window.openModal && openModal(r.id);
      no.onclick = async () => {
        if (!confirm('ยืนยันปฏิเสธคำขอนี้?')) return;
        const fd = new FormData();
        fd.append('request_id', r.id);
        fd.append('action', 'decline');
        const res = await fetch(API.decide, {method:'POST', body:fd, credentials:'include'});
        const data = await res.json().catch(()=>null);
        if (data?.ok){ alert('ปฏิเสธแล้ว'); location.reload(); }
        else alert('ทำรายการไม่สำเร็จ');
      };
      act.append(ok, no);
      c.appendChild(act);
    }
    return c;
  }

  async function render(){
    let data;
    try{
      const r = await fetch(API.list, {credentials:'include', cache:'no-store'});
      data = await r.json();
    }catch(e){
      const empty = document.getElementById('empty');
      if (empty) empty.textContent = 'โหลดข้อมูลไม่สำเร็จ';
      console.error(e);
      return;
    }

    const list  = document.getElementById('list');
    const empty = document.getElementById('empty');
    if (!list) return;

    const arr = data?.incoming || [];
    if (!arr.length){
      if (empty) empty.textContent = 'ไม่มีคำขอเข้ามา';
      return;
    }
    const fr = document.createDocumentFragment();
    arr.forEach(r => fr.appendChild(card(r)));
    list.innerHTML = '';
    list.appendChild(fr);
  }

  // ให้รันหลัง partial header ถูก include แล้ว (กัน timing ชนกับ header)
  document.addEventListener('ex:partials:ready', render);
})();

