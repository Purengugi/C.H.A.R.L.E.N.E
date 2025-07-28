<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/functions.php';

// Initialize PDO connection
$database = new Database();
$pdo = $database->connect();

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_test':
                try {
                    $sql = "INSERT INTO test_catalog (test_code, test_name, test_category, description, sample_type, reference_range, units, turnaround_time, price, is_active) 
                            VALUES (:test_code, :test_name, :test_category, :description, :sample_type, :reference_range, :units, :turnaround_time, :price, :is_active)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':test_code' => sanitize_input($_POST['test_code']),
                        ':test_name' => sanitize_input($_POST['test_name']),
                        ':test_category' => sanitize_input($_POST['test_category']),
                        ':description' => sanitize_input($_POST['description']),
                        ':sample_type' => sanitize_input($_POST['sample_type']),
                        ':reference_range' => sanitize_input($_POST['reference_range']),
                        ':units' => sanitize_input($_POST['units']),
                        ':turnaround_time' => (int)$_POST['turnaround_time'],
                        ':price' => (float)$_POST['price'],
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0
                    ]);
                    $message = 'Test added successfully!';
                    $messageType = 'success';
                    log_activity($_SESSION['user_id'], 'Test Added', 'test_catalog', $pdo->lastInsertId());
                } catch (Exception $e) {
                    $message = 'Error adding test: ' . $e->getMessage();
                    $messageType = 'danger';
                    error_log("Error adding test: " . $e->getMessage());
                }
                break;
                
            case 'toggle_status':
                try {
                    $testId = (int)$_POST['test_id'];
                    $sql = "UPDATE test_catalog SET is_active = NOT is_active WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':id' => $testId]);
                    $message = 'Test status updated successfully!';
                    $messageType = 'success';
                    log_activity($_SESSION['user_id'], 'Test Status Changed', 'test_catalog', $testId);
                } catch (Exception $e) {
                    $message = 'Error updating test status: ' . $e->getMessage();
                    $messageType = 'danger';
                    error_log("Error updating test status: " . $e->getMessage());
                }
                break;
        }
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $testId = (int)$_GET['delete'];
        $sql = "DELETE FROM test_catalog WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $testId]);
        $message = 'Test deleted successfully!';
        $messageType = 'success';
        log_activity($_SESSION['user_id'], 'Test Deleted', 'test_catalog', $testId);
    } catch (Exception $e) {
        $message = 'Error deleting test: ' . $e->getMessage();
        $messageType = 'danger';
        error_log("Error deleting test: " . $e->getMessage());
    }
}

// Fetch tests with search and filter
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$sql = "SELECT * FROM test_catalog WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (test_code LIKE :search OR test_name LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $sql .= " AND test_category = :category";
    $params[':category'] = $category_filter;
}

if ($status_filter !== '') {
    $sql .= " AND is_active = :status";
    $params[':status'] = (int)$status_filter;
}

$sql .= " ORDER BY test_code ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching test catalog: " . $e->getMessage());
    $tests = [];
}

// Fetch categories for filter dropdown
try {
    $sql = "SELECT DISTINCT test_category FROM test_catalog WHERE test_category IS NOT NULL AND test_category != '' ORDER BY test_category";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get statistics
try {
    $sql = "SELECT 
                COUNT(*) as total_tests,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tests,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_tests,
                COUNT(DISTINCT test_category) as total_categories
            FROM test_catalog";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats = ['total_tests' => 0, 'active_tests' => 0, 'inactive_tests' => 0, 'total_categories' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Test Catalog Management</title>
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
                <a class="nav-link" href="system_reports.php">
                    <i class="fas fa-chart-bar"></i> System Reports
                </a>
                <a class="nav-link active" href="test_catalog.php">
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
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-0">
                                <i class="fas fa-list text-gold"></i> Test Catalog Management
                            </h2>
                            <p class="mb-0 text-muted">Manage laboratory tests and pricing</p>
                        </div>
                        <div class="col-md-4 text-right">
                            <button class="btn btn-gold" data-toggle="modal" data-target="#addTestModal">
                                <i class="fas fa-plus"></i> Add New Test
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-flask"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_tests']; ?></div>
                            <div class="label">Total Tests</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="number"><?php echo $stats['active_tests']; ?></div>
                            <div class="label">Active Tests</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-pause-circle"></i>
                            </div>
                            <div class="number"><?php echo $stats['inactive_tests']; ?></div>
                            <div class="label">Inactive Tests</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_categories']; ?></div>
                            <div class="label">Categories</div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-search"></i> Search & Filter
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="search">Search Tests</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search by code, name, or description">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select class="form-control" id="category" name="category">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                                        <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="">All Status</option>
                                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-gold btn-block">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Test Catalog Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Test Catalog
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Test Code</th>
                                        <th>Test Name</th>
                                        <th>Category</th>
                                        <th>Sample Type</th>
                                        <th>Turnaround</th>
                                        <th>Price (KES)</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tests)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> No tests found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tests as $test): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-gold"><?php echo htmlspecialchars($test['test_code']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($test['test_name']); ?></strong>
                                                    <?php if (!empty($test['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($test['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-gold"><?php echo htmlspecialchars($test['test_category'] ?? 'N/A'); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($test['sample_type'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($test['turnaround_time'] ?? 'N/A'); ?> hrs</td>
                                                <td>
                                                    <?php if ($test['price']): ?>
                                                        <strong class="text-gold">KES <?php echo number_format($test['price'], 2); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $test['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                            <i class="fas fa-<?php echo $test['is_active'] ? 'check' : 'times'; ?>"></i>
                                                            <?php echo $test['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        
                                                        <button class="btn btn-sm btn-danger" onclick="deleteTest(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['test_name']); ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
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

    <!-- Add Test Modal -->
    <div class="modal fade" id="addTestModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus"></i> Add New Test
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_test">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="test_code">Test Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="test_code" name="test_code" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="test_name">Test Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="test_name" name="test_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="test_category">Category</label>
                                    <input type="text" class="form-control" id="test_category" name="test_category">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sample_type">Sample Type</label>
                                    <select class="form-control" id="sample_type" name="sample_type">
                                        <option value="">Select Sample Type</option>
                                        <option value="Blood">Blood</option>
                                        <option value="Urine">Urine</option>
                                        <option value="Stool">Stool</option>
                                        <option value="Sputum">Sputum</option>
                                        <option value="Swab">Swab</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reference_range">Reference Range</label>
                                    <input type="text" class="form-control" id="reference_range" name="reference_range">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="units">Units</label>
                                    <input type="text" class="form-control" id="units" name="units">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="turnaround_time">Turnaround Time (hours)</label>
                                    <input type="number" class="form-control" id="turnaround_time" name="turnaround_time" min="1" value="24">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price">Price (KES)</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                <label class="custom-control-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-plus"></i> Add Test
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script>
        
        
        function deleteTest(testId, testName) {
            if (confirm('Are you sure you want to delete the test "' + testName + '"? This action cannot be undone.')) {
                window.location.href = '?delete=' + testId;
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>