<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_role'] !== 'lab') {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->connect();
$pdo = $conn; // Fix: global access for helper functions

try {
    $lab_tech = get_user_by_id($_SESSION['user_id']);
    if (!$lab_tech) {
        throw new Exception("Lab technician not found.");
    }
} catch (Exception $e) {
    error_log("Lab tech load error: " . $e->getMessage());
    $lab_tech = ['full_name' => 'Unknown', 'department' => 'N/A'];
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = sanitize_input($_POST['request_id']);
    $new_status = sanitize_input($_POST['status']);
    $lab_user_id = $_SESSION['user_id'];

    try {
        // Fetch old status before update
        $stmtOld = $conn->prepare("SELECT status FROM test_requests WHERE id = ?");
        $stmtOld->execute([$request_id]);
        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Update status
        $stmt = $conn->prepare("UPDATE test_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_status, $request_id]);

        // Log audit action with old and new status
        log_audit_action(
            $lab_user_id,
            'UPDATE_TEST_STATUS',
            'test_requests',
            $request_id,
            ['status' => $new_status],
            ['status' => $old['status']]
        );

        $success_message = "Test status updated successfully.";
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
        error_log("Error updating test status: " . $e->getMessage());
    }
}


// Filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'Pending';
$urgency_filter = isset($_GET['urgency']) ? sanitize_input($_GET['urgency']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

// Build query with filters
$where_conditions = ["tr.status = ?"];
$params = [$status_filter];

if (!empty($urgency_filter)) {
    $where_conditions[] = "tr.urgency = ?";
    $params[] = $urgency_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(tr.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch test requests with detailed information
$pending_tests = [];
try {
    $sql = "SELECT 
                tr.id,
                tr.request_id,
                tr.urgency,
                tr.clinical_info,
                tr.provisional_diagnosis,
                tr.collection_date,
                tr.collection_time,
                tr.collected_by,
                tr.status,
                tr.created_at,
                p.patient_id,
                p.first_name,
                p.last_name,
                p.date_of_birth,
                p.gender,
                p.phone,
                CONCAT(u.full_name) as doctor_name,
                u.department as doctor_department,
                GROUP_CONCAT(DISTINCT tc.test_name ORDER BY tc.test_name SEPARATOR ', ') as requested_tests,
                GROUP_CONCAT(DISTINCT tc.test_category ORDER BY tc.test_category SEPARATOR ', ') as test_categories,
                GROUP_CONCAT(DISTINCT tc.sample_type ORDER BY tc.sample_type SEPARATOR ', ') as sample_types,
                COUNT(tri.id) as total_tests
            FROM test_requests tr
            JOIN patients p ON tr.patient_id = p.id
            JOIN users u ON tr.doctor_id = u.id
            LEFT JOIN test_request_items tri ON tr.id = tri.request_id
            LEFT JOIN test_catalog tc ON tri.test_id = tc.id
            WHERE $where_clause
            GROUP BY tr.id
            ORDER BY 
                CASE tr.urgency 
                    WHEN 'STAT' THEN 1
                    WHEN 'Urgent' THEN 2
                    WHEN 'Routine' THEN 3
                END,
                tr.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching pending tests: " . $e->getMessage();
    error_log("Error fetching pending tests: " . $e->getMessage());
}

// Get statistics
$stats = [];
try {
    $stat_queries = [
        'pending' => "SELECT COUNT(*) FROM test_requests WHERE status = 'Pending'",
        'in_progress' => "SELECT COUNT(*) FROM test_requests WHERE status = 'In Progress'",
        'urgent' => "SELECT COUNT(*) FROM test_requests WHERE urgency = 'Urgent' AND status IN ('Pending', 'In Progress')",
        'stat' => "SELECT COUNT(*) FROM test_requests WHERE urgency = 'STAT' AND status IN ('Pending', 'In Progress')"
    ];
    
    foreach ($stat_queries as $key => $query) {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats[$key] = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Doctor Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <div class="p-3">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>  
                    <a class="nav-link" href="sample_tracking.php">
                        <i class="fas fa-vial"></i> Sample Tracking
                    </a>  
                    <a class="nav-link" href="enter_results.php">
                        <i class="fas fa-edit"></i> Enter Results
                    </a>
                    <div class="nav-divider my-3"></div>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="dashboard-header">
                <h2 class="mb-0">
                    <i class="fas fa-microscope text-gold"></i>
                    Laboratory Dashboard
                </h2>
                <p class="mb-0">Welcome back, <?php echo htmlspecialchars($lab_tech['full_name']); ?>!</p>
                        <small class="text-muted">Department: <?php echo htmlspecialchars($lab_tech['department']); ?></small>
                
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-filter"></i> Filter Test Requests
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row">
                        
                        <div class="col-md-3">
                            <label for="urgency" class="form-label">Urgency</label>
                            <select name="urgency" id="urgency" class="form-control">
                                <option value="">All</option>
                                <option value="STAT" <?php echo $urgency_filter === 'STAT' ? 'selected' : ''; ?>>STAT</option>
                                <option value="Urgent" <?php echo $urgency_filter === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="Routine" <?php echo $urgency_filter === 'Routine' ? 'selected' : ''; ?>>Routine</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-gold">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Test Requests Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Test Requests 
                        <span class="badge badge-gold ml-2"><?php echo count($pending_tests); ?> requests</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_tests)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <p>No test requests found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Patient Info</th>
                                        <th>Doctor</th>
                                        <th>Tests Requested</th>
                                        <th>Clinical Info</th>
                                        <th>Urgency</th>
                                        <th>Collection</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_tests as $test): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-gold"><?php echo htmlspecialchars($test['request_id']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($test['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    ID: <?php echo htmlspecialchars($test['patient_id']); ?><br>
                                                    <?php echo htmlspecialchars($test['gender']); ?>, 
                                                    <?php echo date('Y') - date('Y', strtotime($test['date_of_birth'])); ?> years<br>
                                                    <?php if ($test['phone']): ?>
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($test['phone']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($test['doctor_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($test['doctor_department']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo $test['total_tests']; ?> test(s)</strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($test['requested_tests']); ?>
                                                </small>
                                                <br>
                                                <small class="text-gold">
                                                    Samples: <?php echo htmlspecialchars($test['sample_types']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?php if ($test['clinical_info']): ?>
                                                        <strong>Clinical:</strong> <?php echo htmlspecialchars(substr($test['clinical_info'], 0, 100)) . (strlen($test['clinical_info']) > 100 ? '...' : ''); ?>
                                                        <br>
                                                    <?php endif; ?>
                                                    <?php if ($test['provisional_diagnosis']): ?>
                                                        <strong>Diagnosis:</strong> <?php echo htmlspecialchars(substr($test['provisional_diagnosis'], 0, 100)) . (strlen($test['provisional_diagnosis']) > 100 ? '...' : ''); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $urgency_class = '';
                                                $urgency_icon = '';
                                                switch ($test['urgency']) {
                                                    case 'STAT':
                                                        $urgency_class = 'badge-danger';
                                                        $urgency_icon = 'fas fa-bolt';
                                                        break;
                                                    case 'Urgent':
                                                        $urgency_class = 'badge-warning';
                                                        $urgency_icon = 'fas fa-exclamation-triangle';
                                                        break;
                                                    case 'Routine':
                                                        $urgency_class = 'badge-success';
                                                        $urgency_icon = 'fas fa-clock';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $urgency_class; ?>">
                                                    <i class="<?php echo $urgency_icon; ?>"></i>
                                                    <?php echo htmlspecialchars($test['urgency']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($test['collection_date']): ?>
                                                    <strong>Date:</strong> <?php echo date('M j, Y', strtotime($test['collection_date'])); ?>
                                                    <br>
                                                <?php endif; ?>
                                                <?php if ($test['collection_time']): ?>
                                                    <strong>Time:</strong> <?php echo date('g:i A', strtotime($test['collection_time'])); ?>
                                                    <br>
                                                <?php endif; ?>
                                                <?php if ($test['collected_by']): ?>
                                                    <small class="text-muted">By: <?php echo htmlspecialchars($test['collected_by']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            
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
</div>



<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script>
function viewDetails(requestId) {
    $('#detailsModal').modal('show');
    
    // Load details via AJAX
    $.ajax({
        url: 'get_test_details.php',
        method: 'GET',
        data: { id: requestId },
        success: function(response) {
            $('#modalBody').html(response);
        },
        error: function() {
            $('#modalBody').html('<p class="text-danger">Error loading details.</p>');
        }
    });
}

// Auto-refresh every 30 seconds for real-time updates
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>