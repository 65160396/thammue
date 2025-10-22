
  (function () {
    const ENDPOINT = '/exchangepage/api/auth/me.php';
    let _me = null;           // cache
    let _ready = null;        // promise กันยิงซ้ำ

    async function fetchMe() {
      try {
        const r = await fetch(ENDPOINT, { credentials:'include', cache:'no-store' });
        const j = await r.json().catch(() => ({}));
        if (r.ok && j && j.ok && j.user && Number.isInteger(j.user.id)) return j.user;
      } catch (_) {}
      return null;
    }

    window.getMe = async function getMe(force = false) {
      if (force) { _ready = null; _me = null; }
      if (_me) return _me;
      if (!_ready) _ready = fetchMe().then(u => (_me = u)).finally(()=>{});
      return _ready;
    };

    // บูตทันที เพื่อให้ header/เมนูใช้ได้เร็ว
    getMe().then(user => {
      // ยิงเหตุการณ์ให้สคริปต์อื่นรู้
      window.dispatchEvent(new CustomEvent('auth:ready', { detail: user }));
    });
  })();

