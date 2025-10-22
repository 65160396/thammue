
/* /js/ex.chat.page.js */
(async function(){
  // แบ็กเอนด์สมมติ: /page/backend/ex_chat_list.php (rooms, messages) / ex_chat_send.php
  const roomListEl = document.getElementById('roomList');
  const msgEl = document.getElementById('msg');
  const sendBtn = document.getElementById('send');
  const messagesEl = document.getElementById('messages');
  const roomTitleEl = document.getElementById('roomTitle');
  let currentRoomId = 0;

  async function loadRooms(){
    const res = await fetch('/page/backend/ex_chat_list.php', {credentials:'include'}).then(r=>r.json());
    if(!res.ok){ throw new Error(res.error||'โหลดห้องสนทนาไม่ได้'); }
    roomListEl.innerHTML='';
    (res.rooms||[]).forEach(r=>{
      const li = document.createElement('li');
      li.innerHTML = `<div><b>#${r.id}</b> ${r.title||'-'}</div><div class="ex-muted">${r.updated_at||''}</div>`;
      li.style.cursor='pointer';
      li.addEventListener('click', ()=>openRoom(r.id, r.title||('ห้อง #'+r.id)));
      roomListEl.appendChild(li);
    });
    if(!roomListEl.children.length){
      roomListEl.innerHTML = '<li class="ex-muted">ยังไม่มีห้องสนทนา</li>';
    }
  }

  async function openRoom(roomId, title){
    currentRoomId = roomId;
    roomTitleEl.textContent = title;
    await refreshMessages();
  }

  async function refreshMessages(){
    if(!currentRoomId){ messagesEl.innerHTML=''; return; }
    const url = '/page/backend/ex_chat_list.php?room_id='+currentRoomId;
    const res = await fetch(url, {credentials:'include'}).then(r=>r.json());
    if(!res.ok){ throw new Error(res.error||'โหลดข้อความไม่ได้'); }
    messagesEl.innerHTML = '';
    (res.messages||[]).forEach(m=>{
      const div = document.createElement('div');
      div.className = 'ex-chat-bubble' + (m.is_me? ' me':'');
      div.innerHTML = `<div>${m.body||''}</div><div class="ex-muted" style="font-size:12px">${m.created_at||''}</div>`;
      messagesEl.appendChild(div);
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  async function send(){
    if(!currentRoomId) return;
    const body = (msgEl.value||'').trim();
    if(!body) return;
    msgEl.value='';
    const res = await fetch('/page/backend/ex_chat_send.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'include',
      body: JSON.stringify({room_id: currentRoomId, body})
    }).then(r=>r.json());
    if(!res.ok){ alert(res.error||'ส่งข้อความไม่สำเร็จ'); return; }
    await refreshMessages();
  }

  sendBtn.addEventListener('click', send);
  msgEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter') send(); });

  try{
    await loadRooms();
  }catch(e){ alert(e.message); }
})();
