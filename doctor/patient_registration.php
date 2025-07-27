<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize DB connection
$database = new Database();
$conn = $database->connect();

// Check if user is logged in and is a doctor
if (!is_logged_in() || $_SESSION['user_role'] !== 'doctor') {
    redirect('../index.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Sanitize inputs
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $date_of_birth = sanitize_input($_POST['date_of_birth']);
        $gender = sanitize_input($_POST['gender']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $address = sanitize_input($_POST['address']);
        $emergency_contact = sanitize_input($_POST['emergency_contact']);
        $emergency_phone = sanitize_input($_POST['emergency_phone']);
        $medical_history = sanitize_input($_POST['medical_history']);
        $allergies = sanitize_input($_POST['allergies']);
        $created_by = $_SESSION['user_id'];

        // Generate unique patient ID
        $patient_id = 'PT' . date('Y') . sprintf('%06d', mt_rand(1, 999999));

        // Insert into database
        $sql = "INSERT INTO patients (
            patient_id, first_name, last_name, date_of_birth, gender, phone, email, address,
            emergency_contact, emergency_phone, medical_history, allergies,
            created_by, created_at, updated_at
        ) VALUES (
            :patient_id, :first_name, :last_name, :date_of_birth, :gender, :phone, :email, :address,
            :emergency_contact, :emergency_phone, :medical_history, :allergies,
            :created_by, NOW(), NOW()
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':patient_id' => $patient_id,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':date_of_birth' => $date_of_birth,
            ':gender' => $gender,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':emergency_contact' => $emergency_contact,
            ':emergency_phone' => $emergency_phone,
            ':medical_history' => $medical_history,
            ':allergies' => $allergies,
            ':created_by' => $created_by
        ]);

        $message = "Patient registered successfully! Patient ID: $patient_id";
        $_POST = []; // Clear form
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
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

<div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="p-3">
                        
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="patient_registration.php">
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
        <div class="col-md-9 col-lg-10">
            <div class="dashboard-header">
                <h2><i class="fas fa-user-plus"></i> Patient Registration</h2>
                <p class="mb-0">Register new patients in the system</p>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card card-gold">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Patient Information Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="fas fa-user"></i> Personal Information</h6>
                                
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please provide a valid first name.</div>
                                </div>

                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please provide a valid last name.</div>
                                </div>

                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please provide a valid date of birth.</div>
                                </div>

                                <div class="form-group">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a gender.</div>
                                </div>

                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                           pattern="[0-9]{10,15}">
                                    <small class="form-text text-muted">Format: 0712345678</small>
                                </div>

                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Emergency Contact & Medical Information -->
                            <div class="col-md-6">
                                <h6 class="text-gold mb-3"><i class="fas fa-phone"></i> Emergency Contact</h6>
                                
                                <div class="form-group">
                                    <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="emergency_phone" class="form-label">Emergency Phone</label>
                                    <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?php echo isset($_POST['emergency_phone']) ? htmlspecialchars($_POST['emergency_phone']) : ''; ?>" 
                                           pattern="[0-9]{10,15}">
                                </div>
                                <h6 class="text-gold mb-3 mt-4"><i class="fas fa-notes-medical"></i> Medical Information</h6>
                                
                                <div class="form-group">
                                    <label for="medical_history" class="form-label">Medical History</label>
                                    <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                              placeholder="Previous illnesses, surgeries, medications..."><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                              placeholder="Drug allergies, food allergies, etc..."><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                                </div>  
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="consent" required>
                                        <label class="custom-control-label text-white" for="consent">
                                            I confirm that the information provided is accurate and I have patient's consent to store this data.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <hr class="my-4">
                                <button type="submit" class="btn btn-gold btn-lg">
                                    <i class="fas fa-save"></i> Register Patient
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg ml-2">
                                    <i class="fas fa-undo"></i> Reset Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
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

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    var value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    e.target.value = value;
});

document.getElementById('emergency_phone').addEventListener('input', function(e) {
    var value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    e.target.value = value;
});

document.getElementById('next_of_kin_phone').addEventListener('input', function(e) {
    var value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    e.target.value = value;
});
</script>

<?php require_once '../includes/footer.php'; ?>