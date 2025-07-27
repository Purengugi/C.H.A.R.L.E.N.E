<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Initialize PDO connection
$database = new Database();
$pdo = $database->connect();

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

// Get dashboard statistics
try {
    // Total staff count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_staff FROM users WHERE role IN ('doctor', 'lab') AND is_active = 1");
    $stmt->execute();
    $total_staff = $stmt->fetch(PDO::FETCH_ASSOC)['total_staff'];

    // Total patients count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_patients FROM patients");
    $stmt->execute();
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'];

    // Total test requests today
    $stmt = $pdo->prepare("SELECT COUNT(*) as today_requests FROM test_requests WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $today_requests = $stmt->fetch(PDO::FETCH_ASSOC)['today_requests'];

    // Pending test requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests FROM test_requests WHERE status = 'Pending'");
    $stmt->execute();
    $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];

    // Recent activities (last 10 activities)
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name 
        FROM audit_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // System statistics by department
    $stmt = $pdo->prepare("
        SELECT 
            u.department,
            COUNT(*) as staff_count,
            AVG(CASE WHEN u.role = 'doctor' THEN 1 ELSE 0 END) * COUNT(*) as doctors,
            AVG(CASE WHEN u.role = 'lab' THEN 1 ELSE 0 END) * COUNT(*) as lab_staff
        FROM users u 
        WHERE u.role IN ('doctor', 'lab') AND u.is_active = 1 
        GROUP BY u.department
    ");
    $stmt->execute();
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly test trends (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as test_count
        FROM test_requests 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top requested tests
    $stmt = $pdo->prepare("
        SELECT 
            tc.test_name,
            COUNT(tri.id) as request_count
        FROM test_request_items tri
        JOIN test_catalog tc ON tri.test_id = tc.id
        WHERE tri.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY tc.test_name
        ORDER BY request_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $total_staff = $total_patients = $today_requests = $pending_requests = 0;
    $recent_activities = $department_stats = $monthly_trends = $top_tests = [];
}

$current_user = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column p-3">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="staff_registration.php">
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
            <div class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid py-4">
                    
                    <!-- Dashboard Header -->
                    <div class="dashboard-header fade-in">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="text-gold mb-2">
                                    <i class="fas fa-shield-alt"></i> Admin Dashboard
                                </h1>
                                <p class="mb-0">System Overview & Management Center</p>
                                <small class="text-muted">Welcome back, <?php echo htmlspecialchars($current_user); ?>!</small>
                            </div>
                            <div class="col-md-4 text-right">
                                <div class="text-gold">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?>
                                </div>
                                <div class="text-muted">
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card card-gold">
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="number"><?php echo number_format($total_staff); ?></div>
                                <div class="label">Active Staff</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card card-gold">
                                <div class="icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="number"><?php echo number_format($total_patients); ?></div>
                                <div class="label">Total Patients</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card card-gold">
                                <div class="icon">
                                    <i class="fas fa-vial"></i>
                                </div>
                                <div class="number"><?php echo number_format($today_requests); ?></div>
                                <div class="label">Today's Requests</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card card-gold">
                                <div class="icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="number"><?php echo number_format($pending_requests); ?></div>
                                <div class="label">Pending Tests</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Analytics -->
                    <div class="row mb-4">
                        <!-- Department Statistics -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-building"></i> Department Statistics
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($department_stats)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Department</th>
                                                        <th class="text-center">Staff Count</th>
                                                        <th class="text-center">Doctors</th>
                                                        <th class="text-center">Lab Staff</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($department_stats as $dept): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($dept['department'] ?? 'Unknown'); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge badge-gold"><?php echo $dept['staff_count']; ?></span>
                                                            </td>
                                                            <td class="text-center"><?php echo floor($dept['doctors']); ?></td>
                                                            <td class="text-center"><?php echo floor($dept['lab_staff']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> No department data available
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Requested Tests -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie"></i> Top Requested Tests (Last 30 Days)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($top_tests)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Test Name</th>
                                                        <th class="text-center">Requests</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_tests as $test): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge badge-gold"><?php echo $test['request_count']; ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> No test data available
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Status and Recent Activities -->
                    <div class="row">
                        <!-- System Status -->
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-server"></i> System Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="status-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Database Connection</span>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        </div>
                                    </div>
                                    <div class="status-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>System Performance</span>
                                            <span class="badge badge-success">
                                                <i class="fas fa-tachometer-alt"></i> Good
                                            </span>
                                        </div>
                                    </div>
                                    <div class="status-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Last Backup</span>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> <?php echo date('M d, Y'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="status-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Security Status</span>
                                            <span class="badge badge-success">
                                                <i class="fas fa-shield-alt"></i> Secure
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history"></i> Recent System Activities
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_activities)): ?>
                                        <div class="activity-list">
                                            <?php foreach ($recent_activities as $activity): ?>
                                                <div class="activity-item mb-3 p-3 bg-light-gray rounded">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></strong>
                                                            <span class="text-muted"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                    <?php else: ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> No recent activities
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);

        // Add fade-in animation to cards
        $(document).ready(function() {
            $('.card').each(function(index) {
                $(this).delay(index * 100).fadeIn(500);
            });
        });
    </script>
   <?php require_once '../includes/footer.php'; ?>
</body>
</html>