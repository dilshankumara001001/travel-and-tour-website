<?php
session_start();
include("../includes/db.php");

// ============================================================
// 1. CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';

// ============================================================
// 2. GET BOOKING ID FROM URL
// ============================================================
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

// ============================================================
// 3. FETCH BOOKING - VERIFY IT BELONGS TO THIS USER
// ============================================================
$query = "SELECT b.*, p.title AS package_name 
          FROM bookings b 
          LEFT JOIN packages p ON b.package_id = p.id 
          WHERE b.id = $booking_id AND b.user_id = $user_id";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: my_bookings.php");
    exit();
}

$booking = mysqli_fetch_assoc($result);

// ============================================================
// 4. FETCH VEHICLES FOR DROPDOWN
// ============================================================
$vehicles_query = "SELECT id, vehicle_name, price_per_km FROM vehicles WHERE status = 'Available' OR id = " . (int)$booking['vehicle_id'];
$vehicles_result = mysqli_query($conn, $vehicles_query);
$vehicles = [];
while ($row = mysqli_fetch_assoc($vehicles_result)) {
    $vehicles[] = $row;
}

// ============================================================
// 5. HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $pickup = mysqli_real_escape_string($conn, $_POST['pickup_location']);
    $destination = mysqli_real_escape_string($conn, $_POST['destination_location']);
    $distance = (float)$_POST['calculated_distance'];
    $cost = (float)$_POST['total_cost'];
    $days = (int)$_POST['number_of_days'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $vehicle_id = (int)$_POST['vehicle_id'];

    $update_query = "UPDATE bookings SET 
                        pickup_location = '$pickup',
                        destination_location = '$destination',
                        calculated_distance = $distance,
                        total_cost = $cost,
                        number_of_days = $days,
                        status = '$status',
                        vehicle_id = $vehicle_id
                     WHERE id = $booking_id AND user_id = $user_id";

    if (mysqli_query($conn, $update_query)) {
        $success = true;
        // Refresh booking data
        $query = "SELECT b.*, p.title AS package_name 
                  FROM bookings b 
                  LEFT JOIN packages p ON b.package_id = p.id 
                  WHERE b.id = $booking_id AND b.user_id = $user_id";
        $result = mysqli_query($conn, $query);
        $booking = mysqli_fetch_assoc($result);
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
}

$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking #<?php echo $booking_id; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT/DARK
           ============================================================ */
        :root {
            --bg-body: #020617;
            --bg-navbar: rgba(0, 0, 0, 0.7);
            --bg-card: rgba(30, 41, 59, 0.6);
            --bg-input: rgba(15, 23, 42, 0.8);
            --border-card: rgba(51, 65, 85, 0.5);
            --border-input: rgba(51, 65, 85, 0.6);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-accent: #60a5fa;
            --text-success: #4ade80;
            --text-danger: #f87171;
            --text-warning: #fbbf24;
            --shadow-card: 0 25px 50px rgba(0,0,0,0.6);
            --toggle-bg: #475569;
            --toggle-dot: #f1f5f9;
            --scrollbar-track: #1e293b;
            --scrollbar-thumb: #60a5fa;
            --btn-primary-bg: #3b82f6;
            --btn-primary-hover: #2563eb;
            --btn-cancel-bg: rgba(255,255,255,0.08);
            --btn-cancel-hover: rgba(255,255,255,0.15);
            --blob-1: rgba(37, 99, 235, 0.08);
            --blob-2: rgba(16, 185, 129, 0.06);
            --blob-3: rgba(245, 158, 11, 0.04);
            --overlay: rgba(2, 6, 23, 0.6);
            --gradient-main: linear-gradient(135deg, #3b82f6, #10b981);
        }

        [data-theme="dark"] {
            --bg-body: #020617;
            --bg-navbar: rgba(0, 0, 0, 0.85);
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-input: rgba(0, 0, 0, 0.5);
            --border-card: rgba(51, 65, 85, 0.6);
            --border-input: rgba(51, 65, 85, 0.7);
            --shadow-card: 0 25px 50px rgba(0,0,0,0.8);
            --toggle-bg: #475569;
            --toggle-dot: #f1f5f9;
            --blob-1: rgba(59, 130, 246, 0.12);
            --blob-2: rgba(52, 211, 153, 0.08);
            --blob-3: rgba(251, 146, 60, 0.06);
            --overlay: rgba(2, 6, 23, 0.7);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--bg-body);
            overflow-x: hidden;
            transition: background 0.3s, color 0.3s;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.6;
            opacity: 0;
            animation: fadeInBody 0.8s ease forwards;
        }
        @keyframes fadeInBody { to { opacity: 1; } }

        .video-background {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -2;
            overflow: hidden;
            background: var(--bg-body);
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
            transition: background 0.3s;
        }

        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: -1;
            animation: blobFloat 16s ease-in-out infinite alternate;
            transition: background 0.3s;
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
            transition: background 0.3s;
        }
        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg) scale(0); opacity: 0; }
            10% { opacity: 0.15; }
            90% { opacity: 0.15; }
            100% { transform: translateY(-10vh) rotate(720deg) scale(1); opacity: 0; }
        }

        .loader {
            position: fixed; inset: 0;
            background: var(--bg-body);
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
            transition: color 0.3s;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulseText { 0%, 100% { opacity: 0.4; } 50% { opacity: 1; } }

        /* ===== NAVBAR ===== */
        .navbar {
            position: sticky; top: 0; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 48px;
            background: var(--bg-navbar);
            backdrop-filter: blur(28px) saturate(1.8);
            border-bottom: 1px solid var(--border-card);
            box-shadow: 0 4px 40px rgba(0,0,0,0.15);
            animation: slideDown 0.8s ease forwards;
            transition: background 0.3s, border-color 0.3s;
            flex-wrap: wrap;
            gap: 12px;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .logo {
            font-size: 26px;
            font-weight: 900;
            background: var(--gradient-main);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex; align-items: center; gap: 12px;
        }
        .logo i { -webkit-text-fill-color: initial; color: var(--text-accent); }
        .nav-group {
            display: flex; align-items: center; gap: 24px;
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
            transition: all 0.3s;
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
            background: rgba(59, 130, 246, 0.08);
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
            transition: all 0.3s;
            white-space: nowrap;
        }
        .admin-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.12);
            color: var(--text-accent);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid var(--border-card);
            transition: all 0.3s;
            white-space: nowrap;
        }
        .admin-badge i { font-size: 14px; }
        .admin-badge:hover { transform: scale(1.02); border-color: var(--text-accent); }

        .theme-toggle-inline {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 16px;
            border-left: 1px solid var(--border-card);
            transition: border-color 0.3s;
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
            transition: all 0.3s;
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
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
            transition: all 0.3s ease;
        }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.25); transform: translateY(-2px); }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main {
            padding: 24px 32px 40px;
            max-width: 1400px;
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
            animation: fadeUp 0.8s ease forwards;
            transition: all 0.3s;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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
            box-shadow: 0 8px 24px rgba(59,130,246,0.25);
            animation: pulseGlow 3s ease-in-out infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 8px 24px rgba(59,130,246,0.25); }
            50% { box-shadow: 0 8px 40px rgba(59,130,246,0.5); }
        }
        .app-header .brand h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            transition: color 0.3s;
        }
        .app-header .brand span {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            display: block;
            transition: color 0.3s;
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
        .btn-ghost {
            background: rgba(255,255,255,0.04);
            color: var(--text-secondary);
            border: 1px solid var(--border-card);
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }

        .split-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            animation: fadeUp 0.8s ease 0.2s both;
        }
        @media (max-width: 992px) {
            .split-layout { grid-template-columns: 1fr; }
        }

        /* Form Card */
        .edit-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 28px;
            padding: 28px 32px;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow-card);
            transition: all 0.3s;
        }
        .edit-card:hover {
            border-color: rgba(59, 130, 246, 0.15);
        }
        .edit-card h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edit-card h2 i { color: var(--text-accent); }
        .edit-card .sub {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .form-group label i {
            margin-right: 6px;
            color: var(--text-accent);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            outline: none;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--text-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-card);
        }
        .btn {
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-update {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(59,130,246,0.3);
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(59,130,246,0.4);
        }
        .btn-cancel {
            background: var(--btn-cancel-bg);
            color: var(--text-secondary);
        }
        .btn-cancel:hover {
            background: var(--btn-cancel-hover);
            color: var(--text-primary);
        }

        /* Map Container */
        .map-container {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            height: 100%;
            min-height: 500px;
            position: relative;
        }
        .map-container #map {
            width: 100%;
            height: 100%;
            min-height: 500px;
            background: #1a1a2e;
        }

        /* Map Controls */
        .map-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            flex-wrap: wrap;
            justify-content: center;
        }
        .map-controls button {
            padding: 6px 16px;
            border-radius: 30px;
            border: 1px solid var(--border-input);
            background: var(--bg-input);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }
        .map-controls button.active {
            background: var(--text-accent);
            color: #fff;
            border-color: var(--text-accent);
        }
        .map-controls button:hover:not(.active) {
            background: rgba(255,255,255,0.05);
        }

        /* Status indicators */
        .status-indicators {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            padding: 10px 14px;
            background: var(--bg-input);
            border-radius: 12px;
            border: 1px solid var(--border-card);
        }
        .status-indicators .indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .status-indicators .indicator .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-pickup { background: #3b82f6; }
        .dot-dest { background: #ef4444; }

        /* Messages */
        .msg-box {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .msg-box.success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--text-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .msg-box.error {
            background: rgba(239, 68, 68, 0.12);
            color: var(--text-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
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
            transition: all 0.3s;
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
            box-shadow: 0 15px 40px rgba(59,130,246,0.2);
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
            border: 2px solid rgba(59,130,246,0.3);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transition: width 0.3s, height 0.3s, border-color 0.3s, transform 0.15s ease;
            transform: translate(-50%, -50%);
            mix-blend-mode: difference;
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 12px; }
        ::-webkit-scrollbar-thumb:hover { background: #2563eb; }

        @media (max-width: 768px) {
            .main { padding: 16px; }
            .app-header {
                padding: 14px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .app-header .brand h1 { font-size: 20px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .edit-card { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .btn { justify-content: center; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .header-actions .btn { padding: 8px 16px; font-size: 13px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
            .map-container { min-height: 350px; }
            .map-container #map { min-height: 350px; }
            .map-controls { bottom: 12px; padding: 6px 12px; gap: 6px; }
            .map-controls button { font-size: 10px; padding: 4px 10px; }
        }
        @media (max-width: 480px) {
            .edit-card { padding: 14px; }
            .edit-card h2 { font-size: 18px; }
            .navbar-clock { font-size: 11px; padding: 4px 8px; }
            .map-container { min-height: 280px; }
            .map-container #map { min-height: 280px; }
        }

        @media print {
            body { background: #fff !important; color: #111 !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .footer-bar, .header-actions, .map-container { display: none !important; }
            .main { padding: 10px !important; }
            .edit-card { background: #f8fafc !important; border: 1px solid #d1d5db !important; box-shadow: none !important; }
            .form-actions .btn { display: none !important; }
        }
    </style>
</head>
<body>

<!-- ===== BACKGROUND ===== -->
<div class="video-background">
    <div class="fallback"></div>
    <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1920&q=80">
        <source src="videos/19329370-uhd_3840_2160_24fps.mp4" type="video/mp4">
        <source src="videos/background.webm" type="video/webm">
    </video>
</div>
<div class="video-overlay"></div>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>
<div class="particles" id="particles"></div>

<!-- Loader -->
<div class="loader" id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">Loading Booking</div>
</div>

<!-- Custom Cursor -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- ===== NAVBAR ===== -->
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
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff !important;padding:8px 20px;border-radius:30px;font-weight:700;transition:all 0.3s ease;box-shadow:0 4px 15px rgba(139,92,246,0.3);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
        </div>
        <span class="navbar-clock" id="liveClock"><i class="fa-regular fa-clock"></i> Loading...</span>
        <div class="admin-badge">
            <i class="fa-solid fa-user-shield"></i>
            User: <?php echo htmlspecialchars($admin_name); ?>
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

<!-- ===== MAIN WRAPPER ===== -->
<main class="main">

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-regular fa-pen-to-square"></i></div>
            <div>
                <h1 id="typing-heading">Edit Booking</h1>
                <span>Update your travel details &bull; #<?php echo $booking_id; ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="my_bookings.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> My Bookings</a>
        </div>
    </header>

    <!-- Split Layout -->
    <div class="split-layout">

        <!-- LEFT: FORM -->
        <div class="edit-card">
            <h2><i class="fa-regular fa-pen-to-square"></i> Edit Booking #<?php echo $booking_id; ?></h2>
            <p class="sub">Update your travel details below.</p>

            <!-- Status Indicators -->
            <div class="status-indicators">
                <span class="indicator"><span class="dot dot-pickup"></span> <span id="pickupStatus">Pickup: Not set</span></span>
                <span class="indicator"><span class="dot dot-dest"></span> <span id="destStatus">Destination: Not set</span></span>
            </div>

            <?php if ($success): ?>
                <div class="msg-box success">
                    <i class="fa-solid fa-circle-check"></i> Booking updated successfully! Redirecting...
                    <script>
                        setTimeout(function() {
                            window.location.href = 'my_bookings.php?success=1';
                        }, 1500);
                    </script>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="msg-box error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="editForm">
                <div class="form-group">
                    <label><i class="fa-regular fa-box"></i> Package (Admin-created, read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($booking['package_name'] ?? 'Custom Tour'); ?>" disabled>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-location-dot"></i> Pickup Location</label>
                        <input type="text" name="pickup_location" id="pickupInput" value="<?php echo htmlspecialchars($booking['pickup_location'] ?? ''); ?>" placeholder="Click on map to set">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-flag"></i> Destination Location</label>
                        <input type="text" name="destination_location" id="destInput" value="<?php echo htmlspecialchars($booking['destination_location'] ?? ''); ?>" placeholder="Click on map to set">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-ruler"></i> Distance (km) <span style="font-weight:400;color:var(--text-muted);font-size:12px;">(auto-calculated)</span></label>
                        <input type="number" step="0.01" name="calculated_distance" id="distanceInput" value="<?php echo $booking['calculated_distance'] ?? '0.00'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-clock"></i> Number of Days</label>
                        <input type="number" name="number_of_days" value="<?php echo $booking['number_of_days'] ?? 1; ?>" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-coins"></i> Total Cost (LKR)</label>
                        <input type="number" step="0.01" name="total_cost" value="<?php echo $booking['total_cost'] ?? '0.00'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-circle"></i> Status</label>
                        <select name="status" required>
                            <option value="pending" <?php echo ($booking['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($booking['status'] ?? '') == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Confirmed" <?php echo ($booking['status'] ?? '') == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="Completed" <?php echo ($booking['status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($booking['status'] ?? '') == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-car"></i> Vehicle (optional)</label>
                    <select name="vehicle_id">
                        <option value="0">None / Not assigned</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($booking['vehicle_id'] == $v['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vehicle_name']); ?> 
                                (Rs. <?php echo number_format($v['price_per_km'] ?? 0, 2); ?>/km)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($booking['booking_date']) && $booking['booking_date'] != '0000-00-00 00:00:00'): ?>
                <div class="form-group" style="opacity:0.7;">
                    <label><i class="fa-regular fa-calendar"></i> Booking Date</label>
                    <input type="text" value="<?php echo date('F j, Y g:i A', strtotime($booking['booking_date'])); ?>" disabled>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <a href="my_bookings.php" class="btn btn-cancel"><i class="fa-regular fa-xmark"></i> Cancel</a>
                    <button type="submit" name="update_booking" class="btn btn-update">
                        <i class="fa-regular fa-floppy-disk"></i> Update Booking
                    </button>
                </div>
            </form>
        </div>

        <!-- RIGHT: MAP -->
        <div class="map-container">
            <div id="map"></div>
            <div class="map-controls">
                <button id="pickupBtn" class="active"><i class="fa-solid fa-location-dot"></i> Pickup</button>
                <button id="destBtn"><i class="fa-solid fa-flag"></i> Destination</button>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?></span>
    </div>

</main>

<!-- Scroll to Top -->
<div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ============================================================
    //  BASIC SETUP (LOADER, CLOCK, THEME, TYPING, CURSOR, SCROLL, PARTICLES)
    // ============================================================
    window.addEventListener("load", function() {
        const loader = document.getElementById("loader");
        if (loader) {
            setTimeout(function() {
                loader.classList.add("hidden");
            }, 400);
        }
        createParticles();
    });

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
        // Initialize map after a short delay
        setTimeout(initMap, 400);
        updateStatusIndicators();
    });

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

    const dot = document.getElementById('cursorDot');
    const ring = document.getElementById('cursorRing');
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

    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 400) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

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

    // ============================================================
    //  UPDATE STATUS INDICATORS
    // ============================================================
    function updateStatusIndicators() {
        const pickupVal = document.getElementById('pickupInput').value;
        const destVal = document.getElementById('destInput').value;
        document.getElementById('pickupStatus').textContent = 'Pickup: ' + (pickupVal ? (pickupVal.length > 30 ? pickupVal.substring(0, 30) + '...' : pickupVal) : 'Not set');
        document.getElementById('destStatus').textContent = 'Destination: ' + (destVal ? (destVal.length > 30 ? destVal.substring(0, 30) + '...' : destVal) : 'Not set');
    }

    // ============================================================
    //  MAP – LEAFLET (Simple mode selector)
    // ============================================================
    let map;
    let pickupMarker, destMarker;
    let currentMode = 'pickup';

    const pickupIcon = L.divIcon({
        html: '<div style="background:#3b82f6;color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-weight:bold;border:2px solid white;box-shadow:0 4px 8px rgba(0,0,0,0.3);">P</div>',
        className: '',
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    });

    const destIcon = L.divIcon({
        html: '<div style="background:#ef4444;color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-weight:bold;border:2px solid white;box-shadow:0 4px 8px rgba(0,0,0,0.3);">D</div>',
        className: '',
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    });

    function parseCoords(str) {
        if (!str) return null;
        let match = str.match(/lat:\s*([\d.-]+)/i);
        let lngMatch = str.match(/lng:\s*([\d.-]+)/i);
        if (match && lngMatch) {
            const lat = parseFloat(match[1]);
            const lng = parseFloat(lngMatch[1]);
            if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
        }
        let parts = str.split(',').map(s => s.trim());
        if (parts.length >= 2) {
            const lat = parseFloat(parts[0]);
            const lng = parseFloat(parts[1]);
            if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
        }
        return null;
    }

    function calculateDistance() {
        const pickupVal = document.getElementById('pickupInput').value;
        const destVal = document.getElementById('destInput').value;
        const p = parseCoords(pickupVal);
        const d = parseCoords(destVal);
        if (p && d) {
            const R = 6371;
            const dLat = (d.lat - p.lat) * Math.PI / 180;
            const dLng = (d.lng - p.lng) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(p.lat * Math.PI / 180) * Math.cos(d.lat * Math.PI / 180) *
                      Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const dist = R * c;
            document.getElementById('distanceInput').value = dist.toFixed(2);
        }
        updateStatusIndicators();
    }

    function reverseGeocode(latlng, type) {
        const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + latlng.lat + '&lon=' + latlng.lng + '&zoom=18&addressdetails=1';
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    const inputId = (type === 'pickup') ? 'pickupInput' : 'destInput';
                    document.getElementById(inputId).value = data.display_name;
                    calculateDistance();
                    updateStatusIndicators();
                }
            })
            .catch(err => console.log('Reverse geocode failed:', err));
    }

    function initMap() {
        const defaultCenter = [7.8731, 80.7718];
        map = L.map('map').setView(defaultCenter, 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Load existing data
        const pickupVal = document.getElementById('pickupInput').value;
        const destVal = document.getElementById('destInput').value;

        if (pickupVal) {
            const coords = parseCoords(pickupVal);
            if (coords) {
                pickupMarker = L.marker([coords.lat, coords.lng], { icon: pickupIcon, draggable: true })
                    .addTo(map)
                    .bindPopup('Pickup Location');
                pickupMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    const lat = pos.lat, lng = pos.lng;
                    document.getElementById('pickupInput').value = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
                    calculateDistance();
                    reverseGeocode(pos, 'pickup');
                    updateStatusIndicators();
                });
            }
        }
        if (destVal) {
            const coords = parseCoords(destVal);
            if (coords) {
                destMarker = L.marker([coords.lat, coords.lng], { icon: destIcon, draggable: true })
                    .addTo(map)
                    .bindPopup('Destination');
                destMarker.on('dragend', function(e) {
                    const pos = e.target.getLatLng();
                    const lat = pos.lat, lng = pos.lng;
                    document.getElementById('destInput').value = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
                    calculateDistance();
                    reverseGeocode(pos, 'destination');
                    updateStatusIndicators();
                });
            }
        }

        // Fit bounds if both markers exist
        if (pickupMarker && destMarker) {
            const bounds = L.latLngBounds([pickupMarker.getLatLng(), destMarker.getLatLng()]);
            map.fitBounds(bounds, { padding: [50, 50] });
        }

        // Click on map to add/set marker
        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            const pos = { lat, lng };

            if (currentMode === 'pickup') {
                if (pickupMarker) {
                    pickupMarker.setLatLng(pos);
                } else {
                    pickupMarker = L.marker(pos, { icon: pickupIcon, draggable: true })
                        .addTo(map)
                        .bindPopup('Pickup Location');
                    pickupMarker.on('dragend', function(ev) {
                        const newPos = ev.target.getLatLng();
                        const lat2 = newPos.lat, lng2 = newPos.lng;
                        document.getElementById('pickupInput').value = 'Lat: ' + lat2.toFixed(6) + ', Lng: ' + lng2.toFixed(6);
                        calculateDistance();
                        reverseGeocode(newPos, 'pickup');
                        updateStatusIndicators();
                    });
                }
                document.getElementById('pickupInput').value = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
                reverseGeocode(e.latlng, 'pickup');
            } else {
                if (destMarker) {
                    destMarker.setLatLng(pos);
                } else {
                    destMarker = L.marker(pos, { icon: destIcon, draggable: true })
                        .addTo(map)
                        .bindPopup('Destination');
                    destMarker.on('dragend', function(ev) {
                        const newPos = ev.target.getLatLng();
                        const lat2 = newPos.lat, lng2 = newPos.lng;
                        document.getElementById('destInput').value = 'Lat: ' + lat2.toFixed(6) + ', Lng: ' + lng2.toFixed(6);
                        calculateDistance();
                        reverseGeocode(newPos, 'destination');
                        updateStatusIndicators();
                    });
                }
                document.getElementById('destInput').value = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
                reverseGeocode(e.latlng, 'destination');
            }
            calculateDistance();
            updateStatusIndicators();
        });

        // Mode toggle
        document.getElementById('pickupBtn').addEventListener('click', function() {
            currentMode = 'pickup';
            this.classList.add('active');
            document.getElementById('destBtn').classList.remove('active');
        });
        document.getElementById('destBtn').addEventListener('click', function() {
            currentMode = 'dest';
            this.classList.add('active');
            document.getElementById('pickupBtn').classList.remove('active');
        });

        // Initial updates
        calculateDistance();
        updateStatusIndicators();
    }

    // Listen for manual input changes
    document.getElementById('pickupInput').addEventListener('input', function() {
        calculateDistance();
        updateStatusIndicators();
    });
    document.getElementById('destInput').addEventListener('input', function() {
        calculateDistance();
        updateStatusIndicators();
    });
</script>

</body>
</html>