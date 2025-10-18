// กรองรายการโปรดในหน้า (ไม่เรียก backend)
(function () {
  const $ = (sel, root = document) => (root || document).querySelector(sel);

  // เก็บสำเนา HTML เดิมของรายการโปรดไว้
  let allCards = [];
  function indexFavCards() {
    const grid = $("#favGrid");
    if (!grid) return;
    allCards = [...grid.querySelectorAll(".product-card")].map(card => {
      // ดึงข้อความที่สำคัญไว้ค้นหา (ชื่อ, จังหวัด, ราคา)
      const name  = (card.querySelector(".title")?.textContent || "").trim();
      const meta  = (card.querySelector(".meta-inline")?.textContent || "").trim();
      const price = (card.querySelector(".price-strong")?.textContent || "").trim();
      return {
        id: card.dataset?.id || "",
        text: (name + " " + meta + " " + price).toLowerCase(),
        html: card.outerHTML
      };
    });
  }

  function showResults(list, totalAll) {
    const sect  = $("#searchSection");
    const box   = $("#results");
    const count = $("#searchCount");
    if (!sect || !box) return;

    sect.hidden = false;
    if (!list.length) {
      box.innerHTML = "ไม่พบสินค้า";
      if (count) count.textContent = "(0 รายการ)";
      return;
    }
    box.innerHTML = list.map(x => x.html).join("");
    if (count) count.textContent = `(${list.length} จากทั้งหมด ${totalAll} รายการ)`;
  }

  function clearResults() {
    const sect  = $("#searchSection");
    const box   = $("#results");
    const count = $("#searchCount");
    if (sect) sect.hidden = true;
    if (box) box.innerHTML = "";
    if (count) count.textContent = "";
  }

  // ฟัง event จาก Search.init ที่หน้า favorites ยิงมา
  window.addEventListener("local-fav:search", (ev) => {
    const q = (ev.detail?.q || "").trim().toLowerCase();
    if (!allCards.length) indexFavCards();
    if (!q) { clearResults(); return; }

    // split เป็นคำ ๆ แล้วต้องเจอทุกคำ
    const tokens = q.split(/\s+/).filter(Boolean);
    const matched = allCards.filter(it => tokens.every(t => it.text.includes(t)));
    showResults(matched, allCards.length);
  });

  // ปุ่ม “ล้างการค้นหา”
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("#clearSearch");
    if (!btn) return;
    e.preventDefault();
    clearResults();
    $("#q")?.focus();
  });

  // สร้างดัชนีครั้งแรกหลังโหลด
  document.addEventListener("DOMContentLoaded", indexFavCards);
})();
