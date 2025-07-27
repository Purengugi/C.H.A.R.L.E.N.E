<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function check_role($required_role) {
    if (!is_logged_in()) {
        redirect('index.php');
    }
    
    if ($_SESSION['user_role'] !== $required_role) {
        redirect('index.php');
    }
}

// Check session timeout
function check_session_timeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        redirect('index.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

// Login function
function login_user($user_data) {
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_name'] = $user_data['full_name'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['last_activity'] = time();
    
    // Log the login
    log_activity('User Login', 'users', $user_data['id']);
}

// Logout function
function logout_user() {
    if (is_logged_in()) {
        log_activity('User Logout', 'users', $_SESSION['user_id']);
    }
    
    session_unset();
    session_destroy();
    redirect('index.php');
}

// Log user activity
function log_activity($action, $table_name = '', $record_id = null, $old_values = null, $new_values = null) {
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':action' => $action,
            ':table_name' => $table_name,
            ':record_id' => $record_id,
            ':old_values' => $old_values ? json_encode($old_values) : null,
            ':new_values' => $new_values ? json_encode($new_values) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Log error but don't stop execution
        error_log("Audit log error: " . $e->getMessage());
    }
}

// Get user info
function get_user_info() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
        'username' => $_SESSION['username']
    ];
}

// Check if user has permission for specific action
function has_permission($action, $resource = null) {
    if (!is_logged_in()) {
        return false;
    }
    
    $role = $_SESSION['user_role'];
    
    switch ($action) {
        case 'view_all_patients':
            return in_array($role, ['admin', 'lab']);
        case 'manage_staff':
            return $role === 'admin';
        case 'create_test_request':
            return $role === 'doctor';
        case 'enter_results':
            return $role === 'lab';
        case 'view_reports':
            return in_array($role, ['admin', 'lab']);
        default:
            return false;
    }
}

// Auto-logout check (call this on every page)
if (is_logged_in()) {
    check_session_timeout();
}
?>