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

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build search query
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE (patient_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Fetch patients with pagination
$patients = [];
$total_patients = 0;

try {
    // Count total patients
    $count_sql = "SELECT COUNT(*) FROM patients " . $where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_patients = $count_stmt->fetchColumn();
    
    // Fetch patients
    $sql = "SELECT p.*, u.full_name as created_by_name 
            FROM patients p 
            LEFT JOIN users u ON p.created_by = u.id 
            " . $where_clause . " 
            ORDER BY p.created_at DESC 
            LIMIT :offset, :per_page";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $_SESSION['error'] = "Error loading patients. Please try again.";
}

// Calculate pagination
$total_pages = ceil($total_patients / $per_page);

// Handle patient deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient_id'])) {
    $patient_id = (int)$_POST['delete_patient_id'];
    
    try {
        // Check if patient has any test requests
        $check_sql = "SELECT COUNT(*) FROM test_requests WHERE patient_id = :patient_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':patient_id' => $patient_id]);
        $has_requests = $check_stmt->fetchColumn() > 0;
        
        if ($has_requests) {
            $_SESSION['error'] = "Cannot delete patient with existing test requests.";
        } else {
            // Get patient data for logging before deletion
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($old_data) {
                // Delete patient
                $delete_sql = "DELETE FROM patients WHERE id = :patient_id";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([':patient_id' => $patient_id]);
                
                // Log the action
                log_activity($_SESSION['user_id'], "Delete Patient", "patients", $patient_id, $old_data, null);
                
                $_SESSION['success'] = "Patient deleted successfully.";
            } else {
                $_SESSION['error'] = "Patient not found.";
            }
        }
    } catch (Exception $e) {
        error_log("Error deleting patient: " . $e->getMessage());
        $_SESSION['error'] = "Error deleting patient. Please try again.";
    }
    

}

// Handle patient update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $id = (int)$_POST['id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $gender = trim($_POST['gender']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    try {
        $update_sql = "UPDATE patients SET 
                      first_name = ?, 
                      last_name = ?, 
                      date_of_birth = ?, 
                      gender = ?, 
                      email = ?, 
                      phone = ?, 
                      address = ? 
                      WHERE id = ?";
        
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([$first_name, $last_name, $date_of_birth, $gender, $email, $phone, $address, $id]);

        $_SESSION['success'] = "Patient updated successfully.";
        log_activity($_SESSION['user_id'], "Update Patient", "patients", $id, [], [
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update patient: " . $e->getMessage();
        error_log("Update error: " . $e->getMessage());
    }
    
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Manage Patients</title>
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
                    <a class="nav-link active" href="manage_patients.php">
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
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 text-gold">
                                <i class="fas fa-users"></i> Manage Patients
                            </h2>
                            <p class="mb-0 text-muted">View and manage all registered patients</p>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <form method="GET" action="" class="form-inline">
                                    <div class="input-group mr-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Search by ID, name, email, or phone..."
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-gold">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="manage_patients.php" class="btn btn-outline-secondary ml-2">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="col-md-4 text-right">
                                <div class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Total Patients: <span class="text-gold font-weight-bold"><?php echo $total_patients; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patients Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table"></i> Patients List
                            <?php if (!empty($search)): ?>
                                <span class="badge badge-gold ml-2">Search Results</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No patients found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($search)): ?>
                                        Try adjusting your search criteria.
                                    <?php else: ?>
                                        No patients have been registered yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-id-card"></i> Patient ID</th>
                                            <th><i class="fas fa-user"></i> Full Name</th>
                                            <th><i class="fas fa-birthday-cake"></i> Date of Birth</th>
                                            <th><i class="fas fa-venus-mars"></i> Gender</th>
                                            <th><i class="fas fa-phone"></i> Phone</th>
                                            <th><i class="fas fa-user-md"></i> Created By</th>
                                            <th><i class="fas fa-calendar"></i> Created</th>
                                            <th><i class="fas fa-cogs"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-gold"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="font-weight-bold">
                                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                    </div>
                                                    <?php if (!empty($patient['email'])): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $dob = new DateTime($patient['date_of_birth']);
                                                    $now = new DateTime();
                                                    $age = $now->diff($dob)->y;
                                                    echo $dob->format('M d, Y');
                                                    ?>
                                                    <br><small class="text-muted">Age: <?php echo $age; ?> years</small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $patient['gender'] === 'Male' ? 'info' : ($patient['gender'] === 'Female' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo htmlspecialchars($patient['gender']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($patient['phone'])): ?>
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($patient['created_by_name'])): ?>
                                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($patient['created_by_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $created = new DateTime($patient['created_at']);
                                                    echo $created->format('M d, Y');
                                                    ?>
                                                    <br><small class="text-muted"><?php echo $created->format('H:i'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <!-- Edit button -->
                                                        <button type="button" class="btn btn-sm btn-warning mr-2 edit-btn"
                                                            data-id="<?php echo $patient['id']; ?>"
                                                            data-firstname="<?php echo htmlspecialchars($patient['first_name']); ?>"
                                                            data-lastname="<?php echo htmlspecialchars($patient['last_name']); ?>"
                                                            data-dob="<?php echo htmlspecialchars($patient['date_of_birth']); ?>"
                                                            data-gender="<?php echo htmlspecialchars($patient['gender']); ?>"
                                                            data-email="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>"
                                                            data-phone="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>"
                                                            data-address="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>

                                                        <!-- Delete button -->
                                                        <form method="POST" action="" style="display:inline;">
                                                            <input type="hidden" name="delete_patient_id" value="<?php echo $patient['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this patient?');">
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

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer">
                                    <nav aria-label="Patients pagination">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                        <i class="fas fa-chevron-left"></i> Previous
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                        Next <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div class="modal fade" id="editPatientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Patient</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_patient" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_first_name">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_last_name">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_date_of_birth">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_gender">Gender</label>
                                <select name="gender" id="edit_gender" class="form-control" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_phone">Phone</label>
                                <input type="tel" name="phone" id="edit_phone" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
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
            $('#edit_first_name').val($(this).data('firstname'));
            $('#edit_last_name').val($(this).data('lastname'));
            $('#edit_date_of_birth').val($(this).data('dob'));
            $('#edit_gender').val($(this).data('gender'));
            $('#edit_email').val($(this).data('email'));
            $('#edit_phone').val($(this).data('phone'));
            $('#edit_address').val($(this).data('address'));
            $('#editPatientModal').modal('show');
        });

        // Add loading state to search form
        $('form').on('submit', function() {
            $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin"></i> Searching...');
        });
    </script>
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>