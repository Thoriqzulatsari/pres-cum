<?php
// Include konfigurasi database
require_once "config.php";

// Password baru yang mudah diingat
$new_password = "admin123";
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password admin
$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "Password admin berhasil direset!<br>";
    echo "Username: admin<br>";
    echo "Password: $new_password<br>";
    echo "<a href='login.php'>Klik disini untuk login</a>";
} else {
    echo "Gagal mereset password: " . $conn->error;
}

$conn->close();
?>