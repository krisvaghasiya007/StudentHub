<?php
require_once __DIR__ . '/auth.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('login.php');

if (!csrf_valid()) {
    flash_set('error', 'Your form expired. Please try again.');
    redirect('login.php');
}
if ($secs = login_locked()) {
    flash_set('error', 'Too many failed attempts. Try again in ' . ceil($secs / 60) . ' minute(s).');
    redirect('login.php');
}

// Server-side CAPTCHA
$expected = $_SESSION['captcha'] ?? null;
unset($_SESSION['captcha']);
$answer = trim((string) ($_POST['captcha'] ?? ''));
if ($expected === null || $answer === '' || (int) $answer !== (int) $expected) {
    login_failed();
    flash_set('error', 'Incorrect CAPTCHA answer. Please try again.');
    redirect('login.php');
}

$identifier = clean_text($_POST['identifier'] ?? '', 100);
$password   = (string) ($_POST['password'] ?? '');
$remember   = !empty($_POST['remember']);

try {
    $row = $identifier === '' ? null :
        db_one('SELECT * FROM students WHERE email = ? OR roll_no = ? LIMIT 1', 'ss', [strtolower($identifier), strtoupper($identifier)]);

    // Always run password_verify so response time does not reveal whether the account exists
    $dummy = '$2y$12$SNySyeZSNg7G9usP4gOdK.KmHN6WvNZftaRF7iqhoZ7hDiBjnqoTq';
    $ok = password_verify($password, $row['password_hash'] ?? $dummy);

    if (!$row || !$ok) {
        login_failed();
        flash_set('error', 'Invalid login details.');
        redirect('login.php');
    }

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE students SET password_hash = ? WHERE student_id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $row['student_id']]);
    }

    login_user($row, $remember);                       // regenerates the session ID
    redirect(role_home($row['role']));                 // role-based redirection
} catch (RuntimeException $e) {
    flash_set('error', $e->getMessage());
    redirect('login.php');
}
