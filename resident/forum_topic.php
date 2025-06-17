<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if topic ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('forum.php');
    exit;
}

$topic_id = (int)$_GET['id'];

// Get topic information
$sql = "SELECT ft.id, ft.title, ft.created_at, u.full_name as author, fc.name as category, fc.id as category_id
        FROM forum_topics ft
        JOIN users u ON ft.user_id = u.id
        JOIN forum_categories fc ON ft.category_id = fc.id
        WHERE ft.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Topic not found
    redirect('forum.php');
    exit;
}

$topic = $result->fetch_assoc();

// Get posts for this topic
$sql = "SELECT fp.id, fp.content, fp.created_at, u.full_name, u.id as user_id
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.topic_id = ?
        ORDER BY fp.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
}

// Handle reply submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reply'])) {
    $content = sanitize($_POST['content']);
    $error = '';
    
    // Validate input
    if (empty($content)) {
        $error = "Please enter a reply.";
    } else {
        // Insert the reply
        $sql = "INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $topic_id, $user_id, $content);
        
        if ($stmt->execute()) {
            // Redirect to refresh the page
            redirect("forum_topic.php?id=" . $topic_id . "#latest");
            exit;
        } else {
            $error = "Failed to post reply. Please try again.";
        }
    }
}

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
    <title><?php echo $topic['title']; ?> - PresDorm Forum</title>
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
        
        /* Post styling */
        .post-card {
            margin-bottom: 20px;
        }
        
        .post-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .post-content {
            padding: 15px;
            white-space: pre-line;
        }
        
        .original-post {
            border-left: 5px solid var(--primary);
        }
        
        .reply {
            border-left: 5px solid var(--secondary);
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
        
        /* Sticky footer */
        .sticky-footer {
            padding: 1rem;
            margin-top: 2rem;
            border-top: 1px solid #e3e6f0;
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
                        <h2><?php echo $topic['title']; ?></h2>
                        <p>
                            <i class="fas fa-folder mr-1"></i> Category: <?php echo $topic['category']; ?> &nbsp;&middot;&nbsp;
                            <i class="fas fa-user mr-1"></i> Started by: <?php echo $topic['author']; ?>
                        </p>
                    </div>
                    <div class="col-md-5 text-right d-none d-md-block">
                        <i class="fas fa-comments" style="font-size: 5rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="welcome-wave"></div>
            </div>

            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="forum.php">Forum</a></li>
                    <li class="breadcrumb-item"><a href="forum_category.php?id=<?php echo $topic['category_id']; ?>"><?php echo $topic['category']; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $topic['title']; ?></li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-end mb-4">
                <a href="forum.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Forum
                </a>
            </div>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $index => $post): ?>
                    <div class="card post-card <?php echo ($index == 0) ? 'original-post' : 'reply'; ?>" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $post['full_name']; ?></strong>
                                    <?php if ($index == 0): ?>
                                        <span class="badge badge-primary ml-2">Original Poster</span>
                                    <?php endif; ?>
                                </div>
                                <small><?php echo date('F d, Y H:i', strtotime($post['created_at'])); ?></small>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                    </div>
                    <?php if ($index == count($posts) - 1): ?>
                        <span id="latest"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning">No posts found for this topic.</div>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Post a Reply</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $topic_id); ?>" method="post">
                        <div class="form-group">
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        <button type="submit" name="submit_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane mr-1"></i> Post Reply
                        </button>
                    </form>
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