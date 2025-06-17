<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Check if pickup_code column exists, and add it if it doesn't
$result = $conn->query("SHOW COLUMNS FROM packages LIKE 'pickup_code'");
if ($result->num_rows === 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE packages ADD COLUMN pickup_code VARCHAR(8) DEFAULT NULL AFTER status");
}

// Create packages table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS packages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    resident_id INT(11) NOT NULL,
    tracking_number VARCHAR(100) NULL,
    courier VARCHAR(100) NOT NULL,
    description TEXT NULL,
    arrival_date DATETIME NOT NULL,
    status ENUM('pending', 'delivered', 'returned') NOT NULL DEFAULT 'pending',
    pickup_code VARCHAR(8) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY resident_id (resident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($sql);

// Create notifications table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($sql);

// Handle package addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_package'])) {
    $resident_id = (int)$_POST['resident_id'];
    $tracking_number = sanitize($_POST['tracking_number']);
    $courier = sanitize($_POST['courier']);
    $description = sanitize($_POST['description']);
    
    // Validate inputs
    $errors = [];
    
    if ($resident_id <= 0) {
        $errors[] = "Please select a resident.";
    }
    
    if (empty($tracking_number) && empty($description)) {
        $errors[] = "Please provide either a tracking number or package description.";
    }
    
    if (empty($courier)) {
        $errors[] = "Courier information is required.";
    }
    
    // Generate a random pickup code (6 alphanumeric characters)
    $pickup_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    
    if (empty($errors)) {
        // Insert item into database
        $sql = "INSERT INTO packages (resident_id, tracking_number, courier, description, 
                arrival_date, pickup_code, status, created_at) 
                VALUES (?, ?, ?, ?, NOW(), ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $resident_id, $tracking_number, $courier, $description, $pickup_code);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Package information added successfully!";
            
            // Get resident details for notification
            $sql = "SELECT u.full_name, u.id as user_id, r.room_number, d.name as dormitory_name
                    FROM users u
                    JOIN resident_profiles rp ON u.id = rp.user_id
                    JOIN rooms r ON rp.room_id = r.id
                    JOIN dormitories d ON rp.dormitory_id = d.id
                    WHERE u.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $resident_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $resident = $result->fetch_assoc();
                
                // Create notification with pickup code
                $notification_title = "Package Arrived";
                $notification_message = "You have a package from " . $courier;
                if (!empty($tracking_number)) {
                    $notification_message .= " (Tracking #: " . $tracking_number . ")";
                }
                $notification_message .= ". Please collect it from the front desk.\n\nYour pickup code is: " . $pickup_code;
                
                $sql = "INSERT INTO notifications (user_id, title, message, created_at, is_read) 
                        VALUES (?, ?, ?, NOW(), 0)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $resident_id, $notification_title, $notification_message);
                $stmt->execute();
            }
            
            redirect("packages.php");
            exit;
        } else {
            $errors[] = "Failed to add package information: " . $conn->error;
        }
    }
}

// Handle package status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $package_id = (int)$_POST['package_id'];
    $status = sanitize($_POST['status']);
    
    // Validate inputs
    if (!in_array($status, ['pending', 'delivered', 'returned'])) {
        $_SESSION['error_msg'] = "Invalid status selected.";
        redirect("packages.php");
        exit;
    }
    
    $sql = "UPDATE packages SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $package_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Package status updated successfully!";
        
        // If delivered, add a notification
        if ($status == 'delivered') {
            $sql = "SELECT resident_id FROM packages WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $resident_id = $row['resident_id'];
                
                $notification_title = "Package Delivered";
                $notification_message = "Your package has been delivered to you. Thank you!";
                
                $sql = "INSERT INTO notifications (user_id, title, message, created_at, is_read) 
                        VALUES (?, ?, ?, NOW(), 0)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $resident_id, $notification_title, $notification_message);
                $stmt->execute();
            }
        }
    } else {
        $_SESSION['error_msg'] = "Failed to update package status: " . $conn->error;
    }
    
    redirect("packages.php");
    exit;
}

// Handle package deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $package_id = (int)$_GET['delete'];
    
    $sql = "DELETE FROM packages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $package_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Package information deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete package information: " . $conn->error;
    }
    
    redirect("packages.php");
    exit;
}

// Get residents for dropdown
$sql = "SELECT u.id, u.full_name, r.room_number, d.name as dormitory_name
        FROM users u
        JOIN resident_profiles rp ON u.id = rp.user_id
        JOIN rooms r ON rp.room_id = r.id
        JOIN dormitories d ON rp.dormitory_id = d.id
        WHERE u.user_type = 'resident'
        ORDER BY d.name, r.room_number, u.full_name";
$result = $conn->query($sql);
$residents = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
}

// Get dormitories for filter
$sql = "SELECT id, name FROM dormitories ORDER BY name";
$result = $conn->query($sql);
$dormitories = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dormitories[] = $row;
    }
}

// Set up filtering
$dorm_filter = isset($_GET['dorm']) ? (int)$_GET['dorm'] : 0;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Skip the complex packages query initially to show just a message and the form
$packages = [];

// Basic check to see if the table exists and is accessible
try {
    $test_query = "SELECT 1 FROM packages LIMIT 1";
    $conn->query($test_query);
    
    // If we got here, the table exists, so we can run the full query
    $sql = "SELECT p.id, p.tracking_number, p.courier, p.description, p.arrival_date, p.status, 
                  p.pickup_code, p.created_at, 
                  u.full_name as resident_name, r.room_number, d.name as dormitory_name, d.id as dormitory_id
            FROM packages p
            JOIN users u ON p.resident_id = u.id
            JOIN resident_profiles rp ON u.id = rp.user_id
            JOIN rooms r ON rp.room_id = r.id
            JOIN dormitories d ON rp.dormitory_id = d.id
            WHERE 1=1";

    // Add filters
    if ($dorm_filter > 0) {
        $sql .= " AND d.id = " . $dorm_filter;
    }
    if (!empty($status_filter)) {
        $sql .= " AND p.status = '" . $status_filter . "'";
    }
    if (!empty($search)) {
        $sql .= " AND (p.tracking_number LIKE '%" . $search . "%' OR p.description LIKE '%" . $search . "%' OR u.full_name LIKE '%" . $search . "%'";
        // Check if pickup_code column exists before including it in search
        $result = $conn->query("SHOW COLUMNS FROM packages LIKE 'pickup_code'");
        if ($result->num_rows > 0) {
            $sql .= " OR p.pickup_code LIKE '%" . $search . "%'";
        }
        $sql .= ")";
    }

    // Add sorting - newest packages first
    $sql .= " ORDER BY p.created_at DESC";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }
} catch (Exception $e) {
    // Table doesn't exist yet or other error
    $_SESSION['error_msg'] = "Setting up package system. Please add your first package.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Management - PresDorm</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a56c5;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --secondary: #858796;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 
                'Helvetica Neue', Arial, sans-serif;
            font-size: 0.9rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary) 10%, var(--primary-dark) 100%);
            color: white;
            transition: width 0.3s;
            width: 250px;
            position: fixed;
            z-index: 1;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #fff;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
            border-left-color: #fff;
            font-weight: 600;
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            opacity: 0.8;
        }
        
        .sidebar .nav-link.active i {
            opacity: 1;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: margin-left 0.3s;
            flex: 1;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 2;
        }
        
        .navbar-brand {
            display: none;
        }
        
        .topbar-divider {
            width: 0;
            border-right: 1px solid #e3e6f0;
            height: 2rem;
            margin: auto 1rem;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            padding: 0.75rem 1.25rem;
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .card-title {
            margin-bottom: 0;
            color: var(--dark);
            font-weight: 700;
            font-size: 1rem;
        }
        
        .stats-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary);
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .stats-number {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        /* Dropdown styles */
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.35rem;
        }
        
        .dropdown-item {
            transition: all 0.2s;
            padding: 0.5rem 1.5rem;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fc;
        }
        
        .dropdown-header {
            background-color: #f8f9fc;
            font-weight: 800;
            font-size: 0.65rem;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .icon-circle {
            height: 2.5rem;
            width: 2.5rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bg-primary {
            background-color: var(--primary) !important;
        }
        
        /* Animated dropdown */
        .animated--grow-in {
            animation-name: growIn;
            animation-duration: 200ms;
            animation-timing-function: transform cubic-bezier(0.18, 1.25, 0.4, 1), opacity cubic-bezier(0, 1, 0.4, 1);
        }
        
        @keyframes growIn {
            0% {
                transform: scale(0.9);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Toggle Button Styles */
        #sidebarToggle {
            background-color: rgba(0, 0, 0, 0.1);
            color: white;
            border: none;
            border-radius: 50%;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            transition: background-color 0.3s;
        }
        
        #sidebarToggle:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        .sticky-footer {
            margin-top: auto;
        }
        
        /* Package specific styles */
        .pickup-code {
            font-family: monospace;
            font-weight: bold;
            font-size: 1.1em;
            padding: 4px 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px dashed #dee2e6;
            display: inline-block;
        }
        
        .status-badge-pending {
            background-color: var(--warning);
            color: #212529;
        }
        
        .status-badge-delivered {
            background-color: var(--success);
            color: white;
        }
        
        .status-badge-returned {
            background-color: var(--danger);
            color: white;
        }
        
        .package-row:hover {
            background-color: #f8f9fa;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .navbar-brand {
                display: block;
            }
            
            .sidebar.toggled {
                width: 250px;
            }
            
            .main-content.toggled {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-building mr-2"></i>
            PresDorm
        </div>
        <div class="pt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="residents.php">
                        <i class="fas fa-users mr-2"></i>Residents
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php">
                        <i class="fas fa-door-open mr-2"></i>Rooms
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="issues.php">
                        <i class="fas fa-tools mr-2"></i>Technical Issues
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar-alt mr-2"></i>Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="forum.php">
                        <i class="fas fa-comments mr-2"></i>Forum
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="marketplace.php">
                        <i class="fas fa-store mr-2"></i>Marketplace
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="packages.php">
                        <i class="fas fa-box mr-2"></i>Package status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check mr-2"></i>Facility Bookings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Content Wrapper -->
    <div class="main-content" id="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light mb-4">
            <button id="sidebarToggle" class="d-md-none">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="dashboard.php">PresDorm</a>
            
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo $_SESSION['full_name']; ?></span>
                        <i class="fas fa-user-circle fa-fw"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                            Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../logout.php" id="logoutLink">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Begin Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Package Management</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPackageModal">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Package
                </button>
            </div>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_msg']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_msg']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
            
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Packages</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="packages.php" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label class="mr-2">Dormitory:</label>
                            <select name="dorm" class="form-control">
                                <option value="0">All Dormitories</option>
                                <?php foreach ($dormitories as $dorm): ?>
                                    <option value="<?php echo $dorm['id']; ?>" <?php echo ($dorm_filter == $dorm['id']) ? 'selected' : ''; ?>>
                                        <?php echo $dorm['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mr-3 mb-2">
                            <label class="mr-2">Status:</label>
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="delivered" <?php echo ($status_filter == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="returned" <?php echo ($status_filter == 'returned') ? 'selected' : ''; ?>>Returned</option>
                            </select>
                        </div>
                        <div class="form-group mr-3 mb-2">
                            <label class="mr-2">Search:</label>
                            <input type="text" name="search" class="form-control" placeholder="Tracking #, pickup code, name..." value="<?php echo $search; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                        <a href="packages.php" class="btn btn-secondary mb-2 ml-2">Clear Filters</a>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-box mr-2"></i>Package List</h6>
                </div>
                <div class="card-body">
                    <?php if (count($packages) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Resident</th>
                                        <th>Room</th>
                                        <th>Courier</th>
                                        <th>Tracking/Description</th>
                                        <th>Pickup Code</th>
                                        <th>Arrival Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $package): ?>
                                        <tr class="package-row">
                                            <td><?php echo htmlspecialchars($package['resident_name']); ?></td>
                                            <td><?php echo $package['dormitory_name'] . ' ' . $package['room_number']; ?></td>
                                            <td><?php echo htmlspecialchars($package['courier']); ?></td>
                                            <td>
                                                <?php if (!empty($package['tracking_number'])): ?>
                                                    <strong>Tracking:</strong> <?php echo htmlspecialchars($package['tracking_number']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($package['description'])): ?>
                                                    <small><?php echo htmlspecialchars($package['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($package['pickup_code'])): ?>
                                                    <span class="pickup-code">
                                                        <?php echo htmlspecialchars($package['pickup_code']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($package['arrival_date'])); ?></td>
                                            <td>
                                                <span class="badge status-badge-<?php echo $package['status']; ?>">
                                                    <?php echo ucfirst($package['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                        <?php if ($package['status'] == 'pending'): ?>
                                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                                <input type="hidden" name="status" value="delivered">
                                                                <button type="submit" name="update_status" class="dropdown-item text-success">
                                                                    <i class="fas fa-check mr-1"></i> Mark as Delivered
                                                                </button>
                                                            </form>
                                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                                <input type="hidden" name="status" value="returned">
                                                                <button type="submit" name="update_status" class="dropdown-item text-danger">
                                                                    <i class="fas fa-undo mr-1"></i> Mark as Returned
                                                                </button>
                                                            </form>
                                                        <?php elseif ($package['status'] == 'delivered'): ?>
                                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                                <input type="hidden" name="status" value="pending">
                                                                <button type="submit" name="update_status" class="dropdown-item text-warning">
                                                                    <i class="fas fa-undo mr-1"></i> Mark as Pending
                                                                </button>
                                                            </form>
                                                        <?php elseif ($package['status'] == 'returned'): ?>
                                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                                <input type="hidden" name="status" value="pending">
                                                                <button type="submit" name="update_status" class="dropdown-item text-warning">
                                                                    <i class="fas fa-undo mr-1"></i> Mark as Pending
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a href="?delete=<?php echo $package['id']; ?>" class="dropdown-item text-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this package information?')">
                                                            <i class="fas fa-trash mr-1"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box text-gray-300" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No packages found matching your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>PresDorm &copy; <?php echo date('Y'); ?></span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->
    </div>
    <!-- End of Main Content -->

    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1" role="dialog" aria-labelledby="addPackageModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addPackageModalLabel">Add New Package</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="resident_id">Resident</label>
                            <select class="form-control" id="resident_id" name="resident_id" required>
                                <option value="">-- Select Resident --</option>
                                <?php foreach ($residents as $resident): ?>
                                    <option value="<?php echo $resident['id']; ?>">
                                        <?php echo htmlspecialchars($resident['full_name']) . ' - ' . $resident['dormitory_name'] . ' ' . $resident['room_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="courier">Courier/Sender</label>
                            <input type="text" class="form-control" id="courier" name="courier" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tracking_number">Tracking Number (Optional)</label>
                            <input type="text" class="form-control" id="tracking_number" name="tracking_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Package Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            <small class="form-text text-muted">Size, type or any distinguishing characteristics of the package.</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> A pickup code will be automatically generated and sent to the resident.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_package" class="btn btn-primary">Add Package & Notify Resident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('toggled');
            document.getElementById('content').classList.toggle('toggled');
        });

        // Initialize Bootstrap dropdowns
        $(document).ready(function() {
            // Enable dropdown functionality
            $('.dropdown-toggle').dropdown();
            
            // Handle logout link click
            $('#logoutLink').on('click', function(e) {
                e.preventDefault();
                window.location.href = '../logout.php';
            });
        });
    </script>
</body>
</html>