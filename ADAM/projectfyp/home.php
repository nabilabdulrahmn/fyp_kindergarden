<?php
// home.php — Role Dispatcher
// Redirects to the correct dashboard based on $_SESSION['role'].
// No content is rendered here; all role-specific pages are self-contained.

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header("Location: admin_home.php");
        break;
    case 'teacher':
        header("Location: teacher_home.php");
        break;
    case 'parent':
        header("Location: parent_home.php");
        break;
    default:
        // Unknown role — destroy session and force re-login
        session_destroy();
        header("Location: login.php?error=invalid_role");
        break;
}
exit();
?>