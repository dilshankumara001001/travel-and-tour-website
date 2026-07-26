<?php
session_start();
include("../includes/db.php");

// ============================================================
// 0. GET ADMIN NAME FROM SESSION
// ============================================================
$admin_name = 'Admin';
if (isset($_SESSION['admin_name']) && !empty($_SESSION['admin_name'])) {
    $admin_name = $_SESSION['admin_name'];
} elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $admin_name = $_SESSION['username'];
} elseif (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) {
    $admin_name = $_SESSION['full_name'];
}

// ============================================================
// 1. DATE FILTERS
// ============================================================
$start_date = isset($_GET['start_date']) && $_GET['start_date'] ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date   = isset($_GET['end_date']) && $_GET['end_date'] ? $_GET['end_date'] : date('Y-m-d');

// ============================================================
// 2. COMPLETED TRIPS (REAL-TIME: END DATE < TODAY)
// ============================================================
$completed_trips_query = "SELECT 
    b.id as booking_id,
    b.booking_date,
    b.pickup_location,
    b.destination_location,
    b.calculated_distance,
    b.total_cost,
    b.status,
    b.number_of_days,
    p.title as package_name,
    u.full_name as customer_name,
    u.email as customer_email,
    v.id as vehicle_id,
    v.vehicle_name,
    v.price_per_km,
    v.capacity,
    v.driver_name,
    v.driver_phone,
    v.status as vehicle_status,
    o.id as operator_id,
    o.name as operator_name,
    o.phone as operator_phone,
    o.email as operator_email,
    'Completed' as realtime_status,
    DATEDIFF(CURDATE(), DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)) as days_completed_ago
FROM bookings b 
LEFT JOIN packages p ON b.package_id = p.id 
LEFT JOIN users u ON b.user_id = u.id 
LEFT JOIN vehicles v ON b.vehicle_id = v.id
LEFT JOIN operators o ON v.operator_id = o.id
WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
  AND b.booking_date BETWEEN '$start_date' AND '$end_date'
  AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)
ORDER BY b.booking_date DESC";
$completed_trips_result = mysqli_query($conn, $completed_trips_query);
$completed_trips_count = mysqli_num_rows($completed_trips_result);

// ============================================================
// 3. ONGOING TRIPS (REAL-TIME: TODAY BETWEEN START & END DATE)
// ============================================================
$ongoing_trips_query = "SELECT 
    b.id as booking_id,
    b.booking_date,
    b.pickup_location,
    b.destination_location,
    b.calculated_distance,
    b.total_cost,
    b.status,
    b.number_of_days,
    p.title as package_name,
    u.full_name as customer_name,
    u.email as customer_email,
    v.id as vehicle_id,
    v.vehicle_name,
    v.price_per_km,
    v.capacity,
    v.driver_name,
    v.driver_phone,
    v.status as vehicle_status,
    o.id as operator_id,
    o.name as operator_name,
    o.phone as operator_phone,
    o.email as operator_email,
    'Ongoing' as realtime_status,
    DATEDIFF(CURDATE(), b.booking_date) + 1 as day_number,
    DATEDIFF(DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY), CURDATE()) as days_remaining
FROM bookings b 
LEFT JOIN packages p ON b.package_id = p.id 
LEFT JOIN users u ON b.user_id = u.id 
LEFT JOIN vehicles v ON b.vehicle_id = v.id
LEFT JOIN operators o ON v.operator_id = o.id
WHERE b.status IN ('Approved', 'Confirmed') 
  AND b.booking_date BETWEEN '$start_date' AND '$end_date'
  AND CURDATE() BETWEEN b.booking_date AND DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)
ORDER BY b.booking_date DESC";
$ongoing_trips_result = mysqli_query($conn, $ongoing_trips_query);
$ongoing_trips_count = mysqli_num_rows($ongoing_trips_result);

// ============================================================
// 4. ALL BOOKINGS (COMPREHENSIVE)
// ============================================================
$all_bookings_query = "SELECT 
    b.id as booking_id,
    b.booking_date,
    b.pickup_location,
    b.destination_location,
    b.calculated_distance,
    b.total_cost,
    b.status,
    b.number_of_days,
    p.title as package_name,
    u.full_name as customer_name,
    u.email as customer_email,
    v.id as vehicle_id,
    v.vehicle_name,
    v.price_per_km,
    v.capacity,
    v.driver_name,
    v.driver_phone,
    v.status as vehicle_status,
    o.id as operator_id,
    o.name as operator_name,
    o.phone as operator_phone,
    o.email as operator_email
FROM bookings b 
LEFT JOIN packages p ON b.package_id = p.id 
LEFT JOIN users u ON b.user_id = u.id 
LEFT JOIN vehicles v ON b.vehicle_id = v.id
LEFT JOIN operators o ON v.operator_id = o.id
WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
ORDER BY b.booking_date DESC";
$all_bookings_result = mysqli_query($conn, $all_bookings_query);

// ============================================================
// 5. TRAVELLERS
// ============================================================
$travellers_query = "SELECT u.id as user_id, u.full_name, u.email, 
                      COUNT(b.id) as total_trips, 
                      COALESCE(SUM(b.total_cost), 0) as total_spent, 
                      MAX(b.booking_date) as last_trip
                     FROM users u
                     LEFT JOIN bookings b ON u.id = b.user_id 
                        AND b.status IN ('Approved', 'Completed', 'Confirmed') 
                        AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                     GROUP BY u.id 
                     HAVING total_trips > 0
                     ORDER BY total_trips DESC";
$travellers_result = mysqli_query($conn, $travellers_query);

// ============================================================
// 6. UNUSED PACKAGES
// ============================================================
$unused_packages_query = "SELECT p.id, p.title, p.price 
                          FROM packages p 
                          LEFT JOIN bookings b ON p.id = b.package_id 
                          WHERE b.id IS NULL AND p.status != 'Deleted'";
$unused_packages_result = mysqli_query($conn, $unused_packages_query);

// ============================================================
// 7. DELETED PACKAGES & BOOKINGS
// ============================================================
$deleted_packages_query = "SELECT id, title, price FROM packages WHERE status = 'Deleted'";
$deleted_packages_result = mysqli_query($conn, $deleted_packages_query);

$deleted_bookings_query = "SELECT b.id, b.booking_date, u.full_name AS user_name, p.title AS pkg_name, b.total_cost 
                           FROM bookings b
                           INNER JOIN users u ON b.user_id = u.id 
                           LEFT JOIN packages p ON b.package_id = p.id 
                           WHERE b.status = 'Deleted'
                           ORDER BY b.booking_date DESC";
$deleted_bookings_result = mysqli_query($conn, $deleted_bookings_query);

// ============================================================
// 8. KPI METRICS (UPDATED WITH REAL-TIME LOGIC)
// ============================================================
$daily_q = "SELECT COALESCE(SUM(b.total_cost), 0) as daily_profit FROM bookings b WHERE DATE(b.booking_date) = CURDATE() AND b.status IN ('Approved', 'Completed', 'Confirmed')";
$daily_res = mysqli_fetch_assoc(mysqli_query($conn, $daily_q));

$monthly_q = "SELECT COALESCE(SUM(b.total_cost), 0) as monthly_profit FROM bookings b WHERE MONTH(b.booking_date) = MONTH(CURDATE()) AND YEAR(b.booking_date) = YEAR(CURDATE()) AND b.status IN ('Approved', 'Completed', 'Confirmed')";
$monthly_res = mysqli_fetch_assoc(mysqli_query($conn, $monthly_q));

$yearly_q = "SELECT COALESCE(SUM(b.total_cost), 0) as yearly_profit FROM bookings b WHERE YEAR(b.booking_date) = YEAR(CURDATE()) AND b.status IN ('Approved', 'Completed', 'Confirmed')";
$yearly_res = mysqli_fetch_assoc(mysqli_query($conn, $yearly_q));

// Total Trips (Approved/Confirmed and Completed by real-time logic)
$total_trips_q = "SELECT COUNT(*) as total FROM bookings b 
                  WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                  AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                  AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$total_trips_res = mysqli_fetch_assoc(mysqli_query($conn, $total_trips_q));
$total_trips = $total_trips_res['total'] ?? 0;

$total_revenue_q = "SELECT COALESCE(SUM(b.total_cost), 0) as total FROM bookings b 
                    WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                    AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$total_revenue_res = mysqli_fetch_assoc(mysqli_query($conn, $total_revenue_q));
$total_revenue = $total_revenue_res['total'] ?? 0;

$unique_travellers_q = "SELECT COUNT(DISTINCT b.user_id) as count FROM bookings b 
                        WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                        AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                        AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$unique_travellers_res = mysqli_fetch_assoc(mysqli_query($conn, $unique_travellers_q));
$unique_travellers = $unique_travellers_res['count'] ?? 0;

$avg_trip_cost_q = "SELECT COALESCE(AVG(b.total_cost), 0) as avg FROM bookings b 
                    WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                    AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$avg_trip_cost_res = mysqli_fetch_assoc(mysqli_query($conn, $avg_trip_cost_q));
$avg_trip_cost = $avg_trip_cost_res['avg'] ?? 0;

// Completion Rate (based on real-time completed vs total)
$completion_rate_q = "SELECT 
    (SELECT COUNT(*) FROM bookings b WHERE b.status IN ('Approved', 'Confirmed', 'Completed') AND b.booking_date BETWEEN '$start_date' AND '$end_date' AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)) as completed,
    (SELECT COUNT(*) FROM bookings b WHERE b.booking_date BETWEEN '$start_date' AND '$end_date' AND b.status IN ('Approved', 'Confirmed', 'Completed')) as total
";
$completion_rate_res = mysqli_fetch_assoc(mysqli_query($conn, $completion_rate_q));
$completed_count = $completion_rate_res['completed'] ?? 0;
$total_bookings = $completion_rate_res['total'] ?? 1;
$completion_rate = ($total_bookings > 0) ? round(($completed_count / $total_bookings) * 100, 1) : 0;

$total_packages_q = "SELECT COUNT(*) as total FROM packages";
$total_packages_res = mysqli_fetch_assoc(mysqli_query($conn, $total_packages_q));
$total_packages = $total_packages_res['total'] ?? 0;

$total_users_q = "SELECT COUNT(*) as total FROM users";
$total_users_res = mysqli_fetch_assoc(mysqli_query($conn, $total_users_q));
$total_users = $total_users_res['total'] ?? 0;

$vehicles_used_q = "SELECT COUNT(DISTINCT b.vehicle_id) as used FROM bookings b 
                    WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                    AND b.booking_date BETWEEN '$start_date' AND '$end_date' AND b.vehicle_id > 0";
$vehicles_used_res = mysqli_fetch_assoc(mysqli_query($conn, $vehicles_used_q));
$vehicles_used = $vehicles_used_res['used'] ?? 0;

$total_vehicles_q = "SELECT COUNT(*) as total FROM vehicles";
$total_vehicles_res = mysqli_fetch_assoc(mysqli_query($conn, $total_vehicles_q));
$total_vehicles = $total_vehicles_res['total'] ?? 0;

$total_distance_q = "SELECT COALESCE(SUM(calculated_distance), 0) as dist FROM bookings b 
                    WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                    AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$total_distance_res = mysqli_fetch_assoc(mysqli_query($conn, $total_distance_q));
$total_distance = $total_distance_res['dist'] ?? 0;

$aov_q = "SELECT COALESCE(AVG(b.total_cost), 0) as aov FROM bookings b 
          WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
          AND b.booking_date BETWEEN '$start_date' AND '$end_date'
          AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$aov_res = mysqli_fetch_assoc(mysqli_query($conn, $aov_q));
$aov = $aov_res['aov'] ?? 0;

$rpu_q = "SELECT COALESCE(SUM(b.total_cost) / NULLIF(COUNT(DISTINCT b.user_id), 0), 0) as rpu 
          FROM bookings b 
          WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
          AND b.booking_date BETWEEN '$start_date' AND '$end_date'
          AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)";
$rpu_res = mysqli_fetch_assoc(mysqli_query($conn, $rpu_q));
$rpu = $rpu_res['rpu'] ?? 0;

// ============================================================
// 9. CHART DATA
// ============================================================
$trend_query = "SELECT DATE_FORMAT(b.booking_date, '%b') as month, COALESCE(SUM(b.total_cost), 0) as revenue 
                FROM bookings b 
                WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                  AND b.status IN ('Approved', 'Confirmed', 'Completed') 
                  AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)
                GROUP BY YEAR(b.booking_date), MONTH(b.booking_date) 
                ORDER BY MIN(b.booking_date) ASC";
$trend_result = mysqli_query($conn, $trend_query);
$trend_labels = [];
$trend_values = [];
while ($row = mysqli_fetch_assoc($trend_result)) {
    $trend_labels[] = $row['month'];
    $trend_values[] = (float) $row['revenue'];
}

$package_usage_q = "SELECT p.title, COUNT(b.id) as usage_count 
                     FROM packages p 
                     LEFT JOIN bookings b ON p.id = b.package_id 
                        AND b.status IN ('Approved', 'Confirmed', 'Completed') 
                        AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                     WHERE p.status != 'Deleted' 
                     GROUP BY p.id 
                     ORDER BY usage_count DESC 
                     LIMIT 5";
$package_usage_result = mysqli_query($conn, $package_usage_q);
$package_labels = [];
$package_values = [];
while ($row = mysqli_fetch_assoc($package_usage_result)) {
    $package_labels[] = $row['title'];
    $package_values[] = (int) $row['usage_count'];
}

$status_dist_q = "SELECT b.status, COUNT(*) as count FROM bookings b WHERE b.booking_date BETWEEN '$start_date' AND '$end_date' GROUP BY b.status";
$status_dist_result = mysqli_query($conn, $status_dist_q);
$status_labels = [];
$status_values = [];
while ($row = mysqli_fetch_assoc($status_dist_result)) {
    $status_labels[] = $row['status'];
    $status_values[] = (int) $row['count'];
}

$vehicle_usage_q = "SELECT v.vehicle_name, COUNT(b.id) as trips 
                    FROM vehicles v 
                    LEFT JOIN bookings b ON v.id = b.vehicle_id 
                        AND b.status IN ('Approved', 'Confirmed', 'Completed') 
                        AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                    WHERE v.status != 'Inactive'
                    GROUP BY v.id 
                    ORDER BY trips DESC 
                    LIMIT 8";
$vehicle_usage_result = mysqli_query($conn, $vehicle_usage_q);
$vehicle_labels = [];
$vehicle_usage_values = [];
while ($row = mysqli_fetch_assoc($vehicle_usage_result)) {
    $vehicle_labels[] = $row['vehicle_name'];
    $vehicle_usage_values[] = (int) $row['trips'];
}

$top_travellers_q = "SELECT u.id, u.full_name, u.email, COUNT(b.id) as trip_count, COALESCE(SUM(b.total_cost), 0) as total_spent
                     FROM users u 
                     INNER JOIN bookings b ON u.id = b.user_id 
                     WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                       AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                       AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)
                     GROUP BY u.id 
                     ORDER BY trip_count DESC 
                     LIMIT 10";
$top_travellers_result = mysqli_query($conn, $top_travellers_q);

$reg_column = 'created_at';
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'created_at'");
if (mysqli_num_rows($check_col) == 0) {
    $check_col2 = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'registration_date'");
    if (mysqli_num_rows($check_col2) > 0) {
        $reg_column = 'registration_date';
    } else {
        $reg_column = null;
    }
}
if ($reg_column) {
    $user_reg_q = "SELECT DATE_FORMAT($reg_column, '%b') as month, COUNT(*) as count 
                   FROM users 
                   WHERE $reg_column >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                   GROUP BY YEAR($reg_column), MONTH($reg_column) 
                   ORDER BY MIN($reg_column) ASC";
    $user_reg_result = mysqli_query($conn, $user_reg_q);
    $user_reg_labels = [];
    $user_reg_values = [];
    while ($row = mysqli_fetch_assoc($user_reg_result)) {
        $user_reg_labels[] = $row['month'];
        $user_reg_values[] = (int) $row['count'];
    }
} else {
    $user_reg_labels = [];
    $user_reg_values = [];
}

$day_of_week_q = "SELECT DAYNAME(b.booking_date) as day_name, COUNT(*) as count 
                  FROM bookings b 
                  WHERE b.status IN ('Approved', 'Confirmed', 'Completed') 
                    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                  GROUP BY DAYNAME(b.booking_date) 
                  ORDER BY FIELD(DAYNAME(b.booking_date), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$day_of_week_result = mysqli_query($conn, $day_of_week_q);
$day_labels = [];
$day_values = [];
while ($row = mysqli_fetch_assoc($day_of_week_result)) {
    $day_labels[] = $row['day_name'];
    $day_values[] = (int) $row['count'];
}

$top_earning_q = "SELECT p.title, COALESCE(SUM(b.total_cost), 0) as total_revenue, COUNT(b.id) as bookings
                  FROM packages p 
                  LEFT JOIN bookings b ON p.id = b.package_id AND b.status IN ('Approved', 'Confirmed', 'Completed') AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                  WHERE p.status != 'Deleted' 
                  GROUP BY p.id 
                  ORDER BY total_revenue DESC 
                  LIMIT 5";
$top_earning_result = mysqli_query($conn, $top_earning_q);

// ============================================================
// 10. PERFORMANCE QUERY
// ============================================================
$performance_q = "SELECT p.title, 
                  COUNT(b.id) as bookings, 
                  COALESCE(SUM(b.total_cost), 0) as revenue,
                  COALESCE(AVG(b.total_cost), 0) as avg_price
                  FROM packages p 
                  LEFT JOIN bookings b ON p.id = b.package_id AND b.status IN ('Approved', 'Confirmed', 'Completed') AND b.booking_date BETWEEN '$start_date' AND '$end_date'
                  WHERE p.status != 'Deleted'
                  GROUP BY p.id 
                  ORDER BY bookings DESC, revenue DESC 
                  LIMIT 10";
$performance_result = mysqli_query($conn, $performance_q);

// ============================================================
// 11. VEHICLE PERFORMANCE REPORT
// ============================================================
$vehicle_performance_q = "SELECT 
    v.id as vehicle_id,
    v.vehicle_name,
    v.price_per_km,
    v.capacity,
    v.status as vehicle_status,
    v.driver_name,
    v.driver_phone,
    COUNT(b.id) as total_trips,
    COALESCE(SUM(b.total_cost), 0) as total_revenue,
    COALESCE(AVG(b.total_cost), 0) as avg_trip_cost,
    COALESCE(SUM(b.calculated_distance), 0) as total_distance,
    o.name as operator_name
FROM vehicles v
LEFT JOIN bookings b ON v.id = b.vehicle_id 
    AND b.status IN ('Approved', 'Confirmed', 'Completed')
    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
LEFT JOIN operators o ON v.operator_id = o.id
WHERE v.status != 'Inactive'
GROUP BY v.id
ORDER BY total_trips DESC, total_revenue DESC";
$vehicle_performance_result = mysqli_query($conn, $vehicle_performance_q);
$vehicle_performance_count = mysqli_num_rows($vehicle_performance_result);

// ============================================================
// 12. DRIVER PERFORMANCE REPORT
// ============================================================
$driver_performance_q = "SELECT 
    d.id as driver_id,
    d.name as driver_name,
    d.phone as driver_phone,
    COUNT(b.id) as total_trips,
    COALESCE(SUM(b.total_cost), 0) as total_revenue,
    COALESCE(AVG(b.total_cost), 0) as avg_trip_cost,
    COUNT(DISTINCT v.id) as vehicles_driven,
    COALESCE(SUM(b.calculated_distance), 0) as total_distance,
    v.vehicle_name as primary_vehicle
FROM drivers d
LEFT JOIN vehicles v ON d.id = v.driver_id
LEFT JOIN bookings b ON v.id = b.vehicle_id 
    AND b.status IN ('Approved', 'Confirmed', 'Completed')
    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
WHERE d.status != 'inactive'
GROUP BY d.id
ORDER BY total_trips DESC, total_revenue DESC";
$driver_performance_result = mysqli_query($conn, $driver_performance_q);
$driver_performance_count = mysqli_num_rows($driver_performance_result);

// ============================================================
// 13. VEHICLE STATUS SUMMARY
// ============================================================
$vehicle_status_q = "SELECT 
    status,
    COUNT(*) as count,
    COUNT(CASE WHEN driver_id IS NOT NULL THEN 1 END) as with_driver,
    COUNT(CASE WHEN driver_id IS NULL THEN 1 END) as without_driver
FROM vehicles
WHERE status != 'Inactive'
GROUP BY status";
$vehicle_status_result = mysqli_query($conn, $vehicle_status_q);

// ============================================================
// 14. MAINTENANCE ALERTS
// ============================================================
$maintenance_alerts_q = "SELECT 
    id,
    vehicle_name,
    next_maintenance,
    insurance_expiry,
    registration_expiry,
    status,
    driver_name,
    operator_id,
    DATEDIFF(next_maintenance, CURDATE()) as days_to_maintenance,
    DATEDIFF(insurance_expiry, CURDATE()) as days_to_insurance,
    DATEDIFF(registration_expiry, CURDATE()) as days_to_registration
FROM vehicles
WHERE (next_maintenance IS NOT NULL AND next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
   OR (insurance_expiry IS NOT NULL AND insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
   OR (registration_expiry IS NOT NULL AND registration_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
ORDER BY LEAST(
    COALESCE(DATEDIFF(next_maintenance, CURDATE()), 999),
    COALESCE(DATEDIFF(insurance_expiry, CURDATE()), 999),
    COALESCE(DATEDIFF(registration_expiry, CURDATE()), 999)
) ASC";
$maintenance_alerts_result = mysqli_query($conn, $maintenance_alerts_q);
$maintenance_alerts_count = mysqli_num_rows($maintenance_alerts_result);

// ============================================================
// 15. OPERATOR VEHICLE DISTRIBUTION
// ============================================================
$operator_dist_q = "SELECT 
    o.id as operator_id,
    o.name as operator_name,
    o.phone as operator_phone,
    COUNT(v.id) as vehicle_count,
    COUNT(CASE WHEN v.status = 'Available' THEN 1 END) as available_count,
    COUNT(CASE WHEN v.status = 'Booked' THEN 1 END) as booked_count,
    COUNT(CASE WHEN v.status = 'Maintenance' THEN 1 END) as maintenance_count
FROM operators o
LEFT JOIN vehicles v ON o.id = v.operator_id
WHERE v.status != 'Inactive' OR v.status IS NULL
GROUP BY o.id
ORDER BY vehicle_count DESC";
$operator_dist_result = mysqli_query($conn, $operator_dist_q);

// ============================================================
// 16. VEHICLE REVENUE CHART DATA
// ============================================================
$vehicle_revenue_q = "SELECT 
    v.vehicle_name,
    COALESCE(SUM(b.total_cost), 0) as revenue,
    COUNT(b.id) as trips
FROM vehicles v
LEFT JOIN bookings b ON v.id = b.vehicle_id 
    AND b.status IN ('Approved', 'Confirmed', 'Completed')
    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
GROUP BY v.id
HAVING revenue > 0 OR trips > 0
ORDER BY revenue DESC
LIMIT 8";
$vehicle_revenue_result = mysqli_query($conn, $vehicle_revenue_q);
$vehicle_revenue_labels = [];
$vehicle_revenue_values = [];
while ($row = mysqli_fetch_assoc($vehicle_revenue_result)) {
    $vehicle_revenue_labels[] = $row['vehicle_name'];
    $vehicle_revenue_values[] = (float) $row['revenue'];
}

// ============================================================
// 17. DRIVER CHART DATA
// ============================================================
$driver_chart_q = "SELECT 
    d.name as driver_name,
    COUNT(b.id) as trips
FROM drivers d
LEFT JOIN vehicles v ON d.id = v.driver_id
LEFT JOIN bookings b ON v.id = b.vehicle_id 
    AND b.status IN ('Approved', 'Confirmed', 'Completed')
    AND b.booking_date BETWEEN '$start_date' AND '$end_date'
GROUP BY d.id
ORDER BY trips DESC
LIMIT 8";
$driver_chart_result = mysqli_query($conn, $driver_chart_q);
$driver_chart_labels = [];
$driver_chart_values = [];
while ($row = mysqli_fetch_assoc($driver_chart_result)) {
    $driver_chart_labels[] = $row['driver_name'];
    $driver_chart_values[] = (int) $row['trips'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Intelligence Reports | Full Details</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <!-- html2canvas + jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT is DEFAULT, DARK toggle
           ============================================================ */
        :root {
            --bg-primary: #f0f4f8;
            --bg-secondary: #e2e8f0;
            --bg-card: rgba(255, 255, 255, 0.92);
            --bg-input: rgba(255, 255, 255, 0.70);
            --text-primary: #000000;
            --text-secondary: #1a1a2e;
            --text-muted: #2d3748;
            --text-accent: #0ea5e9;
            --text-success: #10b981;
            --text-warning: #f59e0b;
            --text-danger: #ef4444;
            --border-card: rgba(0, 0, 0, 0.06);
            --border-input: rgba(0, 0, 0, 0.08);
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.06);
            --nav-bg: rgba(255, 255, 255, 0.85);
            --nav-border: rgba(0, 0, 0, 0.04);
            --blob-1: rgba(14, 165, 233, 0.08);
            --blob-2: rgba(16, 185, 129, 0.06);
            --blob-3: rgba(251, 146, 60, 0.04);
            --overlay: rgba(255, 255, 255, 0.45);
            --transition: 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            --gradient-main: linear-gradient(135deg, #0ea5e9, #10b981);
            --table-header-bg: rgba(0, 0, 0, 0.04);
            --table-row-hover: rgba(0, 0, 0, 0.02);
            --admin-badge-bg: rgba(14, 165, 233, 0.12);
            --admin-badge-color: #0ea5e9;
        }

        [data-theme="dark"] {
            --bg-primary: #0b1120;
            --bg-secondary: #151f33;
            --bg-card: rgba(18, 30, 50, 0.75);
            --bg-input: rgba(0, 0, 0, 0.35);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-accent: #38bdf8;
            --text-success: #34d399;
            --text-warning: #fbbf24;
            --text-danger: #f87171;
            --border-card: rgba(56, 189, 248, 0.12);
            --border-input: rgba(255, 255, 255, 0.06);
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.6);
            --nav-bg: rgba(11, 17, 32, 0.7);
            --nav-border: rgba(56, 189, 248, 0.06);
            --blob-1: rgba(56, 189, 248, 0.15);
            --blob-2: rgba(52, 211, 153, 0.10);
            --blob-3: rgba(251, 146, 60, 0.08);
            --overlay: rgba(5, 10, 20, 0.60);
            --gradient-main: linear-gradient(135deg, #38bdf8, #34d399);
            --table-header-bg: rgba(255, 255, 255, 0.03);
            --table-row-hover: rgba(255, 255, 255, 0.02);
            --admin-badge-bg: rgba(56, 189, 248, 0.15);
            --admin-badge-color: #38bdf8;
        }

        /* Reset & Base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--bg-primary);
            overflow-x: hidden;
            transition: background var(--transition), color var(--transition);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.6;
        }

        /* Video background */
        .video-background {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -2;
            overflow: hidden;
            background: var(--bg-primary);
        }
        .video-background video {
            position: absolute;
            top: 50%; left: 50%;
            min-width: 100%; min-height: 100%;
            width: auto; height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            pointer-events: none;
        }
        .video-background .fallback {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            z-index: -1;
        }
        .video-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            background: var(--overlay);
            backdrop-filter: blur(2px);
            pointer-events: none;
            transition: background var(--transition);
        }

        /* Blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: -1;
            animation: blobFloat 16s ease-in-out infinite alternate;
            transition: background var(--transition);
        }
        .blob-1 { width: 600px; height: 600px; background: var(--blob-1); top: -200px; left: -200px; }
        .blob-2 { width: 500px; height: 500px; background: var(--blob-2); bottom: -150px; right: -150px; animation-delay: 5s; }
        .blob-3 { width: 400px; height: 400px; background: var(--blob-3); top: 50%; left: 70%; animation-delay: 9s; }
        @keyframes blobFloat {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(60px, -50px) scale(1.15); }
            66% { transform: translate(-40px, 60px) scale(0.9); }
            100% { transform: translate(50px, -40px) scale(1.05); }
        }

        /* Particles */
        .particles {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: var(--text-accent);
            border-radius: 50%;
            opacity: 0.15;
            animation: floatParticle linear infinite;
            transition: background var(--transition);
        }
        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg) scale(0); opacity: 0; }
            10% { opacity: 0.15; }
            90% { opacity: 0.15; }
            100% { transform: translateY(-10vh) rotate(720deg) scale(1); opacity: 0; }
        }

        /* Loader */
        .loader {
            position: fixed; inset: 0;
            background: var(--bg-primary);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            z-index: 9999;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }
        .loader.hidden { opacity: 0; visibility: hidden; }
        .loader-ring {
            width: 80px; height: 80px;
            border-radius: 50%;
            border: 4px solid transparent;
            border-top-color: var(--text-accent);
            border-bottom-color: var(--text-success);
            animation: spin 1s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite;
            position: relative;
        }
        .loader-ring::after {
            content: '';
            position: absolute; inset: 10px;
            border-radius: 50%;
            border: 4px solid transparent;
            border-left-color: #f59e0b;
            border-right-color: #8b5cf6;
            animation: spin 0.7s ease-in-out infinite reverse;
        }
        .loader-text {
            margin-top: 30px;
            font-size: 15px; font-weight: 600;
            letter-spacing: 6px; text-transform: uppercase;
            color: var(--text-muted);
            animation: pulseText 1.5s ease-in-out infinite;
            transition: color var(--transition);
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulseText {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        /* Navbar */
        .navbar {
            position: sticky; top: 0; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 48px;
            background: var(--nav-bg);
            backdrop-filter: blur(28px) saturate(1.8);
            border-bottom: 1px solid var(--nav-border);
            box-shadow: 0 4px 40px rgba(0,0,0,0.15);
            animation: fadeInDown 0.8s ease forwards;
            transition: background var(--transition), border-color var(--transition);
            flex-wrap: wrap;
            gap: 12px;
        }
        .logo {
            font-size: 26px;
            font-weight: 900;
            background: var(--gradient-main);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex; align-items: center; gap: 12px;
        }
        .logo i {
            -webkit-text-fill-color: initial;
            color: var(--text-accent);
            filter: drop-shadow(0 0 15px rgba(14,165,233,0.2));
            transition: color var(--transition);
        }
        .nav-group {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-links {
            display: flex; align-items: center; gap: 4px;
        }
        .nav-links a {
            padding: 8px 18px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 15px;
            border-radius: 12px;
            transition: all var(--transition);
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute; bottom: 2px; left: 50%;
            width: 0; height: 2px;
            background: var(--text-accent);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .nav-links a:hover::after { width: 40%; }
        .nav-links a.active::after { width: 60%; }
        .nav-links a:hover { color: var(--text-primary); }
        .nav-links a.active {
            color: var(--text-primary);
            background: rgba(14, 165, 233, 0.08);
        }
        .nav-links a i { margin-right: 6px; font-size: 13px; }

        .navbar-clock {
            font-family: monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-input);
            padding: 6px 14px;
            border-radius: 30px;
            border: 1px solid var(--border-card);
            transition: all var(--transition);
            white-space: nowrap;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--admin-badge-bg);
            color: var(--admin-badge-color);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid var(--border-card);
            transition: all var(--transition);
            white-space: nowrap;
        }
        .admin-badge i { font-size: 14px; }
        .admin-badge:hover {
            transform: scale(1.02);
            border-color: var(--admin-badge-color);
        }

        .theme-toggle-inline {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 16px;
            border-left: 1px solid var(--border-card);
            transition: border-color var(--transition);
        }
        .theme-toggle-inline .toggle-icon {
            color: var(--text-muted);
            font-size: 18px;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        .theme-toggle-inline .toggle-icon.spin {
            transform: rotate(360deg) scale(1.2);
            color: var(--text-accent);
        }
        .theme-toggle-inline .toggle-switch {
            position: relative;
            width: 54px;
            height: 28px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .theme-toggle-inline .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .theme-toggle-inline .toggle-slider {
            position: absolute; inset: 0;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 30px;
            transition: all var(--transition);
        }
        .theme-toggle-inline .toggle-slider::before {
            content: "\f185";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 3px; bottom: 3px;
            width: 20px; height: 20px;
            background: var(--text-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: #fff;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .theme-toggle-inline .toggle-switch input:checked + .toggle-slider::before {
            content: "\f186";
            transform: translateX(26px) rotate(360deg);
            background: var(--text-success);
        }
        .theme-toggle-inline .toggle-switch input:checked + .toggle-slider {
            border-color: var(--text-success);
        }

        .btn-logout {
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.2);
            transition: all 0.3s ease;
        }
        .btn-logout:hover {
            background: rgba(239,68,68,0.25);
            transform: translateY(-2px);
        }

        .vehicle-manager-btn {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff !important;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139,92,246,0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .vehicle-manager-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139,92,246,0.4);
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            color: #fff !important;
        }

        .main-wrapper {
            padding: 24px 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            padding: 18px 28px;
            border-radius: 24px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            animation: fadeInDown 0.8s ease forwards;
            transition: all var(--transition);
        }
        .app-header .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .app-header .brand .logo-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--gradient-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #fff;
            box-shadow: 0 8px 24px rgba(14,165,233,0.25);
            animation: pulseGlow 3s ease-in-out infinite;
            transition: background var(--transition);
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 8px 24px rgba(14,165,233,0.25); }
            50% { box-shadow: 0 8px 40px rgba(14,165,233,0.5); }
        }
        .app-header .brand h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            transition: color var(--transition);
        }
        .app-header .brand span {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            display: block;
            transition: color var(--transition);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .header-actions .btn {
            padding: 10px 22px;
            border-radius: 14px;
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        .header-actions .btn .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: rippleAnim 0.8s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .header-actions .btn::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0; transition: opacity 0.3s ease;
        }
        .header-actions .btn:hover::before { opacity: 1; }
        .header-actions .btn:hover { transform: translateY(-2px); }
        .btn-ghost {
            background: rgba(255,255,255,0.04);
            color: var(--text-secondary);
            border: 1px solid var(--border-card);
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        .btn-primary {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(14,165,233,0.4);
        }

        .welcome-section {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            backdrop-filter: blur(12px);
            transition: all var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-section .welcome-text h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            transition: color var(--transition);
        }
        .welcome-section .welcome-text h2 i {
            color: var(--text-warning);
            margin-right: 10px;
        }
        .welcome-section .welcome-text p {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-top: 2px;
            transition: color var(--transition);
        }
        .welcome-section .welcome-text p i {
            color: var(--text-success);
            margin-right: 6px;
        }
        .welcome-section .welcome-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .welcome-section .welcome-stats .stat-item {
            text-align: center;
        }
        .welcome-section .welcome-stats .stat-item .num {
            font-size: 20px;
            font-weight: 900;
            color: var(--text-primary);
            transition: color var(--transition);
        }
        .welcome-section .welcome-stats .stat-item .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            transition: color var(--transition);
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            background: var(--bg-card);
            padding: 16px 24px;
            border-radius: 20px;
            border: 1px solid var(--border-card);
            margin-bottom: 24px;
            backdrop-filter: blur(12px);
            transition: all var(--transition);
        }
        .filter-bar label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-bar input[type="date"] {
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 14px;
            transition: all var(--transition);
            outline: none;
        }
        .filter-bar input[type="date"]:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .filter-bar .btn {
            padding: 8px 22px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 12px rgba(14,165,233,0.2);
        }
        .filter-bar .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(14,165,233,0.3);
        }
        .filter-bar .btn-reset {
            background: rgba(255,255,255,0.04);
            color: var(--text-muted);
            border: 1px solid var(--border-card);
            box-shadow: none;
        }
        .filter-bar .btn-reset:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .kpi-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 18px 20px;
            backdrop-filter: blur(12px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease forwards;
            box-shadow: var(--shadow-card);
        }
        .kpi-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--gradient-main);
            opacity: 0.6;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            border-color: rgba(14,165,233,0.20);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }
        .kpi-card .kpi-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color var(--transition);
        }
        .kpi-card .kpi-value {
            font-size: 28px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            line-height: 1.2;
            transition: color var(--transition);
        }
        .kpi-card .kpi-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 500;
            transition: color var(--transition);
        }
        .kpi-card .kpi-icon {
            position: absolute; top: 14px; right: 14px;
            font-size: 24px;
            opacity: 0.08;
            color: var(--text-accent);
            transition: all 0.4s ease;
        }
        .kpi-card:hover .kpi-icon {
            opacity: 0.15;
            transform: scale(1.1) rotate(5deg);
        }
        .kpi-card.green .kpi-icon { color: var(--text-success); }
        .kpi-card.purple .kpi-icon { color: #8b5cf6; }
        .kpi-card.orange .kpi-icon { color: #f59e0b; }
        .kpi-card.rose .kpi-icon { color: #f43f5e; }
        .kpi-card.blue .kpi-icon { color: var(--text-accent); }
        .kpi-card.teal .kpi-icon { color: #14b8a6; }
        .kpi-card.indigo .kpi-icon { color: #6366f1; }
        .kpi-card.amber .kpi-icon { color: #fbbf24; }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        .chart-box {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 18px 20px 14px;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease forwards;
            box-shadow: var(--shadow-card);
        }
        .chart-box:hover {
            border-color: rgba(14,165,233,0.15);
            transform: translateY(-2px);
        }
        .chart-box .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .chart-box .chart-header h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color var(--transition);
        }
        .chart-box .chart-header h4 i {
            color: var(--text-accent);
        }
        .chart-box .chart-header span {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            transition: color var(--transition);
        }
        .chart-box canvas {
            max-height: 180px;
            max-width: 100%;
        }

        .tabs-wrapper {
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .tabs {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid var(--border-card);
            padding-bottom: 0;
            min-width: max-content;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 12px 24px;
            cursor: pointer;
            border-radius: 12px 12px 0 0;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn i { font-size: 15px; }
        .tab-btn:hover {
            color: var(--text-primary);
            background: rgba(255,255,255,0.03);
        }
        .tab-btn.active {
            color: var(--text-primary);
            background: rgba(14,165,233,0.08);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute; bottom: -1px; left: 20%; right: 20%;
            height: 2px;
            background: var(--gradient-main);
            border-radius: 4px;
        }

        .report-section {
            display: none;
            animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .report-section.active { display: block; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 18px;
        }
        .section-toolbar .section-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color var(--transition);
        }
        .section-toolbar .section-title i {
            color: var(--text-accent);
            font-size: 22px;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar-actions .btn-sm {
            padding: 8px 18px;
            border-radius: 12px;
            border: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.04);
            color: var(--text-muted);
            border: 1px solid var(--border-card);
        }
        .toolbar-actions .btn-sm:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        .toolbar-actions .btn-sm.pdf {
            background: rgba(239,68,68,0.08);
            color: #f87171;
            border-color: rgba(239,68,68,0.12);
        }
        .toolbar-actions .btn-sm.pdf:hover {
            background: rgba(239,68,68,0.16);
        }
        .toolbar-actions .btn-sm.print {
            background: rgba(14,165,233,0.08);
            color: var(--text-accent);
            border-color: rgba(14,165,233,0.12);
        }
        .toolbar-actions .btn-sm.print:hover {
            background: rgba(14,165,233,0.16);
        }

        .table-container {
            background: rgba(0,0,0,0.1);
            border-radius: 18px;
            border: 1px solid var(--border-card);
            overflow-x: auto;
            padding: 4px 0;
            backdrop-filter: blur(8px);
            transition: all var(--transition);
        }
        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.dataTable thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
            background: var(--table-header-bg);
            transition: all var(--transition);
        }
        table.dataTable tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
            vertical-align: middle;
            transition: all var(--transition);
        }
        table.dataTable tbody tr:hover td {
            background: var(--table-row-hover);
        }

        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .status-badge.completed { background: rgba(14,165,233,0.12); color: var(--text-accent); }
        .status-badge.approved { background: rgba(16,185,129,0.12); color: var(--text-success); }
        .status-badge.confirmed { background: rgba(16,185,129,0.12); color: var(--text-success); }
        .status-badge.ongoing { background: rgba(251,191,36,0.20); color: #b45309; }
        [data-theme="dark"] .status-badge.ongoing { background: rgba(251,191,36,0.25); color: #fbbf24; }
        .status-badge.inprogress { background: rgba(251,191,36,0.20); color: #b45309; }
        [data-theme="dark"] .status-badge.inprogress { background: rgba(251,191,36,0.25); color: #fbbf24; }
        .status-badge.active { background: rgba(251,191,36,0.20); color: #b45309; }
        [data-theme="dark"] .status-badge.active { background: rgba(251,191,36,0.25); color: #fbbf24; }
        .status-badge.deleted { background: rgba(239,68,68,0.12); color: #f87171; }
        .status-badge.pending { background: rgba(245,158,11,0.12); color: #f59e0b; }
        .status-badge.cancelled { background: rgba(139,92,246,0.12); color: #8b5cf6; }

        .vehicle-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .vehicle-status.Available { background: rgba(16,185,129,0.12); color: #10b981; }
        .vehicle-status.OnTrip { background: rgba(245,158,11,0.12); color: #f59e0b; }
        .vehicle-status.Maintenance { background: rgba(239,68,68,0.12); color: #ef4444; }
        .vehicle-status.Inactive { background: rgba(148,163,184,0.12); color: #94a3b8; }

        .text-muted { color: var(--text-muted); }
        .text-success { color: var(--text-success); }
        .text-danger { color: var(--text-danger); }
        .text-warning { color: var(--text-warning); }
        .text-primary { color: var(--text-accent); }
        .fw-bold { font-weight: 700; }
        .text-small { font-size: 11px; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 32px;
            opacity: 0.2;
            margin-bottom: 10px;
            display: block;
        }
        .empty-state p { font-size: 15px; font-weight: 500; }

        .profit-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .profit-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid var(--border-card);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-card);
        }
        .profit-card:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(14,165,233,0.15);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }
        .profit-card .label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color var(--transition);
        }
        .profit-card .value {
            font-size: 26px;
            font-weight: 900;
            color: var(--text-primary);
            margin-top: 2px;
            transition: color var(--transition);
        }

        .footer-bar {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-card);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
            transition: all var(--transition);
        }

        .scroll-top {
            position: fixed;
            bottom: 30px; right: 30px;
            width: 50px; height: 50px;
            border-radius: 50%;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-card);
            color: var(--text-accent);
            font-size: 22px;
            display: flex;
            align-items: center; justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            z-index: 999;
        }
        .scroll-top.visible { opacity: 1; visibility: visible; }
        .scroll-top:hover {
            transform: translateY(-6px) scale(1.1);
            border-color: var(--text-accent);
            box-shadow: 0 15px 40px rgba(14,165,233,0.2);
        }

        .cursor-dot {
            position: fixed;
            width: 8px; height: 8px;
            background: var(--text-accent);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease, background 0.3s;
            transform: translate(-50%, -50%);
            mix-blend-mode: difference;
            opacity: 0.7;
        }
        .cursor-ring {
            position: fixed;
            width: 40px; height: 40px;
            border: 2px solid rgba(14,165,233,0.3);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transition: width 0.3s, height 0.3s, border-color 0.3s, transform 0.15s ease;
            transform: translate(-50%, -50%);
            mix-blend-mode: difference;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 992px) {
            .charts-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .main-wrapper { padding: 16px; }
            .app-header {
                padding: 14px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .app-header .brand h1 { font-size: 20px; }
            .kpi-card .kpi-value { font-size: 22px; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .kpi-card { padding: 14px 16px; }
            .kpi-card .kpi-icon { font-size: 20px; top: 12px; right: 12px; }
            .section-toolbar { flex-direction: column; align-items: stretch; }
            .toolbar-actions { justify-content: flex-start; }
            .tab-btn { padding: 10px 16px; font-size: 13px; }
            .profit-summary { grid-template-columns: 1fr; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .header-actions .btn { padding: 8px 16px; font-size: 13px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar label { justify-content: space-between; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .welcome-section .welcome-stats { width: 100%; justify-content: space-around; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .kpi-card .kpi-value { font-size: 20px; }
            .profit-card .value { font-size: 22px; }
            .navbar-clock { font-size: 11px; padding: 4px 8px; }
        }

        @media print {
            body { background: #fff !important; color: #111 !important; padding: 10px 15px !important; font-size: 12px !important; }
            .app-header .brand h1 { -webkit-text-fill-color: #111 !important; color: #111 !important; background: none !important; font-size: 18px !important; }
            .app-header .brand span { -webkit-text-fill-color: #555 !important; color: #555 !important; font-size: 10px !important; }
            .app-header { margin-bottom: 12px !important; }
            .kpi-card { background: #f8fafc !important; border-color: #d1d5db !important; box-shadow: none !important; padding: 10px 12px !important; border-radius: 10px !important; }
            .kpi-card .kpi-value { font-size: 20px !important; color: #111 !important; }
            .kpi-card .kpi-label { color: #374151 !important; font-size: 9px !important; }
            .kpi-card .kpi-sub { font-size: 9px !important; color: #6b7280 !important; }
            .kpi-card .kpi-icon { opacity: 0.08 !important; font-size: 18px !important; top: 10px !important; right: 10px !important; }
            .kpi-grid { gap: 8px !important; margin-bottom: 12px !important; }
            .charts-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 10px !important; margin-bottom: 12px !important; }
            .chart-box { background: #f8fafc !important; border-color: #d1d5db !important; padding: 10px 12px !important; border-radius: 10px !important; }
            .chart-box .chart-header h4 { font-size: 12px !important; color: #111 !important; }
            .chart-box .chart-header span { font-size: 9px !important; color: #6b7280 !important; }
            .chart-box canvas { max-height: 120px !important; max-width: 100% !important; }
            .section-toolbar .section-title { font-size: 14px !important; color: #111 !important; }
            .section-toolbar .section-title i { display: none !important; }
            .section-toolbar { margin-bottom: 6px !important; }
            .table-container { background: #fff !important; border-color: #d1d5db !important; border-radius: 8px !important; margin-bottom: 8px !important; }
            thead th { color: #374151 !important; background: #f1f5f9 !important; border-color: #d1d5db !important; font-size: 9px !important; padding: 6px 10px !important; }
            tbody td { color: #1e293b !important; border-color: #e5e7eb !important; font-size: 10px !important; padding: 6px 10px !important; }
            tbody tr:hover td { background: transparent !important; }
            .tab-btn, .toolbar-actions, .header-actions .btn, .tabs-wrapper, .filter-bar, .welcome-section .welcome-stats { display: none !important; }
            .report-section { display: block !important; margin-bottom: 10px !important; }
            .report-section:not(.active) { display: block !important; }
            .status-badge { border: 1px solid #d1d5db !important; color: #111 !important; background: #f1f5f9 !important; font-size: 9px !important; padding: 2px 10px !important; }
            .text-muted { color: #6b7280 !important; }
            .text-success { color: #059669 !important; }
            .text-danger { color: #dc2626 !important; }
            .text-warning { color: #d97706 !important; }
            .text-primary { color: #2563eb !important; }
            .empty-state { padding: 20px !important; font-size: 12px !important; }
            .empty-state i { font-size: 24px !important; }
            .app-header .brand .logo-icon { display: none !important; }
            .app-header .brand { gap: 6px !important; }
            .profit-summary { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 8px !important; margin-bottom: 10px !important; }
            .profit-card { background: #f8fafc !important; border: 1px solid #d1d5db !important; border-radius: 8px !important; padding: 8px 12px !important; }
            .profit-card .label { font-size: 9px !important; color: #6b7280 !important; }
            .profit-card .value { font-size: 16px !important; color: #111 !important; }
            .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .navbar-clock { display: none !important; }
            .main-wrapper { padding: 0 !important; }
            .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { display: none !important; }
            .welcome-section { padding: 12px 16px !important; }
            .welcome-section .welcome-text h2 { font-size: 16px !important; color: #111 !important; }
            .welcome-section .welcome-text p { font-size: 11px !important; color: #555 !important; }
            .vehicle-status { border: 1px solid #d1d5db !important; color: #111 !important; background: #f1f5f9 !important; }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- BACKGROUND VIDEO -->
<!-- ============================================ -->
<div class="video-background">
    <div class="fallback"></div>
    <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1920&q=80">
        <source src="videos/19329370-uhd_3840_2160_24fps.mp4" type="video/mp4">
        <source src="videos/background.webm" type="video/webm">
    </video>
</div>
<div class="video-overlay"></div>

<!-- Ambient Blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Particles -->
<div class="particles" id="particles"></div>

<!-- Loader -->
<div class="loader" id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">Loading Intelligence</div>
</div>

<!-- Custom Cursor -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- ============================================================ -->
<!-- NAVBAR -->
<!-- ============================================================ -->
<nav class="navbar">
    <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
    <div class="nav-group">
        <div class="nav-links">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php" class="active"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
        </div>
        <span class="navbar-clock" id="liveClock"><i class="fa-regular fa-clock"></i> Loading...</span>
        <div class="admin-badge">
            <i class="fa-solid fa-user-shield"></i>
            Admin: <?php echo htmlspecialchars($admin_name); ?>
        </div>
        <div class="theme-toggle-inline">
            <i class="fa-regular fa-sun toggle-icon" id="themeIcon"></i>
            <label class="toggle-switch">
                <input type="checkbox" id="themeToggle" />
                <span class="toggle-slider"></span>
            </label>
            <i class="fa-regular fa-moon toggle-icon"></i>
        </div>
        <a href="../index.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ============================================================ -->
<!-- MAIN WRAPPER -->
<!-- ============================================================ -->
<div class="main-wrapper">

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <h1 id="typing-heading">Intelligence Data Center</h1>
                <span>Advanced Reporting Suite &bull; Full Details &bull; v5.0 (Real-Time)</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <button onclick="printAllReport()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print All</button>
        </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fa-regular fa-hand-peace"></i> Welcome Back, Admin: <?php echo htmlspecialchars($admin_name); ?></h2>
            <p><i class="fa-regular fa-circle-check"></i> Ongoing &amp; Completed trips are calculated in <strong>Real-Time</strong> based on travel duration.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="num"><?php echo $total_trips; ?></div>
                <div class="label">Completed Trips</div>
            </div>
            <div class="stat-item">
                <div class="num">Rs. <?php echo number_format($total_revenue, 0); ?></div>
                <div class="label">Revenue</div>
            </div>
            <div class="stat-item">
                <div class="num"><?php echo $unique_travellers; ?></div>
                <div class="label">Travellers</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="" class="filter-bar" id="filterForm">
        <label><i class="fa-regular fa-calendar"></i> From:
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
        </label>
        <label><i class="fa-regular fa-calendar"></i> To:
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
        </label>
        <button type="submit" class="btn"><i class="fa-solid fa-filter"></i> Apply</button>
        <a href="advanced_reports.php" class="btn btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
    </form>

    <!-- KPI Dashboard -->
    <div class="kpi-grid animate__animated animate__fadeInUp">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:var(--text-success);"></i> Revenue</div>
            <div class="kpi-value">Rs. <?php echo number_format($total_revenue, 2); ?></div>
            <div class="kpi-sub"><?php echo date('M j, Y', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fa-solid fa-route"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-calendar-check" style="color:var(--text-success);"></i> Completed Trips</div>
            <div class="kpi-value"><?php echo number_format($total_trips); ?></div>
            <div class="kpi-sub">Real-time (Duration Elapsed)</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-user" style="color:#8b5cf6;"></i> Travellers</div>
            <div class="kpi-value"><?php echo number_format($unique_travellers); ?></div>
            <div class="kpi-sub">Unique customers</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-clock" style="color:#f59e0b;"></i> Avg. Trip Cost</div>
            <div class="kpi-value">Rs. <?php echo number_format($avg_trip_cost, 2); ?></div>
            <div class="kpi-sub">Average per completed booking</div>
        </div>
        <div class="kpi-card rose">
            <div class="kpi-icon"><i class="fa-solid fa-percent"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-chart-simple" style="color:#f43f5e;"></i> Completion Rate</div>
            <div class="kpi-value"><?php echo $completion_rate; ?>%</div>
            <div class="kpi-sub">Real-time completed / total</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-sun" style="color:var(--text-accent);"></i> Today's Revenue</div>
            <div class="kpi-value">Rs. <?php echo number_format($daily_res['daily_profit'], 2); ?></div>
            <div class="kpi-sub"><?php echo date('M j, Y'); ?></div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-icon"><i class="fa-solid fa-spinner"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-clock" style="color:#fbbf24;"></i> Ongoing Trips</div>
            <div class="kpi-value"><?php echo number_format($ongoing_trips_count); ?></div>
            <div class="kpi-sub">Active (Today between dates)</div>
        </div>
        <div class="kpi-card teal">
            <div class="kpi-icon"><i class="fa-solid fa-car"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-car" style="color:#14b8a6;"></i> Vehicles Used</div>
            <div class="kpi-value"><?php echo number_format($vehicles_used); ?> / <?php echo $total_vehicles; ?></div>
            <div class="kpi-sub">Active fleet utilization</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-icon"><i class="fa-solid fa-arrows-left-right"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-arrows" style="color:#6366f1;"></i> Total Distance</div>
            <div class="kpi-value"><?php echo number_format($total_distance, 1); ?> km</div>
            <div class="kpi-sub">Total kilometers covered</div>
        </div>
        <div class="kpi-card" style="grid-column: span 1;">
            <div class="kpi-icon"><i class="fa-solid fa-box"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-folder" style="color:var(--text-accent);"></i> Packages</div>
            <div class="kpi-value"><?php echo number_format($total_packages); ?></div>
            <div class="kpi-sub">Total offerings</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-grid animate__animated animate__fadeInUp" style="animation-delay:0.1s;">
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Revenue Trend</h4>
                <span><?php echo date('M j, Y', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></span>
            </div>
            <canvas id="trendChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-pie"></i> Package Popularity</h4>
                <span>Top 5 packages</span>
            </div>
            <canvas id="packageChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-pie"></i> Booking Status</h4>
                <span>Distribution</span>
            </div>
            <canvas id="statusChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Vehicle Usage</h4>
                <span>Most used vehicles</span>
            </div>
            <canvas id="vehicleUsageChart"></canvas>
        </div>
    </div>

    <!-- Extra Charts -->
    <div class="charts-grid animate__animated animate__fadeInUp" style="animation-delay:0.2s;">
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-user-plus"></i> User Registrations</h4>
                <span>Last 6 months</span>
            </div>
            <canvas id="userRegChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-calendar-days"></i> Day of Week</h4>
                <span>Weekly pattern</span>
            </div>
            <canvas id="dayOfWeekChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-simple"></i> AOV & RPU</h4>
                <span>Average Order Value / Revenue Per User</span>
            </div>
            <canvas id="aovRpuChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-trophy"></i> Top Earning Packages</h4>
                <span>Revenue leaders</span>
            </div>
            <canvas id="topEarningChart"></canvas>
        </div>
    </div>

    <!-- Vehicle Charts -->
    <div class="charts-grid animate__animated animate__fadeInUp" style="animation-delay:0.3s;">
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Top Earning Vehicles</h4>
                <span>Revenue leaders</span>
            </div>
            <canvas id="vehicleRevenueChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Top Drivers</h4>
                <span>Most trips</span>
            </div>
            <canvas id="driverChart"></canvas>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-wrapper">
        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('tab-profit', this)"><i class="fa-solid fa-coins"></i> Profit</button>
            <button class="tab-btn" onclick="openTab('tab-trips', this)"><i class="fa-solid fa-truck-fast"></i> Completed</button>
            <button class="tab-btn" onclick="openTab('tab-ongoing', this)"><i class="fa-solid fa-spinner"></i> Ongoing</button>
            <button class="tab-btn" onclick="openTab('tab-all', this)"><i class="fa-solid fa-table"></i> All Bookings</button>
            <button class="tab-btn" onclick="openTab('tab-travellers', this)"><i class="fa-solid fa-users"></i> Travellers</button>
            <button class="tab-btn" onclick="openTab('tab-vehicle', this)"><i class="fa-solid fa-car"></i> Vehicles</button>
            <button class="tab-btn" onclick="openTab('tab-unused', this)"><i class="fa-solid fa-box-open"></i> Unused</button>
            <button class="tab-btn" onclick="openTab('tab-deleted', this)"><i class="fa-solid fa-trash-can"></i> Deleted</button>
            <button class="tab-btn" onclick="openTab('tab-top', this)"><i class="fa-solid fa-trophy"></i> Top Lists</button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: PROFIT -->
    <!-- ============================================================ -->
    <div id="tab-profit" class="report-section active">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-coins"></i> Financial Overview</div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-profit')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-profit')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="profit-summary">
            <div class="profit-card"><div class="label">Today</div><div class="value">Rs. <?php echo number_format($daily_res['daily_profit'], 2); ?></div></div>
            <div class="profit-card"><div class="label">This Month</div><div class="value">Rs. <?php echo number_format($monthly_res['monthly_profit'], 2); ?></div></div>
            <div class="profit-card"><div class="label">This Year</div><div class="value">Rs. <?php echo number_format($yearly_res['yearly_profit'], 2); ?></div></div>
            <div class="profit-card"><div class="label">Average Order Value (AOV)</div><div class="value">Rs. <?php echo number_format($aov, 2); ?></div></div>
            <div class="profit-card"><div class="label">Revenue Per User (RPU)</div><div class="value">Rs. <?php echo number_format($rpu, 2); ?></div></div>
        </div>

        <div style="background:rgba(255,255,255,0.01);border-radius:16px;padding:14px 20px;border:1px solid var(--border-card);">
            <div style="font-size:14px;color:var(--text-muted);">
                <i class="fa-regular fa-circle-info" style="color:var(--text-accent);margin-right:8px;"></i> 
                All values are in <strong style="color:var(--text-primary);">LKR</strong> and include only real-time completed bookings.
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: COMPLETED TRIPS (REAL-TIME) -->
    <!-- ============================================================ -->
    <div id="tab-trips" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-truck-fast"></i> Completed Trips <span style="font-size:14px;color:var(--text-muted);font-weight:400;">(Real-time: Duration Elapsed)</span></div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-trips')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-trips')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="table-container">
            <table id="tripsTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date/Time</th>
                        <th>Customer</th>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Distance</th>
                        <th>Days</th>
                        <th>Cost</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Manager</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($completed_trips_count > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($completed_trips_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['booking_id']; ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['booking_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Guest'); ?><br><span class="text-small text-muted"><?php echo htmlspecialchars($row['customer_email'] ?? ''); ?></span></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['pickup_location'] ?? '—'); ?></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['destination_location'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['calculated_distance'], 1); ?></td>
                                <td><?php echo $row['number_of_days']; ?></td>
                                <td><span class="fw-bold text-primary">Rs. <?php echo number_format($row['total_cost'], 2); ?></span></td>
                                <td>
                                    <?php if ($row['vehicle_name']): ?>
                                        <strong><?php echo htmlspecialchars($row['vehicle_name']); ?></strong>
                                        <br><span class="vehicle-status <?php echo $row['vehicle_status']; ?>"><?php echo $row['vehicle_status'] ?? 'N/A'; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['driver_name']): ?>
                                        <strong><?php echo htmlspecialchars($row['driver_name']); ?></strong>
                                        <br><span class="text-small text-muted"><?php echo htmlspecialchars($row['driver_phone']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['operator_name']): ?>
                                        <strong><?php echo htmlspecialchars($row['operator_name']); ?></strong>
                                        <br><span class="text-small text-muted"><?php echo htmlspecialchars($row['operator_phone']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['realtime_status']); ?>">
                                        <?php echo $row['realtime_status']; ?>
                                        <?php if (isset($row['days_completed_ago']) && $row['days_completed_ago'] > 0): ?>
                                            <span style="font-size:9px;opacity:0.7;"> (<?php echo $row['days_completed_ago']; ?> days ago)</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No completed trips found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: ONGOING TRIPS (REAL-TIME WITH PROGRESS BAR) -->
    <!-- ============================================================ -->
    <div id="tab-ongoing" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-spinner"></i> Ongoing Trips <span style="font-size:14px;color:var(--text-muted);font-weight:400;">(Active / In Progress)</span></div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-ongoing')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-ongoing')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="table-container">
            <table id="ongoingTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date/Time</th>
                        <th>Customer</th>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Distance</th>
                        <th>Progress</th>
                        <th>Cost</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Manager</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ongoing_trips_count > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($ongoing_trips_result)): 
                            $total_days = $row['number_of_days'];
                            $current_day = $row['day_number'] ?? 1;
                            $progress_percent = min(($current_day / $total_days) * 100, 100);
                        ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['booking_id']; ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['booking_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Guest'); ?></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['pickup_location'] ?? '—'); ?></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['destination_location'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['calculated_distance'], 1); ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;min-width:100px;">
                                        <span style="font-weight:700;font-size:12px;">Day <?php echo $current_day; ?>/<?php echo $total_days; ?></span>
                                        <div style="flex:1;min-width:60px;height:6px;background:var(--border-card);border-radius:6px;overflow:hidden;">
                                            <div style="width:<?php echo $progress_percent; ?>%;height:100%;background:linear-gradient(90deg,#0ea5e9,#10b981);border-radius:6px;transition:width 0.5s ease;"></div>
                                        </div>
                                        <span style="font-size:10px;color:var(--text-muted);"><?php echo round($progress_percent); ?>%</span>
                                    </div>
                                </td>
                                <td><span class="fw-bold text-warning">Rs. <?php echo number_format($row['total_cost'], 2); ?></span></td>
                                <td><?php echo htmlspecialchars($row['vehicle_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['driver_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['operator_name'] ?? '—'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['realtime_status']); ?>">
                                        <?php echo $row['realtime_status']; ?>
                                        <?php if (isset($row['days_remaining']) && $row['days_remaining'] >= 0): ?>
                                            <span style="font-size:9px;opacity:0.7;"> (<?php echo $row['days_remaining']; ?> days left)</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12"><div class="empty-state"><i class="fa-regular fa-face-smile"></i><p>No ongoing trips at the moment.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: ALL BOOKINGS -->
    <!-- ============================================================ -->
    <div id="tab-all" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-table"></i> All Bookings <span style="font-size:14px;color:var(--text-muted);font-weight:400;">(Complete Data Set)</span></div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-all')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-all')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="table-container">
            <table id="allBookingsTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date/Time</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Pickup</th>
                        <th>Destination</th>
                        <th>Distance</th>
                        <th>Days</th>
                        <th>Cost</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Manager</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($all_bookings_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($all_bookings_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['booking_id']; ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['booking_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Guest'); ?></td>
                                <td><?php echo htmlspecialchars($row['package_name'] ?? 'Custom'); ?></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['pickup_location'] ?? '—'); ?></td>
                                <td style="font-size:11px;"><?php echo htmlspecialchars($row['destination_location'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['calculated_distance'], 1); ?></td>
                                <td><?php echo $row['number_of_days']; ?></td>
                                <td>Rs. <?php echo number_format($row['total_cost'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['vehicle_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['driver_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['operator_name'] ?? '—'); ?></td>
                                <td><span class="status-badge <?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="13"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No bookings found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: TRAVELLERS -->
    <!-- ============================================================ -->
    <div id="tab-travellers" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-users"></i> Travellers List</div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-travellers')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-travellers')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="table-container">
            <table id="travellersTable" class="display" style="width:100%">
                <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Total Trips</th><th>Total Spent</th><th>Last Trip</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($travellers_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($travellers_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['user_id']; ?></span></td>
                                <td><?php echo htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($row['total_trips']); ?></td>
                                <td><span class="fw-bold text-primary">Rs. <?php echo number_format($row['total_spent'], 2); ?></span></td>
                                <td><?php echo $row['last_trip'] ? date('M j, Y g:i A', strtotime($row['last_trip'])) : '—'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No travellers found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: VEHICLE REPORTS -->
    <!-- ============================================================ -->
    <div id="tab-vehicle" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-car"></i> Vehicle Reports <span style="font-size:14px;color:var(--text-muted);font-weight:400;">(Full Vehicle &amp; Driver Analytics)</span></div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-vehicle')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-vehicle')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <!-- Vehicle Status Summary Cards -->
        <div class="kpi-grid" style="margin-top:0;">
            <?php 
            $status_summary = [];
            while ($row = mysqli_fetch_assoc($vehicle_status_result)) {
                $status_summary[$row['status']] = $row;
            }
            ?>
            <div class="kpi-card green">
                <div class="kpi-icon"><i class="fa-solid fa-check-circle"></i></div>
                <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:#10b981;"></i> Available</div>
                <div class="kpi-value"><?php echo $status_summary['Available']['count'] ?? 0; ?></div>
                <div class="kpi-sub"><?php echo $status_summary['Available']['with_driver'] ?? 0; ?> with driver</div>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="kpi-label"><i class="fa-regular fa-calendar" style="color:#f59e0b;"></i> Booked</div>
                <div class="kpi-value"><?php echo $status_summary['Booked']['count'] ?? 0; ?></div>
                <div class="kpi-sub"><?php echo $status_summary['Booked']['with_driver'] ?? 0; ?> with driver</div>
            </div>
            <div class="kpi-card rose">
                <div class="kpi-icon"><i class="fa-solid fa-wrench"></i></div>
                <div class="kpi-label"><i class="fa-regular fa-screwdriver-wrench" style="color:#f43f5e;"></i> Maintenance</div>
                <div class="kpi-value"><?php echo $status_summary['Maintenance']['count'] ?? 0; ?></div>
                <div class="kpi-sub"><?php echo $status_summary['Maintenance']['with_driver'] ?? 0; ?> with driver</div>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="kpi-label"><i class="fa-regular fa-user-tie" style="color:var(--text-accent);"></i> Total Vehicles</div>
                <div class="kpi-value"><?php 
                    $total_veh = ($status_summary['Available']['count'] ?? 0) + ($status_summary['Booked']['count'] ?? 0) + ($status_summary['Maintenance']['count'] ?? 0);
                    echo $total_veh;
                ?></div>
                <div class="kpi-sub">Active fleet</div>
            </div>
        </div>

        <!-- Maintenance Alerts -->
        <?php if ($maintenance_alerts_count > 0): ?>
        <div style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:16px;padding:14px 20px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:18px;"></i>
                <span style="font-weight:700;color:#ef4444;">⚠️ Maintenance Alerts (<?php echo $maintenance_alerts_count; ?> vehicles need attention)</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php 
                $alert_icons = ['🔧' => 'Maintenance', '📄' => 'Insurance', '📋' => 'Registration'];
                while ($alert = mysqli_fetch_assoc($maintenance_alerts_result)):
                    $alert_types = [];
                    if ($alert['days_to_maintenance'] !== null && $alert['days_to_maintenance'] <= 30) $alert_types[] = '🔧';
                    if ($alert['days_to_insurance'] !== null && $alert['days_to_insurance'] <= 30) $alert_types[] = '📄';
                    if ($alert['days_to_registration'] !== null && $alert['days_to_registration'] <= 30) $alert_types[] = '📋';
                ?>
                <span style="background:var(--bg-card);padding:4px 12px;border-radius:20px;border:1px solid var(--border-card);font-size:12px;">
                    <strong><?php echo htmlspecialchars($alert['vehicle_name']); ?></strong>
                    <?php foreach ($alert_types as $icon): ?>
                        <span title="<?php echo $alert_icons[$icon] ?? ''; ?>"><?php echo $icon; ?></span>
                    <?php endforeach; ?>
                    <span class="text-muted" style="font-size:10px;">ID: #<?php echo $alert['id']; ?></span>
                </span>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vehicle Performance Table -->
        <h4 style="margin:20px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">🚗 Vehicle Performance</h4>
        <div class="table-container">
            <table id="vehiclePerformanceTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vehicle Name</th>
                        <th>Operator</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Trips</th>
                        <th>Revenue</th>
                        <th>Avg Trip</th>
                        <th>Distance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vehicle_performance_count > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($vehicle_performance_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['vehicle_id']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['vehicle_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['operator_name'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($row['driver_name']): ?>
                                        <strong><?php echo htmlspecialchars($row['driver_name']); ?></strong>
                                        <br><span class="text-small text-muted"><?php echo htmlspecialchars($row['driver_phone']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="vehicle-status <?php echo $row['vehicle_status']; ?>"><?php echo $row['vehicle_status']; ?></span></td>
                                <td><?php echo number_format($row['total_trips']); ?></td>
                                <td><span class="fw-bold text-primary">Rs. <?php echo number_format($row['total_revenue'], 2); ?></span></td>
                                <td>Rs. <?php echo number_format($row['avg_trip_cost'], 2); ?></td>
                                <td><?php echo number_format($row['total_distance'], 1); ?> km</td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No vehicle data found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Driver Performance Table -->
        <h4 style="margin:30px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">👤 Driver Performance</h4>
        <div class="table-container">
            <table id="driverPerformanceTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Driver Name</th>
                        <th>Phone</th>
                        <th>Primary Vehicle</th>
                        <th>Vehicles Driven</th>
                        <th>Trips</th>
                        <th>Revenue</th>
                        <th>Avg Trip</th>
                        <th>Distance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($driver_performance_count > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($driver_performance_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['driver_id']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['driver_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['driver_phone'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['primary_vehicle'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['vehicles_driven']); ?></td>
                                <td><?php echo number_format($row['total_trips']); ?></td>
                                <td><span class="fw-bold text-primary">Rs. <?php echo number_format($row['total_revenue'], 2); ?></span></td>
                                <td>Rs. <?php echo number_format($row['avg_trip_cost'], 2); ?></td>
                                <td><?php echo number_format($row['total_distance'], 1); ?> km</td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No driver data found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Operator Vehicle Distribution -->
        <h4 style="margin:30px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">🏢 Operator Vehicle Distribution</h4>
        <div class="table-container">
            <table id="operatorDistTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Operator Name</th>
                        <th>Phone</th>
                        <th>Total Vehicles</th>
                        <th>Available</th>
                        <th>Booked</th>
                        <th>Maintenance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($operator_dist_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($operator_dist_result)): ?>
                            <tr>
                                <td><span class="fw-bold">#<?php echo $row['operator_id']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['operator_name'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['operator_phone'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['vehicle_count']); ?></td>
                                <td><span class="status-badge approved"><?php echo $row['available_count']; ?></span></td>
                                <td><span class="status-badge pending"><?php echo $row['booked_count']; ?></span></td>
                                <td><span class="status-badge cancelled"><?php echo $row['maintenance_count']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No operator data found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: UNUSED PACKAGES -->
    <!-- ============================================================ -->
    <div id="tab-unused" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-box-open"></i> Unused Packages</div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-unused')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-unused')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>
        <div class="table-container">
            <table id="unusedTable" class="display" style="width:100%">
                <thead><tr><th>Package ID</th><th>Title</th><th>Price</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($unused_packages_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($unused_packages_result)): ?>
                            <tr><td><span class="fw-bold">#<?php echo $row['id']; ?></span></td><td><?php echo htmlspecialchars($row['title']); ?></td><td><span class="text-warning">Rs. <?php echo number_format($row['price'], 2); ?></span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fa-regular fa-face-smile"></i><p>All packages have been used!</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: DELETED -->
    <!-- ============================================================ -->
    <div id="tab-deleted" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-trash-can"></i> Deleted Records</div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-deleted')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-deleted')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>
        <h4 style="margin:20px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">🗑️ Deleted Packages</h4>
        <div class="table-container">
            <table id="deletedPackagesTable" class="display" style="width:100%">
                <thead><tr><th>Package ID</th><th>Title</th><th>Price</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($deleted_packages_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($deleted_packages_result)): ?>
                            <tr><td><span class="fw-bold">#<?php echo $row['id']; ?></span></td><td><?php echo htmlspecialchars($row['title']); ?></td><td><span class="text-danger text-decoration-line-through">Rs. <?php echo number_format($row['price'], 2); ?></span></td><td><span class="status-badge deleted">Deleted</span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fa-regular fa-face-smile"></i><p>No deleted packages found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <h4 style="margin:30px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">📅 Deleted Bookings</h4>
        <div class="table-container">
            <table id="deletedBookingsTable" class="display" style="width:100%">
                <thead><tr><th>Booking ID</th><th>User</th><th>Package</th><th>Date</th><th>Cost</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($deleted_bookings_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($deleted_bookings_result)): ?>
                            <tr><td><span class="fw-bold">#<?php echo $row['id']; ?></span></td><td><?php echo htmlspecialchars($row['user_name']); ?></td><td><?php echo htmlspecialchars($row['pkg_name'] ?? 'Custom Plan'); ?></td><td><?php echo date('M j, Y g:i A', strtotime($row['booking_date'])); ?></td><td><span class="text-danger">Rs. <?php echo number_format($row['total_cost'], 2); ?></span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-regular fa-face-smile"></i><p>No deleted bookings found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: TOP LISTS -->
    <!-- ============================================================ -->
    <div id="tab-top" class="report-section">
        <div class="section-toolbar">
            <div class="section-title"><i class="fa-solid fa-trophy"></i> Top Lists & Performance</div>
            <div class="toolbar-actions">
                <button class="btn-sm print" onclick="printSection('tab-top')"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn-sm pdf" onclick="exportPDF('tab-top')"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <h4 style="margin:20px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">🏆 Top Travellers</h4>
        <div class="table-container">
            <table id="topTravellersTable" class="display" style="width:100%">
                <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Trips</th><th>Total Spent</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($top_travellers_result) > 0): ?>
                        <?php $rank = 1; while ($row = mysqli_fetch_assoc($top_travellers_result)): ?>
                            <tr><td><span class="fw-bold">#<?php echo $rank++; ?></span></td><td><?php echo htmlspecialchars($row['full_name']); ?></td><td><?php echo htmlspecialchars($row['email']); ?></td><td><?php echo $row['trip_count']; ?></td><td><span class="fw-bold text-primary">Rs. <?php echo number_format($row['total_spent'], 2); ?></span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No travellers found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4 style="margin:30px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">💰 Top Earning Packages</h4>
        <div class="table-container">
            <table id="topEarningTable" class="display" style="width:100%">
                <thead><tr><th>#</th><th>Package</th><th>Bookings</th><th>Total Revenue</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($top_earning_result) > 0): ?>
                        <?php $rank = 1; while ($row = mysqli_fetch_assoc($top_earning_result)): ?>
                            <tr><td><span class="fw-bold">#<?php echo $rank++; ?></span></td><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo $row['bookings']; ?></td><td><span class="fw-bold text-success">Rs. <?php echo number_format($row['total_revenue'], 2); ?></span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No data found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4 style="margin:30px 0 12px;color:var(--text-muted);font-weight:700;font-size:16px;">📊 Package Performance Score</h4>
        <div class="table-container">
            <table id="performanceTable" class="display" style="width:100%">
                <thead><tr><th>#</th><th>Package</th><th>Bookings</th><th>Revenue</th><th>Avg Price</th><th>Score</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($performance_result) > 0): ?>
                        <?php $rank = 1; while ($row = mysqli_fetch_assoc($performance_result)): 
                            $score = ($row['bookings'] * 0.4) + ($row['revenue'] / 1000 * 0.6);
                            $score = round($score, 1);
                        ?>
                            <tr><td><span class="fw-bold">#<?php echo $rank++; ?></span></td><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo $row['bookings']; ?></td><td><span class="text-primary">Rs. <?php echo number_format($row['revenue'], 2); ?></span></td><td>Rs. <?php echo number_format($row['avg_price'], 2); ?></td><td><span class="status-badge <?php echo $score > 50 ? 'approved' : 'pending'; ?>"><?php echo $score; ?></span></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-regular fa-face-frown"></i><p>No data found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> Intelligence Data Center &bull; All rights reserved.</span>
        <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?></span>
    </div>

</div>

<!-- Scroll to Top -->
<div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // Loader - Hide after page loads
    window.addEventListener("load", function() {
        const loader = document.getElementById("loader");
        setTimeout(function() {
            loader.classList.add("hidden");
        }, 400);
        createParticles();
        initializeAllDataTables();
    });

    // Video Autoplay
    document.addEventListener("DOMContentLoaded", function() {
        const video = document.getElementById('bgVideo');
        if (video) {
            video.play().catch(function(error) {
                document.addEventListener('click', function playVideo() {
                    video.play();
                    document.removeEventListener('click', playVideo);
                }, { once: true });
            });
        }
        initClock();
        initCharts();
    });

    // Live Clock
    function initClock() {
        const clockEl = document.getElementById('liveClock');
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const mins = String(now.getMinutes()).padStart(2, '0');
            const secs = String(now.getSeconds()).padStart(2, '0');
            clockEl.innerHTML = '<i class="fa-regular fa-clock"></i> ' + hours + ':' + mins + ':' + secs;
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Theme Toggle
    (function initTheme() {
        const toggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');
        const saved = localStorage.getItem('travel-theme');
        if (saved === 'dark') {
            html.setAttribute('data-theme', 'dark');
            toggle.checked = true;
            if (icon) icon.classList.add('spin');
        } else {
            html.removeAttribute('data-theme');
            toggle.checked = false;
            if (icon) icon.classList.remove('spin');
            localStorage.setItem('travel-theme', 'light');
        }
        toggle.addEventListener('change', function() {
            const iconEl = document.getElementById('themeIcon');
            if (this.checked) {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('travel-theme', 'dark');
                if (iconEl) iconEl.classList.add('spin');
            } else {
                html.removeAttribute('data-theme');
                localStorage.setItem('travel-theme', 'light');
                if (iconEl) iconEl.classList.remove('spin');
            }
        });
    })();

    // Print All
    function printAllReport() {
        setTimeout(function() { window.print(); }, 300);
    }

    // Tab Switching
    function openTab(tabId, element) {
        document.querySelectorAll('.report-section').forEach(function(s) { s.classList.remove('active'); });
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.getElementById(tabId).classList.add('active');
        element.classList.add('active');
        setTimeout(function() {
            if (window.trendChart) window.trendChart.resize();
            if (window.packageChart) window.packageChart.resize();
            if (window.statusChart) window.statusChart.resize();
            if (window.vehicleUsageChart) window.vehicleUsageChart.resize();
            if (window.userRegChart) window.userRegChart.resize();
            if (window.dayOfWeekChart) window.dayOfWeekChart.resize();
            if (window.aovRpuChart) window.aovRpuChart.resize();
            if (window.topEarningChart) window.topEarningChart.resize();
            if (window.vehicleRevenueChart) window.vehicleRevenueChart.resize();
            if (window.driverChart) window.driverChart.resize();
            initializeAllDataTables();
        }, 100);
    }

    // Print Section
    function printSection(sectionId) {
        var content = document.getElementById(sectionId).innerHTML;
        var win = window.open('', '', 'height=800,width=1000');
        win.document.write(`
            <html><head><title>Print Report</title>
            <style>
                body{font-family:'Inter',sans-serif;padding:30px;background:#fff;color:#111;}
                .section-toolbar .section-title{font-size:20px;font-weight:700;margin-bottom:16px;}
                .table-container{overflow-x:auto;margin-top:12px;}
                table{width:100%;border-collapse:collapse;font-size:11px;}
                th{background:#f1f5f9;padding:8px 12px;text-align:left;font-weight:700;text-transform:uppercase;font-size:10px;border:1px solid #e2e8f0;}
                td{padding:8px 12px;border:1px solid #e2e8f0;font-size:10px;}
                .status-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:600;}
                .status-badge.completed{background:#dbeafe;color:#1e40af;}
                .status-badge.approved{background:#d1fae5;color:#065f46;}
                .status-badge.confirmed{background:#d1fae5;color:#065f46;}
                .status-badge.ongoing{background:#fef3c7;color:#92400e;}
                .status-badge.inprogress{background:#fef3c7;color:#92400e;}
                .status-badge.active{background:#fef3c7;color:#92400e;}
                .status-badge.deleted{background:#fee2e2;color:#991b1b;}
                .status-badge.pending{background:#fef3c7;color:#92400e;}
                .status-badge.cancelled{background:#ede9fe;color:#5b21b6;}
                .vehicle-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:9px;font-weight:600;}
                .vehicle-status.Available{background:#d1fae5;color:#065f46;}
                .vehicle-status.OnTrip{background:#fef3c7;color:#92400e;}
                .vehicle-status.Maintenance{background:#fee2e2;color:#991b1b;}
                .vehicle-status.Inactive{background:#f1f5f9;color:#6b7280;}
                .text-primary{color:#2563eb;}
                .text-success{color:#059669;}
                .text-danger{color:#dc2626;}
                .text-warning{color:#d97706;}
                .text-muted{color:#6b7280;}
                .fw-bold{font-weight:700;}
                .text-small{font-size:9px;}
                .text-decoration-line-through{text-decoration:line-through;}
                .empty-state{text-align:center;padding:30px;color:#94a3b8;}
                .toolbar-actions,.section-toolbar .section-title i{display:none;}
                .section-toolbar{margin-bottom:8px;}
                .profit-summary{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:12px;}
                .profit-card{background:#f8fafc;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;}
                .profit-card .label{font-size:9px;color:#6b7280;}
                .profit-card .value{font-size:16px;color:#111;}
                .kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
                .kpi-card{background:#f8fafc;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;}
                .kpi-card .kpi-label{font-size:9px;color:#6b7280;text-transform:uppercase;font-weight:700;}
                .kpi-card .kpi-value{font-size:18px;color:#111;font-weight:900;}
                .kpi-card .kpi-sub{font-size:9px;color:#6b7280;}
                .kpi-card .kpi-icon{display:none;}
                .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;}
                .chart-box{background:#f8fafc;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;}
                .chart-box canvas{max-height:120px;max-width:100%;}
                .welcome-section{display:none;}
                .filter-bar{display:none;}
                .tab-btn{display:none;}
            </style>
            </head><body>
        `);
        win.document.write(content);
        win.document.write('</body></html>');
        win.document.close();
        win.print();
    }

    // PDF Export
    function exportPDF(sectionId) {
        var element = document.getElementById(sectionId);
        if (!element) {
            alert('Section not found.');
            return;
        }
        var btn = document.querySelector('.btn-sm.pdf');
        if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';

        html2canvas(element, {
            scale: 2,
            useCORS: true,
            allowTaint: false,
            backgroundColor: '#ffffff'
        }).then(function(canvas) {
            var imgData = canvas.toDataURL('image/png');
            var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
            var pdfWidth = pdf.internal.pageSize.getWidth();
            var pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            var heightLeft = pdfHeight;
            var position = 0;

            pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
            heightLeft -= pdf.internal.pageSize.getHeight();

            while (heightLeft > 0) {
                position = heightLeft - pdfHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
                heightLeft -= pdf.internal.pageSize.getHeight();
            }
            pdf.save('report.pdf');
            if (btn) btn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> PDF';
        }).catch(function(err) {
            alert('Failed to generate PDF: ' + err.message);
            if (btn) btn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> PDF';
        });
    }

    // DataTables
    var dataTablesInitialized = false;

    function initializeAllDataTables() {
        if (dataTablesInitialized) return;
        if (typeof $ !== 'undefined' && $.fn.dataTable) {
            // All tables
            var tables = document.querySelectorAll('.report-section.active .table-container table');
            tables.forEach(function(table) {
                if (table.id && !$(table).hasClass('dataTable')) {
                    $(table).DataTable({
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: [
                            { extend: 'copyHtml5', text: '<i class="fa-regular fa-copy"></i> Copy' },
                            { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel' },
                            { extend: 'csvHtml5', text: '<i class="fa-solid fa-file-csv"></i> CSV' },
                            { extend: 'print', text: '<i class="fa-solid fa-print"></i> Print' }
                        ],
                        pageLength: 10,
                        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
                        language: {
                            search: 'Search:',
                            lengthMenu: 'Show _MENU_ entries',
                            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                            infoEmpty: 'No entries available',
                            infoFiltered: '(filtered from _MAX_ total entries)'
                        }
                    });
                }
            });
            dataTablesInitialized = true;
        }
    }

    $(document).ready(function() {
        initializeAllDataTables();
    });

    // ============================================================
    // CHARTS
    // ============================================================
    function initCharts() {
        var chartColors = ['rgba(14,165,233,0.7)','rgba(139,92,246,0.7)','rgba(16,185,129,0.7)','rgba(245,158,11,0.7)','rgba(244,63,94,0.7)','rgba(100,116,139,0.7)'];

        // 1. Revenue Trend
        var trendCtx = document.getElementById('trendChart').getContext('2d');
        var trendLabels = <?php echo json_encode($trend_labels); ?>;
        var trendValues = <?php echo json_encode($trend_values); ?>;
        window.trendChart = new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels.length ? trendLabels : ['No Data'],
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: trendValues.length ? trendValues : [0],
                    backgroundColor: 'rgba(14,165,233,0.5)',
                    borderColor: 'rgba(14,165,233,1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.65,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return 'Rs. ' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } }
                }
            }
        });

        // 2. Package Popularity
        var pkgCtx = document.getElementById('packageChart').getContext('2d');
        var pkgLabels = <?php echo json_encode($package_labels); ?>;
        var pkgValues = <?php echo json_encode($package_values); ?>;
        var hasPkgData = pkgValues.some(function(v) { return v > 0; });
        window.packageChart = new Chart(pkgCtx, {
            type: 'doughnut',
            data: {
                labels: hasPkgData ? pkgLabels : ['No Data'],
                datasets: [{ data: hasPkgData ? pkgValues : [1], backgroundColor: chartColors, borderColor: '#0a0e1a', borderWidth: 3 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 10, usePointStyle: true, pointStyleWidth: 8 } },
                    tooltip: { callbacks: { label: function(ctx) { var total = ctx.dataset.data.reduce(function(a,b) { return a+b; }, 0); var pct = total > 0 ? ((ctx.parsed/total)*100).toFixed(0) : 0; return ctx.label + ': ' + ctx.parsed + ' trips (' + pct + '%)'; } } }
                },
                cutout: '60%',
            }
        });

        // 3. Booking Status
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusLabels = <?php echo json_encode($status_labels); ?>;
        var statusValues = <?php echo json_encode($status_values); ?>;
        var hasStatusData = statusValues.some(function(v) { return v > 0; });
        window.statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: hasStatusData ? statusLabels : ['No Data'],
                datasets: [{ data: hasStatusData ? statusValues : [1], backgroundColor: ['#10b981','#0ea5e9','#f59e0b','#ef4444','#8b5cf6','#fbbf24','#f43f5e'], borderColor: '#0a0e1a', borderWidth: 2 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 8, usePointStyle: true, pointStyleWidth: 8 } }
                }
            }
        });

        // 4. Vehicle Usage
        var vehicleCtx = document.getElementById('vehicleUsageChart').getContext('2d');
        var vehicleLabels = <?php echo json_encode($vehicle_labels); ?>;
        var vehicleValues = <?php echo json_encode($vehicle_usage_values); ?>;
        var hasVehicleData = vehicleValues.some(function(v) { return v > 0; });
        window.vehicleUsageChart = new Chart(vehicleCtx, {
            type: 'bar',
            data: {
                labels: hasVehicleData ? vehicleLabels : ['No Data'],
                datasets: [{
                    label: 'Trips',
                    data: hasVehicleData ? vehicleValues : [0],
                    backgroundColor: 'rgba(16,185,129,0.5)',
                    borderColor: 'rgba(16,185,129,1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.65,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' trips'; } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9 } } }
                }
            }
        });

        // 5. User Registration
        var userRegCtx = document.getElementById('userRegChart').getContext('2d');
        var userRegLabels = <?php echo json_encode($user_reg_labels); ?>;
        var userRegValues = <?php echo json_encode($user_reg_values); ?>;
        window.userRegChart = new Chart(userRegCtx, {
            type: 'bar',
            data: {
                labels: userRegLabels.length ? userRegLabels : ['No Data'],
                datasets: [{
                    label: 'New Users',
                    data: userRegValues.length ? userRegValues : [0],
                    backgroundColor: 'rgba(139,92,246,0.5)',
                    borderColor: 'rgba(139,92,246,1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.65,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } }
                }
            }
        });

        // 6. Day of Week
        var dayCtx = document.getElementById('dayOfWeekChart').getContext('2d');
        var dayLabels = <?php echo json_encode($day_labels); ?>;
        var dayValues = <?php echo json_encode($day_values); ?>;
        window.dayOfWeekChart = new Chart(dayCtx, {
            type: 'bar',
            data: {
                labels: dayLabels.length ? dayLabels : ['No Data'],
                datasets: [{
                    label: 'Bookings',
                    data: dayValues.length ? dayValues : [0],
                    backgroundColor: ['rgba(14,165,233,0.6)','rgba(14,165,233,0.6)','rgba(14,165,233,0.6)','rgba(14,165,233,0.6)','rgba(14,165,233,0.6)','rgba(16,185,129,0.6)','rgba(16,185,129,0.6)'],
                    borderColor: ['#0ea5e9','#0ea5e9','#0ea5e9','#0ea5e9','#0ea5e9','#10b981','#10b981'],
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.7,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9 } } }
                }
            }
        });

        // 7. AOV & RPU
        var aovCtx = document.getElementById('aovRpuChart').getContext('2d');
        var aovVal = <?php echo json_encode($aov); ?>;
        var rpuVal = <?php echo json_encode($rpu); ?>;
        window.aovRpuChart = new Chart(aovCtx, {
            type: 'bar',
            data: {
                labels: ['Average Order Value', 'Revenue Per User'],
                datasets: [{
                    label: 'Amount (LKR)',
                    data: [aovVal, rpuVal],
                    backgroundColor: ['rgba(16,185,129,0.6)', 'rgba(139,92,246,0.6)'],
                    borderColor: ['#10b981', '#8b5cf6'],
                    borderWidth: 2,
                    borderRadius: 8,
                    barPercentage: 0.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return 'Rs. ' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } }
                }
            }
        });

        // 8. Top Earning Packages
        var earningCtx = document.getElementById('topEarningChart').getContext('2d');
        var earningData = <?php 
            $earn_arr = [];
            if (mysqli_num_rows($top_earning_result) > 0) {
                while ($row = mysqli_fetch_assoc($top_earning_result)) {
                    $earn_arr[] = ['title' => $row['title'], 'revenue' => (float)$row['total_revenue']];
                }
            }
            echo json_encode($earn_arr);
        ?>;
        var earningLabels = earningData.map(function(item) { return item.title; });
        var earningValues = earningData.map(function(item) { return item.revenue; });
        var hasEarningData = earningValues.some(function(v) { return v > 0; });
        window.topEarningChart = new Chart(earningCtx, {
            type: 'bar',
            data: {
                labels: hasEarningData ? earningLabels : ['No Data'],
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: hasEarningData ? earningValues : [0],
                    backgroundColor: 'rgba(245,158,11,0.5)',
                    borderColor: 'rgba(245,158,11,1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.65,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return 'Rs. ' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9 } } }
                }
            }
        });

        // 9. Vehicle Revenue Chart
        var vehicleRevCtx = document.getElementById('vehicleRevenueChart');
        if (vehicleRevCtx) {
            var vehicleRevLabels = <?php echo json_encode($vehicle_revenue_labels); ?>;
            var vehicleRevValues = <?php echo json_encode($vehicle_revenue_values); ?>;
            var hasVehicleRevData = vehicleRevValues.some(function(v) { return v > 0; });
            window.vehicleRevenueChart = new Chart(vehicleRevCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: hasVehicleRevData ? vehicleRevLabels : ['No Data'],
                    datasets: [{
                        label: 'Revenue (LKR)',
                        data: hasVehicleRevData ? vehicleRevValues : [0],
                        backgroundColor: 'rgba(14,165,233,0.5)',
                        borderColor: 'rgba(14,165,233,1)',
                        borderWidth: 2,
                        borderRadius: 6,
                        barPercentage: 0.65,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function(ctx) { return 'Rs. ' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } } },
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9 } } }
                    }
                }
            });
        }

        // 10. Driver Chart
        var driverCtx = document.getElementById('driverChart');
        if (driverCtx) {
            var driverLabels = <?php echo json_encode($driver_chart_labels); ?>;
            var driverValues = <?php echo json_encode($driver_chart_values); ?>;
            var hasDriverData = driverValues.some(function(v) { return v > 0; });
            window.driverChart = new Chart(driverCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: hasDriverData ? driverLabels : ['No Data'],
                    datasets: [{
                        label: 'Trips',
                        data: hasDriverData ? driverValues : [0],
                        backgroundColor: 'rgba(16,185,129,0.5)',
                        borderColor: 'rgba(16,185,129,1)',
                        borderWidth: 2,
                        borderRadius: 6,
                        barPercentage: 0.65,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9 } } }
                    }
                }
            });
        }
    }

    // Keyboard Shortcuts (1-9)
    document.addEventListener('keydown', function(e) {
        if (e.key >= '1' && e.key <= '9') {
            var tabs = document.querySelectorAll('.tab-btn');
            var idx = parseInt(e.key) - 1;
            if (tabs[idx]) {
                tabs[idx].click();
                e.preventDefault();
            }
        }
    });

    // Ripple Effect
    document.querySelectorAll('.btn, .btn-sm').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var rect = this.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            this.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 800);
        });
    });

    // 3D Tilt on KPI Cards
    document.querySelectorAll('.kpi-card').forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            var rect = this.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var centerX = rect.width / 2;
            var centerY = rect.height / 2;
            var rotateX = ((y - centerY) / centerY) * -3;
            var rotateY = ((x - centerX) / centerX) * 3;
            this.style.transform = 'perspective(800px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-4px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(800px) rotateX(0) rotateY(0) translateY(0)';
        });
    });

    // Typing Effect
    var heading = document.getElementById('typing-heading');
    var originalText = heading.innerText;
    heading.innerText = '';
    var charIndex = 0;
    function typeWriter() {
        if (charIndex < originalText.length) {
            heading.innerText += originalText.charAt(charIndex);
            charIndex++;
            setTimeout(typeWriter, 50);
        }
    }
    setTimeout(typeWriter, 800);

    // Custom Cursor
    var dot = document.getElementById('cursorDot');
    var ring = document.getElementById('cursorRing');
    if (dot && ring) {
        document.addEventListener('mousemove', function(e) {
            dot.style.left = e.clientX + 'px';
            dot.style.top = e.clientY + 'px';
            ring.style.left = e.clientX + 'px';
            ring.style.top = e.clientY + 'px';
        });
        if ('ontouchstart' in window) {
            dot.style.display = 'none';
            ring.style.display = 'none';
        }
    }

    // Scroll to Top
    var scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 400) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // Particles
    function createParticles() {
        var container = document.getElementById('particles');
        var count = 30;
        for (var i = 0; i < count; i++) {
            var particle = document.createElement('div');
            particle.className = 'particle';
            var size = Math.random() * 5 + 2;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
            particle.style.animationDelay = (Math.random() * 20) + 's';
            particle.style.opacity = Math.random() * 0.15 + 0.05;
            container.appendChild(particle);
        }
    }

    // Auto Refresh every 60 seconds to update real-time status
    setInterval(function() {
        location.reload();
    }, 60000);
</script>

</body>
</html>