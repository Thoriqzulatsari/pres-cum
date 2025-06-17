<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Create new topic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_topic'])) {
    $category_id = (int)$_POST['category_id'];
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $error = '';
    
    // Validate inputs
    if (empty($title)) {
        $error = "Please enter a topic title.";
    } elseif (empty($content)) {
        $error = "Please enter content for your topic.";
    } elseif ($category_id <= 0) {
        $error = "Please select a category.";
    } else {
        // Insert the topic into the database
        $conn->begin_transaction();
        
        try {
            // Insert topic
            $sql = "INSERT INTO forum_topics (category_id, user_id, title) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $category_id, $user_id, $title);
            
            if ($stmt->execute()) {
                $topic_id = $conn->insert_id;
                
                // Insert first post
                $sql2 = "INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("iis", $topic_id, $user_id, $content);
                
                if ($stmt2->execute()) {
                    $conn->commit();
                    $_SESSION['success_msg'] = "Topic created successfully.";
                    redirect("forum_topic.php?id=" . $topic_id);
                    exit;
                } else {
                    throw new Exception("Error creating post");
                }
            } else {
                throw new Exception("Error creating topic");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to create topic. " . $e->getMessage();
        }
    }
}

// Get forum categories
$sql = "SELECT id, name, description FROM forum_categories ORDER BY name";
$result = $conn->query($sql);
$categories = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get latest topics for each category
$topics_by_category = [];
foreach ($categories as $category) {
    $sql = "SELECT ft.id, ft.title, ft.created_at, u.full_name, 
                   (SELECT COUNT(*) FROM forum_posts WHERE topic_id = ft.id) as post_count,
                   (SELECT MAX(created_at) FROM forum_posts WHERE topic_id = ft.id) as last_post
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            WHERE ft.category_id = ?
            ORDER BY last_post DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $topics = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $topics[] = $row;
        }
    }
    
    $topics_by_category[$category['id']] = $topics;
}

// Get resident information
$sql = "SELECT rp.*, r.room_number, d.name as dormitory_name
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

// Count stats
// Count total topics
$total_topics = 0;
$total_posts = 0;
foreach ($topics_by_category as $topics) {
    $total_topics += count($topics);
    foreach ($topics as $topic) {
        $total_posts += $topic['post_count'];
    }
}

// Get user's topics count
$sql = "SELECT COUNT(*) as topic_count FROM forum_topics WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_topics = $result->fetch_assoc()['topic_count'];

// Get user's posts count
$sql = "SELECT COUNT(*) as post_count FROM forum_posts WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_posts = $result->fetch_assoc()['post_count'];

// Count unread notifications
$sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_notifications = $result->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Forum - PresDorm</title>
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
        
        .category-card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
        }
        
        .category-card .card-header {
            background-color: var(--info);
            color: white;
            font-weight: 600;
        }
        
        .category-card .card-body {
            padding: 1.25rem;
        }
        
        .topic-row {
            transition: all 0.2s;
        }
        
        .topic-row:hover {
            background-color: var(--light);
        }
        
        .topic-row td {
            vertical-align: middle;
        }
        
        .topic-row a {
            color: var(--primary);
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .topic-row a:hover {
            color: var(--primary-dark);
            text-decoration: none;
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
                    <a class="nav-link active" href="forum.php">
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
                        <h2>Community Forum</h2>
                        <p>
                            <i class="fas fa-comments mr-1"></i> Connect with fellow residents, discuss ideas, and share information
                        </p>
                    </div>
                    <div class="col-md-5 text-right d-none d-md-block">
                        <i class="fas fa-comments" style="font-size: 5rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="welcome-wave"></div>
            </div>

            <!-- Content Row: Stats -->
            <div class="row">
                <!-- Total Topics Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card primary h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Total Topics</div>
                                    <div class="stat-value"><?php echo $total_topics; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Posts Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card success h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Total Posts</div>
                                    <div class="stat-value"><?php echo $total_posts; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Your Topics Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card info h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Your Topics</div>
                                    <div class="stat-value"><?php echo $user_topics; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-edit stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Your Posts Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card warning h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="stat-label">Your Posts</div>
                                    <div class="stat-value"><?php echo $user_posts; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comment-dots stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Topic Button -->
            <div class="d-flex justify-content-end mb-4">
                <button class="btn btn-primary" data-toggle="modal" data-target="#newTopicModal">
                    <i class="fas fa-plus-circle mr-1"></i> Create New Topic
                </button>
            </div>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_msg']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Forum Categories -->
            <?php foreach ($categories as $category): ?>
                <div class="card category-card shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo $category['name']; ?></h5>
                            <button class="btn btn-sm btn-light" data-toggle="modal" data-target="#newTopicModal" data-category="<?php echo $category['id']; ?>">
                                <i class="fas fa-plus-circle mr-1"></i> New Topic
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3"><?php echo $category['description']; ?></p>
                        
                        <?php if (count($topics_by_category[$category['id']]) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Topic</th>
                                            <th>Started By</th>
                                            <th>Replies</th>
                                            <th>Last Post</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topics_by_category[$category['id']] as $topic): ?>
                                            <tr class="topic-row">
                                                <td>
                                                    <a href="forum_topic.php?id=<?php echo $topic['id']; ?>"><?php echo $topic['title']; ?></a>
                                                </td>
                                                <td><?php echo $topic['full_name']; ?></td>
                                                <td><?php echo ($topic['post_count'] - 1); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($topic['last_post'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-right mt-3">
                                <a href="forum_category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list mr-1"></i> View All Topics in <?php echo $category['name']; ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments text-gray-300" style="font-size: 3rem;"></i>
                                <p class="text-gray-500 mt-3">No topics in this category yet.</p>
                                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newTopicModal" data-category="<?php echo $category['id']; ?>">
                                    <i class="fas fa-plus-circle mr-1"></i> Start a new topic in <?php echo $category['name']; ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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

    <!-- New Topic Modal -->
    <div class="modal fade" id="newTopicModal" tabindex="-1" role="dialog" aria-labelledby="newTopicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="newTopicModalLabel">Create New Topic</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="title">Topic Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="content">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_topic" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Create Topic
                        </button>
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
            
            // Auto-open modal if action=new is in URL or set category if provided
            <?php if (isset($_GET['action']) && $_GET['action'] == 'new'): ?>
                $('#newTopicModal').modal('show');
            <?php endif; ?>
            
            <?php if (isset($_GET['category'])): ?>
                $('#category_id').val(<?php echo (int)$_GET['category']; ?>);
            <?php endif; ?>
            
            // Set category when clicking "Start a new topic in [Category]" button
            $('[data-target="#newTopicModal"]').click(function() {
                var categoryId = $(this).data('category');
                if (categoryId) {
                    $('#category_id').val(categoryId);
                }
            });
        });
    </script>
</body>
</html>