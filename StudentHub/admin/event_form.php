<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isNew = $id === 0;
$errors = [];
$old = [];
$existing = null;

try {
    $existing = $isNew ? null : event_find($id);
    if (!$isNew && !$existing) {
        flash_set('error', 'Event not found.');
        redirect('admin/events.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $old = $_POST;
        if (!csrf_valid()) {
            $errors[] = 'Security token expired. Please try again.';
        } else {
            [$c, $errors] = event_validate($_POST);
            [$poster, $perr] = poster_handle_upload($_FILES['poster'] ?? null);
            if ($perr) $errors[] = $perr;

            if (!$errors) {
                try {
                    if ($isNew) {
                        event_create($c, $poster);
                    } else {
                        event_update($id, $c, $poster, !empty($_POST['remove_poster']));
                    }
                    flash_set('success', $isNew ? 'Event added successfully.' : 'Event updated successfully.');
                    redirect('admin/events.php');
                } catch (mysqli_sql_exception $ex) {
                    poster_delete($poster);                 // do not leave an orphan file
                    error_log($ex->getMessage());
                    $errors[] = 'Database error. Nothing was saved.';
                }
            } else {
                poster_delete($poster);
            }
        }
    } elseif ($existing) {
        $old = ['title' => $existing['title'], 'date' => $existing['event_date'], 'venue' => $existing['venue'],
                'category' => $existing['category'], 'description' => $existing['description'], 'capacity' => $existing['capacity']];
    }
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
}

$v = fn($k, $d = '') => e($old[$k] ?? $d);
page_start($isNew ? 'Add Event' : 'Edit Event', '../');
?>
<div class="form-box" style="width:60%;">
<h2><?= $isNew ? 'Add New Event' : 'Edit Event' ?></h2>
<?php if ($errors): ?>
<div class="alert alert-error"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" action="event_form.php<?= $isNew ? '' : '?id=' . $id ?>">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= $id ?>">
<label for="title">Event Title</label>
<input type="text" id="title" name="title" value="<?= $v('title') ?>" required minlength="3" maxlength="100">
<label for="date">Date</label>
<input type="date" id="date" name="date" value="<?= $v('date') ?>" required>
<label for="venue">Venue</label>
<input type="text" id="venue" name="venue" value="<?= $v('venue') ?>" required maxlength="100">
<label for="category">Category</label>
<select id="category" name="category" required><option value="">Select Category</option>
<?php foreach (EVENT_CATEGORIES as $c): ?><option <?= ($old['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
</select>
<label for="capacity">Capacity (seats)</label>
<input type="number" id="capacity" name="capacity" value="<?= $v('capacity', '100') ?>" min="1" max="5000" required>
<label for="description">Description</label>
<textarea id="description" name="description" rows="5" required minlength="10" maxlength="1000"><?= $v('description') ?></textarea>
<label for="poster">Event Poster <small>(JPG, PNG or WEBP, max 2 MB)</small></label>
<?php if ($existing && $existing['poster']): ?>
<img class="thumb big" src="../<?= e(UPLOAD_URL . '/' . $existing['poster']) ?>" alt="Current poster" style="margin:8px 0;">
<div class="checkbox-line"><input type="checkbox" id="remove_poster" name="remove_poster" value="1"><label for="remove_poster" style="margin:0;">Remove current poster</label></div>
<?php endif; ?>
<input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
<img id="poster-preview" class="thumb big" alt="" style="display:none;margin-bottom:15px;">
<input type="submit" value="<?= $isNew ? 'Add Event' : 'Save Changes' ?>">
<a class="btn-link" href="events.php" style="background:#777;">Cancel</a>
</form>
</div>
<script>
// Friendly client-side check (the server re-validates type, size and content)
document.getElementById('poster').addEventListener('change', function () {
    var f = this.files[0], prev = document.getElementById('poster-preview');
    prev.style.display = 'none';
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { alert('Please choose a JPG, PNG or WEBP image.'); this.value = ''; return; }
    if (f.size > 2 * 1024 * 1024) { alert('Poster must be 2 MB or smaller.'); this.value = ''; return; }
    prev.src = URL.createObjectURL(f); prev.style.display = 'block';
});
</script>
<?php page_end('../'); ?>
