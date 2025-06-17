<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's dormitory
$sql = "SELECT dormitory_id FROM resident_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $dormitory_id = $row['dormitory_id'];
} else {
    $dormitory_id = 0;
}

// Get dormitory name
$dormitory_name = '';
if ($dormitory_id > 0) {
    $sql = "SELECT name FROM dormitories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dormitory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dormitory_name = $row['name'];
    }
}

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = $_GET['delete'];
    
    // First check if user has permission to delete this event
    $sql = "SELECT created_by FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Only allow the creator of the event or an admin to delete it
        if ($row['created_by'] == $user_id) {
            $sql = "DELETE FROM events WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $event_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Event deleted successfully!";
                header("Location: events.php");
                exit;
            } else {
                $_SESSION['error_msg'] = "Failed to delete event: " . $conn->error;
                header("Location: events.php");
                exit;
            }
        } else {
            $_SESSION['error_msg'] = "You don't have permission to delete this event.";
            header("Location: events.php");
            exit;
        }
    } else {
        $_SESSION['error_msg'] = "Event not found.";
        header("Location: events.php");
        exit;
    }
}

// Get event details for editing
$edit_event = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $event_id = $_GET['edit'];
    
    $sql = "SELECT * FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_event = $result->fetch_assoc();
        // Check if user has permission to edit
        if ($edit_event['created_by'] != $user_id) {
            $_SESSION['error_msg'] = "You don't have permission to edit this event.";
            header("Location: events.php");
            exit;
        }
    } else {
        $_SESSION['error_msg'] = "Event not found.";
        header("Location: events.php");
        exit;
    }
}

// Helper function to format description with links
function formatDescription($description) {
    // Replace the link format [LINK:url] with proper HTML link
    $formatted = preg_replace('/\[LINK:(.*?)\]/', '<a href="$1" target="_blank">$1</a>', $description);
    return nl2br($formatted);
}

// Handle form submission for new or updated event
if (isset($_POST['add_event']) || isset($_POST['update_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    $is_dorm_specific = isset($_POST['is_dorm_specific']) ? 1 : 0;
    
    // Process link input if provided
    $link_url = isset($_POST['link_url']) ? trim($_POST['link_url']) : '';

    // If link URL is provided, append to description
    if (!empty($link_url)) {
        // Validate URL
        if (filter_var($link_url, FILTER_VALIDATE_URL)) {
            $formatted_link = "[LINK:" . $link_url . "]";
            $description .= "\n\n" . $formatted_link;
        } else {
            $errors[] = "Invalid URL format";
        }
    }
    
    $start_datetime = $start_date . ' ' . $start_time . ':00';
    $end_datetime = $end_date . ' ' . $end_time . ':00';
    
    $event_dormitory_id = $is_dorm_specific ? $dormitory_id : NULL;
    
    // Validate inputs for event form
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Event title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Event description is required";
    }
    
    if (empty($start_date) || empty($start_time)) {
        $errors[] = "Start date and time are required";
    }
    
    if (empty($end_date) || empty($end_time)) {
        $errors[] = "End date and time are required";
    }
    
    if (empty($location)) {
        $errors[] = "Event location is required";
    }
    
    // Completely sanitize description - remove all HTML tags
    $description = strip_tags($description);
    
    if (empty($errors)) {
        if (isset($_POST['update_event']) && is_numeric($_POST['event_id'])) {
            // Update existing event
            $event_id = $_POST['event_id'];
            
            // Verify ownership again
            $sql = "SELECT created_by FROM events WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['created_by'] == $user_id) {
                    // Update the event
                    $sql = "UPDATE events SET 
                            title = ?, 
                            description = ?, 
                            start_time = ?, 
                            end_time = ?, 
                            location = ?, 
                            dormitory_id = ?
                            WHERE id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssii", $title, $description, $start_datetime, $end_datetime, $location, $event_dormitory_id, $event_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_msg'] = "Event updated successfully!";
                        header("Location: events.php");
                        exit;
                    } else {
                        $errors[] = "Failed to update event: " . $conn->error;
                    }
                } else {
                    $errors[] = "You don't have permission to update this event.";
                }
            } else {
                $errors[] = "Event not found.";
            }
        } else {
            // Insert new event
            $sql = "INSERT INTO events (title, description, start_time, end_time, location, dormitory_id, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $title, $description, $start_datetime, $end_datetime, $location, $event_dormitory_id, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Event added successfully!";
                header("Location: events.php");
                exit;
            } else {
                $errors[] = "Failed to add event: " . $conn->error;
            }
        }
    }
}

// Get events (all dormitory events + specific dormitory events)
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.end_time, e.location,
               d.name as dormitory_name, u.full_name as created_by
        FROM events e
        LEFT JOIN dormitories d ON e.dormitory_id = d.id
        JOIN users u ON e.created_by = u.id
        WHERE (e.dormitory_id IS NULL OR e.dormitory_id = ?)
        AND e.start_time >= CURDATE()
        ORDER BY e.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dormitory_id);
$stmt->execute();
$result = $stmt->get_result();

$upcoming_events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}

// Get past events
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.end_time, e.location,
               d.name as dormitory_name, u.full_name as created_by
        FROM events e
        LEFT JOIN dormitories d ON e.dormitory_id = d.id
        JOIN users u ON e.created_by = u.id
        WHERE (e.dormitory_id IS NULL OR e.dormitory_id = ?)
        AND e.start_time < CURDATE()
        ORDER BY e.start_time DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dormitory_id);
$stmt->execute();
$result = $stmt->get_result();

$past_events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $past_events[] = $row;
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
    <title>Dormitory Events - PresDorm</title>
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
        
        .event-card {
            margin-bottom: 15px;
            transition: transform 0.2s;
            border-left: 4px solid var(--success);
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .event-date {
            font-size: 0.9rem;
            color: #666;
        }
        
        .event-location {
            font-size: 0.9rem;
            color: #666;
        }
        
        .event-description {
            /* For event descriptions with links */
            word-break: break-word;
        }
        
        .event-description a {
            color: #007bff;
            text-decoration: underline;
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
                    <a class="nav-link active" href="events.php">
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
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Dormitory Events</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#eventModal">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Event
                </button>
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
            
            <?php if (!empty($dormitory_name)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> Showing events for all dormitories and <?php echo $dormitory_name; ?>.
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-alt mr-2"></i>Upcoming Events</h6>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_events) > 0): ?>
                        <div class="row">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="card event-card h-100">
                                        <div class="card-header bg-light">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="event-date">
                                                <i class="far fa-calendar-alt mr-1"></i> 
                                                <?php echo date('l, F d, Y', strtotime($event['start_time'])); ?><br>
                                                <i class="far fa-clock mr-1"></i> 
                                                <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                            </p>
                                            <p class="event-location">
                                                <i class="fas fa-map-marker-alt mr-1"></i> 
                                                <?php echo htmlspecialchars($event['location']); ?>
                                                <?php if (!empty($event['dormitory_name'])): ?>
                                                    (<?php echo htmlspecialchars($event['dormitory_name']); ?>)
                                                <?php endif; ?>
                                            </p>
                                            <p class="event-description"><?php echo formatDescription($event['description']); ?></p>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">Posted by: <?php echo htmlspecialchars($event['created_by']); ?></small>
                                                <div>
                                                    <?php if ($event['created_by'] == $_SESSION['full_name']): ?>
                                                        <a href="?edit=<?php echo $event['id']; ?>" class="btn btn-sm btn-warning mr-1">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="?delete=<?php echo $event['id']; ?>" class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this event?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
            
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-history mr-2"></i>Past Events</h6>
                </div>
                <div class="card-body">
                    <?php if (count($past_events) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($past_events as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['start_time'])); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($event['location']); ?>
                                                <?php if (!empty($event['dormitory_name'])): ?>
                                                    (<?php echo htmlspecialchars($event['dormitory_name']); ?>)
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history text-gray-300" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No past events found.</p>
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
    
    <!-- Event Modal (Add/Edit) -->
    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header <?php echo isset($edit_event) ? 'bg-warning' : 'bg-primary'; ?> text-white">
                    <h5 class="modal-title" id="eventModalLabel">
                        <?php echo isset($edit_event) ? 'Edit Event' : 'Add New Event'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <?php if(isset($edit_event)): ?>
                        <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="title">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                value="<?php echo isset($edit_event) ? htmlspecialchars($edit_event['title']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo isset($edit_event) ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
                            <small class="form-text text-muted">
                                Enter your event details. HTML is not allowed.
                            </small>
                        </div>
                        
                        <!-- New Link Input Field (URL only) -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Add a Link (Optional)</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="link_url">Link URL</label>
                                    <input type="url" class="form-control" id="link_url" name="link_url" 
                                        placeholder="https://example.com">
                                </div>
                                <small class="form-text text-muted">
                                    The link will be added to the end of your description.
                                </small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                        value="<?php echo isset($edit_event) ? date('Y-m-d', strtotime($edit_event['start_time'])) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                        value="<?php echo isset($edit_event) ? date('H:i', strtotime($edit_event['start_time'])) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                        value="<?php echo isset($edit_event) ? date('Y-m-d', strtotime($edit_event['end_time'])) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_time">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                        value="<?php echo isset($edit_event) ? date('H:i', strtotime($edit_event['end_time'])) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="location" name="location" 
                                value="<?php echo isset($edit_event) ? htmlspecialchars($edit_event['location']) : ''; ?>" required>
                        </div>
                        
                        <?php if (!empty($dormitory_name)): ?>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="is_dorm_specific" name="is_dorm_specific"
                                <?php echo (isset($edit_event) && $edit_event['dormitory_id'] == $dormitory_id) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_dorm_specific">
                                This event is only for <?php echo htmlspecialchars($dormitory_name); ?> residents
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <?php if(isset($edit_event)): ?>
                            <button type="submit" name="update_event" class="btn btn-warning">
                                <i class="fas fa-save mr-1"></i> Update Event
                            </button>
                        <?php else: ?>
                            <button type="submit" name="add_event" class="btn btn-primary">
                                <i class="fas fa-plus-circle mr-1"></i> Add Event
                            </button>
                        <?php endif; ?>
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
            
            // Show modal on page load if editing an event
            <?php if(isset($edit_event)): ?>
            $('#eventModal').modal('show');
            <?php endif; ?>
            
            // Auto-fill end date with start date when start date changes
            document.getElementById('start_date').addEventListener('change', function() {
                if (!document.getElementById('end_date').value) {
                    document.getElementById('end_date').value = this.value;
                }
            });
            
            // Set min date for date inputs to today (only for new events)
            <?php if(!isset($edit_event)): ?>
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').setAttribute('min', today);
            document.getElementById('end_date').setAttribute('min', today);
            <?php endif; ?>
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const startDate = new Date(document.getElementById('start_date').value + 'T' + document.getElementById('start_time').value);
                const endDate = new Date(document.getElementById('end_date').value + 'T' + document.getElementById('end_time').value);
                
                if (endDate < startDate) {
                    e.preventDefault();
                    alert('End date and time must be after start date and time');
                }
                
                // Validate URL format if provided
                const linkUrl = document.getElementById('link_url').value;
                if (linkUrl && !linkUrl.match(/^(http|https):\/\/[^ "]+$/)) {
                    e.preventDefault();
                    alert('Please enter a valid URL starting with http:// or https://');
                }
            });
        });
    </script>
</body>
</html>