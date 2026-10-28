<?php
declare(strict_types=1);

function campus_db(): PDO
{
    static $database = null;
    if ($database instanceof PDO) {
        return $database;
    }

    $path = getenv('CAMPUS_FINDS_DB');
    if (!$path) {
        $baseDirectory = getenv('LOCALAPPDATA') ?: (getenv('HOME') ?: sys_get_temp_dir());
        $dataDirectory = $baseDirectory . DIRECTORY_SEPARATOR . 'CampusFinds';
        if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0700, true) && !is_dir($dataDirectory)) {
            throw new RuntimeException('Unable to create the private database directory.');
        }
        $path = $dataDirectory . DIRECTORY_SEPARATOR . 'campus-finds.sqlite';
    } else {
        $dataDirectory = dirname($path);
        if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0700, true) && !is_dir($dataDirectory)) {
            throw new RuntimeException('Unable to create the database directory.');
        }
    }

    $database = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            student_number TEXT UNIQUE,
            program TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL COLLATE NOCASE UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('student', 'admin')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $database->exec(
        "CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            item_type TEXT NOT NULL CHECK (item_type IN ('lost', 'found')),
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            location TEXT NOT NULL,
            incident_date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'published', 'resolved', 'rejected')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $database->exec(
        "CREATE TABLE IF NOT EXISTS item_images (
            item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE,
            mime_type TEXT NOT NULL CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp')),
            image_data BLOB NOT NULL
        )"
    );
    $database->exec(
        "CREATE TABLE IF NOT EXISTS claims (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            claimant_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            details TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'approved', 'rejected')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (item_id, claimant_id)
        )"
    );
    $database->exec('CREATE INDEX IF NOT EXISTS items_status_created ON items(status, created_at DESC)');
    $database->exec('CREATE INDEX IF NOT EXISTS items_reporter_created ON items(reporter_id, created_at DESC)');
    $database->exec('CREATE INDEX IF NOT EXISTS claims_status_created ON claims(status, created_at DESC)');

    return $database;
}

function campus_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }

    session_name('campus_finds_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function campus_public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'studentNumber' => $user['student_number'],
        'program' => $user['program'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function campus_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = campus_db()->prepare(
        'SELECT id, first_name, last_name, student_number, program, email, role
         FROM users WHERE id = :id'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();
    return $user ?: null;
}

function campus_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function campus_input_text(array $input, string $key): string
{
    $value = $input[$key] ?? '';
    return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
}

function campus_require_api_role(string $role): array
{
    return campus_require_api_roles([$role]);
}

function campus_require_api_roles(array $roles): array
{
    $user = campus_current_user();
    if (!$user) {
        campus_json(['error' => 'Please log in to continue.'], 401);
    }
    if (!in_array($user['role'], $roles, true)) {
        campus_json(['error' => 'You do not have permission to access this page.'], 403);
    }
    return $user;
}

function campus_require_page_role(string $role): array
{
    return campus_require_page_roles([$role]);
}

function campus_require_page_roles(array $roles): array
{
    $user = campus_current_user();
    if (!$user) {
        header('Location: index.html');
        exit;
    }
    if (!in_array($user['role'], $roles, true)) {
        header('Location: ' . ($user['role'] === 'admin' ? 'admin-dashboard.php' : 'student-overview.php'));
        exit;
    }
    return $user;
}

function campus_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campus_require_csrf(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || $expected === '' || !hash_equals($expected, $provided)) {
        campus_json(['error' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

campus_start_session();
