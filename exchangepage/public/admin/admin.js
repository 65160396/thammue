
window.admin = {
    get project() { return localStorage.getItem('ADM_PROJECT') || 'exchange'; },
    set project(v) { localStorage.setItem('ADM_PROJECT', v); },
    csrf: null,
    async api(url, data = null, method = 'GET', needCsrf = false) {
        const headers = { 'X-Project-Key': this.project };
        const opt = { method, credentials: 'include', headers };
        if (method !== 'GET') {
            opt.headers['Content-Type'] = 'application/json';
            if (needCsrf) {
                if (!this.csrf) { const me = await this.api('/thammue/admin/me.php'); this.csrf = me.csrf; }
                opt.headers['X-CSRF-Token'] = this.csrf;
            }
            opt.body = JSON.stringify(data || {});
        }
        const res = await fetch(url, opt);
        if (res.status === 401 || res.status === 403) { location.href = 'admin_login.html'; return { ok: false }; }
        return res.json();
    },
    async ensureLogin() {
        const me = await this.api('/thammue/admin/me.php');
        if (me?.ok) { this.csrf = me.csrf; return true; }
        location.href = 'admin_login.html';
    }
};

