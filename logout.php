<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$role = $_GET['role'] ?? '';

if ($role === 'admin') {
    unset($_SESSION['admin_session']);
    unset($_SESSION['admin_auth']);
    setFlash('success', 'Administrative session terminated successfully.');
    redirect('admin.php');
}

unset($_SESSION['student_auth']);
setFlash('success', 'Student session terminated successfully.');
redirect('login.php');
