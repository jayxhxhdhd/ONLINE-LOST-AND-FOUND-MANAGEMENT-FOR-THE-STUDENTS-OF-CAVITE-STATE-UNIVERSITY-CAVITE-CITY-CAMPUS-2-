<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
$currentUser = campus_require_page_role('student');
$csrfToken = campus_csrf_token();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'student-shell.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#153f30">
  <title>Student profile | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <div class="dashboard">
    <?php campus_student_sidebar('profile'); ?>

    <main class="dashboard-main">
      <?php campus_student_topbar(campus_public_user($currentUser), $csrfToken); ?>

      <div class="dashboard-content">
        <section class="profile-page" aria-labelledby="profile-heading">
          <p class="dashboard-eyebrow">Student portal</p>
          <h1 id="profile-heading">Student profile</h1>
          <p class="profile-intro">Review and update the student details shown on your Campus Finds profile.</p>

          <div class="profile-card">
            <div class="profile-card-heading">
              <span class="profile-avatar-large" id="profile-avatar" aria-hidden="true">S</span>
              <div>
                <h2 id="profile-display-name">Your profile</h2>
                <p>Cavite State University · Cavite City Campus</p>
              </div>
            </div>

            <form id="profile-form">
              <div class="profile-form-grid">
                <div class="field">
                  <label for="first-name">First name</label>
                  <input id="first-name" name="firstName" type="text" autocomplete="given-name" required>
                </div>
                <div class="field">
                  <label for="last-name">Last name</label>
                  <input id="last-name" name="lastName" type="text" autocomplete="family-name" required>
                </div>
                <div class="field">
                  <label for="student-number">Student number</label>
                  <input id="student-number" name="studentNumber" type="text" autocomplete="off" required>
                </div>
                <div class="field">
                  <label for="program">Program / course</label>
                  <input id="program" name="program" type="text" autocomplete="organization-title" required>
                </div>
                <div class="field">
                  <label for="campus-email">Campus email</label>
                  <input id="campus-email" name="email" type="email" autocomplete="email" pattern=".+@cvsu\.edu\.ph" title="Use your CvSU campus email address." required>
                </div>
              </div>

              <div class="profile-actions">
                <button class="submit-button" type="submit">Save changes</button>
                <a class="profile-back-link" href="student-overview.php">Back to overview</a>
              </div>
              <p class="profile-message" id="profile-message" role="status" aria-live="polite"></p>
            </form>
          </div>

          <p class="profile-notice">Your profile is securely saved to the Campus Finds database.</p>

          <div class="profile-card password-card">
            <div class="profile-card-heading">
              <div>
                <h2>Change password</h2>
                <p>Keep your Campus Finds account secure.</p>
              </div>
            </div>

            <form id="password-form">
              <div class="profile-form-grid">
                <div class="field">
                  <label for="current-password">Current password</label>
                  <input id="current-password" name="currentPassword" type="password" autocomplete="current-password" minlength="8" maxlength="72" required>
                </div>
                <div class="field">
                  <label for="new-password">New password</label>
                  <input id="new-password" name="newPassword" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                </div>
                <div class="field">
                  <label for="confirm-password">Confirm new password</label>
                  <input id="confirm-password" name="confirmPassword" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                </div>
              </div>
              <div class="profile-actions">
                <button class="submit-button" type="submit">Change password</button>
              </div>
              <p class="profile-message" id="password-message" role="status" aria-live="polite"></p>
            </form>
            <p class="password-help">Forgot your password? <a href="mailto:studentaffairs@cvsu.edu.ph?subject=Campus%20Finds%20password%20reset%20request">Contact School Admin for a password reset.</a></p>
          </div>
        </section>

        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Privacy policy · Campus guidelines</span>
        </footer>
      </div>
    </main>
  </div>

  <script>
    const profileForm = document.querySelector("#profile-form");
    const profileMessage = document.querySelector("#profile-message");
    const profileAvatar = document.querySelector("#profile-avatar");
    const profileDisplayName = document.querySelector("#profile-display-name");
    const passwordForm = document.querySelector("#password-form");
    const passwordMessage = document.querySelector("#password-message");

    let profile = <?= json_encode(campus_public_user($currentUser), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const csrfToken = <?= json_encode($csrfToken) ?>;

    function renderProfile(profile) {
      for (const field of profileForm.elements) {
        if (field.name && Object.hasOwn(profile, field.name)) {
          field.value = profile[field.name];
        }
      }

      const fullName = [profile.firstName, profile.lastName].filter(Boolean).join(" ");
      const initial = fullName ? fullName.charAt(0).toUpperCase() : "S";
      profileDisplayName.textContent = fullName || "Your profile";
      profileAvatar.textContent = initial;
      document.querySelector("#user-name").textContent = fullName || "Student";
      document.querySelector("#user-avatar").textContent = initial;
      document.querySelector("#user-program").textContent = profile.program || "Cavite City Campus";
    }

    renderProfile(profile);

    profileForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(profileForm);
      const updatedProfile = Object.fromEntries(formData.entries());
      try {
        const response = await fetch("api.php?action=profile", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
          body: JSON.stringify(updatedProfile)
        });
        const result = await response.json();
        if (!response.ok) {
          profileMessage.textContent = result.error || "Could not save your profile.";
          profileMessage.classList.add("error");
          return;
        }
        profile = result.user;
      } catch (error) {
        console.error("Unable to save the student profile.", error);
        profileMessage.textContent = "Could not connect to the server. Check your connection and try again.";
        profileMessage.classList.add("error");
        return;
      }

      renderProfile(profile);
      profileMessage.textContent = "Your profile changes have been saved to the database.";
      profileMessage.classList.remove("error");
    });

    passwordForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      passwordMessage.textContent = "";
      passwordMessage.classList.remove("error");
      const passwordData = Object.fromEntries(new FormData(passwordForm).entries());
      try {
        const response = await fetch("api.php?action=change-password", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
          body: JSON.stringify(passwordData)
        });
        const result = await response.json();
        if (!response.ok) {
          passwordMessage.textContent = result.error || "Could not change your password.";
          passwordMessage.classList.add("error");
          return;
        }
        passwordForm.reset();
        passwordMessage.textContent = result.message;
      } catch (error) {
        console.error("Unable to change the student password.", error);
        passwordMessage.textContent = "Could not connect to the server. Check your connection and try again.";
        passwordMessage.classList.add("error");
      }
    });

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
