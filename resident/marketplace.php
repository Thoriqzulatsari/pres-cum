<?php
require_once '../config.php';

// Check if user is logged in and is resident
if (!isLoggedIn() || !isResident()) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

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

// Handle file upload and item creation
if (isset($_POST['add_item'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']); // Changed to floatval for better validation
    $whatsapp = sanitize($_POST['whatsapp']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Item title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Item description is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if (empty($whatsapp)) {
        $errors[] = "WhatsApp number is required";
    } else {
        // Improved WhatsApp number validation
        $whatsapp = preg_replace('/[^0-9+]/', '', $whatsapp); // Allow + for country code
        if (strlen($whatsapp) < 10 || strlen($whatsapp) > 15) {
            $errors[] = "Please enter a valid WhatsApp number";
        }
    }
    
    // Handle image upload
    $image_filename = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['item_image']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Verify file extension
        if (!in_array($filetype, $allowed)) {
            $errors[] = "Please upload a valid image (JPG, JPEG, PNG, GIF)";
        }
        
        // Verify file size - 5MB maximum
        if ($_FILES['item_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Image size exceeds the 5MB limit";
        }
        
        if (empty($errors)) {
            // Create unique filename
            $new_filename = uniqid('item_') . '.' . $filetype;
            $upload_dir = '../uploads/marketplace/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            // Move the file
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_filename = $new_filename;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    if (empty($errors)) {
        // Insert item into database
        $sql = "INSERT INTO marketplace_items (user_id, title, description, price, whatsapp, 
                image, dormitory_id, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'available')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdssi", $user_id, $title, $description, $price, $whatsapp, $image_filename, $dormitory_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Your item has been listed successfully!";
            header("Location: marketplace.php");
            exit;
        } else {
            $errors[] = "Failed to add item: " . $conn->error;
        }
    }
}

// Handle item deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    
    // First check if user has permission to delete this item
    $sql = "SELECT user_id, image FROM marketplace_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Only allow the owner of the item to delete it
        if ($row['user_id'] == $user_id) {
            // Delete the image file if it exists
            if (!empty($row['image'])) {
                $image_path = '../uploads/marketplace/' . $row['image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            // Delete any comments on this item
            $sql = "DELETE FROM marketplace_comments WHERE item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            
            // Delete the item from database
            $sql = "DELETE FROM marketplace_items WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $item_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Item deleted successfully!";
                header("Location: marketplace.php");
                exit;
            } else {
                $_SESSION['error_msg'] = "Failed to delete item: " . $conn->error;
                header("Location: marketplace.php");
                exit;
            }
        } else {
            $_SESSION['error_msg'] = "You don't have permission to delete this item.";
            header("Location: marketplace.php");
            exit;
        }
    } else {
        $_SESSION['error_msg'] = "Item not found.";
        header("Location: marketplace.php");
        exit;
    }
}

// Handle item status update (mark as sold)
if (isset($_GET['sold']) && is_numeric($_GET['sold'])) {
    $item_id = intval($_GET['sold']);
    
    // Check if user has permission
    $sql = "SELECT user_id FROM marketplace_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Only allow the owner to mark as sold
        if ($row['user_id'] == $user_id) {
            $sql = "UPDATE marketplace_items SET status = 'sold', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $item_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Item marked as sold!";
                header("Location: marketplace.php");
                exit;
            } else {
                $_SESSION['error_msg'] = "Failed to update item status: " . $conn->error;
                header("Location: marketplace.php");
                exit;
            }
        } else {
            $_SESSION['error_msg'] = "You don't have permission to update this item.";
            header("Location: marketplace.php");
            exit;
        }
    }
}

// Handle adding comments - FIXED HERE
if (isset($_POST['add_comment']) && isset($_POST['comment']) && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    $comment = sanitize($_POST['comment']);
    
    if (!empty($comment)) {
        // First verify the item exists
        $sql = "SELECT id FROM marketplace_items WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Insert the comment
            $sql = "INSERT INTO marketplace_comments (item_id, user_id, comment, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $item_id, $user_id, $comment);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Comment added successfully!";
            } else {
                $_SESSION['error_msg'] = "Failed to add comment: " . $conn->error;
            }
        } else {
            $_SESSION['error_msg'] = "Item not found.";
        }
        
        // Redirect back to the item page
        header("Location: marketplace.php?view=" . $item_id);
        exit;
    } else {
        $_SESSION['error_msg'] = "Comment cannot be empty";
        header("Location: marketplace.php?view=" . $item_id);
        exit;
    }
}

// Delete comment
if (isset($_GET['delete_comment']) && is_numeric($_GET['delete_comment'])) {
    $comment_id = intval($_GET['delete_comment']);
    $redirect_item = isset($_GET['item']) ? intval($_GET['item']) : 0;
    
    // Check if user has permission to delete this comment
    $sql = "SELECT c.user_id, i.id as item_id
            FROM marketplace_comments c
            JOIN marketplace_items i ON c.item_id = i.id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $item_id = $row['item_id'];
        
        // Allow comment deletion if user is comment author
        if ($row['user_id'] == $user_id) {
            $sql = "DELETE FROM marketplace_comments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $comment_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Comment deleted successfully!";
            } else {
                $_SESSION['error_msg'] = "Failed to delete comment: " . $conn->error;
            }
        } else {
            $_SESSION['error_msg'] = "You don't have permission to delete this comment.";
        }
        
        if ($redirect_item > 0) {
            header("Location: marketplace.php?view=" . $redirect_item);
        } else {
            header("Location: marketplace.php");
        }
        exit;
    } else {
        $_SESSION['error_msg'] = "Comment not found.";
        header("Location: marketplace.php");
        exit;
    }
}

// View specific item if ID is provided
$view_item = null;
$item_comments = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $item_id = intval($_GET['view']);
    
    // Get item details
    $sql = "SELECT m.*, u.full_name as seller_name
            FROM marketplace_items m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $view_item = $result->fetch_assoc();
        
        // Get comments for this item
        $sql = "SELECT c.*, u.full_name 
                FROM marketplace_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.item_id = ?
                ORDER BY c.created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $item_comments[] = $row;
            }
        }
    } else {
        $_SESSION['error_msg'] = "Item not found.";
        header("Location: marketplace.php");
        exit;
    }
}

// Set filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'available';
$my_items_only = isset($_GET['my_items']) && $_GET['my_items'] == '1';

// Prepare query conditions
$conditions = [];
$params = [];
$types = "";

// Only show items from this dormitory or shared items
$conditions[] = "(dormitory_id = ? OR dormitory_id IS NULL)";
$params[] = $dormitory_id;
$types .= "i";

// Filter by status
if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Filter by search term
if (!empty($search)) {
    $conditions[] = "(title LIKE ? OR description LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Filter by my items only
if ($my_items_only) {
    $conditions[] = "user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

// Build the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Set order by clause based on sort parameter
$order_clause = "ORDER BY created_at DESC"; // Default: newest first
if ($sort == 'price_low') {
    $order_clause = "ORDER BY price ASC";
} elseif ($sort == 'price_high') {
    $order_clause = "ORDER BY price DESC";
} elseif ($sort == 'oldest') {
    $order_clause = "ORDER BY created_at ASC";
}

// Get marketplace items
$sql = "SELECT m.*, u.full_name as seller_name, d.name as dormitory_name,
        (SELECT COUNT(*) FROM marketplace_comments WHERE item_id = m.id) as comment_count
        FROM marketplace_items m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN dormitories d ON m.dormitory_id = d.id
        $where_clause
        $order_clause";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Marketplace stats
$total_items = count($items);
$available_items = 0;
$sold_items = 0;
$user_items = 0;

foreach ($items as $item) {
    if ($item['status'] == 'available') {
        $available_items++;
    } else if ($item['status'] == 'sold') {
        $sold_items++;
    }
    
    if ($item['user_id'] == $user_id) {
        $user_items++;
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
    <title>Marketplace - PresDorm</title>
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
            background: linear-gradient(to right, var(--success), #1ab67c);
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
        
        .item-card {
            transition: transform 0.2s;
            height: 100%;
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        
        .item-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--success);
        }
        
        .item-meta {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        .sold-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
        }
        
        .filter-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-right: 40px;
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
        }
        
        .search-box .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .search-box .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            pointer-events: none;
        }
        
        .item-description {
            height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .whatsapp-button {
            background-color: #25D366;
            color: white;
            border: none;
            transition: all 0.2s;
        }
        
        .whatsapp-button:hover {
            background-color: #128C7E;
            color: white;
            transform: translateY(-2px);
        }
        
        .comment-section {
            margin-top: 30px;
            border-top: 1px solid #e3e6f0;
            padding-top: 20px;
        }
        
        .comment {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0.5rem;
            background-color: var(--light);
            box-shadow: 0 0.1rem 0.75rem 0 rgba(58, 59, 69, 0.05);
            transition: transform 0.2s;
        }
        
        .comment:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.2rem 1rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .comment-user {
            font-weight: bold;
            color: var(--dark);
        }
        
        .comment-time {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        .comment-actions {
            float: right;
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
        
        .bg-success {
            background-color: var(--success) !important;
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
        
        .single-item-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .custom-file-label {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
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
                    <a class="nav-link active" href="marketplace.php">
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
            <?php if (isset($view_item)): ?>
                <!-- Single Item View -->
                <div class="mb-3">
                    <a href="marketplace.php" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Listings
                    </a>
                </div>
                
                <div class="card single-item-card shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="m-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($view_item['title']); ?></h3>
                            <?php if ($view_item['status'] == 'sold'): ?>
                                <span class="badge badge-danger">SOLD</span>
                            <?php else: ?>
                                <span class="badge badge-success">AVAILABLE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($view_item['image'])): ?>
                                    <img src="../uploads/marketplace/<?php echo $view_item['image']; ?>" class="img-fluid rounded shadow-sm" alt="<?php echo htmlspecialchars($view_item['title']); ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light rounded shadow-sm" style="height: 300px;">
                                        <i class="fas fa-image text-muted" style="font-size: 100px;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h2 class="item-price mb-3">Rp. <?php echo number_format($view_item['price'], 0, '', '.'); ?></h2>
                                
                                <p class="font-weight-bold text-gray-800">Description:</p>
                                <div class="p-3 bg-light rounded shadow-sm mb-4">
                                    <?php echo nl2br(htmlspecialchars($view_item['description'])); ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <p class="mb-0"><strong>Seller:</strong> <?php echo htmlspecialchars($view_item['seller_name']); ?></p>
                                    </div>
                                    <p class="text-muted mb-0">
                                        <small>
                                            <i class="far fa-clock mr-1"></i> Posted on: 
                                            <?php echo date('F d, Y \a\t g:i A', strtotime($view_item['created_at'])); ?>
                                        </small>
                                    </p>
                                </div>
                                
                                <?php if ($view_item['status'] == 'available' && $view_item['user_id'] != $user_id): ?>
                                    <div class="mt-4">
                                        <a href="https://wa.me/<?php echo $view_item['whatsapp']; ?>" class="btn whatsapp-button btn-lg btn-block shadow-sm" target="_blank">
                                            <i class="fab fa-whatsapp mr-2"></i> Contact via WhatsApp
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($view_item['user_id'] == $user_id): ?>
                                    <div class="mt-4">
                                        <div class="btn-group w-100">
                                            <?php if ($view_item['status'] == 'available'): ?>
                                                <a href="?sold=<?php echo $view_item['id']; ?>" class="btn btn-success shadow-sm" 
                                                   onclick="return confirm('Mark this item as sold?')">
                                                    <i class="fas fa-check mr-1"></i> Mark as Sold
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $view_item['id']; ?>" class="btn btn-danger shadow-sm" 
                                               onclick="return confirm('Are you sure you want to delete this item?')">
                                                <i class="fas fa-trash mr-1"></i> Delete Listing
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Comments Section -->
                        <div class="comment-section">
                            <h4 class="text-gray-800 mb-4"><i class="fas fa-comments mr-2"></i>Comments (<?php echo count($item_comments); ?>)</h4>
                            
                            <!-- Comment Form - FIXED HERE -->
                            <form method="POST" action="marketplace.php" class="mb-4">
                                <input type="hidden" name="item_id" value="<?php echo $view_item['id']; ?>">
                                <div class="form-group">
                                    <textarea class="form-control" name="comment" rows="3" placeholder="Write a comment..." required></textarea>
                                </div>
                                <button type="submit" name="add_comment" class="btn btn-primary shadow-sm">
                                    <i class="fas fa-paper-plane mr-1"></i> Post Comment
                                </button>
                            </form>
                            
                            <!-- Comments List -->
                            <?php if (empty($item_comments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments text-gray-300" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No comments yet. Be the first to comment!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($item_comments as $comment): ?>
                                    <div class="comment">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="comment-user"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                                <span class="comment-time ml-2">
                                                    <?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?>
                                                </span>
                                            </div>
                                            <?php if ($comment['user_id'] == $user_id): ?>
                                                <div class="comment-actions">
                                                    <a href="?delete_comment=<?php echo $comment['id']; ?>&item=<?php echo $view_item['id']; ?>" 
                                                       class="text-danger" onclick="return confirm('Delete this comment?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Marketplace Listings -->
                <!-- Welcome Banner -->
                <div class="dashboard-welcome">
                    <div class="row">
                        <div class="col-md-7">
                            <h2>Dormitory Marketplace</h2>
                            <p>
                                <i class="fas fa-tag mr-1"></i> Buy and sell items with other residents of <?php echo htmlspecialchars($dormitory_name); ?> dormitory
                            </p>
                        </div>
                        <div class="col-md-5 text-right d-none d-md-block">
                            <i class="fas fa-shopping-cart" style="font-size: 5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                    <div class="welcome-wave"></div>
                </div>
                
                <!-- Content Row: Stats -->
                <div class="row">
                    <!-- Total Items Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="stat-label">Total Items</div>
                                        <div class="stat-value"><?php echo $total_items; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-basket stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Items Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="stat-label">Available Items</div>
                                        <div class="stat-value"><?php echo $available_items; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tags stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sold Items Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="stat-label">Sold Items</div>
                                        <div class="stat-value"><?php echo $sold_items; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Your Items Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="stat-label">Your Items</div>
                                        <div class="stat-value"><?php echo $user_items; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-tag stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add New Item Button -->
                <div class="d-flex justify-content-end mb-4">
                    <button type="button" class="btn btn-success shadow-sm" data-toggle="modal" data-target="#addItemModal">
                        <i class="fas fa-plus-circle mr-1"></i> List New Item
                    </button>
                </div>
                
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['success_msg']; 
                            unset($_SESSION['success_msg']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['error_msg']; 
                            unset($_SESSION['error_msg']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <!-- Filter & Search Section -->
                <div class="filter-section mb-4">
                    <h5 class="text-gray-800 mb-3">Filter Items</h5>
                    <form method="GET" action="">
                        <div class="row align-items-end">
                            <div class="col-md-4 mb-3">
                                <label for="search" class="text-gray-700">Search</label>
                                <div class="search-box">
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                                    <i class="fas fa-search search-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="sort" class="text-gray-700">Sort By</label>
                                <select class="form-control" id="sort" name="sort">
                                    <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="price_low" <?php echo ($sort == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo ($sort == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="status" class="text-gray-700">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="available" <?php echo ($status_filter == 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="sold" <?php echo ($status_filter == 'sold') ? 'selected' : ''; ?>>Sold</option>
                                    <option value="" <?php echo ($status_filter == '') ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="my_items" name="my_items" value="1" 
                                           <?php echo $my_items_only ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-gray-700" for="my_items">
                                        My Items Only
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-block shadow-sm">
                                    <i class="fas fa-filter mr-1"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Items Grid -->
                <?php if (count($items) > 0): ?>
                    <div class="row">
                        <?php foreach ($items as $item): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                <div class="card item-card">
                                    <?php if ($item['status'] == 'sold'): ?>
                                        <div class="sold-badge">
                                            <span class="badge badge-danger">SOLD</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="../uploads/marketplace/<?php echo $item['image']; ?>" class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="fas fa-image text-muted" style="font-size: 64px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-gray-800"><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <p class="item-price">Rp. <?php echo number_format($item['price'], 0, '', '.'); ?></p>
                                        <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <p class="item-meta">
                                            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($item['seller_name']); ?><br>
                                            <i class="far fa-clock mr-1"></i> <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                            <?php if ($item['comment_count'] > 0): ?>
                                                <br><i class="fas fa-comments mr-1"></i> <?php echo $item['comment_count']; ?> comments
                                            <?php endif; ?>
                                        </p>
                                        <div class="mt-auto">
                                            <a href="?view=<?php echo $item['id']; ?>" class="btn btn-primary btn-block shadow-sm">
                                                <i class="fas fa-eye mr-1"></i> View Details
                                            </a>
                                            <?php if ($item['user_id'] == $user_id): ?>
                                                <div class="btn-group btn-block mt-2">
                                                    <?php if ($item['status'] == 'available'): ?>
                                                        <a href="?sold=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" 
                                                           onclick="return confirm('Mark this item as sold?')">
                                                            <i class="fas fa-check"></i> Mark Sold
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this item?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart text-gray-300" style="font-size: 5rem;"></i>
                        <p class="text-gray-500 mt-3">No items found matching your criteria.</p>
                        <button type="button" class="btn btn-success mt-2" data-toggle="modal" data-target="#addItemModal">
                            <i class="fas fa-plus-circle mr-1"></i> List Your First Item
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
    
    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addItemModalLabel">List New Item for Sale</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="title">Item Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            <small class="form-text text-muted">Provide detailed information about your item including condition, age, etc.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="number" class="form-control" id="price" name="price" min="1" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="whatsapp">WhatsApp Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                </div>
                                <input type="text" class="form-control" id="whatsapp" name="whatsapp" 
                                       placeholder="Include country code (e.g., 628123456789)" required>
                            </div>
                            <small class="form-text text-muted">Enter your WhatsApp number with country code, without spaces or dashes.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="item_image">Item Image (Optional)</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="item_image" name="item_image" accept="image/*">
                                <label class="custom-file-label" for="item_image">Choose file</label>
                            </div>
                            <small class="form-text text-muted">Upload a clear image of your item. Max size: 5MB. Accepted formats: JPG, JPEG, PNG, GIF.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_item" class="btn btn-success">
                            <i class="fas fa-plus-circle mr-1"></i> List Item
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
            
            // Custom file input label update
            $(".custom-file-input").on("change", function() {
                var fileName = $(this).val().split("\\").pop();
                $(this).siblings(".custom-file-label").addClass("selected").html(fileName || "Choose file");
            });
            
            // Form validation for item listing
            $("form").on("submit", function(e) {
                var isAddItem = $(this).find("button[name='add_item']").length > 0;
                
                // Only validate price for item listing form, not for comment form
                if (isAddItem) {
                    if ($("#price").val() <= 0) {
                        e.preventDefault();
                        alert("Price must be greater than 0");
                        return false;
                    }
                    
                    var whatsapp = $("#whatsapp").val().replace(/[^0-9+]/g, '');
                    if (whatsapp.length < 10 || whatsapp.length > 15) {
                        e.preventDefault();
                        alert("Please enter a valid WhatsApp number with country code");
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>