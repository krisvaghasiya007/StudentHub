<?php
/* =====================================================
   JSON API: /php/api/students.php   (admin only)
   GET    ?q=&course=&year=&role=&page=   list
   GET    ?id=5                           one
   POST   {name,email,mobile,roll,course,year,gender,role,password}
   PUT    ?id=5 {... password optional}
   DELETE ?id=5
   Write requests need the X-CSRF-Token header.
   ===================================================== */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../models.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    $u = current_user();
    if (!$u)                    json_out(401, ['success' => false, 'message' => 'Please log in again (session expired).']);
    if ($u['role'] !== 'admin') json_out(403, ['success' => false, 'message' => 'Admins only.']);
    if ($method !== 'GET' && !csrf_valid()) json_out(403, ['success' => false, 'message' => 'Invalid security token. Reload the page.']);

    if ($method === 'GET') {
        if ($id) {
            $row = student_find($id);
            $row ? json_out(200, ['success' => true, 'data' => student_public($row)])
                 : json_out(404, ['success' => false, 'message' => 'Student not found.']);
        }
        $per = 8;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$rows, $total] = student_list([
            'q' => $_GET['q'] ?? '', 'course' => $_GET['course'] ?? '', 'year' => $_GET['year'] ?? '', 'role' => $_GET['role'] ?? '',
        ], $page, $per);
        json_out(200, ['success' => true, 'total' => $total, 'page' => $page, 'per_page' => $per,
                       'data' => array_map('student_public', $rows)]);
    }

    if ($method === 'POST') {
        [$c, $err] = student_validate(json_input(), true);
        if ($err) json_out(422, ['success' => false, 'message' => 'Validation failed.', 'errors' => $err]);
        $newId = student_create($c);
        json_out(201, ['success' => true, 'message' => 'Student added.', 'data' => student_public(student_find($newId))]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if (!$id || !student_find($id)) json_out(404, ['success' => false, 'message' => 'Student not found.']);
        $in = json_input();
        if ($id === (int) $u['id'] && ($in['role'] ?? 'admin') !== 'admin') {
            json_out(422, ['success' => false, 'message' => 'You cannot remove your own admin role.']);
        }
        [$c, $err] = student_validate($in, false, $id);
        if ($err) json_out(422, ['success' => false, 'message' => 'Validation failed.', 'errors' => $err]);
        student_update($id, $c);
        json_out(200, ['success' => true, 'message' => 'Student updated.', 'data' => student_public(student_find($id))]);
    }

    if ($method === 'DELETE') {
        if (!$id) json_out(400, ['success' => false, 'message' => 'Missing student id.']);
        if ($id === (int) $u['id']) json_out(422, ['success' => false, 'message' => 'You cannot delete your own account.']);
        student_delete($id) ? json_out(200, ['success' => true, 'message' => 'Student deleted.'])
                            : json_out(404, ['success' => false, 'message' => 'Student not found.']);
    }

    header('Allow: GET, POST, PUT, DELETE');
    json_out(405, ['success' => false, 'message' => 'Method not allowed.']);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) json_out(409, ['success' => false, 'message' => 'Email or roll number already exists.']);
    error_log('api/students: ' . $e->getMessage());
    json_out(500, ['success' => false, 'message' => 'Database error.']);
} catch (Throwable $e) {
    error_log('api/students: ' . $e->getMessage());
    json_out(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
