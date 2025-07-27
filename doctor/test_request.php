<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

// Redirect if not doctor
if (!is_logged_in() || $_SESSION['user_role'] !== 'doctor') {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->connect();

$success = '';
$error = '';

// Doctor info
$doctor_id = $_SESSION['user_id'];
$doctor_info = get_user_by_id($doctor_id);

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and log inputs
        $patient_id = sanitize_input($_POST['patient_id']);
        $clinical_info = sanitize_input($_POST['clinical_info']);
        $provisional_diagnosis = sanitize_input($_POST['provisional_diagnosis']);
        $urgency = sanitize_input($_POST['urgency']);
        $collection_date = sanitize_input($_POST['collection_date']);
        $collection_time = sanitize_input($_POST['collection_time']);
        $notes = sanitize_input($_POST['notes']);
        $selected_tests = isset($_POST['tests']) ? $_POST['tests'] : [];

        error_log("Received form: patient_id=$patient_id, urgency=$urgency, tests=" . implode(',', $selected_tests));

        if (empty($patient_id) || empty($selected_tests)) {
            throw new Exception("Patient ID and at least one test are required.");
        }

        // Fetch patient by patient_id string
        $patient = get_patient_by_id($patient_id);
if (!$patient) {
    throw new Exception("Patient not found with ID: " . htmlspecialchars($patient_id));
}


        $request_id = generateRequestID();

        // START transaction
        $conn->beginTransaction();

        // Insert test request
        $stmt = $conn->prepare("
            INSERT INTO test_requests 
            (request_id, patient_id, doctor_id, clinical_info, provisional_diagnosis, urgency, collection_date, collection_time, notes) 
            VALUES 
            (:request_id, :patient_id, :doctor_id, :clinical_info, :provisional_diagnosis, :urgency, :collection_date, :collection_time, :notes)
        ");
        $stmt->execute([
            ':request_id' => $request_id,
            ':patient_id' => $patient['id'],
            ':doctor_id' => $doctor_id,
            ':clinical_info' => $clinical_info,
            ':provisional_diagnosis' => $provisional_diagnosis,
            ':urgency' => $urgency,
            ':collection_date' => $collection_date,
            ':collection_time' => $collection_time,
            ':notes' => $notes
        ]);

        $request_db_id = $conn->lastInsertId();

        // Insert test items
        foreach ($selected_tests as $test_id) {
            $priority_key = 'priority_' . $test_id;
            $priority = isset($_POST[$priority_key]) ? sanitize_input($_POST[$priority_key]) : 'Normal';

            $stmt = $conn->prepare("
                INSERT INTO test_request_items (request_id, test_id, priority) 
                VALUES (:request_id, :test_id, :priority)
            ");
            $stmt->execute([
                ':request_id' => $request_db_id,
                ':test_id' => $test_id,
                ':priority' => $priority
            ]);
        }

        // Commit transaction
        $conn->commit();

        // Log activity
        log_activity($doctor_id, 'CREATE_TEST_REQUEST', 'test_requests', $request_db_id, null, [
            'request_id' => $request_id,
            'patient_id' => $patient_id
        ]);

        $success = "Test request created successfully. Request ID: $request_id";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Failed to create test request: " . $e->getMessage();
        error_log("ERROR: " . $e->getMessage());
    }
}

// Load form data
$tests_by_category = get_tests_by_category();
$recent_patients = get_recent_patients($doctor_id, 10);

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

<!-- Begin HTML display -->

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
                                <a class="nav-link" href="patient_registration.php">
                                    <i class="fas fa-user-plus"></i> Register Patient
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="test_request.php">
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
            

    <div class="col-md-9 main-content">
                <div class="dashboard-header">
                    <h2><i class="fas fa-flask"></i> Create Test Request</h2>
                    <p class="mb-0">Submit laboratory test requests for patients</p>
                </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-file-medical"></i> Test Request Form</h5>
                    </div>

    <form method="POST">
        <div class="form-group">
            <label for="patient_id">Patient ID</label>
            <input type="text" name="patient_id" id="patient_id" class="form-control" required value="<?php echo htmlspecialchars($_POST['patient_id'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="clinical_info">Clinical Info</label>
            <textarea name="clinical_info" id="clinical_info" class="form-control"><?php echo htmlspecialchars($_POST['clinical_info'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="provisional_diagnosis">Provisional Diagnosis</label>
            <textarea name="provisional_diagnosis" id="provisional_diagnosis" class="form-control"><?php echo htmlspecialchars($_POST['provisional_diagnosis'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="urgency">Urgency</label>
            <select name="urgency" id="urgency" class="form-control">
                <option value="Routine">Routine</option>
                <option value="Urgent">Urgent</option>
                <option value="STAT">STAT</option>
            </select>
        </div>

        <div class="form-group">
            <label for="collection_date">Collection Date</label>
            <input type="date" name="collection_date" id="collection_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="form-group">
            <label for="collection_time">Collection Time</label>
            <input type="time" name="collection_time" id="collection_time" class="form-control" value="<?php echo date('H:i'); ?>">
        </div>

        <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea name="notes" id="notes" class="form-control"><?php echo htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

          <h5>Available Tests</h5>
        <div class="container">
         <?php foreach ($tests_by_category as $category => $tests): ?>
          <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0"><u><?php echo htmlspecialchars($category); ?></u></h4>
            </div>
            <div class="card-body">
                <?php foreach ($tests as $test): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="tests[]" value="<?php echo $test['id']; ?>" id="test_<?php echo $test['id']; ?>">
                        <label class="form-check-label" for="test_<?php echo $test['id']; ?>">
                            <?php echo htmlspecialchars($test['test_name']); ?> (<?php echo htmlspecialchars($test['test_code']); ?>)
                        </label>
                        <select name="priority_<?php echo $test['id']; ?>" class="form-control form-control-sm w-auto d-inline ml-2">
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

        <br>
        <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-gold btn-lg">
                                                <i class="fas fa-paper-plane"></i> Submit Test Request
                                            </button>
                                            <button type="button" class="btn btn-black btn-lg ml-2" onclick="window.location.href='dashboard.php'">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            <button type="button" class="btn btn-info btn-lg ml-2" id="previewBtn">
                                                <i class="fas fa-eye"></i> Preview
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
    </form>
    <?php require_once '../includes/footer.php'; ?>
</div>

</html>
