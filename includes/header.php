<?php
// header.php - Common header for all pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Get user info
$user_info = get_user_info();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <!-- Additional CSS if needed -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <meta name="description" content="Clinical Health Access & Results Linking for Efficient Notification Exchange">
    <meta name="author" content="C.H.A.R.L.E.N.E Hospital">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/favicon.png">
</head>
<body class="bg-dark-gray">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-black fixed-top">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand" href="<?php echo BASE_URL . $user_info['role']; ?>/dashboard.php">
                <img src="../assets/images/logo1.png" alt="Logo" style="height: 50px; vertical-align: middle; margin-right: 2px;">
                  C.H.A.R.L.E.N.E
            </a>
            
                <!-- User info dropdown -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($user_info['name']); ?> 
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main content wrapper -->
    <div class="container-fluid mt-5 pt-3">
        <!-- Session timeout warning -->
        <div id="sessionWarning" class="alert alert-warning alert-dismissible fade show d-none" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Session Warning:</strong> Your session will expire in <span id="timeRemaining"></span> minutes. 
            <a href="#" onclick="renewSession()" class="alert-link">Click here to extend your session</a>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        
        <!-- Flash messages -->
        <?php display_flash_message(); ?>
        
        <!-- Page breadcrumb -->
        <?php if (isset($breadcrumb)): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-light-gray">
                    <?php foreach ($breadcrumb as $item): ?>
                        <?php if (isset($item['url'])): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo $item['url']; ?>" class="text-gold">
                                    <?php echo $item['title']; ?>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item active text-white">
                                <?php echo $item['title']; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <!-- Page loading indicator -->
        <div id="pageLoading" class="d-none text-center py-5">
            <div class="spinner"></div>
            <p class="text-muted mt-3">Loading...</p>
        </div>
    </div>

    <!-- Session management script -->
    <script>
        let sessionTimeout = <?php echo SESSION_TIMEOUT; ?>;
        let warningTime = sessionTimeout - 300; // 5 minutes before expiry
        let lastActivity = Date.now();
        
        function checkSession() {
            let elapsed = Math.floor((Date.now() - lastActivity) / 1000);
            let remaining = sessionTimeout - elapsed;
            
            if (remaining <= 300 && remaining > 0) {
                let minutes = Math.floor(remaining / 60);
                document.getElementById('timeRemaining').textContent = minutes;
                document.getElementById('sessionWarning').classList.remove('d-none');
            } else if (remaining <= 0) {
                window.location.href = '<?php echo BASE_URL; ?>index.php?timeout=1';
            }
        }
        
        function renewSession() {
            fetch('<?php echo BASE_URL; ?>includes/renew_session.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        lastActivity = Date.now();
                        document.getElementById('sessionWarning').classList.add('d-none');
                    }
                });
        }
        
        // Update last activity on user interaction
        document.addEventListener('click', () => lastActivity = Date.now());
        document.addEventListener('keypress', () => lastActivity = Date.now());
        
        // Check session every minute
        setInterval(checkSession, 60000);
        
        // Page loading functions
        function showLoading() {
            document.getElementById('pageLoading').classList.remove('d-none');
        }
        
        function hideLoading() {
            document.getElementById('pageLoading').classList.add('d-none');
        }
    </script>