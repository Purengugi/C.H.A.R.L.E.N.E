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

// Get report type from URL parameter
$report_type = isset($_GET['report']) ? $_GET['report'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Function to get test requests summary
function getTestRequestsSummary($pdo, $start_date, $end_date) {
    $sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                
                SUM(CASE WHEN urgency = 'STAT' THEN 1 ELSE 0 END) as stat_requests,
                SUM(CASE WHEN urgency = 'Urgent' THEN 1 ELSE 0 END) as urgent_requests
            FROM test_requests 
            WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get test catalog statistics
function getTestCatalogStats($pdo) {
    $sql = "SELECT 
                test_category,
                COUNT(*) as test_count,
                AVG(price) as avg_price,
                AVG(turnaround_time) as avg_turnaround
            FROM test_catalog 
            WHERE is_active = 1
            GROUP BY test_category
            ORDER BY test_count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get doctor performance
function getDoctorPerformance($pdo, $start_date, $end_date) {
    $sql = "SELECT 
                u.full_name,
                u.department,
                COUNT(tr.id) as total_requests,
                SUM(CASE WHEN tr.status = 'Completed' THEN 1 ELSE 0 END) as completed_requests,
                SUM(CASE WHEN tr.urgency = 'STAT' THEN 1 ELSE 0 END) as stat_requests
            FROM users u
            LEFT JOIN test_requests tr ON u.id = tr.doctor_id 
                AND DATE(tr.created_at) BETWEEN ? AND ?
            WHERE u.role = 'doctor' AND u.is_active = 1
            GROUP BY u.id, u.full_name, u.department
            ORDER BY total_requests DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get most requested tests
function getMostRequestedTests($pdo, $start_date, $end_date) {
    $sql = "SELECT 
                tc.test_name,
                tc.test_category,
                tc.price,
                COUNT(tri.id) as request_count,
                SUM(tc.price) as total_revenue
            FROM test_catalog tc
            JOIN test_request_items tri ON tc.id = tri.test_id
            JOIN test_requests tr ON tri.request_id = tr.id
            WHERE DATE(tr.created_at) BETWEEN ? AND ?
            GROUP BY tc.id, tc.test_name, tc.test_category, tc.price
            ORDER BY request_count DESC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get patient demographics
function getPatientDemographics($pdo) {
    $sql = "SELECT 
                gender,
                COUNT(*) as count,
                CASE 
                    WHEN DATEDIFF(CURDATE(), date_of_birth) < 365*18 THEN 'Under 18'
                    WHEN DATEDIFF(CURDATE(), date_of_birth) < 365*35 THEN '18-34'
                    WHEN DATEDIFF(CURDATE(), date_of_birth) < 365*50 THEN '35-49'
                    WHEN DATEDIFF(CURDATE(), date_of_birth) < 365*65 THEN '50-64'
                    ELSE '65+'
                END as age_group
            FROM patients
            GROUP BY gender, age_group
            ORDER BY gender, age_group";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get sample tracking summary
function getSampleTrackingSummary($pdo, $start_date, $end_date) {
    $sql = "SELECT 
                s.status,
                COUNT(*) as count,
                s.sample_type,
                s.storage_location
            FROM samples s
            JOIN test_requests tr ON s.request_id = tr.id
            WHERE DATE(tr.created_at) BETWEEN ? AND ?
            GROUP BY s.status, s.sample_type, s.storage_location
            ORDER BY s.status, count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get turnaround time analysis
function getTurnaroundTimeAnalysis($pdo, $start_date, $end_date) {
    $sql = "SELECT 
                tc.test_name,
                tc.turnaround_time as expected_hours,
                AVG(TIMESTAMPDIFF(HOUR, tr.created_at, 
                    COALESCE(trs.verified_date, trs.performed_date))) as actual_hours,
                COUNT(*) as test_count
            FROM test_catalog tc
            JOIN test_request_items tri ON tc.id = tri.test_id
            JOIN test_requests tr ON tri.request_id = tr.id
            LEFT JOIN test_results trs ON tri.id = trs.test_item_id
            WHERE DATE(tr.created_at) BETWEEN ? AND ?
                AND tr.status = 'Completed'
            GROUP BY tc.id, tc.test_name, tc.turnaround_time
            HAVING test_count > 0
            ORDER BY test_count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle report generation
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'summary':
        $report_data = getTestRequestsSummary($pdo, $start_date, $end_date);
        $report_title = 'Test Requests Summary';
        break;
    case 'catalog':
        $report_data = getTestCatalogStats($pdo);
        $report_title = 'Test Catalog Statistics';
        break;
    case 'doctors':
        $report_data = getDoctorPerformance($pdo, $start_date, $end_date);
        $report_title = 'Doctor Performance Report';
        break;
    case 'popular_tests':
        $report_data = getMostRequestedTests($pdo, $start_date, $end_date);
        $report_title = 'Most Requested Tests';
        break;
    case 'demographics':
        $report_data = getPatientDemographics($pdo);
        $report_title = 'Patient Demographics';
        break;
    case 'samples':
        $report_data = getSampleTrackingSummary($pdo, $start_date, $end_date);
        $report_title = 'Sample Tracking Summary';
        break;
    case 'turnaround':
        $report_data = getTurnaroundTimeAnalysis($pdo, $start_date, $end_date);
        $report_title = 'Turnaround Time Analysis';
        break;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - System Reports</title>
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
                    <a class="nav-link" href="dashboard.php">
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
                    <a class="nav-link active" href="system_reports.php">
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

            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="dashboard-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-0">
                                    <i class="fas fa-chart-bar text-gold"></i> System Reports
                                </h2>
                                <p class="mb-0">Shows comprehensive system reports and analytics</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Content -->
                    <?php if ($report_type && !empty($report_data)): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-chart-line"></i> <?php echo $report_title; ?>
                                            <small class="text-muted">
                                                (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)
                                            </small>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($report_type == 'summary'): ?>
                                            <!-- Test Requests Summary -->
                                            <div class="row">
                                                <div class="col-md-2">
                                                    <div class="stats-card">
                                                        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                                                        <div class="number"><?php echo $report_data['total_requests']; ?></div>
                                                        <div class="label">Total Requests</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="stats-card">
                                                        <div class="icon"><i class="fas fa-clock"></i></div>
                                                        <div class="number"><?php echo $report_data['pending']; ?></div>
                                                        <div class="label">Pending</div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <div class="stats-card">
                                                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                                                        <div class="number"><?php echo $report_data['completed']; ?></div>
                                                        <div class="label">Completed</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="stats-card">
                                                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                                                        <div class="number"><?php echo $report_data['stat_requests']; ?></div>
                                                        <div class="label">STAT Requests</div>
                                                    </div>
                                                </div>
                                                
                                            </div>

                                        <?php elseif ($report_type == 'catalog'): ?>
                                            <!-- Test Catalog Statistics -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Test Category</th>
                                                            <th>Number of Tests</th>
                                                            <th>Average Price</th>
                                                            <th>Avg. Turnaround (Hours)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($row['test_category']); ?></td>
                                                                <td><?php echo $row['test_count']; ?></td>
                                                                <td>KES <?php echo number_format($row['avg_price'], 2); ?></td>
                                                                <td><?php echo round($row['avg_turnaround'], 1); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        <?php elseif ($report_type == 'doctors'): ?>
                                            <!-- Doctor Performance -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Doctor Name</th>
                                                            <th>Department</th>
                                                            <th>Total Requests</th>
                                                            <th>Completed</th>
                                                            <th>STAT Requests</th>
                                                            <th>Completion Rate</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                                                <td><?php echo $row['total_requests']; ?></td>
                                                                <td><?php echo $row['completed_requests']; ?></td>
                                                                <td><?php echo $row['stat_requests']; ?></td>
                                                                <td>
                                                                    <?php 
                                                                    $completion_rate = $row['total_requests'] > 0 ? 
                                                                        ($row['completed_requests'] / $row['total_requests']) * 100 : 0;
                                                                    echo round($completion_rate, 1) . '%';
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        <?php elseif ($report_type == 'popular_tests'): ?>
                                            <!-- Most Requested Tests -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Test Name</th>
                                                            <th>Category</th>
                                                            <th>Requests</th>
                                                            <th>Price (KES)</th>
                                                            <th>Total Revenue (KES)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($row['test_category']); ?></td>
                                                                <td><?php echo $row['request_count']; ?></td>
                                                                <td><?php echo number_format($row['price'], 2); ?></td>
                                                                <td><?php echo number_format($row['total_revenue'], 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        <?php elseif ($report_type == 'demographics'): ?>
                                            <!-- Patient Demographics -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Gender</th>
                                                            <th>Age Group</th>
                                                            <th>Count</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                                <td><?php echo htmlspecialchars($row['age_group']); ?></td>
                                                                <td><?php echo $row['count']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        <?php elseif ($report_type == 'samples'): ?>
                                            <!-- Sample Tracking Summary -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Status</th>
                                                            <th>Sample Type</th>
                                                            <th>Storage Location</th>
                                                            <th>Count</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td>
                                                                    <span class="badge badge-<?php echo $row['status'] == 'Completed' ? 'success' : 'warning'; ?>">
                                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($row['sample_type']); ?></td>
                                                                <td><?php echo htmlspecialchars($row['storage_location']); ?></td>
                                                                <td><?php echo $row['count']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        <?php elseif ($report_type == 'turnaround'): ?>
                                            <!-- Turnaround Time Analysis -->
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Test Name</th>
                                                            <th>Expected Time (Hours)</th>
                                                            <th>Actual Time (Hours)</th>
                                                            <th>Performance</th>
                                                            <th>Test Count</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report_data as $row): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                                                                <td><?php echo $row['expected_hours']; ?></td>
                                                                <td><?php echo round($row['actual_hours'], 1); ?></td>
                                                                <td>
                                                                    <?php 
                                                                    $performance = $row['actual_hours'] <= $row['expected_hours'] ? 'On Time' : 'Delayed';
                                                                    $badge_class = $performance == 'On Time' ? 'success' : 'danger';
                                                                    ?>
                                                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                                                        <?php echo $performance; ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $row['test_count']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($report_type): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                        <h4 class="mt-3">No Data Available</h4>
                                        <p class="text-muted">No data found for the selected report type and date range.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Default Reports Dashboard -->
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clipboard-list text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Test Requests Summary</h5>
                                        <p class="text-muted">Overview of all test requests by status</p>
                                        <a href="?report=summary" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-flask text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Test Catalog Statistics</h5>
                                        <p class="text-muted">Analysis of available tests by category</p>
                                        <a href="?report=catalog" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-md text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Doctor Performance</h5>
                                        <p class="text-muted">Performance metrics for all doctors</p>
                                        <a href="?report=doctors" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-line text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Most Requested Tests</h5>
                                        <p class="text-muted">Top 10 most popular laboratory tests</p>
                                        <a href="?report=popular_tests" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Patient Demographics</h5>
                                        <p class="text-muted">Patient population analysis by age and gender</p>
                                        <a href="?report=demographics" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-vial text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Sample Tracking</h5>
                                        <p class="text-muted">Overview of sample status and locations</p>
                                        <a href="?report=samples" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card card-gold">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock text-gold" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">Turnaround Time Analysis</h5>
                                        <p class="text-muted">Performance vs expected turnaround times</p>
                                        <a href="?report=turnaround" class="btn btn-gold">Show Report</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>