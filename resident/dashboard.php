<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

// Get resident information
$user_id = $_SESSION['user_id'];

$sql = "SELECT rp.*, rp.student_id, r.room_number, d.name as dormitory_name
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

// Get latest technical issues - FIXED: Changed issue_id to id
$sql = "SELECT id, title, status, reported_at
        FROM technical_issues
        WHERE user_id = ?
        ORDER BY reported_at DESC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$latest_issues = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $latest_issues[] = $row;
    }
}

// Get upcoming events
$sql = "SELECT e.id, e.title, e.start_time, e.end_time, e.location, d.name as dormitory_name
        FROM events e
        LEFT JOIN dormitories d ON e.dormitory_id = d.id
        WHERE (e.dormitory_id IS NULL OR e.dormitory_id = ?)
        AND e.start_time > NOW()
        ORDER BY e.start_time ASC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $resident_info['dormitory_id']);
$stmt->execute();
$result = $stmt->get_result();

$upcoming_events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}

// Get latest forum topics
$sql = "SELECT ft.id, ft.title, fc.name as category, ft.created_at, u.full_name
        FROM forum_topics ft
        JOIN forum_categories fc ON ft.category_id = fc.id
        JOIN users u ON ft.user_id = u.id
        ORDER BY ft.created_at DESC LIMIT 5";

$result = $conn->query($sql);
$latest_topics = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $latest_topics[] = $row;
    }
}

// Get latest marketplace items
$sql = "SELECT m.id, m.title, m.price, m.status, m.created_at, u.full_name as seller
        FROM marketplace_items m
        JOIN users u ON m.user_id = u.id
        WHERE m.status = 'available'
        ORDER BY m.created_at DESC LIMIT 3";

$result = $conn->query($sql);
$latest_items = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $latest_items[] = $row;
    }
}

// Get facility bookings
$sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, 
               f.name as facility_name
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        WHERE b.user_id = ? AND b.booking_date >= CURDATE()
        ORDER BY b.booking_date ASC, b.start_time ASC
        LIMIT 3";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$upcoming_bookings = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $upcoming_bookings[] = $row;
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
    <title>Resident Dashboard - PresDorm</title>
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
        
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-card.primary {
            border-left-color: var(--primary);
        }
        
        .stat-card.success {
            border-left-color: var(--success);
        }
        
        .stat-card.info {
            border-left-color: var(--info);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .stat-card.primary .stat-icon {
            color: var(--primary);
        }
        
        .stat-card.success .stat-icon {
            color: var(--success);
        }
        
        .stat-card.info .stat-icon {
            color: var(--info);
        }
        
        .stat-card.warning .stat-icon {
            color: var(--warning);
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .card-scroll {
            max-height: 300px;
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
        
        .list-item {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #e3e6f0;
            transition: background-color 0.2s;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item:hover {
            background-color: #f8f9fc;
        }
        
        .list-item-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .list-item-subtitle {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        .badge-pending {
            background-color: var(--warning);
            color: #fff;
        }
        
        .badge-in-progress {
            background-color: var(--primary);
            color: #fff;
        }
        
        .badge-resolved {
            background-color: var(--success);
            color: #fff;
        }
        
        .event-date {
            padding: 0.5rem;
            background-color: var(--primary);
            color: white;
            border-radius: 0.25rem;
            text-align: center;
            width: 60px;
            height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .event-date .day {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .event-date .month {
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        
        .marketplace-item {
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
            border-left: 4px solid var(--success);
        }
        
        .marketplace-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .marketplace-item-price {
            font-weight: 700;
            color: var(--success);
        }
        
        .quick-links {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .quick-link-btn {
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            transition: all 0.2s;
            color: var(--dark);
            background-color: #f8f9fc;
            height: 100%;
        }
        
        .quick-link-btn:hover {
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
        }
        
        .quick-link-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .booking-item {
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #f8f9fc;
            margin-bottom: 1rem;
            border-left: 4px solid var(--info);
            transition: transform 0.2s;
        }
        
        .booking-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .booking-item-time {
            font-weight: 600;
            color: var(--dark);
        }
        
        .booking-item-facility {
            color: var(--info);
            font-weight: 600;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <a class="nav-link" href="notifications.php">
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
                        <h2>Welcome back, <?php echo $_SESSION['full_name']; ?>!</h2>
                        <p>
                            <i class="fas fa-home mr-1"></i> <?php echo isset($resident_info['dormitory_name']) ? $resident_info['dormitory_name'] : 'No dormitory assigned'; ?> &bull;
                            <i class="fas fa-door-open mr-1"></i> Room <?php echo isset($resident_info['room_number']) ? $resident_info['room_number'] : 'Not assigned'; ?>
                            <i class="fas fa-id-card mr-1"></i> Student ID: <?php echo isset($resident_info['student_id']) ? $resident_info['student_id'] : 'Belum diatur'; ?>
                        </p>
                    </div>
                    <div class="col-md-5 text-right d-none d-md-block">
                        <i class="fas fa-building" style="font-size: 5rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="welcome-wave"></div>
            </div>

            <!-- Quick Links -->
            <div class="quick-links">
                <h5 class="mb-3">Quick Actions</h5>
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <a href="issues.php?action=new" class="quick-link-btn d-block">
                            <i class="fas fa-tools"></i>
                            Report Issue
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="forum.php?action=new" class="quick-link-btn d-block">
                            <i class="fas fa-comment-alt"></i>
                            New Forum Topic
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="bookings.php" class="quick-link-btn d-block">
                            <i class="fas fa-calendar-check"></i>
                            Book Facility
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="marketplace.php" class="quick-link-btn d-block">
                            <i class="fas fa-tag"></i>
                            Sell Item
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Pending Issues Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card primary h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Pending Issues</div>
                                    <div class="stat-value"><?php echo count(array_filter($latest_issues, function($issue) { return $issue['status'] == 'pending'; })); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tools stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card success h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Upcoming Events</div>
                                    <div class="stat-value"><?php echo count($upcoming_events); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facility Bookings Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card info h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Your Bookings</div>
                                    <div class="stat-value"><?php echo count($upcoming_bookings); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card warning h-100">
                    <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Notifications</div>
                                    <div class="stat-value"><?php echo $unread_notifications; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bell stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Technical Issues Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-tools mr-1 text-primary"></i> Recent Technical Issues
                            </h5>
                            <a href="issues.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0 card-scroll">
                            <?php if (count($latest_issues) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($latest_issues as $issue): ?>
                                        <?php 
                                            $status_class = 'badge-secondary';
                                            $status_text = 'Unknown';
                                            
                                            if ($issue['status'] == 'pending') {
                                                $status_class = 'badge-pending';
                                                $status_text = 'Pending';
                                            } elseif ($issue['status'] == 'in_progress') {
                                                $status_class = 'badge-in-progress';
                                                $status_text = 'In Progress';
                                            } elseif ($issue['status'] == 'resolved') {
                                                $status_class = 'badge-resolved';
                                                $status_text = 'Resolved';
                                            }
                                        ?>
                                        <div class="list-item">
                                            <div class="d-flex justify-content-between">
                                                <div class="list-item-title"><?php echo htmlspecialchars($issue['title']); ?></div>
                                                <div>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </div>
                                            </div>
                                            <div class="list-item-subtitle">
                                                <i class="far fa-clock mr-1"></i> <?php echo date('M d, Y H:i', strtotime($issue['reported_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-tools text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No reported issues yet.</p>
                                    <a href="issues.php?action=new" class="btn btn-sm btn-primary">Report an Issue</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-calendar-alt mr-1 text-success"></i> Upcoming Events
                            </h5>
                            <a href="events.php" class="btn btn-sm btn-success">View All</a>
                        </div>
                        <div class="card-body p-0 card-scroll">
                            <?php if (count($upcoming_events) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <div class="list-item">
                                            <div class="d-flex">
                                                <div class="event-date">
                                                    <span class="day"><?php echo date('d', strtotime($event['start_time'])); ?></span>
                                                    <span class="month"><?php echo date('M', strtotime($event['start_time'])); ?></span>
                                                </div>
                                                <div>
                                                    <div class="list-item-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                                    <div class="list-item-subtitle">
                                                        <i class="far fa-clock mr-1"></i> <?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                    </div>
                                                    <div class="list-item-subtitle">
                                                        <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($event['location']); ?>
                                                        <?php if (!empty($event['dormitory_name'])): ?>
                                                            (<?php echo htmlspecialchars($event['dormitory_name']); ?>)
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No upcoming events scheduled.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Row -->
            <div class="row">
                <!-- Forum Topics -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-comments mr-1 text-info"></i> Latest Forum Topics
                            </h5>
                            <a href="forum.php" class="btn btn-sm btn-info">View All</a>
                        </div>
                        <div class="card-body p-0 card-scroll">
                            <?php if (count($latest_topics) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($latest_topics as $topic): ?>
                                        <div class="list-item">
                                            <div class="list-item-title">
                                                <a href="forum_topic.php?id=<?php echo $topic['id']; ?>" class="text-dark">
                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                </a>
                                            </div>
                                            <div class="list-item-subtitle">
                                                <span class="badge badge-light"><?php echo htmlspecialchars($topic['category']); ?></span>
                                                <i class="far fa-user ml-2 mr-1"></i> <?php echo htmlspecialchars($topic['full_name']); ?>
                                                <i class="far fa-clock ml-2 mr-1"></i> <?php echo date('M d, Y', strtotime($topic['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No forum topics created yet.</p>
                                    <a href="forum.php?action=new" class="btn btn-sm btn-info">Create a Topic</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Marketplace Items & Facility Bookings -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-calendar-check mr-1 text-info"></i> Your Upcoming Bookings
                            </h5>
                            <a href="bookings.php" class="btn btn-sm btn-info">Book Facility</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_bookings) > 0): ?>
                                <?php foreach ($upcoming_bookings as $booking): ?>
                                    <div class="booking-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="booking-item-facility"><?php echo htmlspecialchars($booking['facility_name']); ?></div>
                                                <div class="booking-item-time">
                                                    <i class="far fa-calendar-alt mr-1"></i> <?php echo date('l, F d, Y', strtotime($booking['booking_date'])); ?>
                                                </div>
                                                <div>
                                                    <i class="far fa-clock mr-1"></i> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($booking['status'] == 'pending'): ?>
                                                    <span class="badge badge-warning">Pending</span>
                                                <?php elseif ($booking['status'] == 'approved'): ?>
                                                    <span class="badge badge-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary"><?php echo ucfirst($booking['status']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-check text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">You don't have any upcoming facility bookings.</p>
                                    <a href="bookings.php" class="btn btn-sm btn-info">Book a Facility</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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