<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$user = campus_require_page_roles(['student', 'admin']);
$isAdmin = $user['role'] === 'admin';
$csrfToken = campus_csrf_token();
$itemId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
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
  <title>Item details | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php if ($isAdmin) { campus_admin_navigation('browse'); } else { campus_student_sidebar('browse'); } ?>
    <main class="dashboard-main">
      <?php if ($isAdmin) { campus_admin_topbar($user, $csrfToken); } else { campus_student_topbar(campus_public_user($user), $csrfToken); } ?>
      <div class="dashboard-content">
        <p><a class="profile-back-link" href="browse-items.php">← Back to browse</a></p>
        <p class="inline-message" id="detail-message" role="status" aria-live="polite">Loading item details…</p>
        <article class="item-detail-card" id="item-detail" hidden>
          <div class="item-detail-photo" id="item-photo"></div>
          <div class="item-detail-body">
            <div class="item-detail-labels"><span class="status-pill" id="item-type"></span><span class="item-category" id="item-category"></span></div>
            <h1 id="item-title"></h1>
            <p class="item-detail-meta" id="item-date"></p>
            <p class="item-detail-meta" id="item-location"></p>
            <div class="item-detail-description" id="item-description"></div>
            <div class="item-detail-owner" id="item-owner"></div>
            <form class="claim-form" id="claim-form" hidden>
              <h2>Is this yours?</h2>
              <p>Describe private details only the owner would know. Your proof is visible only to School Admin.</p>
              <label for="claim-details">Proof of ownership</label>
              <textarea id="claim-details" name="details" minlength="15" maxlength="1500" rows="4" placeholder="For example, describe a distinctive mark or what is inside the item." required></textarea>
              <button class="submit-button" type="submit">Submit ownership claim</button>
            </form>
            <p class="profile-message" id="claim-message" role="status" aria-live="polite"></p>
          </div>
        </article>
        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Ownership evidence is kept private.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const itemId = <?= json_encode($itemId) ?>;
    const csrfToken = document.querySelector("[data-logout]").dataset.csrf;
    const itemCard = document.querySelector("#item-detail");
    const statusMessage = document.querySelector("#detail-message");
    const claimForm = document.querySelector("#claim-form");
    const claimMessage = document.querySelector("#claim-message");

    async function loadItem() {
      try {
        const response = await fetch(`api.php?action=item&id=${encodeURIComponent(itemId)}`, { credentials: "same-origin" });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "This item is unavailable.");
        const item = result.item;
        const type = document.querySelector("#item-type");
        type.textContent = item.item_type === "found" ? "Found item" : "Lost item";
        type.classList.add(item.item_type);
        document.querySelector("#item-category").textContent = item.category;
        document.querySelector("#item-title").textContent = item.title;
        document.querySelector("#item-date").textContent = `${item.item_type === "found" ? "Found" : "Lost"} on ${item.incident_date}`;
        document.querySelector("#item-location").textContent = `⌖ ${item.location}`;
        document.querySelector("#item-description").textContent = item.description;
        document.querySelector("#item-owner").textContent = Number(item.is_mine) ? "This is your report." : `Reported by ${item.reporter_first_name}`;
        if (Number(item.has_image)) {
          const image = document.createElement("img");
          image.src = `api.php?action=image&id=${encodeURIComponent(item.id)}`;
          image.alt = item.title;
          document.querySelector("#item-photo").append(image);
        } else {
          const placeholder = document.createElement("span");
          placeholder.className = "item-image-placeholder";
          placeholder.textContent = item.item_type === "found" ? "▧" : "⌕";
          placeholder.setAttribute("aria-hidden", "true");
          document.querySelector("#item-photo").append(placeholder);
        }
        claimForm.hidden = !item.can_claim || <?= $isAdmin ? 'true' : 'false' ?>;
        if (item.my_claim_status) {
          claimMessage.textContent = `Your ownership claim is ${item.my_claim_status}.`;
        }
        if (item.status === "pending") {
          statusMessage.textContent = "This report is awaiting School Admin review and is visible only to you.";
        } else {
          statusMessage.textContent = "";
        }
        itemCard.hidden = false;
      } catch (error) {
        console.error("Unable to load item details.", error);
        statusMessage.textContent = error.message;
      }
    }

    claimForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      claimMessage.textContent = "";
      try {
        const response = await fetch("api.php?action=claim-item", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
          body: JSON.stringify({ itemId, details: new FormData(claimForm).get("details") })
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Unable to submit your claim.");
        claimForm.hidden = true;
        claimMessage.textContent = result.message;
        claimMessage.classList.remove("error");
      } catch (error) {
        console.error("Unable to submit ownership claim.", error);
        claimMessage.textContent = error.message || "Could not connect to the server.";
        claimMessage.classList.add("error");
      }
    });

    loadItem();
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
