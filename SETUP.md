# Campus Finds setup

The login, registration, student activity, and School Admin pages use a PHP backend and a SQLite database. Student accounts, item reports, report photos, ownership claims, and profile updates are saved on the server, not in browser storage. Student registration always assigns the `student` role; it cannot grant School Admin access.

## Requirements

- PHP 8.1 or later with `pdo_sqlite` enabled (included in the project's XAMPP PHP installation).
- A writable local application-data directory.

## Start the application on Windows

Open PowerShell in this project folder and run:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000
```

Keep that terminal open, then visit [http://127.0.0.1:8000](http://127.0.0.1:8000). If you open `index.html` or `register.html` directly with `file://`, the page redirects to the local server. The server must be running because the PHP API and database require it.

By default, the SQLite database is created outside the web folder at `%LOCALAPPDATA%\CampusFinds\campus-finds.sqlite`. To use another database file, set `CAMPUS_FINDS_DB` before starting PHP. Keep database backups private and do not place the database inside a publicly served directory.

## Create the first School Admin

Create an Admin account from PowerShell. The password prompt below is masked and the password is not written into the command itself:

```powershell
$env:CAMPUS_ADMIN_EMAIL = Read-Host 'School Admin CvSU email'
$securePassword = Read-Host 'School Admin password (12+ characters)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
  $env:CAMPUS_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
  & 'C:\xampp\php\php.exe' '.\create-admin.php'
} finally {
  Remove-Item Env:CAMPUS_ADMIN_EMAIL -ErrorAction SilentlyContinue
  Remove-Item Env:CAMPUS_ADMIN_PASSWORD -ErrorAction SilentlyContinue
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}
```

Run this once for each authorized administrator. The script runs only in the command line, requires a `@cvsu.edu.ph` email and a 12-character minimum password, and will not overwrite an existing account.

Students can then create accounts at `register.html` using their CvSU email and log in at `index.html`. Successful logins are routed by the database role. Student pages and School Admin pages are protected by server-side role checks. An authorized School Admin can change account roles from **Registered accounts**; admins cannot change their own role or demote the last School Admin.

## Optional local demo accounts

For local demonstrations only, run `& 'C:\xampp\php\php.exe' '.\seed-demo-accounts.php'` from the project folder. This creates one Student and one School Admin using strong randomly generated passwords, stores only password hashes, and displays the credentials once in the terminal. It refuses to overwrite an existing demo account. Save the passwords privately and remove the demo accounts before any public deployment.

## Lost-and-found workflow

1. A student submits a lost/found report, with an optional JPEG, PNG, or WebP photo (up to 3 MB).
2. The report is saved as `pending` and is not shown in public browse results until a School Admin publishes it.
3. Students can browse and search published reports. They can submit one private ownership claim per found item they did not report.
4. School Admin reviews reports and claims. Approving a claim resolves the item and rejects any other pending claims for it.
5. Students can check report and claim statuses on **My reports & claims**.

The database creates `users`, `items`, `item_images`, and `claims` tables automatically. Photos are stored as validated image BLOBs in SQLite. Pending report photos are only accessible to their reporter and School Admin; published photos are viewable by logged-in students. Ownership evidence is private to the claimant and School Admin.

Student pages: `student-overview.php`, `browse-items.php`, `report-item.php`, `item-details.php?id=ITEM_ID`, `my-reports.php`, and `student-profile.php`. Admin moderation and registered accounts are in `admin-dashboard.php`.

## Database contents

SQLite creates the `users` table automatically. Passwords are stored as hashes using PHP's `password_hash`; plaintext passwords are not stored. Set `CAMPUS_FINDS_DB` to a protected, backed-up location before first launch if this database needs to be shared or retained in a different environment.

This configures a local development database. A shared, internet-accessible production deployment additionally needs a hosted PHP server, database backups, HTTPS, and campus-managed account provisioning.
