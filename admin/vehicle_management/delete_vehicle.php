<?php
// delete_vehicle.php
require_once 'database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: vehicles.php?msg=deleted");
    } else {
        header("Location: vehicles.php?msg=error");
    }
    $stmt->close();
}
$conn->close();
exit;
?>