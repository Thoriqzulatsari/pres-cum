<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Create notifications table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    notification_type VARCHAR(50) DEFAULT 'general',
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($sql);

// Handle marking notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    // Add success message
    $_SESSION['success_msg'] = "Notification marked as read.";
    redirect("notifications.php");
    exit;
}

// Handle marking all notifications as read
if (isset($_GET['mark_all_read'])) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Add success message
    $_SESSION['success_msg'] = "All notifications marked as read.";
    redirect("notifications.php");
    exit;
}

// Handle deleting notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    // Add success message
    $_SESSION['success_msg'] = "Notification deleted successfully.";
    redirect("notifications.php");
    exit;
}

// Handle deleting all read notifications
if (isset($_GET['delete_all_read'])) {
    $sql = "DELETE FROM notifications WHERE user_id = ? AND is_read = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Add success message
    $_SESSION['success_msg'] = "All read notifications deleted successfully.";
    redirect("notifications.php");
    exit;
}

// Get notifications for this user with optional filtering
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Count stats
$unread_count = 0;
$read_count = 0;
foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unread_count++;
    } else {
        $read_count++;
    }
}
$all_count = count($notifications);

// Get resident information
$sql = "SELECT rp.*, r.room_number, d.name as dormitory_name
        FROM resident_profiles rp
        JOIN rooms r ON rp.room_id = r.id
        JOIN dormitories d ON rp.dormitory_id = d.id
        WHERE rp.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $resident_info = $result->fetch_assoc();
} else {
    $resident_info = [];
}

// Get pending packages for resident
$sql = "SELECT p.id, p.tracking_number, p.courier, p.description, p.pickup_code, p.arrival_date 
        FROM packages p 
        WHERE p.resident_id = ? AND p.status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$pending_packages = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $pending_packages[] = $row;
    }
}

// Count unread notifications
$sql = "SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_notifications = $row['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - PresDorm</title>
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
        
        .dashboard-welcome {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-welcome h2 {
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .dashboard-welcome p {
            margin-bottom: 0;
            opacity: 0.8;
        }
        
        .welcome-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSI0MHB4IiB2aWV3Qm94PSIwIDAgMTI4MCAxNDAiIHByZXNlcnZlQXNwZWN0UmF0aW89Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0iI2ZmZmZmZiI+PHBhdGggZD0iTTEyODAgMEw2NDAgNzAgMCAwdjE0MGgxMjgwVjB6IiBmaWxsLW9wYWNpdHk9Ii41Ii8+PHBhdGggZD0iTTEyODAgMEgwbDY0MCA3MCAxMjgwVjB6Ii8+PC9nPjwvc3ZnPg==');
            background-size: 100% 100%;
        }
        
        .card-scroll {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .card-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .card-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .card-scroll::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .card-scroll::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .notification-item {
            padding: 1rem 1.25rem;
            border-left: 4px solid transparent;
            border-bottom: 1px solid #e3e6f0;
            transition: all 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f8f9fc;
            transform: translateY(-2px);
        }
        
        .notification-item.unread {
            background-color: #e8f4fd;
            border-left-color: var(--primary);
        }
        
        .notification-item.unread:hover {
            background-color: #d7ebfc;
        }
        
        .notification-item.package {
            border-left-color: var(--success);
        }
        
        .notification-item.event {
            border-left-color: var(--warning);
        }
        
        .notification-item.issue {
            border-left-color: var(--danger);
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        .notification-badge {
            position: absolute;
            top: 3px;
            right: 3px;
            padding: 3px 6px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .notification-icon.package {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success);
        }
        
        .notification-icon.event {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning);
        }
        
        .notification-icon.issue {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger);
        }
        
        .notification-icon.general {
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary);
        }
        
        .tab-pills {
            margin-bottom: 1rem;
        }
        
        .tab-pills .nav-link {
            border-radius: 0.35rem;
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            transition: all 0.2s;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #d1d3e2;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h4 {
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .pickup-code {
            font-family: monospace;
            font-weight: bold;
            font-size: 1.4em;
            padding: 0.5rem 1rem;
            background-color: #f8f9fc;
            border-radius: 0.35rem;
            border: 2px dashed var(--success);
            display: inline-block;
            color: var(--success);
            letter-spacing: 2px;
        }
        
        .package-card {
            border-left: 4px solid var(--success);
            transition: all 0.2s;
        }
        
        .package-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="issues.php">
                        <i class="fas fa-tools"></i> Report Issues
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar-alt"></i> Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="forum.php">
                        <i class="fas fa-comments"></i> Forum
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="marketplace.php">
                        <i class="fas fa-shopping-cart"></i> Marketplace
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge badge-danger ml-auto"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check"></i> Facility Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
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
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge badge-danger badge-counter"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header">Notifications Center</h6>
                        <?php if ($unread_notifications > 0): ?>
                            <a class="dropdown-item d-flex align-items-center" href="notifications.php">
                                <div class="mr-3">
                                    <div class="icon-circle bg-primary text-white p-2">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">Just Now</div>
                                    <span>You have <?php echo $unread_notifications; ?> unread notifications</span>
                                </div>
                            </a>
                        <?php else: ?>
                            <a class="dropdown-item text-center small text-gray-500" href="notifications.php">No new notifications</a>
                        <?php endif; ?>
                        <a class="dropdown-item text-center small text-gray-500" href="notifications.php">View All Notifications</a>
                    </div>
                </li>
                
                <div class="topbar-divider"></div>
                
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
            <!-- Welcome Banner -->
            <div class="dashboard-welcome">
                <div class="row">
                    <div class="col-md-7">
                        <h2>Your Notifications</h2>
                        <p>
                            <i class="fas fa-home mr-1"></i> <?php echo isset($resident_info['dormitory_name']) ? $resident_info['dormitory_name'] : 'No dormitory assigned'; ?> &bull;
                            <i class="fas fa-door-open mr-1"></i> Room <?php echo isset($resident_info['room_number']) ? $resident_info['room_number'] : 'Not assigned'; ?>
                        </p>
                    </div>
                    <div class="col-md-5 text-right d-none d-md-block">
                        <i class="fas fa-bell" style="font-size: 5rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="welcome-wave"></div>
            </div>

            <!-- Action Buttons -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <ul class="nav nav-pills tab-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($filter === 'all' || !isset($filter)) ? 'active bg-primary' : 'bg-white text-primary'; ?>" href="?filter=all">
                                All (<?php echo $all_count; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($filter === 'unread') ? 'active bg-primary' : 'bg-white text-primary'; ?>" href="?filter=unread">
                                Unread (<?php echo $unread_count; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($filter === 'read') ? 'active bg-primary' : 'bg-white text-primary'; ?>" href="?filter=read">
                                Read (<?php echo $read_count; ?>)
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4 text-right">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-primary mr-2">
                            <i class="fas fa-check-double mr-1"></i> Mark All as Read
                        </a>
                    <?php endif; ?>
                    <?php if ($read_count > 0): ?>
                        <a href="?delete_all_read=1" class="btn btn-danger" onclick="return confirm('Delete all read notifications?')">
                            <i class="fas fa-trash mr-1"></i> Clear Read
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_msg']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            
            <?php if (count($pending_packages) > 0): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">
                        <i class="fas fa-box mr-1 text-success"></i> Pending Packages
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($pending_packages as $package): ?>
                        <div class="col-lg-6 mb-3">
                            <div class="card package-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo !empty($package['courier']) ? htmlspecialchars($package['courier']) : 'Package'; ?>
                                    </h5>
                                    <?php if (!empty($package['tracking_number'])): ?>
                                        <p><strong>Tracking:</strong> <?php echo htmlspecialchars($package['tracking_number']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($package['description'])): ?>
                                        <p><strong>Description:</strong> <?php echo htmlspecialchars($package['description']); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Arrived:</strong> <?php echo date('F d, Y', strtotime($package['arrival_date'])); ?></p>
                                    
                                    <div class="text-center my-3">
                                        <p class="mb-2"><strong>Your Pickup Code:</strong></p>
                                        <span class="pickup-code"><?php echo htmlspecialchars($package['pickup_code']); ?></span>
                                        <p class="mt-3 text-muted small">Show this code to the front desk staff when collecting your package</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications Card -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">
                        <i class="fas fa-bell mr-1 text-primary"></i> Notification List
                    </h5>
                </div>
                <div class="card-body p-0 card-scroll">
                    <?php if (count($notifications) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <?php 
                                    // Determine notification type based on content
                                    $type = 'general';
                                    $icon = 'fa-bell';
                                    
                                    if (stripos($notification['title'], 'package') !== false) {
                                        $type = 'package';
                                        $icon = 'fa-box';
                                    } elseif (stripos($notification['title'], 'event') !== false) {
                                        $type = 'event';
                                        $icon = 'fa-calendar-alt';
                                    } elseif (stripos($notification['title'], 'issue') !== false) {
                                        $type = 'issue';
                                        $icon = 'fa-tools';
                                    }
                                    
                                    // Check for pickup code in message
                                    $hasPickupCode = stripos($notification['message'], 'pickup code') !== false;
                                    
                                    // Extract pickup code if present
                                    $pickupCode = '';
                                    if ($hasPickupCode && preg_match('/pickup code is:\s*([A-Z0-9]{6})/i', $notification['message'], $matches)) {
                                        $pickupCode = $matches[1];
                                    }
                                ?>
                                <div class="notification-item <?php echo ($notification['is_read'] == 0) ? 'unread' : ''; ?> <?php echo $type; ?>">
                                    <div class="d-flex">
                                        <div class="notification-icon <?php echo $type; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <div class="notification-title">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if ($notification['is_read'] == 0): ?>
                                                        <span class="badge badge-primary ml-2">New</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="notification-time">
                                                    <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="notification-message mt-2">
                                                <?php 
                                                    // Format message, highlighting pickup code if present
                                                    $message = htmlspecialchars($notification['message']);
                                                    if (!empty($pickupCode)) {
                                                        $message = str_replace($pickupCode, '<span class="pickup-code">' . $pickupCode . '</span>', $message);
                                                    }
                                                    echo nl2br($message); 
                                                ?>
                                            </div>
                                            <div class="mt-3">
                                                <?php if ($notification['is_read'] == 0): ?>
                                                    <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-check mr-1"></i> Mark as Read
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirm('Are you sure you want to delete this notification?')">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4>No notifications to display</h4>
                            <p class="text-muted">
                                <?php 
                                    if ($filter === 'unread') {
                                        echo "You have no unread notifications.";
                                    } elseif ($filter === 'read') {
                                        echo "You have no read notifications.";
                                    } else {
                                        echo "You don't have any notifications yet.";
                                    }
                                ?>
                            </p>
                            <?php if ($filter !== 'all'): ?>
                                <a href="?filter=all" class="btn btn-outline-primary mt-2">View All Notifications</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->

    </div>
    <!-- End of Main Content -->

    <!-- Footer -->
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>PresDorm &copy; <?php echo date('Y'); ?></span>
            </div>
        </div>
    </footer>
    <!-- End of Footer -->

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