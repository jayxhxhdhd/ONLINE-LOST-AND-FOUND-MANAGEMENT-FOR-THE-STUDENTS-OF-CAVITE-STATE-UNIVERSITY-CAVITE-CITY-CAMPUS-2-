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
  <title>School Admin | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body admin-dashboard-body">
  <div class="dashboard">
    <?php campus_admin_navigation('overview'); ?>

    <main class="dashboard-main">
      <?php campus_admin_topbar($currentUser, $csrfToken); ?>

      <div class="dashboard-content">
        <div class="dashboard-heading">
          <div>
            <p class="dashboard-eyebrow">Campus administration</p>
            <h1>School Admin dashboard</h1>
            <p class="dashboard-subtitle">Oversee reports, verify ownership and keep our campus community safe.</p>
          </div>
          <a class="admin-export-link" href="admin-monthly-report.php">View monthly report <span aria-hidden="true">→</span></a>
        </div>

        <?php if (filter_var($_GET['reported'] ?? null, FILTER_VALIDATE_INT)): ?>
          <p class="profile-notice" role="status">Your item report was submitted and is waiting for review. <a href="#item-review-heading">Go to report reviews</a></p>
        <?php endif; ?>

        <p class="admin-data-error" id="admin-error" role="status" hidden></p>

        <section class="dashboard-stats admin-stats" aria-label="Campus usage summary">
          <article class="stat-card">
            <span class="stat-icon green" aria-hidden="true">▤</span>
            <div><p>Pending reports</p><strong id="pending-report-count">—</strong><small>Waiting for review</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon gold" aria-hidden="true">♧</span>
            <div><p>Ownership claims</p><strong id="claim-count">—</strong><small>Awaiting a decision</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon blue" aria-hidden="true">♙</span>
            <div><p>Student accounts</p><strong id="student-count">—</strong><small>Registered on Campus Finds</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon green" aria-hidden="true">⌕</span>
            <div><p>Found reports</p><strong id="found-report-count">—</strong><small>Published for students</small></div>
          </article>
        </section>

        <section class="admin-panel" id="item-reviews" aria-labelledby="item-review-heading">
          <div class="admin-panel-heading">
            <div>
              <h2 id="item-review-heading">Lost &amp; found reports</h2>
              <p>Review new reports before they are shared with students.</p>
            </div>
            <a class="admin-inline-link" href="browse-items.php">Browse items <span aria-hidden="true">→</span></a>
          </div>
          <p class="admin-count-pill" id="item-review-count" aria-live="polite">Loading…</p>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Item report</th><th>Type</th><th>Reported by</th><th>Status</th><th>Action</th></tr></thead>
              <tbody id="item-review-rows"></tbody>
            </table>
          </div>
          <nav class="admin-pagination" id="item-review-pagination" aria-label="Report pages"></nav>
          <div class="admin-empty-state" id="item-review-empty" hidden>
            <span aria-hidden="true">▤</span><strong>No reports to review</strong><p>New student reports will appear here.</p>
          </div>
        </section>

        <div class="admin-overview-grid">
          <section class="admin-overview-card" aria-labelledby="claims-overview-heading">
            <div class="admin-overview-heading">
              <div><h2 id="claims-overview-heading">Ownership claims</h2><p>Recent requests for found items</p></div>
              <span class="admin-count-pill" id="claim-review-count">Loading…</span>
            </div>
            <div class="admin-activity-list" id="claim-overview-list" aria-live="polite"></div>
            <a class="admin-inline-link" href="admin-claims.php">View all claims <span aria-hidden="true">→</span></a>
          </section>
          <section class="admin-overview-card" aria-labelledby="accounts-overview-heading">
            <div class="admin-overview-heading">
              <div><h2 id="accounts-overview-heading">Student accounts</h2><p>Recently registered students</p></div>
            </div>
            <div class="admin-activity-list" id="account-overview-list" aria-live="polite"></div>
            <a class="admin-inline-link" href="admin-accounts.php">Manage student accounts <span aria-hidden="true">→</span></a>
          </section>
          <section class="admin-overview-card" aria-labelledby="activity-overview-heading">
            <div class="admin-overview-heading">
              <div><h2 id="activity-overview-heading">Recent activity</h2><p>Latest item reports</p></div>
              <span class="admin-activity-icon" aria-hidden="true">◷</span>
            </div>
            <div class="admin-activity-list" id="activity-overview-list" aria-live="polite"></div>
            <a class="admin-inline-link" href="#item-reviews">Open report reviews <span aria-hidden="true">→</span></a>
          </section>
        </div>
        <p class="admin-privacy-note"><span aria-hidden="true">✓</span> <span><strong>Ownership evidence is confidential.</strong> Keep student details private. Verify evidence only before approving a claim.</span><a href="admin-claims.php">Review policy <span aria-hidden="true">→</span></a></p>

        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Account access is managed through the server database.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const errorMessage = document.querySelector("#admin-error");
    let currentItems = [];

    function addCell(row, value) {
      const cell = document.createElement("td");
      cell.textContent = value || "—";
      row.append(cell);
      return cell;
    }

    function makeReviewButton(label, className, action) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = className;
      button.textContent = label;
      button.addEventListener("click", action);
      return button;
    }

    async function sendReview(action, payload) {
      const response = await fetch(`api.php?action=${action}`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Review could not be saved.");
      return result;
    }

    function displayReviewError(error) {
      console.error("Unable to save admin review.", error);
      errorMessage.textContent = error.message || "Review could not be saved.";
      errorMessage.hidden = false;
    }

    function makeOverviewItem(primary, secondary, status, icon) {
      const row = document.createElement("div");
      row.className = "admin-activity-item";
      const mark = document.createElement("span");
      mark.className = "admin-activity-mark";
      mark.setAttribute("aria-hidden", "true");
      mark.textContent = icon;
      const copy = document.createElement("span");
      copy.className = "admin-activity-copy";
      const title = document.createElement("strong");
      title.textContent = primary || "Campus activity";
      const detail = document.createElement("small");
      detail.textContent = secondary || "No additional details";
      copy.append(title, detail);
      row.append(mark, copy);
      if (status) {
        const badge = document.createElement("span");
        badge.className = `status-pill ${status.replaceAll("_", "-")}`;
        badge.textContent = status.replaceAll("_", " ");
        row.append(badge);
      }
      return row;
    }

    function renderItemReviews(items, page = 1) {
      currentItems = items;
      const reviewRows = document.querySelector("#item-review-rows");
      reviewRows.replaceChildren();
      const pending = items.filter((item) => item.status === "pending");
      document.querySelector("#item-review-count").textContent = `${pending.length} pending`;
      document.querySelector("#item-review-empty").hidden = items.length > 0;
      const pageSize = 6;
      const pageCount = Math.ceil(items.length / pageSize);
      const activePage = Math.min(Math.max(page, 1), Math.max(pageCount, 1));
      const start = (activePage - 1) * pageSize;
      for (const item of items.slice(start, start + pageSize)) {
        const row = document.createElement("tr");
        const itemCell = addCell(row, "");
        const itemSummary = document.createElement("div");
        itemSummary.className = "admin-item-summary";
        const thumbnail = document.createElement("span");
        thumbnail.className = "admin-item-thumbnail";
        thumbnail.setAttribute("aria-hidden", "true");
        if (Number(item.has_image)) {
          const image = document.createElement("img");
          image.src = `api.php?action=image&id=${encodeURIComponent(item.id)}`;
          image.alt = "";
          image.loading = "lazy";
          thumbnail.append(image);
        } else {
          thumbnail.textContent = "▤";
        }
        const itemCopy = document.createElement("span");
        itemCopy.className = "admin-item-copy";
        const itemName = document.createElement("strong");
        itemName.textContent = item.title;
        const itemDescription = document.createElement("small");
        itemDescription.className = "table-secondary";
        itemDescription.textContent = `${item.category} · ${item.location} · ${item.incident_date}`;
        itemCopy.append(itemName, itemDescription);
        itemSummary.append(thumbnail, itemCopy);
        itemCell.append(itemSummary);
        addCell(row, item.item_type);
        addCell(row, `${item.reporter_first_name} ${item.reporter_last_name}`);
        const statusCell = addCell(row, "");
        const status = document.createElement("span");
        status.className = `status-pill ${item.status.replaceAll("_", "-")}`;
        status.textContent = item.status.replaceAll("_", " ");
        statusCell.append(status);
        const actionCell = document.createElement("td");
        if (item.status === "pending") {
          actionCell.className = "admin-review-actions";
          actionCell.append(
            makeReviewButton("Publish", "review-approve", async () => {
              try {
                await sendReview("admin-item-status", { itemId: item.id, status: "published" });
                await loadAdminData();
              } catch (error) {
                displayReviewError(error);
              }
            }),
            makeReviewButton("Reject", "review-reject", async () => {
              try {
                await sendReview("admin-item-status", { itemId: item.id, status: "rejected" });
                await loadAdminData();
              } catch (error) {
                displayReviewError(error);
              }
            })
          );
        } else {
          actionCell.textContent = "—";
        }
        row.append(actionCell);
        reviewRows.append(row);
      }
      const pagination = document.querySelector("#item-review-pagination");
      pagination.replaceChildren();
      pagination.hidden = pageCount <= 1;
      if (pageCount > 1) {
        const addPageButton = (label, targetPage, isCurrent = false, ariaLabel = "") => {
          const button = document.createElement("button");
          button.type = "button";
          button.className = isCurrent ? "admin-page-button active" : "admin-page-button";
          button.textContent = label;
          if (ariaLabel) button.setAttribute("aria-label", ariaLabel);
          if (isCurrent) button.setAttribute("aria-current", "page");
          button.disabled = targetPage === activePage;
          button.addEventListener("click", () => renderItemReviews(currentItems, targetPage));
          pagination.append(button);
        };
        addPageButton("‹", Math.max(activePage - 1, 1), false, "Previous page");
        for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
          addPageButton(String(pageNumber), pageNumber, pageNumber === activePage, `Page ${pageNumber}`);
        }
        addPageButton("›", Math.min(activePage + 1, pageCount), false, "Next page");
      }
    }

    function renderClaims(claims) {
      const pendingClaims = claims.filter((claim) => claim.status === "pending");
      document.querySelector("#claim-count").textContent = pendingClaims.length;
      document.querySelector("#claim-review-count").textContent = `${pendingClaims.length} pending`;
      const list = document.querySelector("#claim-overview-list");
      list.replaceChildren();
      const recentClaims = claims.slice(0, 3);
      if (recentClaims.length === 0) {
        list.append(makeOverviewItem("No ownership claims yet", "New requests will appear here", "", "♧"));
        return;
      }
      for (const claim of recentClaims) {
        list.append(makeOverviewItem(
          claim.item_title,
          `${claim.first_name} ${claim.last_name}`,
          claim.status,
          "♧"
        ));
      }
    }

    function renderAccounts(users) {
      const list = document.querySelector("#account-overview-list");
      list.replaceChildren();
      const students = users.filter((user) => user.role === "student").slice(0, 3);
      if (students.length === 0) {
        list.append(makeOverviewItem("No student accounts yet", "Registered students will appear here", "", "♙"));
        return;
      }
      for (const student of students) {
        const details = [student.student_number, student.program].filter(Boolean).join(" · ");
        list.append(makeOverviewItem(
          `${student.first_name} ${student.last_name}`,
          details || student.email,
          "",
          "♙"
        ));
      }
    }

    function renderRecentActivity(items) {
      const list = document.querySelector("#activity-overview-list");
      list.replaceChildren();
      const recentItems = [...items].sort((first, second) =>
        new Date(second.created_at).getTime() - new Date(first.created_at).getTime()
      ).slice(0, 3);
      if (recentItems.length === 0) {
        list.append(makeOverviewItem("No recent reports", "Student reports will appear here", "", "◷"));
        return;
      }
      for (const item of recentItems) {
        list.append(makeOverviewItem(
          item.title,
          `${item.item_type} report · ${item.location}`,
          item.status,
          "◷"
        ));
      }
    }

    async function loadAdminData() {
      try {
        const [summaryResponse, itemResponse, claimResponse] = await Promise.all([
          fetch("api.php?action=admin-summary", { credentials: "same-origin" }),
          fetch("api.php?action=admin-items", { credentials: "same-origin" }),
          fetch("api.php?action=admin-claims", { credentials: "same-origin" })
        ]);
        const [summary, itemResult, claimResult] = await Promise.all([
          summaryResponse.json(),
          itemResponse.json(),
          claimResponse.json()
        ]);
        if (!summaryResponse.ok) throw new Error(summary.error || "Could not load account data.");
        if (!itemResponse.ok) throw new Error(itemResult.error || "Could not load reports.");
        if (!claimResponse.ok) throw new Error(claimResult.error || "Could not load ownership claims.");
        document.querySelector("#student-count").textContent = summary.summary.student;
        document.querySelector("#pending-report-count").textContent = summary.items.pending;
        document.querySelector("#found-report-count").textContent = summary.items.found;
        renderItemReviews(itemResult.items);
        renderClaims(claimResult.claims);
        renderAccounts(summary.users);
        renderRecentActivity(itemResult.items);
      } catch (error) {
        console.error("Unable to load Campus Finds admin data.", error);
        errorMessage.textContent = error.message;
        errorMessage.hidden = false;
        document.querySelector("#item-review-count").textContent = "Unavailable";
      }
    }

    loadAdminData();

    document.querySelector("[data-logout]").addEventListener("click", async () => {
      try {
        const response = await fetch("api.php?action=logout", {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-CSRF-Token": csrfToken }
        });
        if (!response.ok) {
          throw new Error("Unable to end this session.");
        }
        window.location.href = "index.html";
      } catch (error) {
        console.error("Unable to log out of Campus Finds.", error);
        window.alert("Could not log out. Check your connection and try again.");
      }
    });
  </script>
</body>
</html>
