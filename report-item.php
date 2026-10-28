<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$user = campus_require_page_role('admin');
$isAdmin = true;
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
  <title>Report an item | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php if ($isAdmin) { campus_admin_navigation('report'); } else { campus_student_sidebar('report'); } ?>
    <main class="dashboard-main">
      <?php if ($isAdmin) { campus_admin_topbar($user, $csrfToken); } else { campus_student_topbar(campus_public_user($user), $csrfToken); } ?>
      <div class="dashboard-content">
        <section class="report-page" aria-labelledby="report-heading">
          <p class="dashboard-eyebrow">Help your campus community</p>
          <h1 id="report-heading">Report a lost or found item</h1>
          <p class="profile-intro">Share enough detail to help identify the item. Reports are reviewed by School Admin before appearing in browse results.</p>
          <div class="report-layout">
            <form class="profile-card report-form" id="report-form">
              <div class="field">
                <label for="item-type">What are you reporting?</label>
                <select id="item-type" name="itemType" required>
                  <option value="found">I found an item</option>
                  <option value="lost">I lost an item</option>
                </select>
              </div>
              <div class="field">
                <label for="item-title">Item name</label>
                <input id="item-title" name="title" type="text" maxlength="120" placeholder="e.g. Green water bottle" required>
              </div>
              <div class="report-form-grid">
                <div class="field">
                  <label for="item-category">Category</label>
                  <select id="item-category" name="category" required>
                    <option value="">Choose a category</option>
                    <option>Electronics</option>
                    <option>ID / Cards</option>
                    <option>Books &amp; School Supplies</option>
                    <option>Personal Items</option>
                    <option>Clothing &amp; Accessories</option>
                    <option>Keys</option>
                    <option>Other</option>
                  </select>
                </div>
                <div class="field">
                  <label for="incident-date">Date lost or found</label>
                  <input id="incident-date" name="incidentDate" type="date" required>
                </div>
              </div>
              <div class="field">
                <label for="item-location">Location on campus</label>
                <input id="item-location" name="location" type="text" maxlength="160" placeholder="e.g. Library, second floor" required>
              </div>
              <div class="field">
                <label for="item-description">Description</label>
                <textarea id="item-description" name="description" rows="5" minlength="15" maxlength="2000" placeholder="Describe the item and any helpful identifying details. Do not include passwords or sensitive personal information." required></textarea>
              </div>
              <div class="field">
                <label for="item-image">Photo <span class="field-optional">(optional, max 3 MB)</span></label>
                <input id="item-image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <p class="field-hint">JPEG, PNG, or WebP. Avoid photos showing student IDs, phone numbers, or private information.</p>
                <img id="image-preview" class="report-image-preview" alt="Preview of selected item photo" hidden>
              </div>
              <div class="profile-actions">
                <button class="submit-button" id="submit-report" type="submit">Submit for review</button>
                <a class="profile-back-link" href="<?= $isAdmin ? 'admin-dashboard.php' : 'browse-items.php' ?>">Cancel</a>
              </div>
              <p class="profile-message" id="report-message" role="status" aria-live="polite"></p>
            </form>
            <aside class="report-guidance">
              <span class="guide-shield" aria-hidden="true">♧</span>
              <h2>Keep your report safe</h2>
              <ul>
                <li>Do not post passwords or full card/ID numbers.</li>
                <li>Specific proof of ownership should be sent privately through a claim.</li>
                <li>Found items are published only after School Admin review.</li>
                <li>Bring your student ID when coordinating an item return.</li>
              </ul>
            </aside>
          </div>
        </section>
        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Reports and images are stored in the campus database.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const form = document.querySelector("#report-form");
    const message = document.querySelector("#report-message");
    const imageInput = document.querySelector("#item-image");
    const imagePreview = document.querySelector("#image-preview");
    const submitButton = document.querySelector("#submit-report");
    const csrfToken = document.querySelector("[data-logout]").dataset.csrf;
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    document.querySelector("#incident-date").max = new Date().toISOString().slice(0, 10);

    function readImage(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.addEventListener("load", () => resolve(reader.result));
        reader.addEventListener("error", () => reject(new Error("Could not read the selected image.")));
        reader.readAsDataURL(file);
      });
    }

    imageInput.addEventListener("change", () => {
      const file = imageInput.files[0];
      imagePreview.hidden = true;
      if (!file) return;
      if (file.size > 3 * 1024 * 1024 || !["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        imageInput.setCustomValidity("Choose a JPEG, PNG, or WebP image no larger than 3 MB.");
        imageInput.reportValidity();
        imageInput.value = "";
        return;
      }
      imageInput.setCustomValidity("");
      imagePreview.src = URL.createObjectURL(file);
      imagePreview.hidden = false;
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      message.textContent = "";
      message.classList.remove("error");
      submitButton.disabled = true;
      try {
        const data = Object.fromEntries(new FormData(form).entries());
        data.imageData = imageInput.files[0] ? await readImage(imageInput.files[0]) : "";
        delete data.image;
        const response = await fetch("api.php?action=report-item", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
          body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Unable to submit your report.");
        window.location.href = isAdmin
          ? `admin-dashboard.php?reported=${encodeURIComponent(result.itemId)}`
          : `my-reports.php?submitted=${encodeURIComponent(result.itemId)}`;
      } catch (error) {
        console.error("Unable to submit item report.", error);
        message.textContent = error.message || "Could not connect to the server.";
        message.classList.add("error");
      } finally {
        submitButton.disabled = false;
      }
    });

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
