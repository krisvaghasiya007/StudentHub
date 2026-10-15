<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/models.php';

require_admin();
$entity = ($_GET['entity'] ?? 'events') === 'students' ? 'students' : 'events';

page_start('Live Manager', '../', 'Live Manager (Fetch API)');
?>
<div class="card">
<h2>Asynchronous <?= $entity === 'events' ? 'Events' : 'Students' ?></h2>
<p>This page talks to <code>php/api/<?= e($entity) ?>.php</code> with <code>fetch()</code> - list, add, update and delete without a page reload.</p>
<p>
<a class="btn-link" href="live.php?entity=events" <?= $entity === 'events' ? '' : 'style="background:#777;"' ?>>Events</a>
<a class="btn-link" href="live.php?entity=students" <?= $entity === 'students' ? '' : 'style="background:#777;"' ?>>Students</a>
</p>
</div>
<div id="live-app"
     data-entity="<?= e($entity) ?>"
     data-csrf="<?= e(csrf_token()) ?>"
     data-courses="<?= e(json_encode(COURSES)) ?>"
     data-years="<?= e(json_encode(YEARS)) ?>"
     data-genders="<?= e(json_encode(GENDERS)) ?>"
     data-roles="<?= e(json_encode(ROLES)) ?>"
     data-categories="<?= e(json_encode(EVENT_CATEGORIES)) ?>">
<div class="card">
<h2 id="live-form-title">Add</h2>
<div id="live-message" role="status" aria-live="polite"></div>
<form id="live-form" novalidate></form>
</div>
<div class="card">
<h2>Records <small id="live-total"></small></h2>
<div class="toolbar"><input type="search" id="live-search" placeholder="Search..."></div>
<div class="table-wrap"><table id="live-table"></table></div>
<div class="pagination" id="live-pager"></div>
</div>
</div>
<?php page_end('../', ['admin-live.js']); ?>
