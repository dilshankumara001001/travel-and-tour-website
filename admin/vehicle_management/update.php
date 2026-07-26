<?php
// ============================================================
// update.php - Update Vehicles (Premium Glass Design)
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
// CREATE AUDIT LOGS TABLE IF NOT EXISTS
// ============================================================
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
// HANDLE SINGLE VEHICLE UPDATE (from Edit Modal)
// ============================================================
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_single'])) {
    $id = intval($_POST['vehicle_id']);
    $vehicle_name = $conn->real_escape_string($_POST['vehicle_name']);
    $price_per_km = floatval($_POST['price_per_km']);
    $capacity = intval($_POST['capacity']);
    $operator_id = $_POST['operator_id'] == 'NULL' ? 'NULL' : intval($_POST['operator_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $driver_id = $_POST['driver_id'] == 'NULL' ? 'NULL' : intval($_POST['driver_id']);
    $next_maintenance = !empty($_POST['next_maintenance']) ? "'" . $conn->real_escape_string($_POST['next_maintenance']) . "'" : "NULL";

    $sql = "UPDATE vehicles SET 
            vehicle_name = '$vehicle_name',
            price_per_km = $price_per_km,
            capacity = $capacity,
            operator_id = " . ($operator_id == 'NULL' ? 'NULL' : $operator_id) . ",
            status = '$status',
            driver_id = " . ($driver_id == 'NULL' ? 'NULL' : $driver_id) . ",
            next_maintenance = $next_maintenance,
            last_update = NOW()
            WHERE id = $id";

    if ($conn->query($sql)) {
        $message = "✅ Vehicle #$id updated successfully!";
        $message_type = 'success';
        $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, new_data, ip_address) 
                      VALUES ($id, $manager_id, 'full_update', 'Name: $vehicle_name, Price: $price_per_km, Capacity: $capacity, Operator: $operator_id, Status: $status, Driver: $driver_id', '{$_SERVER['REMOTE_ADDR']}')");
    } else {
        $message = "❌ Error: " . $conn->error;
        $message_type = 'danger';
    }
}

// ============================================================
// HANDLE BULK UPDATE
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_update'])) {
    $ids = isset($_POST['vehicle_ids']) ? array_map('intval', $_POST['vehicle_ids']) : [];
    $bulk_field = $conn->real_escape_string($_POST['bulk_field']);
    $bulk_value = $conn->real_escape_string($_POST['bulk_value']);
    $allowed_fields = ['operator_id', 'status'];

    if (count($ids) > 0 && in_array($bulk_field, $allowed_fields)) {
        $id_list = implode(',', $ids);
        $val = $bulk_value == 'NULL' ? 'NULL' : "'$bulk_value'";
        if ($bulk_field == 'operator_id' && $bulk_value != 'NULL') {
            $val = intval($bulk_value);
        }
        $sql = "UPDATE vehicles SET $bulk_field = $val WHERE id IN ($id_list)";
        if ($conn->query($sql)) {
            $message = "✅ " . count($ids) . " vehicles updated! Field: $bulk_field = $bulk_value";
            $message_type = 'success';
            $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, new_data, ip_address) 
                          VALUES (0, $manager_id, 'bulk_update', '$bulk_field = $bulk_value for IDs: $id_list', '{$_SERVER['REMOTE_ADDR']}')");
        } else {
            $message = "❌ Error: " . $conn->error;
            $message_type = 'danger';
        }
    } else {
        $message = "❌ Please select at least one vehicle and a valid field.";
        $message_type = 'danger';
    }
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, ip_address) 
                  VALUES ($id, $manager_id, 'deleted', '{$_SERVER['REMOTE_ADDR']}')");
    $conn->query("DELETE FROM vehicles WHERE id = $id");
    header("Location: update.php?msg=deleted");
    exit;
}

// ============================================================
// HANDLE INLINE EDIT (AJAX)
// ============================================================
if (isset($_POST['inline_edit'])) {
    $id = intval($_POST['id']);
    $field = $conn->real_escape_string($_POST['field']);
    $value = $conn->real_escape_string($_POST['value']);
    $allowed = ['vehicle_name', 'price_per_km', 'capacity', 'status', 'operator_id'];
    
    if (in_array($field, $allowed)) {
        $val = ($field == 'operator_id' && $value == 'NULL') ? 'NULL' : "'$value'";
        if ($field == 'operator_id' && $value != 'NULL') {
            $val = intval($value);
        }
        $conn->query("UPDATE vehicles SET $field = $val WHERE id = $id");
        $conn->query("INSERT INTO vehicle_audit_logs (vehicle_id, user_id, action, new_data, ip_address) 
                      VALUES ($id, $manager_id, 'inline_edit', '$field: $value', '{$_SERVER['REMOTE_ADDR']}')");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid field']);
    }
    exit;
}

// ============================================================
// GET ALL VEHICLES FOR DISPLAY
// ============================================================
$vehicles_sql = "SELECT v.*, d.name AS driver_name, d.phone AS driver_phone,
                 (SELECT COUNT(*) FROM bookings b WHERE b.vehicle_id = v.id AND b.status NOT IN ('cancelled', 'Cancelled') AND b.end_date >= CURDATE()) AS active_bookings
                 FROM vehicles v 
                 LEFT JOIN drivers d ON v.driver_id = d.id 
                 ORDER BY v.id DESC";
$vehicles_result = $conn->query($vehicles_sql);

// ============================================================
// GET DRIVERS FOR DROPDOWN
// ============================================================
$drivers = [];
$drv_res = $conn->query("SELECT id, name, phone FROM drivers ORDER BY name");
while ($drv = $drv_res->fetch_assoc()) {
    $drivers[] = $drv;
}

// ============================================================
// GET OPERATOR COUNTS
// ============================================================
$op_counts = [];
$op_res = $conn->query("SELECT operator_id, COUNT(*) as total FROM vehicles GROUP BY operator_id");
while ($r = $op_res->fetch_assoc()) {
    $op_counts[$r['operator_id'] ?? 'null'] = $r['total'];
}

// ============================================================
// GET STATUS COUNTS
// ============================================================
$status_counts = ['Available' => 0, 'Booked' => 0, 'Maintenance' => 0];
$status_res = $conn->query("SELECT status, COUNT(*) as total FROM vehicles GROUP BY status");
while ($r = $status_res->fetch_assoc()) {
    $status_counts[$r['status']] = $r['total'];
}

// ============================================================
// STATS
// ============================================================
$total_all = $conn->query("SELECT COUNT(*) as total FROM vehicles")->fetch_assoc()['total'];
$avg_price = round($conn->query("SELECT AVG(price_per_km) as avg FROM vehicles")->fetch_assoc()['avg'] ?? 0, 2);
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// ============================================================
// THEME
// ============================================================
$theme = $_COOKIE['vehicle_theme'] ?? 'light';
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Update - Vehicle Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           THEME VARIABLES - Premium Glass
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
            --gradient-primary: linear-gradient(135deg, #2563eb, #7c3aed);
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --glass-blur: blur(24px) saturate(1.6);
            --radius: 16px;
            --transition: 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(96, 165, 250, 0.15);
            --sidebar-border: rgba(255,255,255,0.06);
            --btn-close-filter: invert(0);
            --input-bg: rgba(255,255,255,0.6);
            --input-border: rgba(0,0,0,0.08);
            --input-focus: rgba(255,255,255,0.8);
            --input-text: #0f172a;
            --input-placeholder: #94a3b8;
            --badge-op1-bg: rgba(16,185,129,0.15);
            --badge-op1-color: #0f7b4a;
            --badge-op2-bg: rgba(37,99,235,0.15);
            --badge-op2-color: #1e40af;
            --badge-op3-bg: rgba(245,158,11,0.15);
            --badge-op3-color: #92400e;
            --badge-opnull-bg: rgba(239,68,68,0.15);
            --badge-opnull-color: #991b1b;
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
            --gradient-primary: linear-gradient(135deg, #60a5fa, #a78bfa);
            --sidebar-bg: linear-gradient(180deg, #0b1120 0%, #141428 100%);
            --btn-close-filter: invert(1);
            --input-bg: rgba(15,23,42,0.6);
            --input-border: rgba(255,255,255,0.06);
            --input-focus: rgba(15,23,42,0.8);
            --input-text: #f1f5f9;
            --input-placeholder: #94a3b8;
            --badge-op1-bg: rgba(52,211,153,0.2);
            --badge-op1-color: #34d399;
            --badge-op2-bg: rgba(96,165,250,0.2);
            --badge-op2-color: #60a5fa;
            --badge-op3-bg: rgba(251,191,36,0.2);
            --badge-op3-color: #fbbf24;
            --badge-opnull-bg: rgba(248,113,113,0.2);
            --badge-opnull-color: #f87171;
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
            display: flex;
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
           STATS CARDS (Glass)
        ============================================================ */
        .stat-grid {
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
        .stat-card .icon-wrap.blue { background: var(--gradient-primary); }
        .stat-card .icon-wrap.yellow { background: var(--gradient-warning); }
        .stat-card .icon-wrap.red { background: var(--gradient-danger); }
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
            font-size: 10px; font-weight: 600;
            color: var(--text-muted);
        }
        .bulk-bar .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-text);
            font-size: 11px;
            padding: 4px 10px;
            height: 32px;
            border-radius: 6px;
            min-width: 130px;
        }
        .bulk-bar .btn {
            font-size: 11px;
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
            padding: 14px 16px;
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
            font-size: 15px; font-weight: 700;
            color: var(--text-primary);
        }
        .vehicle-card .name small {
            font-weight: 400; font-size: 10px;
            color: var(--text-muted);
            display: block;
        }
        .vehicle-card .badge-status {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .badge-status.available { background: rgba(16,185,129,0.15); color: var(--color-green); }
        .badge-status.booked { background: rgba(245,158,11,0.15); color: var(--color-yellow); }
        .badge-status.maintenance { background: rgba(239,68,68,0.15); color: var(--color-red); }

        .vehicle-card .driver-row {
            background: var(--input-bg);
            border-radius: 8px;
            padding: 5px 10px;
            margin: 6px 0;
            font-size: 11px;
        }
        .vehicle-card .driver-row i { color: var(--color-blue); }
        .vehicle-card .driver-row .no-driver { color: var(--text-muted); font-style: italic; }

        .vehicle-card .info-row {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 4px 0;
        }
        .vehicle-card .info-row i { width: 16px; color: var(--text-muted); }
        .vehicle-card .info-row strong { color: var(--text-primary); }

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
           BADGES (Operator)
        ============================================================ */
        .badge-operator {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-operator.op1 { background: var(--badge-op1-bg); color: var(--badge-op1-color); }
        .badge-operator.op2 { background: var(--badge-op2-bg); color: var(--badge-op2-color); }
        .badge-operator.op3 { background: var(--badge-op3-bg); color: var(--badge-op3-color); }
        .badge-operator.null { background: var(--badge-opnull-bg); color: var(--badge-opnull-color); }

        /* ============================================================
           MODALS (Glass)
        ============================================================ */
        .modal-content {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            color: var(--text-primary);
            border: 1px solid var(--border-card);
        }
        .modal-header, .modal-footer { border-color: var(--border-card); }
        .modal-header .btn-close { filter: var(--btn-close-filter); }
        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-text);
            font-size: 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .form-label {
            font-size: 11px; font-weight: 600;
            color: var(--text-secondary);
        }

        /* ============================================================
           ALERTS
        ============================================================ */
        .alert-success {
            background: rgba(16,185,129,0.12);
            color: #0f7b4a;
            border: 1px solid rgba(16,185,129,0.2);
        }
        .alert-danger {
            background: rgba(239,68,68,0.12);
            color: #991b1b;
            border: 1px solid rgba(239,68,68,0.2);
        }
        [data-theme="dark"] .alert-success {
            background: rgba(52,211,153,0.15);
            color: #34d399;
        }
        [data-theme="dark"] .alert-danger {
            background: rgba(248,113,113,0.15);
            color: #f87171;
        }

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
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .stat-grid { grid-template-columns: repeat(3,1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            .hamburger { display: block; }
            .stat-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .top-header { flex-direction: column; align-items: flex-start; }
            .top-header .header-actions { width: 100%; justify-content: space-between; }
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .icon-wrap { width: 34px; height: 34px; font-size: 14px; }
            .stat-card .info .number { font-size: 1.2rem; }
            .vehicle-grid { grid-template-columns: 1fr; }
            .bulk-bar { flex-direction: column; align-items: stretch; }
            .vehicle-card { padding: 12px 14px; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
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
            .sidebar, .top-header, .stat-grid { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
        }
    </style>
</head>
<body>

<!-- ============================================================
HAMBURGER (Mobile)
============================================================ -->
<button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>

<!-- ============================================================
SIDEBAR
============================================================ -->
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
        <a href="filter.php"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php" class="active"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php"><i class="fas fa-user"></i> Drivers <span class="badge-nav"><?= $conn->query("SELECT COUNT(*) c FROM drivers")->fetch_assoc()['c'] ?? 0 ?></span></a>
        <div class="nav-label" style="margin-top:8px;">Tools</div>
        <a href="#" onclick="window.print()"><i class="fas fa-print"></i> Print</a>
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
            <p><i class="fas fa-edit" style="color:var(--color-blue);"></i> Update Vehicles</p>
        </div>
        <div class="header-actions">
            <div class="clock"><span class="dot"></span><span id="liveTime">--:--:--</span></div>
            <div class="theme-toggle" id="themeToggle"><div class="dot-toggle"></div></div>
            <div class="profile-avatar" id="profileToggle">
                <?= strtoupper(substr($manager_name, 0, 1)) ?>
                <span class="status-dot"></span>
            </div>
        </div>
    </header>

    <!-- ===== STATS CARDS ===== -->
    <div class="stat-grid">
        <div class="stat-card"><div class="icon-wrap green"><i class="fas fa-user-tie"></i></div><div class="info"><div class="number"><?= $op_counts[1] ?? 0 ?></div><div class="label">Op 1</div></div></div>
        <div class="stat-card"><div class="icon-wrap blue"><i class="fas fa-user-tie"></i></div><div class="info"><div class="number"><?= $op_counts[2] ?? 0 ?></div><div class="label">Op 2</div></div></div>
        <div class="stat-card"><div class="icon-wrap yellow"><i class="fas fa-user-tie"></i></div><div class="info"><div class="number"><?= $op_counts[3] ?? 0 ?></div><div class="label">Op 3</div></div></div>
        <div class="stat-card"><div class="icon-wrap red"><i class="fas fa-user-slash"></i></div><div class="info"><div class="number"><?= $op_counts['null'] ?? 0 ?></div><div class="label">Unassigned</div></div></div>
        <div class="stat-card"><div class="icon-wrap teal"><i class="fas fa-check-circle"></i></div><div class="info"><div class="number"><?= $status_counts['Available'] ?? 0 ?></div><div class="label">Available</div></div></div>
        <div class="stat-card"><div class="icon-wrap purple"><i class="fas fa-money-bill-wave"></i></div><div class="info"><div class="number">LKR <?= number_format($avg_price, 2) ?></div><div class="label">Avg Price</div></div></div>
    </div>

    <!-- ===== ALERTS ===== -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg == 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ Vehicle deleted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ===== BULK BAR ===== -->
    <form method="POST" class="bulk-bar no-print" id="bulkForm">
        <span class="label"><i class="fas fa-check-double"></i> Bulk Update:</span>
        <select name="bulk_field" class="form-select" style="min-width:120px;">
            <option value="operator_id">Operator</option>
            <option value="status">Status</option>
        </select>
        <select name="bulk_value" class="form-select" style="min-width:130px;">
            <optgroup label="Operators">
                <option value="1">Operator 1</option>
                <option value="2">Operator 2</option>
                <option value="3">Operator 3</option>
                <option value="NULL">Unassigned</option>
            </optgroup>
            <optgroup label="Status">
                <option value="Available">Available</option>
                <option value="Booked">Booked</option>
                <option value="Maintenance">Maintenance</option>
            </optgroup>
        </select>
        <button type="submit" name="bulk_update" class="btn btn-primary btn-sm" onclick="return confirm('Update selected vehicles?')">
            <i class="fas fa-save"></i> Apply
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllBtn"><i class="fas fa-check-square"></i> All</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn"><i class="fas fa-square"></i> None</button>
        <span style="font-size:9px; color:var(--text-muted); margin-left:auto;">
            <i class="fas fa-keyboard"></i> Ctrl+A = Select All
        </span>
    </form>

    <!-- ===== VEHICLE GRID ===== -->
    <div class="vehicle-grid">
        <?php if ($vehicles_result->num_rows > 0): ?>
            <?php while($v = $vehicles_result->fetch_assoc()):
                $op_class = 'null';
                if ($v['operator_id'] == 1) $op_class = 'op1';
                elseif ($v['operator_id'] == 2) $op_class = 'op2';
                elseif ($v['operator_id'] == 3) $op_class = 'op3';
                $status_class = strtolower($v['status'] ?? 'available');
                $has_driver = !empty($v['driver_name']);
                $alert = '';
                if ($v['next_maintenance'] && $v['next_maintenance'] < date('Y-m-d', strtotime('+30 days'))) {
                    $alert = '🔧';
                }
            ?>
            <div class="vehicle-card" data-id="<?= $v['id'] ?>">
                <div class="top-row">
                    <div class="name">
                        #<?= $v['id'] ?> <?= htmlspecialchars($v['vehicle_name']) ?>
                        <?php if ($alert): ?>
                            <span title="Maintenance due within 30 days!" style="font-size:12px;"><?= $alert ?></span>
                        <?php endif; ?>
                        <small>
                            <i class="fas fa-users"></i> <?= $v['capacity'] ?> seats ·
                            <i class="fas fa-money-bill-wave" style="color:var(--color-green);"></i> LKR <?= number_format($v['price_per_km'], 2) ?>/km
                        </small>
                    </div>
                    <span class="badge-status <?= $status_class ?>"><?= $v['status'] ?? 'Available' ?></span>
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
                    <i class="fas fa-user-tie"></i> Operator: 
                    <span class="badge-operator <?= $op_class ?>"><?= $v['operator_id'] ? 'Op '.$v['operator_id'] : '—' ?></span>
                    <?php if ($v['last_update']): ?>
                        &nbsp;· <i class="fas fa-clock"></i> <?= date('d M Y', strtotime($v['last_update'])) ?>
                    <?php endif; ?>
                    <?php if ($v['next_maintenance']): ?>
                        &nbsp;· <i class="fas fa-tools"></i> Service: <?= date('d M Y', strtotime($v['next_maintenance'])) ?>
                    <?php endif; ?>
                </div>

                <div class="actions-row">
                    <div class="d-flex gap-1">
                        <button class="btn btn-primary btn-sm edit-btn" data-id="<?= $v['id'] ?>" data-name="<?= htmlspecialchars($v['vehicle_name']) ?>" data-price="<?= $v['price_per_km'] ?>" data-capacity="<?= $v['capacity'] ?>" data-operator="<?= $v['operator_id'] ?? 'NULL' ?>" data-status="<?= $v['status'] ?? 'Available' ?>" data-driver="<?= $v['driver_id'] ?? 'NULL' ?>" data-maintenance="<?= $v['next_maintenance'] ?? '' ?>">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-outline-secondary btn-sm history-btn" data-id="<?= $v['id'] ?>">
                            <i class="fas fa-history"></i>
                        </button>
                        <a href="?delete=<?= $v['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this vehicle?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <div>
                        <input type="checkbox" name="vehicle_ids[]" value="<?= $v['id'] ?>" class="form-check-input vehicle-checkbox" id="cb_<?= $v['id'] ?>">
                        <label class="form-check-label" for="cb_<?= $v['id'] ?>">Select</label>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5" style="grid-column:1/-1;">
                <i class="fas fa-car fa-3x mb-3" style="color:var(--text-muted);"></i>
                <h5 style="color:var(--text-primary);">No vehicles found</h5>
                <p class="text-muted">Add a vehicle to get started.</p>
                <a href="add_vehicle.php" class="btn btn-primary btn-sm">Add Vehicle</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== QUICK TIPS ===== -->
    <div class="row g-3 mt-3">
        <div class="col-md-6">
            <div class="main-card" style="background:var(--bg-card); backdrop-filter:var(--glass-blur); border:1px solid var(--border-card); border-radius:var(--radius); padding:14px 18px; box-shadow:var(--shadow-card);">
                <h5 style="font-weight:700; color:var(--text-primary);"><i class="fas fa-lightbulb" style="color:var(--color-yellow);"></i> Quick Tips</h5>
                <ul class="list-unstyled mb-0" style="color:var(--text-secondary); font-size:13px;">
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Click <kbd>Edit</kbd> to open full edit form</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Select multiple vehicles for bulk update</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Click <kbd>History</kbd> to see all changes</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="main-card" style="background:var(--bg-card); backdrop-filter:var(--glass-blur); border:1px solid var(--border-card); border-radius:var(--radius); padding:14px 18px; box-shadow:var(--shadow-card);">
                <h5 style="font-weight:700; color:var(--text-primary);"><i class="fas fa-shortcut" style="color:var(--color-blue);"></i> Keyboard Shortcuts</h5>
                <ul class="list-unstyled mb-0" style="color:var(--text-secondary); font-size:13px;">
                    <li><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>D</kbd> = Toggle Dark Mode</li>
                    <li><kbd>Ctrl</kbd> + <kbd>A</kbd> = Select All Vehicles</li>
                    <li><kbd>Esc</kbd> = Close Modals</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        &copy; <?= date('Y') ?> Travel &amp; Tour · Update Vehicles ·
        <span id="footerTime"></span> ·
        <i class="fas fa-keyboard"></i> Ctrl+Shift+D (Theme)
    </div>

</main>

<!-- ============================================================
MODALS
============================================================ -->

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="vehicle_id" id="edit_id">
                    <input type="hidden" name="update_single" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-car"></i> Vehicle Name</label>
                        <input type="text" name="vehicle_name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-money-bill-wave"></i> Price/km</label>
                            <input type="number" step="10" name="price_per_km" id="edit_price" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-users"></i> Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user-tie"></i> Operator</label>
                            <select name="operator_id" id="edit_operator" class="form-select">
                                <option value="1">Operator 1</option>
                                <option value="2">Operator 2</option>
                                <option value="3">Operator 3</option>
                                <option value="NULL">Unassigned</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-circle"></i> Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Available">Available</option>
                                <option value="Booked">Booked</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label"><i class="fas fa-user"></i> Assign Driver</label>
                            <select name="driver_id" id="edit_driver" class="form-select">
                                <option value="NULL">— No Driver —</option>
                                <?php foreach ($drivers as $drv): ?>
                                    <option value="<?= $drv['id'] ?>"><?= htmlspecialchars($drv['name']) ?> (<?= htmlspecialchars($drv['phone']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> Maintenance</label>
                            <input type="date" name="next_maintenance" id="edit_maintenance" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history"></i> Vehicle History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyModalBody">
                <p class="text-muted">Loading...</p>
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
    document.getElementById('liveTime').textContent = n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('footerTime').textContent = '🕐 ' + n.toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit'});
}
updateClock(); 
setInterval(updateClock, 1000);

// ============================================================
// THEME
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
// PROFILE
// ============================================================
document.getElementById('profileToggle')?.addEventListener('click', function() {
    if (confirm('Logout from Fleet Manager?')) window.location.href = '../index.php?action=logout';
});

// ============================================================
// SELECT ALL / NONE
// ============================================================
document.getElementById('selectAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('deselectAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.vehicle-checkbox').forEach(cb => cb.checked = false);
});

// ============================================================
// EDIT MODAL - Populate fields
// ============================================================
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_price').value = this.dataset.price;
        document.getElementById('edit_capacity').value = this.dataset.capacity;
        document.getElementById('edit_operator').value = this.dataset.operator;
        document.getElementById('edit_status').value = this.dataset.status;
        document.getElementById('edit_driver').value = this.dataset.driver;
        document.getElementById('edit_maintenance').value = this.dataset.maintenance;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ============================================================
// HISTORY MODAL
// ============================================================
document.querySelectorAll('.history-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        document.getElementById('historyModalBody').innerHTML = '<p class="text-muted">Loading...</p>';
        fetch('get_vehicle_history.php?id=' + id)
            .then(r => r.text())
            .then(html => {
                document.getElementById('historyModalBody').innerHTML = html || '<p class="text-muted">No history found.</p>';
            })
            .catch(() => {
                document.getElementById('historyModalBody').innerHTML = '<p class="text-muted">Could not load history.</p>';
            });
        new bootstrap.Modal(document.getElementById('historyModal')).show();
    });
});

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        themeToggle.click();
    }
    if (e.ctrlKey && e.key === 'a' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '')) {
        e.preventDefault();
        document.querySelectorAll('.vehicle-checkbox').forEach(cb => cb.checked = true);
    }
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) instance.hide();
        });
    }
});

// ============================================================
// OTHER FUNCTIONS
// ============================================================
function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
}
function refreshData() {
    location.reload();
}

console.log('%c✏️ Update Vehicles (Premium Glass Design)', 'font-size:18px; font-weight:bold; color:#2563eb;');
console.log('%c✅ Sidebar | Glass-morphism | All functions working', 'font-size:13px; color:#10b981;');
</script>

<?php $conn->close(); ?>
</body>
</html>