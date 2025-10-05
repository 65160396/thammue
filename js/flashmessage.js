(function(){
var sp = new URLSearchParams(location.search);
var type = sp.get('type'); // success | error
var msg = sp.get('msg');
var SHOW_MS = 2500, FADE_MS = 350;


if (!msg) return;
var box = document.getElementById('flash');
if (!box) return;


box.textContent = decodeURIComponent(msg);
box.style.display = 'block';
box.style.border = '1px solid ' + (type === 'success' ? '#28a745' : '#dc3545');
box.style.background = type === 'success' ? '#e8fff0' : '#fff1f1';
box.style.color = type === 'success' ? '#19692c' : '#7a1f25';


requestAnimationFrame(function(){ box.classList.add('show'); });
setTimeout(function(){
box.classList.add('hide');
setTimeout(function(){ box.style.display='none'; box.classList.remove('show','hide'); }, FADE_MS);
}, SHOW_MS);


// เอาพารามิเตอร์ออกจาก URL
if (history && history.replaceState) {
history.replaceState({}, '', location.pathname);
}
})();