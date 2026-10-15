<?php
/* =====================================================
   Create or reset the admin account. Localhost only.
   Open: http://localhost/StudentHub/php/create_admin.php
   DELETE this file once you are done.
   ===================================================== */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

$email = 'admin@studenthub.local';
$pass  = 'Admin@123';
$hash  = password_hash($pass, PASSWORD_DEFAULT);

try {
    $exists = db_one('SELECT student_id FROM students WHERE email = ?', 's', [$email]);
    if ($exists) {
        db_exec("UPDATE students SET password_hash = ?, role = 'admin' WHERE student_id = ?", 'si', [$hash, $exists['student_id']]);
        echo "Admin password reset.\n";
    } else {
        db_exec("INSERT INTO students (roll_no, full_name, email, mobile, course, study_year, gender, password_hash, role)
                 VALUES ('ADMIN001', 'Portal Administrator', ?, '9000000000', 'Computer Science Engineering', '4th Year', 'Other', ?, 'admin')",
                'ss', [$email, $hash]);
        echo "Admin created.\n";
    }
    echo "Email: $email\nPassword: $pass\nChange this password and delete this file.";
} catch (Throwable $e) {
    echo 'FAILED: ' . $e->getMessage();
}
