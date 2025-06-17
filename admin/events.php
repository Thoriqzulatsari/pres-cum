<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = $_GET['delete'];
    
    $sql = "DELETE FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Event deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete event.";
    }
    
    redirect("events.php");
    exit;
}

// Handle adding/editing event
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_event']) || isset($_POST['edit_event']))) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    
    // Format datetime
    $start_datetime = $start_date . ' ' . $start_time . ':00';
    $end_datetime = $end_date . ' ' . $end_time . ':00';
    
    $dormitory_id = isset($_POST['dormitory_id']) && $_POST['dormitory_id'] > 0 ? (int)$_POST['dormitory_id'] : NULL;
    
    // Validate inputs
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Event title is required.";
    }
    
    if (empty($start_date) || empty($start_time)) {
        $errors[] = "Start date and time are required.";
    }
    
    if (empty($location)) {
        $errors[] = "Event location is required.";
    }
    
    // Check if end date/time is after start date/time
    $start = strtotime($start_datetime);
    $end = strtotime($end_datetime);
    if ($end <= $start) {
        $errors[] = "End time must be after start time.";
    }
    
    if (empty($errors)) {
        if (isset($_POST['edit_event']) && isset($_POST['event_id'])) {
            // Update existing event
            $event_id = (int)$_POST['event_id'];
            
            $sql = "UPDATE events SET 
                    title = ?, 
                    description = ?, 
                    start_time = ?, 
                    end_time = ?, 
                    location = ?, 
                    dormitory_id = ? 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $title, $description, $start_datetime, $end_datetime, $location, $dormitory_id, $event_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Event updated successfully!";
                redirect("events.php");
                exit;
            } else {
                $errors[] = "Failed to update event: " . $conn->error;
            }
        } else {
            // Add new event
            $sql = "INSERT INTO events (title, description, start_time, end_time, location, dormitory_id, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $created_by = $_SESSION['user_id'];
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $title, $description, $start_datetime, $end_datetime, $location, $dormitory_id, $created_by);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Event added successfully!";
                redirect("events.php");
                exit;
            } else {
                $errors[] = "Failed to add event: " . $conn->error;
            }
        }
    }
}

// Get dormitories for filter and form
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
$time_filter = isset($_GET['time']) ? sanitize($_GET['time']) : 'upcoming';

// Base query
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.end_time, e.location, 
               e.created_at, e.created_by, d.name as dormitory_name, u.full_name as created_by_name
        FROM events e
        LEFT JOIN dormitories d ON e.dormitory_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE 1=1";

// Add filters
if ($dorm_filter > 0) {
    $sql .= " AND (e.dormitory_id = $dorm_filter OR e.dormitory_id IS NULL)";
}

// Time filter (upcoming or past)
if ($time_filter == 'upcoming') {
    $sql .= " AND e.start_time >= NOW()";
} else if ($time_filter == 'past') {
    $sql .= " AND e.start_time < NOW()";
}

// Add sorting - upcoming events first, then by date
$sql .= " ORDER BY ";
if ($time_filter == 'upcoming') {
    $sql .= "e.start_time ASC";
} else if ($time_filter == 'past') {
    $sql .= "e.start_time DESC";
} else {
    $sql .= "e.start_time ASC";
}

$result = $conn->query($sql);
$events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
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
    } else {
        $_SESSION['error_msg'] = "Event not found.";
        redirect("events.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - PresDorm</title>
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
            transition: transform 0.2s;
            border-left: 4px solid var(--success);
            height: 100%;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .past-event {
            border-left-color: var(--secondary);
            opacity: 0.8;
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
                    <a class="nav-link active" href="events.php">
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
                <h1 class="h3 mb-0 text-gray-800">Manage Events</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#eventModal">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Event
                </button>
            </div>
            
            <!-- Alerts -->
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
            
            <!-- Filter Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Events</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="events.php" class="form-inline">
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
                            <label class="mr-2">Time:</label>
                            <select name="time" class="form-control">
                                <option value="upcoming" <?php echo ($time_filter == 'upcoming') ? 'selected' : ''; ?>>Upcoming Events</option>
                                <option value="past" <?php echo ($time_filter == 'past') ? 'selected' : ''; ?>>Past Events</option>
                                <option value="all" <?php echo ($time_filter == 'all') ? 'selected' : ''; ?>>All Events</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                        <a href="events.php" class="btn btn-secondary mb-2 ml-2">Clear Filters</a>
                    </form>
                </div>
            </div>
            
            <!-- Events Display -->
            <?php if (count($events) > 0): ?>
                <div class="row">
                    <?php foreach ($events as $event): ?>
                        <?php 
                            $is_past = strtotime($event['start_time']) < time();
                            $card_class = $is_past ? 'event-card past-event' : 'event-card';
                            $badge_class = $is_past ? 'badge-secondary' : 'badge-success';
                            $badge_text = $is_past ? 'Past' : 'Upcoming';
                        ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card <?php echo $card_class; ?> h-100">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="mb-1">
                                            <i class="far fa-calendar-alt text-primary mr-1"></i> 
                                            <?php echo date('l, F d, Y', strtotime($event['start_time'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="far fa-clock text-primary mr-1"></i> 
                                            <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-map-marker-alt text-primary mr-1"></i> 
                                            <?php echo htmlspecialchars($event['location']); ?>
                                            <?php if (!empty($event['dormitory_name'])): ?>
                                                (<?php echo htmlspecialchars($event['dormitory_name']); ?>)
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 100))); ?><?php echo strlen($event['description']) > 100 ? '...' : ''; ?></p>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <div class="row">
                                        <div class="col-6">
                                            <a href="?edit=<?php echo $event['id']; ?>" class="btn btn-warning btn-block">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <a href="?delete=<?php echo $event['id']; ?>" class="btn btn-danger btn-block" 
                                               onclick="return confirm('Are you sure you want to delete this event?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-muted text-center">
                                        <small>Posted by: <?php echo htmlspecialchars($event['created_by_name']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times text-gray-300" style="font-size: 3rem;"></i>
                        <p class="text-gray-500 mt-3">No events found matching your filters.</p>
                        <button type="button" class="btn btn-primary mt-2" data-toggle="modal" data-target="#eventModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Event
                        </button>
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
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo isset($edit_event) ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo isset($edit_event) ? date('Y-m-d', strtotime($edit_event['start_time'])) : date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo isset($edit_event) ? date('H:i', strtotime($edit_event['start_time'])) : '08:00'; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo isset($edit_event) ? date('Y-m-d', strtotime($edit_event['end_time'])) : date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_time">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo isset($edit_event) ? date('H:i', strtotime($edit_event['end_time'])) : '10:00'; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo isset($edit_event) ? htmlspecialchars($edit_event['location']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dormitory_id">Specific to Dormitory (Optional)</label>
                            <select class="form-control" id="dormitory_id" name="dormitory_id">
                                <option value="0">All Dormitories</option>
                                <?php foreach ($dormitories as $dorm): ?>
                                    <option value="<?php echo $dorm['id']; ?>" 
                                            <?php echo (isset($edit_event) && $edit_event['dormitory_id'] == $dorm['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dorm['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">If selected, only residents of this dormitory will see this event.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <?php if(isset($edit_event)): ?>
                            <button type="submit" name="edit_event" class="btn btn-warning">
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
            // Show modal on page load if editing an event
            <?php if(isset($edit_event)): ?>
            $('#eventModal').modal('show');
            <?php endif; ?>
            
            // Enable dropdown functionality
            $('.dropdown-toggle').dropdown();
            
            // Handle logout link click
            $('#logoutLink').on('click', function(e) {
                e.preventDefault();
                window.location.href = '../logout.php';
            });
        });
        
        // Auto-fill end date with start date when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            if (document.getElementById('end_date').value <= this.value) {
                document.getElementById('end_date').value = this.value;
            }
        });
        
        // Validate form before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value + 'T' + document.getElementById('start_time').value);
            const endDate = new Date(document.getElementById('end_date').value + 'T' + document.getElementById('end_time').value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date and time must be after start date and time');
            }
        });
    </script>
</body>
</html>