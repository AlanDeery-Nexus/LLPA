<!-- Bootstrap CSS & Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
 
<!-- NAVBAR -->
<nav class="navbar navbar-dark bg-dark px-3">
  <a class="navbar-brand fw-bold" href="#"><i class="bi bi-mortarboard-fill me-2"></i>LLPA Shared Schedule</a>
</nav>
 
<!-- MAIN CONTENT -->
<div class="container-fluid py-4">
  <h1 class="h4 mb-1 text-dark">Upcoming Training Classes</h1>
  <p class="text-muted mb-3">All prices in <strong>EUR (€)</strong>.</p>
 
  <!-- Loading / error state -->
  <div id="loadingMsg" class="alert alert-info d-flex align-items-center gap-2">
    <span class="spinner-border spinner-border-sm" role="status"></span>
    Loading class data…
  </div>
  <div id="errorMsg" class="alert alert-danger d-none"></div>
 
  <!-- Filter bar (hidden until data loads) -->
  <div id="filterBar" class="row g-2 mb-3 d-none">
    <div class="col-12 col-md-5">
      <input type="search" id="filterName" class="form-control form-control-sm" placeholder="🔍 Filter by course name…" />
    </div>
    <div class="col-6 col-md-4">
      <select id="filterCompany" class="form-select form-select-sm">
        <option value="">All Selling Companies</option>
      </select>
    </div>
    <div class="col-6 col-md-3 text-end">
      <button id="clearFilters" class="btn btn-sm btn-outline-secondary w-100">Clear Filters</button>
    </div>
  </div>
 
  <div class="table-responsive shadow-sm rounded d-none" id="tableWrapper">
    <table class="table table-striped table-hover table-bordered align-middle mb-0" id="classesTable">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Status</th>
          <th>Starts (Local)</th>
          <th>Ends (Local)</th>
          <th>Delivery Language</th>
          <th class="text-end">Price (Tier 1)</th>
          <th class="text-end">Price (Tier 2)</th>
          <th class="text-end">Price (Tier 3)</th>
          <th>Selling Company</th>
          <th class="text-center">Book</th>
        </tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>
  </div>
 
  <p class="text-muted small mt-3 d-none" id="tableNote">
    <i class="bi bi-info-circle me-1"></i>
    The <strong>Book</strong> button opens your email client with a pre-filled urgent booking request — choose your tier in the email before sending.
    Showing <span id="rowCount">0</span> of <span id="totalCount">0</span> classes.
  </p>
</div>
 
<footer class="bg-dark text-white-50 text-center py-3 small">
  &copy; 2026 LLPA · Shared Schedule · All prices in EUR (€)
</footer>
 
<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
 
<script>
let ALL_CLASSES = [];
 
// ─── COLOUR PALETTE for dynamic companies ─────────────────────────────────────
const BADGE_PALETTE = [
  "bg-primary", "bg-success", "bg-info text-dark", "bg-warning text-dark",
  "bg-secondary", "bg-dark", "bg-danger"
];
const domainColourMap = {};
let paletteIndex = 0;
function colourForDomain(key) {
  if (!domainColourMap[key]) {
    domainColourMap[key] = BADGE_PALETTE[paletteIndex % BADGE_PALETTE.length];
    paletteIndex++;
  }
  return domainColourMap[key];
}
 
// ─── DERIVE COMPANY NAME FROM EMAIL DOMAIN ────────────────────────────────────
function companyFromEmail(email) {
  if (!email || !email.includes("@")) return { company: "—", domain: "" };
  const domain = email.split("@")[1].toLowerCase().trim();
  const base = domain
    .replace(/\.(com|ie|co\.uk|org|net|eu|de|fr|nl)$/i, "")
    .replace(/[-_.]/g, " ")
    .replace(/\b\w/g, c => c.toUpperCase());
  return { company: base, domain };
}
 
// ─── FORMAT PRICE ─────────────────────────────────────────────────────────────
function formatPrice(val) {
  const n = parseFloat(val);
  return isNaN(n) || n === 0 ? "—" : "€" + n.toFixed(2);
}
 
// ─── CSV PARSER ───────────────────────────────────────────────────────────────
function parseCSV(text) {
  text = text.replace(/^\uFEFF/, "");
  const lines = text.split(/\r?\n/).filter(l => l.trim() !== "");
  if (lines.length < 2) return [];
 
  function splitLine(line) {
    const fields = [];
    let inQuote = false, field = "";
    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (ch === '"') {
        if (inQuote && line[i+1] === '"') { field += '"'; i++; }
        else inQuote = !inQuote;
      } else if (ch === ',' && !inQuote) {
        fields.push(field.trim()); field = "";
      } else {
        field += ch;
      }
    }
    fields.push(field.trim());
    return fields;
  }
 
  const headers = splitLine(lines[0]).map(h => h.trim());
  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const vals = splitLine(lines[i]);
    const obj = {};
    headers.forEach((h, idx) => { obj[h] = (vals[idx] || "").trim(); });
    rows.push(obj);
  }
  return rows;
}
 
// ─── MAP CSV ROW → CLASS OBJECT ───────────────────────────────────────────────
function mapRow(row) {
  const email = row["Contact Email Address"] || "";
  const { company, domain } = companyFromEmail(email);
  const t1 = parseFloat(row["Price Tier 1"]);
  const t2 = parseFloat(row["Price Tier 2"]);
  const t3 = parseFloat(row["Price Tier 3"]);
  return {
    name:      row["Name"] || "",
    status:    row["Status"] || "",
    starts:    row["Starts (Local)"] || "",
    ends:      row["Ends (Local)"] || "",
    language:  row["Delivery Language"] || "",
    tier1:     isNaN(t1) ? 0 : t1,
    tier2:     isNaN(t2) ? 0 : t2,
    tier3:     isNaN(t3) ? 0 : t3,
    email,
    domain,
    company
  };
}
 
// ─── LOAD CSV ─────────────────────────────────────────────────────────────────
async function loadCSV() {
  try {
    //const res = await fetch("Classes.csv");
    const res = await fetch("https://raw.githubusercontent.com/AlanDeery-Nexus/LLPA/refs/heads/main/Classes.csv");
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    const rows = parseCSV(text);
    if (!rows.length) throw new Error("CSV appears empty or could not be parsed.");
    ALL_CLASSES = rows.map(mapRow).filter(c => c.tier1 > 0 || c.tier2 > 0 || c.tier3 > 0);
    return true;
  } catch (err) {
    document.getElementById("loadingMsg").classList.add("d-none");
    const errEl = document.getElementById("errorMsg");
    errEl.classList.remove("d-none");
    errEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Could not load data:</strong> ${escHtml(err.message)}<br>
      <small class="text-muted">Make sure the data file is in the same folder as this HTML file and you are opening it via a web server.</small>`;
    return false;
  }
}
 
// ─── POPULATE FILTER DROPDOWNS ────────────────────────────────────────────────
function populateFilters() {
  const companies = [...new Set(ALL_CLASSES.map(c => c.company).filter(c => c !== "—"))].sort();
  const coSel = document.getElementById("filterCompany");
  companies.forEach(c => {
    const opt = document.createElement("option"); opt.value = c; opt.textContent = c;
    coSel.appendChild(opt);
  });
}
 
// ─── FILTER + SORT ────────────────────────────────────────────────────────────
function filteredClasses() {
  const nameQ    = document.getElementById("filterName").value.trim().toLowerCase();
  const companyQ = document.getElementById("filterCompany").value;
  return ALL_CLASSES
    .filter(c => {
      if (nameQ    && !c.name.toLowerCase().includes(nameQ)) return false;
      if (companyQ && c.company !== companyQ) return false;
      return true;
    })
    .sort((a, b) => parseDate(a.starts) - parseDate(b.starts));
}
 
// ─── MAILTO BUILDER ───────────────────────────────────────────────────────────
const LLPA_EMAIL = "sharon.oosterberg@thellpa.com";
 
function buildMailto(cls) {
  const subject = encodeURIComponent("! URGENT: LLPA Shared Schedule Booking");
  const body = encodeURIComponent(
`Hi there,
 
Please process this booking Urgently.
 
---------- COURSE DETAILS ----------
Course Name:       ${cls.name}
Start Date/Time:   ${cls.starts}
End Date/Time:     ${cls.ends}
Language:          ${cls.language}
Price Tier 1:      ${formatPrice(cls.tier1)}
Price Tier 2:      ${formatPrice(cls.tier2)}
Price Tier 3:      ${formatPrice(cls.tier3)}
Agreed Price Tier: [Please enter Tier 1 / Tier 2 / Tier 3]
Selling Company:   ${cls.company} (${cls.domain})
 
---------- STUDENT DETAILS ----------
First Name:        [Please enter student first name]
Last Name:         [Please enter student last name]
Student Email:     [Please enter student email address]
 
---------- YOUR CONTACT DETAILS (for confirmation & invoicing) ----------
Your Contact Name: [Please enter your contact name]
Your Email:        [Please enter your contact email]
Your Phone:        [Please enter your contact phone]
PO / Reference:    [Please enter PO number or booking reference if applicable]
 
--------------------------------------
Thank you.`
  );
  return `mailto:${LLPA_EMAIL}?subject=${subject}&body=${body}`;
}
 
// ─── RENDER TABLE ─────────────────────────────────────────────────────────────
function renderTable() {
  const tbody   = document.getElementById("tableBody");
  const classes = filteredClasses();
  tbody.innerHTML = "";
 
  classes.forEach((cls, i) => {
    const statusBadge = cls.status === "Scheduled"
      ? `<span class="badge bg-success">${escHtml(cls.status)}</span>`
      : `<span class="badge bg-secondary">${escHtml(cls.status)}</span>`;
 
    const colour = colourForDomain(cls.domain || cls.company);
    const companyBadge = `<span class="badge ${colour}">${escHtml(cls.company)}</span>`;
 
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="text-muted small">${i + 1}</td>
      <td class="fw-semibold" style="min-width:220px">${escHtml(cls.name)}</td>
      <td>${statusBadge}</td>
      <td class="text-nowrap">${escHtml(cls.starts)}</td>
      <td class="text-nowrap">${escHtml(cls.ends)}</td>
      <td class="small">${escHtml(cls.language)}</td>
      <td class="text-end fw-bold text-success">${escHtml(formatPrice(cls.tier1))}</td>
      <td class="text-end fw-bold text-primary">${escHtml(formatPrice(cls.tier2))}</td>
      <td class="text-end fw-bold text-danger">${escHtml(formatPrice(cls.tier3))}</td>
      <td>
        ${companyBadge}<br>
        ${cls.domain ? `<a href="https://${escHtml(cls.domain)}" target="_blank" class="small text-muted">${escHtml(cls.domain)}</a>` : ""}
      </td>
      <td class="text-center">
        <a href="${buildMailto(cls)}" class="btn btn-sm btn-danger">
          <i class="bi bi-envelope-fill me-1"></i>Book
        </a>
      </td>
    `;
    tbody.appendChild(tr);
  });
 
  document.getElementById("rowCount").textContent   = classes.length;
  document.getElementById("totalCount").textContent = ALL_CLASSES.length;
}
 
// ─── UTILITY ─────────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;")
    .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}
 
function parseDate(str) {
  if (!str) return new Date(0);
  const [datePart, timePart] = str.split(" ");
  const [d, m, y] = datePart.split("/");
  return new Date(`${y}-${m}-${d}T${timePart || "00:00"}`);
}
 
// ─── EVENTS ───────────────────────────────────────────────────────────────────
["filterName","filterCompany"].forEach(id => {
  document.getElementById(id).addEventListener("input", renderTable);
});
 
document.getElementById("clearFilters").addEventListener("click", () => {
  document.getElementById("filterName").value    = "";
  document.getElementById("filterCompany").value = "";
  renderTable();
});
 
// ─── INIT ─────────────────────────────────────────────────────────────────────
(async () => {
  const ok = await loadCSV();
  if (!ok) return;
 
  document.getElementById("loadingMsg").classList.add("d-none");
  document.getElementById("tableWrapper").classList.remove("d-none");
  document.getElementById("filterBar").classList.remove("d-none");
  document.getElementById("tableNote").classList.remove("d-none");
 
  populateFilters();
  renderTable();
})();
</script>
 