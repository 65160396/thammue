// /js/ex.chat.page.js
(function(){
  const API = {
    list   : '/page/backend/ex_chat_list.php',                                 // GET
    msgs   : (chat_id, after_id) => `/page/backend/ex_chat_messages.php?chat_id=${encodeURIComponent(chat_id)}${after_id?`&after_id=${after_id}`:''}`,
    send   : '/page/backend/ex_chat_send.php',                                 // POST {chat_id, body}
  };

  const $roomList = document.getElementById('roomList');
  const $title    = document.getElementById('roomTitle');
  const $msgs     = document.getElementById('messages');
  const $input    = document.getElementById('msg');
  const $send     = document.getElementById('send');

  let rooms = [];
  let current = null;         // {id, name, avatar?}
  let pollTimer = null;
  let lastMsgId = 0;

  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}

  async function apiGet(url){
    const r = await fetch(url, {credentials:'include', cache:'no-store'});
    const t = await r.text();
    try{ return JSON.parse(t); }catch(e){ throw new Error(t.slice(0,120)); }
  }
  async function apiPost(url, fd){
    const r = await fetch(url, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
    const t = await r.text();
    try{ return JSON.parse(t); }catch(e){ throw new Error(t.slice(0,120)); }
  }

  function roomLi(r){
    const li = document.createElement('li');
    li.dataset.id = r.id;
    const ava = document.createElement('img');
    ava.className = 'room-avatar';
    ava.src = r.avatar_url || '/img/sample/placeholder.png';
    ava.alt = '';
    const meta = document.createElement('div');
    meta.className = 'room-meta';
    meta.innerHTML = `
      <div class="room-name">${escapeHtml(r.name||'ห้องสนทนา')}</div>
      <div class="room-last">${escapeHtml(r.last_message||'—')}</div>
    `;
    li.appendChild(ava); li.appendChild(meta);
    li.addEventListener('click', ()=> openRoom(r.id));
    return li;
  }

  function renderRooms(){
    $roomList.innerHTML = '';
    if (!rooms.length){ $roomList.innerHTML = '<li class="ex-muted">ยังไม่มีห้องแชต</li>'; return; }
    const fr = document.createDocumentFragment();
    rooms.forEach(r => fr.appendChild(roomLi(r)));
    $roomList.appendChild(fr);
    // ไฮไลต์ห้องปัจจุบัน
    if (current){
      [...$roomList.children].forEach(li => li.classList.toggle('active', String(li.dataset.id)===String(current.id)));
    }
  }

  function msgBubble(m){
    const div = document.createElement('div');
    const who = m.type==='system' ? 'sys' : (m.is_me ? 'me' : 'other');
    div.className = `msg ${who}`;
    const body = escapeHtml(m.body||'');
    div.innerHTML = `${body}<span class="ts">${m.created_at||''}</span>`;
    return div;
  }

  function renderMessages(list, replace=false){
    if (replace){ $msgs.innerHTML = ''; lastMsgId = 0; }
    if (!Array.isArray(list) || !list.length) return;
    const fr = document.createDocumentFragment();
    list.forEach(m => {
      fr.appendChild(msgBubble(m));
      if (m.id && m.id > lastMsgId) lastMsgId = m.id;
    });
    $msgs.appendChild(fr);
    $msgs.scrollTop = $msgs.scrollHeight + 9999;
  }

  async function openRoom(chat_id){
    if (!chat_id) return;
    try{
      current = rooms.find(r => String(r.id)===String(chat_id)) || {id: chat_id, name:'ห้องสนทนา'};
      $title.textContent = current.name || 'ห้องสนทนา';
      // active ใน sidebar
      [...$roomList.children].forEach(li => li.classList.toggle('active', String(li.dataset.id)===String(chat_id)));
      // โหลดข้อความแรก
      const res = await apiGet(API.msgs(chat_id));
      renderMessages(res.items || [], true);
      // เริ่ม polling
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(async ()=>{
        try{
          const r = await apiGet(API.msgs(chat_id, lastMsgId));
          if (r.items && r.items.length) renderMessages(r.items);
        }catch(e){ /* เงียบ ๆ */ }
      }, 4000);
    }catch(e){
      $msgs.innerHTML = `<div class="msg sys">โหลดข้อความไม่ได้<br>${escapeHtml(e.message||'')}</div>`;
    }
  }

  async function loadRoomsAndMaybeOpen(){
    try{
      const data = await apiGet(API.list);
      rooms = data.items || [];
      renderRooms();

      // เลือกห้องอัตโนมัติจาก query ?chat_id= หรือเปิดห้องแรก
      const qs = new URLSearchParams(location.search);
      const want = qs.get('chat_id');
      if (want)      openRoom(want);
      else if (rooms.length) openRoom(rooms[0].id);
    }catch(e){
      $roomList.innerHTML = `<li class="ex-muted">โหลดรายการห้องไม่ได้</li>`;
    }
  }

  async function send(){
    if (!current?.id) return;
    const text = ($input.value||'').trim();
    if (!text) return;
    $send.disabled = true;
    try{
      const fd = new FormData();
      fd.append('chat_id', current.id);
      fd.append('body', text);
      const r = await apiPost(API.send, fd);
      if (r?.ok){
        // แสดงของเราเลย (optimistic)
        renderMessages([{ id: (lastMsgId||0)+1, body: text, is_me: true, type:'text', created_at: new Date().toLocaleString() }]);
        $input.value = '';
      }else{
        throw new Error(r?.error || 'send_fail');
      }
    }catch(e){
      alert('ส่งไม่ได้: '+ (e.message||e));
    }finally{
      $send.disabled = false;
      $input.focus();
    }
  }

  $send.addEventListener('click', send);
  $input.addEventListener('keydown', e=>{
    if (e.key==='Enter' && !e.shiftKey){
      e.preventDefault();
      send();
    }
  });

  loadRoomsAndMaybeOpen();
})();
