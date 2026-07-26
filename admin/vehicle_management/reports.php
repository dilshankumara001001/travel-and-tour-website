<?php
// ============================================================
// 📊 PACKAGE PROGRESS REPORT (MANAGER) - KANBAN EDITION
// Version: 4.1.0 - Added Drag & Drop Kanban Board
// ============================================================

session_start();

// ============================================================
// CONFIGURATION
// ============================================================
$config = [
    'app_name' => 'Fleet Management',
    'app_version' => '4.1.0',
    'timezone' => 'Asia/Colombo',
    'auto_refresh_interval' => 30,
    'google_maps_api_key' => 'AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw',
    'dark_mode_default' => 'light',
];
date_default_timezone_set($config['timezone']);

// ============================================================
// DATABASE CONNECTION
// ============================================================
$conn = new mysqli('localhost', 'root', '', 'travel_tour_db');
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);
$conn->set_charset("utf8");

// ============================================================
// CHECK LOGIN
// ============================================================
if (!isset($_SESSION['manager_id'])) {
    header("Location: vehicle_manager_login.php");
    exit;
}
$manager_id = $_SESSION['manager_id'];
$manager_name = $_SESSION['manager_name'] ?? 'Manager';

// ============================================================
// 📊 FETCH SUPPORTING DATA (Vehicles & Notifications)
// ============================================================

// 1. Get Vehicle List for Modals
$vehicle_list_query = "SELECT id, vehicle_name FROM vehicles ORDER BY id DESC";
$vehicle_list_result = $conn->query($vehicle_list_query);
if (!$vehicle_list_result) {
    $vehicle_list_result = false;
}

// 2. Get Pending Bookings Count for Notification Bell
$pending_count = 0;
$pending_check = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
if ($pending_check) {
    $pending_count = $pending_check->fetch_assoc()['count'] ?? 0;
}

// ============================================================
// 📊 DATE RANGE FILTER HANDLING
// ============================================================
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';

$date_condition = "";
if (!empty($start_date) && !empty($end_date)) {
    $date_condition = " AND b.booking_date BETWEEN '$start_date' AND '$end_date'";
}

// ============================================================
// 📊 FETCH PACKAGE REPORT DATA (WITH REAL-TIME STATUS)
// ============================================================

$package_report_query = "
    SELECT 
        p.id,
        p.title,
        p.status,
        p.created_at,
        COUNT(b.id) as total_bookings,
        COALESCE(SUM(b.total_cost), 0) as total_revenue,
        MAX(b.booking_date) as last_booking_date,
        (
            SELECT 
                CASE 
                    WHEN CURDATE() BETWEEN b2.booking_date AND DATE_ADD(b2.booking_date, INTERVAL (b2.number_of_days - 1) DAY) 
                    THEN '🟢 Ongoing'
                    WHEN CURDATE() > DATE_ADD(b2.booking_date, INTERVAL (b2.number_of_days - 1) DAY) 
                    THEN '✅ Completed'
                    ELSE '⏳ Upcoming'
                END
            FROM bookings b2
            WHERE b2.package_id = p.id 
              AND b2.status IN ('Approved', 'Confirmed', 'Completed')
            ORDER BY b2.booking_date DESC 
            LIMIT 1
        ) as latest_trip_status
    FROM packages p
    LEFT JOIN bookings b ON p.id = b.package_id 
        AND b.status IN ('Approved', 'Confirmed', 'Completed')
        $date_condition
    GROUP BY p.id
    ORDER BY total_bookings DESC
";

$package_result = $conn->query($package_report_query);
if (!$package_result) {
    die("Query Failed: " . $conn->error);
}

$packages = [];
$max_bookings = 0;
$total_package_revenue = 0;
$total_package_bookings = 0;

while ($row = $package_result->fetch_assoc()) {
    $packages[] = $row;
    if ($row['total_bookings'] > $max_bookings) {
        $max_bookings = $row['total_bookings'];
    }
    $total_package_revenue += $row['total_revenue'];
    $total_package_bookings += $row['total_bookings'];
}

// 2. Overall Stats
$total_packages = $conn->query("SELECT COUNT(*) as count FROM packages")->fetch_assoc()['count'] ?? 0;
$active_packages = $conn->query("SELECT COUNT(*) as count FROM packages WHERE status != 'Deleted'")->fetch_assoc()['count'] ?? 0;

// 3. Fleet Stats (for overview)
$total_vehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles")->fetch_assoc()['count'] ?? 0;
$total_drivers = $conn->query("SELECT COUNT(*) as count FROM drivers")->fetch_assoc()['count'] ?? 0;
$total_fleet_revenue = $conn->query("SELECT SUM(total_cost) as total FROM bookings WHERE status IN ('Approved','Confirmed','Completed')")->fetch_assoc()['total'] ?? 0;

// 4. Chart Data (Packages)
$chart_labels = [];
$chart_bookings = [];
$chart_revenue = [];
$chart_colors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#ec4899', '#14b8a6', '#f97316'];

foreach ($packages as $index => $pkg) {
    $chart_labels[] = $pkg['title'];
    $chart_bookings[] = (int)$pkg['total_bookings'];
    $chart_revenue[] = (float)$pkg['total_revenue'];
}

// ============================================================
// 📊 ADVANCED FLEET STATS
// ============================================================

// 1. Vehicle Status Counts
$status_counts = [
    'Available' => 0,
    'On Trip' => 0,
    'Maintenance' => 0,
    'Inactive' => 0,
    'Total' => 0
];
$status_query = $conn->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
if ($status_query) {
    while ($row = $status_query->fetch_assoc()) {
        $stat = $row['status'] ?: 'Inactive';
        if (isset($status_counts[$stat])) {
            $status_counts[$stat] = (int)$row['count'];
        } else {
            $status_counts['Inactive'] += (int)$row['count'];
        }
        $status_counts['Total'] += (int)$row['count'];
    }
}

$on_trip_count = $status_counts['On Trip'] ?? 0;
$maintenance_count = $status_counts['Maintenance'] ?? 0;
$total_vehicles_count = $status_counts['Total'];
$utilization_rate = ($total_vehicles_count > 0) ? round(($on_trip_count / $total_vehicles_count) * 100, 1) : 0;

// 2. Upcoming Maintenance (Next 30 days)
$upcoming_maintenance = [];
$maintenance_query = $conn->query("
    SELECT id, vehicle_name, category, next_maintenance, insurance_expiry, registration_expiry, status
    FROM vehicles 
    WHERE next_maintenance IS NOT NULL 
      AND next_maintenance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY next_maintenance ASC
    LIMIT 10
");
if ($maintenance_query) {
    while ($row = $maintenance_query->fetch_assoc()) {
        $upcoming_maintenance[] = $row;
    }
}

// 3. Top 5 Earning Vehicles
$top_vehicles = [];
$top_vehicles_query = $conn->query("
    SELECT 
        v.id,
        v.vehicle_name,
        v.category,
        COUNT(b.id) as total_bookings,
        COALESCE(SUM(b.total_cost), 0) as total_revenue
    FROM vehicles v
    LEFT JOIN bookings b ON v.id = b.vehicle_id AND b.status IN ('Approved', 'Confirmed', 'Completed')
    GROUP BY v.id
    ORDER BY total_revenue DESC
    LIMIT 5
");
if ($top_vehicles_query) {
    while ($row = $top_vehicles_query->fetch_assoc()) {
        $top_vehicles[] = $row;
    }
}

// ============================================================
// 📊 KANBAN BOARD DATA - Real-time Bookings with status mapping
// ============================================================
// Map database status to Kanban columns
// 'pending', 'Approved' -> Upcoming (or based on date)
// 'Confirmed' -> Ongoing (if date is between)
// 'Completed' -> Completed

$kanban_query = "
    SELECT 
        b.id AS booking_id,
        u.full_name AS user_name,
        u.email AS user_email,
        p.title AS package_name,
        b.manual_package_name,
        b.booking_date,
        b.number_of_days,
        b.total_cost,
        b.status AS booking_status,
        CASE 
            WHEN CURDATE() BETWEEN b.booking_date AND DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
            THEN 'Ongoing'
            WHEN CURDATE() > DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
            THEN 'Completed'
            ELSE 'Upcoming'
        END AS kanban_status,
        DATEDIFF(CURDATE(), b.booking_date) + 1 AS day_number,
        COALESCE(b.number_of_days, 1) AS total_days,
        DATEDIFF(DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY), CURDATE()) AS days_remaining
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.status IN ('pending', 'Approved', 'Confirmed', 'Completed')
    ORDER BY b.booking_date DESC
    LIMIT 50
";

$kanban_result = $conn->query($kanban_query);
if (!$kanban_result) {
    // Fallback: users table may not exist
    $kanban_query = "
        SELECT 
            b.id AS booking_id,
            'Guest' AS user_name,
            '-' AS user_email,
            p.title AS package_name,
            b.manual_package_name,
            b.booking_date,
            b.number_of_days,
            b.total_cost,
            b.status AS booking_status,
            CASE 
                WHEN CURDATE() BETWEEN b.booking_date AND DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                THEN 'Ongoing'
                WHEN CURDATE() > DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                THEN 'Completed'
                ELSE 'Upcoming'
            END AS kanban_status,
            DATEDIFF(CURDATE(), b.booking_date) + 1 AS day_number,
            COALESCE(b.number_of_days, 1) AS total_days,
            DATEDIFF(DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY), CURDATE()) AS days_remaining
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE b.status IN ('pending', 'Approved', 'Confirmed', 'Completed')
        ORDER BY b.id DESC
        LIMIT 50
    ";
    $kanban_result = $conn->query($kanban_query);
}

$kanban_bookings = [];
if ($kanban_result) {
    while ($row = $kanban_result->fetch_assoc()) {
        $kanban_bookings[] = $row;
    }
}

// Group bookings by kanban_status for easier display
$kanban_groups = [
    'Upcoming' => [],
    'Ongoing' => [],
    'Completed' => []
];
foreach ($kanban_bookings as $kb) {
    $status = $kb['kanban_status'] ?? 'Upcoming';
    if (!isset($kanban_groups[$status])) {
        $kanban_groups[$status] = [];
    }
    $kanban_groups[$status][] = $kb;
}

// ============================================================
// HANDLE AJAX REQUEST FOR KANBAN UPDATE
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_kanban_status') {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    
    // Map Kanban status to database status
    $db_status_map = [
        'Upcoming' => 'pending',
        'Ongoing' => 'Approved',
        'Completed' => 'Completed'
    ];
    $db_status = $db_status_map[$new_status] ?? 'pending';
    
    $update_query = "UPDATE bookings SET status = '$db_status' WHERE id = $booking_id";
    if ($conn->query($update_query)) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// Greeting & Theme
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$theme = $_COOKIE['vehicle_theme'] ?? $config['dark_mode_default'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app_name'] ?> - Package Progress Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ============================================================
           THEME & GLOBAL STYLES (Same as before)
           ============================================================ */
        :root {
            --bg-body: #f0f4f8;
            --bg-sidebar: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --bg-card: rgba(255,255,255,0.7);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-color: rgba(255,255,255,0.3);
            --shadow: 0 8px 32px rgba(0,0,0,0.04);
            --shadow-hover: 0 16px 48px rgba(37,99,235,0.12);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            --color-blue: #2563eb;
            --color-green: #10b981;
            --color-yellow: #f59e0b;
            --color-red: #ef4444;
            --color-purple: #8b5cf6;
            --color-teal: #14b8a6;
            --color-pink: #ec4899;
            --color-orange: #f97316;
            --gradient-primary: linear-gradient(135deg, #2563eb, #7c3aed);
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
        }
        [data-theme="dark"] {
            --bg-body: #0b1120;
            --bg-card: rgba(30,41,59,0.5);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: rgba(255,255,255,0.05);
            --shadow: 0 8px 32px rgba(0,0,0,0.3);
            --shadow-hover: 0 16px 48px rgba(37,99,235,0.2);
            --color-blue: #60a5fa;
            --color-green: #34d399;
            --color-yellow: #fbbf24;
            --color-red: #f87171;
            --color-purple: #a78bfa;
            --color-teal: #2dd4bf;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg-body); color:var(--text-primary); font-family:'Inter',sans-serif; font-size:13px; font-weight:400; transition:var(--transition); min-height:100vh; display:flex; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg-body); }
        ::-webkit-scrollbar-thumb { background:var(--color-blue); border-radius:8px; }

        /* SIDEBAR */
        .sidebar { position:fixed; left:0; top:0; height:100vh; width:260px; background:var(--bg-sidebar); padding:20px 16px; display:flex; flex-direction:column; z-index:1000; transition:var(--transition); border-right:1px solid rgba(255,255,255,0.04); overflow-y:auto; }
        .sidebar-brand { display:flex; align-items:center; gap:12px; padding-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.04); margin-bottom:16px; }
        .sidebar-brand i { font-size:28px; color:var(--color-blue); filter:drop-shadow(0 4px 12px rgba(37,99,235,0.3)); }
        .sidebar-brand span { color:#fff; font-size:18px; font-weight:800; }
        .sidebar-brand small { color:rgba(255,255,255,0.25); font-size:8px; display:block; text-transform:uppercase; letter-spacing:1px; }
        .sidebar-nav { flex:1; }
        .sidebar-nav .nav-label { color:rgba(255,255,255,0.12); font-size:8px; text-transform:uppercase; letter-spacing:1.5px; padding:12px 12px 4px; font-weight:600; }
        .sidebar-nav a { display:flex; align-items:center; gap:12px; padding:8px 12px; border-radius:10px; color:rgba(255,255,255,0.35); text-decoration:none; font-size:13px; font-weight:500; transition:var(--transition); margin-bottom:1px; }
        .sidebar-nav a i { width:20px; font-size:15px; text-align:center; }
        .sidebar-nav a:hover { color:#fff; background:rgba(255,255,255,0.05); transform:translateX(4px); }
        .sidebar-nav a.active { color:#fff; background:rgba(59,130,246,0.12); box-shadow:inset 2px 0 0 var(--color-blue); }
        .sidebar-nav a .badge-nav { margin-left:auto; background:var(--color-red); color:#fff; font-size:8px; padding:1px 8px; border-radius:20px; }
        .sidebar-nav .logout-link { margin-top:auto; border-top:1px solid rgba(255,255,255,0.04); padding-top:12px; color:rgba(255,255,255,0.2); }
        .sidebar-nav .logout-link:hover { color:var(--color-red); background:rgba(239,68,68,0.08); }
        .hamburger { display:none; position:fixed; top:12px; left:12px; z-index:1001; background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:10px; padding:6px 10px; cursor:pointer; font-size:18px; color:var(--text-primary); box-shadow:var(--shadow); }

        /* MAIN CONTENT */
        .main-content { margin-left:260px; flex:1; padding:16px 24px 24px; min-height:100vh; width:calc(100% - 260px); transition:var(--transition); }
        .top-header { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; background:var(--bg-card); backdrop-filter:blur(20px); border:1px solid var(--border-color); border-radius:var(--radius); box-shadow:var(--shadow); margin-bottom:16px; flex-wrap:wrap; gap:10px; }
        .top-header .greeting h4 { font-weight:800; font-size:16px; color:var(--text-primary); margin:0; }
        .top-header .greeting h4 span { background:var(--gradient-primary); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .top-header .greeting p { font-size:11px; color:var(--text-muted); margin:0; }
        .header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

        .premium-clock { display:flex; align-items:center; gap:8px; padding:4px 12px 4px 8px; background:var(--bg-body); border-radius:30px; border:1px solid var(--border-color); font-size:11px; }
        .premium-clock .dot { width:6px; height:6px; border-radius:50%; background:var(--color-green); animation:pulse-dot 1.5s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.3;transform:scale(0.6)} }
        .premium-clock .time { font-weight:700; font-size:12px; color:var(--text-primary); }
        .premium-clock .date { color:var(--text-muted); font-size:10px; }
        .notif-bell { position:relative; font-size:18px; color:var(--text-muted); cursor:pointer; transition:0.3s; padding:4px; }
        .notif-bell:hover { color:var(--text-primary); transform:scale(1.1); }
        .notif-bell .badge-count { position:absolute; top:-4px; right:-4px; background:var(--color-red); color:#fff; font-size:7px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--bg-card); }
        .theme-toggle-premium { width:36px; height:18px; background:var(--bg-body); border:1px solid var(--border-color); border-radius:9px; cursor:pointer; position:relative; transition:var(--transition); flex-shrink:0; }
        .theme-toggle-premium .dot { position:absolute; top:1px; left:1px; width:14px; height:14px; background:var(--color-blue); border-radius:50%; transition:var(--transition); box-shadow:0 2px 8px rgba(37,99,235,0.2); }
        .theme-toggle-premium.active .dot { left:19px; }
        .theme-toggle-premium .icon { position:absolute; top:50%; transform:translateY(-50%); font-size:7px; color:var(--text-muted); }
        .theme-toggle-premium .icon-left { left:3px; }
        .theme-toggle-premium .icon-right { right:3px; }
        .theme-toggle-premium.active .icon-left { opacity:0.3; }
        .theme-toggle-premium.active .icon-right { color:var(--color-yellow); }
        .profile-avatar-premium { width:32px; height:32px; border-radius:50%; background:var(--gradient-primary); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:12px; cursor:pointer; border:2px solid var(--border-color); transition:var(--transition); position:relative; }
        .profile-avatar-premium .status-dot { position:absolute; bottom:-1px; right:-1px; width:8px; height:8px; border-radius:50%; background:var(--color-green); border:2px solid var(--bg-card); }

        /* STATS GRID */
        .stat-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:16px; }
        .stat-card-premium { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:var(--radius); padding:14px 16px; box-shadow:var(--shadow); transition:var(--transition); display:flex; align-items:center; gap:12px; position:relative; overflow:hidden; }
        .stat-card-premium::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; border-radius:3px 0 0 3px; }
        .stat-card-premium:hover { transform:translateY(-3px); border-color:var(--color-blue); box-shadow:var(--shadow-hover); }
        .stat-card-premium .icon-wrap { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; flex-shrink:0; }
        .stat-card-premium .icon-wrap.green { background:var(--gradient-success); }
        .stat-card-premium .icon-wrap.yellow { background:var(--gradient-warning); }
        .stat-card-premium .icon-wrap.red { background:var(--gradient-danger); }
        .stat-card-premium .icon-wrap.blue { background:var(--gradient-primary); }
        .stat-card-premium .icon-wrap.purple { background:linear-gradient(135deg, #ec4899, #f472b6); }
        .stat-card-premium .icon-wrap.teal { background:linear-gradient(135deg, #14b8a6, #2dd4bf); }
        .stat-card-premium .icon-wrap.orange { background:linear-gradient(135deg, #f97316, #fb923c); }
        .stat-card-premium .info .number { font-size:1.4rem; font-weight:800; color:var(--text-primary); line-height:1.2; }
        .stat-card-premium .info .label { font-size:10px; color:var(--text-muted); font-weight:500; }

        /* MAIN GRID (Charts) */
        .main-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .card-premium { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:var(--radius); padding:16px 18px; box-shadow:var(--shadow); transition:var(--transition); }
        .card-premium:hover { border-color:rgba(37,99,235,0.12); box-shadow:var(--shadow-hover); }
        .card-premium .head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .card-premium .head h5 { font-weight:700; font-size:13px; color:var(--text-primary); margin:0; }
        .card-premium .head h5 i { margin-right:6px; color:var(--color-blue); }
        .card-premium .head small { font-size:10px; color:var(--text-muted); }
        .chart-container { height:150px; background:rgba(255,255,255,0.1); border-radius:10px; padding:4px; }

        /* FILTER BAR */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            align-items: center;
            background: var(--bg-card);
            padding: 12px 18px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(8px);
        }
        .filter-bar input[type="date"] {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 12px;
        }
        .filter-bar .btn-filter {
            padding: 6px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-filter-primary { background: var(--color-blue); color: #fff; }
        .btn-filter-primary:hover { opacity: 0.8; transform: scale(0.97); }
        .btn-filter-danger { background: var(--color-red); color: #fff; }
        .btn-filter-danger:hover { opacity: 0.8; }
        .btn-filter-success { background: var(--color-green); color: #fff; }
        .btn-filter-success:hover { opacity: 0.8; }

        /* TABLE STYLES */
        .table-wrapper { overflow-x:auto; }
        .table-wrapper table { width:100%; font-size:12px; border-collapse:collapse; }
        .table-wrapper th { text-align:left; padding:8px 10px; color:var(--text-muted); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border-color); }
        .table-wrapper td { padding:8px 10px; border-bottom:1px solid var(--border-color); color:var(--text-secondary); vertical-align:middle; }
        .table-wrapper tr:hover td { background:rgba(0,0,0,0.02); }
        .status-badge { display:inline-block; padding:1px 12px; border-radius:20px; font-size:9px; font-weight:700; text-transform:uppercase; }
        .status-badge.active { background:rgba(16,185,129,0.12); color:#10b981; }
        .status-badge.deleted { background:rgba(239,68,68,0.12); color:#ef4444; }
        .status-badge.inactive { background:rgba(245,158,11,0.12); color:#f59e0b; }

        .progress-bar-container { width:100%; height:4px; background:var(--bg-body); border-radius:10px; overflow:hidden; margin-top:4px; }
        .progress-bar-container .bar { height:100%; border-radius:10px; background:var(--gradient-primary); transition:width 0.8s ease; }

        .text-truncate-custom { max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; }
        .trip-status-badge { font-size:11px; font-weight:600; padding:2px 10px; border-radius:12px; display:inline-block; }
        .trip-status-badge.ongoing { background:rgba(16,185,129,0.15); color:#10b981; }
        .trip-status-badge.completed { background:rgba(59,130,246,0.12); color:#3b82f6; }
        .trip-status-badge.upcoming { background:rgba(148,163,184,0.12); color:var(--text-muted); }

        .badge-ongoing { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 2px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-completed { background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.15); padding: 2px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-upcoming { background: rgba(148, 163, 184, 0.12); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.15); padding: 2px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        .footer-premium { margin-top:16px; padding:10px 0; border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:10px; color:var(--text-muted); }
        .footer-premium .heart { color:var(--color-red); }

        /* MODALS */
        .modal-content { background:var(--bg-card); backdrop-filter:blur(12px); color:var(--text-primary); border:1px solid var(--border-color); }
        .modal-header { border-bottom:1px solid var(--border-color); }
        .modal-footer { border-top:1px solid var(--border-color); }
        .form-control, .form-select { background:var(--bg-body); color:var(--text-primary); border:1px solid var(--border-color); }
        .form-control:focus, .form-select:focus { background:var(--bg-body); color:var(--text-primary); border-color:var(--color-blue); box-shadow:0 0 0 0.2rem rgba(37,99,235,0.15); }

        /* ============================================================
           KANBAN BOARD STYLES (NEW)
           ============================================================ */
        .view-switcher {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .view-switcher .btn-view {
            padding: 8px 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: 0.3s;
        }
        .view-switcher .btn-view:hover {
            border-color: var(--color-blue);
        }
        .view-switcher .btn-view.active {
            background: var(--color-blue);
            color: #fff;
            border-color: var(--color-blue);
        }
        .view-container {
            display: none;
        }
        .view-container.active {
            display: block;
        }

        /* Kanban Columns */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            min-height: 400px;
        }
        .kanban-column {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 12px 14px;
            min-height: 300px;
            backdrop-filter: blur(8px);
            transition: 0.3s;
        }
        .kanban-column .col-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 12px;
        }
        .kanban-column .col-header h6 {
            font-weight: 700;
            font-size: 13px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kanban-column .col-header .count {
            font-size: 11px;
            color: var(--text-muted);
            background: var(--bg-body);
            padding: 1px 10px;
            border-radius: 20px;
        }
        .kanban-column .col-header .count i {
            margin-right: 4px;
        }

        .kanban-column.upcoming .col-header { border-color: var(--text-muted); }
        .kanban-column.upcoming .col-header h6 { color: var(--text-muted); }
        .kanban-column.ongoing .col-header { border-color: var(--color-green); }
        .kanban-column.ongoing .col-header h6 { color: var(--color-green); }
        .kanban-column.completed .col-header { border-color: var(--color-blue); }
        .kanban-column.completed .col-header h6 { color: var(--color-blue); }

        .kanban-column .drop-zone {
            min-height: 250px;
            padding: 4px 2px;
            transition: 0.3s;
            border-radius: 8px;
            border: 2px dashed transparent;
        }
        .kanban-column .drop-zone.dragover {
            border-color: var(--color-blue);
            background: rgba(37, 99, 235, 0.05);
        }

        /* Kanban Cards */
        .kanban-card {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
            border-left: 3px solid var(--border-color);
            box-shadow: var(--shadow);
            cursor: grab;
            transition: 0.3s;
            position: relative;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        .kanban-card:active {
            cursor: grabbing;
            opacity: 0.5;
        }
        .kanban-card.dragging {
            opacity: 0.4;
            transform: scale(0.95);
        }
        .kanban-card .card-id {
            font-size: 9px;
            color: var(--text-muted);
            font-weight: 600;
            background: var(--bg-card);
            padding: 1px 8px;
            border-radius: 10px;
            display: inline-block;
        }
        .kanban-card .card-title {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            margin: 4px 0 2px;
        }
        .kanban-card .card-user {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .kanban-card .card-user i { font-size: 10px; }
        .kanban-card .card-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
        }
        .kanban-card .card-meta .cost {
            font-weight: 700;
            color: var(--color-teal);
        }
        .kanban-card .card-meta .date {
            font-family: monospace;
        }

        .kanban-empty {
            text-align: center;
            padding: 30px 10px;
            color: var(--text-muted);
            font-size: 13px;
        }
        .kanban-empty i {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
            opacity: 0.3;
        }

        @media (max-width: 992px) {
            .kanban-board { grid-template-columns: 1fr; }
            .stat-grid { grid-template-columns: repeat(3,1fr); }
            .main-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
            .kanban-column { min-height: 200px; }
        }
    </style>
</head>
<body>

<!-- ============================================================
SIDEBAR
============================================================ -->
<button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-car-side"></i>
        <div><span><?= $config['app_name'] ?></span><small>v<?= $config['app_version'] ?></small></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Navigation</div>
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="vehicles.php"><i class="fas fa-list"></i> Vehicles <span class="badge-nav"><?= $total_vehicles_count ?></span></a>
        <a href="filter.php"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php"><i class="fas fa-user"></i> Drivers <span class="badge-nav"><?= $total_drivers ?></span></a>
        <div class="nav-label" style="margin-top:8px;">Management</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-coins"></i> Add Expense</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal"><i class="fas fa-tools"></i> Maintenance</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#documentModal"><i class="fas fa-file"></i> Documents</a>
        <div class="nav-label" style="margin-top:8px;">Tools</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user-cog"></i> Profile</a>
        <a href="#" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="#" onclick="exportCSV()"><i class="fas fa-file-csv"></i> Export CSV</a>
        <a href="#" onclick="exportAdvancedCSV()"><i class="fas fa-file-excel"></i> Advanced CSV</a>
        <a href="#" onclick="toggleFullscreen()"><i class="fas fa-expand"></i> Full Screen</a>
        <a href="#" onclick="refreshData()"><i class="fas fa-sync"></i> Refresh</a>
        <a href="reports.php" class="active"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="../index.php?action=logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ============================================================
MAIN CONTENT
============================================================ -->
<main class="main-content" id="mainContent">

    <!-- TOP HEADER -->
    <header class="top-header">
        <div class="greeting">
            <h4><span><?= $greeting ?></span>, <?= htmlspecialchars($manager_name) ?> 📊</h4>
            <p><i class="fas fa-boxes"></i> Package Progress & Analytics Report</p>
        </div>
        <div class="header-actions">
            <div class="premium-clock"><span class="dot"></span><span class="time" id="liveTime">--:--:--</span><span class="date" id="liveDate">-- --- ----</span></div>
            <div class="notif-bell" onclick="showNotifications()"><i class="fas fa-bell"></i><span class="badge-count" id="notifCount"><?= $pending_count ?></span></div>
            <div class="theme-toggle-premium" id="themeToggle"><span class="icon icon-left"><i class="fas fa-sun"></i></span><span class="icon icon-right"><i class="fas fa-moon"></i></span><div class="dot"></div></div>
            <div class="profile-avatar-premium" id="profileToggle"><?= strtoupper(substr($manager_name,0,1)) ?><span class="status-dot"></span></div>
        </div>
    </header>

    <!-- STATS CARDS -->
    <div class="stat-grid">
        <div class="stat-card-premium">
            <div class="icon-wrap blue"><i class="fas fa-box"></i></div>
            <div class="info"><div class="number"><?= $total_packages ?></div><div class="label">Total Packages</div></div>
        </div>
        <div class="stat-card-premium">
            <div class="icon-wrap green"><i class="fas fa-check-circle"></i></div>
            <div class="info"><div class="number" style="color:var(--color-green);"><?= $active_packages ?></div><div class="label">Active Packages</div></div>
        </div>
        <div class="stat-card-premium">
            <div class="icon-wrap yellow"><i class="fas fa-ticket"></i></div>
            <div class="info"><div class="number"><?= $total_package_bookings ?></div><div class="label">Package Bookings</div></div>
        </div>
        <div class="stat-card-premium">
            <div class="icon-wrap teal"><i class="fas fa-dollar-sign"></i></div>
            <div class="info"><div class="number" style="color:var(--color-teal);">LKR <?= number_format($total_package_revenue, 0) ?></div><div class="label">Package Revenue</div></div>
        </div>
        <div class="stat-card-premium">
            <div class="icon-wrap orange"><i class="fas fa-circle"></i></div>
            <div class="info"><div class="number" style="color:var(--color-orange);"><?= $on_trip_count ?></div><div class="label">On Trip Now</div></div>
        </div>
        <div class="stat-card-premium">
            <div class="icon-wrap red"><i class="fas fa-wrench"></i></div>
            <div class="info"><div class="number" style="color:var(--color-red);"><?= $maintenance_count ?></div><div class="label">Maintenance</div></div>
        </div>
    </div>

    <!-- CHARTS GRID -->
    <div class="main-grid">
        <div class="card-premium">
            <div class="head"><h5><i class="fas fa-chart-bar" style="color:var(--color-blue);"></i> Top Packages by Bookings</h5><small>Most popular</small></div>
            <div class="chart-container"><canvas id="bookingsChart"></canvas></div>
        </div>
        <div class="card-premium">
            <div class="head"><h5><i class="fas fa-trophy" style="color:var(--color-yellow);"></i> Top 5 Earning Vehicles</h5><small>By revenue</small></div>
            <div class="chart-container"><canvas id="vehicleRevenueChart"></canvas></div>
        </div>
    </div>

    <!-- UPCOMING MAINTENANCE -->
    <div class="card-premium" style="margin-bottom:16px;">
        <div class="head">
            <h5><i class="fas fa-triangle-exclamation" style="color:var(--color-red);"></i> Upcoming Maintenance Alerts (Next 30 Days)</h5>
            <small><?= count($upcoming_maintenance) ?> vehicles</small>
        </div>
        <?php if (count($upcoming_maintenance) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Category</th>
                        <th>Next Maintenance</th>
                        <th>Insurance Expiry</th>
                        <th>Registration Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_maintenance as $v): 
                        $days_left = (int)((strtotime($v['next_maintenance']) - time()) / 86400);
                        $alert_class = $days_left <= 7 ? 'style="color:var(--color-red);font-weight:700;"' : '';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['vehicle_name']) ?></strong></td>
                        <td><?= htmlspecialchars($v['category']) ?></td>
                        <td <?= $alert_class ?>>
                            <?= date('d M Y', strtotime($v['next_maintenance'])) ?>
                            <?php if ($days_left <= 7): ?>
                                <span class="badge" style="background:var(--color-red);color:#fff;font-size:8px;padding:1px 8px;">⚠️ <?= $days_left ?> days</span>
                            <?php else: ?>
                                <span style="font-size:9px;color:var(--text-muted);">(<?= $days_left ?> days)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $v['insurance_expiry'] ? date('d M Y', strtotime($v['insurance_expiry'])) : 'N/A' ?></td>
                        <td><?= $v['registration_expiry'] ? date('d M Y', strtotime($v['registration_expiry'])) : 'N/A' ?></td>
                        <td><span class="status-badge <?= strtolower($v['status'] ?? 'inactive') ?>"><?= $v['status'] ?? 'Inactive' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="padding:15px 0;color:var(--text-muted);text-align:center;font-size:13px;">
            <i class="fas fa-check-circle" style="color:var(--color-green);"></i> No upcoming maintenance in the next 30 days.
        </p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    KANBAN BOARD SECTION (NEW)
    ============================================================ -->
    <div class="card-premium" style="margin-bottom:16px;">
        <div class="head">
            <h5><i class="fas fa-columns" style="color:var(--color-purple);"></i> 📋 Kanban Board – Drag & Drop Bookings</h5>
            <small>Drag cards to update status</small>
        </div>

        <!-- View Switcher -->
        <div class="view-switcher">
            <button class="btn-view active" id="kanbanViewBtn" onclick="switchView('kanban')">
                <i class="fas fa-columns"></i> Kanban Board
            </button>
            <button class="btn-view" id="tableViewBtn" onclick="switchView('table')">
                <i class="fas fa-table"></i> Table View
            </button>
        </div>

        <!-- Kanban View -->
        <div class="view-container active" id="kanbanView">
            <div class="kanban-board" id="kanbanBoard">
                <?php
                $column_config = [
                    'Upcoming' => ['icon' => 'fa-clock', 'class' => 'upcoming', 'color' => 'var(--text-muted)'],
                    'Ongoing' => ['icon' => 'fa-spinner', 'class' => 'ongoing', 'color' => 'var(--color-green)'],
                    'Completed' => ['icon' => 'fa-check-circle', 'class' => 'completed', 'color' => 'var(--color-blue)']
                ];
                foreach ($column_config as $status => $cfg):
                    $items = $kanban_groups[$status] ?? [];
                ?>
                <div class="kanban-column <?= $cfg['class'] ?>" data-status="<?= $status ?>">
                    <div class="col-header">
                        <h6><i class="fas <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;"></i> <?= $status ?></h6>
                        <span class="count"><i class="fas fa-copy"></i> <?= count($items) ?></span>
                    </div>
                    <div class="drop-zone" id="dropZone-<?= $status ?>" 
                         ondragover="onDragOver(event)" 
                         ondrop="onDrop(event, '<?= $status ?>')">
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $card): 
                                $pkg_display = !empty($card['manual_package_name']) ? $card['manual_package_name'] : ($card['package_name'] ?? 'Custom Package');
                            ?>
                            <div class="kanban-card" draggable="true" 
                                 data-id="<?= $card['booking_id'] ?>"
                                 data-status="<?= $status ?>"
                                 ondragstart="onDragStart(event)"
                                 ondragend="onDragEnd(event)">
                                <span class="card-id">#<?= $card['booking_id'] ?></span>
                                <div class="card-title"><?= htmlspecialchars($pkg_display) ?></div>
                                <div class="card-user">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($card['user_name'] ?? 'Guest') ?>
                                    <span style="margin-left:8px;font-size:10px;"><?= htmlspecialchars($card['user_email'] ?? '') ?></span>
                                </div>
                                <div class="card-meta">
                                    <span class="cost">LKR <?= number_format($card['total_cost'] ?? 0, 2) ?></span>
                                    <span class="date"><i class="fas fa-calendar-alt"></i> <?= date('d M Y', strtotime($card['booking_date'])) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="kanban-empty">
                                <i class="fas fa-inbox"></i>
                                No bookings in <?= $status ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:10px;font-size:10px;color:var(--text-muted);text-align:right;">
                <i class="fas fa-arrows-alt"></i> Drag & drop cards between columns to update status
            </div>
        </div>

        <!-- Table View (Real-time Bookings) -->
        <div class="view-container" id="tableView">
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:center;">
                <input type="text" id="userSearch" placeholder="🔍 Search by user or package..." onkeyup="filterUserBookings()" style="flex:1;min-width:180px;padding:6px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-body);color:var(--text-primary);">
                <select id="statusFilterLive" onchange="filterUserBookings()" style="padding:6px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-body);color:var(--text-primary);">
                    <option value="all">All Status</option>
                    <option value="Ongoing">🟢 Ongoing</option>
                    <option value="Completed">🔵 Completed</option>
                    <option value="Upcoming">⏳ Upcoming</option>
                </select>
                <span style="font-size:11px;color:var(--text-muted);" id="liveCount"><?= count($kanban_bookings) ?> bookings</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>User</th>
                            <th>Package</th>
                            <th>Travel Date</th>
                            <th>Duration</th>
                            <th>Cost (LKR)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="realtimeBookingsTable">
                        <?php if (count($kanban_bookings) > 0): ?>
                            <?php foreach ($kanban_bookings as $rb): 
                                $pkg_display = !empty($rb['manual_package_name']) ? $rb['manual_package_name'] : ($rb['package_name'] ?? 'Custom Package');
                                $status_badge_class = '';
                                $status = $rb['kanban_status'] ?? 'Upcoming';
                                if ($status == 'Ongoing') $status_badge_class = 'badge-ongoing';
                                elseif ($status == 'Completed') $status_badge_class = 'badge-completed';
                                else $status_badge_class = 'badge-upcoming';
                            ?>
                            <tr data-status="<?= $status ?>">
                                <td><strong>#<?= $rb['booking_id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($rb['user_name'] ?? 'Guest') ?></strong>
                                    <div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($rb['user_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span style="font-weight:600;"><?= htmlspecialchars($pkg_display) ?></span>
                                    <div style="font-size:9px;color:var(--text-muted);"><?= $rb['booking_status'] ?></div>
                                </td>
                                <td style="font-family:monospace;font-size:12px;"><?= date('d M Y', strtotime($rb['booking_date'])) ?></td>
                                <td><strong><?= $rb['number_of_days'] ?? 1 ?></strong> day(s)</td>
                                <td style="font-weight:700;color:var(--color-teal);">LKR <?= number_format($rb['total_cost'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="<?= $status_badge_class ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                                No bookings found.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================
    PACKAGE REPORT TABLE
    ============================================================ -->
    <div class="filter-bar">
        <form method="GET" action="reports.php" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; width:100%;">
            <label style="font-size:12px; font-weight:600; color:var(--text-muted);"><i class="fas fa-calendar-alt"></i> From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="width:160px;">
            <label style="font-size:12px; font-weight:600; color:var(--text-muted);">To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="width:160px;">
            <button type="submit" class="btn-filter btn-filter-primary"><i class="fas fa-filter"></i> Apply</button>
            <a href="reports.php" class="btn-filter btn-filter-danger"><i class="fas fa-times"></i> Clear</a>
            <span style="margin-left:auto; font-size:11px; color:var(--text-muted);">
                <i class="fas fa-info-circle"></i> <?= count($packages) ?> packages found
            </span>
        </form>
    </div>

    <div class="card-premium">
        <div class="head">
            <h5><i class="fas fa-list-ul" style="color:var(--color-purple);"></i> All Packages – Progress & Status Report</h5>
            <small><?= count($packages) ?> packages</small>
        </div>
        <div class="table-wrapper">
            <table id="reportTable">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Package Name</th>
                        <th>Status</th>
                        <th>Bookings</th>
                        <th>Revenue (LKR)</th>
                        <th style="min-width:140px;">Progress</th>
                        <th>Last Booking</th>
                        <th>Latest Trip Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($packages) > 0): ?>
                        <?php foreach ($packages as $pkg): 
                            $progress_percent = ($max_bookings > 0) ? round(($pkg['total_bookings'] / $max_bookings) * 100) : 0;
                            $status_class = strtolower($pkg['status']);
                            if ($status_class == 'active') $status_class = 'active';
                            elseif ($status_class == 'deleted') $status_class = 'deleted';
                            else $status_class = 'inactive';
                            $trip_status = $pkg['latest_trip_status'] ?? '⏳ No bookings';
                            $trip_class = '';
                            if (strpos($trip_status, 'Ongoing') !== false) $trip_class = 'ongoing';
                            elseif (strpos($trip_status, 'Completed') !== false) $trip_class = 'completed';
                            else $trip_class = 'upcoming';
                        ?>
                        <tr>
                            <td><strong>#<?= $pkg['id'] ?></strong></td>
                            <td><span class="text-truncate-custom" title="<?= htmlspecialchars($pkg['title']) ?>"><?= htmlspecialchars($pkg['title']) ?></span></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $pkg['status'] ?></span></td>
                            <td><strong><?= $pkg['total_bookings'] ?></strong></td>
                            <td>LKR <?= number_format($pkg['total_revenue'], 2) ?></td>
                            <td>
                                <div style="display:flex; justify-content:space-between; font-size:9px; font-weight:600; color:var(--text-muted);">
                                    <span><?= $progress_percent ?>%</span>
                                    <span><?= $pkg['total_bookings'] ?> / <?= $max_bookings ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="bar" style="width: <?= $progress_percent ?>%;"></div>
                                </div>
                            </td>
                            <td style="font-size:11px; color:var(--text-muted);">
                                <?= $pkg['last_booking_date'] ? date('d M Y', strtotime($pkg['last_booking_date'])) : 'No bookings' ?>
                            </td>
                            <td>
                                <span class="trip-status-badge <?= $trip_class ?>">
                                    <?= $trip_status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No packages found in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-premium">
        <span>© <?= date('Y') ?> <?= $config['app_name'] ?> · v<?= $config['app_version'] ?></span>
        <span><i class="fas fa-heart heart"></i> <span id="footerDateTime"></span></span>
    </footer>

</main>

<!-- ============================================================
MODALS (Same as before)
============================================================ -->
<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-coins"></i> Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="dashboard.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label>
            <select name="vehicle_id" class="form-select" required>
                <option value="">-- Select Vehicle --</option>
                <?php if ($vehicle_list_result): while($v = $vehicle_list_result->fetch_assoc()): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label">Type</label><select name="expense_type" class="form-select"><option value="fuel">Fuel</option><option value="repair">Repair</option></select></div>
        <div class="mb-2"><label class="form-label">Amount (LKR)</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <input type="hidden" name="add_expense" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form>
</div></div></div>

<!-- Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-tools"></i> Schedule Maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="dashboard.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label>
            <select name="vehicle_id" class="form-select" required>
                <option value="">-- Select Vehicle --</option>
                <?php if ($vehicle_list_result): 
                    $vehicle_list_result->data_seek(0); 
                    while($v = $vehicle_list_result->fetch_assoc()): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" class="form-control" required></div>
        <input type="hidden" name="update_maintenance" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-warning">Update</button></div></form>
</div></div></div>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file"></i> Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="dashboard.php" enctype="multipart/form-data"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label>
            <select name="vehicle_id" class="form-select" required>
                <option value="">-- Select Vehicle --</option>
                <?php if ($vehicle_list_result): 
                    $vehicle_list_result->data_seek(0); 
                    while($v = $vehicle_list_result->fetch_assoc()): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label">Document Name</label><input type="text" name="doc_name" class="form-control"></div>
        <div class="mb-2"><label class="form-label">File</label><input type="file" name="doc_file" class="form-control"></div>
        <input type="hidden" name="add_document" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Upload</button></div></form>
</div></div></div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-cog"></i> Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="dashboard.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Name</label><input type="text" name="manager_name" class="form-control" value="<?= htmlspecialchars($manager_name) ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="manager_email" class="form-control" value=""></div>
        <input type="hidden" name="update_profile" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form>
</div></div></div>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
// SIDEBAR TOGGLE
// ============================================================
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
hamburger.addEventListener('click', function() {
    sidebar.classList.toggle('open');
    this.querySelector('i').className = sidebar.classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
});
document.addEventListener('click', function(e) {
    if (!sidebar.contains(e.target) && !hamburger.contains(e.target) && window.innerWidth <= 992) {
        sidebar.classList.remove('open');
        hamburger.querySelector('i').className = 'fas fa-bars';
    }
});

// ============================================================
// CLOCK
// ============================================================
function updateClock() {
    const n = new Date();
    document.getElementById('liveDate').textContent = n.toLocaleDateString('en-US', {day:'2-digit',month:'short',year:'numeric'});
    document.getElementById('liveTime').textContent = n.toLocaleTimeString('en-US', {hour12:false,hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('footerDateTime').textContent = '🕐 ' + n.toLocaleDateString('en-US', {day:'2-digit',month:'short'}) + ' · ' + n.toLocaleTimeString('en-US', {hour12:false,hour:'2-digit',minute:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);

// ============================================================
// THEME
// ============================================================
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;
let saved = localStorage.getItem('vehicle_theme') || '<?= $config['dark_mode_default'] ?>';
html.setAttribute('data-theme', saved);
if (saved === 'dark') themeToggle.classList.add('active');

themeToggle.addEventListener('click', function() {
    const cur = html.getAttribute('data-theme');
    const next = cur === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    this.classList.toggle('active');
    localStorage.setItem('vehicle_theme', next);
    initCharts();
});

// ============================================================
// PROFILE TOGGLE (Logout)
// ============================================================
document.getElementById('profileToggle')?.addEventListener('click', function() {
    if (confirm('Logout from Fleet Manager?')) window.location.href = '../index.php?action=logout';
});

// ============================================================
// FUNCTIONS
// ============================================================
function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
}

function exportPDF() {
    const element = document.getElementById('mainContent');
    html2pdf().set({ margin: 0.5, filename: 'Package_Progress_Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'in', format: 'a3', orientation: 'landscape' } }).from(element).save();
}

function exportCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#reportTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            let text = cols[j].innerText.replace(/,/g, ';');
            row.push(text);
        }
        csv.push(row.join(","));
    }
    let csvContent = csv.join("\n");
    let link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
    link.download = 'package_report.csv';
    link.click();
}

function exportAdvancedCSV() {
    let csv = "Report Type,Item,Detail,Value,Status\n";
    // Top vehicles
    <?php foreach ($top_vehicles as $v): ?>
        csv += "Top Earning,<?= addslashes($v['vehicle_name']) ?>,<?= addslashes($v['category']) ?>,<?= $v['total_revenue'] ?>,-\\n";
    <?php endforeach; ?>
    // Maintenance alerts
    <?php foreach ($upcoming_maintenance as $v): ?>
        csv += "Maintenance Alert,<?= addslashes($v['vehicle_name']) ?>,<?= addslashes($v['category']) ?>,-,<?= $v['next_maintenance'] ?>\\n";
    <?php endforeach; ?>
    // Kanban bookings
    <?php foreach ($kanban_bookings as $rb): ?>
        csv += "User Booking,<?= addslashes($rb['user_name'] ?? 'Guest') ?>,<?= addslashes($rb['package_name'] ?? 'Custom') ?>,<?= $rb['total_cost'] ?>,<?= $rb['kanban_status'] ?>\\n";
    <?php endforeach; ?>
    let link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = 'fleet_advanced_report.csv';
    link.click();
}

function refreshData() {
    location.reload();
}

function showNotifications() {
    let count = document.getElementById('notifCount').innerText;
    if (count > 0) {
        alert('📢 You have ' + count + ' pending booking(s) waiting for approval.');
    } else {
        alert('📢 No pending notifications.');
    }
}

// ============================================================
// VIEW SWITCHER (Table / Kanban)
// ============================================================
function switchView(view) {
    document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.btn-view').forEach(el => el.classList.remove('active'));
    
    if (view === 'kanban') {
        document.getElementById('kanbanView').classList.add('active');
        document.getElementById('kanbanViewBtn').classList.add('active');
    } else {
        document.getElementById('tableView').classList.add('active');
        document.getElementById('tableViewBtn').classList.add('active');
    }
}

// ============================================================
// FILTER FOR TABLE VIEW
// ============================================================
function filterUserBookings() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const status = document.getElementById('statusFilterLive').value;
    const rows = document.querySelectorAll('#realtimeBookingsTable tr[data-status]');
    let visible = 0;
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const rowStatus = row.dataset.status || '';
        const matchSearch = text.includes(search);
        const matchStatus = status === 'all' || rowStatus === status;
        if (matchSearch && matchStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('liveCount').innerText = visible + ' bookings';
}

// ============================================================
// KANBAN BOARD - DRAG & DROP (WITH AJAX UPDATE)
// ============================================================
let draggedCard = null;
let draggedId = null;
let draggedStatus = null;

function onDragStart(event) {
    draggedCard = event.target.closest('.kanban-card');
    if (!draggedCard) return;
    draggedId = draggedCard.dataset.id;
    draggedStatus = draggedCard.dataset.status;
    event.dataTransfer.setData('text/plain', draggedId);
    event.dataTransfer.effectAllowed = 'move';
    setTimeout(() => draggedCard.classList.add('dragging'), 0);
}

function onDragEnd(event) {
    const card = event.target.closest('.kanban-card');
    if (card) card.classList.remove('dragging');
    document.querySelectorAll('.drop-zone').forEach(el => el.classList.remove('dragover'));
}

function onDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    const dropZone = event.target.closest('.drop-zone');
    if (dropZone) {
        document.querySelectorAll('.drop-zone').forEach(el => el.classList.remove('dragover'));
        dropZone.classList.add('dragover');
    }
}

function onDrop(event, newStatus) {
    event.preventDefault();
    document.querySelectorAll('.drop-zone').forEach(el => el.classList.remove('dragover'));
    
    const data = event.dataTransfer.getData('text/plain');
    if (!data) return;
    
    const card = document.querySelector(`.kanban-card[data-id="${data}"]`);
    if (!card) return;
    
    const currentStatus = card.dataset.status;
    if (currentStatus === newStatus) {
        // Same column - do nothing
        return;
    }
    
    // Move card to new column
    const targetDropZone = document.getElementById('dropZone-' + newStatus);
    if (targetDropZone) {
        // Remove empty placeholder if exists
        const emptyMsg = targetDropZone.querySelector('.kanban-empty');
        if (emptyMsg) emptyMsg.remove();
        
        card.dataset.status = newStatus;
        targetDropZone.appendChild(card);
        
        // Update counts
        updateColumnCounts();
        
        // Update database via AJAX
        updateBookingStatus(data, newStatus);
    }
}

function updateColumnCounts() {
    // Update counts for each column
    ['Upcoming', 'Ongoing', 'Completed'].forEach(status => {
        const column = document.querySelector(`.kanban-column[data-status="${status}"]`);
        if (!column) return;
        const dropZone = column.querySelector('.drop-zone');
        const countSpan = column.querySelector('.count');
        if (countSpan) {
            const count = dropZone ? dropZone.querySelectorAll('.kanban-card').length : 0;
            countSpan.innerHTML = `<i class="fas fa-copy"></i> ${count}`;
        }
    });
}

function updateBookingStatus(bookingId, newStatus) {
    // Map Kanban status to database status
    const statusMap = {
        'Upcoming': 'pending',
        'Ongoing': 'Approved',
        'Completed': 'Completed'
    };
    const dbStatus = statusMap[newStatus] || 'pending';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_kanban_status&booking_id=' + bookingId + '&new_status=' + newStatus
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Booking #' + bookingId + ' moved to ' + newStatus, 'success');
        } else {
            showToast('❌ Error: ' + data.message, 'error');
            // Reload page to revert changes
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('❌ Network error. Please refresh.', 'error');
    });
}

// ============================================================
// TOAST NOTIFICATIONS (for Kanban updates)
// ============================================================
function showToast(message, type = 'success') {
    const toastContainer = document.createElement('div');
    toastContainer.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 99999;
        background: var(--bg-card);
        backdrop-filter: blur(12px);
        padding: 14px 20px;
        border-radius: 12px;
        border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        color: var(--text-primary);
        font-weight: 500;
        font-size: 14px;
        animation: slideInRight 0.4s ease, fadeOut 0.4s ease 2.6s forwards;
        max-width: 350px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Inter', sans-serif;
    `;
    toastContainer.innerHTML = message;
    document.body.appendChild(toastContainer);
    setTimeout(() => toastContainer.remove(), 3000);
}

// ============================================================
// CHARTS
// ============================================================
let chartInstances = [];

function initCharts() {
    chartInstances.forEach(chart => chart.destroy());
    chartInstances = [];

    const isDark = html.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#94a3b8' : '#475569';

    // 1. Bookings Chart
    const labels = <?= json_encode($chart_labels) ?>;
    const bookingsData = <?= json_encode($chart_bookings) ?>;
    const colors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#ec4899', '#14b8a6', '#f97316'];

    const ctx1 = document.getElementById('bookingsChart').getContext('2d');
    const chart1 = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{ label: 'Bookings', data: bookingsData.length ? bookingsData : [0], backgroundColor: colors.slice(0, bookingsData.length || 1), borderRadius: 6, barPercentage: 0.6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => ctx.parsed.y + ' bookings' } } },
            scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { size: 9 }, stepSize: 1 } }, x: { grid: { display: false }, ticks: { color: textColor, font: { size: 8 } } } }
        }
    });
    chartInstances.push(chart1);

    // 2. Top Vehicles Chart (Doughnut)
    const vehicleLabels = <?= json_encode(array_column($top_vehicles, 'vehicle_name')) ?>;
    const vehicleData = <?= json_encode(array_column($top_vehicles, 'total_revenue')) ?>;
    const ctx2 = document.getElementById('vehicleRevenueChart').getContext('2d');
    const chart2 = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: vehicleLabels.length ? vehicleLabels : ['No Data'],
            datasets: [{
                data: vehicleData.length ? vehicleData : [1],
                backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
                borderWidth: 2,
                borderColor: isDark ? '#0b1120' : '#f0f4f8'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, font: { size: 9 } } },
                tooltip: { callbacks: { label: (ctx) => 'LKR ' + ctx.parsed.toLocaleString() } }
            }
        }
    });
    chartInstances.push(chart2);
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initCharts, 200);
});
</script>
</body>
</html>