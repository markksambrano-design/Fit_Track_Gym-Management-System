<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) && $_GET['page'] !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

// Define allowed pages
$allowed_pages = [
    'login' => 'login.php',
    'dashboard' => 'pages/dashboard.php',
    'members' => 'pages/members.php',
    'staff' => 'pages/staff.php',
    'trainer' => 'pages/trainer.php',
    'staff_payroll' => 'pages/staff_payroll.php',
    'payroll_history' => 'pages/payroll_history.php',
    'attendance' => 'pages/attendance.php',
    'payments' => 'pages/payments.php',
    'register' => 'pages/register.php',
    'scanner' => 'pages/scanner.php',
    'walk_in' => 'pages/walk_in.php',
    'walk_in_history' => 'pages/walk_in_history.php',
    'walk_in_details' => 'pages/walk_in_details.php',
    'quick_walk_in' => 'pages/quick_walk_in.php',
    'announcement' => 'pages/announcement.php',
    'feedback' => 'pages/feedback.php',
    'profile' => 'pages/profile.php',
    'logout' => 'logout.php'
];

// Get the requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Check if page is allowed
if (!array_key_exists($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Include the appropriate file
$file_to_include = $allowed_pages[$page];

// For login page, don't include header/footer
if ($page === 'login') {
    include $file_to_include;
    exit;
}

// For logout, just include the file
if ($page === 'logout') {
    include $file_to_include;
    exit;
}

// For all other pages, include the file normally
include $file_to_include;
?>
