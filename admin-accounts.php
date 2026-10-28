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
  <title>Registered accounts | Campus Finds</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body admin-dashboard-body">
  <div class="dashboard">
    <?php campus_admin_navigation('accounts'); ?>
    <main class="dashboard-main">
      <?php campus_admin_topbar($currentUser, $csrfToken); ?>
      <div class="dashboard-content">
        <div class="dashboard-heading">
          <div>
            <p class="dashboard-eyebrow">Campus administration</p>
            <h1>Registered accounts</h1>
            <p class="dashboard-subtitle">Search student and School Admin accounts registered in Campus Finds.</p>
          </div>
        </div>
        <p class="admin-data-error" id="accounts-error" role="alert" hidden></p>
        <section class="admin-panel" aria-labelledby="accounts-heading">
          <div class="admin-panel-heading">
            <div>
              <h2 id="accounts-heading">Campus accounts</h2>
              <p>Assign account access carefully. At least one School Admin account must remain active.</p>
            </div>
            <span class="admin-count-pill" id="account-count">Loading…</span>
          </div>
          <p class="account-role-notice" id="account-role-notice" role="status" hidden></p>
          <label class="admin-search">
            <span aria-hidden="true">⌕</span>
            <span class="sr-only">Search accounts</span>
            <input id="account-search" type="search" placeholder="Search name, email, or student number">
          </label>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Name</th><th>Email</th><th>Student number</th><th>Program</th><th>Account role</th><th>Created</th></tr></thead>
              <tbody id="account-rows"></tbody>
            </table>
          </div>
          <div class="admin-empty-state" id="account-empty" hidden>
            <span aria-hidden="true">♙</span>
            <strong>No accounts found</strong>
            <p>Try changing your search or register a student account.</p>
          </div>
        </section>
        <footer class="dashboard-footer">
          <span>Campus Finds · A service for the CvSU Cavite City Campus community</span>
          <span>Account access is managed through the server database.</span>
        </footer>
      </div>
    </main>
  </div>
  <script>
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const currentUserId = <?= json_encode((int) $currentUser['id']) ?>;
    const accountRows = document.querySelector("#account-rows");
    const accountEmpty = document.querySelector("#account-empty");
    const searchInput = document.querySelector("#account-search");
    const errorMessage = document.querySelector("#accounts-error");
    const roleNotice = document.querySelector("#account-role-notice");
    let accounts = [];

    async function updateAccountRole(userId, role) {
      const response = await fetch("api.php?action=admin-user-role", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: JSON.stringify({ userId, role })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Could not update this account role.");
      return result;
    }

    function renderAccounts() {
      const query = searchInput.value.trim().toLowerCase();
      const filtered = accounts.filter((account) =>
        [account.first_name, account.last_name, account.email, account.student_number, account.program, account.role]
          .some((value) => String(value || "").toLowerCase().includes(query))
      );
      accountRows.replaceChildren();
      for (const account of filtered) {
        const row = document.createElement("tr");
        const values = [
          `${account.first_name} ${account.last_name}`,
          account.email,
          account.student_number || "—",
          account.program || "—"
        ];
        for (const value of values) {
          const cell = document.createElement("td");
          cell.textContent = value;
          row.append(cell);
        }
        const roleCell = document.createElement("td");
        const roleSelect = document.createElement("select");
        roleSelect.className = "account-role-select";
        roleSelect.setAttribute("aria-label", `Account role for ${account.first_name} ${account.last_name}`);
        for (const [value, label] of [["student", "Student"], ["admin", "School Admin"]]) {
          const option = document.createElement("option");
          option.value = value;
          option.textContent = label;
          option.selected = account.role === value;
          roleSelect.append(option);
        }
        const isCurrentUser = Number(account.id) === currentUserId;
        roleSelect.disabled = isCurrentUser;
        if (isCurrentUser) roleSelect.title = "You cannot change your own account role.";
        roleSelect.addEventListener("change", async () => {
          const nextRole = roleSelect.value;
          const previousRole = account.role;
          const roleLabel = nextRole === "admin" ? "School Admin" : "Student";
          if (!window.confirm(`Change ${account.first_name} ${account.last_name}'s role to ${roleLabel}?`)) {
            roleSelect.value = previousRole;
            return;
          }
          roleSelect.disabled = true;
          errorMessage.hidden = true;
          roleNotice.hidden = true;
          try {
            await updateAccountRole(account.id, nextRole);
            account.role = nextRole;
            roleNotice.textContent = `${account.first_name} ${account.last_name}'s role was updated to ${roleLabel}.`;
            roleNotice.hidden = false;
            renderAccounts();
          } catch (error) {
            console.error("Unable to update Campus Finds account role.", error);
            roleSelect.value = previousRole;
            roleSelect.disabled = isCurrentUser;
            errorMessage.textContent = error.message || "Could not update this account role.";
            errorMessage.hidden = false;
          }
        });
        roleCell.append(roleSelect);
        row.append(roleCell);
        const createdCell = document.createElement("td");
        createdCell.textContent = account.created_at;
        row.append(createdCell);
        accountRows.append(row);
      }
      accountEmpty.hidden = filtered.length !== 0;
      document.querySelector("#account-count").textContent = `${filtered.length} shown`;
    }

    async function loadAccounts() {
      try {
        const response = await fetch("api.php?action=admin-summary", { credentials: "same-origin" });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Could not load registered accounts.");
        accounts = result.users;
        errorMessage.hidden = true;
        renderAccounts();
      } catch (error) {
        console.error("Unable to load registered Campus Finds accounts.", error);
        errorMessage.textContent = error.message;
        errorMessage.hidden = false;
        document.querySelector("#account-count").textContent = "Unavailable";
      }
    }

    searchInput.addEventListener("input", renderAccounts);
    loadAccounts();
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
