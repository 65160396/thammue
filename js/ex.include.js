// /js/ex.include.js
async function includePartials() {
  const nodes = document.querySelectorAll('[data-include]');
  for (const el of nodes) {
    const url = el.getAttribute('data-include');
    try {
      const res = await fetch(url, { credentials: 'include' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const html = await res.text();
      el.outerHTML = html;
    } catch (e) {
      console.error('include fail:', url, e);
    }
  }
  // init header after inject
  if (window.requestAnimationFrame) {
    requestAnimationFrame(() => window.exHeaderInit && window.exHeaderInit());
  } else {
    setTimeout(() => window.exHeaderInit && window.exHeaderInit(), 0);
  }
}
document.addEventListener('DOMContentLoaded', includePartials);
