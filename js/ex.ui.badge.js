
/* อัปเดต badge จำนวนแจ้งเตือนในเฮดเดอร์ (เช่น <span id="exNotiBadge"></span>) */
(function(){
  async function refreshBadge(){
    try{
      const res = await Ex.exListNoti();
      const count = (res.items||[]).filter(x=>!x.is_read).length;
      const el = document.getElementById('exNotiBadge');
      if (el) el.textContent = count>0 ? count : '';
    }catch(e){ /* เงียบ */ }
  }
  window.addEventListener('DOMContentLoaded', refreshBadge);
  window.ExUI = { refreshBadge };
})();
