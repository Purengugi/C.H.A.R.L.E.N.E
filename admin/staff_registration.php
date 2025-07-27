<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/functions.php';

// Initialize database connection
$database = new Database();
$pdo = $database->connect();

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$success_message = '';
$error_message = '';

// Process staff registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $department = trim($_POST['department']);

    // Basic validation
    if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
        $error_message = 'Please fill in all required fields.';
    } else if (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if username already exists
            $check_sql = "SELECT id FROM users WHERE username = :username";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':username' => $username]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = 'Username already exists. Please choose a different username.';
            } else {
                // Hash password and insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO users (username, password, full_name, email, phone, role, department, created_at) 
                        VALUES (:username, :password, :full_name, :email, :phone, :role, :department, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $hashed_password,
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':department' => $department
                ]);
                
                $success_message = 'Staff member registered successfully!';
                
                // Clear form data after successful registration
                $_POST = array();
            }
        } catch (Exception $e) {
            error_log("Error registering staff: " . $e->getMessage());
            $error_message = 'An error occurred while registering the staff member. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Register Staff</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-dark-gray">
    <div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 sidebar">
            
            <nav class="nav flex-column p-3">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link active" href="staff_registration.php">
                    <i class="fas fa-user-md"></i> Register Staff
                </a>
                <a class="nav-link" href="manage_staff.php">
                    <i class="fas fa-users-cog"></i> Manage Staff
                </a>
                <a class="nav-link" href="manage_patients.php">
                    <i class="fas fa-users"></i> Manage Patients
                </a>
                <a class="nav-link" href="system_reports.php">
                    <i class="fas fa-chart-bar"></i> System Reports
                </a>
                <a class="nav-link" href="test_catalog.php">
                    <i class="fas fa-list"></i> Test Catalog
                </a>
                <div class="nav-divider my-3"></div>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="dashboard-header mb-4">
                    <h2 class="text-gold mb-2">
                        <i class="fas fa-user-plus"></i> Register New Staff Member
                    </h2>
                    <p class="text-white mb-0">Add doctors, lab technicians, and other staff to the system</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <div class="card card-gold">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-md"></i> Staff Registration Form
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="full_name" class="form-label">
                                            <i class="fas fa-user"></i> Full Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="full_name" 
                                               name="full_name" 
                                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                               required>
                                        <div class="invalid-feedback">
                                            Please provide a valid full name.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user-circle"></i> Username <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                               required>
                                        <div class="invalid-feedback">
                                            Please provide a valid username.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i> Password <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               minlength="6"
                                               required>
                                        <div class="invalid-feedback">
                                            Password must be at least 6 characters long.
                                        </div>
                                        <small class="form-text text-muted">Minimum 6 characters required</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="role" class="form-label">
                                            <i class="fas fa-user-tag"></i> Role <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-control" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                                            <option value="lab" <?php echo (isset($_POST['role']) && $_POST['role'] == 'lab') ? 'selected' : ''; ?>>Lab Staff</option>
                                            
                                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Please select a role.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        <div class="invalid-feedback">
                                            Please provide a valid email address.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="phone" 
                                               name="phone" 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                        <div class="invalid-feedback">
                                            Please provide a valid phone number.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="department" class="form-label">
                                            <i class="fas fa-building"></i> Department
                                        </label>
                                        <select class="form-control" id="department" name="department">
                                            <option value="">Select Department</option>
                                            <option value="Internal Medicine" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Internal Medicine') ? 'selected' : ''; ?>>Internal Medicine</option>
                                            <option value="Pediatrics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                            <option value="Surgery" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                            <option value="Orthopedics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                            <option value="Cardiology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                            <option value="Clinical Laboratory" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Clinical Laboratory') ? 'selected' : ''; ?>>Clinical Laboratory</option>
                                            <option value="Radiology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Radiology') ? 'selected' : ''; ?>>Radiology</option>
                                            <option value="Emergency" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                                            <option value="Administration" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-gold btn-lg">
                                    <i class="fas fa-user-plus"></i> Register Staff Member
                                </button>
                                <a href="manage_staff.php" class="btn btn-secondary btn-lg ml-2">
                                    <i class="fas fa-list"></i> View All Staff
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>