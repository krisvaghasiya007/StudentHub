<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_valid()) {
        flash_set('error', 'Security token expired. Please try again.');
    } else {
        try {
            event_delete((int) ($_POST['id'] ?? 0)) ? flash_set('success', 'Event deleted successfully.') : flash_set('error', 'Event not found.');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            flash_set('error', 'Could not delete the event.');
        }
    }
    header('Location: events.php');
    exit;
}

$f = [
    'q'        => clean_text($_GET['q'] ?? '', 60),
    'category' => in_array($_GET['category'] ?? '', EVENT_CATEGORIES, true) ? $_GET['category'] : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 8;
$rows = [];
$total = 0;
$dbError = '';
try {
    [$rows, $total] = event_list($f, $page, $per);
} catch (Throwable $e) {
    $dbError = 'Could not load events.';
}

page_start('Event Management', '../');
?>
<div class="card">
<h2>Events <small>(<?= $total ?>)</small></h2>
<?= flash_html() ?>
<?php if ($dbError): ?><div class="alert alert-error"><?= e($dbError) ?></div><?php endif; ?>
<form method="get" class="toolbar">
<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search title or venue...">
<select name="category"><option value="">All Categories</option>
<?php foreach (EVENT_CATEGORIES as $c): ?><option <?= $f['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
</select>
<input type="submit" value="Filter" style="flex:0 0 auto;width:auto;">
<a class="btn-link" href="events.php" style="flex:0 0 auto;margin:0;">Reset</a>
</form>
<p><a class="btn-link" href="event_form.php">+ Add Event</a></p>
<div class="table-wrap">
<table>
<tr><th>Poster</th><th>Title</th><th>Date</th><th>Venue</th><th>Category</th><th>Seats</th><th>Actions</th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?php if ($r['poster']): ?><img class="thumb" src="../<?= e(UPLOAD_URL . '/' . $r['poster']) ?>" alt="Poster for <?= e($r['title']) ?>"><?php else: ?>-<?php endif; ?></td>
<td><?= e($r['title']) ?></td><td><?= e($r['event_date']) ?></td><td><?= e($r['venue']) ?></td>
<td><span class="badge"><?= e($r['category']) ?></span></td>
<td><?= (int) $r['registered'] ?> / <?= (int) $r['capacity'] ?></td>
<td class="actions">
<a class="btn-sm" href="event_form.php?id=<?= (int) $r['event_id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this event and all its registrations?');" style="display:inline;">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int) $r['event_id'] ?>">
<button type="submit" class="btn-sm btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; if (!$rows && !$dbError): ?>
<tr><td colspan="7">No events found.</td></tr>
<?php endif; ?>
</table>
</div>
<?= pager_html($total, $page, $per, $f) ?>
</div>
<?php page_end('../'); ?>
