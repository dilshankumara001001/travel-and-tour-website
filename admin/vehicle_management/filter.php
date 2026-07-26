<?php
// ============================================================
// filter.php - Advanced Vehicle Filter (Premium Glass Design)
// ============================================================
session_start();
$config = [
    'app_name' => 'Fleet Management',
    'app_version' => '3.2.1',
    'timezone' => 'Asia/Colombo',
    'auto_refresh_interval' => 30,
    'google_maps_api_key' => 'AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw',
    'dark_mode_default' => 'light',
];
date_default_timezone_set($config['timezone']);


if (!isset($_SESSION['manager_id'])) {
    header("Location: vehicle_manager_login.php");
    exit;
}

$manager_id = $_SESSION['manager_id'];

// Fetch manager name from database
$manager_name = 'Manager';
$conn = new mysqli('localhost', 'root', '', 'travel_tour_db');
if ($conn->connect_error) die("Connection Failed");
$conn->set_charset("utf8");

$name_query = $conn->query("SELECT name FROM vehicle_manager WHERE id = $manager_id");
if ($name_query && $name_query->num_rows > 0) {
    $row = $name_query->fetch_assoc();
    $manager_name = $row['name'];
    $_SESSION['manager_name'] = $manager_name;
} else {
    $manager_name = $_SESSION['manager_name'] ?? 'Manager';
}
$manager_email = $_SESSION['manager_email'] ?? '';

// ============================================================
// ADD COLUMNS IF MISSING
// ============================================================
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS start_date DATE AFTER vehicle_id");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS end_date DATE AFTER start_date");

// ============================================================
// HANDLE BULK STATUS UPDATE
// ============================================================
$bulk_msg = '';
if (isset($_POST['bulk_status']) && isset($_POST['vehicle_ids']) && is_array($_POST['vehicle_ids'])) {
    $ids = array_map('intval', $_POST['vehicle_ids']);
    $new_status = $conn->real_escape_string($_POST['bulk_status']);
    if (count($ids) > 0) {
        $id_list = implode(',', $ids);
        if ($conn->query("UPDATE vehicles SET status = '$new_status' WHERE id IN ($id_list)")) {
            $bulk_msg = count($ids) . " vehicles updated to " . ucfirst($new_status) . "!";
        }
    }
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$op_id = isset($_GET['op']) ? intval($_GET['op']) : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'id_desc';
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 999999;
$min_capacity = isset($_GET['min_capacity']) ? intval($_GET['min_capacity']) : 0;
$max_capacity = isset($_GET['max_capacity']) ? intval($_GET['max_capacity']) : 999;

// ============================================================
// BUILD DYNAMIC SQL QUERY
// ============================================================
$where = "WHERE v.operator_id = $op_id";
if ($search) $where .= " AND v.vehicle_name LIKE '%$search%'";
if ($min_price > 0) $where .= " AND v.price_per_km >= $min_price";
if ($max_price < 999999) $where .= " AND v.price_per_km <= $max_price";
if ($min_capacity > 0) $where .= " AND v.capacity >= $min_capacity";
if ($max_capacity < 999) $where .= " AND v.capacity <= $max_capacity";

if ($start_date && $end_date) {
    $where .= " AND v.id NOT IN (
        SELECT vehicle_id FROM bookings 
        WHERE (start_date <= '$end_date' AND end_date >= '$start_date') 
        AND status NOT IN ('cancelled', 'Cancelled')
        AND vehicle_id IS NOT NULL
    )";
}

$order_map = [
    'price_asc' => 'v.price_per_km ASC',
    'price_desc' => 'v.price_per_km DESC',
    'capacity_desc' => 'v.capacity DESC',
    'capacity_asc' => 'v.capacity ASC',
    'name_asc' => 'v.vehicle_name ASC',
    'name_desc' => 'v.vehicle_name DESC',
    'id_desc' => 'v.id DESC'
];
$order = $order_map[$sort] ?? 'v.id DESC';

$sql = "SELECT v.*, 
        v.driver_name AS driver_name, 
        v.driver_phone AS driver_phone,
        (SELECT COUNT(*) FROM bookings b WHERE b.vehicle_id = v.id AND b.status NOT IN ('cancelled', 'Cancelled') AND b.end_date >= CURDATE()) AS active_bookings
        FROM vehicles v 
        $where 
        ORDER BY $order";

$result = $conn->query($sql);
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

// ============================================================
// STATS
// ============================================================
$total_vehicles = count($vehicles);
$available = 0; $booked = 0; $maintenance = 0; $total_price = 0;
$max_cap = 0; $min_cap = 999;

foreach ($vehicles as $v) {
    $status_lower = strtolower($v['status'] ?? '');
    if ($status_lower == 'available') $available++;
    elseif ($status_lower == 'booked') $booked++;
    elseif ($status_lower == 'maintenance') $maintenance++;
    $total_price += $v['price_per_km'];
    if ($v['capacity'] > $max_cap) $max_cap = $v['capacity'];
    if ($v['capacity'] < $min_cap) $min_cap = $v['capacity'];
}
$avg_price = ($total_vehicles > 0) ? round($total_price / $total_vehicles, 2) : 0;
$total_all = $conn->query("SELECT COUNT(*) as total FROM vehicles")->fetch_assoc()['total'];

// ============================================================
// FETCH VEHICLES LIST FOR DROPDOWNS (for modals)
// ============================================================
$vehicles_list = [];
$res = $conn->query("SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name");
while ($row = $res->fetch_assoc()) $vehicles_list[] = $row;

$theme = $_COOKIE['vehicle_theme'] ?? 'light';
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚗 Filter Vehicles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================================
           THEME VARIABLES - Premium Glass (no video)
        ============================================================ */
        :root {
            --bg-body: #f0f4f8;
            --bg-card: rgba(255,255,255,0.72);
            --text-primary: #0f172a;
            --text-secondary: #1e293b;
            --text-muted: #64748b;
            --border-card: rgba(255,255,255,0.35);
            --shadow-card: 0 8px 40px rgba(0,0,0,0.05);
            --shadow-hover: 0 16px 56px rgba(37,99,235,0.12);
            --color-blue: #2563eb;
            --color-green: #10b981;
            --color-yellow: #f59e0b;
            --color-red: #ef4444;
            --color-purple: #8b5cf6;
            --color-teal: #14b8a6;
            --color-orange: #f97316;
            --color-pink: #ec4899;
            --gradient-primary: linear-gradient(135deg, #2563eb, #7c3aed);
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --glass-blur: blur(24px) saturate(1.6);
            --radius: 16px;
            --transition: 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(96, 165, 250, 0.15);
            --sidebar-border: rgba(255, 255, 255, 0.06);
            --btn-close-filter: invert(0);
        }

        [data-theme="dark"] {
            --bg-body: #0b1120;
            --bg-card: rgba(18,30,50,0.65);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-card: rgba(56,189,248,0.08);
            --shadow-card: 0 8px 40px rgba(0,0,0,0.4);
            --shadow-hover: 0 16px 56px rgba(37,99,235,0.18);
            --color-blue: #60a5fa;
            --color-green: #34d399;
            --color-yellow: #fbbf24;
            --color-red: #f87171;
            --color-purple: #a78bfa;
            --color-teal: #2dd4bf;
            --gradient-primary: linear-gradient(135deg, #60a5fa, #a78bfa);
            --sidebar-bg: linear-gradient(180deg, #0b1120 0%, #141428 100%);
            --btn-close-filter: invert(1);
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 400;
            transition: all var(--transition);
            min-height: 100vh;
            padding-bottom: 30px;
            overflow-x: hidden;
        }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--color-blue); border-radius: 8px; }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            position: fixed; left: 0; top: 0;
            height: 100vh; width: 270px;
            background: var(--sidebar-bg);
            padding: 24px 18px;
            display: flex; flex-direction: column;
            z-index: 1000;
            transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.04);
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 32px; padding-left: 4px;
        }
        .sidebar-brand .brand-icon {
            width: 44px; height: 44px;
            border-radius: 14px;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 22px;
            box-shadow: 0 6px 20px rgba(37,99,235,0.25);
        }
        .sidebar-brand span {
            font-size: 20px; font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .sidebar-brand small {
            font-size: 8px; font-weight: 600;
            color: rgba(255,255,255,0.25);
            display: block;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: -2px;
        }
        .sidebar-nav { flex: 1; }
        .sidebar-nav .nav-label {
            font-size: 8px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.5px;
            color: rgba(255,255,255,0.25);
            padding: 12px 12px 4px;
            opacity: 0.6;
        }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: rgba(255,255,255,0.35);
            text-decoration: none;
            font-size: 14px; font-weight: 600;
            transition: all 0.25s ease;
        }
        .sidebar-nav a i { width: 22px; font-size: 16px; text-align: center; }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
            transform: translateX(4px);
        }
        .sidebar-nav a.active {
            background: rgba(59,130,246,0.12);
            color: #60a5fa;
            box-shadow: inset 3px 0 0 #60a5fa;
        }
        .sidebar-nav a .badge-nav {
            margin-left: auto;
            background: var(--color-red);
            color: #fff;
            font-size: 8px;
            padding: 1px 10px;
            border-radius: 20px;
        }
        .sidebar-nav .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.04);
            padding-top: 14px;
            color: rgba(255,255,255,0.2);
        }
        .sidebar-nav .logout-link:hover {
            color: var(--color-red);
            background: rgba(239,68,68,0.08);
        }
        .hamburger {
            display: none;
            position: fixed; top: 16px; left: 16px;
            z-index: 1001;
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-primary);
            box-shadow: var(--shadow-card);
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main-content {
            margin-left: 270px;
            flex: 1;
            padding: 20px 28px 30px;
            min-height: 100vh;
            width: calc(100% - 270px);
            transition: margin-left 0.35s cubic-bezier(0.16,1,0.3,1);
        }

        /* ============================================================
           TOP HEADER
        ============================================================ */
        .top-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 24px;
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-card);
            margin-bottom: 16px;
            flex-wrap: wrap; gap: 12px;
        }
        .top-header .greeting h4 {
            font-size: 18px; font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }
        .top-header .greeting h4 span {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .top-header .greeting p {
            font-size: 12px; color: var(--text-muted);
            margin: 0;
        }
        .header-actions {
            display: flex; align-items: center; gap: 14px;
            flex-wrap: wrap;
        }
        .header-actions .clock {
            font-family: monospace;
            font-size: 14px; font-weight: 700;
            color: var(--text-primary);
            background: var(--bg-body);
            padding: 5px 16px;
            border-radius: 30px;
            border: 1px solid var(--border-card);
            display: flex; align-items: center; gap: 8px;
        }
        .header-actions .clock .dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--color-green);
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0%,100% { opacity:1; transform:scale(1); }
            50% { opacity:0.3; transform:scale(0.6); }
        }
        .header-actions .notif-bell {
            position: relative;
            font-size: 20px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .header-actions .notif-bell:hover {
            color: var(--color-blue);
            background: rgba(37,99,235,0.06);
        }
        .header-actions .notif-bell .badge-count {
            position: absolute; top: -4px; right: -2px;
            background: var(--color-red);
            color: #fff;
            font-size: 9px; font-weight: 700;
            min-width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--bg-card);
        }
        .theme-toggle {
            width: 44px; height: 22px;
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .theme-toggle .dot-toggle {
            position: absolute; top: 2px; left: 2px;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: var(--color-blue);
            transition: all 0.35s cubic-bezier(0.68,-0.55,0.27,1.55);
            box-shadow: 0 2px 8px rgba(37,99,235,0.2);
        }
        .theme-toggle.active .dot-toggle { left: 24px; }
        .profile-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 14px;
            cursor: pointer;
            border: 2px solid var(--border-card);
            transition: all 0.3s ease;
            position: relative;
        }
        .profile-avatar:hover { transform: scale(1.05); }
        .profile-avatar .status-dot {
            position: absolute; bottom: 0; right: 0;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--color-green);
            border: 2px solid var(--bg-card);
        }

        /* ============================================================
           STATS CARDS
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 14px 16px;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
            display: flex; align-items: center; gap: 14px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(37,99,235,0.15);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .icon-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px;
            flex-shrink: 0;
        }
        .stat-card .icon-wrap.green { background: var(--gradient-success); }
        .stat-card .icon-wrap.yellow { background: var(--gradient-warning); }
        .stat-card .icon-wrap.red { background: var(--gradient-danger); }
        .stat-card .icon-wrap.blue { background: var(--gradient-primary); }
        .stat-card .icon-wrap.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
        .stat-card .icon-wrap.teal { background: linear-gradient(135deg, #14b8a6, #2dd4bf); }
        .stat-card .info .number {
            font-size: 1.5rem; font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .stat-card .info .label {
            font-size: 10px; font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ============================================================
           OPERATOR TABS (Glass)
        ============================================================ */
        .operator-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .operator-tabs .btn-op {
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid var(--border-card);
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            color: var(--text-secondary);
            transition: var(--transition);
            text-decoration: none;
            cursor: pointer;
            box-shadow: var(--shadow-card);
        }
        .operator-tabs .btn-op:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        .operator-tabs .btn-op.active-op1 {
            border-color: var(--color-green);
            color: var(--color-green);
            background: rgba(16,185,129,0.08);
        }
        .operator-tabs .btn-op.active-op2 {
            border-color: var(--color-blue);
            color: var(--color-blue);
            background: rgba(37,99,235,0.08);
        }
        .operator-tabs .btn-op.active-op3 {
            border-color: var(--color-yellow);
            color: #92400e;
            background: rgba(245,158,11,0.08);
        }
        [data-theme="dark"] .operator-tabs .btn-op.active-op3 {
            color: var(--color-yellow);
        }
        .operator-tabs .btn-op i { margin-right: 5px; }
        .operator-tabs .btn-op .badge-count {
            background: var(--bg-body);
            color: var(--text-muted);
            padding: 0 8px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 4px;
        }

        /* ============================================================
           FILTER BAR (Glass)
        ============================================================ */
        .filter-bar {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            box-shadow: var(--shadow-card);
            margin-bottom: 16px;
            transition: all var(--transition);
        }
        .filter-bar .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: flex-end;
        }
        .filter-bar .filter-group .field {
            flex: 1;
            min-width: 100px;
        }
        .filter-bar .filter-group .field-sm {
            flex: 0 0 80px;
            min-width: 60px;
        }
        .filter-bar label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 2px;
        }
        .filter-bar .form-control,
        .filter-bar .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-primary);
            font-size: 12px;
            border-radius: 8px;
            padding: 4px 10px;
            height: 32px;
            width: 100%;
            transition: all var(--transition);
        }
        .filter-bar .form-control:focus,
        .filter-bar .form-select:focus {
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .filter-bar .btn-filter {
            background: var(--color-blue);
            color: #fff;
            border: none;
            padding: 4px 18px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            height: 32px;
            transition: all var(--transition);
        }
        .filter-bar .btn-filter:hover { opacity: 0.85; }
        .filter-bar .btn-reset {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-secondary);
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 12px;
            height: 32px;
            transition: all var(--transition);
        }
        .filter-bar .btn-reset:hover { background: var(--border-card); }

        /* ============================================================
           BULK BAR (Glass)
        ============================================================ */
        .bulk-bar {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 10px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-card);
        }
        .bulk-bar .label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .bulk-bar .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-primary);
            font-size: 12px;
            padding: 4px 10px;
            height: 32px;
            border-radius: 6px;
            min-width: 130px;
        }
        .bulk-bar .btn {
            font-size: 12px;
            padding: 4px 14px;
            border-radius: 6px;
        }
        .bulk-bar .alert {
            margin-bottom: 0;
            padding: 4px 12px;
            font-size: 12px;
        }
        .bulk-bar .alert .btn-close { font-size: 10px; padding: 2px; }

        /* ============================================================
           VEHICLE GRID (Glass Cards)
        ============================================================ */
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .vehicle-card {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
        }
        .vehicle-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--color-blue);
        }
        .vehicle-card .top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .vehicle-card .name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .vehicle-card .name small {
            font-weight: 400;
            font-size: 10px;
            color: var(--text-muted);
            display: block;
        }
        .vehicle-card .badge-status {
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .badge-status.available { background: rgba(16,185,129,0.15); color: var(--color-green); }
        .badge-status.booked { background: rgba(245,158,11,0.15); color: var(--color-yellow); }
        .badge-status.maintenance { background: rgba(239,68,68,0.15); color: var(--color-red); }

        .vehicle-card .info-row {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 4px 0;
        }
        .vehicle-card .info-row i { width: 16px; color: var(--text-muted); }
        .vehicle-card .info-row strong { color: var(--text-primary); }

        .vehicle-card .driver-row {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 5px 10px;
            margin: 6px 0;
            font-size: 11px;
        }
        .vehicle-card .driver-row i { color: var(--color-blue); }
        .vehicle-card .driver-row .no-driver { color: var(--text-muted); font-style: italic; }

        .vehicle-card .booking-badge {
            display: inline-block;
            background: rgba(245,158,11,0.15);
            color: var(--color-yellow);
            padding: 0 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            margin-left: 4px;
        }

        .vehicle-card .actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-card);
        }
        .vehicle-card .actions-row .btn {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 6px;
        }
        .vehicle-card .actions-row .form-check-input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .vehicle-card .actions-row .form-check-label {
            font-size: 10px;
            color: var(--text-muted);
            margin-left: 4px;
        }

        /* ============================================================
           COMPARE MODAL (Glass)
        ============================================================ */
        .modal-content {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            color: var(--text-primary);
            border: 1px solid var(--border-card);
        }
        .modal-header, .modal-footer { border-color: var(--border-card); }
        .modal-header .btn-close { filter: var(--btn-close-filter); }
        .compare-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-primary);
        }
        .compare-table th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 11px;
            padding: 8px 12px;
        }
        .compare-table tr:hover td { background: rgba(0,0,0,0.02); }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer {
            margin-top: 24px;
            padding: 12px 0;
            border-top: 1px solid var(--border-card);
            text-align: center;
            color: var(--text-muted);
            font-size: 11px;
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            .hamburger { display: block; }
            .stats-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .top-header { flex-direction: column; align-items: flex-start; }
            .top-header .header-actions { width: 100%; justify-content: space-between; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .icon-wrap { width: 34px; height: 34px; font-size: 14px; }
            .stat-card .info .number { font-size: 1.2rem; }
            .vehicle-grid { grid-template-columns: 1fr; }
            .filter-bar .filter-group .field { flex: 1 1 100%; }
            .filter-bar .filter-group .field-sm { flex: 1 1 45%; }
            .bulk-bar { flex-direction: column; align-items: stretch; }
            .operator-tabs { justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        /* ============================================================
           PRINT
        ============================================================ */
        @media print {
            .no-print { display: none !important; }
            .vehicle-grid { display: block !important; }
            .vehicle-card {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                margin-bottom: 10px !important;
                background: #fff !important;
                color: #000 !important;
                box-shadow: none !important;
            }
            body { background: #fff !important; }
            .main-content { padding: 10px !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
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
        <div class="brand-icon"><i class="fas fa-car-side"></i></div>
        <div>
            <span>Fleet Manager</span>
            <small>v3.2.1</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Navigation</div>
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="vehicles.php"><i class="fas fa-list"></i> Vehicles <span class="badge-nav"><?= $total_all ?></span></a>
        <a href="filter.php" class="active"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php"><i class="fas fa-user"></i> Drivers <span class="badge-nav">0</span></a>
        <div class="nav-label" style="margin-top:8px;">Management</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-coins"></i> Add Expense</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal"><i class="fas fa-tools"></i> Maintenance</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#documentModal"><i class="fas fa-file"></i> Documents</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="fas fa-sync-alt"></i> Update Status</a>
        <div class="nav-label" style="margin-top:8px;">Tools</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user-cog"></i> Profile</a>
        <a href="#" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="#" onclick="toggleFullscreen()"><i class="fas fa-expand"></i> Full Screen</a>
        <a href="#" onclick="refreshData()"><i class="fas fa-sync"></i> Refresh</a>
        <a href="../index.php?action=logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ============================================================
MAIN CONTENT
============================================================ -->
<main class="main-content">

    <!-- ===== TOP HEADER ===== -->
    <header class="top-header">
        <div class="greeting">
            <h4><span><?= $greeting ?></span>, <?= htmlspecialchars($manager_name) ?> 👋</h4>
            <p><i class="fas fa-flag-checkered"></i> Advanced Vehicle Filter</p>
        </div>
        <div class="header-actions">
            <div class="clock"><span class="dot"></span><span id="liveTime">--:--:--</span></div>
            <div class="notif-bell" onclick="showNotifications()"><i class="fas fa-bell"></i><span class="badge-count">0</span></div>
            <div class="theme-toggle" id="themeToggle"><div class="dot-toggle"></div></div>
            <div class="profile-avatar" id="profileToggle">
                <?= strtoupper(substr($manager_name, 0, 1)) ?>
                <span class="status-dot"></span>
            </div>
        </div>
    </header>

    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div>
            <h4 style="font-weight:800; color:var(--text-primary); margin:0;"><i class="fas fa-filter" style="color:var(--color-blue);"></i> Filter Vehicles</h4>
            <small style="color:var(--text-muted);">Operator #<?= $op_id ?> · <?= $total_vehicles ?> vehicles found</small>
        </div>
        <div class="actions no-print" style="display:flex; gap:8px; flex-wrap:wrap;">
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="fas fa-print"></i> Print</button>
            <a href="filter.php?op=<?= $op_id ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync"></i> Reset</a>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card"><div class="icon-wrap blue"><i class="fas fa-car"></i></div><div class="info"><div class="number"><?= $total_vehicles ?></div><div class="label">Total</div></div></div>
        <div class="stat-card"><div class="icon-wrap green"><i class="fas fa-check-circle"></i></div><div class="info"><div class="number"><?= $available ?></div><div class="label">Available</div></div></div>
        <div class="stat-card"><div class="icon-wrap yellow"><i class="fas fa-calendar-check"></i></div><div class="info"><div class="number"><?= $booked ?></div><div class="label">Booked</div></div></div>
        <div class="stat-card"><div class="icon-wrap red"><i class="fas fa-wrench"></i></div><div class="info"><div class="number"><?= $maintenance ?></div><div class="label">Maintenance</div></div></div>
        <div class="stat-card"><div class="icon-wrap purple"><i class="fas fa-money-bill-wave"></i></div><div class="info"><div class="number">LKR <?= number_format($avg_price, 2) ?></div><div class="label">Avg Price</div></div></div>
        <div class="stat-card"><div class="icon-wrap teal"><i class="fas fa-arrows-alt-h"></i></div><div class="info"><div class="number"><?= $min_cap == 999 ? '—' : $min_cap . '–' . $max_cap ?></div><div class="label">Seats</div></div></div>
    </div>

    <!-- ===== OPERATOR TABS ===== -->
    <div class="operator-tabs no-print">
        <a href="filter.php?op=1" class="btn-op <?= $op_id == 1 ? 'active-op1' : '' ?>">
            <i class="fas fa-user-tie"></i> Operator 1
            <span class="badge-count"><?= $conn->query("SELECT COUNT(*) c FROM vehicles WHERE operator_id=1")->fetch_assoc()['c'] ?></span>
        </a>
        <a href="filter.php?op=2" class="btn-op <?= $op_id == 2 ? 'active-op2' : '' ?>">
            <i class="fas fa-user-tie"></i> Operator 2
            <span class="badge-count"><?= $conn->query("SELECT COUNT(*) c FROM vehicles WHERE operator_id=2")->fetch_assoc()['c'] ?></span>
        </a>
        <a href="filter.php?op=3" class="btn-op <?= $op_id == 3 ? 'active-op3' : '' ?>">
            <i class="fas fa-user-tie"></i> Operator 3
            <span class="badge-count"><?= $conn->query("SELECT COUNT(*) c FROM vehicles WHERE operator_id=3")->fetch_assoc()['c'] ?></span>
        </a>
        <span style="font-size:11px; color:var(--text-muted); margin-left:8px;">
            <i class="fas fa-keyboard"></i> Alt+1/2/3
        </span>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <form method="GET" class="filter-bar no-print">
        <input type="hidden" name="op" value="<?= $op_id ?>">
        <div class="filter-group">
            <div class="field">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="form-control" placeholder="Vehicle name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="field-sm">
                <label>Min Price</label>
                <input type="number" name="min_price" class="form-control" placeholder="0" value="<?= $min_price > 0 ? $min_price : '' ?>">
            </div>
            <div class="field-sm">
                <label>Max Price</label>
                <input type="number" name="max_price" class="form-control" placeholder="All" value="<?= $max_price < 999999 ? $max_price : '' ?>">
            </div>
            <div class="field-sm">
                <label>Min Seats</label>
                <input type="number" name="min_capacity" class="form-control" placeholder="0" value="<?= $min_capacity > 0 ? $min_capacity : '' ?>">
            </div>
            <div class="field-sm">
                <label>Max Seats</label>
                <input type="number" name="max_capacity" class="form-control" placeholder="All" value="<?= $max_capacity < 999 ? $max_capacity : '' ?>">
            </div>
            <div class="field" style="flex:0 0 130px;">
                <label><i class="fas fa-sort"></i> Sort</label>
                <select name="sort" class="form-select">
                    <option value="id_desc" <?= $sort == 'id_desc' ? 'selected' : '' ?>>Latest</option>
                    <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Price ↑</option>
                    <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Price ↓</option>
                    <option value="capacity_desc" <?= $sort == 'capacity_desc' ? 'selected' : '' ?>>Capacity ↓</option>
                    <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                </select>
            </div>
            <div class="field" style="flex:0 0 200px;">
                <label><i class="fas fa-calendar-alt"></i> Available</label>
                <div class="d-flex gap-1">
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" placeholder="From" style="font-size:11px;">
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" placeholder="To" style="font-size:11px;">
                </div>
            </div>
            <div class="field" style="flex:0 0 auto;">
                <label>&nbsp;</label>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                    <a href="filter.php?op=<?= $op_id ?>" class="btn-reset"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </div>
    </form>

    <!-- ===== BULK BAR ===== -->
    <form method="POST" class="bulk-bar no-print" id="bulkForm">
        <?php if ($bulk_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✅ <?= $bulk_msg ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <span class="label"><i class="fas fa-check-double"></i> Bulk:</span>
        <select name="bulk_status" class="form-select">
            <option value="available">Available</option>
            <option value="booked">Booked</option>
            <option value="maintenance">Maintenance</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Update selected vehicles?')">
            <i class="fas fa-save"></i> Update
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllBtn">
            <i class="fas fa-check-square"></i> All
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">
            <i class="fas fa-square"></i> None
        </button>
        <button type="button" class="btn btn-outline-info btn-sm" id="compareBtn">
            <i class="fas fa-balance-scale"></i> Compare
        </button>
        <span style="font-size:10px; color:var(--text-muted); margin-left:auto;">
            <i class="fas fa-keyboard"></i> Ctrl+A = Select All
        </span>
    </form>

    <!-- ===== VEHICLE GRID ===== -->
    <div class="vehicle-grid">
        <?php if (count($vehicles) > 0): ?>
            <?php foreach ($vehicles as $v):
                $status_lower = strtolower($v['status'] ?? 'available');
                $has_driver = !empty($v['driver_name']);
            ?>
            <div class="vehicle-card" data-id="<?= $v['id'] ?>">
                <div class="top-row">
                    <div class="name">
                        #<?= $v['id'] ?> <?= htmlspecialchars($v['vehicle_name']) ?>
                        <small>
                            <i class="fas fa-users"></i> <?= $v['capacity'] ?> seats ·
                            <i class="fas fa-money-bill-wave" style="color:var(--color-green);"></i> LKR <?= number_format($v['price_per_km'], 2) ?>/km
                            <?php if ($v['petrol_rate']): ?> · ⛽ <?= $v['petrol_rate'] ?>/L<?php endif; ?>
                        </small>
                    </div>
                    <span class="badge-status <?= $status_lower ?>"><?= $v['status'] ?? 'Available' ?></span>
                </div>

                <div class="driver-row">
                    <i class="fas fa-user"></i> Driver:
                    <?php if ($has_driver): ?>
                        <strong><?= htmlspecialchars($v['driver_name']) ?></strong>
                        <?php if ($v['driver_phone']): ?>
                            <span style="color:var(--text-muted);">(<?= htmlspecialchars($v['driver_phone']) ?>)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="no-driver">Not assigned</span>
                    <?php endif; ?>
                    <?php if (($v['active_bookings'] ?? 0) > 0): ?>
                        <span class="booking-badge">
                            <i class="fas fa-calendar-check"></i> <?= $v['active_bookings'] ?> active
                        </span>
                    <?php endif; ?>
                </div>

                <div class="info-row">
                    <i class="fas fa-user-tie"></i> Operator: <strong>#<?= $v['operator_id'] ?></strong>
                    <?php if ($v['last_update']): ?>
                        &nbsp;· <i class="fas fa-clock"></i> <?= date('d M Y', strtotime($v['last_update'])) ?>
                    <?php endif; ?>
                    <?php if ($v['next_maintenance']): ?>
                        &nbsp;· <i class="fas fa-tools"></i> Service: <?= date('d M Y', strtotime($v['next_maintenance'])) ?>
                    <?php endif; ?>
                </div>

                <div class="actions-row">
                    <div class="d-flex gap-1">
                        <a href="update.php?id=<?= $v['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="vehicles.php?search=<?= urlencode($v['vehicle_name']) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-search"></i>
                        </a>
                    </div>
                    <div>
                        <input type="checkbox" name="vehicle_ids[]" value="<?= $v['id'] ?>" class="form-check-input vehicle-checkbox" id="cb_<?= $v['id'] ?>">
                        <label class="form-check-label" for="cb_<?= $v['id'] ?>">Select</label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5" style="grid-column:1/-1;">
                <i class="fas fa-search fa-3x mb-3" style="color:var(--text-muted);"></i>
                <h5 style="color:var(--text-primary);">No vehicles found</h5>
                <p class="text-muted">Try adjusting your filters or assign vehicles to this operator.</p>
                <a href="update.php" class="btn btn-primary btn-sm">Assign Vehicles</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        &copy; <?= date('Y') ?> Travel &amp; Tour ·
        <span id="footerTime"></span> ·
        Total: <?= $total_all ?> vehicles
        <span style="color:var(--text-muted);">· <i class="fas fa-keyboard"></i> Alt+1/2/3 | Ctrl+Shift+D</span>
    </div>

</main>

<!-- ============================================================
MODALS (Glass)
============================================================ -->

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-coins"></i> Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="vehicles.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Type</label><select name="expense_type" class="form-select"><option value="fuel">Fuel</option><option value="repair">Repair</option><option value="insurance">Insurance</option><option value="tax">Tax</option><option value="other">Other</option></select></div>
        <div class="mb-2"><label class="form-label">Amount (LKR)</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-2"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
        <input type="hidden" name="add_expense" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form>
</div></div></div>

<!-- Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-tools"></i> Schedule Maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="vehicles.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" class="form-control" required></div>
        <input type="hidden" name="update_maintenance" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-warning">Update</button></div></form>
</div></div></div>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file"></i> Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data" action="vehicles.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Document Type</label><select name="doc_type" class="form-select"><option value="insurance">Insurance</option><option value="registration">Registration</option><option value="emission">Emission Test</option><option value="other">Other</option></select></div>
        <div class="mb-2"><label class="form-label">Document Name</label><input type="text" name="doc_name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Expiry Date</label><input type="date" name="doc_expiry" class="form-control"></div>
        <div class="mb-2"><label class="form-label">File</label><input type="file" name="doc_file" class="form-control" required></div>
        <input type="hidden" name="add_document" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Upload</button></div></form>
</div></div></div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-sync-alt"></i> Update Vehicle Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="vehicles.php">
        <div class="modal-body">
            <div class="mb-2"><label class="form-label">Select Vehicle</label>
                <select name="vehicle_id" id="statusVehicleSelect" class="form-select" required>
                    <?php foreach($vehicles_list as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2"><label class="form-label">New Status</label>
                <select name="new_status" class="form-select" required>
                    <option value="Available">Available</option>
                    <option value="Booked">Booked</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
            <input type="hidden" name="update_vehicle_status" value="1">
            <div class="alert alert-info" style="font-size:10px; margin-top:8px;">
                <i class="fas fa-info-circle"></i> Changing status will update the vehicle record.
            </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Update Status</button></div>
    </form>
</div></div></div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-cog"></i> Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="vehicles.php"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Name</label><input type="text" name="manager_name" class="form-control" value="<?= htmlspecialchars($manager_name) ?>" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="manager_email" class="form-control" value="<?= htmlspecialchars($_SESSION['manager_email'] ?? '') ?>" required></div>
        <hr><h6>Change Password</h6>
        <div class="mb-2"><label class="form-label">Old Password</label><input type="password" name="old_password" class="form-control" placeholder="Enter old"></div>
        <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Enter new"></div>
        <input type="hidden" name="update_profile" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form>
</div></div></div>

<!-- Notification Modal -->
<div class="modal fade" id="notifModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-bell"></i> Notifications</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="text-muted text-center py-4">No new notifications</p></div>
</div></div></div>

<!-- Compare Modal -->
<div class="modal fade" id="compareModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-balance-scale"></i> Compare Vehicles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="compareModalBody">
                <p class="text-muted">Select at least 2 vehicles to compare.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
// 1. SIDEBAR TOGGLE
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
// 2. CLOCK (DATE + TIME)
// ============================================================
function updateClock() {
    const n = new Date();
    const opts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    document.getElementById('liveTime').textContent = n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('footerTime').textContent = '🕐 ' + n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit'});
}
updateClock(); 
setInterval(updateClock, 1000);

// ============================================================
// 3. THEME
// ============================================================
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;
let saved = localStorage.getItem('vehicle_theme') || 'light';
html.setAttribute('data-theme', saved);
if (saved === 'dark') themeToggle.classList.add('active');
themeToggle.addEventListener('click', function() {
    const cur = html.getAttribute('data-theme');
    const next = cur === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    this.classList.toggle('active');
    localStorage.setItem('vehicle_theme', next);
});

// ============================================================
// 4. PROFILE
// ============================================================
document.getElementById('profileToggle')?.addEventListener('click', function() {
    if (confirm('Logout from Fleet Manager?')) window.location.href = '../index.php?action=logout';
});

// ============================================================
// 5. SELECT ALL / NONE
// ============================================================
document.getElementById('selectAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('deselectAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-checkbox').forEach(cb => cb.checked = false);
});

// ============================================================
// 6. COMPARE
// ============================================================
document.getElementById('compareBtn')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.vehicle-checkbox:checked');
    if (checked.length < 2) {
        alert('Please select at least 2 vehicles to compare.');
        return;
    }
    const ids = Array.from(checked).map(cb => cb.value);
    let html = `<table class="table compare-table">
        <tr><th style="width:120px;">Feature</th>`;
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const name = card?.querySelector('.name')?.textContent?.trim()?.split('\n')[0]?.trim() || 'Vehicle';
        html += `<th>${name}</th>`;
    });
    html += '</tr>';

    // Status
    html += '<tr><td><strong>Status</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const status = card?.querySelector('.badge-status')?.textContent?.trim() || 'N/A';
        html += `<td>${status}</td>`;
    });
    html += '</tr>';

    // Capacity & Price
    html += '<tr><td><strong>Capacity</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const text = card?.querySelector('.name small')?.textContent || '';
        const cap = text.match(/(\d+)\s*seats/)?.[1] || 'N/A';
        html += `<td>${cap}</td>`;
    });
    html += '</tr>';

    html += '<tr><td><strong>Price/km</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const text = card?.querySelector('.name small')?.textContent || '';
        const price = text.match(/LKR\s*([\d,]+\.?\d*)/)?.[1] || 'N/A';
        html += `<td>LKR ${price}</td>`;
    });
    html += '</tr>';

    // Driver
    html += '<tr><td><strong>Driver</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const driver = card?.querySelector('.driver-row strong')?.textContent?.trim() || 'Not assigned';
        html += `<td>${driver}</td>`;
    });
    html += '</tr>';

    // Driver Phone
    html += '<tr><td><strong>Driver Phone</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const phone = card?.querySelector('.driver-row span')?.textContent?.replace(/[()]/g, '')?.trim() || 'N/A';
        html += `<td>${phone}</td>`;
    });
    html += '</tr>';

    // Operator
    html += '<tr><td><strong>Operator</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const op = card?.querySelector('.info-row strong')?.textContent?.trim() || 'N/A';
        html += `<td>${op}</td>`;
    });
    html += '</tr>';

    // Active Bookings
    html += '<tr><td><strong>Active Bookings</strong></td>';
    ids.forEach(id => {
        const card = document.querySelector(`.vehicle-card[data-id="${id}"]`);
        const badge = card?.querySelector('.booking-badge');
        const count = badge?.textContent?.match(/(\d+)/)?.[1] || '0';
        html += `<td>${count}</td>`;
    });
    html += '</tr>';

    html += '</table>';
    document.getElementById('compareModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('compareModal')).show();
});

// ============================================================
// 7. KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        themeToggle.click();
    }
    if (e.altKey && ['1', '2', '3'].includes(e.key)) {
        e.preventDefault();
        window.location.href = 'filter.php?op=' + e.key;
    }
    if (e.ctrlKey && e.key === 'a' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '')) {
        e.preventDefault();
        document.getElementById('selectAllBtn')?.click();
    }
});

// ============================================================
// 8. FUNCTIONS
// ============================================================
function showNotifications() {
    new bootstrap.Modal(document.getElementById('notifModal')).show();
}

function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
}

function exportPDF() {
    const element = document.querySelector('.main-content');
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set({ margin: 0.5, filename: 'Filter_Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'in', format: 'a3', orientation: 'landscape' } }).from(element).save();
    } else {
        window.print();
    }
}

function refreshData() {
    location.reload();
}

console.log('%c🚗 Filter (Premium Glass Design)', 'font-size:18px; font-weight:bold; color:#2563eb;');
console.log('%c✅ Sidebar | Glass-morphism | All functions working', 'font-size:13px; color:#10b981;');
</script>

<?php $conn->close(); ?>
</body>
</html>