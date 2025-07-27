<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Check if user is logged in and has lab role
if (!is_logged_in() || $_SESSION['user_role'] !== 'lab') {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->connect();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_results'])) {
        $test_item_id = sanitize_input($_POST['test_item_id']);
        $result_value = sanitize_input($_POST['result_value']);
        $result_status = sanitize_input($_POST['result_status']);
        $reference_range = sanitize_input($_POST['reference_range']);
        $units = sanitize_input($_POST['units']);
        $method = sanitize_input($_POST['method']);
        $comments = sanitize_input($_POST['comments']);
        $performed_by = $_SESSION['user_id'];
        
        try {
            $conn->beginTransaction();
            
            // Insert test result
            $sql = "INSERT INTO test_results (test_item_id, result_value, result_status, reference_range, units, method, performed_by, performed_date, comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$test_item_id, $result_value, $result_status, $reference_range, $units, $method, $performed_by, $comments]);
            
            // Update test request item status
            $sql = "UPDATE test_request_items SET status = 'Completed' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$test_item_id]);
            
            // Check if all tests in the request are completed
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed 
                    FROM test_request_items 
                    WHERE request_id = (SELECT request_id FROM test_request_items WHERE id = ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$test_item_id]);
            $result = $stmt->fetch();
            
            // If all tests are completed, update the main request status
            if ($result['total'] == $result['completed']) {
                // Fetch the correct test_requests.id
                $sql = "SELECT request_id FROM test_request_items WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$test_item_id]);
                $reqRow = $stmt->fetch();
                $request_id = $reqRow ? $reqRow['request_id'] : null;

                if ($request_id) {
                    $sql = "UPDATE test_requests SET status = 'Completed', updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$request_id]);
                }
            }
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'INSERT', 'test_results', $test_item_id, null, [
                'test_item_id' => $test_item_id,
                'result_value' => $result_value,
                'result_status' => $result_status
            ]);
            
            $conn->commit();
            $message = 'Test result entered successfully!';
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error entering test result: ' . $e->getMessage();
            error_log($error);
        }
    }
}

// Get test item details if ID is provided
$test_item = null;
if (isset($_GET['id'])) {
    $test_item_id = sanitize_input($_GET['id']);
    
    $sql = "SELECT tri.*, tc.test_name, tc.test_code, tc.reference_range, tc.units, tc.sample_type,
                   tr.request_id, tr.clinical_info, tr.provisional_diagnosis, tr.urgency,
                   p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth,
                   s.sample_id, s.collection_date, s.collection_time, s.collected_by
            FROM test_request_items tri
            JOIN test_catalog tc ON tri.test_id = tc.id
            JOIN test_requests tr ON tri.request_id = tr.id
            JOIN patients p ON tr.patient_id = p.id
            LEFT JOIN samples s ON tr.id = s.request_id
            WHERE tri.id = ? AND tri.status IN ('Pending', 'In Progress')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$test_item_id]);
    $test_item = $stmt->fetch();
}

// Fetch pending test items for the list
$pending_tests = [];
try {
    $sql = "SELECT tri.id, tri.status, tri.priority, tri.sample_id,
                   tc.test_name, tc.test_code, tc.test_category, tc.sample_type,
                   tr.request_id, tr.urgency, tr.collection_date,
                   p.first_name, p.last_name, p.patient_id,
                   s.sample_id as sample_number, s.collection_date as sample_collection_date
            FROM test_request_items tri
            JOIN test_catalog tc ON tri.test_id = tc.id
            JOIN test_requests tr ON tri.request_id = tr.id
            JOIN patients p ON tr.patient_id = p.id
            LEFT JOIN samples s ON tr.id = s.request_id
            WHERE tri.status IN ('Pending', 'In Progress')
            ORDER BY tri.priority DESC, tr.collection_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $pending_tests = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching pending tests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Enter Test Results</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="p-3">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>  
                    <a class="nav-link" href="sample_tracking.php">
                        <i class="fas fa-vial"></i> Sample Tracking
                    </a>  
                    <a class="nav-link active" href="enter_results.php">
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
                        <div class="col-md-6">
                           <h2> <i class="fas fa-edit"></i> Enter Test Results
                        </h2>
                        <p class="mb-0">Enter and manage laboratory test results</p>
                    </div>
</div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($test_item): ?>
                        <!-- Test Result Entry Form -->
                        <div class="card card-gold mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-flask"></i> Enter Result for <?php echo htmlspecialchars($test_item['test_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Patient Information -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card bg-light-gray">
                                            <div class="card-body">
                                                <h6 class="text-gold">Patient Information</h6>
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($test_item['first_name'] . ' ' . $test_item['last_name']); ?></p>
                                                <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($test_item['patient_id']); ?></p>
                                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($test_item['gender']); ?></p>
                                                <p><strong>DOB:</strong> <?php echo htmlspecialchars($test_item['date_of_birth']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light-gray">
                                            <div class="card-body">
                                                <h6 class="text-gold">Test Information</h6>
                                                <p><strong>Test Code:</strong> <?php echo htmlspecialchars($test_item['test_code']); ?></p>
                                                <p><strong>Request ID:</strong> <?php echo htmlspecialchars($test_item['request_id']); ?></p>
                                                <p><strong>Sample Type:</strong> <?php echo htmlspecialchars($test_item['sample_type']); ?></p>
                                                <p><strong>Priority:</strong> 
                                                    <span class="badge badge-<?php echo $test_item['priority'] == 'Critical' ? 'danger' : ($test_item['priority'] == 'High' ? 'warning' : 'info'); ?>">
                                                        <?php echo htmlspecialchars($test_item['priority']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Clinical Information -->
                                <?php if (!empty($test_item['clinical_info']) || !empty($test_item['provisional_diagnosis'])): ?>
                                    <div class="card bg-light-gray mb-4">
                                        <div class="card-body">
                                            <h6 class="text-gold">Clinical Information</h6>
                                            <?php if (!empty($test_item['clinical_info'])): ?>
                                                <p><strong>Clinical Info:</strong> <?php echo htmlspecialchars($test_item['clinical_info']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($test_item['provisional_diagnosis'])): ?>
                                                <p><strong>Provisional Diagnosis:</strong> <?php echo htmlspecialchars($test_item['provisional_diagnosis']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Result Entry Form -->
                                <form method="POST" action="">
                                    <input type="hidden" name="test_item_id" value="<?php echo htmlspecialchars($test_item['id']); ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="result_value" class="form-label">
                                                    <i class="fas fa-vial"></i> Result Value <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="result_value" name="result_value" 
                                                       placeholder="Enter test result value" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="result_status" class="form-label">
                                                    <i class="fas fa-chart-line"></i> Result Status <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control" id="result_status" name="result_status" required>
                                                    <option value="">Select Status</option>
                                                    <option value="Normal">Normal</option>
                                                    <option value="Abnormal">Abnormal</option>
                                                    <option value="Critical">Critical</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="reference_range" class="form-label">
                                                    <i class="fas fa-ruler"></i> Reference Range
                                                </label>
                                                <input type="text" class="form-control" id="reference_range" name="reference_range" 
                                                       value="<?php echo htmlspecialchars($test_item['reference_range']); ?>"
                                                       placeholder="Normal range">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="units" class="form-label">
                                                    <i class="fas fa-balance-scale"></i> Units
                                                </label>
                                                <input type="text" class="form-control" id="units" name="units" 
                                                       value="<?php echo htmlspecialchars($test_item['units']); ?>"
                                                       placeholder="e.g., mg/dL, g/L">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="method" class="form-label">
                                            <i class="fas fa-cog"></i> Method/Technique
                                        </label>
                                        <input type="text" class="form-control" id="method" name="method" 
                                               placeholder="Testing method or technique used">
                                    </div>

                                    <div class="form-group">
                                        <label for="comments" class="form-label">
                                            <i class="fas fa-comment"></i> Comments/Notes
                                        </label>
                                        <textarea class="form-control" id="comments" name="comments" rows="3" 
                                                  placeholder="Additional comments or observations"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" name="submit_results" class="btn btn-gold mr-2">
                                            <i class="fas fa-save"></i> Save Result
                                        </button>
                                        <a href="enter_results.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Pending Tests List -->
                        <div class="card card-gold">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Pending Tests - Enter Results
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_tests)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-check-circle text-gold fa-3x mb-3"></i>
                                        <h5>No Pending Tests</h5>
                                        <p class="text-muted">All tests have been completed or there are no pending tests at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Request ID</th>
                                                    <th>Patient</th>
                                                    <th>Test Name</th>
                                                    <th>Category</th>
                                                    <th>Priority</th>
                                                    <th>Sample Type</th>
                                                    <th>Collection Date</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_tests as $test): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($test['request_id']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($test['patient_id']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($test['test_name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($test['test_code']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info">
                                                                <?php echo htmlspecialchars($test['test_category']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $test['priority'] == 'Critical' ? 'danger' : ($test['priority'] == 'High' ? 'warning' : 'info'); ?>">
                                                                <?php echo htmlspecialchars($test['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($test['sample_type']); ?></td>
                                                        <td>
                                                            <?php if (!empty($test['sample_collection_date'])): ?>
                                                                <?php echo date('M j, Y', strtotime($test['sample_collection_date'])); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not collected</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $test['status'] == 'In Progress' ? 'warning' : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars($test['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="enter_results.php?id=<?php echo htmlspecialchars($test['id']); ?>" 
                                                               class="btn btn-gold btn-sm">
                                                                <i class="fas fa-edit"></i> Enter Result
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php require_once '../includes/footer.php'; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Result status change handler
            $('#result_status').change(function() {
                const status = $(this).val();
                if (status === 'Critical') {
                    $('#comments').attr('placeholder', 'Critical result - please provide detailed comments');
                    $('#comments').focus();
                } else if (status === 'Abnormal') {
                    $('#comments').attr('placeholder', 'Abnormal result - please provide relevant comments');
                } else {
                    $('#comments').attr('placeholder', 'Additional comments or observations');
                }
            });
            
            // Form validation
            $('form').on('submit', function(e) {
                const resultValue = $('#result_value').val().trim();
                const resultStatus = $('#result_status').val();
                
                if (!resultValue) {
                    e.preventDefault();
                    alert('Please enter a result value');
                    $('#result_value').focus();
                    return false;
                }
                
                if (!resultStatus) {
                    e.preventDefault();
                    alert('Please select a result status');
                    $('#result_status').focus();
                    return false;
                }
                
                if (resultStatus === 'Critical') {
                    const comments = $('#comments').val().trim();
                    if (!comments) {
                        e.preventDefault();
                        alert('Please provide comments for critical results');
                        $('#comments').focus();
                        return false;
                    }
                }
                
                return confirm('Are you sure you want to save this result? This action cannot be undone.');
            });
        });
    </script>
</body>
</html>