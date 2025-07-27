USE lims_hospital;

-- Users table (doctors, lab staff, admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('doctor', 'lab', 'admin') NOT NULL,
    department VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    medical_history TEXT,
    allergies TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Test catalog table
CREATE TABLE test_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_code VARCHAR(10) UNIQUE NOT NULL,
    test_name VARCHAR(100) NOT NULL,
    test_category VARCHAR(50),
    description TEXT,
    sample_type VARCHAR(50),
    reference_range VARCHAR(100),
    units VARCHAR(20),
    turnaround_time INT DEFAULT 24, -- hours
    price DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Test requests table
CREATE TABLE test_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    clinical_info TEXT,
    provisional_diagnosis TEXT,
    urgency ENUM('Routine', 'Urgent', 'STAT') DEFAULT 'Routine',
    collection_date DATETIME,
    collection_time TIME,
    collected_by VARCHAR(100),
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

-- Test request items (individual tests within a request)
CREATE TABLE test_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    test_id INT NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    priority ENUM('Low', 'Normal', 'High', 'Critical') DEFAULT 'Normal',
    sample_id VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES test_requests(id),
    FOREIGN KEY (test_id) REFERENCES test_catalog(id)
);

-- Test results table
CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_item_id INT NOT NULL,
    result_value VARCHAR(500),
    result_status ENUM('Normal', 'Abnormal', 'Critical') DEFAULT 'Normal',
    reference_range VARCHAR(100),
    units VARCHAR(20),
    method VARCHAR(100),
    performed_by INT,
    verified_by INT,
    performed_date DATETIME,
    verified_date DATETIME,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (test_item_id) REFERENCES test_request_items(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Sample tracking table
CREATE TABLE samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sample_id VARCHAR(20) UNIQUE NOT NULL,
    request_id INT NOT NULL,
    sample_type VARCHAR(50),
    collection_date DATETIME,
    collection_time TIME,
    collected_by VARCHAR(100),
    volume VARCHAR(20),
    condition_on_receipt VARCHAR(100),
    storage_location VARCHAR(50),
    storage_temperature VARCHAR(20),
    status ENUM('Collected', 'Received', 'Processing', 'Tested', 'Stored', 'Discarded') DEFAULT 'Collected',
    received_by INT,
    received_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES test_requests(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
);

-- Audit log table
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@hospital.com', 'admin');

-- Insert sample test catalog
INSERT INTO test_catalog (test_code, test_name, test_category, description, sample_type, reference_range, units, turnaround_time, price) VALUES 
('FBC001', 'Full Blood Count', 'Hematology', 'Complete blood count with differential', 'Blood', '12-16 g/dL', 'g/dL', 2, 500.00),
('GLU001', 'Random Blood Sugar', 'Biochemistry', 'Blood glucose measurement', 'Blood', '70-140 mg/dL', 'mg/dL', 1, 200.00),
('URI001', 'Urinalysis', 'Microbiology', 'Complete urine analysis', 'Urine', 'Normal', '', 2, 300.00),
('HBA001', 'HbA1c', 'Biochemistry', 'Glycated hemoglobin', 'Blood', '<6.5%', '%', 4, 800.00),
('LIP001', 'Lipid Profile', 'Biochemistry', 'Cholesterol and triglycerides', 'Blood', 'Variable', 'mg/dL', 4, 1200.00),
('LFT001', 'Liver Function Tests', 'Biochemistry', 'Liver enzymes and function', 'Blood', 'Variable', 'U/L', 6, 1500.00),
('RFT001', 'Renal Function Tests', 'Biochemistry', 'Kidney function assessment', 'Blood', 'Variable', 'mg/dL', 4, 1000.00),
('TSH001', 'Thyroid Function', 'Endocrinology', 'Thyroid stimulating hormone', 'Blood', '0.4-4.0 mIU/L', 'mIU/L', 24, 1800.00),
('MAL001', 'Malaria Test', 'Parasitology', 'Malaria parasite detection', 'Blood', 'Negative', '', 1, 400.00),
('HIV001', 'HIV Test', 'Serology', 'HIV antibody test', 'Blood', 'Non-reactive', '', 2, 600.00);

-- Create indexes for better performance
CREATE INDEX idx_patients_patient_id ON patients(patient_id);
CREATE INDEX idx_test_requests_request_id ON test_requests(request_id);
CREATE INDEX idx_test_requests_patient ON test_requests(patient_id);
CREATE INDEX idx_test_requests_doctor ON test_requests(doctor_id);
CREATE INDEX idx_test_requests_status ON test_requests(status);
CREATE INDEX idx_samples_sample_id ON samples(sample_id);
CREATE INDEX idx_audit_log_user ON audit_log(user_id);
CREATE INDEX idx_audit_log_created ON audit_log(created_at);

-- Insert sample users
INSERT INTO users (username, password, full_name, email, role, department, created_at, updated_at) VALUES
('doctor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Smith', 'doctor1@hospital.com', 'doctor', 'Internal Medicine', NOW(), NOW()),
('doctor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Johnson', 'doctor2@hospital.com', 'doctor', 'Pediatrics', NOW(), NOW()),
('lab1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Lab Tech', 'lab1@hospital.com', 'lab', 'Clinical Laboratory', NOW(), NOW()),
('lab2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Lab Tech', 'lab2@hospital.com', 'lab', 'Radiology', NOW(), NOW());