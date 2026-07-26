<?php
// ============================================================
// get_vehicle_calendar.php - Get vehicle booking calendar data
// ============================================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo '<p class="text-muted text-center py-3">Invalid vehicle ID.</p>';
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'travel_tour_db');
if ($conn->connect_error) {
    echo '<p class="text-danger">Database connection failed.</p>';
    exit;
}

// Get vehicle name
$vname = $conn->query("SELECT vehicle_name FROM vehicles WHERE id = $id")->fetch_assoc();
$vehicle_name = $vname ? $vname['vehicle_name'] : 'Vehicle #' . $id;

// Get bookings for this vehicle
$query = "SELECT 
            b.id, 
            b.booking_date, 
            b.total_cost, 
            b.status,
            b.number_of_days,
            u.full_name as customer_name,
            DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY) as end_date
          FROM bookings b
          LEFT JOIN users u ON b.user_id = u.id
          WHERE b.vehicle_id = $id 
            AND b.status NOT IN ('cancelled', 'Cancelled', 'Deleted')
          ORDER BY b.booking_date DESC
          LIMIT 30";

$result = $conn->query($query);
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

?>
<div style="padding: 4px 0;">
    <h6 class="mb-3" style="color: var(--text-primary); font-weight: 700;">
        <i class="fas fa-calendar-alt" style="color: #2563eb;"></i> 
        <?= htmlspecialchars($vehicle_name) ?> - Booking Calendar
    </h6>

    <?php if (count($bookings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): 
                        $status_class = strtolower($b['status']);
                        $status_color = $status_class == 'approved' ? 'success' : 
                                       ($status_class == 'confirmed' ? 'info' : 
                                       ($status_class == 'pending' ? 'warning' : 'secondary'));
                    ?>
                        <tr>
                            <td><strong>#<?= $b['id'] ?></strong></td>
                            <td><?= htmlspecialchars($b['customer_name'] ?? 'Guest') ?></td>
                            <td><?= date('Y-m-d', strtotime($b['booking_date'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($b['end_date'])) ?></td>
                            <td><?= $b['number_of_days'] ?></td>
                            <td>LKR <?= number_format($b['total_cost'], 2) ?></td>
                            <td><span class="badge bg-<?= $status_color ?>"><?= ucfirst($b['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary -->
        <div class="mt-2 d-flex gap-3 flex-wrap" style="font-size: 12px;">
            <span><strong>Total Bookings:</strong> <?= count($bookings) ?></span>
            <?php 
            $total_revenue = array_sum(array_column($bookings, 'total_cost'));
            ?>
            <span><strong>Total Revenue:</strong> <span style="color: #10b981; font-weight: 700;">LKR <?= number_format($total_revenue, 2) ?></span></span>
        </div>
    <?php else: ?>
        <div class="text-center py-4" style="color: var(--text-muted);">
            <i class="fas fa-calendar-plus fa-2x d-block mb-2" style="opacity: 0.3;"></i>
            <p>No bookings found for this vehicle.</p>
        </div>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>