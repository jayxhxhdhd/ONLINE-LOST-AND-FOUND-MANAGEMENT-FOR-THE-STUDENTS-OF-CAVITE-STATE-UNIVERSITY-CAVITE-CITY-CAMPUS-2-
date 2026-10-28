<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$currentUser = campus_require_page_role('student');
$csrfToken = campus_csrf_token();
$database = campus_db();
$summaryQuery = $database->prepare(
    "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
     FROM claims WHERE claimant_id = :user_id"
);
$summaryQuery->execute(['user_id' => $currentUser['id']]);
$summary = $summaryQuery->fetch();
$claimsQuery = $database->prepare(
    'SELECT c.id, c.status, c.created_at, i.id AS item_id, i.title, i.item_type, i.location
     FROM claims c JOIN items i ON i.id = c.item_id
     WHERE c.claimant_id = :user_id
     ORDER BY c.created_at DESC, c.id DESC LIMIT 5'
);
$claimsQuery->execute(['user_id' => $currentUser['id']]);
$recentClaims = $claimsQuery->fetchAll();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'student-shell.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153f30">
  <title>Student overview | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php campus_student_sidebar('overview'); ?>

    <main class="dashboard-main">
      <?php campus_student_topbar(campus_public_user($currentUser), $csrfToken); ?>

      <div class="dashboard-content">
        <div class="dashboard-heading">
          <div>
            <p class="dashboard-eyebrow">Your campus, connected</p>
            <h1>Welcome, <?= htmlspecialchars($currentUser['first_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="dashboard-subtitle">Track the review status of your item ownership claims.</p>
          </div>
        </div>

        <section class="dashboard-hero" aria-labelledby="hero-heading">
          <div>
            <h2 id="hero-heading">Your claims, clearly tracked.</h2>
            <p>School Admin reviews each claim. Pending claims will update here when a decision is made.</p>
          </div>
          <span class="hero-decoration" aria-hidden="true">CvSU</span>
        </section>

        <section class="dashboard-stats claim-summary-stats" aria-label="Your claim status summary">
          <article class="stat-card">
            <span class="stat-icon green" aria-hidden="true">♧</span>
            <div><p>Total claims</p><strong><?= (int) ($summary['total'] ?? 0) ?></strong><small>Ownership requests</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon gold" aria-hidden="true">◷</span>
            <div><p>Pending approval</p><strong><?= (int) ($summary['pending'] ?? 0) ?></strong><small>Waiting for School Admin</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon green" aria-hidden="true">✓</span>
            <div><p>Approved claims</p><strong><?= (int) ($summary['approved'] ?? 0) ?></strong><small>Ready for follow-up</small></div>
          </article>
          <article class="stat-card">
            <span class="stat-icon blue" aria-hidden="true">↩</span>
            <div><p>Declined claims</p><strong><?= (int) ($summary['rejected'] ?? 0) ?></strong><small>Review the item details</small></div>
          </article>
        </section>

        <section class="claim-updates-section" aria-labelledby="claims-heading">
          <div class="section-heading">
            <div>
              <h2 id="claims-heading">Recent claim updates</h2>
              <p class="claim-section-description">See the decision status for each item you claimed.</p>
            </div>
            <a href="my-reports.php">View all claims →</a>
          </div>
          <?php
          $claimStatusLabels = [
              'pending' => 'Pending approval',
              'approved' => 'Approved',
              'rejected' => 'Declined',
          ];
          ?>
          <?php if ($recentClaims): ?>
            <div class="activity-list">
              <?php foreach ($recentClaims as $claim): ?>
                <article class="activity-card">
                  <div class="activity-card-content">
                    <a href="item-details.php?id=<?= (int) $claim['item_id'] ?>"><?= htmlspecialchars($claim['title'], ENT_QUOTES, 'UTF-8') ?></a>
                    <p><?= htmlspecialchars(ucfirst($claim['item_type']), ENT_QUOTES, 'UTF-8') ?> item · <?= htmlspecialchars($claim['location'], ENT_QUOTES, 'UTF-8') ?> · Claimed <?= htmlspecialchars($claim['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="activity-card-footer">
                      <span class="status-pill <?= htmlspecialchars($claim['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($claimStatusLabels[$claim['status']] ?? 'Status unavailable', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-claims">
              <span class="empty-items-icon" aria-hidden="true">♧</span>
              <h3>No claims submitted yet</h3>
              <p>When you submit an ownership claim, its approval status will appear here.</p>
            </div>
          <?php endif; ?>
        </section>

        <section class="dashboard-guide" aria-label="How Campus Finds works">
          <span class="guide-shield" aria-hidden="true">♧</span>
          <strong>Claim status guide</strong>
          <span>Pending approval: being reviewed</span>
          <span>Approved: coordinate with School Admin</span>
          <span>Declined: claim was not approved</span>
        </section>

        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Privacy policy · Campus guidelines</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    document.querySelector("[data-logout]").addEventListener("click", async (event) => {
      const button = event.currentTarget;
      try {
        const response = await fetch("api.php?action=logout", {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-CSRF-Token": button.dataset.csrf }
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
