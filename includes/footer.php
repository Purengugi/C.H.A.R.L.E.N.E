</div> <!-- End main content wrapper -->
    
    <!-- Footer -->
    <footer class="bg-black text-center py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-md-left">
                    <p class="mb-0 text-white">
                        <i class="fas fa-flask text-gold"></i>
                        &copy; <?php echo date('Y'); ?> C.H.A.R.L.E.N.E - Clinical Hub for Accurate Results, Lab Efficiency & Notification Enhancement
                    </p>
                </div>
                <div class="col-md-6 text-md-right">
                    <p class="mb-0 text-muted">
                        Version <?php echo APP_VERSION; ?> | 
                        <span class="text-gold">User:</span> <?php echo htmlspecialchars($user_info['name']); ?> 
                        (<span class="text-gold"><?php echo ucfirst($user_info['role']); ?></span>)
                    </p>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-gold"></i>
                        All patient data is encrypted and protected | 
                        <i class="fas fa-clock text-gold"></i>
                        System Time: <?php echo date('Y-m-d H:i:s'); ?> (<?php echo TIMEZONE; ?>)
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-gold">
                        <i class="fas fa-question-circle"></i> Confirm Action
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-white" id="confirmationMessage">Are you sure you want to perform this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-gold" id="confirmButton">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner mb-3"></div>
                    <p class="text-white mb-0">Processing...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-gold">
                        <i class="fas fa-check-circle"></i> Success
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-white" id="successMessage">Operation completed successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-gold" data-dismiss="modal">
                        <i class="fas fa-check"></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-white" id="errorMessage">An error occurred. Please try again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo BASE_URL; ?>assets/js/script.js"></script>
    
    <!-- Additional JavaScript if needed -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($page_js)): ?>
        <script>
            <?php echo $page_js; ?>
        </script>
    <?php endif; ?>

    <script>
        // Global JavaScript functions
        
        // Show confirmation modal
        function showConfirmation(message, callback) {
            $('#confirmationMessage').text(message);
            $('#confirmButton').off('click').on('click', function() {
                $('#confirmationModal').modal('hide');
                if (typeof callback === 'function') {
                    callback();
                }
            });
            $('#confirmationModal').modal('show');
        }
        
        // Show success modal
        function showSuccess(message, callback) {
            $('#successMessage').text(message);
            $('#successModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
                if (typeof callback === 'function') {
                    callback();
                }
            });
            $('#successModal').modal('show');
        }
        
        // Show error modal
        function showError(message) {
            $('#errorMessage').text(message);
            $('#errorModal').modal('show');
        }
        
        // Show loading modal
        function showLoading() {
            $('#loadingModal').modal('show');
        }
        
        // Hide loading modal
        function hideLoading() {
            $('#loadingModal').modal('hide');
        }
        
        // AJAX form submission with loading
        function submitForm(formElement, successCallback, errorCallback) {
            const form = $(formElement);
            const formData = new FormData(form[0]);
            
            showLoading();
            
            $.ajax({
                url: form.attr('action') || window.location.href,
                type: form.attr('method') || 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideLoading();
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            if (typeof successCallback === 'function') {
                                successCallback(data);
                            } else {
                                showSuccess(data.message || 'Operation completed successfully!');
                            }
                        } else {
                            showError(data.message || 'An error occurred. Please try again.');
                        }
                    } catch (e) {
                        if (typeof errorCallback === 'function') {
                            errorCallback(response);
                        } else {
                            showError('Invalid response from server.');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    if (typeof errorCallback === 'function') {
                        errorCallback(xhr.responseText);
                    } else {
                        showError('Network error. Please check your connection and try again.');
                    }
                }
            });
        }
        
        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
        
        // Format datetime for display
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Print functionality
        function printPage() {
            window.print();
        }
        
        // Export table to CSV
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let cellData = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellData + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Auto-focus first input on page load
        $(document).ready(function() {
            $('input[type="text"], input[type="email"], input[type="password"], textarea, select').filter(':visible:first').focus();
            
            // Add loading state to buttons on form submission
            $('form').on('submit', function() {
                $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            });
            
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-toggle="popover"]').popover();
        });
        
        // Prevent double form submission
        let formSubmitted = false;
        $('form').on('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
            
            // Reset after 5 seconds
            setTimeout(function() {
                formSubmitted = false;
            }, 5000);
        });
        
        // Real-time clock in footer
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('en-GB', {
                timeZone: '<?php echo TIMEZONE; ?>',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            const clockElement = document.querySelector('footer small');
            if (clockElement) {
                clockElement.innerHTML = clockElement.innerHTML.replace(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/, timeString);
            }
        }
        
        // Update clock every second
        setInterval(updateClock, 1000);
    </script>
</body>
</html>