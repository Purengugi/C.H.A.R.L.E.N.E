// LIMS Project JavaScript Functions
// Main script file for Laboratory Information Management System

// Initialize when document is ready
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Initialize DataTables if available
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
    
    // Initialize date pickers
    if ($.fn.datepicker) {
        $('.date-picker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Session timeout warning
    setupSessionTimeout();
    
    // Form validation
    setupFormValidation();
    
    // AJAX setup
    setupAjax();
});

// Session timeout management
function setupSessionTimeout() {
    let sessionTimeout = 3600; // 1 hour in seconds
    let warningTime = 300; // 5 minutes before timeout
    let timeLeft = sessionTimeout;
    
    // Update every minute
    setInterval(function() {
        timeLeft -= 60;
        
        if (timeLeft <= warningTime && timeLeft > 0) {
            showSessionWarning(timeLeft);
        } else if (timeLeft <= 0) {
            handleSessionTimeout();
        }
    }, 60000);
}

// Show session timeout warning
function showSessionWarning(timeLeft) {
    let minutes = Math.floor(timeLeft / 60);
    let seconds = timeLeft % 60;
    
    if (!$('#sessionWarningModal').length) {
        $('body').append(`
            <div class="modal fade" id="sessionWarningModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Session Timeout Warning</h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session will expire in <span id="timeRemaining"></span>.</p>
                            <p>Click "Continue" to extend your session.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-gold" id="extendSession">Continue</button>
                            <button type="button" class="btn btn-secondary" id="logoutNow">Logout Now</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('#extendSession').click(function() {
            extendSession();
        });
        
        $('#logoutNow').click(function() {
            logout();
        });
    }
    
    $('#timeRemaining').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
    $('#sessionWarningModal').modal('show');
}

// Handle session timeout
function handleSessionTimeout() {
    showAlert('Your session has expired. You will be redirected to the login page.', 'warning');
    setTimeout(function() {
        window.location.href = 'logout.php';
    }, 2000);
}

// Extend session
function extendSession() {
    $.ajax({
        url: 'includes/extend_session.php',
        method: 'POST',
        success: function(response) {
            $('#sessionWarningModal').modal('hide');
            showAlert('Session extended successfully.', 'success');
        },
        error: function() {
            showAlert('Error extending session. Please login again.', 'error');
        }
    });
}

// Logout function
function logout() {
    window.location.href = 'logout.php';
}

// Form validation setup
function setupFormValidation() {
    // Patient registration form validation
    $('#patientForm').on('submit', function(e) {
        let isValid = true;
        let errors = [];
        
        // Validate required fields
        $(this).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                errors.push($(this).attr('name') + ' is required');
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Validate email if provided
        let email = $('#email').val();
        if (email && !validateEmail(email)) {
            isValid = false;
            errors.push('Please enter a valid email address');
            $('#email').addClass('is-invalid');
        }
        
        // Validate phone if provided
        let phone = $('#phone').val();
        if (phone && !validatePhone(phone)) {
            isValid = false;
            errors.push('Please enter a valid phone number');
            $('#phone').addClass('is-invalid');
        }
        
        // Validate date of birth
        let dob = $('#date_of_birth').val();
        if (dob && !validateDate(dob)) {
            isValid = false;
            errors.push('Please enter a valid date of birth');
            $('#date_of_birth').addClass('is-invalid');
        }
        
        if (!isValid) {
            e.preventDefault();
            showAlert(errors.join('<br>'), 'error');
        }
    });
    
    // Test request form validation
    $('#testRequestForm').on('submit', function(e) {
        let selectedTests = $('input[name="selected_tests[]"]:checked');
        
        if (selectedTests.length === 0) {
            e.preventDefault();
            showAlert('Please select at least one test.', 'error');
            return false;
        }
        
        // Validate clinical information
        let clinicalInfo = $('#clinical_info').val();
        if (!clinicalInfo.trim()) {
            e.preventDefault();
            showAlert('Clinical information is required.', 'error');
            $('#clinical_info').addClass('is-invalid');
            return false;
        }
    });
    
    // Results entry form validation
    $('#resultsForm').on('submit', function(e) {
        let isValid = true;
        
        // Validate all result fields
        $('.result-input').each(function() {
            let value = $(this).val().trim();
            if (!value) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showAlert('Please enter all test results.', 'error');
        }
    });
}

// AJAX setup
function setupAjax() {
    // Set default AJAX settings
    $.ajaxSetup({
        timeout: 30000,
        beforeSend: function() {
            showLoading();
        },
        complete: function() {
            hideLoading();
        },
        error: function(xhr, status, error) {
            if (xhr.status === 401) {
                showAlert('Session expired. Please login again.', 'error');
                setTimeout(function() {
                    window.location.href = 'logout.php';
                }, 2000);
            } else {
                showAlert('An error occurred: ' + error, 'error');
            }
        }
    });
}

// Utility functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[\d\s\-\+\(\)]{10,}$/;
    return re.test(phone);
}

function validateDate(date) {
    const re = /^\d{2}\/\d{2}\/\d{4}$/;
    if (!re.test(date)) return false;
    
    const parts = date.split('/');
    const day = parseInt(parts[0]);
    const month = parseInt(parts[1]);
    const year = parseInt(parts[2]);
    
    const testDate = new Date(year, month - 1, day);
    return testDate.getDate() === day && 
           testDate.getMonth() === month - 1 && 
           testDate.getFullYear() === year;
}

// Alert functions
function showAlert(message, type = 'info') {
    let alertClass = 'alert-info';
    let icon = 'fa-info-circle';
    
    switch(type) {
        case 'success':
            alertClass = 'alert-success';
            icon = 'fa-check-circle';
            break;
        case 'error':
        case 'danger':
            alertClass = 'alert-danger';
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            alertClass = 'alert-warning';
            icon = 'fa-exclamation-triangle';
            break;
    }
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${icon}"></i> ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the page
    $('body').prepend(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}

// Loading functions
function showLoading() {
    if (!$('#loadingOverlay').length) {
        $('body').append(`
            <div id="loadingOverlay" class="loading-overlay">
                <div class="spinner"></div>
                <p>Please wait...</p>
            </div>
        `);
    }
    $('#loadingOverlay').show();
}

function hideLoading() {
    $('#loadingOverlay').hide();
}

// Search functions
function searchPatients() {
    let searchTerm = $('#patientSearch').val().trim();
    
    if (searchTerm.length < 2) {
        showAlert('Please enter at least 2 characters to search.', 'warning');
        return;
    }
    
    $.ajax({
        url: 'includes/search_patients.php',
        method: 'POST',
        data: { search: searchTerm },
        success: function(response) {
            $('#patientResults').html(response);
        },
        error: function() {
            showAlert('Error searching patients.', 'error');
        }
    });
}

function searchTests() {
    let searchTerm = $('#testSearch').val().trim();
    
    if (searchTerm.length < 2) {
        $('#testResults').html('');
        return;
    }
    
    $.ajax({
        url: 'includes/search_tests.php',
        method: 'POST',
        data: { search: searchTerm },
        success: function(response) {
            $('#testResults').html(response);
        },
        error: function() {
            showAlert('Error searching tests.', 'error');
        }
    });
}

// Print functions
function printPage() {
    window.print();
}

function printElement(elementId) {
    let content = document.getElementById(elementId).innerHTML;
    let printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .no-print { display: none; }
                </style>
            </head>
            <body>
                ${content}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Export functions
function exportToCSV(tableId, filename) {
    let csv = [];
    let rows = document.querySelectorAll(`#${tableId} tr`);
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    let csvFile = new Blob([csv], { type: 'text/csv' });
    let downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Dashboard functions
function refreshDashboard() {
    location.reload();
}

function updateStats() {
    $.ajax({
        url: 'includes/get_stats.php',
        method: 'GET',
        success: function(response) {
            let stats = JSON.parse(response);
            updateStatCards(stats);
        },
        error: function() {
            showAlert('Error updating statistics.', 'error');
        }
    });
}

function updateStatCards(stats) {
    Object.keys(stats).forEach(function(key) {
        $(`#${key}`).text(stats[key]);
    });
}

// Patient functions
function selectPatient(patientId) {
    $.ajax({
        url: 'includes/get_patient.php',
        method: 'POST',
        data: { patient_id: patientId },
        success: function(response) {
            let patient = JSON.parse(response);
            populatePatientForm(patient);
        },
        error: function() {
            showAlert('Error loading patient information.', 'error');
        }
    });
}

function populatePatientForm(patient) {
    $('#selected_patient_id').val(patient.id);
    $('#patient_name').text(patient.first_name + ' ' + patient.last_name);
    $('#patient_id_display').text(patient.patient_id);
    $('#patient_dob').text(patient.date_of_birth);
    $('#patient_gender').text(patient.gender);
    $('#patient_phone').text(patient.phone);
}

// Test functions
function selectTest(testId, testName) {
    let checkbox = $(`#test_${testId}`);
    let isChecked = checkbox.is(':checked');
    
    if (isChecked) {
        addTestToOrder(testId, testName);
    } else {
        removeTestFromOrder(testId);
    }
}

function addTestToOrder(testId, testName) {
    if (!$(`#order_${testId}`).length) {
        $('#selectedTests').append(`
            <div class="selected-test" id="order_${testId}">
                <span class="test-name">${testName}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTestFromOrder(${testId})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `);
    }
}

function removeTestFromOrder(testId) {
    $(`#order_${testId}`).remove();
    $(`#test_${testId}`).prop('checked', false);
}

// Sample tracking functions
function updateSampleStatus(sampleId, status) {
    $.ajax({
        url: 'includes/update_sample_status.php',
        method: 'POST',
        data: { 
            sample_id: sampleId, 
            status: status 
        },
        success: function(response) {
            showAlert('Sample status updated successfully.', 'success');
            location.reload();
        },
        error: function() {
            showAlert('Error updating sample status.', 'error');
        }
    });
}

// Result entry functions
function calculateNormalRange(testId, value) {
    // This function can be extended to calculate normal ranges
    // based on test type, patient age, gender, etc.
    return 'Normal'; // Placeholder
}

function validateResult(input) {
    let value = $(input).val();
    let testId = $(input).data('test-id');
    
    // Basic validation - can be extended
    if (value && !isNaN(value)) {
        $(input).removeClass('is-invalid').addClass('is-valid');
    } else if (value) {
        $(input).removeClass('is-valid').addClass('is-invalid');
    }
}

// Report functions
function generateReport(type, params) {
    let url = `includes/generate_report.php?type=${type}`;
    
    if (params) {
        Object.keys(params).forEach(key => {
            url += `&${key}=${params[key]}`;
        });
    }
    
    window.open(url, '_blank');
}

// Notification functions
function showNotification(message, type = 'info') {
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('LIMS System', {
                body: message,
                icon: 'assets/images/logo.png'
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('LIMS System', {
                        body: message,
                        icon: 'assets/images/logo.png'
                    });
                }
            });
        }
    }
}

// Initialize notifications
function initializeNotifications() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Call on page load
$(document).ready(function() {
    initializeNotifications();
});