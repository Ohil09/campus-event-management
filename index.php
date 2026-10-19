<?php
session_start();

if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'organizer':
            header('Location: organizer/organizer-dashboard.php');
            exit;
        case 'student':
            header('Location: student/');
            exit;
        case 'administrator':
            header('Location: admin/');
            exit;
        default:
            header('Location: auth/login.php');
            exit;
    }
} else {
    header('Location: auth/login.php');
    exit;
}

