
/* สคริปต์เฉพาะหน้า ex_notifications.html */
(function(){
  async function render(){
    const res = await Ex.exListNoti();
    const ul = document.getElementById('notis');
    if(!res.items || !res.items.length){ ul.innerHTML = '<li class="ex-muted">ไม่มีแจ้งเตือน</li>'; return; }
    res.items.forEach(n=>{
      const li = document.createElement('li');
      li.className = 'ex-noti-item';
      li.innerHTML = `<div><b>${n.title||'-'}</b><div class="ex-muted">${n.body||''}</div></div><div class="ex-noti-time">${n.created_at}</div>`;
      ul.appendChild(li);
    });
  }
  window.addEventListener('DOMContentLoaded', ()=>render().catch(e=>alert(e.message)));
})();
