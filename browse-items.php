<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$user = campus_require_page_roles(['student', 'admin']);
$isAdmin = $user['role'] === 'admin';
$csrfToken = campus_csrf_token();
if ($isAdmin) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'admin-shell.php';
} else {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'student-shell.php';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153f30">
  <title>Browse lost and found | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php if ($isAdmin) { campus_admin_navigation('browse'); } else { campus_student_sidebar('browse'); } ?>
    <main class="dashboard-main">
      <?php if ($isAdmin) { campus_admin_topbar($user, $csrfToken); } else { campus_student_topbar(campus_public_user($user), $csrfToken); } ?>
      <div class="dashboard-content">
        <div class="dashboard-heading">
          <div>
            <p class="dashboard-eyebrow">Campus community</p>
            <h1>Browse lost and found</h1>
            <p class="dashboard-subtitle">Search reviewed reports from Cavite City Campus.</p>
          </div>
          <?php if ($isAdmin): ?><a class="action-link" href="report-item.php">＋ Report an item</a><?php endif; ?>
        </div>

        <section class="browse-toolbar" aria-label="Filter reports">
          <form id="browse-filter" class="browse-filter">
            <label class="admin-search browse-search">
              <span aria-hidden="true">⌕</span>
              <span class="sr-only">Search reports</span>
              <input id="browse-query" name="q" type="search" placeholder="Search item, category, or location">
            </label>
            <label class="filter-label" for="browse-type">Report type</label>
            <select id="browse-type" name="type">
              <option value="">All reports</option>
              <option value="found">Found items</option>
              <option value="lost">Lost items</option>
            </select>
            <button class="filter-button" type="submit">Search</button>
          </form>
        </section>

        <p class="inline-message" id="browse-message" role="status" aria-live="polite"></p>
        <div class="item-grid" id="item-grid" aria-live="polite"></div>
        <div class="admin-empty-state" id="browse-empty" hidden>
          <span aria-hidden="true">⌕</span>
          <strong>No matching reports yet</strong>
          <p>Try a different search to find a reviewed campus item.</p>
          <?php if ($isAdmin): ?><a href="report-item.php">Report an item</a><?php endif; ?>
        </div>
        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Only admin-reviewed reports appear here.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const filterForm = document.querySelector("#browse-filter");
    const itemGrid = document.querySelector("#item-grid");
    const emptyState = document.querySelector("#browse-empty");
    const message = document.querySelector("#browse-message");
    const params = new URLSearchParams(window.location.search);
    document.querySelector("#browse-query").value = params.get("q") || "";
    document.querySelector("#browse-type").value = params.get("type") || "";

    function renderItems(items) {
      itemGrid.replaceChildren();
      for (const item of items) {
        const card = document.createElement("article");
        card.className = "item-card";
        const imageLink = document.createElement("a");
        imageLink.className = "item-card-image";
        imageLink.href = `item-details.php?id=${encodeURIComponent(item.id)}`;
        if (Number(item.has_image)) {
          const image = document.createElement("img");
          image.src = `api.php?action=image&id=${encodeURIComponent(item.id)}`;
          image.alt = item.title;
          image.loading = "lazy";
          imageLink.append(image);
        } else {
          const placeholder = document.createElement("span");
          placeholder.className = "item-image-placeholder";
          placeholder.setAttribute("aria-hidden", "true");
          placeholder.textContent = item.item_type === "found" ? "▧" : "⌕";
          imageLink.append(placeholder);
        }
        const content = document.createElement("div");
        content.className = "item-card-content";
        const type = document.createElement("span");
        type.className = `status-pill ${item.item_type}`;
        type.textContent = item.item_type === "found" ? "Found" : "Lost";
        const category = document.createElement("span");
        category.className = "item-category";
        category.textContent = item.category;
        const title = document.createElement("h2");
        const titleLink = document.createElement("a");
        titleLink.href = imageLink.href;
        titleLink.textContent = item.title;
        title.append(titleLink);
        const location = document.createElement("p");
        location.className = "item-card-location";
        location.textContent = `⌖ ${item.location}`;
        const date = document.createElement("p");
        date.className = "item-card-date";
        date.textContent = `${item.item_type === "found" ? "Found" : "Lost"} on ${item.incident_date}`;
        const footer = document.createElement("div");
        footer.className = "item-card-footer";
        const reporter = document.createElement("span");
        reporter.textContent = `Reported by ${item.reporter_first_name}`;
        const details = document.createElement("a");
        details.href = imageLink.href;
        details.textContent = "View details →";
        footer.append(reporter, details);
        content.append(type, category, title, location, date, footer);
        card.append(imageLink, content);
        itemGrid.append(card);
      }
      emptyState.hidden = items.length > 0;
    }

    async function loadItems(event) {
      if (event) event.preventDefault();
      const search = new URLSearchParams({
        q: document.querySelector("#browse-query").value.trim(),
        type: document.querySelector("#browse-type").value
      });
      history.replaceState(null, "", `browse-items.php?${search}`);
      message.textContent = "Loading campus reports…";
      emptyState.hidden = true;
      try {
        const response = await fetch(`api.php?action=browse&${search}`, { credentials: "same-origin" });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Unable to load item reports.");
        renderItems(result.items);
        message.textContent = `${result.items.length} reviewed report${result.items.length === 1 ? "" : "s"}`;
      } catch (error) {
        console.error("Unable to load lost-and-found reports.", error);
        message.textContent = error.message;
      }
    }

    filterForm.addEventListener("submit", loadItems);
    loadItems();
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
