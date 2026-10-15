<?php
/* Student <-> event sign-up (many-to-many via registrations) */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('events.html');

$user = current_user();
if (!$user) {
    flash_set('error', 'Please log in to register for an event.');
    redirect('login.php');
}

$eventId = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT);
$roll    = strtoupper(clean_text($_POST['roll_no'] ?? '', 15));
$back    = ['Back to Events' => '../events.html'];

if (!$eventId || !preg_match(RX_ROLL, $roll)) {
    render_result('Event Registration', 'error', 'Invalid event or roll number.', [], [], $back);
}

try {
    $student = db_one('SELECT student_id, full_name, roll_no FROM students WHERE roll_no = ?', 's', [$roll]);
    if (!$student) {
        render_result('Event Registration', 'error', 'No student found with that roll number.', [], [], $back);
    }
    if ($user['role'] !== 'admin' && (int) $student['student_id'] !== (int) $user['id']) {
        render_result('Event Registration', 'error', 'That roll number does not match your account.', [], [], $back);
    }
    $event = event_find($eventId);
    if (!$event) {
        render_result('Event Registration', 'error', 'Event not found.', [], [], $back);
    }
    if ($event['event_date'] < date('Y-m-d')) {
        render_result('Event Registration', 'error', 'Registration is closed - this event has already taken place.', [], [], $back);
    }
    if ((int) $event['registered'] >= (int) $event['capacity']) {
        render_result('Event Registration', 'error', 'Sorry, this event is full.', [], [], $back);
    }

    db_exec("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'registered')", 'ii', [$student['student_id'], $eventId]);
    render_result('Event Registration', 'success', 'You are registered!', [
        'Student' => $student['full_name'], 'Event' => $event['title'], 'Date' => $event['event_date'], 'Venue' => $event['venue'],
    ], [], $back);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() === 1062) {
        render_result('Event Registration', 'warning', 'You are already registered for this event.', [], [], $back);
    }
    error_log('event_register: ' . $ex->getMessage());
    render_result('Event Registration', 'error', 'Could not complete registration. Please try again.', [], [], $back);
} catch (RuntimeException $ex) {
    render_result('Event Registration', 'error', $ex->getMessage(), [], [], $back);
}
