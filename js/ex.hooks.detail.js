
/* ฮุคสำหรับหน้า detail เดิม: ใส่ปุ่ม 'ขอแลก' ให้ item ที่ไม่ใช่ของเรา */
(function(){
  async function attach(buttonSelector, getIds){
    const btn = document.querySelector(buttonSelector);
    if(!btn) return;
    btn.addEventListener('click', async ()=>{
      try{
        const {requested_item_id, offered_item_id} = await getIds();
        const message = prompt('ข้อความถึงเจ้าของสินค้า (ถ้ามี)') || '';
        const res = await Ex.exCreateRequest({requested_item_id, offered_item_id, message});
        alert('ส่งคำขอแล้ว #' + res.request_id);
      }catch(e){ alert('ผิดพลาด: '+e.message); }
    });
  }
  // ให้หน้า detail เรียกใช้: ExHooks.attach('#btnEx', ()=>({requested_item_id: ID1, offered_item_id: ID2}))
  window.ExHooks = { attach };
})();
