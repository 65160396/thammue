// /js/ex.item.view.js  (v2) — โหลดชื่อเจ้าของจาก backend ทุกครั้ง
(function(){
  const qs = new URLSearchParams(location.search);
  const itemId = qs.get('id');

  const API = {
    getItem     : id => `/page/backend/ex_item_get.php?id=${encodeURIComponent(id)}`,
    getOwner    : id => `/page/backend/ex_item_owner.php?id=${encodeURIComponent(id)}`, // <- ใช้ไฟล์นี้เสมอ
    listMine    : `/page/backend/ex_list_my_items.php`,
    createReq   : `/page/backend/ex_request_create.php`,
    badge       : `/page/backend/ex_badge_counts.php`,
    favStatus   : id => `/page/backend/ex_favorite_status.php?item_id=${encodeURIComponent(id)}`,
    favToggle   : `/page/backend/ex_favorite_toggle.php`,
  };

  const $notfound   = document.getElementById('notfound');
  const $detail     = document.getElementById('detail');
  const $mainImg    = document.getElementById('mainImg');
  const $thumbs     = document.getElementById('thumbs');
  const $title      = document.getElementById('title');
  const $cat        = document.getElementById('cat');
  const $loc        = document.getElementById('loc');
  const $pickupLine = document.getElementById('pickupLine');
  const $desc       = document.getElementById('desc');
  const $owner      = document.getElementById('ownerName');
  const $ref        = document.getElementById('refCode');
  const $favBtn     = document.getElementById('favBtn');
  const $favIcon    = document.getElementById('favIcon');
  const $favText    = document.getElementById('favText');
  const $flash      = document.getElementById('flash');
  const $chatBtn    = document.getElementById('chatBtn');
  const $openOffer  = document.getElementById('openOffer');
  const $ownBanner  = document.getElementById('ownBanner');

  // modal
  const $mBackdrop  = document.getElementById('offerBackdrop');
  const $mModal     = document.getElementById('offerModal');
  const $mClose     = document.getElementById('offerClose');
  const $mGrid      = document.getElementById('myItemsGrid');
  const $mHint      = document.getElementById('myItemsHint');
  const $mNote      = document.getElementById('offerNote');
  const $mSubmit    = document.getElementById('offerSubmit');

  if (!itemId) { $notfound.style.display = ''; return; }

  // -------- owner loader (ใหม่) --------
 // inside /js/ex.item.view.js
async function loadOwnerName() {
  try {
    $owner.textContent = 'กำลังโหลด…';
    const res = await fetch(`/page/backend/ex_item_owner.php?id=${encodeURIComponent(itemId)}`, {
      credentials:'include', cache:'no-store'
    });
    const txt = await res.text();
    let j = null;
    try { j = JSON.parse(txt); } catch { console.warn('owner JSON parse fail:', txt); }
    if (j?.ok && j.owner_name) {
      $owner.textContent = j.owner_name;
    } else if (j?.owner_id) {
      $owner.textContent = 'ผู้ใช้ #'+ j.owner_id;
    } else {
      $owner.textContent = 'ผู้ใช้';
    }
  } catch (e) {
    console.error('owner fetch error:', e);
    $owner.textContent = 'ผู้ใช้';
  }
}


  // โหลดรายละเอียดสินค้า
  fetch(API.getItem(itemId), { cache:'no-store', credentials:'include' })
    .then(async r => {
      const raw = await r.text();
      try {
        const d = JSON.parse(raw);
        if (!d.ok || !d.item) throw new Error(d.error || 'no item');
        const it = d.item;

        $detail.style.display = '';
        $title.textContent = it.title || '—';
        $cat.textContent   = `หมวด: ${it.category_name || '-'}`;
        const area = [it.addr_province, it.addr_subdistrict].filter(Boolean).join(' · ');
        $loc.textContent = `พื้นที่: ${area || '-'}`;

        // สถานที่นัดรับ
        const pickText = (it.place_detail || '').trim();
        $pickupLine.style.display = pickText ? '' : 'none';
        if (pickText) $pickupLine.textContent = `สถานที่นัดรับ: ${pickText}`;

        $desc.textContent  = it.description || '-';
        $ref.textContent   = it.ref_code || `EX${String(it.id||'').padStart(6,'0')}`;

        // รูป
        const imgs = [];
        if (it.thumbnail_url) imgs.push(it.thumbnail_url);
        if (Array.isArray(it.images)) imgs.push(...it.images);
        const uniq = [...new Set(imgs.filter(Boolean))];
        if (uniq.length) {
          $mainImg.src = uniq[0];
          renderThumbs(uniq);
        }

        // เจ้าของสินค้า: ถ้า API หลักส่งมาก็ใช้ได้เลย, แต่เราจะเรียก endpoint ใหม่ทับเพื่อความชัวร์
        if (it.owner_name) $owner.textContent = it.owner_name;
        // โหลดจาก backend ใหม่เสมอ (สำหรับเคสที่ it ไม่มี owner_name / schema ต่าง)
        loadOwnerName();

        if (it.is_owner) {
          markOwnerMode();
        } else if (it.id) {
          $chatBtn.href = `/page/storepage/exchat.html?with_item_id=${encodeURIComponent(it.id)}`;
          initFavoriteState();
        }
      } catch(e) {
        console.error('getItem error:', e, 'raw=', raw);
        $notfound.style.display = '';
      }
    })
    .catch(() => { $notfound.style.display = ''; });

  async function initFavoriteState(){
    try{
      const r = await fetch(API.favStatus(itemId), {credentials:'include', cache:'no-store'});
    const d = await r.json();
      if(d.ok){ setFavState(!!d.is_favorite); }
    }catch(e){ console.warn('fav status', e); }
  }

  function renderThumbs(arr){
    $thumbs.innerHTML = '';
    arr.forEach((src,i)=>{
      const b = document.createElement('button');
      b.className = 'thumb' + (i===0 ? ' active':'' );
      b.type='button';
      b.onclick = () => {
        [...$thumbs.children].forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        $mainImg.src = src;
      };
      const im = document.createElement('img');
      im.src = src; im.alt='ภาพสินค้า';
      b.appendChild(im);
      $thumbs.appendChild(b);
    });
  }

  function setFavState(on){
    $favBtn.classList.toggle('fav-on', on);
    $favBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    $favIcon.textContent = on ? '💜' : '🤍';
    $favText.textContent = on ? 'ลบจากโปรด' : 'รายการโปรด';
  }

  $favBtn.addEventListener('click', async () => {
    try{
      $favBtn.disabled = true;
      const r = await fetch(API.favToggle, {
        method:'POST',
        credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({ item_id: itemId }).toString()
      });
      const d = await r.json().catch(()=>null);
      if (d?.ok) {
        setFavState(Boolean(d.is_favorite));
        flash(d.is_favorite ? 'เพิ่มในรายการโปรดแล้ว' : 'นำออกจากรายการโปรดแล้ว');
      } else {
        flash(d?.error || 'ทำรายการไม่สำเร็จ', true);
      }
    }catch{
      flash('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว', true);
    }finally{
      $favBtn.disabled = false;
    }
  });

  function markOwnerMode(){
    $ownBanner.hidden = false;
    $favBtn.setAttribute('disabled', 'true');
    $chatBtn.setAttribute('disabled', 'true'); $chatBtn.removeAttribute('href');
    $openOffer.setAttribute('disabled', 'true');
    $favBtn.title = 'ไม่สามารถเพิ่มรายการโปรดให้สินค้าของตัวเองได้';
    $chatBtn.title = 'นี่คือสินค้าของคุณ';
    $openOffer.title = 'ไม่สามารถแลกเปลี่ยนสินค้ากับตัวเองได้';
  }

  function flash(msg, error=false){
    $flash.textContent = msg;
    $flash.style.background = error ? '#fdecec' : '#e8f7ef';
    $flash.style.color = error ? '#7f1d1d' : '#14532d';
    $flash.style.borderColor = error ? '#fecaca' : '#bbf7d0';
    $flash.hidden = false;
    setTimeout(()=>{ $flash.hidden = true; }, 2200);
  }

  /* ===== Modal logic ===== */
  function openOfferModal(){ $mBackdrop.classList.add('open'); $mModal.classList.add('open'); }
  function closeOfferModal(){ $mBackdrop.classList.remove('open'); $mModal.classList.remove('open'); }
  $mClose.addEventListener('click', closeOfferModal);
  $mBackdrop.addEventListener('click', closeOfferModal);

  $openOffer.addEventListener('click', async () => {
    $mGrid.innerHTML = '';
    $mHint.textContent = 'กำลังโหลดสินค้าของฉัน…';
    openOfferModal();
    try{
      const r = await fetch(API.listMine, {credentials:'include', cache:'no-store'});
      const d = await r.json();
      if (!d?.ok){ $mHint.textContent = 'โหลดไม่สำเร็จ'; return; }
      if (!Array.isArray(d.items) || d.items.length===0){
        $mHint.textContent = 'คุณยังไม่มีสินค้า กรุณาอัปโหลดก่อน';
        return;
      }
      $mHint.textContent = '';
      const fr = document.createDocumentFragment();
      d.items.forEach(it=>{
        const art = document.createElement('article');
        art.className='my-card';
        art.innerHTML = `
          <div class="my-thumb" style="background-image:url('${it.thumbnail_url||''}')"></div>
          <div class="my-body">
            <div style="font-weight:700">${escapeHtml(it.title||'-')}</div>
            <label class="radio-row">
              <input type="radio" name="offerItem" value="${it.id}">
              ใช้ชิ้นนี้ในการแลก
            </label>
          </div>
        `;
        fr.appendChild(art);
      });
      $mGrid.appendChild(fr);
    }catch(e){
      $mHint.textContent = 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้';
    }
  });

  $mSubmit.addEventListener('click', async ()=>{
    const chosen = document.querySelector('input[name="offerItem"]:checked');
    if(!chosen){ alert('กรุณาเลือกสินค้าที่จะใช้แลก'); return; }
    const fd = new FormData();
    fd.append('target_item_id', itemId);
    fd.append('offer_item_id', chosen.value);
    fd.append('note', $mNote.value||'');

    $mSubmit.disabled = true;
    try{
      const r = await fetch(API.createReq, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
      const raw = await r.text(); let d;
      try{ d = JSON.parse(raw); }catch{ alert('ส่งไม่สำเร็จ: '+raw.slice(0,120)); return; }
      if(!d.ok){ alert('ส่งไม่สำเร็จ: '+(d.error||'unknown')); return; }
      closeOfferModal();
      flash('ส่งคำขอแล้ว');
      refreshBadges();
    }finally{ $mSubmit.disabled = false; }
  });

  async function refreshBadges(){
    try{
      const r = await fetch(API.badge, {credentials:'include', cache:'no-store'});
      const b = await r.json();
      const el = document.getElementById('reqBadge');
      const el2= document.getElementById('reqBadgeMobile');
      if(el){ el.hidden = !b.incoming_requests; el.textContent = b.incoming_requests||''; }
      if(el2){ el2.hidden = !b.incoming_requests; el2.textContent = b.incoming_requests||''; }
    }catch{}
  }

  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
})();
