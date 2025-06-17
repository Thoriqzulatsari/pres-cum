<?php
require_once '../config.php';

// Periksa apakah pengguna sudah login dan merupakan admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
    exit;
}

// --- PHP LOGIC FROM add_marketplace_item.php STARTS HERE ---
// Inisialisasi variabel untuk form tambah item
$add_title = $add_description = $add_price = $add_whatsapp = $add_image_filename = "";
$add_seller_id = $add_dormitory_id = 0;
$add_errors = []; // Gunakan array error yang berbeda untuk form tambah

// Ambil daftar penduduk untuk dropdown penjual
$sql_residents = "SELECT id, full_name, username FROM users WHERE user_type = 'resident' ORDER BY full_name ASC";
$result_residents = $conn->query($sql_residents);
$residents_list = []; // Ganti nama variabel agar tidak konflik
if ($result_residents && $result_residents->num_rows > 0) {
    while ($row_res = $result_residents->fetch_assoc()) {
        $residents_list[] = $row_res;
    }
}

// Ambil daftar asrama untuk dropdown (opsional)
$sql_dormitories_form = "SELECT id, name FROM dormitories ORDER BY name ASC"; // Query baru untuk form
$result_dormitories_form = $conn->query($sql_dormitories_form);
$dormitories_list_form = []; // Ganti nama variabel
if ($result_dormitories_form && $result_dormitories_form->num_rows > 0) {
    while ($row_dorm_form = $result_dormitories_form->fetch_assoc()) {
        $dormitories_list_form[] = $row_dorm_form;
    }
}

// Proses form tambah item ketika disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item_modal'])) { // Ubah nama tombol submit modal
    $add_seller_id = isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0;
    $add_title = sanitize($_POST['title']);
    $add_description = sanitize($_POST['description']);
    $add_price = isset($_POST['price']) ? filter_var($_POST['price'], FILTER_VALIDATE_FLOAT) : false;
    $add_whatsapp = sanitize($_POST['whatsapp']);
    $add_dormitory_id = isset($_POST['dormitory_id']) && !empty($_POST['dormitory_id']) ? (int)$_POST['dormitory_id'] : NULL;

    // Validasi input
    if ($add_seller_id <= 0) {
        $add_errors[] = "Silakan pilih penjual.";
    }
    if (empty($add_title)) {
        $add_errors[] = "Judul item tidak boleh kosong.";
    }
    if (empty($add_description)) {
        $add_errors[] = "Deskripsi item tidak boleh kosong.";
    }
    if ($add_price === false || $add_price <= 0) {
        $add_errors[] = "Harga harus angka positif.";
    }
    if (empty($add_whatsapp)) {
        $add_errors[] = "Nomor WhatsApp penjual tidak boleh kosong.";
    } elseif (!preg_match('/^[0-9+]{10,15}$/', preg_replace('/[^0-9+]/', '', $add_whatsapp))) {
        $add_errors[] = "Format nomor WhatsApp tidak valid.";
    }

    // Handle upload gambar
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file = $_FILES['item_image'];

        if (!in_array($file['type'], $allowed_types)) {
            $add_errors[] = "Hanya format JPG, JPEG, PNG, dan GIF yang diperbolehkan untuk gambar.";
        } elseif ($file['size'] > $max_size) {
            $add_errors[] = "Ukuran file gambar tidak boleh melebihi 5MB.";
        } else {
            $upload_dir = '../uploads/marketplace/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $add_image_filename = 'item_' . uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $add_image_filename;

            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $add_errors[] = "Gagal mengunggah gambar. Silakan coba lagi.";
                $add_image_filename = null;
            }
        }
    } elseif (isset($_FILES['item_image']) && $_FILES['item_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $add_errors[] = "Terjadi kesalahan saat mengunggah gambar: Error code " . $_FILES['item_image']['error'];
    }

    if (empty($add_errors)) {
        $sql_insert = "INSERT INTO marketplace_items (user_id, title, description, price, whatsapp, image, dormitory_id, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'available', NOW())";
        $stmt_insert = $conn->prepare($sql_insert);
        $final_dorm_id_modal = ($add_dormitory_id === 0 || $add_dormitory_id === NULL) ? NULL : $add_dormitory_id;
        $stmt_insert->bind_param("issdssi", $add_seller_id, $add_title, $add_description, $add_price, $add_whatsapp, $add_image_filename, $final_dorm_id_modal);

        if ($stmt_insert->execute()) {
            $_SESSION['success_msg'] = "Item berhasil ditambahkan ke marketplace.";
            // Kosongkan variabel form setelah berhasil
            $add_title = $add_description = $add_price = $add_whatsapp = $add_image_filename = "";
            $add_seller_id = $add_dormitory_id = 0;
            // Tidak perlu redirect, halaman akan me-refresh data marketplace di bawah
        } else {
            $add_errors[] = "Gagal menambahkan item: " . $conn->error;
        }
        $stmt_insert->close();
    }
}
// --- PHP LOGIC FROM add_marketplace_item.php ENDS HERE ---


// Handle item actions (delete, toggle status, delete comment) - LOGIC YANG SUDAH ADA
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $itemId = (int)$_GET['id'];
    
    if ($action === 'delete') {
        $sql = "DELETE FROM marketplace_items WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $itemId);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Item deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting item: " . $conn->error;
        }
        $stmt->close();
        redirect("marketplace.php"); 
        exit;
    } elseif ($action === 'toggle_status') {
        $sql = "UPDATE marketplace_items SET status = CASE WHEN status = 'available' THEN 'sold' ELSE 'available' END WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $itemId);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Item status updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating item status: " . $conn->error;
        }
        $stmt->close();
        redirect("marketplace.php");
        exit;
    } elseif ($action === 'delete_comment' && isset($_GET['comment_id'])) {
        $commentId = (int)$_GET['comment_id'];
        $sql = "DELETE FROM marketplace_comments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $commentId);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Comment deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting comment: " . $conn->error;
        }
        $stmt->close();
        redirect("marketplace.php");
        exit;
    }
}


// Get statistics - LOGIC YANG SUDAH ADA
$stats = [];
$sql = "SELECT COUNT(*) as total FROM marketplace_items";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) { $stats['total_items'] = $result->fetch_assoc()['total']; } else { $stats['total_items'] = 0; }

$sql = "SELECT COUNT(*) as total FROM marketplace_items WHERE status = 'available'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) { $stats['available_items'] = $result->fetch_assoc()['total']; } else { $stats['available_items'] = 0; }

$sql = "SELECT COUNT(*) as total FROM marketplace_items WHERE status = 'sold'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) { $stats['sold_items'] = $result->fetch_assoc()['total']; } else { $stats['sold_items'] = 0; }

// Get latest marketplace items - LOGIC YANG SUDAH ADA
$sql_latest_items = "SELECT mi.id, mi.title, mi.price, mi.status, mi.created_at, u.full_name, d.name as dormitory_name
        FROM marketplace_items mi
        JOIN users u ON mi.user_id = u.id
        LEFT JOIN dormitories d ON mi.dormitory_id = d.id
        ORDER BY mi.created_at DESC LIMIT 10"; 
$latest_items = [];
$result_latest_items = $conn->query($sql_latest_items);
if ($result_latest_items && $result_latest_items->num_rows > 0) {
    while($row_latest = $result_latest_items->fetch_assoc()) {
        $latest_items[] = $row_latest;
    }
}

// Get latest comments - LOGIC YANG SUDAH ADA
$sql_latest_comments = "SELECT mc.id, mc.comment, mc.created_at, u.full_name, mi.title as item_title, mi.id as item_id
        FROM marketplace_comments mc
        JOIN users u ON mc.user_id = u.id
        JOIN marketplace_items mi ON mc.item_id = mi.id
        ORDER BY mc.created_at DESC LIMIT 5";
$latest_comments = [];
$result_latest_comments = $conn->query($sql_latest_comments);
if ($result_latest_comments && $result_latest_comments->num_rows > 0) {
    while($row_comments = $result_latest_comments->fetch_assoc()) {
        $latest_comments[] = $row_comments;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Management - PresDorm</title>
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
            background-color: var(--light);
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
        .custom-file-label::after {
            content: "Browse";
        }
        
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
                    <a class="nav-link active" href="marketplace.php">
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
                <h1 class="h3 mb-0 text-gray-800">Marketplace Management</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                </a>
            </div>
            
            <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
             <?php if (!empty($add_errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($add_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards (Existing logic) -->
            <div class="row mb-4">
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100" style="border-left-color: var(--primary);">
                        <div class="card-body">
                            <div class="stats-icon text-primary">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['total_items'] ?? 0; ?></div>
                            <div class="stats-label">Total Listings</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100" style="border-left-color: var(--success);">
                        <div class="card-body">
                            <div class="stats-icon text-success">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['available_items'] ?? 0; ?></div>
                            <div class="stats-label">Available Items</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card h-100" style="border-left-color: var(--info);">
                        <div class="card-body">
                            <div class="stats-icon text-info">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['sold_items'] ?? 0; ?></div>
                            <div class="stats-label">Sold Items</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-store mr-2"></i>Recent Marketplace Listings</h6>
                            <div>
                                <!-- Tombol untuk memicu modal -->
                                <button type="button" class="btn btn-sm btn-success mr-2" data-toggle="modal" data-target="#addItemModal">
                                    <i class="fas fa-plus"></i> Add New Item
                                </button>
                                <a href="marketplace_items.php" class="btn btn-sm btn-primary">  
                                    <i class="fas fa-arrow-right"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($latest_items) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Seller</th>
                                                <th>Price</th>
                                                <th>Dormitory</th>
                                                <th>Status</th>
                                                <th>Listed On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latest_items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                                    <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                                    <td><?php echo htmlspecialchars($item['dormitory_name'] ?? 'All Dorms'); ?></td>
                                                    <td>
                                                        <?php if ($item['status'] == 'available'): ?>
                                                            <span class="badge badge-success">Available</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Sold</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                                    <td>
                                                        <a href="?action=toggle_status&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" onclick="return confirm('Are you sure you want to change the status of this item?')">
                                                            <i class="fas fa-exchange-alt"></i> Status
                                                        </a>
                                                        <a href="?action=delete&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-store text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No marketplace items listed yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (count($latest_comments) > 0): ?>
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-comments mr-2"></i>Recent Comments</h6>
                            <a href="marketplace_comments.php" class="btn btn-sm btn-success"> 
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Item</th>
                                            <th>Comment</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latest_comments as $comment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($comment['full_name']); ?></td>
                                                <td>
                                                    <a href="view_marketplace_item.php?id=<?php echo $comment['item_id']; ?>">
                                                        <?php echo htmlspecialchars($comment['item_title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($comment['comment'], 0, 50)) . (strlen($comment['comment']) > 50 ? '...' : ''); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#commentModal<?php echo $comment['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="?action=delete_comment&id=<?php echo $comment['item_id']; ?>&comment_id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this comment?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    
                                                    <!-- Comment Modal -->
                                                    <div class="modal fade" id="commentModal<?php echo $comment['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="commentModalLabel<?php echo $comment['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="commentModalLabel<?php echo $comment['id']; ?>">Comment Details</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p><strong>Item:</strong> <?php echo htmlspecialchars($comment['item_title']); ?></p>
                                                                    <p><strong>User:</strong> <?php echo htmlspecialchars($comment['full_name']); ?></p>
                                                                    <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($comment['created_at'])); ?></p>
                                                                    <p><strong>Comment:</strong></p>
                                                                    <div class="p-3 bg-light rounded">
                                                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                    <a href="?action=delete_comment&id=<?php echo $comment['item_id']; ?>&comment_id=<?php echo $comment['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this comment?')">
                                                                        Delete Comment
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Marketplace Item</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modal_seller_id">Seller (Resident) <span class="text-danger">*</span></label>
                            <select class="form-control" id="modal_seller_id" name="seller_id" required>
                                <option value="">-- Select Seller --</option>
                                <?php foreach ($residents_list as $resident): ?>
                                    <option value="<?php echo $resident['id']; ?>" <?php echo ($add_seller_id == $resident['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($resident['full_name']) . " (" . htmlspecialchars($resident['username']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modal_title">Item Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_title" name="title" value="<?php echo htmlspecialchars($add_title); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="modal_description">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="modal_description" name="description" rows="5" required><?php echo htmlspecialchars($add_description); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal_price">Price (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="modal_price" name="price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($add_price); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="modal_whatsapp">Seller's WhatsApp Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_whatsapp" name="whatsapp" placeholder="e.g., 6281234567890" value="<?php echo htmlspecialchars($add_whatsapp); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_item_image">Item Image (Optional)</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="modal_item_image" name="item_image" accept="image/jpeg,image/png,image/gif">
                                <label class="custom-file-label" for="modal_item_image">Choose file...</label>
                            </div>
                            <small class="form-text text-muted">Max file size: 5MB. Allowed types: JPG, PNG, GIF.</small>
                        </div>

                        <div class="form-group">
                            <label for="modal_dormitory_id">Specific to Dormitory (Optional)</label>
                            <select class="form-control" id="modal_dormitory_id" name="dormitory_id">
                                <option value="">-- All Dormitories --</option>
                                <?php foreach ($dormitories_list_form as $dorm_form): ?>
                                    <option value="<?php echo $dorm_form['id']; ?>" <?php echo ($add_dormitory_id == $dorm_form['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dorm_form['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_item_modal" class="btn btn-success"> 
                            <i class="fas fa-plus-circle mr-1"></i> Add Item
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
            $('.dropdown-toggle').dropdown();
            
            $('#logoutLink').on('click', function(e) {
                e.preventDefault();
                window.location.href = '../logout.php';
            });

            // Update custom file input label with filename
            $('.custom-file-input').on('change', function() {
               let fileName = $(this).val().split('\\').pop();
               $(this).next('.custom-file-label').addClass("selected").html(fileName || "Choose file...");
            });

            // If there were errors adding an item, show the modal again
            <?php if (!empty($add_errors) && isset($_POST['add_item_modal'])): ?>
                $('#addItemModal').modal('show');
            <?php endif; ?>
        });
    </script>
</body>
</html>
