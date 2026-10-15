<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

$user = require_admin();               // admins only
try {
    $s = admin_stats();
} catch (Throwable $e) {
    $s = null;
}

page_start('Admin Dashboard', '../');
?>
<div class="card">
<h2>Welcome, <?= e($user['name']) ?></h2>
<?= flash_html() ?>
<p>Overview of users, events and the latest activity on StudentHub.</p>
<a class="btn-link" href="students.php">Manage Students</a>
<a class="btn-link" href="events.php">Manage Events</a>
<a class="btn-link" href="live.php">Live Manager (AJAX)</a>
</div>
<?php if (!$s): ?>
<div class="card"><div class="alert alert-error">Could not load statistics. Check the database connection.</div></div>
<?php else: ?>
<div class="card">
<h2>Statistics</h2>
<div class="grid grid-4" style="width:100%;margin:10px 0 0;">
<div class="stat-card"><span class="stat-value"><?= $s['users'] ?></span>Total Users</div>
<div class="stat-card"><span class="stat-value"><?= $s['students'] ?></span>Students</div>
<div class="stat-card"><span class="stat-value"><?= $s['events'] ?></span>Events (<?= $s['upcoming'] ?> upcoming)</div>
<div class="stat-card"><span class="stat-value"><?= $s['registrations'] ?></span>Event Registrations</div>
</div>
</div>

<div class="flex-row">
<div class="card">
<h2>Latest Event Registrations</h2>
<table>
<tr><th>Student</th><th>Event</th><th>When</th></tr>
<?php foreach ($s['latest_regs'] as $r): ?>
<tr><td><?= e($r['full_name']) ?><br><small><?= e($r['roll_no']) ?></small></td><td><?= e($r['title']) ?></td><td><?= e($r['registered_at']) ?></td></tr>
<?php endforeach; if (!$s['latest_regs']): ?>
<tr><td colspan="3">No registrations yet.</td></tr>
<?php endif; ?>
</table>
</div>
<div class="card">
<h2>Newest Users</h2>
<table>
<tr><th>Name</th><th>Course</th><th>Joined</th></tr>
<?php foreach ($s['latest_students'] as $r): ?>
<tr><td><?= e($r['full_name']) ?><br><small><?= e($r['email']) ?></small></td><td><?= e($r['course']) ?></td><td><?= e(substr($r['created_at'], 0, 10)) ?></td></tr>
<?php endforeach; ?>
</table>
</div>
</div>

<div class="card">
<h2>Registrations per Event</h2>
<?php foreach ($s['per_event'] as $r):
    $pct = $r['capacity'] > 0 ? min(100, round($r['cnt'] / $r['capacity'] * 100)) : 0; ?>
<div style="margin-bottom:12px;">
<strong><?= e($r['title']) ?></strong> <small>(<?= (int) $r['cnt'] ?> / <?= (int) $r['capacity'] ?>)</small>
<div class="bar"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_end('../'); ?>
