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

  // เรียก init header หลังจาก inject เสร็จจริง ๆ
  if (window.requestAnimationFrame) {
    requestAnimationFrame(() => window.exHeaderInit && window.exHeaderInit());
  } else {
    setTimeout(() => window.exHeaderInit && window.exHeaderInit(), 0);
  }

  // แจ้งว่า partials พร้อมแล้ว
  document.dispatchEvent(new CustomEvent('ex:partials:ready'));
}

document.addEventListener('DOMContentLoaded', includePartials);

// ❌ อย่าเรียก exHeaderInit ตรงนี้ก่อน include เสร็จ
// if (window.exHeaderInit) window.exHeaderInit();
// document.dispatchEvent(new CustomEvent('ex:partials:ready'));
