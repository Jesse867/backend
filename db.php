<?php
$host = "localhost";
$user = "root";  // Change if needed
$pass = "";      // Change if needed
$db_name = "hospital_db";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
