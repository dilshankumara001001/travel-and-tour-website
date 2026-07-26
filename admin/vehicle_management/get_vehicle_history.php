<?php
$id = intval($_GET['id'] ?? 0);
$conn = new mysqli('localhost','root','','travel_tour_db');
$res = $conn->query("SELECT * FROM vehicle_audit_logs WHERE vehicle_id=$id OR vehicle_id=0 ORDER BY created_at DESC LIMIT 50");
if ($res->num_rows > 0) {
    echo '<table class="table table-sm"><thead><tr><th>Date</th><th>Action</th><th>Details</th></tr></thead><tbody>';
    while($r=$res->fetch_assoc()) {
        echo '<tr><td>'.$r['created_at'].'</td><td>'.$r['action'].'</td><td>'.($r['new_data']??'—').'</td></tr>';
    }
    echo '</tbody></table>';
} else echo '<p class="text-muted">No history.</p>';
?>