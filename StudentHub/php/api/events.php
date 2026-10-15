<?php
/* =====================================================
   JSON API: /php/api/events.php
   GET    ?q=&category=&page=&per_page=   list   (public)
   GET    ?id=5                           one    (public)
   POST   {title,date,venue,...}          create (admin)
   PUT    ?id=5 {...}                     update (admin)
   DELETE ?id=5                           delete (admin)
   Write requests need the X-CSRF-Token header.
   ===================================================== */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../models.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    if ($method === 'GET') {
        if ($id) {
            $row = event_find($id);
            $row ? json_out(200, ['success' => true, 'data' => event_public($row)])
                 : json_out(404, ['success' => false, 'message' => 'Event not found.']);
        }
        $per  = isset($_GET['per_page']) ? min(100, max(1, (int) $_GET['per_page'])) : 0;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$rows, $total] = event_list(['q' => $_GET['q'] ?? '', 'category' => $_GET['category'] ?? ''], $page, $per);
        json_out(200, ['success' => true, 'total' => $total, 'page' => $page, 'per_page' => $per,
                       'data' => array_map('event_public', $rows)]);
    }

    // ---- write operations: admin + CSRF ----
    $u = current_user();
    if (!$u)                      json_out(401, ['success' => false, 'message' => 'Please log in again (session expired).']);
    if ($u['role'] !== 'admin')   json_out(403, ['success' => false, 'message' => 'Admins only.']);
    if (!csrf_valid())            json_out(403, ['success' => false, 'message' => 'Invalid security token. Reload the page.']);

    if ($method === 'POST') {
        [$c, $err] = event_validate(json_input());
        if ($err) json_out(422, ['success' => false, 'message' => 'Validation failed.', 'errors' => $err]);
        $newId = event_create($c, null);
        json_out(201, ['success' => true, 'message' => 'Event added.', 'data' => event_public(event_find($newId))]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if (!$id || !event_find($id)) json_out(404, ['success' => false, 'message' => 'Event not found.']);
        [$c, $err] = event_validate(json_input());
        if ($err) json_out(422, ['success' => false, 'message' => 'Validation failed.', 'errors' => $err]);
        event_update($id, $c);
        json_out(200, ['success' => true, 'message' => 'Event updated.', 'data' => event_public(event_find($id))]);
    }

    if ($method === 'DELETE') {
        if (!$id) json_out(400, ['success' => false, 'message' => 'Missing event id.']);
        event_delete($id) ? json_out(200, ['success' => true, 'message' => 'Event deleted.'])
                          : json_out(404, ['success' => false, 'message' => 'Event not found.']);
    }

    header('Allow: GET, POST, PUT, DELETE');
    json_out(405, ['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $e) {
    error_log('api/events: ' . $e->getMessage());
    json_out(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
