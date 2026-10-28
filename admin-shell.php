<?php
declare(strict_types=1);

function campus_admin_navigation(string $active): void
{
    $links = [
        'overview' => ['admin-dashboard.php', '▦', 'Overview'],
        'browse' => ['browse-items.php', '⌕', 'Lost & found'],
        'claims' => ['admin-claims.php', '♧', 'Ownership claims'],
        'accounts' => ['admin-accounts.php', '♙', 'Student accounts'],
        'report' => ['report-item.php', '＋', 'Report an item'],
        'monthly-report' => ['admin-monthly-report.php', '▤', 'Monthly report'],
    ];
    ?>
    <aside class="dashboard-sidebar">
      <a class="brand" href="admin-dashboard.php" aria-label="Campus Finds administration overview">
        <span class="brand-copy">
          <span class="brand-name">CvSU <span class="dashboard-campus-tag">Cavite City</span></span>
          <span class="brand-caption">Cavite State University<br>Cavite City Campus</span>
        </span>
      </a>
      <div class="dashboard-nav-group">
        <a class="dashboard-product" href="admin-dashboard.php">
          <strong>Campus Finds</strong>
          <span>Administration</span>
        </a>
        <p class="dashboard-nav-label">Campus administration</p>
        <nav class="dashboard-nav" aria-label="Admin navigation">
          <?php foreach ($links as $key => [$url, $icon, $label]): ?>
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>>
              <span aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="dashboard-sidebar-bottom">
        <div class="dashboard-tip">
          <span aria-hidden="true">✧</span>
          <strong>Trusted access. Responsible care.</strong>
          <p>Student details and ownership evidence are private. Review them only when needed.</p>
        </div>
        <a class="dashboard-help" href="admin-claims.php">Review ownership claims</a>
      </div>
    </aside>
    <?php
}

function campus_admin_topbar(array $user, string $csrfToken): void
{
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
    ?>
    <header class="dashboard-topbar">
      <span class="dashboard-campus-name"><span class="mini-seal" aria-hidden="true">Cv</span> Cavite City Campus <span class="portal-pill">School Admin</span></span>
      <div class="dashboard-user">
        <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="user-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?><small>Cavite City Campus</small></span>
        <button type="button" class="logout-link" data-logout data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">Log out</button>
      </div>
    </header>
    <?php
}
?>
