<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

$user = require_admin();

// ---- Delete (POST + CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!csrf_valid()) {
        flash_set('error', 'Security token expired. Please try again.');
    } elseif ($id === (int) $user['id']) {
        flash_set('error', 'You cannot delete your own account.');
    } else {
        try {
            student_delete($id) ? flash_set('success', 'Student deleted successfully.') : flash_set('error', 'Student not found.');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            flash_set('error', 'Could not delete the student.');
        }
    }
    header('Location: students.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'course' => $_POST['course'] ?? '', 'year' => $_POST['year'] ?? '', 'role' => $_POST['role_f'] ?? '', 'page' => $_POST['page'] ?? ''])));
    exit;
}

$f = [
    'q'      => clean_text($_GET['q'] ?? '', 60),
    'course' => in_array($_GET['course'] ?? '', COURSES, true) ? $_GET['course'] : '',
    'year'   => in_array($_GET['year'] ?? '', YEARS, true) ? $_GET['year'] : '',
    'role'   => in_array($_GET['role'] ?? '', ROLES, true) ? $_GET['role'] : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 10;
$rows = [];
$total = 0;
$dbError = '';
try {
    [$rows, $total] = student_list($f, $page, $per);
} catch (Throwable $e) {
    $dbError = 'Could not load students.';
}

page_start('Student Management', '../');
?>
<div class="card">
<h2>Students <small>(<?= $total ?>)</small></h2>
<?= flash_html() ?>
<?php if ($dbError): ?><div class="alert alert-error"><?= e($dbError) ?></div><?php endif; ?>
<form method="get" class="toolbar">
<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, email, roll, mobile...">
<select name="course"><option value="">All Courses</option>
<?php foreach (COURSES as $c): ?><option <?= $f['course'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
</select>
<select name="year"><option value="">All Years</option>
<?php foreach (YEARS as $y): ?><option <?= $f['year'] === $y ? 'selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
</select>
<select name="role"><option value="">All Roles</option>
<?php foreach (ROLES as $r): ?><option value="<?= e($r) ?>" <?= $f['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
</select>
<input type="submit" value="Filter" style="flex:0 0 auto;width:auto;">
<a class="btn-link" href="students.php" style="flex:0 0 auto;margin:0;">Reset</a>
</form>
<p><a class="btn-link" href="student_form.php">+ Add Student</a></p>
<div class="table-wrap">
<table>
<tr><th>Roll No</th><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Role</th><th>Actions</th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['roll_no']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['email']) ?></td>
<td><?= e($r['mobile']) ?></td><td><?= e($r['course']) ?></td><td><?= e($r['study_year']) ?></td>
<td><span class="badge"><?= e($r['role']) ?></span></td>
<td class="actions">
<a class="btn-sm" href="student_form.php?id=<?= (int) $r['student_id'] ?>">Edit</a>
<?php if ((int) $r['student_id'] !== (int) $user['id']): ?>
<form method="post" onsubmit="return confirm('Delete this student? This also removes their event registrations.');" style="display:inline;">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int) $r['student_id'] ?>">
<input type="hidden" name="q" value="<?= e($f['q']) ?>">
<input type="hidden" name="course" value="<?= e($f['course']) ?>">
<input type="hidden" name="year" value="<?= e($f['year']) ?>">
<input type="hidden" name="role_f" value="<?= e($f['role']) ?>">
<input type="hidden" name="page" value="<?= $page ?>">
<button type="submit" class="btn-sm btn-danger">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; if (!$rows && !$dbError): ?>
<tr><td colspan="8">No students match your search.</td></tr>
<?php endif; ?>
</table>
</div>
<?= pager_html($total, $page, $per, $f) ?>
</div>
<?php page_end('../'); ?>
