<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS facility_types (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (id)
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS facilities (
    id INT(11) NOT NULL AUTO_INCREMENT,
    facility_type_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    capacity INT(11),
    location VARCHAR(255),
    PRIMARY KEY (id),
    FOREIGN KEY (facility_type_id) REFERENCES facility_types(id)
)";
$conn->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    facility_id INT(11) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (facility_id) REFERENCES facilities(id)
)";
$conn->query($sql);

// Insert facility types if they don't exist
$types = [
    ['name' => 'Common Room', 'description' => 'Shared space for study and social activities'],
    ['name' => 'Prayer Room', 'description' => 'Musholla - Space for prayer and meditation'],
    ['name' => 'Basketball Court', 'description' => 'Basketball court (SBH)'],
    ['name' => 'Volleyball Court', 'description' => 'Volleyball court (SBH)'],
    ['name' => 'Canteen Gazebo', 'description' => 'Outdoor pavilion in the canteen area']
];

foreach ($types as $type) {
    $sql = "SELECT id FROM facility_types WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $type['name']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $sql = "INSERT INTO facility_types (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $type['name'], $type['description']);
        $stmt->execute();
    }
}

// Insert default facilities if they don't exist
$facilities = [
    ['type' => 'Common Room', 'name' => 'Main Common Room', 'description' => 'Main common room located on the ground floor', 'capacity' => 30, 'location' => 'Ground Floor'],
    ['type' => 'Prayer Room', 'name' => 'Musholla', 'description' => 'Prayer room for resident use', 'capacity' => 20, 'location' => 'Ground Floor'],
    ['type' => 'Basketball Court', 'name' => 'SBH Basketball Court', 'description' => 'Full-size basketball court', 'capacity' => 10, 'location' => 'Student Boarding House Yard'],
    ['type' => 'Volleyball Court', 'name' => 'SBH Volleyball Court', 'description' => 'Volleyball court near the dormitory', 'capacity' => 12, 'location' => 'Student Boarding House Yard'],
    ['type' => 'Canteen Gazebo', 'name' => 'Canteen Gazebo 1', 'description' => 'Small gazebo near the canteen', 'capacity' => 8, 'location' => 'Canteen Area'],
    ['type' => 'Canteen Gazebo', 'name' => 'Canteen Gazebo 2', 'description' => 'Large gazebo with picnic tables', 'capacity' => 12, 'location' => 'Canteen Area']
];

foreach ($facilities as $facility) {
    // Get facility type ID
    $sql = "SELECT id FROM facility_types WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $facility['type']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $type_id = $result->fetch_assoc()['id'];
        
        // Check if facility exists
        $sql = "SELECT id FROM facilities WHERE name = ? AND facility_type_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $facility['name'], $type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $sql = "INSERT INTO facilities (facility_type_id, name, description, capacity, location) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issis", $type_id, $facility['name'], $facility['description'], 
                             $facility['capacity'], $facility['location']);
            $stmt->execute();
        }
    }
}

// Handle booking creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_booking'])) {
    $facility_id = (int)$_POST['facility_id'];
    $booking_date = sanitize($_POST['booking_date']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $purpose = sanitize($_POST['purpose']);
    $errors = [];
    
    // Validate inputs
    if (empty($booking_date)) {
        $errors[] = "Booking date is required";
    } else {
        $current_date = date('Y-m-d');
        if ($booking_date < $current_date) {
            $errors[] = "Booking date cannot be in the past";
        }
    }
    
    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start and end time are required";
    } else {
        // Check if end time is after start time
        if ($end_time <= $start_time) {
            $errors[] = "End time must be after start time";
        }
        
        // Check for existing bookings that conflict with this time slot
        $sql = "SELECT id FROM bookings 
                WHERE facility_id = ? AND booking_date = ? AND status != 'cancelled'
                AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $facility_id, $booking_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "This time slot is already booked. Please choose another time.";
        }
    }
    
    if (empty($purpose)) {
        $errors[] = "Purpose of booking is required";
    }
    
    if (empty($errors)) {
        $status = 'pending'; // Default status
        $created_at = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO bookings (user_id, facility_id, booking_date, start_time, end_time, purpose, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssss", $user_id, $facility_id, $booking_date, $start_time, $end_time, $purpose, $status, $created_at);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Booking request submitted successfully!";
            // Redirect to bookings page showing the date that was just booked
            redirect("bookings.php?tab=calendar&date=" . $booking_date);
            exit;
        } else {
            $errors[] = "Failed to create booking: " . $conn->error;
        }
    }
}

// Handle booking cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = (int)$_GET['cancel'];
    
    // Check if booking belongs to user and is pending
    $sql = "SELECT id, status, booking_date FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        $booking_date = $booking['booking_date']; // Save for redirect
        
        if ($booking['status'] == 'pending' || $booking['status'] == 'approved') {
            // Cancel the booking
            $sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $booking_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Booking cancelled successfully!";
            } else {
                $_SESSION['error_msg'] = "Failed to cancel booking: " . $conn->error;
            }
        } else {
            $_SESSION['error_msg'] = "This booking cannot be cancelled.";
        }
        
        // Redirect back to the booking date in calendar view
        redirect("bookings.php?tab=calendar&date=" . $booking_date);
        exit;
    } else {
        $_SESSION['error_msg'] = "Booking not found or you don't have permission to cancel it.";
        redirect("bookings.php");
        exit;
    }
}

// Get facility types for filter
$sql = "SELECT id, name FROM facility_types ORDER BY name";
$result = $conn->query($sql);
$facility_types = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $facility_types[$row['id']] = $row['name'];
    }
}

// Get facilities for booking
$sql = "SELECT f.id, f.name, f.description, f.capacity, f.location, ft.name as type_name 
        FROM facilities f
        JOIN facility_types ft ON f.facility_type_id = ft.id
        ORDER BY ft.name, f.name";
$result = $conn->query($sql);
$facilities = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $facilities[] = $row;
    }
}

// Get user's bookings
$sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.purpose, b.status, b.created_at,
               f.name as facility_name, f.location, ft.name as facility_type
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN facility_types ft ON f.facility_type_id = ft.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.start_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user_bookings = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $user_bookings[] = $row;
    }
}

// Get all upcoming bookings for timeline view
$current_date = date('Y-m-d');
$filter_date = isset($_GET['date']) ? sanitize($_GET['date']) : $current_date;
$filter_facility = isset($_GET['facility']) ? (int)$_GET['facility'] : 0;

// FIX: Modified the SQL query to properly retrieve bookings
$sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.user_id,
               f.id as facility_id, f.name as facility_name, u.full_name as booked_by
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_date = ? AND b.status != 'cancelled'";

if ($filter_facility > 0) {
    $sql .= " AND f.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $filter_date, $filter_facility);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_date);
}

$stmt->execute();
$result = $stmt->get_result();

$day_bookings = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $day_bookings[] = $row;
    }
}

// Check if specific facility is being viewed
$view_facility = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $facility_id = (int)$_GET['view'];
    
    foreach ($facilities as $facility) {
        if ($facility['id'] == $facility_id) {
            $view_facility = $facility;
            break;
        }
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
            background: linear-gradient(180deg, var(--primary) 10%, var(--primary-dark) 100%);
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
        
        .facility-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
            height: 100%;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .facility-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .facility-image {
            height: 150px;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--primary);
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .booking-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.8rem;
        }
        
        .timeline {
            position: relative;
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            padding: 20px 0;
        }
        
        .timeline-hours {
            display: flex;
            position: relative;
            height: 60px;
            min-width: 1200px;
        }
        
        .timeline-hour {
            width: 100px;
            text-align: center;
            border-left: 1px solid #dee2e6;
            flex: 1;
        }
        
        .timeline-hour:last-child {
            border-right: 1px solid #dee2e6;
        }
        
        .timeline-facilities {
            width: 100%;
            min-width: 1200px;
        }
        
        .timeline-facility {
            height: 50px;
            margin-bottom: 10px;
            position: relative;
            background-color: #f8f9fc;
            border-radius: 4px;
        }
        
        .timeline-booking {
            position: absolute;
            height: 40px;
            top: 5px;
            background-color: var(--primary);
            color: white;
            border-radius: 4px;
            padding: 0 5px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .timeline-booking.user-booking {
            background-color: var(--success);
        }
        
        .booking-details-modal .modal-header {
            border-bottom: none;
        }
        
        .booking-details-modal .modal-footer {
            border-top: none;
        }
        
        .date-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .date-nav .btn-group {
            margin-left: 10px;
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
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--secondary);
            font-weight: 600;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            border-radius: 0;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            border-color: var(--primary);
            color: var(--primary);
            background-color: transparent;
        }
        
        .badge-success {
            background-color: var(--success);
        }
        
        .badge-warning {
            background-color: var(--warning);
            color: #212529;
        }
        
        .badge-danger {
            background-color: var(--danger);
        }
        
        /* Calendar Styles */
        .calendar-table {
            table-layout: fixed;
        }

        .calendar-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            padding: 10px;
            text-align: center;
        }

        .calendar-cell {
            height: 120px;
            padding: 5px !important;
            vertical-align: top !important;
            position: relative;
        }

        .calendar-cell.today {
            background-color: rgba(78, 115, 223, 0.05);
            border: 2px solid #4e73df !important;
        }

        .calendar-cell.empty-day {
            background-color: #f8f9fc;
        }

        .date-number {
            font-weight: bold;
            color: #5a5c69;
            text-align: right;
            margin-bottom: 5px;
        }

        .today .date-number {
            color: #4e73df;
        }

        .booking-list {
            font-size: 0.8rem;
            overflow-y: auto;
            max-height: 85px;
        }

        .booking-entry {
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .booking-entry.user-booking {
            background-color: #1cc88a;
            color: white;
        }

        .booking-entry.other-booking {
            background-color: #4e73df;
            color: white;
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
                    <a class="nav-link" href="notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge badge-danger ml-auto"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings.php">
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
            <?php if (isset($view_facility)): ?>
                <!-- Single Facility View -->
                <!-- Welcome Banner -->
                <div class="dashboard-welcome">
                    <div class="row">
                        <div class="col-md-7">
                            <h2><?php echo htmlspecialchars($view_facility['name']); ?></h2>
                            <p>
                                <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($view_facility['location']); ?> &bull;
                                <i class="fas fa-users mr-1"></i> Capacity: <?php echo htmlspecialchars($view_facility['capacity']); ?> people
                            </p>
                        </div>
                        <div class="col-md-5 text-right d-none d-md-block">
                            <?php 
                                $icon = 'fa-building';
                                if (stripos($view_facility['type_name'], 'basketball') !== false) {
                                    $icon = 'fa-basketball-ball';
                                } elseif (stripos($view_facility['type_name'], 'volleyball') !== false) {
                                    $icon = 'fa-volleyball-ball';
                                } elseif (stripos($view_facility['type_name'], 'prayer') !== false) {
                                    $icon = 'fa-praying-hands';
                                } elseif (stripos($view_facility['type_name'], 'gazebo') !== false) {
                                    $icon = 'fa-umbrella';
                                }
                            ?>
                            <i class="fas <?php echo $icon; ?>" style="font-size: 5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                    <div class="welcome-wave"></div>
                </div>
                
                <div class="mb-3">
                    <a href="bookings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Facilities
                    </a>
                </div>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-5">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">
                                    <i class="fas <?php echo $icon; ?> mr-1 text-primary"></i> Facility Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="facility-image mb-3">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($view_facility['type_name']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($view_facility['location']); ?></p>
                                <p><strong>Capacity:</strong> <?php echo htmlspecialchars($view_facility['capacity']); ?> people</p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($view_facility['description']); ?></p>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">
                                    <i class="fas fa-calendar-plus mr-1 text-primary"></i> Book This Facility
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="facility_id" value="<?php echo $view_facility['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="booking_date">Date</label>
                                        <input type="date" class="form-control" id="booking_date" name="booking_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_time">Start Time</label>
                                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_time">End Time</label>
                                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="purpose">Purpose of Booking</label>
                                        <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                                    </div>
                                    
                                    <button type="submit" name="create_booking" class="btn btn-primary btn-block">
                                        <i class="fas fa-calendar-check mr-1"></i> Submit Booking Request
                                    </button>
                                    
                                    <div class="mt-3">
                                        <p class="text-muted small">
                                            <i class="fas fa-info-circle mr-1"></i> Your booking will be reviewed and confirmed by the administration. 
                                            You'll receive a notification once your booking is approved.
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Existing Bookings for this facility -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="fas fa-calendar mr-1 text-primary"></i> Current Bookings for <?php echo htmlspecialchars($view_facility['name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="date-nav">
                            <div class="d-flex align-items-center">
                                <form method="GET" action="" class="d-flex align-items-center">
                                    <input type="hidden" name="view" value="<?php echo $view_facility['id']; ?>">
                                    <label for="date" class="mr-2">Date:</label>
                                    <input type="date" id="date" name="date" class="form-control form-control-sm mr-2" 
                                           value="<?php echo $filter_date; ?>" min="<?php echo date('Y-m-d'); ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">View Bookings</button>
                                </form>
                                <div class="btn-group ml-3">
                                    <?php 
                                        $prev_date = date('Y-m-d', strtotime($filter_date . ' -1 day'));
                                        $next_date = date('Y-m-d', strtotime($filter_date . ' +1 day'));
                                    ?>
                                    <a href="?view=<?php echo $view_facility['id']; ?>&date=<?php echo $prev_date; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-chevron-left"></i> Previous Day
                                    </a>
                                    <a href="?view=<?php echo $view_facility['id']; ?>&date=<?php echo $next_date; ?>" class="btn btn-sm btn-outline-secondary">
                                        Next Day <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="text-center"><?php echo date('l, F d, Y', strtotime($filter_date)); ?></h6>
                        
                        <div class="timeline">
                            <div class="timeline-hours">
                                <?php for ($hour = 6; $hour <= 22; $hour++): ?>
                                    <div class="timeline-hour">
                                        <?php echo ($hour < 12) ? $hour . ' AM' : (($hour == 12) ? '12 PM' : ($hour - 12) . ' PM'); ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="timeline-facilities">
                                <div class="timeline-facility">
                                    <?php
                                        // Filter bookings for this facility
                                        $facility_bookings = array_filter($day_bookings, function($booking) use ($view_facility) {
                                            return $booking['facility_id'] == $view_facility['id'];
                                        });
                                        
                                        foreach ($facility_bookings as $booking):
                                            $start_hour = intval(substr($booking['start_time'], 0, 2));
                                            $start_minute = intval(substr($booking['start_time'], 3, 2));
                                            $end_hour = intval(substr($booking['end_time'], 0, 2));
                                            $end_minute = intval(substr($booking['end_time'], 3, 2));
                                            
                                            // Calculate position and width
                                            $start_pos = ($start_hour - 6) * 100 + ($start_minute / 60) * 100;
                                            $end_pos = ($end_hour - 6) * 100 + ($end_minute / 60) * 100;
                                            $width = $end_pos - $start_pos;
                                            
                                            // Check if this is user's booking
                                            $is_user_booking = ($booking['user_id'] == $user_id);
                                            $booking_class = $is_user_booking ? 'user-booking' : '';
                                    ?>
                                    <div class="timeline-booking <?php echo $booking_class; ?>" 
                                         style="left: <?php echo $start_pos; ?>px; width: <?php echo $width; ?>px;"
                                         data-toggle="tooltip" 
                                         title="<?php echo htmlspecialchars($booking['booked_by']); ?>: <?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?>">
                                        <?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mb-3">
                            <span class="badge badge-primary mr-2">Other Bookings</span>
                            <span class="badge badge-success">Your Bookings</span>
                        </div>
                        
                        <?php if (empty($facility_bookings)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> No bookings for this date. This facility is available for booking!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            
            <?php else: ?>
                <!-- Facilities List View -->
                <!-- Welcome Banner -->
                <div class="dashboard-welcome">
                    <div class="row">
                        <div class="col-md-7">
                            <h2>Facility Bookings</h2>
                            <p>
                                <i class="fas fa-info-circle mr-1"></i> Book common areas and facilities for your activities
                            </p>
                        </div>
                        <div class="col-md-5 text-right d-none d-md-block">
                            <i class="fas fa-calendar-check" style="font-size: 5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                    <div class="welcome-wave"></div>
                </div>
                
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['success_msg']; 
                            unset($_SESSION['success_msg']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['error_msg']; 
                            unset($_SESSION['error_msg']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'facilities') ? 'active' : ''; ?>" id="facilities-tab" data-toggle="tab" href="#facilities">Available Facilities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'my-bookings') ? 'active' : ''; ?>" id="my-bookings-tab" data-toggle="tab" href="#my-bookings">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'calendar') ? 'active' : ''; ?>" id="booking-calendar-tab" data-toggle="tab" href="#booking-calendar">Booking Calendar</a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Facilities Tab -->
                    <div class="tab-pane fade <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'facilities') ? 'show active' : ''; ?>" id="facilities">
                        <div class="row">
                            <?php if (count($facility_types) > 0): ?>
                                <?php foreach ($facility_types as $type_id => $type_name): ?>
                                    <div class="col-12 mb-3">
                                        <h4><?php echo htmlspecialchars($type_name); ?></h4>
                                        <hr>
                                        <div class="row">
                                            <?php 
                                                $filtered_facilities = array_filter($facilities, function($facility) use ($type_name) {
                                                    return $facility['type_name'] === $type_name;
                                                });
                                                
                                                foreach ($filtered_facilities as $facility): 
                                                    $icon = 'fa-building';
                                                    if (stripos($type_name, 'basketball') !== false) {
                                                        $icon = 'fa-basketball-ball';
                                                    } elseif (stripos($type_name, 'volleyball') !== false) {
                                                        $icon = 'fa-volleyball-ball';
                                                    } elseif (stripos($type_name, 'prayer') !== false) {
                                                        $icon = 'fa-praying-hands';
                                                    } elseif (stripos($type_name, 'gazebo') !== false) {
                                                        $icon = 'fa-umbrella';
                                                    }
                                            ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card facility-card h-100">
                                                    <div class="facility-image">
                                                        <i class="fas <?php echo $icon; ?>"></i>
                                                    </div>
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($facility['name']); ?></h5>
                                                        <p class="card-text">
                                                            <i class="fas fa-map-marker-alt mr-1 text-muted"></i> <?php echo htmlspecialchars($facility['location']); ?><br>
                                                            <i class="fas fa-users mr-1 text-muted"></i> Capacity: <?php echo htmlspecialchars($facility['capacity']); ?> people
                                                        </p>
                                                        <p class="card-text text-muted small"><?php echo htmlspecialchars($facility['description']); ?></p>
                                                    </div>
                                                    <div class="card-footer bg-transparent border-0">
                                                        <a href="?view=<?php echo $facility['id']; ?>" class="btn btn-primary btn-block">
                                                            <i class="fas fa-calendar-plus mr-1"></i> Book Facility
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i> No facilities are available for booking at this time.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- My Bookings Tab -->
                    <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'my-bookings') ? 'show active' : ''; ?>" id="my-bookings">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">My Booking Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($user_bookings) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Facility</th>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Purpose</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($user_bookings as $booking): ?>
                                                    <?php 
                                                        $badge_class = 'badge-secondary';
                                                        if ($booking['status'] == 'approved') {
                                                            $badge_class = 'badge-success';
                                                        } elseif ($booking['status'] == 'pending') {
                                                            $badge_class = 'badge-warning';
                                                        } elseif ($booking['status'] == 'rejected') {
                                                            $badge_class = 'badge-danger';
                                                        } elseif ($booking['status'] == 'cancelled') {
                                                            $badge_class = 'badge-secondary';
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($booking['facility_name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($booking['facility_type']); ?> - <?php echo htmlspecialchars($booking['location']); ?></small>
                                                        </td>
                                                        <td><?php echo date('l, F d, Y', strtotime($booking['booking_date'])); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                                        <td>
                                                            <?php if ($booking['status'] == 'pending' || $booking['status'] == 'approved'): ?>
                                                                <?php 
                                                                    // Only allow cancellation of future bookings
                                                                    $booking_datetime = $booking['booking_date'] . ' ' . $booking['start_time'];
                                                                    $now = date('Y-m-d H:i:s');
                                                                    if ($booking_datetime > $now):
                                                                ?>
                                                                <a href="?cancel=<?php echo $booking['id']; ?>" class="btn btn-sm btn-danger" 
                                                                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                                    <i class="fas fa-times-circle"></i> Cancel
                                                                </a>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i> You don't have any bookings yet. 
                                        <a href="#facilities" class="alert-link" data-toggle="tab" role="tab" aria-controls="facilities" aria-selected="true">Book a facility now</a>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Calendar Tab -->
                    <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'calendar') ? 'show active' : ''; ?>" id="booking-calendar">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Facility Booking Calendar</h5>
                            </div>
                            <div class="card-body">
                                <div class="date-nav mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <form method="GET" action="" class="d-flex align-items-center">
                                                <input type="hidden" name="tab" value="calendar">
                                                
                                                <label for="calendar-month" class="mr-2">Month:</label>
                                                <?php 
                                                    $selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
                                                    $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
                                                    
                                                    // Ensure we're using valid month/year
                                                    if ($selected_month < 1 || $selected_month > 12) {
                                                        $selected_month = date('n');
                                                    }
                                                ?>
                                                <select id="calendar-month" name="month" class="form-control form-control-sm mr-2">
                                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                                        <option value="<?php echo $m; ?>" <?php echo ($selected_month == $m) ? 'selected' : ''; ?>>
                                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                
                                                <label for="calendar-year" class="ml-2 mr-2">Year:</label>
                                                <select id="calendar-year" name="year" class="form-control form-control-sm mr-2">
                                                    <?php 
                                                        $current_year = date('Y');
                                                        for ($y = $current_year; $y <= $current_year + 2; $y++): 
                                                    ?>
                                                        <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                                                            <?php echo $y; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                
                                                <label for="calendar-facility" class="ml-3 mr-2">Facility:</label>
                                                <select id="calendar-facility" name="facility" class="form-control form-control-sm mr-2">
                                                    <option value="0">All Facilities</option>
                                                    <?php foreach ($facilities as $facility): ?>
                                                        <option value="<?php echo $facility['id']; ?>" <?php echo ($filter_facility == $facility['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($facility['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary">View Schedule</button>
                                            </form>
                                        </div>
                                        <div>
                                            <?php 
                                                // Previous and next month buttons
                                                $prev_month = $selected_month - 1;
                                                $prev_year = $selected_year;
                                                if ($prev_month < 1) {
                                                    $prev_month = 12;
                                                    $prev_year--;
                                                }
                                                
                                                $next_month = $selected_month + 1;
                                                $next_year = $selected_year;
                                                if ($next_month > 12) {
                                                    $next_month = 1;
                                                    $next_year++;
                                                }
                                                
                                                $facility_param = $filter_facility > 0 ? "&facility=$filter_facility" : '';
                                            ?>
                                            <a href="?tab=calendar&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year . $facility_param; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-chevron-left"></i> Previous Month
                                            </a>
                                            <a href="?tab=calendar&month=<?php echo $next_month; ?>&year=<?php echo $next_year . $facility_param; ?>" class="btn btn-sm btn-outline-secondary">
                                                Next Month <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <h4 class="text-center mb-4"><?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?></h4>
                                
                                <?php
                                    // Get bookings for the entire month
                                    $start_date = date('Y-m-d', mktime(0, 0, 0, $selected_month, 1, $selected_year));
                                    $end_date = date('Y-m-t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
                                    
                                    // SQL to get all bookings for the month
                                    $sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.user_id,
                                                  f.id as facility_id, f.name as facility_name, u.full_name as booked_by
                                          FROM bookings b
                                          JOIN facilities f ON b.facility_id = f.id
                                          JOIN users u ON b.user_id = u.id
                                          WHERE b.booking_date BETWEEN ? AND ? AND b.status != 'cancelled'";
                                    
                                    if ($filter_facility > 0) {
                                        $sql .= " AND f.id = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->bind_param("ssi", $start_date, $end_date, $filter_facility);
                                    } else {
                                        $stmt = $conn->prepare($sql);
                                        $stmt->bind_param("ss", $start_date, $end_date);
                                    }
                                    
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    $month_bookings = [];
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $date = $row['booking_date'];
                                            if (!isset($month_bookings[$date])) {
                                                $month_bookings[$date] = [];
                                            }
                                            $month_bookings[$date][] = $row;
                                        }
                                    }
                                    
                                    // Generate calendar
                                    $first_day_of_month = mktime(0, 0, 0, $selected_month, 1, $selected_year);
                                    $days_in_month = date('t', $first_day_of_month);
                                    $first_day_of_week = date('w', $first_day_of_month); // 0 (Sunday) to 6 (Saturday)
                                    
                                    // Adjust for starting week with Sunday
                                    $day_names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                                ?>
                                
                                <!-- Calendar Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered calendar-table">
                                        <thead>
                                            <tr>
                                                <?php foreach ($day_names as $day): ?>
                                                    <th class="text-center"><?php echo $day; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <?php
                                                    // Add empty cells for days before the first day of month
                                                    for ($i = 0; $i < $first_day_of_week; $i++) {
                                                        echo '<td class="calendar-cell empty-day"></td>';
                                                    }
                                                    
                                                    // Fill in the days of the month
                                                    $current_day = 1;
                                                    $current_day_of_week = $first_day_of_week;
                                                    
                                                    while ($current_day <= $days_in_month) {
                                                        if ($current_day_of_week == 7) {
                                                            echo '</tr><tr>';
                                                            $current_day_of_week = 0;
                                                        }
                                                        
                                                        $date_str = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $current_day);
                                                        $is_today = ($date_str == date('Y-m-d'));
                                                        $has_bookings = isset($month_bookings[$date_str]) && !empty($month_bookings[$date_str]);
                                                        
                                                        $cell_class = 'calendar-cell';
                                                        if ($is_today) {
                                                            $cell_class .= ' today';
                                                        }
                                                        
                                                        echo '<td class="' . $cell_class . '">';
                                                        echo '<div class="date-number">' . $current_day . '</div>';
                                                        
                                                        if ($has_bookings) {
                                                            echo '<div class="booking-list">';
                                                            foreach ($month_bookings[$date_str] as $booking) {
                                                                $is_user_booking = ($booking['user_id'] == $user_id);
                                                                $booking_class = $is_user_booking ? 'user-booking' : 'other-booking';
                                                                
                                                                $start_time = date('H:i', strtotime($booking['start_time']));
                                                                $end_time = date('H:i', strtotime($booking['end_time']));
                                                                
                                                                echo '<div class="booking-entry ' . $booking_class . '">';
                                                                echo $start_time . '-' . $end_time . ' ';
                                                                echo htmlspecialchars($booking['facility_name']);
                                                                echo '</div>';
                                                            }
                                                            echo '</div>';
                                                        }
                                                        
                                                        echo '</td>';
                                                        
                                                        $current_day++;
                                                        $current_day_of_week++;
                                                    }
                                                    
                                                    // Add empty cells for days after the end of month
                                                    while ($current_day_of_week < 7) {
                                                        echo '<td class="calendar-cell empty-day"></td>';
                                                        $current_day_of_week++;
                                                    }
                                                ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="text-center mt-3 mb-3">
                                    <span class="badge badge-primary mr-2">Other Bookings</span>
                                    <span class="badge badge-success">Your Bookings</span>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <a href="#" class="btn btn-sm btn-outline-primary" id="backToTodayBtn">Today</a>
                                    <p class="text-muted mt-3">
                                        <i class="fas fa-info-circle mr-1"></i> To book a facility, select one from the
                                        <a href="#facilities" data-toggle="tab" role="tab" aria-controls="facilities" aria-selected="true">Available Facilities</a> tab.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
    
    <!-- Booking Details Modal -->
    <div class="modal fade booking-details-modal" id="bookingDetailsModal" tabindex="-1" role="dialog" aria-labelledby="bookingDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingDetailsModalLabel">Booking Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="bookingDetailsContent">
                        <!-- Booking details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
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

        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
            
            // Check if URL has tab parameter and activate that tab
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                $('a[href="#' + tab + '"]').tab('show');
            }
            
            // Update URL when tabs are clicked
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const tabId = $(e.target).attr('href').substr(1);
                if (history.pushState) {
                    let searchParams = new URLSearchParams(window.location.search);
                    searchParams.set('tab', tabId);
                    let newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + searchParams.toString();
                    window.history.pushState({path: newurl}, '', newurl);
                }
            });
            
            // Set minimum date for booking to today
            const today = new Date().toISOString().split('T')[0];
            $('#booking_date').attr('min', today);
            
            // Validate booking form
            $('form').on('submit', function(e) {
                const startTime = $('#start_time').val();
                const endTime = $('#end_time').val();
                
                if (startTime && endTime && startTime >= endTime) {
                    e.preventDefault();
                    alert('End time must be after start time');
                    return false;
                }
            });

            // Handle logout link click
            $('#logoutLink').on('click', function(e) {
                e.preventDefault();
                window.location.href = '../logout.php';
            });
            
            // Today button functionality
            $('#backToTodayBtn').on('click', function(e) {
                e.preventDefault();
                const today = new Date();
                const month = today.getMonth() + 1;
                const year = today.getFullYear();
                
                let searchParams = new URLSearchParams(window.location.search);
                searchParams.set('tab', 'calendar');
                searchParams.set('month', month);
                searchParams.set('year', year);
                
                // Preserve facility filter if set
                const facility = searchParams.get('facility');
                if (facility) {
                    searchParams.set('facility', facility);
                }
                
                let newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + searchParams.toString();
                window.location.href = newurl;
            });
        });
    </script>
</body>
</html>