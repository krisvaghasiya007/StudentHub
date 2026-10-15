<?php
require_once __DIR__ . '/auth.php';
logout_user();
start_secure_session();
flash_set('success', 'You have been logged out successfully.');
redirect('login.php');
