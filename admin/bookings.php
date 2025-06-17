<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Add admin_notes column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'admin_notes'");
if ($result->num_rows === 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE bookings ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER status");
}

// Add notification_type column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'notification_type'");
if ($result->num_rows === 0) {
    // Column doesn't exist, add it
    $conn->query("ALTER TABLE notifications ADD COLUMN notification_type VARCHAR(50) DEFAULT 'general' AFTER is_read");
}

// Handle booking approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = sanitize($_POST['status']);
    $admin_notes = sanitize($_POST['admin_notes'] ?? '');
    
    // Validate status
    if (!in_array($status, ['approved', 'rejected'])) {
        $_SESSION['error_msg'] = "Invalid status selected.";
        redirect("bookings.php");
        exit;
    }
    
    // Update booking status
    $sql = "UPDATE bookings SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $admin_notes, $booking_id);
    
    if ($stmt->execute()) {
        // Get booking info for notification
        $sql = "SELECT b.*, u.id as user_id, f.name as facility_name 
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN facilities f ON b.facility_id = f.id
                WHERE b.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            
            // Create notification for user
            $notification_title = "Booking " . ucfirst($status);
            if ($status == 'approved') {
                $notification_message = "Your booking for {$booking['facility_name']} on " . date('F d, Y', strtotime($booking['booking_date'])) . 
                                        " from " . date('g:i A', strtotime($booking['start_time'])) . 
                                        " to " . date('g:i A', strtotime($booking['end_time'])) . 
                                        " has been approved.";
            } else {
                $notification_message = "Your booking for {$booking['facility_name']} on " . date('F d, Y', strtotime($booking['booking_date'])) . 
                                        " has been rejected.";
            }
            
            // Add admin notes if provided
            if (!empty($admin_notes)) {
                $notification_message .= "\n\nAdmin notes: " . $admin_notes;
            }
            
            // Check if notification_type column exists
            $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'notification_type'");
            if ($result->num_rows > 0) {
                // Include notification_type if the column exists
                $sql = "INSERT INTO notifications (user_id, title, message, created_at, is_read, notification_type) 
                        VALUES (?, ?, ?, NOW(), 0, 'event')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $booking['user_id'], $notification_title, $notification_message);
            } else {
                // Fallback without notification_type if column doesn't exist
                $sql = "INSERT INTO notifications (user_id, title, message, created_at, is_read) 
                        VALUES (?, ?, ?, NOW(), 0)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $booking['user_id'], $notification_title, $notification_message);
            }
            $stmt->execute();
        }
        
        $_SESSION['success_msg'] = "Booking status updated successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to update booking status: " . $conn->error;
    }
    
    redirect("bookings.php");
    exit;
}

// Get facilities for filter
$sql = "SELECT f.id, f.name, ft.name as type_name 
        FROM facilities f
        JOIN facility_types ft ON f.facility_type_id = ft.id
        ORDER BY ft.name, f.name";
$result = $conn->query($sql);
$facilities = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $facilities[$row['id']] = "{$row['name']} ({$row['type_name']})";
    }
}

// Set up filtering
$facility_filter = isset($_GET['facility']) ? (int)$_GET['facility'] : 0;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Prepare query conditions
$conditions = [];
$params = [];
$types = "";

// Filter by facility
if ($facility_filter > 0) {
    $conditions[] = "b.facility_id = ?";
    $params[] = $facility_filter;
    $types .= "i";
}

// Filter by status
if (!empty($status_filter)) {
    $conditions[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Filter by date
if (!empty($date_filter)) {
    $conditions[] = "b.booking_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

// Filter by search term
if (!empty($search)) {
    $conditions[] = "(u.full_name LIKE ? OR f.name LIKE ? OR b.purpose LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Build the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get bookings
$sql = "SELECT b.*, u.full_name as resident_name, u.id as user_id, 
               f.name as facility_name, f.location,
               r.room_number, d.name as dormitory_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN facilities f ON b.facility_id = f.id
        JOIN resident_profiles rp ON u.id = rp.user_id
        JOIN rooms r ON rp.room_id = r.id
        JOIN dormitories d ON rp.dormitory_id = d.id
        $where_clause
        ORDER BY b.booking_date DESC, b.start_time DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$bookings = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Get booking counts by status
$sql = "SELECT status, COUNT(*) as count FROM bookings GROUP BY status";
$result = $conn->query($sql);
$status_counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0
];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Get upcoming bookings today
$today = date('Y-m-d');
$sql = "SELECT COUNT(*) as today_bookings FROM bookings WHERE booking_date = ? AND status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$today_bookings = $result->fetch_assoc()['today_bookings'];

// View specific booking if ID is provided
$view_booking = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $booking_id = $_GET['view'];
    
    // Get booking details
    $sql = "SELECT b.*, u.full_name as resident_name, u.email as resident_email, 
                  f.name as facility_name, f.location, f.capacity,
                  r.room_number, d.name as dormitory_name
           FROM bookings b
           JOIN users u ON b.user_id = u.id
           JOIN facilities f ON b.facility_id = f.id
           JOIN resident_profiles rp ON u.id = rp.user_id
           JOIN rooms r ON rp.room_id = r.id
           JOIN dormitories d ON rp.dormitory_id = d.id
           WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $view_booking = $result->fetch_assoc();
    } else {
        $_SESSION['error_msg'] = "Booking not found.";
        redirect("bookings.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Bookings - PresDorm</title>
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
        
        /* Booking specific styles */
        .status-badge-pending {
            background-color: var(--warning);
            color: #212529;
        }
        
        .status-badge-approved {
            background-color: var(--success);
            color: white;
        }
        
        .status-badge-rejected {
            background-color: var(--danger);
            color: white;
        }
        
        .status-badge-cancelled {
            background-color: var(--secondary);
            color: white;
        }
        
        .booking-row:hover {
            background-color: #f8f9fa;
        }
        
        .today-highlight {
            border-left: 4px solid var(--success);
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
                    <a class="nav-link" href="packages.php">
                        <i class="fas fa-box mr-2"></i>Package status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings.php">
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
            <?php if (isset($view_booking)): ?>
                <!-- Single Booking View -->
                <div class="mb-3">
                    <a href="bookings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Back to All Bookings
                    </a>
                </div>
                
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-2"></i>Booking Details #<?php echo $view_booking['id']; ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Facility Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Facility</th>
                                        <td><?php echo htmlspecialchars($view_booking['facility_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Location</th>
                                        <td><?php echo htmlspecialchars($view_booking['location']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Capacity</th>
                                        <td><?php echo htmlspecialchars($view_booking['capacity']); ?> people</td>
                                    </tr>
                                    <tr>
                                        <th>Date</th>
                                        <td><?php echo date('l, F d, Y', strtotime($view_booking['booking_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Time</th>
                                        <td>
                                            <?php echo date('g:i A', strtotime($view_booking['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($view_booking['end_time'])); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Purpose</th>
                                        <td><?php echo nl2br(htmlspecialchars($view_booking['purpose'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php if ($view_booking['status'] == 'pending'): ?>
                                                <span class="badge status-badge-pending">Pending</span>
                                            <?php elseif ($view_booking['status'] == 'approved'): ?>
                                                <span class="badge status-badge-approved">Approved</span>
                                            <?php elseif ($view_booking['status'] == 'rejected'): ?>
                                                <span class="badge status-badge-rejected">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge status-badge-cancelled">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($view_booking['admin_notes'])): ?>
                                    <tr>
                                        <th>Admin Notes</th>
                                        <td><?php echo nl2br(htmlspecialchars($view_booking['admin_notes'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Resident Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Name</th>
                                        <td><?php echo htmlspecialchars($view_booking['resident_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($view_booking['resident_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dormitory</th>
                                        <td><?php echo htmlspecialchars($view_booking['dormitory_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Room</th>
                                        <td><?php echo htmlspecialchars($view_booking['room_number']); ?></td>
                                    </tr>
                                </table>
                                
                                <h5 class="mt-4">Booking Request Timeline</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Requested On</th>
                                        <td><?php echo date('F d, Y g:i A', strtotime($view_booking['created_at'])); ?></td>
                                    </tr>
                                    <?php if ($view_booking['updated_at']): ?>
                                    <tr>
                                        <th>Last Updated</th>
                                        <td><?php echo date('F d, Y g:i A', strtotime($view_booking['updated_at'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                                
                                <?php if ($view_booking['status'] == 'pending'): ?>
                                <div class="mt-4">
                                    <h5>Action Required</h5>
                                    <form method="POST" action="">
                                        <input type="hidden" name="booking_id" value="<?php echo $view_booking['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label for="admin_notes">Admin Notes (Optional)</label>
                                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="Add any notes or special instructions for the resident..."></textarea>
                                        </div>
                                        
                                        <div class="btn-group btn-group-lg w-100">
                                            <button type="submit" name="update_status" value="approved" class="btn btn-success" onclick="document.getElementById('status').value='approved';">
                                                <i class="fas fa-check mr-1"></i> Approve Booking
                                            </button>
                                            <button type="submit" name="update_status" value="rejected" class="btn btn-danger" onclick="document.getElementById('status').value='rejected';">
                                                <i class="fas fa-times mr-1"></i> Reject Booking
                                            </button>
                                        </div>
                                        <input type="hidden" id="status" name="status" value="">
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Bookings List View -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Facility Bookings Management</h1>
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
                
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="stats-icon text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stats-number"><?php echo $status_counts['pending']; ?></div>
                                <div class="stats-label">Pending Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="stats-icon text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stats-number"><?php echo $status_counts['approved']; ?></div>
                                <div class="stats-label">Approved Bookings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="stats-icon text-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stats-number"><?php echo $status_counts['rejected']; ?></div>
                                <div class="stats-label">Rejected Bookings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card today-highlight h-100">
                            <div class="card-body">
                                <div class="stats-icon text-primary">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="stats-number"><?php echo $today_bookings; ?></div>
                                <div class="stats-label">Bookings Today</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Bookings</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-inline">
                            <div class="form-group mr-3 mb-2">
                                <label class="mr-2">Facility:</label>
                                <select name="facility" class="form-control">
                                    <option value="0">All Facilities</option>
                                    <?php foreach ($facilities as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ($facility_filter == $id) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <label class="mr-2">Status:</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <label class="mr-2">Date:</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <label class="mr-2">Search:</label>
                                <input type="text" name="search" class="form-control" placeholder="Name, facility, purpose..." value="<?php echo $search; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary mb-2 mr-2">Apply Filters</button>
                            <a href="bookings.php" class="btn btn-secondary mb-2">Clear Filters</a>
                        </form>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-check mr-2"></i>Booking Requests</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($bookings) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Resident</th>
                                            <th>Facility</th>
                                            <th>Date & Time</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Requested</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): 
                                            $is_today = ($booking['booking_date'] == date('Y-m-d'));
                                        ?>
                                            <tr class="booking-row <?php echo $is_today ? 'table-success' : ''; ?>">
                                                <td><?php echo $booking['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['resident_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $booking['dormitory_name'] . ' Room ' . $booking['room_number']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['facility_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                                    <?php if ($is_today): ?>
                                                        <span class="badge badge-success">Today</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small>
                                                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($booking['purpose'], 0, 50)); ?><?php echo (strlen($booking['purpose']) > 50) ? '...' : ''; ?></td>
                                                <td>
                                                    <?php if ($booking['status'] == 'pending'): ?>
                                                        <span class="badge status-badge-pending">Pending</span>
                                                    <?php elseif ($booking['status'] == 'approved'): ?>
                                                        <span class="badge status-badge-approved">Approved</span>
                                                    <?php elseif ($booking['status'] == 'rejected'): ?>
                                                        <span class="badge status-badge-rejected">Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge status-badge-cancelled">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                                <td>
                                                    <a href="?view=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    
                                                    <?php if ($booking['status'] == 'pending'): ?>
                                                        <div class="btn-group mt-1">
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="approved">
                                                                <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="rejected">
                                                                <button type="submit" name="update_status" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times text-gray-300" style="font-size: 3rem;"></i>
                                <p class="text-gray-500 mt-3">No bookings found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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