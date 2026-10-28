<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$user = campus_require_page_role('student');
$csrfToken = campus_csrf_token();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'student-shell.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153f30">
    <title>My claims | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php campus_student_sidebar('reports'); ?>
    <main class="dashboard-main">
      <?php campus_student_topbar(campus_public_user($user), $csrfToken); ?>
      <div class="dashboard-content">
        <div class="dashboard-heading">
          <div>
            <p class="dashboard-eyebrow">Your activity</p>
            <h1>My claims</h1>
            <p class="dashboard-subtitle">Track your private ownership claims.</p>
          </div>
          <div class="dashboard-heading-actions">
            <a class="action-link" href="browse-items.php?type=found">⌕ Browse found items</a>
          </div>
        </div>
        <p class="inline-message" id="activity-message" role="status" aria-live="polite"></p>
        <div class="activity-columns">
          <section class="admin-panel activity-panel" aria-labelledby="my-claims-heading">
            <div class="admin-panel-heading">
              <div><h2 id="my-claims-heading">My ownership claims</h2><p>Proof of ownership is private and reviewed by School Admin.</p></div>
              <span class="admin-count-pill" id="claim-count">0 claims</span>
            </div>
            <div class="activity-list" id="my-claim-list"></div>
            <div class="admin-empty-state" id="claims-empty" hidden>
              <span aria-hidden="true">♧</span><strong>No claims yet</strong>
              <p>Browse found items and send a private claim if you recognize one.</p><a href="browse-items.php?type=found">Browse found items</a>
            </div>
          </section>
        </div>
        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Claim evidence is visible only to authorized staff.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const message = document.querySelector("#activity-message");
    const claimList = document.querySelector("#my-claim-list");

    function addActivityCard(container, titleText, subtitleText, status, link, type, imageId) {
      const card = document.createElement("article");
      card.className = "activity-card";
      if (imageId) {
        const image = document.createElement("img");
        image.className = "activity-thumbnail";
        image.src = `api.php?action=image&id=${encodeURIComponent(imageId)}`;
        image.alt = "";
        image.loading = "lazy";
        card.append(image);
      }
      const content = document.createElement("div");
      content.className = "activity-card-content";
      const title = document.createElement("a");
      title.href = link;
      title.textContent = titleText;
      const subtitle = document.createElement("p");
      subtitle.textContent = subtitleText;
      const badge = document.createElement("span");
      badge.className = `status-pill ${status}`;
      badge.textContent = status.replaceAll("_", " ");
      content.append(title, subtitle);
      const footer = document.createElement("div");
      footer.className = "activity-card-footer";
      const reportType = document.createElement("span");
      reportType.className = `status-pill ${type}`;
      reportType.textContent = type;
      footer.append(reportType, badge);
      content.append(footer);
      card.append(content);
      container.append(card);
    }

    async function loadActivity() {
      try {
        const response = await fetch("api.php?action=my-items", { credentials: "same-origin" });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Unable to load your claims.");
        for (const claim of result.claims) {
          addActivityCard(
            claimList,
            claim.title,
            `Ownership claim · ${claim.location} · ${claim.claimed_at}`,
            claim.claim_status,
            `item-details.php?id=${encodeURIComponent(claim.item_id)}`,
            claim.item_type,
            Number(claim.has_image) && claim.item_status !== "rejected" ? claim.item_id : null
          );
        }
        document.querySelector("#claim-count").textContent = `${result.claims.length} claim${result.claims.length === 1 ? "" : "s"}`;
        document.querySelector("#claims-empty").hidden = result.claims.length > 0;
        message.textContent = "";
      } catch (error) {
        console.error("Unable to load student activity.", error);
        message.textContent = error.message;
      }
    }

    loadActivity();
    document.querySelector("[data-logout]").addEventListener("click", async (event) => {
      const button = event.currentTarget;
      try {
        const response = await fetch("api.php?action=logout", { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": button.dataset.csrf } });
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
