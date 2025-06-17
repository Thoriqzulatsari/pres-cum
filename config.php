<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'u287442801_presdorm');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

// Function to check if user is resident
function isResident() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'resident';
}

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to generate random room
function getRandomAvailableRoom($dormitory_id) {
    global $conn;
    
    $sql = "SELECT id, room_number FROM rooms 
            WHERE dormitory_id = ? AND is_occupied = FALSE 
            ORDER BY RAND() LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dormitory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Function to mark room as occupied
function assignRoomToResident($room_id, $user_id, $dormitory_id) {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update room status
        $sql1 = "UPDATE rooms SET is_occupied = TRUE WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $room_id);
        $stmt1->execute();
        
        // Update resident profile
        $sql2 = "UPDATE resident_profiles SET room_id = ?, dormitory_id = ? WHERE user_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("iii", $room_id, $dormitory_id, $user_id);
        $stmt2->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return false;
    }
}