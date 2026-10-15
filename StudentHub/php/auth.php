<?php
/* =====================================================
   Authentication: secure sessions, login state, timeout,
   remember-me tokens, CSRF, role checks, role-based menu.
   ===================================================== */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('STUDENTHUBSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    start_secure_session();
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf']) && is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

/* ---------- Remember-me (selector + hashed validator) ---------- */
function remember_cookie_set(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
}

function remember_issue(int $studentId): void
{
    $selector  = bin2hex(random_bytes(12));          // 24 chars
    $validator = bin2hex(random_bytes(32));          // 64 chars
    $expires   = time() + REMEMBER_DAYS * 86400;
    db_exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');
    db_exec('INSERT INTO remember_tokens (student_id, selector, token_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))',
        'issi', [$studentId, $selector, hash('sha256', $validator), $expires]);
    remember_cookie_set($selector . ':' . $validator, $expires);
}

function remember_login(): ?array
{
    if (!USE_DB || empty($_COOKIE[REMEMBER_COOKIE])) return null;
    $parts = explode(':', (string) $_COOKIE[REMEMBER_COOKIE]);
    if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        remember_cookie_set('', time() - 3600);
        return null;
    }
    try {
        $row = db_one('SELECT t.token_id, t.token_hash, s.*
                       FROM remember_tokens t JOIN students s ON s.student_id = t.student_id
                       WHERE t.selector = ? AND t.expires_at > NOW()', 's', [$parts[0]]);
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $parts[1]))) {
            if ($row) db_exec('DELETE FROM remember_tokens WHERE token_id = ?', 'i', [$row['token_id']]);
            remember_cookie_set('', time() - 3600);
            return null;
        }
        db_exec('DELETE FROM remember_tokens WHERE token_id = ?', 'i', [$row['token_id']]); // rotate
        login_user($row, true);
        return $_SESSION['user'];
    } catch (Throwable $e) {
        error_log('remember_login: ' . $e->getMessage());
        return null;
    }
}

/* ---------- Login / logout ---------- */
function login_user(array $row, bool $remember = false): void
{
    start_secure_session();
    session_regenerate_id(true);                      // prevents session fixation
    $_SESSION['user'] = [
        'id'    => (int) $row['student_id'],
        'name'  => $row['full_name'],
        'email' => $row['email'],
        'roll'  => $row['roll_no'],
        'role'  => $row['role'],
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    unset($_SESSION['captcha'], $_SESSION['login_fail']);
    db_exec('UPDATE students SET last_login = NOW() WHERE student_id = ?', 'i', [(int) $row['student_id']]);
    if ($remember) remember_issue((int) $row['student_id']);
}

function logout_user(): void
{
    start_secure_session();
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $sel = explode(':', (string) $_COOKIE[REMEMBER_COOKIE])[0];
        if (preg_match('/^[a-f0-9]{24}$/', $sel) && USE_DB) {
            try { db_exec('DELETE FROM remember_tokens WHERE selector = ?', 's', [$sel]); } catch (Throwable $e) {}
        }
        remember_cookie_set('', time() - 3600);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/** Returns the logged-in user (session array) or null. Enforces the inactivity timeout. */
function current_user(): ?array
{
    start_secure_session();
    if (!empty($_SESSION['user'])) {
        if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
            unset($_SESSION['user'], $_SESSION['last_activity']);
            if (remember_login()) return $_SESSION['user'];   // user chose "remember me"
            flash_set('warning', 'Your session expired after 30 minutes of inactivity. Please log in again.');
            return null;
        }
        $_SESSION['last_activity'] = time();
        return $_SESSION['user'];
    }
    return remember_login();
}

function role_home(string $role): string
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash_set('error', 'Please log in to access that page.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        page_start('Access Denied', '../');
        echo '<div class="card"><div class="alert alert-error"><strong>403 - Admins only</strong>You do not have permission to view this page.</div>'
           . '<a class="btn-link" href="' . e(url(role_home($u['role']))) . '">Back to my dashboard</a></div>';
        page_end('../');
        exit;
    }
    return $u;
}

/* ---------- Login throttling (per session) ---------- */
function login_locked(): int
{
    start_secure_session();
    $f = $_SESSION['login_fail'] ?? ['count' => 0, 'time' => 0];
    if ($f['count'] >= MAX_LOGIN_ATTEMPTS) {
        $left = LOGIN_LOCK_SECONDS - (time() - $f['time']);
        if ($left > 0) return $left;
        unset($_SESSION['login_fail']);
    }
    return 0;
}

function login_failed(): void
{
    $f = $_SESSION['login_fail'] ?? ['count' => 0, 'time' => 0];
    $_SESSION['login_fail'] = ['count' => $f['count'] + 1, 'time' => time()];
}

/* ---------- Role-based menu (single source for PHP pages + auth.js) ---------- */
function nav_items(?array $user): array
{
    $m = [['Home', 'index.html'], ['About', 'about.html']];
    if (!$user) {
        $m[] = ['Login', 'login.php'];
        $m[] = ['Register', 'register.html'];
    }
    if ($user) {
        $m[] = ['Dashboard', role_home($user['role'])];
        $m[] = ['Courses', 'courses.html'];
        $m[] = ['Assignments', 'assignments.html'];
    }
    $m[] = ['Events', 'events.html'];
    if ($user) {
        $m[] = ['Profile', 'profile.html'];
    }
    if ($user && $user['role'] === 'admin') {
        $m[] = ['Students', 'admin/students.php'];
        $m[] = ['Manage Events', 'admin/events.php'];
        $m[] = ['Live Manager', 'admin/live.php'];
    }
    $m[] = ['Contact', 'contact.html'];
    if ($user) {
        $m[] = ['Logout (' . strtok($user['name'], ' ') . ')', 'php/logout.php'];
    }
    return $m;
}
