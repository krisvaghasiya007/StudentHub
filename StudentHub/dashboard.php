<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/models.php';

$user = require_login();
if ($user['role'] === 'admin') {            // role-based redirection
    redirect('admin/dashboard.php');
}

$upcoming = 0;
$myRegs = 0;
try {
    $upcoming = (int) db_value('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()');
    $myRegs   = (int) db_value("SELECT COUNT(*) FROM registrations WHERE student_id = ? AND status = 'registered'", 'i', [$user['id']]);
} catch (Throwable $e) { /* show 0 if DB is down */ }

page_start('Student Dashboard');
?>
<div class="card">
<h2>Welcome, <?= e($user['name']) ?>!</h2>
<?= flash_html() ?>
<p>
Welcome to your StudentHub Dashboard. This dashboard provides an overview
of your academic activities. You can view your enrolled courses,
check assignment status, stay updated with upcoming college events,
and monitor your attendance from one place.
</p>
</div>
<div class="card">
<h2>Academic Overview</h2>
<div class="grid grid-4" style="width:100%;margin:10px 0 0;">
<div class="stat-card"><span class="stat-value">4</span>Total Courses</div>
<div class="stat-card"><span class="stat-value">4</span>Assignments Pending</div>
<div class="stat-card"><span class="stat-value"><?= $upcoming ?></span>Upcoming Events</div>
<div class="stat-card"><span class="stat-value"><?= $myRegs ?></span>My Event Registrations</div>
</div>
</div>
<div class="card">
<h2>Latest Notifications</h2>
<div class="toolbar">
<input type="search" id="notice-search" placeholder="Search notifications...">
<select id="notice-filter">
<option value="">All Types</option>
<option value="Assignment">Assignment</option>
<option value="Event">Event</option>
<option value="Exam">Exam</option>
<option value="General">General</option>
</select>
</div>
<div id="notice-list"><p class="loading-state">Loading notifications...</p></div>
<div class="pagination" id="notice-pagination"></div>
</div>
<div class="card">
<h2>Student Tips</h2>
<ul>
    <li>Complete assignments before the deadline.</li>
    <li>Maintain attendance above 75%.</li>
    <li>Participate in college workshops and technical events.</li>
    <li>Regularly check notices and announcements.</li>
</ul>
</div>
<?php page_end('', ['notices.js']); ?>
