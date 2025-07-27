<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Get user information by ID
function get_user_by_id($user_id) {
    try {
        $database = new Database();
        $conn = $database->connect();

        $sql = "SELECT * FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return false;
    }
}

// Get all active tests grouped by category
function get_tests_by_category() {
    try {
        $database = new Database();
        $conn = $database->connect();

        $sql = "SELECT * FROM test_catalog WHERE is_active = 1 ORDER BY test_category, test_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $tests = $stmt->fetchAll();

        $grouped = [];
        foreach ($tests as $test) {
            $category = $test['test_category'] ?? 'Uncategorized';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $test;
        }

        return $grouped;
    } catch (Exception $e) {
        error_log("Error fetching tests by category: " . $e->getMessage());
        return [];
    }
}

// Get recent patients registered by a specific doctor
function get_recent_patients($doctor_id, $limit = 10) {
    try {
        $database = new Database();
        $conn = $database->connect();

        $sql = "SELECT * FROM patients WHERE created_by = :doctor_id ORDER BY created_at DESC LIMIT :limit";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching recent patients: " . $e->getMessage());
        return [];
    }
}

// User authentication
function authenticate_user($username, $password) {
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $sql = "SELECT * FROM users WHERE username = :username AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':username' => $username]);
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

// Get patient information
function get_patient_by_id($patient_id) {
    try {
        $database = new Database();
        $conn = $database->connect();
        $sql = "SELECT * FROM patients WHERE patient_id = :patient_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':patient_id' => $patient_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching patient: " . $e->getMessage());
        return false;
    }
}

// Get all tests from catalog
function get_all_tests() {
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $sql = "SELECT * FROM test_catalog ORDER BY category, test_name";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching tests: " . $e->getMessage());
        return [];
    }
}

// Format date for display
function format_date($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

// Format datetime for display
function format_datetime($datetime, $format = DISPLAY_DATETIME_FORMAT) {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

// Generate secure random password
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone number (basic)
function validate_phone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

// Get test request status badge
function get_status_badge($status) {
    $badges = [
        'Pending' => 'badge-warning',
        'In Progress' => 'badge-info',
        'Completed' => 'badge-success',
        'Cancelled' => 'badge-danger'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    return "<span class='badge $class'>$status</span>";
}

// Get urgency badge
function get_urgency_badge($urgency) {
    $badges = [
        'Routine' => 'badge-secondary',
        'Urgent' => 'badge-warning',
        'STAT' => 'badge-danger'
    ];
    
    $class = $badges[$urgency] ?? 'badge-secondary';
    return "<span class='badge $class'>$urgency</span>";
}

// Calculate age from date of birth
function calculate_age($dob) {
    $today = new DateTime();
    $birthDate = new DateTime($dob);
    $age = $today->diff($birthDate);
    return $age->y;
}

// Send email notification (basic implementation)
function send_notification($to, $subject, $message) {
    // This is a basic implementation - you can integrate with actual email service
    $headers = "From: " . SMTP_USER . "\r\n";
    $headers .= "Reply-To: " . SMTP_USER . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function log_audit_action($user_id, $action, $table_name, $record_id, $new_values = [], $old_values = []) {
    try {
        $database = new Database();
        $conn = $database->connect();

        // Prepare JSON fields
        $old_values_json = !empty($old_values) ? json_encode($old_values) : null;
        $new_values_json = !empty($new_values) ? json_encode($new_values) : null;

        // Get user agent and IP
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values_json,
            $new_values_json,
            $ip_address,
            $user_agent
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        // Optionally, handle this error in UI if critical
    }
}



?>