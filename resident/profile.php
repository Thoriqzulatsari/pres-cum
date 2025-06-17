<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user detailed information including room, dormitory, etc.
$sql = "SELECT u.*, rp.phone, rp.student_id, rp.move_in_date, r.room_number, d.name as dormitory_name
        FROM users u
        LEFT JOIN resident_profiles rp ON u.id = rp.user_id
        LEFT JOIN rooms r ON rp.room_id = r.id
        LEFT JOIN dormitories d ON rp.dormitory_id = d.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $_SESSION['error_msg'] = "User information not found.";
    redirect('dashboard.php');
    exit;
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $error = false;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = "Please enter a valid email address.";
        $error = true;
    }
    
    // Check if email is already in use
    if ($email != $user['email']) {
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error_msg'] = "This email is already in use by another account.";
            $error = true;
        }
    }
    
    // Handle password change if specified
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $_SESSION['error_msg'] = "Password must be at least 6 characters long.";
            $error = true;
        } elseif ($password != $confirm_password) {
            $_SESSION['error_msg'] = "Password confirmation does not match.";
            $error = true;
        }
    }
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture']; // Keep existing by default
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file = $_FILES['profile_picture'];
        
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error_msg'] = "Only JPG, JPEG, PNG and GIF images are allowed.";
            $error = true;
        } 
        // Validate file size
        else if ($file['size'] > $max_size) {
            $_SESSION['error_msg'] = "File size should not exceed 2MB.";
            $error = true;
        } 
        // Process valid file
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $profile_picture = $upload_path;
            } else {
                $_SESSION['error_msg'] = "Failed to upload profile picture. Please try again.";
                $error = true;
            }
        }
    }
    
    // Continue with the update if no errors
    if (!$error) {
        $conn->begin_transaction();
        
        try {
            // Update user table
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name = ?, email = ?, password = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $full_name, $email, $hashed_password, $profile_picture, $user_id);
            } else {
                $sql = "UPDATE users SET full_name = ?, email = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $full_name, $email, $profile_picture, $user_id);
            }
            $stmt->execute();
            
            // Update resident profile table (phone only)
            $sql = "UPDATE resident_profiles SET phone = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $phone, $user_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Update session variables
            $_SESSION['full_name'] = $full_name;
            
            $_SESSION['success_msg'] = "Profile updated successfully!";
            redirect('profile.php');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Get user's technical issues count
$sql = "SELECT COUNT(*) as issue_count FROM technical_issues WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$issue_count = $result->fetch_assoc()['issue_count'];

// Get user's forum posts count
$sql = "SELECT COUNT(*) as post_count FROM forum_posts WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$post_count = $result->fetch_assoc()['post_count'];

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
    <title>User Profile - PresDorm</title>
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
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            background-color: rgba(78, 115, 223, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            color: var(--primary);
            margin: 0 auto 1.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-stats {
            display: flex;
            margin-top: 20px;
        }
        
        .stat-box {
            padding: 10px 15px;
            margin-right: 15px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: bold;
        }
        
        .info-value {
            color: #212529;
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
        
        /* Profile picture upload button */
        .profile-upload-btn {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            text-align: center;
            padding: 5px 0;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-avatar:hover .profile-upload-btn {
            opacity: 1;
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
                    <a class="nav-link active" href="profile.php">
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
                        <h2>Your Profile</h2>
                        <p>
                            <i class="fas fa-home mr-1"></i> <?php echo isset($user['dormitory_name']) ? $user['dormitory_name'] : 'No dormitory assigned'; ?> &bull;
                            <i class="fas fa-door-open mr-1"></i> Room <?php echo isset($user['room_number']) ? $user['room_number'] : 'Not assigned'; ?>
                        </p>
                    </div>
                    <div class="col-md-5 text-right d-none d-md-block">
                        <i class="fas fa-user-circle" style="font-size: 5rem; opacity: 0.3;"></i>
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
            
            <div class="row">
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <div class="profile-avatar" id="profileImageContainer">
                                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo $user['profile_picture']; ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                                <label for="profile_picture_upload" class="profile-upload-btn">
                                    <i class="fas fa-camera mr-1"></i> Change Photo
                                </label>
                            </div>
                            <h4><?php echo $user['full_name']; ?></h4>
                            <p class="text-muted"><?php echo ucfirst($user['user_type']); ?></p>
                            
                            <div class="profile-stats">
                                <div class="stat-box flex-fill">
                                    <div class="stat-number"><?php echo $issue_count; ?></div>
                                    <div class="stat-label">Issues</div>
                                </div>
                                <div class="stat-box flex-fill">
                                    <div class="stat-number"><?php echo $post_count; ?></div>
                                    <div class="stat-label">Forum Posts</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Residence Information Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-home mr-1 text-primary"></i> Residence Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-group">
                                <div class="info-label">Student ID (NIM)</div>
                                <div class="info-value"><?php echo $user['student_id'] ?? 'Belum diatur'; ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Dormitory</div>
                                <div class="info-value"><?php echo $user['dormitory_name'] ?? 'Not assigned'; ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Room Number</div>
                                <div class="info-value"><?php echo $user['room_number'] ?? 'Not assigned'; ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Move-in Date</div>
                                <div class="info-value">
                                    <?php echo isset($user['move_in_date']) ? date('F d, Y', strtotime($user['move_in_date'])) : 'Not available'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Information Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-info-circle mr-1 text-info"></i> Account Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <div class="info-label">Account Type</div>
                                        <div class="info-value"><?php echo ucfirst($user['user_type']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <div class="info-label">Account Created</div>
                                        <div class="info-value">
                                            <?php echo isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'Not available'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <!-- Personal Information Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-user mr-1 text-primary"></i> Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <!-- Hidden file input for profile picture -->
                                <input type="file" id="profile_picture_upload" name="profile_picture" class="d-none" accept="image/*">
                                
                                <div class="form-group row">
                                    <label for="username" class="col-sm-3 col-form-label">Username</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control-plaintext" id="username" value="<?php echo $user['username']; ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="full_name" class="col-sm-3 col-form-label">Full Name</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="email" class="col-sm-3 col-form-label">Email Address</label>
                                    <div class="col-sm-9">
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="phone" class="col-sm-3 col-form-label">Phone Number</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $user['phone'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <hr>
                                <h5>Change Password</h5>
                                <p class="text-muted small">Leave blank if you don't want to change your password</p>
                                
                                <div class="form-group row">
                                    <label for="password" class="col-sm-3 col-form-label">New Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" class="form-control" id="password" name="password" minlength="6">
                                        <small class="form-text text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="confirm_password" class="col-sm-3 col-form-label">Confirm Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <div class="col-sm-9 offset-sm-3">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
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

        // Handle logout link click
        $('#logoutLink').on('click', function(e) {
            e.preventDefault();
            window.location.href = '../logout.php';
        });
        
        // Display selected profile picture before upload
        document.getElementById('profile_picture_upload').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const container = document.getElementById('profileImageContainer');
                    
                    // Remove existing image or icon if any
                    while (container.firstChild) {
                        if (container.lastChild.tagName !== 'LABEL') {
                            container.removeChild(container.firstChild);
                        } else {
                            break;
                        }
                    }
                    
                    // Create new image element
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Preview';
                    
                    // Insert the new image at the beginning of the container
                    container.insertBefore(img, container.firstChild);
                };
                
                reader.readAsDataURL(file);
            }
        });
        
        // Trigger file input when clicking on the avatar or label
        document.querySelector('.profile-upload-btn').addEventListener('click', function() {
            document.getElementById('profile_picture_upload').click();
        });
    </script>
</body>
</html>