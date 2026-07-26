<?php
// ============================================================
// add_vehicle.php - Add New Vehicle (Premium Glass Design)
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
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$message_type = '';
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vehicle'])) {
    $vehicle_name = trim($_POST['vehicle_name'] ?? '');
    $price_per_km = floatval($_POST['price_per_km'] ?? 0);
    $petrol_rate = floatval($_POST['petrol_rate'] ?? 0);
    $capacity = intval($_POST['capacity'] ?? 0);
    $operator_id = isset($_POST['operator_id']) && $_POST['operator_id'] !== '' ? intval($_POST['operator_id']) : null;
    $status = trim($_POST['status'] ?? 'Available');
    $driver_name = trim($_POST['driver_name'] ?? '');
    $driver_phone = trim($_POST['driver_phone'] ?? '');
    $last_lat = floatval($_POST['last_lat'] ?? 0);
    $last_lng = floatval($_POST['last_lng'] ?? 0);
    
    $form_data = [
        'vehicle_name' => $vehicle_name,
        'price_per_km' => $price_per_km,
        'petrol_rate' => $petrol_rate,
        'capacity' => $capacity,
        'operator_id' => $operator_id,
        'status' => $status,
        'driver_name' => $driver_name,
        'driver_phone' => $driver_phone,
        'last_lat' => $last_lat,
        'last_lng' => $last_lng
    ];
    
    $errors = [];
    if (empty($vehicle_name)) $errors[] = "Vehicle name is required";
    if ($price_per_km <= 0) $errors[] = "Price per KM must be greater than 0";
    if ($capacity <= 0) $errors[] = "Capacity must be greater than 0";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO vehicles 
            (vehicle_name, price_per_km, petrol_rate, capacity, operator_id, status, 
             driver_name, driver_phone, last_lat, last_lng, last_update) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->bind_param(
            "sddiisssdd",
            $vehicle_name,
            $price_per_km,
            $petrol_rate,
            $capacity,
            $operator_id,
            $status,
            $driver_name,
            $driver_phone,
            $last_lat,
            $last_lng
        );
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $message = '✅ Vehicle "' . htmlspecialchars($vehicle_name) . '" added successfully! (ID: ' . $new_id . ')';
            $message_type = 'success';
            $form_data = [];
            echo "<script>localStorage.removeItem('add_vehicle_draft');</script>";
        } else {
            $message = '❌ Error: ' . $conn->error;
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        $message = '❌ Please fix the following errors: ' . implode(', ', $errors);
        $message_type = 'danger';
    }
}

// ============================================================
// HANDLE AJAX VALIDATION (Check duplicate vehicle name)
// ============================================================
if (isset($_GET['check_name'])) {
    $name = trim($_GET['check_name']);
    $check = $conn->query("SELECT id FROM vehicles WHERE vehicle_name = '$name'");
    if ($check->num_rows > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// ============================================================
// HANDLE AJAX CLONE (Get vehicle data for cloning)
// ============================================================
if (isset($_GET['clone'])) {
    $id = intval($_GET['clone']);
    $res = $conn->query("SELECT * FROM vehicles WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'vehicle_name' => $row['vehicle_name'] . ' (Copy)',
            'price_per_km' => $row['price_per_km'],
            'petrol_rate' => $row['petrol_rate'],
            'capacity' => $row['capacity'],
            'operator_id' => $row['operator_id'],
            'status' => $row['status'],
            'driver_name' => $row['driver_name'],
            'driver_phone' => $row['driver_phone'],
            'last_lat' => $row['last_lat'],
            'last_lng' => $row['last_lng']
        ]);
    } else {
        echo json_encode(['error' => 'Vehicle not found']);
    }
    exit;
}

// ============================================================
// GET DRIVERS FOR SUGGESTIONS
// ============================================================
$drivers = [];
$drv_res = $conn->query("SELECT name, phone FROM drivers ORDER BY name");
while ($drv = $drv_res->fetch_assoc()) {
    $drivers[] = $drv;
}
$drivers_json = json_encode($drivers);

// ============================================================
// GET VEHICLES FOR CLONE DROPDOWN
// ============================================================
$vehicles_list = [];
$v_res = $conn->query("SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name");
while ($v = $v_res->fetch_assoc()) {
    $vehicles_list[] = $v;
}

// ============================================================
// STATS
// ============================================================
$total_all = $conn->query("SELECT COUNT(*) as total FROM vehicles")->fetch_assoc()['total'];
$op_counts = [];
$op_res = $conn->query("SELECT operator_id, COUNT(*) as total FROM vehicles GROUP BY operator_id");
while ($row = $op_res->fetch_assoc()) {
    $op_counts[$row['operator_id'] ?? 'null'] = $row['total'];
}

// ============================================================
// THEME & GREETING
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
    <title>➕ Add Vehicle</title>
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
            /* Inputs */
            --input-bg: rgba(255,255,255,0.6);
            --input-border: rgba(0,0,0,0.08);
            --input-focus: rgba(255,255,255,0.8);
            --input-text: #0f172a;
            --input-placeholder: #94a3b8;
            /* Alerts */
            --success-bg: rgba(16,185,129,0.12);
            --success-text: #0f7b4a;
            --danger-bg: rgba(239,68,68,0.12);
            --danger-text: #991b1b;
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
            --success-bg: rgba(16,185,129,0.15);
            --success-text: #34d399;
            --danger-bg: rgba(239,68,68,0.15);
            --danger-text: #f87171;
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
            grid-template-columns: repeat(4, 1fr);
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
           MAIN CARDS (Glass)
        ============================================================ */
        .main-card {
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
        }
        .main-card:hover {
            border-color: rgba(37,99,235,0.1);
            box-shadow: var(--shadow-hover);
        }
        .main-card h4, .main-card h5 {
            font-weight: 700;
            color: var(--text-primary);
        }
        .main-card label.form-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
        }
        .main-card .form-control,
        .main-card .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-text);
            font-size: 13px;
            border-radius: 10px;
            padding: 10px 14px;
            transition: all var(--transition);
        }
        .main-card .form-control:focus,
        .main-card .form-select:focus {
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
            background: var(--input-focus);
        }
        .main-card .form-control::placeholder {
            color: var(--input-placeholder);
        }
        .main-card .input-group-text {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-secondary);
        }
        .main-card .form-text {
            color: var(--text-muted);
            font-size: 11px;
        }
        .main-card .btn-primary {
            background: var(--gradient-primary);
            border: none;
            color: #fff;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            transition: all var(--transition);
        }
        .main-card .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37,99,235,0.25);
        }
        .main-card .btn-success {
            background: var(--gradient-success);
            border: none;
            color: #fff;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            transition: all var(--transition);
        }
        .main-card .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16,185,129,0.25);
        }
        .main-card .btn-outline-secondary {
            border-color: var(--border-card);
            color: var(--text-secondary);
            background: var(--bg-body);
        }
        .main-card .btn-outline-secondary:hover {
            background: var(--border-card);
            color: var(--text-primary);
        }

        /* ============================================================
           ALERTS (Glass)
        ============================================================ */
        .alert-custom {
            border-radius: 12px;
            padding: 14px 20px;
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-card);
        }
        .alert-custom.success {
            background: var(--success-bg);
            color: var(--success-text);
            border-left: 4px solid var(--color-green);
        }
        .alert-custom.danger {
            background: var(--danger-bg);
            color: var(--danger-text);
            border-left: 4px solid var(--color-red);
        }
        .alert-custom i { margin-right: 10px; }

        /* ============================================================
           PREVIEW CARD
        ============================================================ */
        .preview-card {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 16px 18px;
            border: 1px solid var(--border-card);
            min-height: 180px;
        }
        .preview-card .preview-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .preview-card .preview-detail {
            font-size: 13px;
            color: var(--text-secondary);
            padding: 3px 0;
        }
        .preview-card .preview-detail i {
            width: 20px;
            color: var(--color-blue);
        }
        .preview-card .preview-empty {
            color: var(--text-muted);
            text-align: center;
            padding: 30px 0;
            font-style: italic;
        }

        /* ============================================================
           CLONE SECTION
        ============================================================ */
        .clone-section {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px dashed var(--border-card);
            margin-bottom: 16px;
        }

        /* ============================================================
           PROGRESS BAR
        ============================================================ */
        .progress-form {
            height: 4px;
            background: var(--border-card);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .progress-form .progress-bar-form {
            height: 100%;
            background: var(--color-green);
            transition: width 0.5s ease;
            width: 0%;
            border-radius: 2px;
        }

        /* ============================================================
           AUTOCOMPLETE
        ============================================================ */
        .autocomplete-items {
            position: absolute;
            border: 1px solid var(--border-card);
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            backdrop-filter: var(--glass-blur);
            border-radius: 0 0 10px 10px;
            max-height: 150px;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
        }
        .autocomplete-items div {
            padding: 6px 12px;
            cursor: pointer;
            color: var(--text-primary);
            transition: 0.2s;
            font-size: 13px;
        }
        .autocomplete-items div:hover {
            background: rgba(0,0,0,0.04);
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
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
            .main-card { padding: 14px 16px; }
            .main-card .row.g-4 { gap: 0; }
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
            .main-card { box-shadow: none !important; border: 1px solid #ddd !important; background: #fff !important; }
            .sidebar, .top-header, .stat-grid { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 10px !important; width: 100% !important; }
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
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php" class="active"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
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
            <p><i class="fas fa-plus-circle" style="color:var(--color-green);"></i> Add New Vehicle</p>
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
    </div>

    <!-- ===== ALERTS ===== -->
    <?php if (!empty($message)): ?>
        <div class="alert-custom <?= $message_type ?> mb-4">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
            <?php if ($message_type == 'success'): ?>
                <div class="mt-2">
                    <a href="vehicles.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list"></i> View All</a>
                    <a href="add_vehicle.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus"></i> Add Another</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ===== MAIN FORM CARD ===== -->
    <div class="main-card">
        <h4><i class="fas fa-plus-circle" style="color:var(--color-green);"></i> Vehicle Details</h4>

        <!-- Clone Section -->
        <div class="clone-section no-print">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <label class="form-label mb-1"><i class="fas fa-copy"></i> Clone from Existing Vehicle</label>
                    <select class="form-select" id="cloneSelect">
                        <option value="">-- Select Vehicle to Clone --</option>
                        <?php foreach ($vehicles_list as $v): ?>
                            <option value="<?= $v['id'] ?>">#<?= $v['id'] ?> - <?= htmlspecialchars($v['vehicle_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">&nbsp;</label>
                    <button type="button" class="btn btn-primary w-100" id="cloneBtn">
                        <i class="fas fa-copy"></i> Clone
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-form">
            <div class="progress-bar-form" id="formProgress"></div>
        </div>

        <!-- Form -->
        <form method="POST" action="" id="vehicleForm">
            <input type="hidden" name="add_vehicle" value="1">
            <div class="row g-4">
                <!-- Vehicle Name -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-car" style="color:var(--color-blue);"></i> Vehicle Name <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="text" name="vehicle_name" id="vehicleName" class="form-control" 
                               placeholder="e.g., Toyota Camry 2024" 
                               value="<?= htmlspecialchars($form_data['vehicle_name'] ?? '') ?>" required>
                        <span class="position-absolute" style="right:12px; top:50%; transform:translateY(-50%);" id="nameValidation"></span>
                    </div>
                    <div class="form-text" id="nameFeedback">Enter the full vehicle name</div>
                </div>

                <!-- Operator -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-user-tie" style="color:var(--color-blue);"></i> Assign Operator</label>
                    <select name="operator_id" class="form-select" id="operatorSelect">
                        <option value="">-- Not Assigned --</option>
                        <option value="1" <?= ($form_data['operator_id'] ?? '') == 1 ? 'selected' : '' ?>>Operator 1</option>
                        <option value="2" <?= ($form_data['operator_id'] ?? '') == 2 ? 'selected' : '' ?>>Operator 2</option>
                        <option value="3" <?= ($form_data['operator_id'] ?? '') == 3 ? 'selected' : '' ?>>Operator 3</option>
                    </select>
                </div>

                <!-- Price per KM -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-money-bill-wave" style="color:var(--color-green);"></i> Price per KM <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">LKR</span>
                        <input type="number" name="price_per_km" id="pricePerKm" class="form-control" 
                               placeholder="120.00" step="0.01" min="0"
                               value="<?= htmlspecialchars($form_data['price_per_km'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Petrol Rate -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-gas-pump" style="color:var(--color-yellow);"></i> Petrol Rate</label>
                    <div class="input-group">
                        <span class="input-group-text">LKR</span>
                        <input type="number" name="petrol_rate" id="petrolRate" class="form-control" 
                               placeholder="45.00" step="0.01" min="0"
                               value="<?= htmlspecialchars($form_data['petrol_rate'] ?? '') ?>">
                    </div>
                </div>

                <!-- Capacity -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-users" style="color:var(--color-teal);"></i> Capacity <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" id="capacity" class="form-control" 
                           placeholder="4" min="1"
                           value="<?= htmlspecialchars($form_data['capacity'] ?? '') ?>" required>
                </div>

                <!-- Status -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-circle" style="color:var(--color-blue);"></i> Status</label>
                    <div class="d-flex gap-2" id="statusToggle">
                        <button type="button" class="btn btn-sm status-btn <?= ($form_data['status'] ?? 'Available') == 'Available' ? 'btn-success' : 'btn-outline-secondary' ?>" data-status="Available">Available</button>
                        <button type="button" class="btn btn-sm status-btn <?= ($form_data['status'] ?? '') == 'Booked' ? 'btn-warning' : 'btn-outline-secondary' ?>" data-status="Booked">Booked</button>
                        <button type="button" class="btn btn-sm status-btn <?= ($form_data['status'] ?? '') == 'Maintenance' ? 'btn-danger' : 'btn-outline-secondary' ?>" data-status="Maintenance">Maintenance</button>
                    </div>
                    <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($form_data['status'] ?? 'Available') ?>">
                </div>

                <!-- Driver Name -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-user" style="color:var(--color-blue);"></i> Driver Name</label>
                    <div class="position-relative">
                        <input type="text" name="driver_name" id="driverName" class="form-control" 
                               placeholder="Type driver name..." autocomplete="off"
                               value="<?= htmlspecialchars($form_data['driver_name'] ?? '') ?>">
                        <div class="autocomplete-items" id="driverSuggestions"></div>
                    </div>
                </div>

                <!-- Driver Phone -->
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-phone" style="color:var(--color-blue);"></i> Driver Phone</label>
                    <input type="text" name="driver_phone" id="driverPhone" class="form-control" 
                           placeholder="071-2345678"
                           value="<?= htmlspecialchars($form_data['driver_phone'] ?? '') ?>">
                </div>

                <!-- Location -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-map-marker-alt" style="color:var(--color-red);"></i> Last Latitude</label>
                    <div class="input-group">
                        <input type="number" name="last_lat" id="lastLat" class="form-control" 
                               placeholder="6.9271" step="0.0001"
                               value="<?= htmlspecialchars($form_data['last_lat'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-secondary" id="getLocationBtn" title="Get Current Location">
                            <i class="fas fa-location-dot"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-map-marker-alt" style="color:var(--color-red);"></i> Last Longitude</label>
                    <input type="number" name="last_lng" id="lastLng" class="form-control" 
                           placeholder="79.8612" step="0.0001"
                           value="<?= htmlspecialchars($form_data['last_lng'] ?? '') ?>">
                </div>

                <!-- Submit -->
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Add Vehicle</button>
                    <button type="button" class="btn btn-outline-secondary" id="resetDraftBtn"><i class="fas fa-trash"></i> Clear Draft</button>
                    <a href="vehicles.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <!-- ===== PREVIEW & TIPS ===== -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="main-card">
                <h5><i class="fas fa-eye" style="color:var(--color-blue);"></i> Live Preview</h5>
                <div class="preview-card" id="livePreview">
                    <div class="preview-empty">
                        <i class="fas fa-car fa-2x d-block mb-2"></i>
                        <span>Fill in the form to see preview</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="main-card">
                <h5><i class="fas fa-lightbulb" style="color:var(--color-yellow);"></i> Quick Tips</h5>
                <ul class="list-unstyled mb-0" style="color:var(--text-secondary); font-size:13px;">
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Fields with <span class="text-danger">*</span> are required</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> GPS coordinates are optional</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> <kbd>Ctrl+Enter</kbd> = Submit</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Data auto‑saved as draft</li>
                    <li><i class="fas fa-check-circle" style="color:var(--color-green);"></i> Click <kbd>Clone</kbd> to copy existing vehicle</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer" style="margin-top:20px; padding:10px 0; border-top:1px solid var(--border-card); text-align:center; color:var(--text-muted); font-size:10px;">
        &copy; <?= date('Y') ?> Travel &amp; Tour · Add Vehicle · <span id="footerTime"></span>
        <span style="margin-left:12px;"><i class="fas fa-keyboard"></i> Ctrl+Shift+D (Theme)</span>
    </div>

</main>

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
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        themeToggle.click();
    }
    if (e.ctrlKey && e.key === 'Enter') {
        const form = document.getElementById('vehicleForm');
        if (form) form.submit();
    }
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        if (confirm('Reset all form fields?')) {
            document.getElementById('vehicleForm').reset();
            updatePreview();
            updateProgress();
        }
    }
});

// ============================================================
// CLONE
// ============================================================
document.getElementById('cloneBtn')?.addEventListener('click', function() {
    const id = document.getElementById('cloneSelect').value;
    if (!id) {
        alert('Please select a vehicle to clone.');
        return;
    }
    if (!confirm('Clone this vehicle? All data will be copied.')) return;
    
    fetch('add_vehicle.php?clone=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            document.getElementById('vehicleName').value = data.vehicle_name || '';
            document.getElementById('pricePerKm').value = data.price_per_km || '';
            document.getElementById('petrolRate').value = data.petrol_rate || '';
            document.getElementById('capacity').value = data.capacity || '';
            document.getElementById('operatorSelect').value = data.operator_id || '';
            document.getElementById('statusInput').value = data.status || 'Available';
            document.getElementById('driverName').value = data.driver_name || '';
            document.getElementById('driverPhone').value = data.driver_phone || '';
            document.getElementById('lastLat').value = data.last_lat || '';
            document.getElementById('lastLng').value = data.last_lng || '';
            
            updateStatusButtons(data.status || 'Available');
            updatePreview();
            updateProgress();
            showToast('Vehicle data cloned successfully!');
        })
        .catch(err => alert('Error cloning vehicle: ' + err));
});

// ============================================================
// AJAX VALIDATION (Duplicate Name)
// ============================================================
let nameCheckTimer = null;
document.getElementById('vehicleName')?.addEventListener('input', function() {
    const name = this.value.trim();
    const icon = document.getElementById('nameValidation');
    const feedback = document.getElementById('nameFeedback');
    
    clearTimeout(nameCheckTimer);
    if (name.length < 3) {
        icon.innerHTML = '';
        feedback.textContent = 'Enter at least 3 characters';
        feedback.style.color = 'var(--text-secondary)';
        return;
    }
    
    icon.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#0d6efd;"></i>';
    feedback.textContent = 'Checking...';
    feedback.style.color = 'var(--text-secondary)';
    
    nameCheckTimer = setTimeout(() => {
        fetch('add_vehicle.php?check_name=' + encodeURIComponent(name))
            .then(r => r.json())
            .then(data => {
                if (data.exists) {
                    icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
                    feedback.textContent = '⚠️ This vehicle name already exists!';
                    feedback.style.color = '#dc3545';
                } else {
                    icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
                    feedback.textContent = '✅ Name is available';
                    feedback.style.color = '#28a745';
                }
            })
            .catch(() => {
                icon.innerHTML = '';
                feedback.textContent = 'Could not check name.';
                feedback.style.color = 'var(--text-secondary)';
            });
    }, 500);
});

// ============================================================
// DRIVER SUGGESTIONS
// ============================================================
const drivers = <?= $drivers_json ?>;
const driverInput = document.getElementById('driverName');
const suggestionsDiv = document.getElementById('driverSuggestions');

driverInput?.addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    suggestionsDiv.innerHTML = '';
    if (val.length < 1) return;
    
    const matches = drivers.filter(d => d.name.toLowerCase().includes(val));
    if (matches.length === 0) return;
    
    matches.slice(0, 5).forEach(d => {
        const div = document.createElement('div');
        div.textContent = d.name + ' (' + d.phone + ')';
        div.addEventListener('click', function() {
            driverInput.value = d.name;
            document.getElementById('driverPhone').value = d.phone;
            suggestionsDiv.innerHTML = '';
            updatePreview();
        });
        suggestionsDiv.appendChild(div);
    });
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.position-relative')) {
        suggestionsDiv.innerHTML = '';
    }
});

// ============================================================
// AUTO-SAVE DRAFT
// ============================================================
function saveDraft() {
    const data = {
        vehicle_name: document.getElementById('vehicleName').value,
        price_per_km: document.getElementById('pricePerKm').value,
        petrol_rate: document.getElementById('petrolRate').value,
        capacity: document.getElementById('capacity').value,
        operator_id: document.getElementById('operatorSelect').value,
        status: document.getElementById('statusInput').value,
        driver_name: document.getElementById('driverName').value,
        driver_phone: document.getElementById('driverPhone').value,
        last_lat: document.getElementById('lastLat').value,
        last_lng: document.getElementById('lastLng').value
    };
    localStorage.setItem('add_vehicle_draft', JSON.stringify(data));
}

function loadDraft() {
    const saved = localStorage.getItem('add_vehicle_draft');
    if (!saved) return;
    try {
        const data = JSON.parse(saved);
        if (data.vehicle_name && !document.getElementById('vehicleName').value) {
            document.getElementById('vehicleName').value = data.vehicle_name || '';
            document.getElementById('pricePerKm').value = data.price_per_km || '';
            document.getElementById('petrolRate').value = data.petrol_rate || '';
            document.getElementById('capacity').value = data.capacity || '';
            document.getElementById('operatorSelect').value = data.operator_id || '';
            document.getElementById('statusInput').value = data.status || 'Available';
            document.getElementById('driverName').value = data.driver_name || '';
            document.getElementById('driverPhone').value = data.driver_phone || '';
            document.getElementById('lastLat').value = data.last_lat || '';
            document.getElementById('lastLng').value = data.last_lng || '';
            updateStatusButtons(data.status || 'Available');
            updatePreview();
            updateProgress();
            showToast('Draft loaded!');
        }
    } catch(e) {}
}

// Auto-save on change
document.querySelectorAll('#vehicleForm input, #vehicleForm select').forEach(el => {
    el.addEventListener('change', function() {
        saveDraft();
        updatePreview();
        updateProgress();
    });
    el.addEventListener('keyup', function() {
        if (this.tagName === 'INPUT') {
            saveDraft();
            updatePreview();
            updateProgress();
        }
    });
});

document.getElementById('resetDraftBtn')?.addEventListener('click', function() {
    if (confirm('Clear all form data and remove draft?')) {
        localStorage.removeItem('add_vehicle_draft');
        document.getElementById('vehicleForm').reset();
        document.getElementById('statusInput').value = 'Available';
        updateStatusButtons('Available');
        updatePreview();
        updateProgress();
        showToast('Draft cleared!');
    }
});

document.addEventListener('DOMContentLoaded', loadDraft);

// ============================================================
// LIVE PREVIEW
// ============================================================
function updatePreview() {
    const name = document.getElementById('vehicleName').value || 'Not specified';
    const price = document.getElementById('pricePerKm').value || '0.00';
    const capacity = document.getElementById('capacity').value || '0';
    const operator = document.getElementById('operatorSelect');
    const opText = operator.options[operator.selectedIndex]?.text || 'Not Assigned';
    const status = document.getElementById('statusInput').value || 'Available';
    const driver = document.getElementById('driverName').value || 'Not assigned';
    const phone = document.getElementById('driverPhone').value || '';
    
    const statusColors = { 'Available': 'success', 'Booked': 'warning', 'Maintenance': 'danger' };
    const preview = document.getElementById('livePreview');
    if (!name || name === 'Not specified') {
        preview.innerHTML = `
            <div class="preview-empty">
                <i class="fas fa-car fa-2x d-block mb-2"></i>
                <span>Fill in the form to see preview</span>
            </div>
        `;
        return;
    }
    
    preview.innerHTML = `
        <div class="preview-name">
            <i class="fas fa-car" style="color:#0d6efd;"></i> ${name}
            <span class="badge bg-${statusColors[status] || 'secondary'} ms-2">${status}</span>
        </div>
        <div class="preview-detail"><i class="fas fa-money-bill-wave"></i> Price: <strong>LKR ${parseFloat(price).toFixed(2)}</strong> / km</div>
        <div class="preview-detail"><i class="fas fa-users"></i> Capacity: <strong>${capacity}</strong> seats</div>
        <div class="preview-detail"><i class="fas fa-user-tie"></i> Operator: <strong>${opText}</strong></div>
        <div class="preview-detail"><i class="fas fa-user"></i> Driver: <strong>${driver}</strong> ${phone ? '('+phone+')' : ''}</div>
        <div class="preview-detail"><i class="fas fa-gas-pump"></i> Petrol Rate: <strong>LKR ${parseFloat(document.getElementById('petrolRate').value || 0).toFixed(2)}</strong> / km</div>
    `;
}

function updateProgress() {
    const fields = ['vehicleName', 'pricePerKm', 'capacity'];
    let filled = 0;
    fields.forEach(id => {
        const val = document.getElementById(id)?.value;
        if (val && val != '0' && val != '0.00') filled++;
    });
    const total = fields.length;
    const pct = Math.round((filled / total) * 100);
    const bar = document.getElementById('formProgress');
    if (bar) bar.style.width = pct + '%';
}

function updateStatusButtons(status) {
    document.querySelectorAll('.status-btn').forEach(btn => {
        const s = btn.dataset.status;
        btn.className = 'btn btn-sm status-btn';
        if (s === status) {
            if (s === 'Available') btn.classList.add('btn-success');
            else if (s === 'Booked') btn.classList.add('btn-warning');
            else if (s === 'Maintenance') btn.classList.add('btn-danger');
        } else {
            btn.classList.add('btn-outline-secondary');
        }
    });
    document.getElementById('statusInput').value = status;
}

document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const status = this.dataset.status;
        updateStatusButtons(status);
        saveDraft();
        updatePreview();
    });
});

document.querySelectorAll('#vehicleForm input, #vehicleForm select').forEach(el => {
    el.addEventListener('change', updatePreview);
    el.addEventListener('keyup', function() {
        if (this.tagName === 'INPUT') updatePreview();
    });
});

// ============================================================
// GEOLOCATION
// ============================================================
document.getElementById('getLocationBtn')?.addEventListener('click', function() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('lastLat').value = pos.coords.latitude.toFixed(6);
            document.getElementById('lastLng').value = pos.coords.longitude.toFixed(6);
            document.getElementById('getLocationBtn').innerHTML = '<i class="fas fa-location-dot"></i>';
            showToast('Location updated!');
            saveDraft();
            updatePreview();
        },
        function(err) {
            document.getElementById('getLocationBtn').innerHTML = '<i class="fas fa-location-dot"></i>';
            alert('Error getting location: ' + err.message);
        }
    );
});

// ============================================================
// TOAST
// ============================================================
function showToast(msg, type = 'success') {
    const div = document.createElement('div');
    div.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    div.style.cssText = 'bottom:20px; right:20px; z-index:9999; padding:8px 20px; font-size:13px; border-radius:10px;';
    div.innerHTML = msg + ' <button type="button" class="btn-close btn-sm" onclick="this.parentElement.remove()"></button>';
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

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

// Initial updates
updatePreview();
updateProgress();

console.log('%c➕ Add Vehicle (Premium Glass Design)', 'font-size:18px; font-weight:bold; color:#2563eb;');
console.log('%c✅ Sidebar | Glass-morphism | All features working', 'font-size:13px; color:#10b981;');
</script>

<?php $conn->close(); ?>
</body>
</html>