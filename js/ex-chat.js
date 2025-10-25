// /page/js/ex-chat.js
(function(){
  if (!window.EX_ITEM_ID) { console.warn('EX_ITEM_ID is required'); return; }

  const box = document.querySelector('#ex-chat');
  const msgs = box.querySelector('#ex-chat-msgs');
  const title = box.querySelector('#ex-chat-title');
  const input = box.querySelector('#ex-chat-input');
  const btn = box.querySelector('#ex-chat-send');

  let ROOM_ID = 0;
  let since_id = 0;

  const esc = s => s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function addMessage(m){
    const isMe = (m.user_id === (window.ME_ID||0));
    const wrap = document.createElement('div');
    wrap.style.margin = '4px 0';
    wrap.innerHTML = `
      <div style="display:inline-block;max-width:88%;padding:6px 10px;border-radius:8px;${isMe?'background:#dcf8c6;float:right':'background:#f5f5f5;float:left'}">
        <div style="font-size:12px;color:#777;margin-bottom:2px">${esc(m.user_name || ('User#'+m.user_id))}</div>
        <div>${esc(m.body)}</div>
        <div style="font-size:11px;color:#999;margin-top:2px">${m.created_at}</div>
      </div><div style="clear:both"></div>
    `;
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
    since_id = Math.max(since_id, m.id);
  }

  async function ensureRoom(){
    const r = await fetch('/page/backend/ex_chat.php?action=ensure_room', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ ex_item_id: window.EX_ITEM_ID })
    }).then(r=>r.json());
    if (!r.ok) { msgs.innerHTML = 'เปิดห้องไม่ได้: '+r.error; return; }
    ROOM_ID = r.room_id;
    title.textContent = 'แชทแลกเปลี่ยน: ' + (r.title || ('EX #'+window.EX_ITEM_ID));
  }

  async function poll(){
    if (!ROOM_ID) return setTimeout(poll, 500);
    try {
      const r = await fetch(`/page/backend/ex_chat.php?action=fetch&room_id=${ROOM_ID}&since_id=${since_id}`).then(r=>r.json());
      if (r.ok && Array.isArray(r.messages)) r.messages.forEach(addMessage);
    } catch(e) {}
    setTimeout(poll, 300);
  }

  async function send(){
    const text = input.value.trim();
    if (!text || !ROOM_ID) return;
    input.value = '';
    await fetch('/page/backend/ex_chat.php?action=send', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ room_id: ROOM_ID, body: text })
    });
  }

  btn.addEventListener('click', send);
  input.addEventListener('keydown', e => { if (e.key==='Enter') send(); });

  ensureRoom().then(poll);
})();
