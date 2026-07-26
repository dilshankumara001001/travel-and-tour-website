<?php
// ============================================================
// vehicles.php - Super Vehicle Management (Premium Glass Design)
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
// AUTO ADD MISSING COLUMNS
// ============================================================
$columns_to_add = [
    'category' => "ALTER TABLE vehicles ADD COLUMN category VARCHAR(50) DEFAULT 'Sedan' AFTER vehicle_name",
    'insurance_expiry' => "ALTER TABLE vehicles ADD COLUMN insurance_expiry DATE NULL AFTER next_maintenance",
    'registration_expiry' => "ALTER TABLE vehicles ADD COLUMN registration_expiry DATE NULL AFTER insurance_expiry",
    'fuel_type' => "ALTER TABLE vehicles ADD COLUMN fuel_type VARCHAR(20) DEFAULT 'Petrol' AFTER registration_expiry",
    'transmission' => "ALTER TABLE vehicles ADD COLUMN transmission VARCHAR(20) DEFAULT 'Manual' AFTER fuel_type",
    'created_at' => "ALTER TABLE vehicles ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];
foreach ($columns_to_add as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM vehicles LIKE '$col'");
    if ($check->num_rows == 0) $conn->query($sql);
}

$check_img = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'image'");
if ($check_img->num_rows > 0) $conn->query("ALTER TABLE vehicles DROP COLUMN image");

$conn->query("CREATE TABLE IF NOT EXISTS vehicle_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    old_data TEXT NULL,
    new_data TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

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
            $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, new_data, ip_address) 
                          VALUES (0, $manager_id, 'bulk_status_update', '$new_status for IDs: $id_list', '{$_SERVER['REMOTE_ADDR']}')");
        }
    }
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, ip_address) 
                  VALUES ($id, $manager_id, 'deleted', '{$_SERVER['REMOTE_ADDR']}')");
    $conn->query("DELETE FROM vehicles WHERE id = $id");
    header("Location: vehicles.php?msg=deleted");
    exit;
}

// ============================================================
// HANDLE INLINE EDIT (AJAX)
// ============================================================
if (isset($_POST['inline_edit'])) {
    $id = (int)$_POST['id'];
    $field = $conn->real_escape_string($_POST['field']);
    $value = $conn->real_escape_string($_POST['value']);
    $allowed = ['vehicle_name', 'price_per_km', 'capacity', 'status', 'category', 'fuel_type', 'transmission'];
    if (in_array($field, $allowed)) {
        $conn->query("UPDATE vehicles SET $field = '$value' WHERE id = $id");
        $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, new_data, ip_address) 
                      VALUES ($id, $manager_id, 'inline_edit', '$field = $value', '{$_SERVER['REMOTE_ADDR']}')");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid field']);
    }
    exit;
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$operator_filter = isset($_GET['operator']) ? intval($_GET['operator']) : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;

$allowed_sort = ['id', 'vehicle_name', 'price_per_km', 'capacity', 'operator_id', 'status', 'category'];
$sort_by = in_array($sort_by, $allowed_sort) ? $sort_by : 'id';
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// ============================================================
// BUILD WHERE CLAUSE
// ============================================================
$where = [];
$params = [];
$types = "";

if ($search) { $where[] = "v.vehicle_name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }
if ($operator_filter > 0) { $where[] = "v.operator_id = ?"; $params[] = $operator_filter; $types .= "i"; }
if ($status_filter) { $where[] = "v.status = ?"; $params[] = $status_filter; $types .= "s"; }
if ($category_filter) { $where[] = "v.category = ?"; $params[] = $category_filter; $types .= "s"; }
if ($price_min > 0) { $where[] = "v.price_per_km >= ?"; $params[] = $price_min; $types .= "d"; }
if ($price_max > 0) { $where[] = "v.price_per_km <= ?"; $params[] = $price_max; $types .= "d"; }

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================================
// GET TOTAL COUNT
// ============================================================
$count_sql = "SELECT COUNT(*) as total FROM vehicles v $where_clause";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// ============================================================
// GET MAIN DATA WITH DRIVER JOIN
// ============================================================
$sql = "SELECT v.*, d.name AS driver_name, d.phone AS driver_phone,
        (SELECT COUNT(*) FROM bookings b WHERE b.vehicle_id = v.id AND b.status NOT IN ('cancelled', 'Cancelled') AND b.end_date >= CURDATE()) AS active_bookings
        FROM vehicles v
        LEFT JOIN drivers d ON v.driver_id = d.id
        $where_clause
        ORDER BY v.$sort_by $sort_order
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$vehicles = $stmt->get_result();

// ============================================================
// STATS
// ============================================================
$total_all = $conn->query("SELECT COUNT(*) as total FROM vehicles")->fetch_assoc()['total'];
$op_counts = [];
$op_res = $conn->query("SELECT operator_id, COUNT(*) as total FROM vehicles GROUP BY operator_id");
while ($r = $op_res->fetch_assoc()) $op_counts[$r['operator_id'] ?? 'null'] = $r['total'];

$status_counts = ['Available' => 0, 'Booked' => 0, 'Maintenance' => 0];
$status_res = $conn->query("SELECT status, COUNT(*) as total FROM vehicles GROUP BY status");
while ($r = $status_res->fetch_assoc()) $status_counts[$r['status']] = $r['total'];

$cat_counts = [];
$cat_res = $conn->query("SELECT category, COUNT(*) as total FROM vehicles GROUP BY category");
while ($r = $cat_res->fetch_assoc()) $cat_counts[$r['category']] = $r['total'];

$avg_price = round($conn->query("SELECT AVG(price_per_km) as avg FROM vehicles")->fetch_assoc()['avg'] ?? 0, 2);

// ============================================================
// THEME
// ============================================================
$theme = $_COOKIE['vehicle_theme'] ?? 'light';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

// ============================================================
// FETCH VEHICLES LIST FOR DROPDOWNS
// ============================================================
$vehicles_list = [];
$res = $conn->query("SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name");
while ($row = $res->fetch_assoc()) $vehicles_list[] = $row;

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚗 Super Vehicles - Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================================
           THEME VARIABLES - Premium Glass (no video background)
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
            --table-bg: #ffffff;
            --table-header-bg: #0f172a;
            --table-header-text: #ffffff;
            --table-row-bg: #ffffff;
            --table-row-hover: #f1f5f9;
            --table-border: #e2e8f0;
            --table-text: #0a1929;
            --table-stripe: #f8fafc;
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
            --table-bg: #ffffff;
            --table-header-bg: #0f172a;
            --table-header-text: #ffffff;
            --table-row-bg: #ffffff;
            --table-row-hover: #f1f5f9;
            --table-border: #d1d5db;
            --table-text: #000000;
            --table-stripe: #f8fafc;
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
           PAGE HEADER
        ============================================================ */
        .page-header {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .page-header h4 {
            font-weight: 800; color: var(--text-primary); margin: 0;
        }
        .page-header h4 i { color: var(--color-blue); }
        .page-header small { color: var(--text-muted); }
        .page-header .actions {
            display: flex; gap: 6px; flex-wrap: wrap;
        }
        .page-header .actions .btn {
            font-size: 11px; padding: 4px 12px; border-radius: 8px;
        }

        /* ============================================================
           FILTER BAR
        ============================================================ */
        .filter-bar {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 12px 16px;
            box-shadow: var(--shadow-card);
            margin-bottom: 16px;
            transition: all var(--transition);
        }
        .filter-bar .filter-group {
            display: flex; flex-wrap: wrap; gap: 6px; align-items: flex-end;
        }
        .filter-bar .filter-group .field { flex: 1; min-width: 90px; }
        .filter-bar .filter-group .field-sm { flex: 0 0 70px; min-width: 55px; }
        .filter-bar label {
            font-size: 8px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-muted);
            display: block; margin-bottom: 2px;
        }
        .filter-bar .form-control,
        .filter-bar .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-primary);
            font-size: 11px;
            border-radius: 6px;
            padding: 4px 8px;
            height: 30px;
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
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            height: 30px;
            transition: all var(--transition);
        }
        .filter-bar .btn-filter:hover { opacity: 0.85; }
        .filter-bar .btn-reset {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            height: 30px;
        }

        /* ============================================================
           BULK BAR
        ============================================================ */
        .bulk-bar {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 8px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-card);
        }
        .bulk-bar .label {
            font-size: 10px; font-weight: 600; color: var(--text-muted);
        }
        .bulk-bar .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-primary);
            font-size: 11px;
            padding: 3px 8px;
            height: 28px;
            border-radius: 6px;
            min-width: 110px;
        }
        .bulk-bar .btn {
            font-size: 11px; padding: 3px 12px; border-radius: 6px;
        }
        .bulk-bar .alert {
            margin-bottom: 0; padding: 3px 10px; font-size: 11px;
        }

        /* ============================================================
           TABLE
        ============================================================ */
        .table-wrap {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 14px 16px;
            box-shadow: var(--shadow-card);
            overflow-x: auto;
        }
        .table {
            font-size: 12px;
            margin-bottom: 0;
            background: var(--table-bg) !important;
            border-collapse: collapse;
            width: 100%;
        }
        .table thead th {
            background: var(--table-header-bg) !important;
            color: var(--table-header-text) !important;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-color: var(--table-border) !important;
            border-bottom: 2px solid var(--table-border) !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table thead th a { color: #fff !important; text-decoration: none; }
        .table thead th a:hover { color: #0dcaf0 !important; }
        .table tbody td {
            padding: 6px 10px;
            vertical-align: middle;
            border-color: var(--table-border) !important;
            color: var(--table-text) !important;
            background: var(--table-row-bg) !important;
        }
        .table tbody tr:hover td { background: var(--table-row-hover) !important; }
        .table tbody tr:nth-of-type(odd) td { background: var(--table-stripe) !important; }
        .table tbody tr:nth-of-type(odd):hover td { background: var(--table-row-hover) !important; }
        .table tbody td strong,
        .table tbody td .badge-status,
        .table tbody td .badge-operator,
        .table tbody td .text-muted,
        .table tbody td span,
        .table tbody td small,
        .table tbody td .editable {
            color: var(--table-text) !important;
        }

        [data-theme="dark"] .table { background: #ffffff !important; }
        [data-theme="dark"] .table tbody td {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #d1d5db !important;
        }
        [data-theme="dark"] .table tbody tr:nth-of-type(odd) td { background: #f8fafc !important; }
        [data-theme="dark"] .table tbody tr:hover td { background: #e2e8f0 !important; }
        [data-theme="dark"] .table tbody td strong,
        [data-theme="dark"] .table tbody td .badge-status,
        [data-theme="dark"] .table tbody td .badge-operator,
        [data-theme="dark"] .table tbody td .text-muted,
        [data-theme="dark"] .table tbody td span,
        [data-theme="dark"] .table tbody td small,
        [data-theme="dark"] .table tbody td .editable { color: #000000 !important; }
        [data-theme="dark"] .table thead th {
            background: #0f172a !important;
            color: #ffffff !important;
            border-color: #334155 !important;
        }

        [data-theme="light"] .table { background: #ffffff !important; }
        [data-theme="light"] .table tbody td {
            background: #ffffff !important;
            color: #0a1929 !important;
            border-color: #e2e8f0 !important;
        }
        [data-theme="light"] .table tbody tr:nth-of-type(odd) td { background: #f8fafc !important; }
        [data-theme="light"] .table tbody tr:hover td { background: #f1f5f9 !important; }
        [data-theme="light"] .table thead th {
            background: #0f172a !important;
            color: #ffffff !important;
            border-color: #e2e8f0 !important;
        }

        /* ============================================================
           BADGES
        ============================================================ */
        .badge-status {
            padding: 2px 10px; border-radius: 20px;
            font-size: 9px; font-weight: 700;
            text-transform: uppercase; display: inline-block;
        }
        .badge-status.available { background: rgba(16,185,129,0.15); color: #10b981 !important; }
        .badge-status.booked { background: rgba(245,158,11,0.15); color: #f59e0b !important; }
        .badge-status.maintenance { background: rgba(239,68,68,0.15); color: #ef4444 !important; }

        .badge-operator {
            padding: 2px 10px; border-radius: 20px;
            font-size: 9px; font-weight: 600; display: inline-block;
        }
        .badge-operator.op1 { background: rgba(16,185,129,0.15); color: #10b981 !important; }
        .badge-operator.op2 { background: rgba(37,99,235,0.15); color: #2563eb !important; }
        .badge-operator.op3 { background: rgba(245,158,11,0.15); color: #f59e0b !important; }
        .badge-operator.null { background: rgba(239,68,68,0.1); color: #ef4444 !important; }

        [data-theme="dark"] .badge-status.available { background: #dcfce7 !important; color: #166534 !important; }
        [data-theme="dark"] .badge-status.booked { background: #fef3c7 !important; color: #92400e !important; }
        [data-theme="dark"] .badge-status.maintenance { background: #fee2e2 !important; color: #991b1b !important; }
        [data-theme="dark"] .badge-operator.op1 { background: #dcfce7 !important; color: #166534 !important; }
        [data-theme="dark"] .badge-operator.op2 { background: #dbeafe !important; color: #1e40af !important; }
        [data-theme="dark"] .badge-operator.op3 { background: #fef3c7 !important; color: #92400e !important; }
        [data-theme="dark"] .badge-operator.null { background: #fee2e2 !important; color: #991b1b !important; }

        .alert-badge {
            display: inline-block; padding: 1px 6px;
            border-radius: 10px; font-size: 7px; font-weight: 700;
            background: rgba(239,68,68,0.15); color: #ef4444; margin-left: 2px;
        }

        .btn-action {
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-secondary);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            transition: all var(--transition);
        }
        .btn-action:hover {
            background: var(--color-blue);
            color: #fff;
            border-color: var(--color-blue);
        }
        .btn-action.danger:hover {
            background: var(--color-red);
            border-color: var(--color-red);
        }

        /* ============================================================
           PAGINATION
        ============================================================ */
        .pagination .page-link {
            background: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--border-card);
            font-size: 11px;
            padding: 4px 10px;
        }
        .pagination .page-item.active .page-link {
            background: var(--color-blue);
            border-color: var(--color-blue);
            color: #fff;
        }

        /* ============================================================
           MODALS
        ============================================================ */
        .modal-content {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            color: var(--text-primary);
            border: 1px solid var(--border-card);
        }
        .modal-header, .modal-footer { border-color: var(--border-card); }
        .modal-header .btn-close { filter: var(--btn-close-filter); }

        /* ============================================================
           CHARTS
        ============================================================ */
        .chart-container {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 12px;
            box-shadow: var(--shadow-card);
        }
        .chart-container canvas { max-height: 200px; }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer {
            margin-top: 20px;
            padding: 10px 0;
            border-top: 1px solid var(--border-card);
            text-align: center;
            color: var(--text-muted);
            font-size: 10px;
        }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--color-blue); border-radius: 8px; }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3,1fr); }
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
            .filter-bar .filter-group .field { flex: 1 1 100%; }
            .filter-bar .filter-group .field-sm { flex: 1 1 45%; }
            .page-header { flex-direction: column; align-items: stretch; gap: 8px; }
            .bulk-bar { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table thead th { font-size: 8px; padding: 4px 6px; }
            .table tbody td { font-size: 10px; padding: 4px 6px; }
        }

        /* ============================================================
           PRINT
        ============================================================ */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .table-wrap { box-shadow: none !important; border: 1px solid #ddd !important; }
            .table { background: #fff !important; }
            .table tbody td { color: #000 !important; }
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
        <a href="vehicles.php" class="active"><i class="fas fa-list"></i> Vehicles <span class="badge-nav"><?= $total_all ?></span></a>
        <a href="filter.php"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php"><i class="fas fa-user"></i> Drivers <span class="badge-nav"><?= $status_counts['Available'] + $status_counts['Booked'] + $status_counts['Maintenance'] ?></span></a>
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
            <p><i class="fas fa-flag-checkered"></i> Super Vehicle Management</p>
        </div>
        <div class="header-actions">
            <div class="clock"><span class="dot"></span><span id="liveTime">--:--:--</span></div>
            <div class="notif-bell" onclick="showNotifications()"><i class="fas fa-bell"></i><span class="badge-count"><?= 0 ?></span></div>
            <div class="theme-toggle" id="themeToggle"><div class="dot-toggle"></div></div>
            <div class="profile-avatar" id="profileToggle">
                <?= strtoupper(substr($manager_name, 0, 1)) ?>
                <span class="status-dot"></span>
            </div>
        </div>
    </header>

    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header">
        <div>
            <h4><i class="fas fa-list"></i> Super Vehicle Management</h4>
            <small><i class="fas fa-car"></i> <?= $total_all ?> total vehicles · <?= $total_rows ?> filtered</small>
        </div>
        <div class="actions no-print">
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="fas fa-print"></i> Print</button>
            <a href="?export=csv" class="btn btn-outline-success btn-sm" id="exportBtn"><i class="fas fa-file-csv"></i> CSV</a>
            <a href="?export=excel" class="btn btn-outline-info btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="add_vehicle.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</a>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card"><div class="icon-wrap blue"><i class="fas fa-car"></i></div><div class="info"><div class="number"><?= $total_all ?></div><div class="label">Total</div></div></div>
        <div class="stat-card"><div class="icon-wrap green"><i class="fas fa-check-circle"></i></div><div class="info"><div class="number"><?= $status_counts['Available'] ?? 0 ?></div><div class="label">Available</div></div></div>
        <div class="stat-card"><div class="icon-wrap yellow"><i class="fas fa-calendar-check"></i></div><div class="info"><div class="number"><?= $status_counts['Booked'] ?? 0 ?></div><div class="label">Booked</div></div></div>
        <div class="stat-card"><div class="icon-wrap red"><i class="fas fa-wrench"></i></div><div class="info"><div class="number"><?= $status_counts['Maintenance'] ?? 0 ?></div><div class="label">Maintenance</div></div></div>
        <div class="stat-card"><div class="icon-wrap purple"><i class="fas fa-money-bill-wave"></i></div><div class="info"><div class="number">LKR <?= number_format($avg_price, 2) ?></div><div class="label">Avg Price</div></div></div>
        <div class="stat-card"><div class="icon-wrap teal"><i class="fas fa-user-tie"></i></div><div class="info"><div class="number"><?= count($op_counts) ?></div><div class="label">Operators</div></div></div>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <form method="GET" class="filter-bar no-print">
        <div class="filter-group">
            <div class="field"><label><i class="fas fa-search"></i> Search</label><input type="text" name="search" class="form-control" placeholder="Name..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="field-sm"><label>Operator</label><select name="operator" class="form-select"><option value="0">All</option><option value="1" <?= $operator_filter==1?'selected':'' ?>>Op1</option><option value="2" <?= $operator_filter==2?'selected':'' ?>>Op2</option><option value="3" <?= $operator_filter==3?'selected':'' ?>>Op3</option></select></div>
            <div class="field-sm"><label>Status</label><select name="status" class="form-select"><option value="">All</option><option value="Available" <?= $status_filter=='Available'?'selected':'' ?>>Available</option><option value="Booked" <?= $status_filter=='Booked'?'selected':'' ?>>Booked</option><option value="Maintenance" <?= $status_filter=='Maintenance'?'selected':'' ?>>Maintenance</option></select></div>
            <div class="field-sm"><label>Category</label><select name="category" class="form-select"><option value="">All</option><?php foreach($cat_counts as $cat=>$cnt): ?><option value="<?= $cat ?>" <?= $category_filter==$cat?'selected':'' ?>><?= $cat ?> (<?= $cnt ?>)</option><?php endforeach; ?></select></div>
            <div class="field-sm"><label>Min $</label><input type="number" name="price_min" class="form-control" placeholder="0" value="<?= $price_min?:'' ?>"></div>
            <div class="field-sm"><label>Max $</label><input type="number" name="price_max" class="form-control" placeholder="All" value="<?= $price_max?:'' ?>"></div>
            <div class="field-sm"><label>Sort</label><select name="sort_by" class="form-select"><option value="id" <?= $sort_by=='id'?'selected':'' ?>>ID</option><option value="vehicle_name" <?= $sort_by=='vehicle_name'?'selected':'' ?>>Name</option><option value="price_per_km" <?= $sort_by=='price_per_km'?'selected':'' ?>>Price</option><option value="capacity" <?= $sort_by=='capacity'?'selected':'' ?>>Capacity</option><option value="status" <?= $sort_by=='status'?'selected':'' ?>>Status</option></select></div>
            <div class="field-sm"><label>Order</label><select name="sort_order" class="form-select"><option value="ASC" <?= $sort_order=='ASC'?'selected':'' ?>>↑</option><option value="DESC" <?= $sort_order=='DESC'?'selected':'' ?>>↓</option></select></div>
            <div class="field-sm"><label>Show</label><select name="per_page" class="form-select"><option value="10" <?= $per_page==10?'selected':'' ?>>10</option><option value="25" <?= $per_page==25?'selected':'' ?>>25</option><option value="50" <?= $per_page==50?'selected':'' ?>>50</option></select></div>
            <div class="field" style="flex:0 0 auto;"><label>&nbsp;</label><div class="d-flex gap-1"><button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button><a href="vehicles.php" class="btn-reset"><i class="fas fa-times"></i></a></div></div>
        </div>
    </form>

    <!-- ===== BULK BAR ===== -->
    <form method="POST" class="bulk-bar no-print" id="bulkForm">
        <?php if ($bulk_msg): ?><div class="alert alert-success alert-dismissible fade show">✅ <?= $bulk_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($msg == 'deleted'): ?><div class="alert alert-success alert-dismissible fade show">✅ Vehicle deleted successfully!<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <span class="label"><i class="fas fa-check-double"></i> Bulk:</span>
        <select name="bulk_status" class="form-select"><option value="available">Available</option><option value="booked">Booked</option><option value="maintenance">Maintenance</option></select>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Update selected vehicles?')"><i class="fas fa-save"></i> Update</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAll"><i class="fas fa-check-square"></i> All</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAll"><i class="fas fa-square"></i> None</button>
        <span style="font-size:9px; color:var(--text-muted); margin-left:auto;"><i class="fas fa-keyboard"></i> Ctrl+A</span>
    </form>

    <!-- ===== TABLE ===== -->
    <div class="table-wrap">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <span style="font-size:11px; color:var(--text-muted);">Showing <?= $total_rows > 0 ? $offset+1 : 0 ?> - <?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?></span>
            <span style="font-size:10px; color:var(--text-muted);"><i class="fas fa-mouse-pointer"></i> Click fields to edit inline</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="vehicleTable">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="checkAll"></th>
                        <th style="width:30px;">#</th>
                        <th>Name / Category</th>
                        <th style="width:60px;">Price</th>
                        <th style="width:50px;">Seats</th>
                        <th style="width:80px;">Operator</th>
                        <th style="width:70px;">Status</th>
                        <th style="width:130px;">Driver</th>
                        <th style="width:50px;">Bkgs</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vehicles->num_rows > 0): ?>
                        <?php while($v = $vehicles->fetch_assoc()):
                            $op_class = 'null';
                            if ($v['operator_id'] == 1) $op_class = 'op1';
                            elseif ($v['operator_id'] == 2) $op_class = 'op2';
                            elseif ($v['operator_id'] == 3) $op_class = 'op3';
                            $status_class = strtolower($v['status'] ?? 'available');

                            $alerts = [];
                            $today = date('Y-m-d');
                            if ($v['next_maintenance'] && $v['next_maintenance'] < date('Y-m-d', strtotime('+30 days'))) $alerts[] = '🔧';
                            if ($v['insurance_expiry'] && $v['insurance_expiry'] < date('Y-m-d', strtotime('+30 days'))) $alerts[] = '📄';
                            if ($v['registration_expiry'] && $v['registration_expiry'] < date('Y-m-d', strtotime('+30 days'))) $alerts[] = '📋';
                            $alert_html = !empty($alerts) ? '<span class="alert-badge" title="Maintenance/Insurance/Registration due!">'.implode(' ', $alerts).'</span>' : '';
                        ?>
                        <tr data-id="<?= $v['id'] ?>">
                            <td><input type="checkbox" name="vehicle_ids[]" value="<?= $v['id'] ?>" class="vehicle-check"></td>
                            <td><strong>#<?= $v['id'] ?></strong></td>
                            <td>
                                <strong class="editable" data-field="vehicle_name" data-id="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></strong>
                                <br><small class="text-muted editable" data-field="category" data-id="<?= $v['id'] ?>"><?= htmlspecialchars($v['category'] ?? 'Sedan') ?></small>
                                <small class="text-muted"> · <?= $v['fuel_type'] ?? 'Petrol' ?> · <?= $v['transmission'] ?? 'Manual' ?></small>
                                <?= $alert_html ?>
                            </td>
                            <td><strong class="editable" data-field="price_per_km" data-id="<?= $v['id'] ?>">LKR <?= number_format($v['price_per_km'], 2) ?></strong></td>
                            <td><span class="editable" data-field="capacity" data-id="<?= $v['id'] ?>"><?= $v['capacity'] ?></span></td>
                            <td><span class="badge-operator <?= $op_class ?>">Op <?= $v['operator_id'] ?? '—' ?></span></td>
                            <td><span class="badge-status <?= $status_class ?> editable" data-field="status" data-id="<?= $v['id'] ?>"><?= $v['status'] ?? 'Available' ?></span></td>
                            <td>
                                <?php if (!empty($v['driver_name'])): ?>
                                    <span style="font-weight:600;"><?= htmlspecialchars($v['driver_name']) ?></span>
                                    <br><small class="text-muted"><?= htmlspecialchars($v['driver_phone'] ?? '') ?></small>
                                <?php else: ?>
                                    <span class="text-muted" style="font-style:italic;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $bk = $v['active_bookings'] ?? 0; ?>
                                <span class="badge <?= $bk > 0 ? 'bg-warning text-dark' : 'bg-secondary text-white' ?>" style="font-size:9px;"><?= $bk ?></span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <button class="btn-action" onclick="openEditModal(<?= $v['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-action" onclick="viewHistory(<?= $v['id'] ?>)" title="History"><i class="fas fa-history"></i></button>
                                    <button class="btn-action" onclick="viewCalendar(<?= $v['id'] ?>)" title="Calendar"><i class="fas fa-calendar-alt"></i></button>
                                    <a href="?delete=<?= $v['id'] ?>" class="btn-action danger" onclick="return confirm('Delete this vehicle?')" title="Delete"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center py-4"><i class="fas fa-search fa-2x d-block mb-2 text-muted"></i><p class="text-muted">No vehicles found</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mt-3">
            <span class="text-muted" style="font-size:11px;">Page <?= $page ?> of <?= $total_pages ?></span>
            <nav><ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>"><i class="fas fa-angle-double-left"></i></a></li>
                <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>"><i class="fas fa-angle-left"></i></a></li>
                <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>"><i class="fas fa-angle-right"></i></a></li>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$total_pages])) ?>"><i class="fas fa-angle-double-right"></i></a></li>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== CHARTS SECTION ===== -->
    <div class="row g-3 mt-2 no-print">
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="mb-2" style="color:var(--text-primary); font-weight:700;"><i class="fas fa-chart-pie"></i> Status Distribution</h6>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="mb-2" style="color:var(--text-primary); font-weight:700;"><i class="fas fa-chart-bar"></i> Operator Distribution</h6>
                <canvas id="operatorChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        &copy; <?= date('Y') ?> Travel &amp; Tour · Super Vehicle Management ·
        <span id="footerTime"></span> ·
        <i class="fas fa-keyboard"></i> Ctrl+Shift+D (Theme) · Ctrl+A (Select All)
    </div>

</main>

<!-- ============================================================
MODALS
============================================================ -->

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-coins"></i> Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
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
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" class="form-control" required></div>
        <input type="hidden" name="update_maintenance" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-warning">Update</button></div></form>
</div></div></div>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file"></i> Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data"><div class="modal-body">
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
    <form method="POST">
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
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Name</label><input type="text" name="manager_name" class="form-control" value="<?= htmlspecialchars($manager_name) ?>" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="manager_email" class="form-control" value="<?= htmlspecialchars($_SESSION['manager_email'] ?? '') ?>" required></div>
        <hr><h6>Change Password</h6>
        <div class="mb-2"><label class="form-label">Old Password</label><input type="password" name="old_password" class="form-control" placeholder="Enter old"></div>
        <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Enter new"></div>
        <input type="hidden" name="update_profile" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit"></i> Edit Vehicle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="editModalBody">
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-2"><label class="form-label">Name</label><input type="text" name="vehicle_name" id="edit_name" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Category</label><input type="text" name="category" id="edit_category" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Price/km</label><input type="number" step="10" name="price_per_km" id="edit_price" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Capacity</label><input type="number" name="capacity" id="edit_capacity" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Status</label><select name="status" id="edit_status" class="form-select"><option value="Available">Available</option><option value="Booked">Booked</option><option value="Maintenance">Maintenance</option></select></div>
                    <div class="mb-2"><label class="form-label">Fuel Type</label><input type="text" name="fuel_type" id="edit_fuel" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Transmission</label><input type="text" name="transmission" id="edit_trans" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" id="edit_maintenance" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Insurance Expiry</label><input type="date" name="insurance_expiry" id="edit_insurance" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Registration Expiry</label><input type="date" name="registration_expiry" id="edit_registration" class="form-control"></div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-history"></i> Vehicle History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="historyModalBody"><p class="text-muted">Loading...</p></div>
        </div>
    </div>
</div>

<!-- Calendar Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Availability Calendar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="calendarModalBody"><p class="text-muted">Loading...</p></div>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div class="modal fade" id="notifModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-bell"></i> Notifications</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="text-muted text-center py-4">No new notifications</p></div>
</div></div></div>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
// 1. CLOCK (DATE + TIME)
// ============================================================
function updateClock() {
    const n = new Date();
    document.getElementById('liveTime').textContent = n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('footerTime').textContent = '🕐 ' + n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit'});
}
updateClock(); 
setInterval(updateClock, 1000);

// ============================================================
// 2. THEME
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
// 3. PROFILE
// ============================================================
document.getElementById('profileToggle')?.addEventListener('click', function() {
    if (confirm('Logout?')) window.location.href = '../index.php?action=logout';
});

// ============================================================
// 4. CHECK ALL
// ============================================================
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.vehicle-check').forEach(cb => cb.checked = this.checked);
});
document.getElementById('selectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-check').forEach(cb => cb.checked = true);
});
document.getElementById('deselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-check').forEach(cb => cb.checked = false);
});

// ============================================================
// 5. INLINE EDIT
// ============================================================
document.querySelectorAll('.editable').forEach(el => {
    el.addEventListener('click', function() {
        const field = this.dataset.field;
        const id = this.dataset.id;
        const current = this.textContent.trim().replace(/[^0-9a-zA-Z\s]/g, '').trim();
        const newVal = prompt('Edit ' + field + ':', current);
        if (newVal !== null && newVal !== current) {
            fetch('vehicles.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'inline_edit=1&id=' + id + '&field=' + field + '&value=' + encodeURIComponent(newVal)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.textContent = newVal;
                    showToast('Updated!');
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    });
});

// ============================================================
// 6. EDIT MODAL
// ============================================================
function openEditModal(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = row.querySelector('[data-field="vehicle_name"]')?.textContent?.trim() || '';
    document.getElementById('edit_category').value = row.querySelector('[data-field="category"]')?.textContent?.trim() || '';
    document.getElementById('edit_price').value = row.querySelector('[data-field="price_per_km"]')?.textContent?.replace(/[^0-9.]/g, '') || '';
    document.getElementById('edit_capacity').value = row.querySelector('[data-field="capacity"]')?.textContent?.trim() || '';
    document.getElementById('edit_status').value = row.querySelector('[data-field="status"]')?.textContent?.trim() || 'Available';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

document.getElementById('editForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('inline_edit', 1);
    const params = new URLSearchParams();
    for (let [key, val] of formData) params.append(key, val);
    fetch('vehicles.php', {method: 'POST', body: params})
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert('Error: ' + data.error); }
    });
});

// ============================================================
// 7. HISTORY
// ============================================================
function viewHistory(id) {
    document.getElementById('historyModalBody').innerHTML = '<p class="text-muted">Loading history...</p>';
    fetch('get_vehicle_history.php?id=' + id)
    .then(r => r.text())
    .then(html => {
        document.getElementById('historyModalBody').innerHTML = html || '<p class="text-muted">No history found.</p>';
    })
    .catch(() => {
        document.getElementById('historyModalBody').innerHTML = '<p class="text-muted">Could not load history.</p>';
    });
    new bootstrap.Modal(document.getElementById('historyModal')).show();
}

// ============================================================
// 8. CALENDAR
// ============================================================
function viewCalendar(id) {
    document.getElementById('calendarModalBody').innerHTML = '<p class="text-muted">Loading calendar...</p>';
    fetch('get_vehicle_calendar.php?id=' + id)
    .then(r => r.text())
    .then(html => {
        document.getElementById('calendarModalBody').innerHTML = html || '<p class="text-muted">No bookings found.</p>';
    })
    .catch(() => {
        document.getElementById('calendarModalBody').innerHTML = '<p class="text-muted">Could not load calendar.</p>';
    });
    new bootstrap.Modal(document.getElementById('calendarModal')).show();
}

// ============================================================
// 9. CHARTS
// ============================================================
const statusData = <?= json_encode(array_values($status_counts)) ?>;
const statusLabels = <?= json_encode(array_keys($status_counts)) ?>;
const operatorData = <?= json_encode(array_values($op_counts)) ?>;
const operatorLabels = <?= json_encode(array_keys($op_counts)) ?>;

if (document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') } } } }
    });
}

if (document.getElementById('operatorChart')) {
    const colors = ['#10b981', '#2563eb', '#f59e0b', '#ef4444'];
    new Chart(document.getElementById('operatorChart'), {
        type: 'bar',
        data: {
            labels: operatorLabels.map(l => l == 'null' ? 'Unassigned' : 'Op ' + l),
            datasets: [{ data: operatorData, backgroundColor: colors.slice(0, operatorData.length) }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-muted') } }, x: { ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-muted') } } } }
    });
}

// ============================================================
// 10. KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') { e.preventDefault(); themeToggle.click(); }
    if (e.ctrlKey && e.key === 'a' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName || '')) {
        e.preventDefault();
        document.querySelectorAll('.vehicle-check').forEach(cb => cb.checked = true);
    }
    if (e.key === 'Escape') {
        const search = document.querySelector('input[name="search"]');
        if (search && document.activeElement === search) { search.value = ''; search.closest('form').submit(); }
    }
});

// ============================================================
// 11. TOAST
// ============================================================
function showToast(msg) {
    const div = document.createElement('div');
    div.className = 'alert alert-success alert-dismissible fade show position-fixed';
    div.style.cssText = 'bottom:20px; right:20px; z-index:9999; padding:6px 16px; font-size:13px; border-radius:10px; background:#10b981; color:#fff; border:none;';
    div.innerHTML = msg + ' <button type="button" class="btn-close btn-sm" onclick="this.parentElement.remove()"></button>';
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

// ============================================================
// 12. FUNCTIONS
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
        html2pdf().set({ margin: 0.5, filename: 'Vehicle_Management_Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'in', format: 'a3', orientation: 'landscape' } }).from(element).save();
    } else {
        window.print();
    }
}

function refreshData() {
    location.reload();
}

console.log('%c🚗 Super Vehicles Loaded! (Premium Glass Design - No BG Video)', 'font-size:18px; font-weight:bold; color:#2563eb;');
console.log('%c✅ Glass-morphism design | All functions working', 'font-size:12px; color:#10b981;');
</script>

<?php $conn->close(); ?>
</body>
</html>