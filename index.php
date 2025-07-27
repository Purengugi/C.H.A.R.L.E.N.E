<?php
// index.php - Main Login Page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$database = new Database();
$pdo = $database->connect();

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    redirect(BASE_URL . $user['role'] . '/dashboard.php');

}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $user = authenticate_user($username, $password);
        
        if ($user) {
            login_user($user);
            redirect($user['role'] . '/dashboard.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <link rel="icon" href="assets/images/logo.png" type="image/x-icon">
</head>
<body class="login-page">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-6 d-none d-md-block" style="
    background: linear-gradient(rgba(0,0,0,0.7), rgba(26,26,26,0.8)),
                url('assets/images/image1.webp');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
">
    <div class="overlay" style="position: relative; z-index: 1; padding: 2rem;">
        <div class="content">
            <h2 class="text-white mb-4">
                <img src="assets/images/logo.png" alt="Logo" style="height: 200px; vertical-align: middle; margin-right: 8px;"><br></br>
                Clinical Hub for Accurate Results, Lab Efficiency &amp; Notification Enhancement
            </h2>
            <p class="text-white lead">
                Digitizing healthcare, one test at a time. Streamline your laboratory operations 
                with our comprehensive management system.
            </p>
            <ul class="text-white">
                <li><i class="fas fa-check-circle text-gold"></i> Digital Test Requests</li>
                <li><i class="fas fa-check-circle text-gold"></i> Real-time Results</li>
                <li><i class="fas fa-check-circle text-gold"></i> Sample Tracking</li>
                <li><i class="fas fa-check-circle text-gold"></i> Secure Patient Data</li>
            </ul>
        </div>
    </div>
</div>

            
            <div class="col-md-6 d-flex align-items-center">
                <div class="login-form w-100">
                    <div class="text-center mb-4">
                        <h3 class="text-gold mb-2">Welcome Back</h3>
                        <p class="text-muted">Please sign in to your account</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['timeout'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> Your session has expired. Please login again.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username" class="text-white">Username</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="text-white">Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-gold btn-block btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Contact system administrator for account assistance
                        </small>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    
</body>
</html>