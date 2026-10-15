<?php
/* Task 1: contact form handler (CSV + JSON storage) */
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('contact.html');
}
if (!empty($_POST['website'])) {
    render_result('Contact', 'error', 'Message could not be sent.', [], ['Spam protection triggered.'], ['Back' => '../contact.html']);
}

[$c, $errors] = validate_contact($_POST);
if ($errors) {
    render_result('Contact', 'error', 'Please fix the following and try again:', [], $errors, ['Back to form' => '../contact.html']);
}

if (!save_record('contacts', $c)) {
    render_result('Contact', 'error', 'Your message could not be saved. Please try again later.', [], [], ['Back' => '../contact.html']);
}

render_result('Contact', 'success', 'Thank you! Your message has been received.', [
    'Name' => $c['name'], 'Email' => $c['email'], 'Subject' => $c['subject'],
], [], ['Home' => '../index.html']);
