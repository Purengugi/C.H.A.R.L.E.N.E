<?php
// doctor/logout.php - Doctor Logout Functionality
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$pdo = $db->connect();

// Check if user is logged in and is a doctor
if (!is_logged_in() || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Log the logout action for audit trail
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    
    // Log audit trail
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $user_id,
        'Doctor Logout',
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page with logout confirmation
header("Location: ../index.php?logout=success");
exit();
?>