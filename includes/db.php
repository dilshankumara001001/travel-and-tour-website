<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "travel_tour_db";

// Create connection with Error Reporting
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

// Set character set to utf8mb4 for proper text handling
mysqli_set_charset($conn, "utf8mb4");
?>