/* ============================================================
   UNIVERSAL TABLE MODULE  — reusable for ANY page/table
   ============================================================ */

function initTableModule(config) {

    const ajaxUrl        = config.ajaxUrl;
    const tableContainer = document.querySelector(config.tableContainer);

    // Optional elements (might not exist on some pages)
    const filterForm  = config.filterForm  ? document.querySelector(config.filterForm)  : null;
    const liveSearch  = config.liveSearch  ? document.querySelector(config.liveSearch)  : null;
    const modalOverlay = config.modalOverlay ? document.querySelector(config.modalOverlay) : null;
    const modalClose   = config.modalClose   ? document.querySelector(config.modalClose)   : null;
    const modalContent = config.modalContent ? document.querySelector(config.modalContent) : null;

    const exportCSVBtn   = config.exportCSVBtn   ? document.querySelector(config.exportCSVBtn)   : null;
    const exportExcelBtn = config.exportExcelBtn ? document.querySelector(config.exportExcelBtn) : null;
    const exportPDFBtn   = config.exportPDFBtn   ? document.querySelector(config.exportPDFBtn)   : null;

    /* ===========================
       LOAD TABLE (AJAX)
       =========================== */
    function loadTable(page = 1) {
        if (!tableContainer) return;

        let formData = new FormData();
        if (filterForm) {
            formData = new FormData(filterForm);
        }

        formData.set("page", page);
        formData.set("live", liveSearch ? liveSearch.value : "");

        fetch(ajaxUrl, { method: "POST", body: formData })
            .then(r => r.text())
            .then(html => {
                tableContainer.innerHTML = html;
                initSorting();
                initPagination();
                initModalButtons();
            })
            .catch(err => {
                console.error("TableModule AJAX error:", err);
                tableContainer.innerHTML = "<div style='color:red;'>Error loading data.</div>";
            });
    }

    /* ===========================
       SORTING
       =========================== */
    function initSorting() {
        const table = tableContainer.querySelector("table");
        if (!table) return;

        const headers = table.querySelectorAll("th.sortable");

        headers.forEach((th, index) => {
            th.addEventListener("click", () => {
                const tbody = table.querySelector("tbody");
                const rows  = Array.from(tbody.querySelectorAll("tr"));

                const direction = th.dataset.sort === "asc" ? "desc" : "asc";
                th.dataset.sort = direction;

                rows.sort((a, b) => {
                    let A = a.children[index].innerText.trim();
                    let B = b.children[index].innerText.trim();
                    return direction === "asc"
                        ? A.localeCompare(B)
                        : B.localeCompare(A);
                });

                rows.forEach(r => tbody.appendChild(r));
            });
        });
    }

    /* ===========================
       PAGINATION
       =========================== */
    function initPagination() {
        if (!tableContainer) return;
        const links = tableContainer.querySelectorAll(".page-link");
        links.forEach(btn => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.dataset.page || "1", 10);
                loadTable(p);
            });
        });
    }

    /* ===========================
       MODAL BUTTONS (optional)
       =========================== */
    function initModalButtons() {
        if (!modalOverlay || !modalContent) return;

        const buttons = tableContainer.querySelectorAll(".view-details");
        buttons.forEach(btn => {
            btn.addEventListener("click", () => {
                modalContent.innerHTML = btn.dataset.details || "";
                modalOverlay.style.display = "flex";
            });
        });
    }

    if (modalClose && modalOverlay) {
        modalClose.addEventListener("click", () => {
            modalOverlay.style.display = "none";
        });
        modalOverlay.addEventListener("click", e => {
            if (e.target === modalOverlay) {
                modalOverlay.style.display = "none";
            }
        });
    }

    /* ===========================
       EXPORT CSV
       =========================== */
    function exportCSV() {
        const table = tableContainer.querySelector("table");
        if (!table) {
            alert("No data to export.");
            return;
        }

        const rows = table.querySelectorAll("tr");
        const csv = [];

        rows.forEach(row => {
            const cols = row.querySelectorAll("th,td");
            const data = Array.from(cols).map(col => {
                let text = col.innerText.replace(/"/g, '""');
                return `"${text}"`;
            });
            csv.push(data.join(","));
        });

        const blob = new Blob([csv.join("\n")], { type: "text/csv" });
        const url  = URL.createObjectURL(blob);

        const a = document.createElement("a");
        a.href = url;
        a.download = (config.exportFileName || "export") + ".csv";
        a.click();
    }

    /* ===========================
       EXPORT EXCEL
       =========================== */
    function exportExcel() {
        const table = tableContainer.querySelector("table");
        if (!table) {
            alert("No data to export.");
            return;
        }

        const html = table.outerHTML.replace(/ /g, "%20");
        const a = document.createElement("a");
        a.href = "data:application/vnd.ms-excel," + html;
        a.download = (config.exportFileName || "export") + ".xls";
        a.click();
    }

    /* ===========================
       EXPORT PDF
       =========================== */
    function exportPDF() {
        const table = tableContainer.querySelector("table");
        if (!table) {
            alert("No data to export.");
            return;
        }

        if (!window.jspdf || !window.jspdf.jsPDF) {
            alert("jsPDF not loaded.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF("l", "pt", "a4");

        doc.text(config.exportTitle || "Report", 40, 40);

        doc.autoTable({
            html: table,
            startY: 60,
            theme: "grid",
            styles: { fontSize: 7 }
        });

        doc.save((config.exportFileName || "export") + ".pdf");
    }

    // Hook export buttons
    if (exportCSVBtn)   exportCSVBtn.addEventListener("click", exportCSV);
    if (exportExcelBtn) exportExcelBtn.addEventListener("click", exportExcel);
    if (exportPDFBtn)   exportPDFBtn.addEventListener("click", exportPDF);

    // Triggers
    if (liveSearch) {
        liveSearch.addEventListener("keyup", () => loadTable());
    }

    if (filterForm) {
        filterForm.addEventListener("submit", e => {
            e.preventDefault();
            loadTable();
        });
    }

    // Initial load
    loadTable();
}
