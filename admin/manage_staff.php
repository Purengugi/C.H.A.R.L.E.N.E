<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

// Initialize database connection
$database = new Database();
$pdo = $database->connect();

// Handle staff deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff_id'])) {
    $user_id = (int)$_POST['delete_staff_id'];

    // Prevent self-deletion
    if ($_SESSION['user_id'] == $user_id) {
        $_SESSION['error'] = "You cannot delete your own account.";
        header("Location: manage_staff.php");
        exit();
    }

    try {
        // Check if the staff member has associated test requests
        $check_sql = "SELECT COUNT(*) FROM test_requests WHERE doctor_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id]);
        $count = $check_stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete staff member with associated test requests. Please deactivate instead.";
        } else {
            // Get staff data for logging before deletion
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($old_data) {
                // Delete from database
                $delete_sql = "DELETE FROM users WHERE id = ? AND role IN ('doctor', 'lab')";
                $stmt = $pdo->prepare($delete_sql);
                $stmt->execute([$user_id]);

                // Log the action
                log_activity($_SESSION['user_id'], "Delete Staff", "users", $user_id, $old_data, null);
                
                $_SESSION['success'] = "Staff member deleted successfully!";
            } else {
                $_SESSION['error'] = "Staff member not found.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting staff member: " . $e->getMessage();
        error_log("Delete error: " . $e->getMessage());
    }
}

// Handle staff update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $id = (int)$_POST['id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);

    try {
        $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, department = ? WHERE id = ?";
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([$full_name, $email, $phone, $department, $id]);

        $_SESSION['success'] = "Staff member updated successfully.";
        log_activity($_SESSION['user_id'], "Edit Staff", "users", $id, [], ["updated" => $full_name]);
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update staff: " . $e->getMessage();
        error_log("Update error: " . $e->getMessage());
    }
}

// Fetch filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build the query with filters
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM test_requests tr WHERE tr.doctor_id = u.id) as total_requests,
        (SELECT COUNT(*) FROM test_results tr 
         JOIN test_request_items tri ON tr.test_item_id = tri.id 
         WHERE tr.performed_by = u.id OR tr.verified_by = u.id) as total_results
        FROM users u 
        WHERE u.role IN ('doctor', 'lab')";

$params = [];
$where_conditions = [];

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "u.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
}

if ($department_filter) {
    $where_conditions[] = "u.department LIKE ?";
    $params[] = "%{$department_filter}%";
}

if ($search_query) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
}

if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY u.full_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching staff members: " . $e->getMessage();
    error_log("Error fetching staff: " . $e->getMessage());
    $staff_members = [];
}

// Get departments for filter dropdown
try {
    $dept_sql = "SELECT DISTINCT department FROM users WHERE role IN ('doctor', 'lab') AND department IS NOT NULL ORDER BY department";
    $dept_stmt = $pdo->prepare($dept_sql);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departments = [];
}

// Get statistics
try {
   $stats_sql = "SELECT 
    COUNT(*) as total_staff,
    SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as total_doctors,
    SUM(CASE WHEN role = 'lab' THEN 1 ELSE 0 END) as total_lab_staff,
    
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_staff,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_staff
    FROM users WHERE role IN ('doctor', 'lab')";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_staff' => 0, 'total_doctors' => 0, 'total_lab_staff' => 0, 'active_staff' => 0, 'inactive_staff' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Manage Staff</title>
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
                        <i class="fas fa-user-plus"></i> Register Staff
                    </a>
                    <a class="nav-link active" href="manage_staff.php">
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
                <div class="dashboard-header">
                    <h1 class="text-gold">
                        <i class="fas fa-users-cog"></i> Staff Management
                    </h1>
                    <p class="mb-0">Manage doctors and laboratory staff members</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_staff']; ?></div>
                            <div class="label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_doctors']; ?></div>
                            <div class="label">Doctors</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-microscope"></i>
                            </div>
                            <div class="number"><?php echo $stats['total_lab_staff']; ?></div>
                            <div class="label">Lab Staff</div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="number"><?php echo $stats['active_staff']; ?></div>
                            <div class="label">Active</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="number"><?php echo $stats['inactive_staff']; ?></div>
                            <div class="label">Inactive</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stats-card">
                            <a href="staff_registration.php" class="btn btn-gold btn-block">
                                <i class="fas fa-plus"></i> Add Staff
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter"></i> Filters & Search
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select name="role" id="role" class="form-control">
                                        <option value="">All Roles</option>
                                        <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                        <option value="lab" <?php echo $role_filter === 'lab' ? 'selected' : ''; ?>>Lab Staff</option>
                                        
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="department" class="form-label">Department</label>
                                    <select name="department" id="department" class="form-control">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                                    <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <input type="text" name="search" id="search" class="form-control" 
                                               placeholder="Name, username, email..." 
                                               value="<?php echo htmlspecialchars($search_query); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-gold">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-gold">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="manage_staff.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Staff List -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Staff Members 
                        <span class="badge badge-gold ml-2"><?php echo count($staff_members); ?> found</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($staff_members)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No staff members found</h4>
                                <p class="text-muted">Try adjusting your filters or search criteria</p>
                                <a href="staff_registration.php" class="btn btn-gold">
                                    <i class="fas fa-plus"></i> Register New Staff
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Staff Info</th>
                                            <th>Role & Department</th>
                                            <th>Contact</th>
                                            <th>Activity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staff_members as $staff): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong class="text-gold">
                                                            <?php echo htmlspecialchars($staff['full_name']); ?>
                                                        </strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user"></i> 
                                                            <?php echo htmlspecialchars($staff['username']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge badge-<?php echo $staff['role'] === 'doctor' ? 'info' : 'warning'; ?>">
                                                            <i class="fas fa-<?php echo $staff['role'] === 'doctor' ? 'user-md' : 'microscope'; ?>"></i>
                                                            
                                                            <?php echo ucfirst($staff['role']); ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php if ($staff['email']): ?>
                                                            <small>
                                                                <i class="fas fa-envelope"></i> 
                                                                <?php echo htmlspecialchars($staff['email']); ?>
                                                            </small>
                                                            <br>
                                                        <?php endif; ?>
                                                        <?php if ($staff['phone']): ?>
                                                            <small>
                                                                <i class="fas fa-phone"></i> 
                                                                <?php echo htmlspecialchars($staff['phone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php if ($staff['role'] === 'doctor'): ?>
                                                            <small>
                                                                <i class="fas fa-file-medical"></i> 
                                                                <?php echo $staff['total_requests']; ?> requests
                                                            </small>
                                                        <?php else: ?>
                                                            <small>
                                                                <i class="fas fa-vial"></i> 
                                                                <?php echo $staff['total_results']; ?> results
                                                            </small>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar"></i> 
                                                            <?php echo date('M j, Y', strtotime($staff['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $staff['is_active'] ? 'success' : 'danger'; ?>">
                                                        <i class="fas fa-<?php echo $staff['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                        <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <!-- Edit button -->
                                                        <button type="button" class="btn btn-sm btn-warning mr-2 edit-btn"
                                                            data-id="<?php echo $staff['id']; ?>"
                                                            data-fullname="<?php echo htmlspecialchars($staff['full_name']); ?>"
                                                            data-email="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>"
                                                            data-phone="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>"
                                                            data-department="<?php echo htmlspecialchars($staff['department'] ?? ''); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>

                                                        <!-- Delete button -->
                                                        <form method="POST" action="" style="display:inline;">
                                                            <input type="hidden" name="delete_staff_id" value="<?php echo $staff['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this staff member?');">
                                                                <i class="fas fa-trash-alt"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
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

    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Staff Member</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_staff" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_full_name">Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" name="department" id="edit_department" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Edit button functionality
        $(document).on('click', '.edit-btn', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_full_name').val($(this).data('fullname'));
            $('#edit_email').val($(this).data('email'));
            $('#edit_phone').val($(this).data('phone'));
            $('#edit_department').val($(this).data('department'));
            $('#editStaffModal').modal('show');
        });
    </script>
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>