<?php
declare(strict_types=1);

function campus_student_sidebar(string $active): void
{
    $links = [
        'overview' => ['student-overview.php', '▦', 'Overview'],
        'browse' => ['browse-items.php?type=found', '⌕', 'View found items'],
        'reports' => ['my-reports.php', '▤', 'My claims'],
        'profile' => ['student-profile.php', '♙', 'My profile'],
    ];
    ?>
    <aside class="dashboard-sidebar">
      <a class="brand" href="student-overview.php" aria-label="Campus Finds overview">
        <span class="brand-copy">
          <span class="brand-name">CvSU <span class="dashboard-campus-tag">Cavite City</span></span>
          <span class="brand-caption">Cavite State University<br>Cavite City Campus</span>
        </span>
      </a>
      <div class="dashboard-nav-group">
        <a class="dashboard-product" href="student-overview.php">
          <strong>Campus Finds</strong>
          <span>Student lost &amp; found</span>
        </a>
        <p class="dashboard-nav-label"></p>
        <nav class="dashboard-nav" aria-label="Student navigation">
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
          <strong>Safe returns, every time.</strong>
          <p>Ownership details stay private and are only shared with authorized staff.</p>
        </div>
        <a class="dashboard-help" href="mailto:studentaffairs@cvsu.edu.ph">Help &amp; campus guidelines</a>
      </div>
    </aside>
    <?php
}

function campus_student_topbar(array $user, string $csrfToken): void
{
    $name = trim($user['firstName'] . ' ' . $user['lastName']);
    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
    ?>
    <header class="dashboard-topbar">
      <span class="dashboard-campus-name"><span class="mini-seal" aria-hidden="true">Cv</span> Cavite City Campus <span class="portal-pill">Student portal</span></span>
      <div class="dashboard-user">
        <a class="dashboard-profile-link" href="student-profile.php" aria-label="Open student profile">
          <span class="user-avatar" id="user-avatar" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="user-name"><span id="user-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span><small id="user-program"><?= htmlspecialchars($user['program'] ?: 'Cavite City Campus', ENT_QUOTES, 'UTF-8') ?></small></span>
        </a>
        <button type="button" class="logout-link" data-logout data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">Log out</button>
      </div>
    </header>
    <?php
}
?>
