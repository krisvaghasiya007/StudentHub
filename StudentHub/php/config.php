<?php
/* =====================================================
   StudentHub - central settings
   ===================================================== */

// true  = use MySQL (needed for login, students, events, admin dashboard)
// false = Task 1 only (forms are saved to CSV/JSON files)
define('USE_DB', true);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'studenthub');
define('DB_PORT', 3306);

// Paths
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('UPLOAD_DIR', BASE_DIR . '/uploads/posters');
define('UPLOAD_URL', 'uploads/posters');          // relative to site root

// Poster upload rules
define('MAX_POSTER_SIZE', 2 * 1024 * 1024);       // 2 MB
define('ALLOWED_POSTER_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_POSTER_MIME', ['image/jpeg', 'image/png', 'image/webp']);

// Sessions / login
define('SESSION_TIMEOUT', 1800);                  // 30 minutes of inactivity
define('REMEMBER_DAYS', 30);                      // remember-me cookie lifetime
define('REMEMBER_COOKIE', 'studenthub_remember');
define('MAX_LOGIN_ATTEMPTS', 5);                  // failed tries before a short lock
define('LOGIN_LOCK_SECONDS', 300);

// Whitelists shared by forms, API and validation
define('COURSES', [
    'Computer Science Engineering',
    'Information Technology',
    'Electronics & Communication',
    'Mechanical Engineering',
    'Civil Engineering',
]);
define('YEARS', ['1st Year', '2nd Year', '3rd Year', '4th Year']);
define('GENDERS', ['Female', 'Male', 'Other']);
define('ROLES', ['student', 'admin']);
define('EVENT_CATEGORIES', ['Technical', 'Programming', 'Sports', 'Seminar', 'Cultural']);

date_default_timezone_set('Asia/Kolkata');
