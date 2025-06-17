<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Handle room reassignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reassign_room'])) {
    $resident_id = (int)$_POST['resident_id'];
    $room_id = (int)$_POST['room_id']; 
    $old_room_id = (int)$_POST['old_room_id'];
    $dormitory_id = (int)$_POST['dormitory_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Free up the old room if exists
        if ($old_room_id > 0) {
            $sql1 = "UPDATE rooms SET is_occupied = FALSE WHERE id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("i", $old_room_id);
            $stmt1->execute();
        }
        
        // Assign the new room
        $sql2 = "UPDATE rooms SET is_occupied = TRUE WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $room_id);
        $stmt2->execute();
        
        // Update resident profile
        $sql3 = "UPDATE resident_profiles SET room_id = ?, dormitory_id = ? WHERE user_id = ?";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("iii", $room_id, $dormitory_id, $resident_id);
        $stmt3->execute();
        
        // Commit transaction
        $conn->commit();
        $_SESSION['success_msg'] = "Room reassigned successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_msg'] = "Failed to reassign room: " . $e->getMessage();
    }
    
    redirect("residents.php");
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
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Base query
$sql = "SELECT u.id, u.username, u.full_name, u.email, u.user_type, 
               rp.phone, rp.student_id, rp.room_id, rp.dormitory_id, rp.move_in_date,
               r.room_number, d.name as dormitory_name
        FROM users u
        JOIN resident_profiles rp ON u.id = rp.user_id
        LEFT JOIN rooms r ON rp.room_id = r.id
        LEFT JOIN dormitories d ON rp.dormitory_id = d.id
        WHERE u.user_type = 'resident'";

// Add filters
if ($dorm_filter > 0) {
    $sql .= " AND rp.dormitory_id = " . $dorm_filter;
}
if (!empty($search)) {
    $sql .= " AND (u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

// Add sorting
$sql .= " ORDER BY u.full_name ASC";

$result = $conn->query($sql);
$residents = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
}

// Get available rooms for each dormitory
$available_rooms = [];
foreach ($dormitories as $dorm) {
    $dorm_id = $dorm['id'];
    $sql = "SELECT id, room_number FROM rooms WHERE dormitory_id = $dorm_id AND is_occupied = FALSE ORDER BY room_number";
    $result = $conn->query($sql);
    
    $rooms = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }
    $available_rooms[$dorm_id] = $rooms;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - PresDorm</title>
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="residents.php">
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
                <h1 class="h3 mb-0 text-gray-800">Manage Residents</h1>
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
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter mr-2"></i>Filter Residents
                    </h6>
                </div>
                <div class="card-body">
                    <form method="get" action="residents.php" class="form-inline">
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
                            <label class="mr-2">Search:</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, username, email..." value="<?php echo $search; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                        <a href="residents.php" class="btn btn-secondary mb-2 ml-2">Clear Filters</a>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users mr-2"></i>Residents (<?php echo count($residents); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (count($residents) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Student ID</th>
                                        <th>Email</th>
                                        <th>Dormitory</th>
                                        <th>Room</th>
                                        <th>Move-in Date</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($residents as $resident): ?>
                                        <tr>
                                            <td><?php echo $resident['full_name']; ?></td>
                                            <td><?php echo $resident['username']; ?></td>
                                            <td><?php echo $resident['student_id'] ?: 'Tidak ada'; ?></td>:
                                            <td><?php echo $resident['email']; ?></td>
                                            <td><?php echo $resident['dormitory_name'] ?? 'Not assigned'; ?></td>
                                            <td><?php echo $resident['room_number'] ?? 'Not assigned'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($resident['move_in_date'])); ?></td>
                                            <td><?php echo $resident['phone'] ?: 'Not provided'; ?></td>
                                            <td>
                                                <span class="badge badge-success">Active</span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-toggle="modal" 
                                                            data-target="#viewResidentModal" 
                                                            data-resident='<?php echo json_encode($resident); ?>'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-toggle="modal" 
                                                            data-target="#reassignRoomModal" 
                                                            data-resident='<?php echo json_encode($resident); ?>'>
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users text-gray-300" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No residents found matching your filters.</p>
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

    <!-- View Resident Modal -->
    <div class="modal fade" id="viewResidentModal" tabindex="-1" role="dialog" aria-labelledby="viewResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewResidentModalLabel">Resident Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-5x text-muted"></i>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong> <span id="resident-name"></span></p>
                            <p><strong>Username:</strong> <span id="resident-username"></span></p>
                            <p><strong>Email:</strong> <span id="resident-email"></span></p>
                            <p><strong>Phone:</strong> <span id="resident-phone"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Dormitory:</strong> <span id="resident-dormitory"></span></p>
                            <p><strong>Room Number:</strong> <span id="resident-room"></span></p>
                            <p><strong>Move-in Date:</strong> <span id="resident-move-in"></span></p>
                            <p><strong>Status:</strong> <span id="resident-status"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reassign Room Modal -->
    <div class="modal fade" id="reassignRoomModal" tabindex="-1" role="dialog" aria-labelledby="reassignRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="reassignRoomModalLabel">Reassign Room</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="resident_id" id="reassign-resident-id">
                        <input type="hidden" name="old_room_id" id="reassign-old-room-id">
                        
                        <p>Reassigning room for: <strong><span id="reassign-resident-name"></span></strong></p>
                        <p>Current assignment: <strong><span id="reassign-current-assignment"></span></strong></p>
                        
                        <div class="form-group">
                            <label for="dormitory_id">Select Dormitory:</label>
                            <select class="form-control" id="dormitory_id" name="dormitory_id" required>
                                <?php foreach ($dormitories as $dorm): ?>
                                    <option value="<?php echo $dorm['id']; ?>" data-dorm-id="<?php echo $dorm['id']; ?>">
                                        <?php echo $dorm['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php foreach ($dormitories as $dorm): ?>
                            <div class="form-group room-options" id="rooms-dorm-<?php echo $dorm['id']; ?>" style="display: none;">
                                <label for="room_id_<?php echo $dorm['id']; ?>">Select Room:</label>
                                <select class="form-control room-select" id="room_id_<?php echo $dorm['id']; ?>" name="room_id" data-dorm-id="<?php echo $dorm['id']; ?>" required>
                                    <option value="">-- Select Room --</option>
                                    <?php if (isset($available_rooms[$dorm['id']])): ?>
                                        <?php foreach ($available_rooms[$dorm['id']] as $room): ?>
                                            <option value="<?php echo $room['id']; ?>">
                                                <?php echo $room['room_number']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if (empty($available_rooms[$dorm['id']])): ?>
                                    <div class="text-danger mt-2">No available rooms in this dormitory</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="reassign_room" class="btn btn-warning">Reassign Room</button>
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
        
        // View resident details
        $('#viewResidentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var resident = button.data('resident');
            
            $('#resident-name').text(resident.full_name);
            $('#resident-username').text(resident.username);
            $('#resident-email').text(resident.email);
            $('#resident-phone').text(resident.phone || 'Not provided');
            $('#resident-dormitory').text(resident.dormitory_name || 'Not assigned');
            $('#resident-room').text(resident.room_number || 'Not assigned');
            $('#resident-move-in').text(new Date(resident.move_in_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
            
            // Display only active status since we don't have status column
            $('#resident-status').html('<span class="badge badge-success">Active</span>');
        });
        
        // Reassign room
        $('#reassignRoomModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var resident = button.data('resident');
            
            $('#reassign-resident-id').val(resident.id);
            $('#reassign-old-room-id').val(resident.room_id);
            $('#reassign-resident-name').text(resident.full_name);
            
            var currentAssignment = '';
            if (resident.dormitory_name && resident.room_number) {
                currentAssignment = resident.dormitory_name + ', Room ' + resident.room_number;
            } else {
                currentAssignment = 'No room assigned';
            }
            $('#reassign-current-assignment').text(currentAssignment);
            
            // Set dormitory dropdown to current dormitory
            if (resident.dormitory_id) {
                $('#dormitory_id').val(resident.dormitory_id).trigger('change');
            }
        });
        
        // Show room options based on dormitory selection
        $('#dormitory_id').change(function() {
            var dormId = $(this).val();
            $('.room-options').hide();
            $('#rooms-dorm-' + dormId).show();
            
            // Reset all room selections and only enable the current one
            $('.room-select').prop('disabled', true).prop('required', false);
            $('#room_id_' + dormId).prop('disabled', false).prop('required', true);
        });
        
        // Trigger change on page load to show the first dormitory's rooms
        $(document).ready(function() {
            $('#dormitory_id').trigger('change');
        });
    </script>
</body>
</html>