// /js/ex.item.form.js  — safe & match IDs in your HTML
(function () {
  const $id     = document.getElementById('item_id');
  const $title  = document.getElementById('title');
  const $desc   = document.getElementById('description');
  const $thumbI = document.getElementById('thumb');          // <input type=file>
  const $prev   = document.getElementById('thumbPreview');   // <img>
  const $urlBox = document.getElementById('thumbUrlBox');
  const $btnUp  = document.getElementById('uploadBtn');
  const $btn    = document.getElementById('updateBtn');

  // กันพลาด: ถ้า element ใดไม่เจอ ให้หยุดและแจ้ง
  const els = [$id,$title,$desc,$thumbI,$prev,$urlBox,$btnUp,$btn];
  if (els.some(e => !e)) {
    alert('หน้าฟอร์มไม่ครบ: ตรวจสอบ id ของ input ให้ตรงกับสคริปต์ (item_id,title,description,thumb,thumbPreview,thumbUrlBox,uploadBtn,updateBtn)');
    return;
  }

  // ===== อ่านพารามิเตอร์ id =====
  const params = new URLSearchParams(location.search);
  const itemId = Number(params.get('id') || 0);
  if (!itemId) {
    alert('ไม่มีรหัสสินค้าใน URL');
    history.back();
    return;
  }
  $id.value = String(itemId);

  // ===== โหลดข้อมูลเดิม =====
  const GET_URL = `/page/backend/ex_item_get.php?id=${encodeURIComponent(itemId)}`;
  fetch(GET_URL, { credentials:'include', cache:'no-store' })
    .then(r => r.text())
    .then(t => {
      let j = null; try { j = JSON.parse(t); } catch {}
      if (!j?.ok || !j.item) throw new Error(j?.error || 'ไม่พบข้อมูลสินค้า');
      const it = j.item;
      $title.value = it.title || '';
      $desc.value  = it.description || '';
      const imgUrl = it.thumbnail_url || (Array.isArray(it.images) ? it.images[0] : '') || '';
      if (imgUrl) {
        $prev.src = imgUrl;
        $urlBox.textContent = imgUrl;
      } else {
        $prev.removeAttribute('src');
        $urlBox.textContent = '(ยังไม่มีรูปหลัก)';
      }
    })
    .catch(err => alert(err.message || 'โหลดข้อมูลล้มเหลว'));

  // ===== พรีวิวรูปเมื่อเลือกไฟล์ใหม่ =====
  $btnUp.addEventListener('click', () => $thumbI.click());
  $thumbI.addEventListener('change', () => {
    const f = $thumbI.files && $thumbI.files[0];
    if (!f) return;
    const url = URL.createObjectURL(f);
    $prev.src = url;
    $urlBox.textContent = f.name;
  });

  // ===== อัปโหลดไฟล์รูปเฉพาะ (ถ้ามี) -> ได้ URL กลับมา =====
  async function uploadThumbIfNeeded () {
    const f = $thumbI.files && $thumbI.files[0];
    if (!f) return null; // ไม่ได้เลือกไฟล์ใหม่
    const fd = new FormData(); fd.append('file', f);
    const r = await fetch('/page/backend/ex_item_upload.php', {
      method:'POST', body:fd, credentials:'include'
    });
    const j = await r.json().catch(()=>null);
    if (!j?.ok || !j.url) throw new Error(j?.error || 'อัปโหลดรูปไม่สำเร็จ');
    return j.url;
  }

  // ===== ส่งอัปเดตข้อมูล =====
  $btn.addEventListener('click', async () => {
    try {
      $btn.disabled = true;

      const newThumb = await uploadThumbIfNeeded(); // ถ้าอัปโหลดรูปใหม่ จะได้ URL

      const payload = {
        id: itemId,
        title: ($title.value || '').trim(),
        description: ($desc.value || '').trim(),
      };
      if (newThumb) payload.thumbnail_url = newThumb;

      const r = await fetch('/page/backend/ex_item_update.php', {
        method:'POST',
        credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const j = await r.json().catch(()=>null);
      if (!j?.ok) throw new Error(j?.error || 'อัปเดตไม่สำเร็จ');

      alert('อัปเดตเรียบร้อย');
      location.href = `/page/ex_item_view.html?id=${encodeURIComponent(itemId)}`;
    } catch (e) {
      alert(e.message || 'เกิดข้อผิดพลาด');
    } finally {
      $btn.disabled = false;
    }
  });
})();
