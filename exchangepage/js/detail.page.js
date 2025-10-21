import { getItem, toggleFavorite } from './detail.api.js';
import { initOfferModal } from './detail.modal.js';

const qs   = new URLSearchParams(location.search);
const id   = parseInt(qs.get('id') || '0', 10);
const $    = (s) => document.getElementById(s);
const flash = $('flash');

function showError(msg){
  const nf = $('notfound');
  nf.style.display = 'block';
  nf.querySelector('.box').textContent = msg || 'ไม่พบสินค้า';
}

function setFavUI(liked){
  const btn = $('favBtn');
  btn.dataset.liked = liked ? '1' : '0';
  btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
  btn.innerHTML = liked ? '❤️ อยู่ในรายการโปรด' : '🤍 รายการโปรด';
}

function onAuthRedirect(){
  location.href = '/thammue/page/login.html?next=' + encodeURIComponent(location.pathname + location.search);
}

async function bootstrap(){
  if (!id) return showError('ลิงก์ไม่ถูกต้อง (ไม่มี id)');

  let it;
  try {
    const raw = await getItem(id);
    const { ok, ...data } = raw;
    it = data;
  } catch (e) {
    return showError(e.message || 'โหลดข้อมูลไม่สำเร็จ');
  }

  // Render
  $('detail').style.display = '';
  $('title').textContent = it.title || '';
  $('cat').textContent   = `หมวด: ${it.category_name || '-'}`;
  $('loc').textContent   = `พื้นที่: ${(it.province || '') + (it.district ? ' · ' + it.district : '') || '-'}`;
  $('desc').textContent  = it.description || '';
  $('ownerName').textContent = it.owner?.name || '-';
  $('refCode').textContent   = it.ref || '-';

  // Gallery
  const images = (Array.isArray(it.images) && it.images.length) ? it.images : (it.cover ? [it.cover] : []);
  const main   = $('mainImg');
  const thumbs = $('thumbs');
  main.src = images[0] || 'https://picsum.photos/800?grayscale';
  thumbs.innerHTML = images.map((src,i)=>`
    <div class="thumb ${i===0?'active':''}" data-src="${src}">
      <img src="${src}" alt="">
    </div>`).join('');
  thumbs.addEventListener('click', e => {
    const t = e.target.closest('.thumb'); if (!t) return;
    thumbs.querySelectorAll('.thumb').forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    main.src = t.dataset.src;
  });

  // Favorite
  setFavUI(!!it.is_favorite);
  $('favBtn')?.addEventListener('click', async () => {
    try {
      const status = await toggleFavorite(it.id);
      setFavUI(status === 'added');
    } catch (e) {
      if (e.message === 'AUTH') onAuthRedirect();
      else alert('ทำรายการโปรดไม่สำเร็จ');
    }
  });

  // Chat
// Chat
if (it.id) {
  $('chatBtn').addEventListener('click', (ev) => {
    ev.preventDefault();
    // ใช้ absolute path กัน <base href="/thammue/public/"> กลืน
    location.href = '/thammue/public/chat.html?with_owner_of=' + it.id;
  });
}


  // Offer Modal
  const modal = initOfferModal({ itemId: it.id, onAuthRedirect });
  $('openOffer')?.addEventListener('click', (e) => { e.preventDefault(); modal.open(); });

  // Flash helper
  function flashMsg(text){
    if (!flash) return;
    flash.textContent = text;
    flash.hidden = !text;
  }
  // (ตอนนี้ flash ใช้กับเคสอื่น ๆ ได้ในอนาคต)
}

bootstrap();
