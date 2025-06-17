<?php
require_once 'config.php';

// Hitung jumlah single room yang masih tersedia
$sql = "SELECT COUNT(*) as available_rooms FROM rooms 
        WHERE dormitory_id = 2 
        AND room_type = 'single' 
        AND is_occupied = 0";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

// Kembalikan jumlah kamar tersedia
echo $row['available_rooms'];
?>