
/* /js/ex.item.form.js */
(function(){
  const Q = sel => document.querySelector(sel);
  const img = Q('#thumbPreview');
  const box = Q('#thumbUrlBox');
  const file = Q('#thumb');
  const uploadBtn = Q('#uploadBtn');

  if (file) {
    file.addEventListener('change', ()=>{
      const f = file.files[0]; if(!f) return;
      const url = URL.createObjectURL(f);
      img && (img.src = url);
    });
  }

  async function uploadThumb(){
    const f = file.files[0];
    if (!f) { alert('ยังไม่ได้เลือกไฟล์'); return null; }
    const fd = new FormData();
    fd.append('image', f);
    const res = await fetch('/page/backend/ex_item_upload_image.php', { method:'POST', credentials:'include', body: fd }).then(r=>r.json());
    if (!res.ok) { alert(res.error||'อัปโหลดรูปไม่สำเร็จ'); return null; }
    box.textContent = res.url || '';
    img && (img.src = res.url || img.src);
    return res.url || '';
  }

  uploadBtn && uploadBtn.addEventListener('click', uploadThumb);

  // New item page
  const saveBtn = Q('#saveBtn');
  if (saveBtn){
    saveBtn.addEventListener('click', async ()=>{
      try{
        const payload = {
          title: Q('#title').value.trim(),
          description: Q('#description').value.trim(),
          price: parseFloat(Q('#price').value || '0') || null,
          thumbnail_url: box.textContent.trim() || null
        };
        const res = await fetch('/page/backend/ex_item_create.php', {
          method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(payload)
        }).then(r=>r.json());
        if(!res.ok){ alert(res.error||'บันทึกไม่สำเร็จ'); return; }
        alert('สร้างสินค้า #' + res.id + ' สำเร็จ');
        location.href = '/page/ex_my_items.html';
      }catch(e){ alert(e.message); }
    });
  }

  // Edit item page
  const updateBtn = Q('#updateBtn');
  if (updateBtn){
    const params = new URLSearchParams(location.search);
    const item_id = parseInt(params.get('id')||'0',10);
    if(!item_id){ alert('ไม่พบ item id'); location.href='/page/ex_my_items.html'; return; }

    // load current
    (async function(){
      const res = await fetch('/page/backend/ex_item_get.php?id='+item_id, {credentials:'include'}).then(r=>r.json());
      if(!res.ok){ alert(res.error||'โหลดสินค้าไม่ได้'); return; }
      Q('#item_id').value = res.item.id;
      Q('#title').value = res.item.title || '';
      Q('#description').value = res.item.description || '';
      Q('#price').value = res.item.price || '';
      if (res.item.thumbnail_url){ img && (img.src = res.item.thumbnail_url); box.textContent = res.item.thumbnail_url; }
    })();

    updateBtn.addEventListener('click', async ()=>{
      try{
        const payload = {
          id: item_id,
          title: Q('#title').value.trim(),
          description: Q('#description').value.trim(),
          price: parseFloat(Q('#price').value || '0') || null,
          thumbnail_url: box.textContent.trim() || null
        };
        const res = await fetch('/page/backend/ex_item_update.php', {
          method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(payload)
        }).then(r=>r.json());
        if(!res.ok){ alert(res.error||'อัปเดตไม่สำเร็จ'); return; }
        alert('อัปเดตแล้ว');
        location.href = '/page/ex_my_items.html';
      }catch(e){ alert(e.message); }
    });
  }
})();
