<?php
session_start();
include("../includes/db.php");

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// ============================================================
// 1. HANDLE ACTIONS (ADD, EDIT, DELETE, STATUS UPDATE)
// ============================================================

// --- ADD VEHICLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $vehicle_name = mysqli_real_escape_string($conn, $_POST['vehicle_name']);
    $price_per_km = (float) $_POST['price_per_km'];
    $petrol_rate = (float) $_POST['petrol_rate'];
    $capacity = (int) $_POST['capacity'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $driver_name = mysqli_real_escape_string($conn, $_POST['driver_name'] ?? '');
    $driver_phone = mysqli_real_escape_string($conn, $_POST['driver_phone'] ?? '');

    $query = "INSERT INTO vehicles (vehicle_name, price_per_km, petrol_rate, capacity, status, driver_name, driver_phone) 
              VALUES ('$vehicle_name', $price_per_km, $petrol_rate, $capacity, '$status', '$driver_name', '$driver_phone')";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('✅ Vehicle added successfully!'); window.location.href='vehicles.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . mysqli_error($conn) . "');</script>";
    }
}

// --- EDIT VEHICLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vehicle'])) {
    $id = (int) $_POST['id'];
    $vehicle_name = mysqli_real_escape_string($conn, $_POST['vehicle_name']);
    $price_per_km = (float) $_POST['price_per_km'];
    $petrol_rate = (float) $_POST['petrol_rate'];
    $capacity = (int) $_POST['capacity'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $driver_name = mysqli_real_escape_string($conn, $_POST['driver_name'] ?? '');
    $driver_phone = mysqli_real_escape_string($conn, $_POST['driver_phone'] ?? '');

    $query = "UPDATE vehicles SET 
              vehicle_name = '$vehicle_name',
              price_per_km = $price_per_km,
              petrol_rate = $petrol_rate,
              capacity = $capacity,
              status = '$status',
              driver_name = '$driver_name',
              driver_phone = '$driver_phone'
              WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('✅ Vehicle updated successfully!'); window.location.href='vehicles.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . mysqli_error($conn) . "');</script>";
    }
}

// --- DELETE VEHICLE ---
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $query = "DELETE FROM vehicles WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('✅ Vehicle deleted successfully!'); window.location.href='vehicles.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . mysqli_error($conn) . "');</script>";
    }
}

// --- STATUS UPDATE ---
if (isset($_GET['change_status']) && isset($_GET['status'])) {
    $id = (int) $_GET['change_status'];
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $query = "UPDATE vehicles SET status = '$status' WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: vehicles.php");
    exit();
}

// ============================================================
// 2. FETCH VEHICLES
// ============================================================
$vehicles_query = "SELECT * FROM vehicles ORDER BY id DESC";
$vehicles_result = mysqli_query($conn, $vehicles_query);
$vehicle_count = mysqli_num_rows($vehicles_result);

// Get status counts for stats
$status_counts = [];
$status_q = "SELECT status, COUNT(*) as count FROM vehicles GROUP BY status";
$status_r = mysqli_query($conn, $status_q);
while ($row = mysqli_fetch_assoc($status_r)) {
    $status_counts[$row['status']] = $row['count'];
}

// Admin name
$admin_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 
              (isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin');
$display_admin = "Admin: " . $admin_name;

// Helper function for safe queries
function getValue($conn, $query) {
    $result = mysqli_query($conn, $query);
    if (!$result) return 0;
    $row = mysqli_fetch_array($result);
    return $row[0] ?? 0;
}

// Get some extra stats for dashboard-like KPI
$total_vehicles = $vehicle_count;
$available_vehicles = $status_counts['Available'] ?? 0;
$ontrip_vehicles = $status_counts['On Trip'] ?? 0;
$maintenance_vehicles = $status_counts['Maintenance'] ?? 0;
$inactive_vehicles = $status_counts['Inactive'] ?? 0;

// Get chart data: vehicles by status for pie chart
$status_chart_labels = [];
$status_chart_values = [];
foreach ($status_counts as $status => $count) {
    $status_chart_labels[] = $status;
    $status_chart_values[] = $count;
}

// Get vehicles by capacity for bar chart
$capacity_q = "SELECT capacity, COUNT(*) as count FROM vehicles GROUP BY capacity ORDER BY capacity ASC";
$capacity_result = mysqli_query($conn, $capacity_q);
$capacity_labels = [];
$capacity_values = [];
while ($row = mysqli_fetch_assoc($capacity_result)) {
    $capacity_labels[] = $row['capacity'] . ' Seats';
    $capacity_values[] = (int) $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Fleet Management | Admin</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT is DEFAULT, DARK toggle
           (SAME AS DASHBOARD)
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

        /* ============================================
           NAVBAR (SAME AS DASHBOARD)
           ============================================ */
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

        /* Vehicle Manager Button */
        .nav-links .vehicle-manager-btn {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff !important;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }
        .nav-links .vehicle-manager-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.4);
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            color: #fff !important;
        }
        .nav-links .vehicle-manager-btn::after {
            display: none !important;
        }
        .nav-links .vehicle-manager-btn i {
            color: #fff !important;
        }

        /* Clock */
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

        /* Admin Badge */
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

        /* Theme toggle */
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
        .theme-toggle-inline .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .theme-toggle-inline .toggle-slider {
            position: absolute;
            inset: 0;
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
            left: 3px;
            bottom: 3px;
            width: 20px;
            height: 20px;
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

        /* ============================================
           MAIN WRAPPER (SAME AS DASHBOARD)
           ============================================ */
        .main {
            padding: 24px 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Header (SAME AS DASHBOARD) */
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
        }
        .header-actions .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.3);
        }
        .btn-primary:hover { box-shadow: 0 8px 28px rgba(14,165,233,0.4); }
        .btn-success {
            background: var(--text-success);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16,185,129,0.3);
        }
        .btn-success:hover { box-shadow: 0 8px 28px rgba(16,185,129,0.4); }
        .btn-warning {
            background: var(--text-warning);
            color: #fff;
            box-shadow: 0 4px 16px rgba(245,158,11,0.3);
        }
        .btn-warning:hover { box-shadow: 0 8px 28px rgba(245,158,11,0.4); }

        /* Welcome Section (SAME AS DASHBOARD) */
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

        /* ============================================
           KPI GRID (SAME AS DASHBOARD - 8 CARDS)
           ============================================ */
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
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--gradient-main);
            opacity: 0.6;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            border-color: rgba(14, 165, 233, 0.20);
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
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 22px;
            opacity: 0.08;
            color: var(--text-accent);
            transition: all 0.4s ease;
        }
        .kpi-card:hover .kpi-icon {
            opacity: 0.15;
            transform: scale(1.1) rotate(5deg);
        }
        .kpi-card.green .kpi-icon { color: var(--text-success); }
        .kpi-card.orange .kpi-icon { color: #f59e0b; }
        .kpi-card.rose .kpi-icon { color: #f43f5e; }
        .kpi-card.blue .kpi-icon { color: var(--text-accent); }
        .kpi-card.purple .kpi-icon { color: #8b5cf6; }
        .kpi-card.teal .kpi-icon { color: #14b8a6; }

        .kpi-value.available { color: var(--text-success); }
        .kpi-value.ontrip { color: var(--text-warning); }
        .kpi-value.maintenance { color: var(--text-danger); }
        .kpi-value.inactive { color: #94a3b8; }

        /* ============================================
           CHARTS GRID (SAME AS DASHBOARD - 2 CHARTS)
           ============================================ */
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

        /* ============================================
           TABLE (SAME AS DASHBOARD STYLE)
           ============================================ */
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 18px;
            padding: 4px 0;
            overflow-x: auto;
            box-shadow: var(--shadow-card);
            backdrop-filter: blur(12px);
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
            background: rgba(0,0,0,0.02);
        }
        table.dataTable tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
            vertical-align: middle;
            transition: color var(--transition);
        }
        table.dataTable tbody tr:hover td {
            background: rgba(0,0,0,0.02);
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 10px;
            padding: 6px 14px;
            border: 1px solid var(--border-card);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 10px;
            padding: 4px 8px;
            border: 1px solid var(--border-card);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .dataTables_wrapper .dataTables_info {
            color: var(--text-muted);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-muted) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--text-accent) !important;
            color: #fff !important;
            border-radius: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .status-badge.Available { background: rgba(16,185,129,0.12); color: var(--text-success); }
        .status-badge.OnTrip { background: rgba(245,158,11,0.12); color: var(--text-warning); }
        .status-badge.Maintenance { background: rgba(239,68,68,0.12); color: var(--text-danger); }
        .status-badge.Inactive { background: rgba(148,163,184,0.12); color: #64748b; }

        .status-select {
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid var(--border-card);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        .status-select:focus {
            outline: none;
            border-color: var(--text-accent);
        }
        .status-select option { background: var(--bg-card); }

        .btn-icon {
            padding: 5px 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(14,165,233,0.08);
            color: var(--text-accent);
        }
        .btn-icon:hover {
            background: rgba(14,165,233,0.16);
            transform: translateY(-2px);
        }
        .btn-icon.danger {
            background: rgba(239,68,68,0.08);
            color: #f87171;
        }
        .btn-icon.danger:hover {
            background: rgba(239,68,68,0.16);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 48px;
            opacity: 0.2;
            display: block;
            margin-bottom: 12px;
        }

        /* ============================================
           MODALS (SAME AS DASHBOARD)
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-box {
            background: var(--bg-card);
            backdrop-filter: blur(40px);
            border-radius: 24px;
            padding: 36px 40px;
            max-width: 560px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            animation: modalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalIn {
            from { transform: scale(0.95) translateY(30px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-box .close-btn {
            float: right;
            font-size: 32px;
            cursor: pointer;
            color: var(--text-muted);
            background: none;
            border: none;
            transition: all 0.3s ease;
            line-height: 1;
        }
        .modal-box .close-btn:hover {
            color: var(--text-danger);
            transform: rotate(90deg);
        }
        .modal-box h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .modal-box .sub {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 20px;
        }
        .modal-box .modal-icon {
            font-size: 40px;
            display: block;
            margin-bottom: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border-input);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--bg-input);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--text-accent);
            box-shadow: 0 0 0 4px rgba(14,165,233,0.08);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
        }
        .btn-submit.primary {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.3);
        }
        .btn-submit.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(14,165,233,0.4);
        }
        .btn-submit.warning {
            background: var(--text-warning);
            color: #fff;
            box-shadow: 0 4px 16px rgba(245,158,11,0.3);
        }
        .btn-submit.warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(245,158,11,0.4);
        }

        /* Footer */
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

        /* Scroll to top */
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
        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }
        .scroll-top:hover {
            transform: translateY(-6px) scale(1.1);
            border-color: var(--text-accent);
            box-shadow: 0 15px 40px rgba(14,165,233,0.2);
        }

        /* Custom cursor */
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

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        /* Responsive */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 992px) {
            .charts-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .app-header {
                padding: 14px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .app-header .brand h1 { font-size: 20px; }
            .kpi-card .kpi-value { font-size: 22px; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .kpi-card { padding: 14px 16px; }
            .kpi-card .kpi-icon { font-size: 18px; top: 10px; right: 10px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .welcome-section .welcome-stats { width: 100%; justify-content: space-around; }
            .form-row { grid-template-columns: 1fr; }
            .modal-box { padding: 24px 20px; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .header-actions .btn { padding: 8px 16px; font-size: 13px; }
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .kpi-card .kpi-value { font-size: 18px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
        }

        /* Print overrides */
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
            .table-container { background: #fff !important; border-color: #d1d5db !important; border-radius: 8px !important; margin-bottom: 8px !important; }
            thead th { color: #374151 !important; background: #f1f5f9 !important; border-color: #d1d5db !important; font-size: 9px !important; padding: 6px 10px !important; }
            tbody td { color: #1e293b !important; border-color: #e5e7eb !important; font-size: 10px !important; padding: 6px 10px !important; }
            tbody tr:hover td { background: transparent !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .footer-bar { display: none !important; }
            .main { padding: 0 !important; }
            .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { display: none !important; }
            .welcome-section { padding: 12px 16px !important; }
            .welcome-section .welcome-text h2 { font-size: 16px !important; color: #111 !important; }
            .welcome-section .welcome-text p { font-size: 11px !important; color: #555 !important; }
            .welcome-section .welcome-stats { display: none !important; }
            .btn, .header-actions { display: none !important; }
            .modal-overlay { display: none !important; }
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
    <div class="loader-text">Loading Vehicles</div>
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
            <a href="vehicles.php" class="active"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
        </div>
        <!-- Live Clock -->
        <span class="navbar-clock" id="liveClock"><i class="fa-regular fa-clock"></i> Loading...</span>
        <div class="admin-badge">
            <i class="fa-solid fa-user-shield"></i>
            <?php echo htmlspecialchars($display_admin); ?>
        </div>
        <div class="theme-toggle-inline">
            <i class="fa-regular fa-sun toggle-icon" id="themeIcon"></i>
            <label class="toggle-switch">
                <input type="checkbox" id="themeToggle" />
                <span class="toggle-slider"></span>
            </label>
            <i class="fa-regular fa-moon toggle-icon"></i>
        </div>
        <!-- Logout -->
        <a href="../index.php" style="padding:8px 18px;border-radius:30px;font-size:14px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.2);transition:all 0.3s ease;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ============================================================ -->
<!-- MAIN WRAPPER -->
<!-- ============================================================ -->
<main class="main">

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-car"></i></div>
            <div>
                <h1 id="typing-heading">Vehicle Fleet Management</h1>
                <span>Manage all vehicles, drivers &amp; GPS tracking</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="vehicle_tracking.php" class="btn btn-success"><i class="fa-solid fa-map-location-dot"></i> Live Map</a>
            <a href="simulate_gps.php" class="btn btn-warning"><i class="fa-solid fa-satellite-dish"></i> GPS Simulator</a>
            <button class="btn btn-primary" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Vehicle</button>
        </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fa-regular fa-hand-peace"></i> Welcome Back, <?php echo htmlspecialchars($admin_name); ?></h2>
            <p><i class="fa-regular fa-circle-check"></i> Manage your vehicle fleet efficiently.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="num"><?php echo $total_vehicles; ?></div>
                <div class="label">Total Vehicles</div>
            </div>
            <div class="stat-item">
                <div class="num" style="color:var(--text-success);"><?php echo $available_vehicles; ?></div>
                <div class="label">Available</div>
            </div>
            <div class="stat-item">
                <div class="num" style="color:var(--text-warning);"><?php echo $ontrip_vehicles; ?></div>
                <div class="label">On Trip</div>
            </div>
        </div>
    </div>

    <!-- KPI Grid (8 Cards - same as dashboard) -->
    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fa-solid fa-car"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:var(--text-accent);"></i> Total Fleet</div>
            <div class="kpi-value"><?php echo $total_vehicles; ?></div>
            <div class="kpi-sub">All vehicles</div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fa-solid fa-check-circle"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:var(--text-success);"></i> Available</div>
            <div class="kpi-value available"><?php echo $available_vehicles; ?></div>
            <div class="kpi-sub">Ready to assign</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fa-solid fa-road"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-clock" style="color:#f59e0b;"></i> On Trip</div>
            <div class="kpi-value ontrip"><?php echo $ontrip_vehicles; ?></div>
            <div class="kpi-sub">Currently active</div>
        </div>
        <div class="kpi-card rose">
            <div class="kpi-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-screwdriver-wrench" style="color:#f43f5e;"></i> Maintenance</div>
            <div class="kpi-value maintenance"><?php echo $maintenance_vehicles; ?></div>
            <div class="kpi-sub">Under service</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="fa-solid fa-ban"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-xmark" style="color:#8b5cf6;"></i> Inactive</div>
            <div class="kpi-value inactive"><?php echo $inactive_vehicles; ?></div>
            <div class="kpi-sub">Decommissioned</div>
        </div>
        <div class="kpi-card teal">
            <div class="kpi-icon"><i class="fa-solid fa-user"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-user" style="color:#14b8a6;"></i> Drivers</div>
            <div class="kpi-value"><?php echo getValue($conn, "SELECT COUNT(*) FROM vehicles WHERE driver_name != '' AND driver_name IS NOT NULL"); ?></div>
            <div class="kpi-sub">Assigned drivers</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fa-solid fa-gauge-high"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-gauge" style="color:var(--text-accent);"></i> Avg Price/km</div>
            <div class="kpi-value">Rs. <?php echo number_format(getValue($conn, "SELECT COALESCE(AVG(price_per_km), 0) FROM vehicles"), 2); ?></div>
            <div class="kpi-sub">Per kilometer</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fa-solid fa-gas-pump"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-gas-pump" style="color:#f59e0b;"></i> Avg Petrol Rate</div>
            <div class="kpi-value">Rs. <?php echo number_format(getValue($conn, "SELECT COALESCE(AVG(petrol_rate), 0) FROM vehicles"), 2); ?></div>
            <div class="kpi-sub">Per liter</div>
        </div>
    </div>

    <!-- Charts Grid (2 Charts - same as dashboard) -->
    <div class="charts-grid">
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-pie"></i> Vehicle Status Distribution</h4>
                <span>Fleet overview</span>
            </div>
            <canvas id="statusChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Vehicles by Capacity</h4>
                <span>Seating distribution</span>
            </div>
            <canvas id="capacityChart"></canvas>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table id="vehiclesTable" class="display">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vehicle Name</th>
                    <th>Price/km (LKR)</th>
                    <th>Petrol Rate</th>
                    <th>Capacity</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>GPS Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vehicle_count > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($vehicles_result)): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['vehicle_name']); ?></td>
                            <td>Rs. <?php echo number_format($row['price_per_km'], 2); ?></td>
                            <td>Rs. <?php echo number_format($row['petrol_rate'], 2); ?></td>
                            <td><?php echo $row['capacity']; ?></td>
                            <td>
                                <?php if ($row['driver_name']): ?>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($row['driver_name']); ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($row['driver_phone']); ?></div>
                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:12px;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="GET" style="display:inline;">
                                    <input type="hidden" name="change_status" value="<?php echo $row['id']; ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="Available" <?php echo $row['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="On Trip" <?php echo $row['status'] == 'On Trip' ? 'selected' : ''; ?>>On Trip</option>
                                        <option value="Maintenance" <?php echo $row['status'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="Inactive" <?php echo $row['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?php if ($row['last_lat'] && $row['last_lng']): ?>
                                    <span style="font-size:11px;color:var(--text-success);">
                                        <i class="fa-solid fa-location-dot"></i> 
                                        <?php echo number_format($row['last_lat'], 6); ?>, <?php echo number_format($row['last_lng'], 6); ?>
                                    </span>
                                    <br>
                                    <span style="font-size:10px;color:var(--text-muted);">
                                        <?php echo $row['last_update'] ? date('Y-m-d H:i', strtotime($row['last_update'])) : 'N/A'; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:12px;">No GPS data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-icon" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <a href="?delete_id=<?php echo $row['id']; ?>" class="btn-icon danger" onclick="return confirm('Delete this vehicle permanently?')">
                                    <i class="fa-regular fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fa-regular fa-car"></i><p>No vehicles found. Click "Add Vehicle" to get started.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> Vehicle Management System &bull; All rights reserved.</span>
        <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?></span>
    </div>

</main>

<!-- Scroll to Top -->
<div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="close-btn" onclick="closeModal('addModal')">&times;</button>
        <span class="modal-icon"><i class="fa-solid fa-plus-circle" style="color:var(--text-accent);"></i></span>
        <h2>Add New Vehicle</h2>
        <p class="sub">Enter vehicle details below</p>
        <form method="POST">
            <div class="form-group">
                <label>Vehicle Name *</label>
                <input type="text" name="vehicle_name" placeholder="e.g. Toyota Axio" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price per km (LKR) *</label>
                    <input type="number" step="0.01" name="price_per_km" placeholder="120.00" required>
                </div>
                <div class="form-group">
                    <label>Petrol Rate (LKR) *</label>
                    <input type="number" step="0.01" name="petrol_rate" placeholder="45.00" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Capacity (Seats) *</label>
                    <input type="number" name="capacity" placeholder="4" required>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="Available">Available</option>
                        <option value="On Trip">On Trip</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" placeholder="e.g. Kamal Perera">
                </div>
                <div class="form-group">
                    <label>Driver Phone</label>
                    <input type="text" name="driver_phone" placeholder="077-1234567">
                </div>
            </div>
            <button type="submit" name="add_vehicle" class="btn-submit primary">
                <i class="fa-solid fa-plus"></i> Add Vehicle
            </button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
        <span class="modal-icon"><i class="fa-regular fa-pen-to-square" style="color:var(--text-warning);"></i></span>
        <h2>Edit Vehicle</h2>
        <p class="sub">Update vehicle details</p>
        <form method="POST" id="editForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Vehicle Name *</label>
                <input type="text" name="vehicle_name" id="edit_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price per km (LKR) *</label>
                    <input type="number" step="0.01" name="price_per_km" id="edit_price" required>
                </div>
                <div class="form-group">
                    <label>Petrol Rate (LKR) *</label>
                    <input type="number" step="0.01" name="petrol_rate" id="edit_petrol" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Capacity (Seats) *</label>
                    <input type="number" name="capacity" id="edit_capacity" required>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" required>
                        <option value="Available">Available</option>
                        <option value="On Trip">On Trip</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" id="edit_driver" placeholder="e.g. Kamal Perera">
                </div>
                <div class="form-group">
                    <label>Driver Phone</label>
                    <input type="text" name="driver_phone" id="edit_phone" placeholder="077-1234567">
                </div>
            </div>
            <button type="submit" name="edit_vehicle" class="btn-submit warning">
                <i class="fa-solid fa-save"></i> Update Vehicle
            </button>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== LOADER =====
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        setTimeout(() => { loader.classList.add("hidden"); }, 600);
        createParticles();
        initCharts();
    });

    // ===== VIDEO AUTOPLAY =====
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
    });

    // ===== LIVE CLOCK =====
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

    // ===== THEME TOGGLE =====
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

    // ===== TYPING EFFECT =====
    const heading = document.getElementById('typing-heading');
    const originalText = heading.innerText;
    heading.innerText = '';
    let charIndex = 0;
    function typeWriter() {
        if (charIndex < originalText.length) {
            heading.innerText += originalText.charAt(charIndex);
            charIndex++;
            setTimeout(typeWriter, 60);
        }
    }
    setTimeout(typeWriter, 800);

    // ===== CHARTS (Same as dashboard style) =====
    function initCharts() {
        // 1. Status Distribution (Pie Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusLabels = <?php echo json_encode($status_chart_labels); ?>;
        const statusValues = <?php echo json_encode($status_chart_values); ?>;
        const hasStatusData = statusValues.some(v => v > 0);
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: hasStatusData ? statusLabels : ['No Data'],
                datasets: [{
                    data: hasStatusData ? statusValues : [1],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
                    borderColor: '#0a0e1a',
                    borderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 10, usePointStyle: true, pointStyleWidth: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(0) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '60%',
            }
        });

        // 2. Capacity Distribution (Bar Chart)
        const capacityCtx = document.getElementById('capacityChart').getContext('2d');
        const capacityLabels = <?php echo json_encode($capacity_labels); ?>;
        const capacityValues = <?php echo json_encode($capacity_values); ?>;
        const hasCapData = capacityValues.some(v => v > 0);
        new Chart(capacityCtx, {
            type: 'bar',
            data: {
                labels: hasCapData ? capacityLabels : ['No Data'],
                datasets: [{
                    label: 'Vehicles',
                    data: hasCapData ? capacityValues : [0],
                    backgroundColor: 'rgba(139, 92, 246, 0.5)',
                    borderColor: 'rgba(139, 92, 246, 1)',
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
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.parsed.y + ' vehicles';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: { color: '#64748b', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 9 } }
                    }
                }
            }
        });
    }

    // ===== DATA TABLE =====
    $(document).ready(function() {
        $('#vehiclesTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copyHtml5', text: '<i class="fa-regular fa-copy"></i> Copy' },
                { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel' },
                { extend: 'csvHtml5', text: '<i class="fa-solid fa-file-csv"></i> CSV' },
                { extend: 'print', text: '<i class="fa-solid fa-print"></i> Print' }
            ],
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'No entries available',
                infoFiltered: '(filtered from _MAX_ total entries)'
            },
            order: [[0, 'desc']]
        });
    });

    // ===== MODALS =====
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.vehicle_name;
        document.getElementById('edit_price').value = data.price_per_km;
        document.getElementById('edit_petrol').value = data.petrol_rate;
        document.getElementById('edit_capacity').value = data.capacity;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_driver').value = data.driver_name || '';
        document.getElementById('edit_phone').value = data.driver_phone || '';
        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // ===== CUSTOM CURSOR =====
    const dot = document.getElementById('cursorDot');
    const ring = document.getElementById('cursorRing');
    if (dot && ring) {
        document.addEventListener('mousemove', (e) => {
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

    // ===== SCROLL TO TOP =====
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // ===== PARTICLES =====
    function createParticles() {
        const container = document.getElementById('particles');
        const count = 30;
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            const size = Math.random() * 5 + 2;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
            particle.style.animationDelay = (Math.random() * 20) + 's';
            particle.style.opacity = Math.random() * 0.15 + 0.05;
            container.appendChild(particle);
        }
    }

    // ===== ESC KEY TO CLOSE MODALS =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(el => {
                el.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
</script>

</body>
</html>