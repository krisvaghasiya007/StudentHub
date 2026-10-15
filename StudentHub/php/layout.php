<?php
/* Shared page frame for every PHP-rendered page. $root = path back to the site root ('' or '../'). */
require_once __DIR__ . '/auth.php';

function page_start(string $title, string $root = '', string $heading = ''): void
{
    $user = current_user();
    $menu = nav_items($user);
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | StudentHub</title>
    <link rel="stylesheet" href="<?= e($root) ?>style.css">
</head>
<body>
<h1><?= e($heading ?: $title) ?></h1>
<header class="site-header">
<div class="site-nav">
<a href="<?= e($root) ?>index.html" class="brand">StudentHub</a>
<button class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
<span></span><span></span><span></span>
</button>
<ul class="nav-links">
<?php foreach ($menu as [$label, $href]): ?>
<li><a href="<?= e($root . $href) ?>"<?= basename($href) === $current ? ' class="active"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
</ul>
<button class="theme-toggle" aria-label="Toggle dark mode">🌙 Dark</button>
</div>
</header>
<?php
}

function page_end(string $root = '', array $scripts = []): void
{
    ?>
<footer>
    <p>© 2026 StudentHub Portal | Developed by Kris Vaghasiya</p>
</footer>
<script src="<?= e($root) ?>script.js"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e($root . $s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
