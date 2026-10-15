<?php
/* Returns login state + role-based menu for auth.js (public pages are static .html) */
require_once __DIR__ . '/../auth.php';
$u = current_user();
json_out(200, [
    'success'   => true,
    'logged_in' => (bool) $u,
    'user'      => $u ? ['name' => $u['name'], 'role' => $u['role']] : null,
    'menu'      => array_map(fn($m) => ['label' => $m[0], 'href' => $m[1]], nav_items($u)),
]);
