<?php
/* Tasks 1 + 3: server-side registration handler */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.html');
}

// Honeypot: real users never see this field
if (!empty($_POST['website'])) {
    render_result('Registration', 'error', 'Registration could not be processed.', [], ['Spam protection triggered.'], ['Back' => '../register.html']);
}

[$c, $errors] = [null, []];

if (USE_DB) {
    try {
        [$c, $errors] = student_validate($_POST + ['role' => 'student'], true);
    } catch (RuntimeException $e) {
        render_result('Registration', 'error', 'Service unavailable.', [], [$e->getMessage()], ['Back' => '../register.html']);
    }
    $c['role'] = 'student';                       // never accept a role from the public form
} else {
    // File-only mode: same rules, without database duplicate checks
    $c = [
        'name' => clean_text($_POST['name'] ?? '', 50), 'email' => strtolower(clean_text($_POST['email'] ?? '', 100)),
        'mobile' => clean_text($_POST['mobile'] ?? '', 10), 'roll' => strtoupper(clean_text($_POST['roll'] ?? '', 15)),
        'course' => clean_text($_POST['course'] ?? ''), 'year' => clean_text($_POST['year'] ?? ''),
        'gender' => clean_text($_POST['gender'] ?? ''), 'role' => 'student', 'password' => (string) ($_POST['password'] ?? ''),
    ];
    if (!preg_match(RX_NAME, $c['name']))       $errors[] = 'Name should contain only letters and spaces (3-50 characters).';
    if (!preg_match(RX_EMAIL, $c['email']))     $errors[] = 'Please enter a valid email address.';
    if (!preg_match(RX_MOBILE, $c['mobile']))   $errors[] = 'Mobile must be 10 digits starting with 6-9.';
    if (!preg_match(RX_ROLL, $c['roll']))       $errors[] = 'Roll number must be 5-15 letters/digits.';
    if (!in_array($c['course'], COURSES, true)) $errors[] = 'Please select a valid course.';
    if (!in_array($c['year'], YEARS, true))     $errors[] = 'Please select a valid year.';
    if (!in_array($c['gender'], GENDERS, true)) $errors[] = 'Please select a valid gender.';
    if (!preg_match(RX_PASSWORD, $c['password'])) $errors[] = 'Password needs 8+ characters with a letter, a number and a special character.';
}

if (!hash_equals($c['password'], (string) ($_POST['confirm'] ?? ''))) $errors[] = 'Passwords do not match.';
if (empty($_POST['terms'])) $errors[] = 'You must accept the Terms & Conditions.';

if ($errors) {
    render_result('Registration', 'error', 'Please fix the following and try again:', [], $errors, ['Back to form' => '../register.html']);
}

if (USE_DB) {
    try {
        student_create($c);
    } catch (mysqli_sql_exception $ex) {
        $msg = $ex->getCode() === 1062 ? 'This email or roll number is already registered.' : 'Could not save your registration. Please try again.';
        if ($ex->getCode() !== 1062) error_log('register: ' . $ex->getMessage());
        render_result('Registration', 'error', $msg, [], [], ['Back to form' => '../register.html', 'Login' => '../login.php']);
    } catch (RuntimeException $ex) {
        render_result('Registration', 'error', $ex->getMessage(), [], [], ['Back' => '../register.html']);
    }
}

// Task 1: also keep a CSV + JSON copy (never the password)
save_record('registrations', [
    'name' => $c['name'], 'email' => $c['email'], 'mobile' => $c['mobile'], 'roll' => $c['roll'],
    'course' => $c['course'], 'year' => $c['year'], 'gender' => $c['gender'],
]);

render_result('Registration', 'success', 'Registration successful! You can now log in to StudentHub.', [
    'Name' => $c['name'], 'Email' => $c['email'], 'Roll Number' => $c['roll'], 'Course' => $c['course'], 'Year' => $c['year'],
], [], ['Go to Login' => '../login.php', 'Home' => '../index.html']);
