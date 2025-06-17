<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Handle room status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $room_id = (int)$_POST['room_id'];
    $is_occupied = $_POST['is_occupied'] === "1" ? 0 : 1; // Toggle the status
    
    $sql = "UPDATE rooms SET is_occupied = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $is_occupied, $room_id);
    
    if ($stmt->execute()) {
        if ($is_occupied == 0) {
            // If room is now unoccupied, also update any resident assigned to this room
            $sql = "UPDATE resident_profiles SET room_id = NULL WHERE room_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
        }
        
        $_SESSION['success_msg'] = "Room status updated successfully.";
    } else {
        $_SESSION['error_msg'] = "Failed to update room status.";
    }
    
    redirect("rooms.php");
    exit;
}

// Handle adding new room
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_room'])) {
    $dormitory_id = (int)$_POST['dormitory_id'];
    $room_number = sanitize($_POST['room_number']);
    
    // Validate inputs
    $errors = [];
    
    if ($dormitory_id <= 0) {
        $errors[] = "Please select a valid dormitory.";
    }
    
    if (empty($room_number)) {
        $errors[] = "Room number is required.";
    } else {
        // Check if room number already exists in this dormitory
        $sql = "SELECT id FROM rooms WHERE room_number = ? AND dormitory_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $room_number, $dormitory_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Room number already exists in this dormitory.";
        }
    }
    
    if (empty($errors)) {
        $sql = "INSERT INTO rooms (dormitory_id, room_number, is_occupied) 
                VALUES (?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $dormitory_id, $room_number);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Room added successfully.";
            redirect("rooms.php");
            exit;
        } else {
            $errors[] = "Failed to add room: " . $conn->error;
        }
    }
}

// Handle editing a room
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_room'])) {
    $room_id = (int)$_POST['room_id'];
    $room_number = sanitize($_POST['room_number']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($room_number)) {
        $errors[] = "Room number is required.";
    } else {
        // Check if room number already exists in this dormitory (excluding the current room)
        $sql = "SELECT r1.id FROM rooms r1 
                JOIN rooms r2 ON r1.dormitory_id = r2.dormitory_id 
                WHERE r1.room_number = ? AND r2.id = ? AND r1.id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $room_number, $room_id, $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Room number already exists in this dormitory.";
        }
    }
    
    if (empty($errors)) {
        $sql = "UPDATE rooms SET room_number = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $room_number, $room_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Room updated successfully.";
            redirect("rooms.php");
            exit;
        } else {
            $errors[] = "Failed to update room: " . $conn->error;
        }
    }
}

// Handle deleting a room
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $room_id = (int)$_GET['delete'];
    
    // Check if room is occupied
    $sql = "SELECT is_occupied FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_occupied']) {
            $_SESSION['error_msg'] = "Cannot delete an occupied room. Please reassign the resident first.";
            redirect("rooms.php");
            exit;
        }
        
        // Delete the room
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Room deleted successfully.";
        } else {
            $_SESSION['error_msg'] = "Failed to delete room.";
        }
    } else {
        $_SESSION['error_msg'] = "Room not found.";
    }
    
    redirect("rooms.php");
    exit;
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
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Initialize rooms array
$rooms = [];

// Only fetch rooms if search is provided
if (!empty($search_query)) {
    // Base query
    $sql = "SELECT r.id, r.room_number, r.is_occupied, 
                   d.name as dormitory_name, d.id as dormitory_id,
                   u.full_name as resident_name, u.id as resident_id
            FROM rooms r
            JOIN dormitories d ON r.dormitory_id = d.id
            LEFT JOIN resident_profiles rp ON r.id = rp.room_id
            LEFT JOIN users u ON rp.user_id = u.id
            WHERE r.room_number LIKE ?";
    
    // Add filters
    $params = ["%$search_query%"];
    $types = "s";
    
    if ($dorm_filter > 0) {
        $sql .= " AND d.id = ?";
        $params[] = $dorm_filter;
        $types .= "i";
    }
    
    if ($status_filter === 'occupied') {
        $sql .= " AND r.is_occupied = 1";
    } elseif ($status_filter === 'available') {
        $sql .= " AND r.is_occupied = 0";
    }
    
    // Add sorting
    $sql .= " ORDER BY d.name, r.room_number";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - PresDorm</title>
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
        
        .room-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary);
            height: 100%;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
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
        
        /* Empty state styles */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            margin-top: 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: var(--secondary);
            max-width: 500px;
            margin: 0 auto 1.5rem;
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
                    <a class="nav-link active" href="rooms.php">
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
                <h1 class="h3 mb-0 text-gray-800">Manage Rooms</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRoomModal">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Room
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
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Rooms</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="rooms.php" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label class="mr-2">Room Number:</label>
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search room number" required>
                        </div>
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
                                <option value="occupied" <?php echo ($status_filter == 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                                <option value="available" <?php echo ($status_filter == 'available') ? 'selected' : ''; ?>>Available</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Search Rooms</button>
                        <a href="rooms.php" class="btn btn-secondary mb-2 ml-2">Clear Filters</a>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($search_query)): ?>
                <!-- Display search results -->
                <div class="row">
                    <?php if (!empty($rooms)): ?>
                        <?php foreach ($rooms as $room): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card room-card h-100">
                                    <?php if ($room['is_occupied']): ?>
                                        <span class="badge badge-danger status-badge">Occupied</span>
                                    <?php else: ?>
                                        <span class="badge badge-success status-badge">Available</span>
                                    <?php endif; ?>
                                    
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            Room <?php echo $room['room_number']; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Dormitory:</strong> <?php echo $room['dormitory_name']; ?></p>
                                        
                                        <?php if ($room['is_occupied'] && !empty($room['resident_name'])): ?>
                                            <p><strong>Resident:</strong> <?php echo $room['resident_name']; ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <div class="btn-group btn-block">
                                                <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#editRoomModal" 
                                                        data-room='<?php echo json_encode($room); ?>'>
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                    <input type="hidden" name="is_occupied" value="<?php echo $room['is_occupied']; ?>">
                                                    <button type="submit" name="toggle_status" class="btn <?php echo $room['is_occupied'] ? 'btn-success' : 'btn-danger'; ?>">
                                                        <i class="fas <?php echo $room['is_occupied'] ? 'fa-door-open' : 'fa-door-closed'; ?>"></i>
                                                        <?php echo $room['is_occupied'] ? 'Mark Available' : 'Mark Occupied'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                            
                                            <?php if (!$room['is_occupied']): ?>
                                                <a href="?delete=<?php echo $room['id']; ?>" class="btn btn-sm btn-danger btn-block mt-2" 
                                                   onclick="return confirm('Are you sure you want to delete this room?')">
                                                    <i class="fas fa-trash"></i> Delete Room
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No rooms found matching your search criteria.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Empty state - shown when no search has been performed -->
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h4>Search for Rooms</h4>
                    <p>Enter a room number in the search field above to find and manage specific rooms.</p>
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

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1" role="dialog" aria-labelledby="addRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addRoomModalLabel">Add New Room</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="add_dormitory_id">Dormitory</label>
                            <select class="form-control" id="add_dormitory_id" name="dormitory_id" required>
                                <option value="">-- Select Dormitory --</option>
                                <?php foreach ($dormitories as $dorm): ?>
                                    <option value="<?php echo $dorm['id']; ?>">
                                        <?php echo $dorm['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_room_number">Room Number</label>
                            <input type="text" class="form-control" id="add_room_number" name="room_number" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1" role="dialog" aria-labelledby="editRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editRoomModalLabel">Edit Room</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" id="edit_room_id" name="room_id">
                        
                        <div class="form-group">
                            <label for="edit_dormitory">Dormitory</label>
                            <input type="text" class="form-control" id="edit_dormitory" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_room_number">Room Number</label>
                            <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <input type="text" class="form-control" id="edit_status" readonly>
                        </div>
                        
                        <div class="form-group" id="edit_resident_group">
                            <label for="edit_resident">Current Resident</label>
                            <input type="text" class="form-control" id="edit_resident" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_room" class="btn btn-warning">Update Room</button>
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
            
            // Initialize edit room modal with room data
            $('#editRoomModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var room = button.data('room');
                
                $('#edit_room_id').val(room.id);
                $('#edit_dormitory').val(room.dormitory_name);
                $('#edit_room_number').val(room.room_number);
                
                var status = room.is_occupied == 1 ? 'Occupied' : 'Available';
                $('#edit_status').val(status);
                
                if (room.is_occupied == 1 && room.resident_name) {
                    $('#edit_resident').val(room.resident_name);
                    $('#edit_resident_group').show();
                } else {
                    $('#edit_resident_group').hide();
                }
            });
        });
    </script>
</body>
</html>