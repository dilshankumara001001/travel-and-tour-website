<?php
session_start();
include("../includes/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['vehicle_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];

    // Check vehicle availability
    $check = $conn->query("SELECT status FROM vehicles WHERE id = $vehicle_id");
    $row = $check->fetch_assoc();
    if ($row['status'] !== 'Available') {
        die("Vehicle is not available");
    }

    // Insert into booking_vehicles
    $stmt = $conn->prepare("INSERT INTO booking_vehicles (booking_id, vehicle_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $booking_id, $vehicle_id);
    if ($stmt->execute()) {
        // Update vehicle status
        $conn->query("UPDATE vehicles SET status = 'On Trip' WHERE id = $vehicle_id");
        echo "Vehicle assigned successfully";
    } else {
        echo "Error assigning vehicle";
    }
    $stmt->close();
} else {
    echo "Invalid request";
}
?>