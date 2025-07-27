<?php
// Application Configuration
define('APP_NAME', 'LIMS - Laboratory Information Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8081/lims_project/');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lims_hospital');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', 'uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Email Configuration (for notifications)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@hospital.com');
define('SMTP_PASS', '');

// System Settings
define('TIMEZONE', 'Africa/Nairobi');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auto-generate patient and request IDs
function generatePatientID() {
    return 'P' . date('Y') . sprintf('%06d', rand(1, 999999));
}

function generateRequestID() {
    return 'R' . date('Ymd') . sprintf('%04d', rand(1, 9999));
}

function generateSampleID() {
    return 'S' . date('Ymd') . sprintf('%04d', rand(1, 9999));
}

// Common functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function flash_message($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'];
        $message = $_SESSION['flash_message'];
        $class = ($type == 'success') ? 'alert-success' : 'alert-danger';
        
        echo "<div class='alert $class alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='close' data-dismiss='alert'>
                    <span>&times;</span>
                </button>
              </div>";
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}
?>