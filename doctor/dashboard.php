<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize DB connection
try {
    $database = new Database();
    $conn = $database->connect();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in and is a doctor
if (!is_logged_in()) {
    redirect('../login.php');
}
if ($_SESSION['user_role'] !== 'doctor') {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    redirect('../dashboard.php');
}

$user = get_user_info();
$doctor_id = $user['id'];
$doctor_name = $user['name'];

$page_title = 'Patient Management';
$current_page = 'patient_history';

// Handle patient actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $patient_id = (int)$_POST['patient_id'];
    
    // Validate patient ID
    if ($patient_id <= 0) {
        $_SESSION['error_message'] = "Invalid patient ID.";
        redirect("dashboard.php");
    }
    
    try {
        switch ($action) {
            case 'update':
                // Validate and sanitize inputs
                $required_fields = ['first_name', 'last_name', 'date_of_birth', 'gender'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $_SESSION['error_message'] = "Required field '$field' is missing.";
                        redirect("dashboard.php?patient_id=$patient_id");
                    }
                }
                
                $update_data = [
                    'first_name' => sanitize_input($_POST['first_name']),
                    'last_name' => sanitize_input($_POST['last_name']),
                    'date_of_birth' => sanitize_input($_POST['date_of_birth']),
                    'gender' => sanitize_input($_POST['gender']),
                    'phone' => sanitize_input($_POST['phone'] ?? ''),
                    'email' => sanitize_input($_POST['email'] ?? ''),
                    'address' => sanitize_input($_POST['address'] ?? ''),
                    'emergency_contact' => sanitize_input($_POST['emergency_contact'] ?? ''),
                    'emergency_phone' => sanitize_input($_POST['emergency_phone'] ?? ''),
                    'medical_history' => sanitize_input($_POST['medical_history'] ?? ''),
                    'allergies' => sanitize_input($_POST['allergies'] ?? ''),
                    'patient_id' => $patient_id
                ];
                
                $stmt = $conn->prepare("
                    UPDATE patients SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        date_of_birth = :date_of_birth, 
                        gender = :gender, 
                        phone = :phone, 
                        email = :email, 
                        address = :address, 
                        emergency_contact = :emergency_contact, 
                        emergency_phone = :emergency_phone, 
                        medical_history = :medical_history, 
                        allergies = :allergies,
                        updated_at = NOW()
                    WHERE id = :patient_id
                ");
                
                if (!$stmt->execute($update_data)) {
                    throw new Exception("Failed to update patient: " . implode(", ", $stmt->errorInfo()));
                }
                
                $_SESSION['success_message'] = "Patient information updated successfully.";
                break;
                
            case 'delete':
                // Verify patient exists before deletion
                $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
                if (!$stmt->fetch()) {
                    $_SESSION['error_message'] = "Patient not found.";
                    redirect("dashboard.php");
                }
                
                // Hard delete (since we don't have deleted_at column)
                $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
                if (!$stmt->execute([$patient_id])) {
                    throw new Exception("Failed to delete patient: " . implode(", ", $stmt->errorInfo()));
                }
                
                $_SESSION['success_message'] = "Patient record deleted successfully.";
                $patient_id = 0; // Reset patient ID after deletion
                break;
                
            default:
                $_SESSION['error_message'] = "Invalid action specified.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    
}

// Get patient ID from URL parameter
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Fetch patient information if patient_id is provided
$patient_info = null;
if ($patient_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, u.full_name as created_by_name 
            FROM patients p 
            LEFT JOIN users u ON p.created_by = u.id 
            WHERE p.id = ?
        ");
        if (!$stmt->execute([$patient_id])) {
            throw new Exception("Patient query failed: " . implode(", ", $stmt->errorInfo()));
        }
        
        $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient_info) {
            $_SESSION['error_message'] = "Patient not found.";
            redirect("dashboard.php");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error loading patient: " . $e->getMessage();
        $patient_id = 0;
    }
}

// Search patients if search query is provided
$search_results = [];
if (!empty($search_query)) {
    try {
        $search_term = "%$search_query%";
        $stmt = $conn->prepare("
            SELECT id, patient_id, first_name, last_name, date_of_birth, phone, email
            FROM patients 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR phone LIKE ?)
            ORDER BY last_name, first_name
            LIMIT 20
        ");
        if (!$stmt->execute([$search_term, $search_term, $search_term, $search_term])) {
            throw new Exception("Search query failed: " . implode(", ", $stmt->errorInfo()));
        }
        
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Search error: " . $e->getMessage();
    }
}

// Fetch patient history if patient is selected
$test_requests = [];
$total_tests = 0;
$pending_tests = 0;
$completed_tests = 0;

if ($patient_id > 0 && $patient_info) {
    try {
        // Get test requests for this patient
        $stmt = $conn->prepare("
            SELECT tr.id, tr.request_id, tr.created_at, tr.status, tr.urgency, 
                   u.full_name as doctor_name,
                   COUNT(tri.id) as total_tests,
                   SUM(CASE WHEN tri.status = 'Completed' THEN 1 ELSE 0 END) as completed_tests,
                   SUM(CASE WHEN tri.status = 'Pending' THEN 1 ELSE 0 END) as pending_tests
            FROM test_requests tr
            LEFT JOIN users u ON tr.doctor_id = u.id
            LEFT JOIN test_request_items tri ON tr.id = tri.request_id
            WHERE tr.patient_id = ?
            GROUP BY tr.id, tr.request_id, tr.created_at, tr.status, tr.urgency, u.full_name
            ORDER BY tr.created_at DESC
        ");
        if (!$stmt->execute([$patient_id])) {
            throw new Exception("Test requests query failed: " . implode(", ", $stmt->errorInfo()));
        }
        
        $test_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate statistics
        foreach ($test_requests as $request) {
            $total_tests += (int)$request['total_tests'];
            $completed_tests += (int)$request['completed_tests'];
            $pending_tests += (int)$request['pending_tests'];
        }

        // Get detailed test results for patient
        $stmt = $conn->prepare("
            SELECT tr.id as request_db_id, tr.request_id, tr.created_at as request_date, 
                   tr.urgency, tr.status as request_status,
                   tc.test_name, tc.test_category, tc.reference_range, tc.units,
                   tri.id as test_item_id, tri.status as test_status, tri.sample_id,
                   trs.result_value, trs.result_status, trs.performed_date, trs.verified_date, trs.comments,
                   u1.full_name as performed_by_name, u2.full_name as verified_by_name
            FROM test_requests tr
            INNER JOIN test_request_items tri ON tr.id = tri.request_id
            INNER JOIN test_catalog tc ON tri.test_id = tc.id
            LEFT JOIN test_results trs ON tri.id = trs.test_item_id
            LEFT JOIN users u1 ON trs.performed_by = u1.id
            LEFT JOIN users u2 ON trs.verified_by = u2.id
            WHERE tr.patient_id = ?
            ORDER BY tr.created_at DESC, tc.test_name
        ");
        if (!$stmt->execute([$patient_id])) {
            throw new Exception("Test results query failed: " . implode(", ", $stmt->errorInfo()));
        }
        
        $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error loading patient history: " . $e->getMessage();
    }
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
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar - Added directly since include file was missing -->
        <div class="col-md-3 col-lg-2 px-0">
            <div class="sidebar">
                <div class="p-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="patient_registration.php">
                                <i class="fas fa-user-plus"></i> Register Patient
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="test_request.php">
                                <i class="fas fa-file-medical"></i> Test Request
                            </a>
                        </li>
                         
                        <div class="nav-divider my-3"></div>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="col-md-8">
                            <h1 class="text-gold mb-2">
                                <i class="fas fa-user-md"></i> Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?>
                            </h1>
                            <p class="text-muted mb-0">Manage your patients and laboratory requests efficiently</p>
                        <br></br><p class="mb-0">View and manage patient results and history</p>
                    </div>
                    <div class="text-right">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Doctor'); ?><br>
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                        </small>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Patient Search -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search"></i> Search Patient</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_query); ?>" 
                                       placeholder="Search by name, patient ID, or phone number" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-gold">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($patient_id > 0 || !empty($search_query)): ?>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if (!empty($search_results)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Search Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>Date of Birth</th>
                                        <th>Phone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $patient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($patient['patient_id'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                            <td><?php echo !empty($patient['date_of_birth']) ? date('M j, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="dashboard.php?patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-sm btn-gold">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Patient Information and Edit Form -->
           <?php if ($patient_info): ?>
    <!-- Patient Information Card -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-user-circle"></i> 
                Patient Information: <?php echo htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?>
            </h5>
            <div>
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" 
        data-target="#editPatientModal">
    <i class="fas fa-edit"></i> Edit
</button>
                <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" 
                        data-target="#deletePatientModal">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_info['patient_id'] ?? 'N/A'); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo !empty($patient_info['date_of_birth']) ? date('M j, Y', strtotime($patient_info['date_of_birth'])) : 'N/A'; ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient_info['gender'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient_info['phone'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient_info['email'] ?? 'N/A'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($patient_info['address'] ?? 'N/A'); ?></p>
                    <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($patient_info['emergency_contact'] ?? 'N/A'); ?></p>
                    <p><strong>Emergency Phone:</strong> <?php echo htmlspecialchars($patient_info['emergency_phone'] ?? 'N/A'); ?></p>
                    <p><strong>Medical History:</strong> <?php echo htmlspecialchars($patient_info['medical_history'] ?? 'None'); ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient_info['allergies'] ?? 'None'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Requests Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-flask"></i> Test Requests Summary</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-12">
                    <div class="stat-card bg-primary text-white">
                        <h3><?php echo $total_tests; ?></h3>
                        <p class="mb-0">Total Tests</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Test Requests History -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-history"></i> Test Requests History</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($test_requests)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Urgency</th>
                                <th>Tests</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($request['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($request['doctor_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $request['status'] === 'Completed' ? 'success' : 
                                                 ($request['status'] === 'Pending' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $request['urgency'] === 'STAT' ? 'danger' : 
                                                 ($request['urgency'] === 'Urgent' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($request['urgency']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo (int)$request['completed_tests']; ?> of <?php echo (int)$request['total_tests']; ?> completed
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No test requests found for this patient.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detailed Test Results -->
    <?php if (!empty($test_results)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-medical-alt"></i> Detailed Test Results</h5>
            </div>
            <div class="card-body">
                <?php 
$current_request_id = null;
$total_results = count($test_results);
foreach ($test_results as $index => $result): 
    if ($current_request_id !== $result['request_id']):
        $current_request_id = $result['request_id'];
?>
        <h5 class="mt-4 mb-3">
            Request: <?php echo htmlspecialchars($result['request_id']); ?>
            <small class="text-muted">(<?php echo date('M j, Y', strtotime($result['request_date'])); ?>)</small>
        </h5>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th>Result</th>
                        <th>Units</th>
                        <th>Status</th>
                        <th>Reference Range</th>
                        <th>Performed On</th>
                        <th>Verified On</th>
                    </tr>
                </thead>
                <tbody>
    <?php endif; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['test_name']); ?></td>
                        <td><?php echo htmlspecialchars($result['test_category']); ?></td>
                        <td>
                            <?php if (!empty($result['result_value'])): ?>
                                <?php echo htmlspecialchars($result['result_value']); ?> 
                                
                            <?php else: ?>
                                <span class="text-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($result['units']); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $result['result_status'] === 'Normal' ? 'success' : 
                                     ($result['result_status'] === 'Abnormal' ? 'danger' : 'secondary'); 
                            ?>">
                                <?php echo htmlspecialchars($result['result_status'] ?? 'Pending'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($result['reference_range']); ?></td>
                        <td>
                            <?php if (!empty($result['performed_date'])): ?>
                                <?php echo date('M j, Y', strtotime($result['performed_date'])); ?>
                                <small class="text-muted d-block">by <?php echo htmlspecialchars($result['performed_by_name'] ?? 'N/A'); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($result['verified_date'])): ?>
                                <?php echo date('M j, Y', strtotime($result['verified_date'])); ?>
                                <small class="text-muted d-block">by <?php echo htmlspecialchars($result['verified_by_name'] ?? 'N/A'); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Not verified</span>
                            <?php endif; ?>
                        </td>
                    </tr>
    <?php 
    // Check if this is the last item or if the next item has a different request_id
    $is_last_item = ($index === $total_results - 1);
    $next_has_different_request = !$is_last_item && $test_results[$index + 1]['request_id'] !== $current_request_id;
    
    if ($is_last_item || $next_has_different_request): 
    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Delete Patient Modal -->
    <div class="modal fade" id="deletePatientModal" tabindex="-1" role="dialog" aria-labelledby="deletePatientModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deletePatientModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this patient record?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <input type="hidden" name="action" value="delete">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editPatientModal" tabindex="-1" role="dialog" aria-labelledby="editPatientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editPatientModalLabel">Edit Patient Information</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($patient_info['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($patient_info['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($patient_info['date_of_birth'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="Male" <?php echo ($patient_info['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patient_info['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patient_info['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($patient_info['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($patient_info['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($patient_info['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($patient_info['emergency_contact'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="emergency_phone">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                       value="<?php echo htmlspecialchars($patient_info['emergency_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="medical_history">Medical History</label>
                                <textarea class="form-control" id="medical_history" name="medical_history" rows="3"><?php echo htmlspecialchars($patient_info['medical_history'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="allergies">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="3"><?php echo htmlspecialchars($patient_info['allergies'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <input type="hidden" name="action" value="update">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Scroll to top if there are messages
    if ($('.alert').length) {
        $('html, body').animate({ scrollTop: 0 }, 'fast');
    }
    
    // Confirm before deleting patient
    $('#deletePatientModal').on('show.bs.modal', function(e) {
        var patientName = "<?php echo isset($patient_info) ? htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']) : ''; ?>";
        $(this).find('.modal-body p:first').text("Are you sure you want to delete the patient record for " + patientName + "?");
    });
});
// Handle form validation before submission
    $('#editPatientModal form').on('submit', function(e) {
        let valid = true;
        
        // Check required fields
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                valid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            // Scroll to first invalid field
            $('html, body').animate({
                scrollTop: $(this).find('.is-invalid').first().offset().top - 100
            }, 'fast');
        }
    });
    
    // Clear validation errors when modal is hidden
    $('#editPatientModal').on('hidden.bs.modal', function() {
        $(this).find('.is-invalid').removeClass('is-invalid');
    });
</script>
</body>
</html>