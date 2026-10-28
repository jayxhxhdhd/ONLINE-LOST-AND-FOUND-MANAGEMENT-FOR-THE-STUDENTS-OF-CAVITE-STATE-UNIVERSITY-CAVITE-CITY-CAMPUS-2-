<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';

$accounts = [
    [
        'first_name' => 'Demo',
        'last_name' => 'Student',
        'student_number' => 'DEMO-0001',
        'program' => 'BS Information Technology',
        'email' => 'demo.student@cvsu.edu.ph',
        'role' => 'student',
    ],
    [
        'first_name' => 'Demo',
        'last_name' => 'Admin',
        'student_number' => null,
        'program' => 'Cavite City Campus',
        'email' => 'ashjecel.admin@cvsu.edu.ph',
        'role' => 'admin',
    ],
];

$database = campus_db();
$check = $database->prepare('SELECT email FROM users WHERE email = :email');
foreach ($accounts as $account) {
    $check->execute(['email' => $account['email']]);
    if ($check->fetch()) {
        fwrite(STDERR, "A demo account already exists; no accounts or passwords were changed.\n");
        exit(1);
    }
}

$passwords = [];
$insert = $database->prepare(
    'INSERT INTO users (first_name, last_name, student_number, program, email, password_hash, role)
     VALUES (:first_name, :last_name, :student_number, :program, :email, :password_hash, :role)'
);

$database->beginTransaction();
try {
    foreach ($accounts as $account) {
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        $passwords[$account['email']] = $password;
        $insert->execute($account + ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }
    $database->commit();
} catch (Throwable $error) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    throw $error;
}

fwrite(STDOUT, "Demo accounts created. Save these generated passwords; they are shown only once.\n");
fwrite(STDOUT, "Student: {$accounts[0]['email']} / {$passwords[$accounts[0]['email']]}\n");
fwrite(STDOUT, "School Admin: {$accounts[1]['email']} / {$passwords[$accounts[1]['email']]}\n");
fwrite(STDOUT, "Local development only. Delete these accounts before any public deployment.\n");
