<?php
// Allow POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Simple API key check (optional)
$api_key = isset($_POST['api_key']) ? $_POST['api_key'] : '';
if ($api_key !== 'YOUR_SECRET_API_KEY') {
    http_response_code(401);
    exit('Unauthorized');
}

include("../includes/db.php");

$vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($vehicle_id <= 0 || $lat === null || $lng === null) {
    http_response_code(400);
    exit('Invalid parameters');
}

// Update database
$stmt = $conn->prepare("UPDATE vehicles SET last_lat = ?, last_lng = ?, last_update = NOW() WHERE id = ?");
$stmt->bind_param("ddi", $lat, $lng, $vehicle_id);
if ($stmt->execute()) {
    echo "Location updated successfully";
} else {
    http_response_code(500);
    echo "Error updating location";
}
$stmt->close();
?>