// /js/ex.chat.page.js
(function () {
  const API = {
    listRooms: '/page/backend/ex_chat_list.php', // GET rooms
    msgs: (room_id, after_id) =>
      `/page/backend/ex_chat_messages.php?room_id=${encodeURIComponent(room_id)}${after_id ? `&since_id=${after_id}` : ''}`,
    send: '/page/backend/ex_chat_send.php',      // POST {room_id, body}
    open: '/page/backend/ex_chat_open.php'       // POST {other_user_id, item_id?}
  };

  const $convList = document.getElementById('convList');
  const $msgs     = document.getElementById('chatMsgs');
  const $box      = document.getElementById('msgBox');
  const $send     = document.getElementById('btnSend');
  const $head     = document.getElementById('chatHead');

  let rooms = [];
  let currentRoom = 0;
  let lastId = 0;
  let timer = null;

  const qs    = new URLSearchParams(location.search);
  const qRoom = +(qs.get('chat_id') || qs.get('room_id') || 0);
  const qTo   = +(qs.get('to') || 0);
  const qItem = +(qs.get('item') || 0);

  function esc(s) {
    return String(s).replace(/[&<>"']/g, m => (
      { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]
    ));
  }
  async function jget(url){
    const r = await fetch(url, {credentials:'include', cache:'no-store'});
    const t = await r.text();
    try { return JSON.parse(t); } catch { throw new Error(t); }
  }
  async function jpost(url, fd){
    const r = await fetch(url, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
    const t = await r.text();
    try { return JSON.parse(t); } catch { throw new Error(t); }
  }

  function renderRooms(){
    $convList.innerHTML = '';
    if (!rooms.length){
      $convList.innerHTML = '<li><a class="meta">ยังไม่มีห้องแชท</a></li>';
      return;
    }
    const frag = document.createDocumentFragment();
    rooms.forEach(r=>{
      const li = document.createElement('li');
      li.dataset.id = r.id;
      li.dataset.title = r.title || `แชต #${r.id}`;
      li.innerHTML = `
        <a>
          <span class="title">${esc(r.title || `แชต #${r.id}`)}</span>
          <span class="meta">${esc(r.updated_at || '')}</span>
        </a>`;
      li.addEventListener('click', ()=> openRoom(r.id));
      frag.appendChild(li);
    });
    $convList.appendChild(frag);
    highlightCurrent();
  }
  function highlightCurrent(){
    [...$convList.children].forEach(li =>
      li.classList.toggle('is-active', +li.dataset.id === +currentRoom)
    );
  }
  function setRoomHeaderTitle(room_id){
    const r = rooms.find(x => +x.id === +room_id);
    const title = r?.title || 'กำลังสนทนา';
    if ($head.firstChild) $head.firstChild.textContent = title;
    else $head.textContent = title;
  }

  function renderMessages(list, replace=false){
    if (replace){ $msgs.innerHTML=''; lastId=0; }
    (list||[]).forEach(m=>{
      const isMe = !!(m.is_me || m.me===1);
      const div = document.createElement('div');
      div.className = `msg ${isMe?'me':'you'}`;
      div.innerHTML = `<div class="bubble">${esc(m.body||'')}
        <span class="at">${esc(m.created_at||'')}</span></div>`;
      $msgs.appendChild(div);
      if (m.id && m.id>lastId) lastId = m.id;
    });
    $msgs.scrollTop = $msgs.scrollHeight + 999;
  }

  async function openRoom(room_id){
    if (!room_id) return;
    currentRoom = room_id;
    setRoomHeaderTitle(room_id);
    highlightCurrent();

    try{
      const res = await jget(API.msgs(room_id));
      renderMessages(res.items || [], true);
    }catch(e){
      $msgs.innerHTML = `<div class="hint">โหลดข้อความไม่ได้</div>`;
    }

    if (timer) clearInterval(timer);
    timer = setInterval(async ()=>{
      try{
        const r = await jget(API.msgs(room_id, lastId));
        if (r.items && r.items.length) renderMessages(r.items,false);
      }catch{}
    }, 3000);
  }

  async function ensureOpenFromQuery(){
    // มีหมายเลขห้อง → เปิดเลย
    if (qRoom>0){
      await openRoom(qRoom);
      return;
    }
    // มีผู้รับ (เริ่มจากปุ่ม “ข้อความ” ในหน้าไอเท็ม)
    if (qTo>0){
      const fd = new FormData();
      fd.append('other_user_id', qTo);
      if (qItem>0) fd.append('item_id', qItem);

      const r = await jpost(API.open, fd);
      if (r?.ok && r.room_id){
        await loadRooms();       // refresh list เพื่อมีห้องใหม่
        await openRoom(r.room_id);
        return;
      } else {
        // แสดงข้อความสั้น ๆ ช่วย debug หากเปิดห้องไม่สำเร็จ
        console.warn('open failed', r);
      }
    }
  }

  async function loadRooms(){
    const data = await jget(API.listRooms);
    rooms = data.rooms || data.items || [];
    renderRooms();
  }

  async function send(){
    if (!currentRoom) return;
    const text = ($box.value||'').trim();
    if (!text) return;
    $send.disabled = true;
    try{
      const fd = new FormData();
      fd.append('room_id', currentRoom);
      fd.append('body', text);
      const r = await jpost(API.send, fd);
      if (r?.ok){
        renderMessages([{id:lastId+1, body:text, is_me:true,
          created_at: new Date().toLocaleString()}]);
        $box.value=''; $box.focus();
      } else {
        alert('ส่งไม่ได้: '+(r?.error||'unknown'));
      }
    }catch(e){
      alert('ส่งไม่ได้: '+ (e.message||e));
    }finally{
      $send.disabled = false;
    }
  }

  $send.addEventListener('click', send);
  $box.addEventListener('keydown', e=>{
    if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); send(); }
  });

  // Bootstrap ลำดับชัดเจน: โหลดห้อง → เปิดจากพารามิเตอร์ → ถ้าไม่มี เปิดห้องแรก
  (async ()=>{
    await loadRooms();
    await ensureOpenFromQuery();
    if (!currentRoom && rooms.length){
      openRoom(rooms[0].id);
    }
  })();
})();
