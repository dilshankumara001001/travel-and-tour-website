<?php
// drivers.php - Complete Driver Management (Premium Glass Design)
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
// CREATE DRIVERS TABLE IF NOT EXISTS
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    license_number VARCHAR(50),
    license_expiry DATE,
    address TEXT,
    status ENUM('available','on_duty','on_leave','inactive') DEFAULT 'available',
    assigned_vehicle_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ============================================================
// HANDLE POST REQUESTS
// ============================================================
$msg = '';
$error = '';

// --- Add Driver ---
if (isset($_POST['add_driver'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $license = $conn->real_escape_string($_POST['license_number']);
    $license_expiry = $conn->real_escape_string($_POST['license_expiry']);
    $address = $conn->real_escape_string($_POST['address']);
    $status = $conn->real_escape_string($_POST['status']);
    if ($conn->query("INSERT INTO drivers (name, phone, license_number, license_expiry, address, status) VALUES ('$name', '$phone', '$license', '$license_expiry', '$address', '$status')")) {
        $msg = "Driver added successfully!";
        $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ({$_SESSION['manager_id']}, 'Added driver: $name', '".$_SERVER['REMOTE_ADDR']."')");
    } else {
        $error = "Failed to add driver: " . $conn->error;
    }
}

// --- Edit Driver ---
if (isset($_POST['edit_driver'])) {
    $id = (int)$_POST['driver_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $license = $conn->real_escape_string($_POST['license_number']);
    $license_expiry = $conn->real_escape_string($_POST['license_expiry']);
    $address = $conn->real_escape_string($_POST['address']);
    $status = $conn->real_escape_string($_POST['status']);
    if ($conn->query("UPDATE drivers SET name='$name', phone='$phone', license_number='$license', license_expiry='$license_expiry', address='$address', status='$status' WHERE id=$id")) {
        $msg = "Driver updated successfully!";
    } else {
        $error = "Failed to update: " . $conn->error;
    }
}

// --- Delete Driver ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $check = $conn->query("SELECT assigned_vehicle_id FROM drivers WHERE id=$id");
    $row = $check->fetch_assoc();
    if ($row && $row['assigned_vehicle_id']) {
        $conn->query("UPDATE vehicles SET driver_id = NULL WHERE driver_id = $id");
    }
    if ($conn->query("DELETE FROM drivers WHERE id=$id")) {
        $msg = "Driver deleted successfully!";
    } else {
        $error = "Failed to delete: " . $conn->error;
    }
}

// --- Assign Driver to Vehicle ---
if (isset($_POST['assign_driver'])) {
    $driver_id = (int)$_POST['driver_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $conn->query("UPDATE vehicles SET driver_id = NULL WHERE driver_id = $driver_id");
    if ($conn->query("UPDATE vehicles SET driver_id = $driver_id WHERE id = $vehicle_id") && 
        $conn->query("UPDATE drivers SET assigned_vehicle_id = $vehicle_id WHERE id = $driver_id")) {
        $msg = "Driver assigned to vehicle successfully!";
    } else {
        $error = "Failed to assign: " . $conn->error;
    }
}

// --- Unassign Driver ---
if (isset($_GET['unassign']) && is_numeric($_GET['unassign'])) {
    $id = (int)$_GET['unassign'];
    if ($conn->query("UPDATE drivers SET assigned_vehicle_id = NULL WHERE id = $id") &&
        $conn->query("UPDATE vehicles SET driver_id = NULL WHERE driver_id = $id")) {
        $msg = "Driver unassigned successfully!";
    } else {
        $error = "Failed to unassign: " . $conn->error;
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$drivers = [];
$res = $conn->query("SELECT d.*, v.vehicle_name AS assigned_vehicle 
                      FROM drivers d 
                      LEFT JOIN vehicles v ON d.assigned_vehicle_id = v.id 
                      ORDER BY d.id DESC");
while ($row = $res->fetch_assoc()) {
    $drivers[] = $row;
}

$vehicles_list = [];
$res = $conn->query("SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name");
while ($row = $res->fetch_assoc()) {
    $vehicles_list[] = $row;
}

$total_drivers = count($drivers);
$available_drivers = $conn->query("SELECT COUNT(*) c FROM drivers WHERE status = 'available'")->fetch_assoc()['c'] ?? 0;
$on_duty = $conn->query("SELECT COUNT(*) c FROM drivers WHERE status = 'on_duty'")->fetch_assoc()['c'] ?? 0;
$on_leave = $conn->query("SELECT COUNT(*) c FROM drivers WHERE status = 'on_leave'")->fetch_assoc()['c'] ?? 0;
$all_drivers = $total_drivers;

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
    <title>🚗 Driver Management</title>
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
           STATS CARDS
        ============================================================ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
        .stat-card .icon-wrap.blue { background: var(--gradient-primary); }
        .stat-card .icon-wrap.green { background: var(--gradient-success); }
        .stat-card .icon-wrap.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
        .stat-card .icon-wrap.yellow { background: var(--gradient-warning); }
        .stat-card .icon-wrap.red { background: var(--gradient-danger); }
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
           TABLE CONTAINER - GLASS
        ============================================================ */
        .table-container {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow-card);
            overflow-x: auto;
        }
        .table-container .table-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap; gap: 8px;
        }
        .table-container .table-header h5 {
            font-size: 15px; font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .table-container .table-header h5 i { color: var(--color-blue); }

        .table {
            font-size: 12px;
            margin-bottom: 0;
            background: #ffffff !important;
            border-collapse: collapse;
            width: 100%;
        }
        .table thead th {
            background: #f8fafc !important;
            color: #1e293b !important;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        .table thead th i { color: #64748b; }
        .table tbody td {
            padding: 8px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #0a1929 !important;
            background: #ffffff !important;
        }
        .table tbody tr:hover td {
            background: #f1f5f9 !important;
        }

        /* Force dark mode table to remain white with black text */
        [data-theme="dark"] .table-container {
            background: #ffffff !important;
            border-color: #e2e8f0 !important;
        }
        [data-theme="dark"] .table {
            background: #ffffff !important;
            color: #000000 !important;
        }
        [data-theme="dark"] .table thead th {
            background: #f8fafc !important;
            color: #1e293b !important;
            border-color: #e2e8f0 !important;
        }
        [data-theme="dark"] .table tbody td {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #e2e8f0 !important;
        }
        [data-theme="dark"] .table tbody tr:hover td {
            background: #f1f5f9 !important;
        }
        [data-theme="dark"] .table tbody td strong,
        [data-theme="dark"] .table tbody td .fw-bold,
        [data-theme="dark"] .table tbody td span,
        [data-theme="dark"] .table tbody td a,
        [data-theme="dark"] .table tbody td .btn {
            color: inherit !important;
        }

        /* ============================================================
           BADGES
        ============================================================ */
        .badge-status {
            padding: 2px 10px; border-radius: 20px;
            font-size: 9px; font-weight: 600;
            display: inline-block;
        }
        .badge-status.available { background: #dcfce7; color: #166534; }
        .badge-status.on_duty { background: #dbeafe; color: #1e40af; }
        .badge-status.on_leave { background: #fef3c7; color: #92400e; }
        .badge-status.inactive { background: #fee2e2; color: #991b1b; }

        .badge-assigned {
            background: #dcfce7;
            color: #166534;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-unassigned {
            color: #6b7280;
            font-size: 10px;
        }

        /* ============================================================
           BUTTONS
        ============================================================ */
        .btn-sm { padding: 3px 10px; font-size: 10px; border-radius: 6px; }
        .btn-edit {
            background: #e0e7ff;
            color: #3730a3 !important;
            border: 1px solid #c7d2fe;
        }
        .btn-edit:hover { background: #c7d2fe; }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b !important;
            border: 1px solid #fecaca;
        }
        .btn-delete:hover { background: #fecaca; }
        .btn-assign {
            background: #dcfce7;
            color: #166534 !important;
            border: 1px solid #bbf7d0;
        }
        .btn-assign:hover { background: #bbf7d0; }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            color: #fff;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-success {
            background: var(--gradient-success);
            border: none;
            color: #fff;
        }
        .btn-success:hover { opacity: 0.9; transform: translateY(-1px); }

        /* ============================================================
           MODALS - GLASS
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
            background: var(--bg-body);
            border: 1px solid var(--border-card);
            color: var(--text-primary);
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
        .form-control::placeholder { color: var(--text-muted); }

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
            .table-container { padding: 12px 14px; }
            .table thead th, .table tbody td { font-size: 11px; padding: 6px 8px; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }

        /* ============================================================
           PRINT
        ============================================================ */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .table-container { box-shadow: none !important; border: 1px solid #ddd !important; }
            .table { background: #fff !important; }
            .table tbody td { color: #000 !important; }
            .sidebar, .top-header, .stat-grid { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 10px !important; width: 100% !important; }
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
        <a href="vehicles.php"><i class="fas fa-list"></i> Vehicles <span class="badge-nav"><?= $conn->query("SELECT COUNT(*) c FROM vehicles")->fetch_assoc()['c'] ?? 0 ?></span></a>
        <a href="filter.php"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php" class="active"><i class="fas fa-user"></i> Drivers <span class="badge-nav"><?= $total_drivers ?></span></a>
        <div class="nav-label" style="margin-top:8px;">Management</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#addDriverModal"><i class="fas fa-user-plus"></i> Add Driver</a>
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
            <p><i class="fas fa-users"></i> Driver Management</p>
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
        <div class="stat-card"><div class="icon-wrap blue"><i class="fas fa-users"></i></div><div class="info"><div class="number"><?= $total_drivers ?></div><div class="label">Total Drivers</div></div></div>
        <div class="stat-card"><div class="icon-wrap green"><i class="fas fa-check-circle"></i></div><div class="info"><div class="number"><?= $available_drivers ?></div><div class="label">Available</div></div></div>
        <div class="stat-card"><div class="icon-wrap purple"><i class="fas fa-clock"></i></div><div class="info"><div class="number"><?= $on_duty ?></div><div class="label">On Duty</div></div></div>
        <div class="stat-card"><div class="icon-wrap yellow"><i class="fas fa-coffee"></i></div><div class="info"><div class="number"><?= $on_leave ?></div><div class="label">On Leave</div></div></div>
        <div class="stat-card"><div class="icon-wrap red"><i class="fas fa-user-friends"></i></div><div class="info"><div class="number"><?= $all_drivers ?></div><div class="label">All Drivers</div></div></div>
    </div>

    <!-- ===== ALERTS ===== -->
    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ===== DRIVERS TABLE ===== -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-user"></i> All Drivers</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                <i class="fas fa-plus"></i> Add Driver
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-user"></i> Name</th>
                        <th><i class="fas fa-phone"></i> Phone</th>
                        <th><i class="fas fa-id-card"></i> License</th>
                        <th><i class="fas fa-calendar-alt"></i> Expiry</th>
                        <th><i class="fas fa-circle"></i> Status</th>
                        <th><i class="fas fa-car"></i> Assigned Vehicle</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($drivers) > 0): ?>
                        <?php foreach ($drivers as $d): ?>
                        <tr>
                            <td><strong>#<?= $d['id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                            <td><?= htmlspecialchars($d['phone']) ?></td>
                            <td><?= htmlspecialchars($d['license_number']) ?></td>
                            <td><?= $d['license_expiry'] ? date('d M Y', strtotime($d['license_expiry'])) : 'N/A' ?></td>
                            <td><span class="badge-status <?= $d['status'] ?>"><?= ucfirst(str_replace('_',' ',$d['status'])) ?></span></td>
                            <td>
                                <?php if ($d['assigned_vehicle_id']): ?>
                                    <span class="badge-assigned"><?= htmlspecialchars($d['assigned_vehicle']) ?></span>
                                    <a href="?unassign=<?= $d['id'] ?>" class="btn btn-sm btn-delete" onclick="return confirm('Unassign this driver?')"><i class="fas fa-times"></i></a>
                                <?php else: ?>
                                    <span class="badge-unassigned">Not assigned</span>
                                    <button class="btn btn-sm btn-assign" data-bs-toggle="modal" data-bs-target="#assignModal" data-driver-id="<?= $d['id'] ?>" data-driver-name="<?= htmlspecialchars($d['name']) ?>"><i class="fas fa-link"></i> Assign</button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-edit" data-bs-toggle="modal" data-bs-target="#editDriverModal" 
                                        data-id="<?= $d['id'] ?>" 
                                        data-name="<?= htmlspecialchars($d['name']) ?>" 
                                        data-phone="<?= htmlspecialchars($d['phone']) ?>" 
                                        data-license="<?= htmlspecialchars($d['license_number']) ?>" 
                                        data-expiry="<?= $d['license_expiry'] ?>" 
                                        data-address="<?= htmlspecialchars($d['address']) ?>" 
                                        data-status="<?= $d['status'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $d['id'] ?>" class="btn btn-sm btn-delete" onclick="return confirm('Delete this driver?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-3" style="color:var(--text-muted);">No drivers found. Add a driver to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        &copy; <?= date('Y') ?> Travel &amp; Tour · Driver Management ·
        <span id="footerTime"></span> ·
        <i class="fas fa-keyboard"></i> Ctrl+Shift+D (Theme)
    </div>

</main>

<!-- ============================================================
MODALS
============================================================ -->

<!-- Add Driver Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="mb-2"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-control"></div>
        <div class="mb-2"><label class="form-label">License Expiry</label><input type="date" name="license_expiry" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="available">Available</option><option value="on_duty">On Duty</option><option value="on_leave">On Leave</option><option value="inactive">Inactive</option></select></div>
        <input type="hidden" name="add_driver" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Add Driver</button></div></form>
</div></div></div>

<!-- Edit Driver Modal -->
<div class="modal fade" id="editDriverModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <input type="hidden" name="driver_id" id="edit_driver_id">
        <div class="mb-2"><label class="form-label">Full Name *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
        <div class="mb-2"><label class="form-label">License Number</label><input type="text" name="license_number" id="edit_license" class="form-control"></div>
        <div class="mb-2"><label class="form-label">License Expiry</label><input type="date" name="license_expiry" id="edit_expiry" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" id="edit_address" class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Status</label><select name="status" id="edit_status" class="form-select"><option value="available">Available</option><option value="on_duty">On Duty</option><option value="on_leave">On Leave</option><option value="inactive">Inactive</option></select></div>
        <input type="hidden" name="edit_driver" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update Driver</button></div></form>
</div></div></div>

<!-- Assign Driver Modal -->
<div class="modal fade" id="assignModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-link"></i> Assign Driver to Vehicle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Driver</label><input type="text" id="assign_driver_name" class="form-control" disabled></div>
        <input type="hidden" name="driver_id" id="assign_driver_id">
        <div class="mb-2"><label class="form-label">Select Vehicle</label><select name="vehicle_id" class="form-select" required>
            <option value="">-- Choose Vehicle --</option>
            <?php foreach ($vehicles_list as $v): ?>
                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
            <?php endforeach; ?>
        </select></div>
        <input type="hidden" name="assign_driver" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-success">Assign</button></div></form>
</div></div></div>

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
// 2. CLOCK
// ============================================================
function updateClock() {
    const n = new Date();
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
// 5. EDIT MODAL POPULATE
// ============================================================
document.querySelectorAll('[data-bs-target="#editDriverModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_driver_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_phone').value = this.dataset.phone;
        document.getElementById('edit_license').value = this.dataset.license;
        document.getElementById('edit_expiry').value = this.dataset.expiry;
        document.getElementById('edit_address').value = this.dataset.address;
        document.getElementById('edit_status').value = this.dataset.status;
    });
});

// ============================================================
// 6. ASSIGN MODAL POPULATE
// ============================================================
document.querySelectorAll('[data-bs-target="#assignModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('assign_driver_id').value = this.dataset.driverId;
        document.getElementById('assign_driver_name').value = this.dataset.driverName;
    });
});

// ============================================================
// 7. KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        themeToggle.click();
    }
});

// ============================================================
// 8. FUNCTIONS
// ============================================================
function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
}
function refreshData() {
    location.reload();
}

console.log('%c🚗 Driver Management (Premium Glass Design)', 'font-size:18px; font-weight:bold; color:#2563eb;');
console.log('%c✅ Sidebar | Glass-morphism | All functions working', 'font-size:13px; color:#10b981;');
</script>
</body>
</html>