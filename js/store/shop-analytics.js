// /js/store/shop-analytics.js

// --------- Helpers (ใช้ซ้ำ) ----------
const nf = new Intl.NumberFormat("th-TH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}
function escapeHtml(s) {
  return (s ?? "").toString().replace(/[&<>"']/g, m => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m]));
}

// เก็บอินสแตนซ์กราฟไว้ทำลาย/อัปเดต
let chart;

// --------- โหลดข้อมูลตามช่วงเวลา + วาดกราฟ + เติมตาราง ----------
function loadAnalytics(shopId) {
  const from  = document.getElementById("f-from")?.value;
  const to    = document.getElementById("f-to")?.value;
  const group = document.getElementById("f-group")?.value || "day";

  if (!from || !to) {
    alert("กรุณาเลือกช่วงวันที่ก่อน");
    return;
  }

  const url = `/page/store/shop_sales.php?shop_id=${encodeURIComponent(shopId)}&from=${from}&to=${to}&group=${group}`;

  fetch(url)
    .then(r => r.json())
    .then(d => {
      if (!d.ok) throw new Error(d.error || "fetch failed");

      // 1) อัปเดตการ์ดตัวเลขใหญ่ด้านบน
      setText("sum-revenue-big", nf.format(d.summary.total_revenue || 0));
      setText("sum-orders-small", d.summary.total_orders || 0);
      setText("sum-items-small", d.summary.total_items_sold || 0);
      setText("avg-order-small", nf.format(d.avg_per_order || 0));

      // 2) อัปเดตสรุปเล็กด้านล่าง
      setText("sum-orders", d.summary.total_orders || 0);
      setText("sum-items", d.summary.total_items_sold || 0);
      setText("sum-revenue", nf.format(d.summary.total_revenue || 0));
      setText("sum-qr", nf.format(d.summary.qr_revenue || 0));
      setText("sum-cod", nf.format(d.summary.cod_revenue || 0));

      // 3) เติมตาราง "สินค้าขายดี"
      const tbodyTop = document.getElementById("top-items"); // <tbody id="top-items">
      if (tbodyTop) {
        tbodyTop.innerHTML = "";
        (d.top_items || []).forEach(row => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${escapeHtml(row.name || "-")}</td>
            <td align="right">${row.sold_qty || 0}</td>
            <td align="right">${nf.format(row.revenue || 0)}</td>
          `;
          tbodyTop.appendChild(tr);
        });
      }

      // 4) เติมตาราง timeseries รายวัน/เดือน/ปี
      const ts = d.timeseries || [];
      const tsBody = document.getElementById("ts-body"); // <tbody id="ts-body">
      if (tsBody) {
        tsBody.innerHTML = "";
        ts.forEach(r => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${escapeHtml(r.bucket)}</td>
            <td align="right">${nf.format(r.revenue || 0)}</td>
            <td align="right">${r.orders || 0}</td>
            <td align="right">${r.items || 0}</td>
          `;
          tsBody.appendChild(tr);
        });
      }

      // 5) วาด/อัปเดตกราฟรายได้
      const ctx = document.getElementById("chartRevenue");
      if (ctx) {
        const labels = ts.map(r => r.bucket);
        const data   = ts.map(r => Number(r.revenue || 0));
        if (chart) chart.destroy();
        chart = new Chart(ctx, {
          type: "bar", // เปลี่ยนเป็น "line" ได้ตามชอบ
          data: { labels, datasets: [{ label: "รายได้ (บาท)", data, borderWidth: 1 }] },
          options: { scales: { y: { beginAtZero: true } } }
        });
      }
    })
    .catch(err => {
      console.error(err);
      alert("โหลดรายงานไม่สำเร็จ: " + err.message);
    });
}

// --------- ตั้งค่าฟิลเตอร์เริ่มต้น + bind ปุ่ม ---------
(function initFilters() {
  const qs = new URLSearchParams(location.search);
  const shopId = qs.get("shop_id");
  if (!shopId) return;

  // ค่าเริ่มต้น: 30 วันล่าสุด
  const to   = new Date();
  const from = new Date(); from.setDate(from.getDate() - 30);
  const pad = n => String(n).padStart(2, "0");
  const d2s = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  const fromEl  = document.getElementById("f-from");
  const toEl    = document.getElementById("f-to");
  const groupEl = document.getElementById("f-group");
  const runBtn  = document.getElementById("btn-run");

  if (fromEl) fromEl.value = d2s(from);
  if (toEl)   toEl.value   = d2s(to);
  if (groupEl) groupEl.value = "day";

  if (runBtn) runBtn.addEventListener("click", () => loadAnalytics(shopId));

  // โหลดครั้งแรกทันที
  loadAnalytics(shopId);
})();
