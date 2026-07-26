<?php
session_start();
include("../includes/db.php");

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$query = "SELECT id, vehicle_name, status, driver_name, driver_phone, last_lat, last_lng, last_update 
          FROM vehicles 
          WHERE status != 'Inactive' 
          ORDER BY status";
$result = mysqli_query($conn, $query);
$vehicles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vehicles[] = $row;
}
header('Content-Type: application/json');
echo json_encode($vehicles);
?>