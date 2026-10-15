<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

$user = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isNew = $id === 0;
$errors = [];
$old = [];

try {
    $existing = $isNew ? null : student_find($id);
    if (!$isNew && !$existing) {
        flash_set('error', 'Student not found.');
        redirect('admin/students.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $old = $_POST;
        if (!csrf_valid()) {
            $errors[] = 'Security token expired. Please try again.';
        } else {
            if (!$isNew && $id === (int) $user['id'] && ($_POST['role'] ?? '') !== 'admin') {
                $errors[] = 'You cannot remove your own admin role.';
            }
            [$c, $verr] = student_validate($_POST, $isNew, $id);
            $errors = array_merge($errors, $verr);
            if (!$errors) {
                try {
                    $isNew ? student_create($c) : student_update($id, $c);
                    flash_set('success', $isNew ? 'Student added successfully.' : 'Student updated successfully.');
                    redirect('admin/students.php');
                } catch (mysqli_sql_exception $ex) {
                    $errors[] = $ex->getCode() === 1062 ? 'Email or roll number already exists.' : 'Database error. Nothing was saved.';
                    if ($ex->getCode() !== 1062) error_log($ex->getMessage());
                }
            }
        }
    } elseif ($existing) {
        $old = ['name' => $existing['full_name'], 'email' => $existing['email'], 'mobile' => $existing['mobile'], 'roll' => $existing['roll_no'],
                'course' => $existing['course'], 'year' => $existing['study_year'], 'gender' => $existing['gender'], 'role' => $existing['role']];
    }
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
}

$v = fn($k, $d = '') => e($old[$k] ?? $d);
page_start($isNew ? 'Add Student' : 'Edit Student', '../');
?>
<div class="form-box" style="width:60%;">
<h2><?= $isNew ? 'Add New Student' : 'Edit Student' ?></h2>
<?php if ($errors): ?>
<div class="alert alert-error"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="student_form.php<?= $isNew ? '' : '?id=' . $id ?>">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= $id ?>">
<label for="name">Full Name</label>
<input type="text" id="name" name="name" value="<?= $v('name') ?>" required maxlength="50">
<label for="email">Email</label>
<input type="email" id="email" name="email" value="<?= $v('email') ?>" required maxlength="100">
<label for="mobile">Mobile</label>
<input type="tel" id="mobile" name="mobile" value="<?= $v('mobile') ?>" required pattern="[6-9][0-9]{9}" maxlength="10">
<label for="roll">Roll Number</label>
<input type="text" id="roll" name="roll" value="<?= $v('roll') ?>" required pattern="[A-Za-z0-9]{5,15}" maxlength="15">
<label for="course">Course</label>
<select id="course" name="course" required><option value="">Select Course</option>
<?php foreach (COURSES as $c): ?><option <?= ($old['course'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
</select>
<label for="year">Year</label>
<select id="year" name="year" required><option value="">Select Year</option>
<?php foreach (YEARS as $y): ?><option <?= ($old['year'] ?? '') === $y ? 'selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
</select>
<label for="gender">Gender</label>
<select id="gender" name="gender" required><option value="">Select Gender</option>
<?php foreach (GENDERS as $g): ?><option <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
</select>
<label for="role">Role</label>
<select id="role" name="role" required>
<?php foreach (ROLES as $r): ?><option value="<?= e($r) ?>" <?= ($old['role'] ?? 'student') === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
</select>
<label for="password">Password <?= $isNew ? '' : '<small>(leave blank to keep current)</small>' ?></label>
<input type="password" id="password" name="password" <?= $isNew ? 'required' : '' ?> autocomplete="new-password" placeholder="Min 8 chars, letter + number + symbol">
<input type="submit" value="<?= $isNew ? 'Add Student' : 'Save Changes' ?>">
<a class="btn-link" href="students.php" style="background:#777;">Cancel</a>
</form>
</div>
<?php page_end('../'); ?>
