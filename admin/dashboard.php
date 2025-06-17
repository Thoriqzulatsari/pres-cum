<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Get statistics
$stats = [];

// Total residents
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'resident'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['total_residents'] = $row['total'];
}

// Total rooms
$sql = "SELECT COUNT(*) as total FROM rooms";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['total_rooms'] = $row['total'];
}

// Occupied rooms
$sql = "SELECT COUNT(*) as total FROM rooms WHERE is_occupied = TRUE";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['occupied_rooms'] = $row['total'];
}

// Pending issues
$sql = "SELECT COUNT(*) as total FROM technical_issues WHERE status = 'pending'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['pending_issues'] = $row['total'];
}

// Upcoming events
$sql = "SELECT COUNT(*) as total FROM events WHERE start_time > NOW()";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['upcoming_events'] = $row['total'];
}

// Get latest issues
$sql = "SELECT ti.issue_id AS id, ti.title, ti.status, ti.reported_at, u.full_name, r.room_number, d.name as dormitory_name
        FROM technical_issues ti
        JOIN users u ON ti.user_id = u.id
        JOIN rooms r ON ti.room_id = r.id
        JOIN dormitories d ON r.dormitory_id = d.id
        ORDER BY ti.reported_at DESC LIMIT 5";
$latest_issues = [];
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $latest_issues[] = $row;
    }
}

// Get upcoming events
$sql = "SELECT e.id, e.title, e.start_time, e.location, d.name as dormitory_name
        FROM events e
        LEFT JOIN dormitories d ON e.dormitory_id = d.id
        WHERE e.start_time > NOW()
        ORDER BY e.start_time ASC LIMIT 5";
$upcoming_events = [];
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PresDorm</title>
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
                <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="stats-icon text-primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['total_residents'] ?? 0; ?></div>
                            <div class="stats-label">Total Residents</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="stats-icon text-success">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['occupied_rooms'] ?? 0; ?> / <?php echo $stats['total_rooms'] ?? 0; ?></div>
                            <div class="stats-label">Occupied Rooms</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="stats-icon text-danger">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['pending_issues'] ?? 0; ?></div>
                            <div class="stats-label">Pending Issues</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tools mr-2"></i>Latest Technical Issues</h6>
                            <a href="issues.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($latest_issues) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Issue</th>
                                                <th>Resident</th>
                                                <th>Status</th>
                                                <th>Reported</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latest_issues as $issue): ?>
                                                <tr>
                                                    <td><?php echo $issue['title']; ?></td>
                                                    <td><?php echo $issue['full_name']; ?> (<?php echo $issue['dormitory_name']; ?> <?php echo $issue['room_number']; ?>)</td>
                                                    <td>
                                                        <?php if ($issue['status'] == 'pending'): ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php elseif ($issue['status'] == 'in_progress'): ?>
                                                            <span class="badge badge-primary">In Progress</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">Resolved</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, H:i', strtotime($issue['reported_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-tools text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No technical issues reported yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-calendar-alt mr-2"></i>Upcoming Events</h6>
                            <a href="events.php" class="btn btn-sm btn-success">
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_events) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Location</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_events as $event): ?>
                                                <tr>
                                                    <td><?php echo $event['title']; ?></td>
                                                    <td>
                                                        <?php echo $event['location']; ?>
                                                        <?php if ($event['dormitory_name']): ?>
                                                            (<?php echo $event['dormitory_name']; ?>)
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, H:i', strtotime($event['start_time'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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