(() => {
  const qs = new URLSearchParams(location.search);
  const shopId = qs.get("shop_id");
  if (!shopId) return;

  const btn = document.getElementById("btn-load-sales");
  const section = document.getElementById("sales-section");

  btn.addEventListener("click", () => {
    btn.disabled = true;
    btn.textContent = "⏳ กำลังโหลดข้อมูล...";
    section.style.display = "none";

    fetch(`/page/store/shop_sales.php?shop_id=${shopId}`)
      .then((r) => r.json())
      .then((d) => {
        if (!d.ok) throw new Error(d.error || "fetch failed");
        const nf = new Intl.NumberFormat("th-TH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });

        document.getElementById("sum-orders").textContent =
          d.summary.total_orders || 0;
        document.getElementById("sum-items").textContent =
          d.summary.total_items_sold || 0;
        document.getElementById("sum-revenue").textContent = nf.format(
          d.summary.total_revenue || 0
        );
        document.getElementById("sum-qr").textContent = nf.format(
          d.summary.qr_revenue || 0
        );
        document.getElementById("sum-cod").textContent = nf.format(
          d.summary.cod_revenue || 0
        );

        const tbody = document.querySelector("#top-items tbody");
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

        section.style.display = "block";
        btn.textContent = "✅ โหลดข้อมูลแล้ว";
      })
      .catch((err) => {
        console.error(err);
        btn.textContent = "⚠️ โหลดไม่สำเร็จ";
        section.innerHTML = `<p style="color:#f55">${err.message}</p>`;
      });
  });

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
})();
