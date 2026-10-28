<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';

$email = strtolower(trim((string) getenv('CAMPUS_ADMIN_EMAIL')));
$password = (string) getenv('CAMPUS_ADMIN_PASSWORD');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@cvsu.edu.ph')) {
    fwrite(STDERR, "Set CAMPUS_ADMIN_EMAIL to a valid @cvsu.edu.ph address.\n");
    exit(1);
}
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set CAMPUS_ADMIN_PASSWORD to a password between 12 and 72 characters.\n");
    exit(1);
}

$statement = campus_db()->prepare(
    'INSERT INTO users (first_name, last_name, program, email, password_hash, role)
     VALUES (:first_name, :last_name, :program, :email, :password_hash, \'admin\')'
);
try {
    $statement->execute([
        'first_name' => 'School',
        'last_name' => 'Admin',
        'program' => 'Cavite City Campus',
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $error) {
    if ($error->getCode() === '23000') {
        fwrite(STDERR, "That email is already registered. No account was changed.\n");
        exit(1);
    }
    throw $error;
}

fwrite(STDOUT, "School Admin account created for {$email}.\n");
