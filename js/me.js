/*หน้าที่: ดึงสถานะผู้ใช้จาก /page/backend/me.php แล้วแคชไว้ใช้งานร่วมกัน*/
(function (w) {
let cache = null;
async function _fetchMe() {
try {
const r = await fetch('/page/backend/me.php', { cache: 'no-store' });
return r.ok ? await r.json() : { ok:false };
} catch { return { ok:false }; }
}


w.Me = {
/**
* ดึงข้อมูล me.php (มีแคชในหน้านี้)
* @param {boolean} force - true = ล้างแคชแล้วดึงใหม่
*/
async get(force = false) {
if (!force && cache) return cache;
cache = await _fetchMe();
// เผื่อสคริปต์อื่นอยากเข้าถึง user ตรงๆ
w.CURRENT_USER = cache.ok ? (cache.user || null) : null;
return cache;
},
/** เคลียร์แคช (หลัง logout หรือเปลี่ยนบัญชี) */
clear() { cache = null; w.CURRENT_USER = null; }
};
})(window);