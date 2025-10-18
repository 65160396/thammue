// js/store/shop-sales-page.js
(function () {
  // -----------------------------
  // 1) อ่าน shop_id จาก query string
  // -----------------------------
  const qs = new URLSearchParams(location.search);
  const shopId = qs.get("shop_id");
  if (!shopId) return; // ถ้าไม่มี shop_id ก็ไม่ต้องทำอะไร

  // ตัวช่วยฟอร์แมตตัวเลขเป็นสกุลไทย (เช่น 785.00)
  const nf = new Intl.NumberFormat("th-TH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  // -----------------------------
  // 2) เรียก API ข้อมูลยอดขายของร้าน
  // -----------------------------
  fetch(`/page/store/shop_sales.php?shop_id=${encodeURIComponent(shopId)}`)
    .then((r) => r.json())
    .then((d) => {
      // ถ้า API ส่ง ok:false ให้โยน error ออกไปที่ catch
      if (!d.ok) throw new Error(d.error || "fetch failed");

      // -----------------------------
      // 3) อัปเดต “ตัวเลขสรุป” ด้านล่าง (การ์ดสรุปเล็ก)
      // -----------------------------
      setText("sum-orders", d.summary.total_orders || 0);
      setText("sum-items", d.summary.total_items_sold || 0);
      setText("sum-revenue", nf.format(d.summary.total_revenue || 0));

      // -----------------------------
      // 4) อัปเดต “ตัวเลขใหญ่ด้านบน” (บล็อก highlight)
      // -----------------------------
      setText("sum-revenue-big", nf.format(d.summary.total_revenue || 0));
      setText("sum-orders-small", d.summary.total_orders || 0);
      setText("sum-items-small", d.summary.total_items_sold || 0);
      setText("avg-order-small", nf.format(d.avg_per_order || 0));

      // -----------------------------
      // 5) เติมข้อมูลย่อยลงการ์ดสรุป (เฉลี่ย/ยกเลิก/จำนวนสินค้า/ช่องทาง)
      // -----------------------------
      injectExtraSummary({
        avg: d.avg_per_order || 0,
        cancel_count: d.cancelled_orders || 0,
        cancel_rate: d.cancel_rate || 0,
        prod_count: d.product_count || 0,
        pay: d.payment_breakdown || { qr_pct: 0, cod_pct: 0 },
      });

      // -----------------------------
      // 6) เติม “ตารางสินค้าขายดี”
      //    NOTE: ถ้า id อยู่บน <tbody id="top-items"> ให้ใช้ getElementById
      //          ถ้า id อยู่บน <table id="top-items"> ให้ใช้ querySelector('#top-items tbody')
      // -----------------------------
      const tbody = document.getElementById("top-items"); // <- ปรับตาม HTML ของคุณ
      if (!tbody) {
        console.warn("ไม่พบ <tbody id='top-items'> ใน DOM");
      } else {
        tbody.innerHTML = "";
        (d.top_items || []).forEach((row) => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${escapeHtml(row.name || "-")}</td>
            <td align="right">${row.sold_qty || 0}</td>
            <td align="right">${nf.format(row.revenue || 0)}</td>
          `;
          tbody.appendChild(tr);
        });
      }

      // -----------------------------
      // 7) (ทางเลือก) วาดกราฟจาก d.daily / d.monthly ที่นี่
      // -----------------------------
    })
    .catch((err) => {
      // -----------------------------
      // 8) แสดงข้อความผิดพลาดกรณีโหลดข้อมูลไม่ได้
      // -----------------------------
      console.error(err);
      document
        .getElementById("summary-card")
        .insertAdjacentHTML(
          "beforeend",
          `<p style="color:#f55">โหลดไม่สำเร็จ: ${escapeHtml(err.message)}</p>`
        );
    });

  // ---------- Helpers ----------
  // ใส่ข้อความให้ element ที่มี id นั้น ๆ (ถ้าไม่เจอ id จะเงียบ ๆ)
  function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  // escape ข้อความเพื่อกัน XSS เวลาอัด HTML เข้าไป
  function escapeHtml(s) {
    return (s ?? "")
      .toString()
      .replace(/[&<>"']/g, (m) => {
        return {
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[m];
      });
  }

  // เติมข้อมูลย่อยใต้การ์ดสรุป
  function injectExtraSummary({ avg, cancel_count, cancel_rate, prod_count, pay }) {
    const box = document.getElementById("summary-card");
    if (!box) return;
    box.insertAdjacentHTML(
      "beforeend",
      `
      <div style="margin-top:8px;color:#374151">
        รายได้เฉลี่ยต่อออเดอร์: <b>${nf.format(avg)}</b> บาท<br>
        ออเดอร์ยกเลิก: <b>${cancel_count}</b> (${cancel_rate}%)<br>
        จำนวนสินค้าในร้าน: <b>${prod_count}</b> รายการ<br>
        ช่องทางชำระ: QR <b>${pay.qr_pct ?? 0}%</b> • COD <b>${pay.cod_pct ?? 0}%</b>
      </div>
    `
    );
  }
})();
