<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$currentUser = campus_require_page_role('admin');
$csrfToken = campus_csrf_token();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-shell.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153f30">
  <title>Monthly report | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body admin-dashboard-body admin-monthly-report-body">
  <div class="dashboard">
    <?php campus_admin_navigation('monthly-report'); ?>
    <main class="dashboard-main">
      <?php campus_admin_topbar($currentUser, $csrfToken); ?>
      <div class="dashboard-content">
        <div class="dashboard-heading report-page-heading">
          <div>
            <p class="dashboard-eyebrow">Campus administration</p>
            <h1>Monthly report</h1>
            <p class="dashboard-subtitle">Summary of Campus Finds activity for the current month.</p>
          </div>
          <button type="button" class="filter-button print-button" id="print-monthly-report" disabled>Print report</button>
        </div>
        <p class="admin-data-error" id="monthly-report-error" role="alert" hidden></p>
        <article class="monthly-report-document" id="monthly-report-document">
          <header class="monthly-report-header">
            <div class="report-mark" aria-hidden="true">CvSU</div>
            <div>
              <p class="report-institution">Cavite State University</p>
              <p class="report-campus">Cavite City Campus · Campus Finds</p>
            </div>
            <div class="report-period-block">
              <span>Reporting period</span>
              <strong id="monthly-period">Loading…</strong>
            </div>
          </header>
          <div class="report-title-block">
            <p>LOST AND FOUND MANAGEMENT</p>
            <h2>Monthly Activity Report</h2>
            <span>Prepared <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name'], ENT_QUOTES, 'UTF-8') ?> · Generated <time id="report-generated-date"></time></span>
          </div>
          <section class="report-section" aria-labelledby="summary-section-title">
            <h3 id="summary-section-title"><span>01</span> Monthly activity summary</h3>
            <table class="monthly-report-table">
              <caption class="sr-only">Campus Finds monthly activity summary by category</caption>
              <thead>
                <tr><th scope="col">Category</th><th scope="col">Activity</th><th scope="col">Total</th></tr>
              </thead>
              <tbody>
                <tr><td>Item reports</td><th scope="row">Total reports submitted</th><td id="monthly-reports-total">—</td></tr>
                <tr><td>Item reports</td><th scope="row">Published found-item reports</th><td id="monthly-found-published">—</td></tr>
                <tr><td>Item reports</td><th scope="row">Published lost-item reports</th><td id="monthly-lost-published">—</td></tr>
                <tr><td>Item reports</td><th scope="row">Pending report reviews</th><td id="monthly-pending-reports">—</td></tr>
                <tr><td>Item reports</td><th scope="row">Rejected reports</th><td id="monthly-rejected-reports">—</td></tr>
                <tr><td>Ownership claims</td><th scope="row">Total claims submitted</th><td id="monthly-claims-total">—</td></tr>
                <tr><td>Ownership claims</td><th scope="row">Approved claims</th><td id="monthly-approved-claims">—</td></tr>
                <tr><td>Ownership claims</td><th scope="row">Rejected claims</th><td id="monthly-rejected-claims">—</td></tr>
                <tr><td>Community</td><th scope="row">New student accounts</th><td id="monthly-new-students">—</td></tr>
              </tbody>
            </table>
          </section>
          <footer class="monthly-report-footer">
            <span>Campus Finds · Cavite State University, Cavite City Campus</span>
            <span>For school administration use</span>
          </footer>
        </article>
      </div>
    </main>
  </div>
  <script>
    document.querySelector("#report-generated-date").textContent = new Intl.DateTimeFormat("en-PH", {
      year: "numeric",
      month: "long",
      day: "numeric"
    }).format(new Date());

    async function loadMonthlyReport() {
      const errorMessage = document.querySelector("#monthly-report-error");
      try {
        const response = await fetch("api.php?action=admin-monthly-report", { credentials: "same-origin" });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Could not load the monthly report.");
        const monthly = result.monthly;
        document.querySelector("#monthly-period").textContent = monthly.period;
        document.querySelector("#monthly-reports-total").textContent = monthly.reports_total;
        document.querySelector("#monthly-found-published").textContent = monthly.found_published;
        document.querySelector("#monthly-lost-published").textContent = monthly.lost_published;
        document.querySelector("#monthly-pending-reports").textContent = monthly.pending_reports;
        document.querySelector("#monthly-rejected-reports").textContent = monthly.rejected_reports;
        document.querySelector("#monthly-claims-total").textContent = monthly.claims_total;
        document.querySelector("#monthly-approved-claims").textContent = monthly.approved_claims;
        document.querySelector("#monthly-rejected-claims").textContent = monthly.rejected_claims;
        document.querySelector("#monthly-new-students").textContent = monthly.new_students;
        document.querySelector("#print-monthly-report").disabled = false;
      } catch (error) {
        console.error("Unable to load the Campus Finds monthly report.", error);
        errorMessage.textContent = error.message;
        errorMessage.hidden = false;
      }
    }

    document.querySelector("#print-monthly-report").addEventListener("click", () => window.print());
    loadMonthlyReport();
    document.querySelector("[data-logout]").addEventListener("click", async (event) => {
      const button = event.currentTarget;
      try {
        const response = await fetch("api.php?action=logout", {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-CSRF-Token": button.dataset.csrf }
        });
        if (!response.ok) throw new Error("Unable to end this session.");
        window.location.href = "index.html";
      } catch (error) {
        console.error("Unable to log out of Campus Finds.", error);
        window.alert("Could not log out. Check your connection and try again.");
      }
    });
  </script>
</body>
</html>
