<?php
// Ensure sessions are started at the very beginning
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has lab role
if (!is_logged_in() || $_SESSION['user_role'] !== 'lab') {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->connect();

// Verify database connection
if (!$conn) {
    $_SESSION['error_message'] = 'Database connection failed';
    error_log("Database connection error: " . print_r($database->errorInfo(), true));
    redirect('sample_tracking.php');
}

// Handle sample creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_sample'])) {
    // Log input data
    error_log("Input Data: " . print_r($_POST, true));

    // Check session user ID
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User ID not set in session");
    }

    // Validate required fields
    $required = ['request_id', 'sample_type', 'collection_date'];
    $missing = array_diff($required, array_keys($_POST));
    
    if (!empty($missing)) {
        $_SESSION['error_message'] = 'Missing required fields: ' . implode(', ', $missing);
        redirect('sample_tracking.php');
    }
    
    // Sanitize inputs
    $request_id = sanitize_input($_POST['request_id']);
    $sample_type = sanitize_input($_POST['sample_type']);
    $volume = sanitize_input($_POST['volume'] ?? '');
    $collection_date = sanitize_input($_POST['collection_date']);
    $collected_by = sanitize_input($_POST['collected_by'] ?? '');
    $condition_on_receipt = sanitize_input($_POST['condition_on_receipt'] ?? 'Good');
    $storage_location = sanitize_input($_POST['storage_location'] ?? '');
    $storage_temperature = sanitize_input($_POST['storage_temperature'] ?? 'Room Temperature');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Generate unique sample ID
    $sample_id = 'S' . date('Y') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Log generated sample ID
    error_log("Generated Sample ID: " . $sample_id);

    try {
        $conn->beginTransaction();
        
        // Verify request exists
        $request_check = "SELECT COUNT(*) FROM test_requests WHERE id = :request_id";
        $request_stmt = $conn->prepare($request_check);
        $request_stmt->execute([':request_id' => $request_id]);

        if ($request_stmt->fetchColumn() == 0) {
            throw new Exception("Test request does not exist");
        }
        
        // Insert new sample
        $sql = "INSERT INTO samples (
                    sample_id, 
                    request_id, 
                    sample_type, 
                    volume, 
                    collection_date, 
                    collected_by, 
                    condition_on_receipt, 
                    storage_location, 
                    storage_temperature, 
                    status, 
                    notes, 
                    received_by, 
                    received_date, 
                    created_at, 
                    updated_at
                ) VALUES (
                    :sample_id, 
                    :request_id, 
                    :sample_type, 
                    :volume, 
                    :collection_date, 
                    :collected_by, 
                    :condition_on_receipt, 
                    :storage_location, 
                    :storage_temperature, 
                    'Received', 
                    :notes, 
                    :received_by, 
                    NOW(), 
                    NOW(), 
                    NOW()
                )";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([
            ':sample_id' => $sample_id,
            ':request_id' => $request_id,
            ':sample_type' => $sample_type,
            ':volume' => $volume,
            ':collection_date' => $collection_date,
            ':collected_by' => $collected_by,
            ':condition_on_receipt' => $condition_on_receipt,
            ':storage_location' => $storage_location,
            ':storage_temperature' => $storage_temperature,
            ':notes' => $notes,
            ':received_by' => $_SESSION['user_id']
        ])) {
            error_log("SQL Error: " . implode(' ', $stmt->errorInfo()));
            throw new Exception("Insert failed: " . implode(' ', $stmt->errorInfo()));
        }
        
        // Update test request status
        $update_request_sql = "UPDATE test_requests SET status = 'Sample Collected' WHERE id = :request_id";
        $update_stmt = $conn->prepare($update_request_sql);
        $update_stmt->execute([':request_id' => $request_id]);
        
        // Log the action
        log_activity($_SESSION['user_id'], 'Sample Created', 'samples', $sample_id, 
                    ['request_id' => $request_id, 'sample_type' => $sample_type]);
        
        $conn->commit();
        
        $_SESSION['success_message'] = 'Sample created successfully with ID: ' . $sample_id;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Sample creation error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error creating sample: ' . $e->getMessage();
    }
}

// Handle sample status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_sample'])) {
    $sample_id = sanitize_input($_POST['sample_id']);
    $new_status = sanitize_input($_POST['new_status']);
    $notes = sanitize_input($_POST['notes'] ?? '');
    $storage_location = sanitize_input($_POST['storage_location'] ?? '');
    $storage_temperature = sanitize_input($_POST['storage_temperature'] ?? 'Room Temperature');
    
    try {
        $conn->beginTransaction();
        
        // Verify sample exists
        $sample_check = "SELECT COUNT(*) FROM samples WHERE sample_id = :sample_id";
        $sample_stmt = $conn->prepare($sample_check);
        $sample_stmt->execute([':sample_id' => $sample_id]);
        
        if ($sample_stmt->fetchColumn() == 0) {
            throw new Exception("Sample does not exist");
        }
        
        $sql = "UPDATE samples SET 
                status = :status, 
                notes = :notes, 
                storage_location = :storage_location, 
                storage_temperature = :storage_temperature,
                received_by = :received_by,
                received_date = NOW(),
                updated_at = NOW() 
                WHERE sample_id = :sample_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status' => $new_status,
            ':notes' => $notes,
            ':storage_location' => $storage_location,
            ':storage_temperature' => $storage_temperature,
            ':received_by' => $_SESSION['user_id'],
            ':sample_id' => $sample_id
        ]);
        
        // Log the action
        log_activity($_SESSION['user_id'], 'Sample Updated', 'samples', $sample_id, 
                    ['status' => $new_status, 'notes' => $notes]);
        
        $conn->commit();
        
        $_SESSION['success_message'] = 'Sample status updated successfully!';
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Sample update error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating sample: ' . $e->getMessage();
    }
    
}

// Fetch test requests that don't have samples yet
$pending_requests = [];
try {
    $sql = "SELECT tr.*, 
                   p.first_name, 
                   p.last_name, 
                   p.patient_id,
                   u.full_name as doctor_name,
                   COUNT(tri.id) as test_count
            FROM test_requests tr
            JOIN patients p ON tr.patient_id = p.id
            JOIN users u ON tr.doctor_id = u.id
            LEFT JOIN test_request_items tri ON tr.id = tri.request_id
            LEFT JOIN samples s ON tr.id = s.request_id
            WHERE s.id IS NULL 
            AND tr.status IN ('Pending', 'Approved')
            GROUP BY tr.id 
            ORDER BY tr.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error loading pending requests';
}

// Fetch samples with related information
$samples = [];
$search_query = '';
$status_filter = '';
$date_filter = '';

if (isset($_GET['search'])) {
    $search_query = sanitize_input($_GET['search']);
}
if (isset($_GET['status'])) {
    $status_filter = sanitize_input($_GET['status']);
}
if (isset($_GET['date'])) {
    $date_filter = sanitize_input($_GET['date']);
}

try {
    $sql = "SELECT s.*, 
                   tr.request_id as request_number,
                   tr.urgency,
                   tr.clinical_info,
                   p.patient_id,
                   p.first_name,
                   p.last_name,
                   u.full_name as doctor_name,
                   COUNT(tri.id) as test_count
            FROM samples s
            JOIN test_requests tr ON s.request_id = tr.id
            JOIN patients p ON tr.patient_id = p.id
            JOIN users u ON tr.doctor_id = u.id
            LEFT JOIN test_request_items tri ON tr.id = tri.request_id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search_query)) {
        $sql .= " AND (s.sample_id LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search OR tr.request_id LIKE :search)";
        $params[':search'] = '%' . $search_query . '%';
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND s.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $sql .= " AND DATE(s.collection_date) = :date";
        $params[':date'] = $date_filter;
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching samples: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error loading samples data';
}

// Get sample status counts for dashboard
$status_counts = [];
try {
    $sql = "SELECT status, COUNT(*) as count FROM samples GROUP BY status";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Error fetching status counts: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Sample Tracking</title>
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
                     <a class="nav-link active" href="sample_tracking.php">
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
            <div class="col-md-10 main-content">
                <div class="dashboard-header">
                    <h1><i class="fas fa-vial"></i> Sample Tracking System</h1>
                    <p>Track and manage laboratory samples from collection to disposal</p>

                    <!-- Create Sample Button -->
                <div class="mb-4 text-right">
                    <button type="button" class="btn btn-gold btn-lg" data-toggle="modal" data-target="#createSampleModal">
                        <i class="fas fa-plus"></i> Create Sample
                    </button>
                    
                </div>
                </div>

                 <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                
                <!-- Sample Status Overview -->
                <div class="row mb-4">
                    
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon"><i class="fas fa-inbox"></i></div>
                            <div class="number"><?php echo $status_counts['Received'] ?? 0; ?></div>
                            <div class="label">Received</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                            <div class="number"><?php echo $status_counts['Tested'] ?? 0; ?></div>
                            <div class="label">Tested</div>
                        </div>
                    </div>
                </div>

                

                <!-- Search and Filter Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-search"></i> Search & Filter Samples</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="search">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Sample ID, Patient Name, Request ID..." 
                                               value="<?php echo htmlspecialchars($search_query); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="Collected" <?php echo $status_filter == 'Collected' ? 'selected' : ''; ?>>Collected</option>
                                            <option value="Received" <?php echo $status_filter == 'Received' ? 'selected' : ''; ?>>Received</option>
                                            <option value="Tested" <?php echo $status_filter == 'Tested' ? 'selected' : ''; ?>>Tested</option>
                                            <option value="Stored" <?php echo $status_filter == 'Stored' ? 'selected' : ''; ?>>Stored</option>
                                            <option value="Discarded" <?php echo $status_filter == 'Discarded' ? 'selected' : ''; ?>>Discarded</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date">Collection Date</label>
                                        <input type="date" class="form-control" id="date" name="date" 
                                               value="<?php echo htmlspecialchars($date_filter); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-gold">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Samples Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Sample Tracking List</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover sample-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Sample ID</th>
                                        <th>Patient</th>
                                        <th>Request ID</th>
                                        <th>Sample Type</th>
                                        <th>Collection Date</th>
                                        <th>Status</th>
                                        <th>Urgency</th>
                                        <th>Tests</th>
                                        <th>Storage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($samples)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="fas fa-search text-muted mb-2" style="font-size: 2rem;"></i>
                                                <div>No samples found</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($samples as $sample): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-primary"><?php echo htmlspecialchars($sample['sample_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sample['first_name'] . ' ' . $sample['last_name']); ?></strong>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($sample['patient_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($sample['request_number']); ?></td>
                                                <td>
                                                    <span class="badge badge-secondary">
                                                        <?php echo htmlspecialchars($sample['sample_type'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($sample['collection_date']) {
                                                        echo date('M d, Y H:i', strtotime($sample['collection_date']));
                                                    } else {
                                                        echo '<span class="text-muted">Not collected</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($sample['status']) {
                                                        case 'Collected':
                                                            $status_class = 'badge-warning';
                                                            break;
                                                        case 'Received':
                                                            $status_class = 'badge-info';
                                                            break;
                                                        case 'Tested':
                                                            $status_class = 'badge-success';
                                                            break;
                                                        case 'Stored':
                                                            $status_class = 'badge-secondary';
                                                            break;
                                                        case 'Discarded':
                                                            $status_class = 'badge-dark';
                                                            break;
                                                        default:
                                                            $status_class = 'badge-light';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo htmlspecialchars($sample['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $urgency_class = '';
                                                    switch ($sample['urgency']) {
                                                        case 'STAT':
                                                            $urgency_class = 'badge-danger';
                                                            break;
                                                        case 'Urgent':
                                                            $urgency_class = 'badge-warning';
                                                            break;
                                                        default:
                                                            $urgency_class = 'badge-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $urgency_class; ?>">
                                                        <?php echo htmlspecialchars($sample['urgency']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-primary">
                                                        <?php echo $sample['test_count']; ?> test<?php echo $sample['test_count'] != 1 ? 's' : ''; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted d-block">
                                                        <?php echo htmlspecialchars($sample['storage_location'] ?: 'Not specified'); ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($sample['storage_temperature'] ?: 'Room temp'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-action btn-sm" 
                                                            onclick="updateSample('<?php echo htmlspecialchars($sample['sample_id']); ?>', '<?php echo htmlspecialchars($sample['status']); ?>', '<?php echo htmlspecialchars($sample['storage_location']); ?>', '<?php echo htmlspecialchars($sample['storage_temperature']); ?>', '<?php echo htmlspecialchars($sample['notes']); ?>')">
                                                        <i class="fas fa-edit"></i> Update
                                                    </button>
                                                    <button type="button" class="btn btn-info btn-action btn-sm" 
                                                            onclick="viewSample('<?php echo htmlspecialchars($sample['sample_id']); ?>', <?php echo htmlspecialchars(json_encode($sample)); ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Sample Modal -->
    <div class="modal fade" id="createSampleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Sample</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Test Request Selection -->
                        <div class="form-section">
                            <h6><i class="fas fa-clipboard-list"></i> Test Request Information</h6>
                            <div class="form-group">
                                <label for="request_id">Select Test Request *</label>
                                <select class="form-control" id="request_id" name="request_id" required onchange="updatePatientInfo()">
                                    <option value="">-- Select Test Request --</option>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <option value="<?php echo $request['id']; ?>" 
                                                data-patient-name="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>"
                                                data-patient-id="<?php echo htmlspecialchars($request['patient_id']); ?>"
                                                data-doctor="<?php echo htmlspecialchars($request['doctor_name']); ?>"
                                                data-urgency="<?php echo htmlspecialchars($request['urgency']); ?>"
                                                data-test-count="<?php echo $request['test_count']; ?>"
                                                data-clinical-info="<?php echo htmlspecialchars($request['clinical_info']); ?>">
                                            <?php echo htmlspecialchars($request['request_id']); ?> - 
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            (<?php echo $request['test_count']; ?> tests)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Patient Information Display -->
                            <div id="patient-info" class="patient-info" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Patient:</strong> <span id="patient-name"></span><br>
                                        <strong>Patient ID:</strong> <span id="patient-id"></span><br>
                                        <strong>Doctor:</strong> <span id="doctor-name"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Urgency:</strong> <span id="urgency-level"></span><br>
                                        <strong>Tests Ordered:</strong> <span id="test-count"></span><br>
                                        <strong>Clinical Info:</strong> <span id="clinical-info"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sample Details -->
                        <div class="form-section">
                            <h6><i class="fas fa-vial"></i> Sample Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sample_type">Sample Type *</label>
                                        <select class="form-control" id="sample_type" name="sample_type" required>
                                            <option value="">-- Select Sample Type --</option>
                                            <option value="Blood">Blood</option>
                                            <option value="Serum">Serum</option>
                                            <option value="Plasma">Plasma</option>
                                            <option value="Urine">Urine</option>
                                            <option value="Stool">Stool</option>
                                            <option value="Sputum">Sputum</option>
                                            <option value="CSF">Cerebrospinal Fluid (CSF)</option>
                                            <option value="Tissue">Tissue</option>
                                            <option value="Swab">Swab</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="volume">Volume (ml)</label>
                                        <input type="number" step="0.1" class="form-control" id="volume" name="volume" 
                                               placeholder="Enter sample volume">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Information -->
                        <div class="form-section">
                            <h6><i class="fas fa-calendar-alt"></i> Collection Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collection_date">Collection Date & Time *</label>
                                        <input type="datetime-local" class="form-control" id="collection_date" 
                                               name="collection_date" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collected_by">Collected By</label>
                                        <input type="text" class="form-control" id="collected_by" name="collected_by" 
                                               placeholder="Name of person who collected sample">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Receipt and Storage -->
                        <div class="form-section">
                            <h6><i class="fas fa-warehouse"></i> Receipt & Storage</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="condition_on_receipt">Condition on Receipt</label>
                                        <select class="form-control" id="condition_on_receipt" name="condition_on_receipt">
                                            <option value="Good">Good</option>
                                            <option value="Acceptable">Acceptable</option>
                                            <option value="Poor">Poor</option>
                                            <option value="Contaminated">Contaminated</option>
                                            <option value="Insufficient">Insufficient Volume</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="storage_location">Storage Location</label>
                                        <input type="text" class="form-control" id="storage_location" name="storage_location" 
                                               placeholder="e.g., Freezer A, Shelf B-2">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="storage_temperature">Storage Temperature</label>
                                        <select class="form-control" id="storage_temperature" name="storage_temperature">
                                            <option value="Room Temperature">Room Temperature (20-25°C)</option>
                                            <option value="Refrigerated">Refrigerated (2-8°C)</option>
                                            <option value="Frozen">Frozen (-20°C)</option>
                                            <option value="Deep Frozen">Deep Frozen (-80°C)</option>
                                            <option value="Liquid Nitrogen">Liquid Nitrogen (-196°C)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Additional notes or observations"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_sample" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Sample
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Sample Modal -->
    <div class="modal fade" id="updateSampleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Update Sample Status</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="update_sample_id" name="sample_id">
                        
                        <div class="form-group">
                            <label for="new_status">Sample Status *</label>
                            <select class="form-control" id="new_status" name="new_status" required>
                                <option value="Collected">Collected</option>
                                <option value="Received">Received</option>
                                <option value="Processing">Processing</option>
                                <option value="Tested">Tested</option>
                                <option value="Stored">Stored</option>
                                <option value="Discarded">Discarded</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="update_storage_location">Storage Location</label>
                                    <input type="text" class="form-control" id="update_storage_location" 
                                           name="storage_location" placeholder="e.g., Freezer A, Shelf B-2">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="update_storage_temperature">Storage Temperature</label>
                                    <select class="form-control" id="update_storage_temperature" name="storage_temperature">
                                        <option value="Room Temperature">Room Temperature (20-25°C)</option>
                                        <option value="Refrigerated">Refrigerated (2-8°C)</option>
                                        <option value="Frozen">Frozen (-20°C)</option>
                                        <option value="Deep Frozen">Deep Frozen (-80°C)</option>
                                        <option value="Liquid Nitrogen">Liquid Nitrogen (-196°C)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="update_notes">Notes</label>
                            <textarea class="form-control" id="update_notes" name="notes" rows="3" 
                                      placeholder="Add notes about the status update"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_sample" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Sample
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Sample Modal -->
    <div class="modal fade" id="viewSampleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Sample Details</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="sample-details-content">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
        <?php require_once '../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update patient information when request is selected
        function updatePatientInfo() {
            const select = document.getElementById('request_id');
            const selectedOption = select.options[select.selectedIndex];
            const patientInfo = document.getElementById('patient-info');
            
            if (selectedOption.value) {
                document.getElementById('patient-name').textContent = selectedOption.dataset.patientName;
                document.getElementById('patient-id').textContent = selectedOption.dataset.patientId;
                document.getElementById('doctor-name').textContent = selectedOption.dataset.doctor;
                document.getElementById('urgency-level').textContent = selectedOption.dataset.urgency;
                document.getElementById('test-count').textContent = selectedOption.dataset.testCount;
                document.getElementById('clinical-info').textContent = selectedOption.dataset.clinicalInfo || 'None provided';
                
                patientInfo.style.display = 'block';
            } else {
                patientInfo.style.display = 'none';
            }
        }

        // Update sample function
        function updateSample(sampleId, currentStatus, storageLocation, storageTemp, notes) {
            document.getElementById('update_sample_id').value = sampleId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('update_storage_location').value = storageLocation;
            document.getElementById('update_storage_temperature').value = storageTemp;
            document.getElementById('update_notes').value = notes;
            
            $('#updateSampleModal').modal('show');
        }

        // View sample function
        function viewSample(sampleId, sampleData) {
            const content = document.getElementById('sample-details-content');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Sample Information</h6>
                        <table class="table table-sm">
                            <tr><th width="40%">Sample ID:</th><td><strong class="text-primary">${sampleData.sample_id}</strong></td></tr>
                            <tr><th>Request ID:</th><td>${sampleData.request_number}</td></tr>
                            <tr><th>Sample Type:</th><td>${sampleData.sample_type || 'N/A'}</td></tr>
                            <tr><th>Volume:</th><td>${sampleData.volume || 'N/A'} ml</td></tr>
                            <tr><th>Status:</th><td><span class="badge badge-info">${sampleData.status}</span></td></tr>
                            <tr><th>Urgency:</th><td><span class="badge badge-warning">${sampleData.urgency}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success mb-3">Patient Information</h6>
                        <table class="table table-sm">
                            <tr><th width="40%">Patient:</th><td><strong>${sampleData.first_name} ${sampleData.last_name}</strong></td></tr>
                            <tr><th>Patient ID:</th><td>${sampleData.patient_id}</td></tr>
                            <tr><th>Doctor:</th><td>${sampleData.doctor_name}</td></tr>
                            <tr><th>Tests Ordered:</th><td><span class="badge badge-primary">${sampleData.test_count} tests</span></td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-warning mb-3">Collection & Receipt</h6>
                        <table class="table table-sm">
                            <tr><th width="40%">Collection Date:</th><td>${sampleData.collection_date ? new Date(sampleData.collection_date).toLocaleString() : 'N/A'}</td></tr>
                            <tr><th>Collected By:</th><td>${sampleData.collected_by || 'N/A'}</td></tr>
                            <tr><th>Condition:</th><td>${sampleData.condition_on_receipt || 'N/A'}</td></tr>
                            <tr><th>Received Date:</th><td>${sampleData.received_date ? new Date(sampleData.received_date).toLocaleString() : 'N/A'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-secondary mb-3">Storage Information</h6>
                        <table class="table table-sm">
                            <tr><th width="40%">Location:</th><td>${sampleData.storage_location || 'Not specified'}</td></tr>
                            <tr><th>Temperature:</th><td>${sampleData.storage_temperature || 'Room Temperature'}</td></tr>
                            <tr><th>Created:</th><td>${sampleData.created_at ? new Date(sampleData.created_at).toLocaleString() : 'N/A'}</td></tr>
                            <tr><th>Last Updated:</th><td>${sampleData.updated_at ? new Date(sampleData.updated_at).toLocaleString() : 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                ${sampleData.notes ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-info mb-3">Notes</h6>
                        <div class="alert alert-light">
                            ${sampleData.notes}
                        </div>
                    </div>
                </div>
                ` : ''}
                ${sampleData.clinical_info ? `
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-info mb-3">Clinical Information</h6>
                        <div class="alert alert-light">
                            ${sampleData.clinical_info}
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            $('#viewSampleModal').modal('show');
        }

        // Auto-dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>