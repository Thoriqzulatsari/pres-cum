<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $issue_id = (int)$_POST['issue_id']; // This should correspond to issue_id from the form
    $status = sanitize($_POST['status']);

    // Construct SQL query properly
    if ($status == 'resolved') {
        $sql = "UPDATE technical_issues SET status = ?, resolved_at = NOW() WHERE issue_id = ?"; // Fixed: id to issue_id
    } else {
        $sql = "UPDATE technical_issues SET status = ? WHERE issue_id = ?"; // Fixed: id to issue_id
    }

    $stmt = $conn->prepare($sql);
    // The $issue_id variable here should hold the correct issue_id value from the form
    $stmt->bind_param("si", $status, $issue_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Issue status updated successfully.";
    } else {
        $_SESSION['error_msg'] = "Failed to update issue status.";
    }

    redirect("issues.php");
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

// Base query
// Fixed: ti.id to ti.issue_id and aliased as id for compatibility if other parts of the code expect 'id'
$sql = "SELECT ti.issue_id AS id, ti.title, ti.description, ti.status, ti.reported_at, ti.resolved_at,
               u.full_name, r.room_number, d.name as dormitory_name, d.id as dormitory_id
        FROM technical_issues ti
        JOIN users u ON ti.user_id = u.id
        JOIN rooms r ON ti.room_id = r.id
        JOIN dormitories d ON r.dormitory_id = d.id
        WHERE 1=1";

// Add filters
if ($dorm_filter > 0) {
    $sql .= " AND d.id = " . $dorm_filter;
}
if (!empty($status_filter)) {
    $sql .= " AND ti.status = '" . $status_filter . "'";
}

// Add sorting
$sql .= " ORDER BY ti.reported_at DESC";

$result = $conn->query($sql);
$issues = [];
if ($result && $result->num_rows > 0) { // Added check for $result
    while($row = $result->fetch_assoc()) {
        $issues[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Issues - PresDorm</title>
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

        .issue-card {
            margin-bottom: 15px;
            border-left: 5px solid;
            transition: transform 0.2s;
        }

        .issue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .pending {
            border-left-color: var(--warning);
        }

        .in_progress {
            border-left-color: var(--primary);
        }

        .resolved {
            border-left-color: var(--success);
        }

        .sticky-footer {
            margin-top: auto;
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
                    <a class="nav-link active" href="issues.php">
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
                <h1 class="h3 mb-0 text-gray-800">Technical Issues</h1>
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

            <!-- Filter Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Issues</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="issues.php" class="form-inline">
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
                                <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Apply Filters</button>
                        <a href="issues.php" class="btn btn-secondary mb-2 ml-2">Clear Filters</a>
                    </form>
                </div>
            </div>

            <!-- Issues Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tools mr-2"></i>Technical Issues (<?php echo count($issues); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (count($issues) > 0): ?>
                        <?php foreach ($issues as $issue): ?>
                            <div class="card issue-card <?php echo $issue['status']; ?> mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title"><?php echo htmlspecialchars($issue['title']); ?></h5>
                                        <?php if ($issue['status'] == 'pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php elseif ($issue['status'] == 'in_progress'): ?>
                                            <span class="badge badge-primary">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Resolved</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="card-text"><?php echo htmlspecialchars($issue['description']); ?></p>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>Resident:</strong> <?php echo htmlspecialchars($issue['full_name']); ?></p>
                                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($issue['dormitory_name']); ?>, Room <?php echo htmlspecialchars($issue['room_number']); ?></p>
                                            <p class="mb-1"><strong>Reported:</strong> <?php echo date('F d, Y H:i', strtotime($issue['reported_at'])); ?></p>
                                            <?php if ($issue['status'] == 'resolved' && $issue['resolved_at']): ?>
                                                <p class="mb-1"><strong>Resolved:</strong> <?php echo date('F d, Y H:i', strtotime($issue['resolved_at'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                                <div class="input-group">
                                                    <select name="status" class="form-control">
                                                        <option value="pending" <?php echo ($issue['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="in_progress" <?php echo ($issue['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="resolved" <?php echo ($issue['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools text-gray-300" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No technical issues found matching your filters.</p>
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
