
/* /js/ex.my.items.page.js */
(async function(){
  const tbody = document.getElementById('myItemsBody');
  async function load(){
    const res = await fetch('/page/backend/ex_item_list_my.php', {credentials:'include'}).then(r=>r.json());
    if(!res.ok){ alert(res.error||'โหลดสินค้าไม่ได้'); return; }
    tbody.innerHTML = '';
    (res.items||[]).forEach(it=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="padding:8px">#${it.id}</td>
        <td style="padding:8px">${it.thumbnail_url ? '<img src="'+it.thumbnail_url+'" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #eee;">':''}</td>
        <td style="padding:8px">${it.title||'-'}</td>
        <td style="padding:8px">${it.price ?? '-'}</td>
        <td style="padding:8px">${it.updated_at||''}</td>
        <td style="padding:8px">
          <a class="ex-btn secondary" href="/page/ex_item_edit.html?id=${it.id}">แก้ไข</a>
          <a class="ex-btn" href="/page/ex_detail.html?id=${it.id}" target="_blank">ดูหน้า</a>
        </td>
      `;
      tbody.appendChild(tr);
    });
    if(!tbody.children.length){
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="6" style="padding:12px" class="ex-muted">ยังไม่มีสินค้า</td>';
      tbody.appendChild(tr);
    }
  }
  await load();
})();
