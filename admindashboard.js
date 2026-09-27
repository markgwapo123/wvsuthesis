// ==============================
// Chart.js Pie Chart
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const chartCanvas = document.getElementById("pieChart");
  if (chartCanvas) {
    const ctx = chartCanvas.getContext("2d");
    new Chart(ctx, {
      type: "pie",
      data: {
        labels: ["Stationary", "Cleaning", "Tech", "Misc"],
        datasets: [{
          data: [147, 90, 126, 108],
          backgroundColor: ["#4e73df", "#e74a3b", "#36b9cc", "#f6c23e"]
        }]
      }
    });
  }
});

// ==============================
// Sidebar Submenu Toggle
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const submenuParents = document.querySelectorAll(".has-submenu");

  submenuParents.forEach(item => {
    item.addEventListener("click", (e) => {
      if (e.target.tagName.toLowerCase() === "p") return;

      submenuParents.forEach(other => {
        if (other !== item) {
          other.classList.remove("active");
          other.innerHTML = other.innerHTML.replace("▾", "▸");
        }
      });

      item.classList.toggle("active");

      if (item.classList.contains("active")) {
        item.innerHTML = item.innerHTML.replace("▸", "▾");
      } else {
        item.innerHTML = item.innerHTML.replace("▾", "▸");
      }
    });
  });
});

// ==============================
// Admin Manage Inventory Actions
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const addInventoryBtn = document.getElementById("addInventoryItemBtn") || document.querySelector(".inventory-container .add-btn");
  const exportBtn = document.querySelector(".export-btn");

  if (addInventoryBtn) {
    addInventoryBtn.addEventListener("click", (e) => {
      if (addInventoryBtn.tagName.toLowerCase() !== 'a') {
        const modal = document.getElementById("addItemModal");
        if (modal) {
          e.preventDefault();
          modal.style.display = "block";
        }
      }
    });
  }

  if (exportBtn) {
    exportBtn.addEventListener("click", () => {
      exportTableToCSV("inventory_export.csv");
    });
  }
});

// ==============================
// Logout Confirmation
// ==============================
document.addEventListener("DOMContentLoaded", () => { 
  const logoutBtn = document.querySelector(".logout");
  if (!logoutBtn) return;

  logoutBtn.addEventListener("click", (e) => {
    e.preventDefault();

    const confirmBox = document.createElement("div");
    confirmBox.style.cssText = `
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
    `;

    confirmBox.innerHTML = `
      <div style="background: #fff; padding: 20px; border-radius: 10px; text-align: center; max-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <p style="font-size: 16px; margin-bottom: 20px; color: #1e293b; font-family: sans-serif;">Are you sure you want to log out?</p>
        <button id="yesBtn" style="margin-right: 10px; padding: 8px 16px; background: #e74a3b; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Yes</button>
        <button id="noBtn" style="padding: 8px 16px; background: #36b9cc; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">No</button>
      </div>
    `;

    document.body.appendChild(confirmBox);

    document.getElementById("yesBtn").addEventListener("click", async () => {
      try {
        const response = await fetch("logout.php", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin"
        });
        if (!response.ok) throw new Error(`Logout request failed (${response.status})`);
      } catch (err) {
        console.error("Logout failed:", err);
        window.location.replace("logout.php");
        return;
      }
      window.location.replace("index.php");
    });

    document.getElementById("noBtn").addEventListener("click", () => {
      document.body.removeChild(confirmBox);
    });
  });
});

// ==============================
// Export Utilities (CSV/Excel/PDF)
// ==============================

// Appends UTF-8 BOM byte flags to ensure Excel opens file directly and preserves layouts
function downloadCSV(csv, filename) {
  const BOM = "\uFEFF"; 
  const csvFile = new Blob([BOM + csv], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(csvFile);
  link.download = filename;
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Custom parser that extracts child elements and reformats them as vertical lists
function parseCellText(cell) {
  // Check if cell contains multiple items (looking for divs, spans, or paragraph breaks)
  const itemBlocks = cell.querySelectorAll("div, p, li");
  if (itemBlocks.length > 0) {
    const parsedItems = [];
    itemBlocks.forEach(block => {
      const textVal = block.innerText.trim().replace(/\s+/g, ' ');
      if (textVal) parsedItems.push(textVal);
    });
    return parsedItems.join("\n");
  }
  
  // Alternative fallback check if text layout is separated by <br> tags
  if (cell.querySelector("br")) {
    return Array.from(cell.childNodes)
      .map(node => node.nodeType === Node.TEXT_NODE ? node.nodeValue.trim() : (node.tagName === 'BR' ? '\n' : node.innerText || ''))
      .join("")
      .trim();
  }

  return cell.innerText.trim();
}

function exportTableToCSV(filename) {
  const table = document.getElementById("requestTable") || document.getElementById("damageTable") || document.querySelector(".table");
  if (!table) return;

  const rows = table.querySelectorAll("tr");
  const csv = [];

  rows.forEach(row => {
    const cols = row.querySelectorAll("td, th");
    const rowData = [];
    cols.forEach(col => {
      let rawText = parseCellText(col);
      // Double quotes around content and escape existing quotes to preserve multiline data in one Excel row
      rawText = rawText.replace(/"/g, '""');
      rowData.push(`"${rawText}"`);
    });
    csv.push(rowData.join(","));
  });

  downloadCSV(csv.join("\n"), filename);
}

function exportTableToPDF() {
  const doc = new jsPDF('p', 'pt');
  const table = document.getElementById("requestTable") || document.getElementById("damageTable") || document.querySelector(".table");
  if (!table) return;

  const headers = Array.from(table.querySelectorAll("thead th")).map(th => th.innerText);
  const body = Array.from(table.querySelectorAll("tbody tr")).map(tr =>
    Array.from(tr.querySelectorAll("td")).map(td => parseCellText(td))
  );

  doc.text("Report Table Data", 40, 40);
  doc.autoTable({
    startY: 60,
    head: [headers],
    body: body,
    styles: { fontSize: 10, cellPadding: 8, valign: 'middle' },
    theme: 'grid'
  });

  doc.save("table_data.pdf");
}

// Download dropdown menu option toggles and triggers
document.addEventListener("DOMContentLoaded", () => {
  const downloadBtn = document.getElementById("downloadBtn") || document.getElementById("btnExcel") || document.getElementById("btnCSV");
  const menu = document.getElementById("downloadOptions");

  document.body.addEventListener("click", (e) => {
    const targetId = e.target.id || "";
    if (targetId === "btnExcel" || targetId === "btnCSV" || e.target.closest("#btnExcel") || e.target.closest("#btnCSV")) {
      e.preventDefault();
      exportTableToCSV("damage_lost_expired_items.csv");
    }
    if (targetId === "btnPDF" || e.target.closest("#btnPDF")) {
      e.preventDefault();
      exportTableToPDF();
    }
  });

  if (downloadBtn && menu) {
    downloadBtn.addEventListener("click", function() {
      menu.classList.toggle("hidden");
    });

    document.addEventListener("click", function(e) {
      if (!downloadBtn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add("hidden");
      }
    });

    const excelBtn = document.getElementById("downloadExcel") || menu.querySelector(".excel-option");
    if (excelBtn) {
      excelBtn.addEventListener("click", (e) => {
        e.preventDefault();
        exportTableToCSV("damage_lost_expired_items.csv");
        menu.classList.add("hidden");
      });
    }

    const csvBtn = document.getElementById("downloadCSV") || menu.querySelector(".csv-option");
    if (csvBtn) {
      csvBtn.addEventListener("click", (e) => {
        e.preventDefault();
        exportTableToCSV("damage_lost_expired_items.csv");
        menu.classList.add("hidden");
      });
    }

    const pdfBtn = document.getElementById("downloadPDF") || menu.querySelector(".pdf-option");
    if (pdfBtn) {
      pdfBtn.addEventListener("click", (e) => {
        e.preventDefault();
        exportTableToPDF();
        menu.classList.add("hidden");
      });
    }
  }
});

// ==============================
// Live Badge Notification Pulling
// ==============================
document.addEventListener("DOMContentLoaded", () => {
  const badge = document.querySelector(".notif-badge");
  if (!badge) return;
  setInterval(async () => {
    try {
      const res = await fetch("unread_count.php");
      const { count } = await res.json();
      badge.textContent = count > 0 ? count : "";
      badge.style.display = count > 0 ? "inline-block" : "none";
    } catch (e) {
      console.log("Notification check dropped.");
    }
  }, 30000); 
});