
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
THAMMUE ex_* Auto-Integrator
- Adds header includes (CSS/JS)
- Injects nav links with badge
- Enhances likely "detail" pages with exchange actions

USAGE:
  python3 patcher.py /path/to/your/project/root

Notes:
- Creates .bak files once per file (first run).
- Idempotent: will not duplicate injected blocks (checks BEGIN/END markers).
- Detail page detection: filenames containing 'detail' or 'product' or 'item' (case-insensitive).
- You can adjust selectors/regexes below for your project's HTML structure.
"""
import sys, os, re, shutil

ROOT = sys.argv[1] if len(sys.argv) > 1 else "."

HEADER_SNIP = """<!-- BEGIN ex_includes -->
<link rel=\"stylesheet\" href=\"/css/ex.exchange.css\">
<script src=\"/js/ex.exchange.js\"></script>
<script src=\"/js/ex.ui.badge.js\"></script>
<!-- END ex_includes -->"""
NAV_SNIP = """<!-- BEGIN ex_nav -->
<li><a href=\"/page/ex_requests.html\">คำขอแลกเปลี่ยน</a></li>
<li><a href=\"/page/ex_notifications.html\">แจ้งเตือน <span id=\"exNotiBadge\" class=\"badge\"></span></a></li>
<!-- END ex_nav -->"""
DETAIL_SNIP = """<!-- BEGIN ex_detail_actions -->
<div class=\"ex-actions\" style=\"margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;\">
  <a class=\"ex-btn\" href=\"/page/ex_select_offer.html\" id=\"exBtnSelectOffer\">เลือกของที่จะเสนอ</a>
  <button class=\"ex-btn secondary\" id=\"exBtnMakeRequest\" disabled>ขอแลกทันที</button>
</div>
<script src=\"/js/ex.hooks.detail.js\"></script>
<script>
(function(){
  var params = new URLSearchParams(location.search);
  var fallbackId = parseInt(params.get('id')||'0', 10);
  var REQUESTED_ID = (typeof CURRENT_ITEM_ID !== 'undefined' ? CURRENT_ITEM_ID : fallbackId);
  function offeredId(){ return parseInt(sessionStorage.getItem('ex_offered_item_id')||'0',10); }
  function refresh(){ document.getElementById('exBtnMakeRequest').disabled = !(REQUESTED_ID && offeredId()); }
  refresh();
  ExHooks.attach('#exBtnMakeRequest', ()=>({requested_item_id: REQUESTED_ID, offered_item_id: offeredId()}));
})();
</script>
<!-- END ex_detail_actions -->"""

def read(path):
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        return f.read()

def write(path, text):
    with open(path, "w", encoding="utf-8") as f:
        f.write(text)

def backup_once(path):
    bak = path + ".bak"
    if not os.path.exists(bak):
        shutil.copy2(path, bak)

def has_block(text, marker):
    return f"BEGIN {marker}" in text and f"END {marker}" in text

def inject_header(html):
    if has_block(html, "ex_includes"): return html
    return re.sub(r"</head>", f"{HEADER_SNIP}\n</head>", html, flags=re.I, count=1)

def inject_nav(html):
    if has_block(html, "ex_nav"): return html
    patterns = [
        r"(<nav[^>]*>.*?<ul[^>]*>)(.*?)(</ul>.*?</nav>)",
        r"(<header[^>]*>.*?<ul[^>]*>)(.*?)(</ul>.*?</header>)",
        r"(<ul[^>]*class=[\"'][^\"']*(?:nav|menu)[^\"']*[\"'][^>]*>)(.*?)(</ul>)",
    ]
    for pat in patterns:
        m = re.search(pat, html, flags=re.I|re.S)
        if m:
            before, inner, after = m.group(1), m.group(2), m.group(3)
            new_inner = inner + "\\n" + NAV_SNIP + "\\n"
            return html.replace(m.group(0), before + new_inner + after, 1)
    return html

def looks_like_detail(filename):
    n = filename.lower()
    keys = ("detail", "product", "item")
    return any(k in n for k in keys)

def inject_detail(html):
    if has_block(html, "ex_detail_actions"): return html
    html2, n = re.subn(r"(</h1>)", r"\\1\\n" + DETAIL_SNIP, html, count=1, flags=re.I)
    if n: return html2
    return html

def main():
    changed = 0
    scanned = 0
    for root, _, files in os.walk(ROOT):
        for fn in files:
            if not fn.lower().endswith(".html"): continue
            path = os.path.join(root, fn)
            try:
                html = read(path)
            except Exception:
                continue
            scanned += 1
            orig = html

            if "</head>" in html.lower():
                html = inject_header(html)

            base = os.path.basename(fn).lower()
            if base in ("index.html", "home.html") or "layout" in base or "header" in base or "nav" in base:
                html = inject_nav(html)

            if looks_like_detail(fn):
                html = inject_detail(html)

            if html != orig:
                backup_once(path)
                write(path, html)
                changed += 1

    print(f"Scanned {scanned} HTML files, modified {changed}.")
    if changed == 0:
        print("No changes applied. Run me at your real project root or adjust detection rules.")
if __name__ == "__main__":
    main()
