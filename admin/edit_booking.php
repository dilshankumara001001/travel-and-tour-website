<?php
include("../includes/db.php");
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location:../index.php"); 
    exit();
}

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
$display_admin = "Admin: " . $admin_name;

// ============================================================
// 1. GET BOOKING ID
// ============================================================
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($booking_id == 0) {
    header("Location: booking.php?error=invalid_id");
    exit();
}

// ============================================================
// 2. FETCH BOOKING DETAILS
// ============================================================
$query = "SELECT b.*, u.full_name AS user_name, p.title AS pkg_name 
          FROM bookings b
          INNER JOIN users u ON b.user_id = u.id 
          LEFT JOIN packages p ON b.package_id = p.id 
          WHERE b.id = $booking_id";
$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if(!$booking) {
    header("Location: booking.php?error=not_found");
    exit();
}

// ============================================================
// 3. HANDLE UPDATE
// ============================================================
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $pickup_location = mysqli_real_escape_string($conn, $_POST['pickup_location']);
    $destination_location = mysqli_real_escape_string($conn, $_POST['destination_location']);
    $total_cost = floatval($_POST['total_cost']);
    $vehicle_id = intval($_POST['vehicle_id']);
    $calculated_distance = floatval($_POST['calculated_distance']);
    
    $update_query = "UPDATE bookings SET 
                     status = '$status',
                     pickup_location = '$pickup_location',
                     destination_location = '$destination_location',
                     total_cost = '$total_cost',
                     vehicle_id = '$vehicle_id',
                     calculated_distance = '$calculated_distance'
                     WHERE id = $booking_id";
    
    if(mysqli_query($conn, $update_query)) {
        header("Location: booking.php?success=updated");
        exit();
    } else {
        $error = "Failed to update booking: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking • Advanced Map Editor</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw&libraries=places,geometry&callback=initMap" async defer></script>

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
            --admin-badge-bg: rgba(14, 165, 233, 0.12);
            --admin-badge-color: #0ea5e9;
            --map-dark-bg: #0a0e1a;
            --map-light-bg: #f5f5f5;
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
           NAVBAR
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
           MAIN CONTENT
           ============================================ */
        .main {
            padding: 24px 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Container */
        .container {
            background: var(--bg-card);
            backdrop-filter: blur(24px);
            border-radius: 32px;
            border: 1px solid var(--border-card);
            padding: 32px;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header h2 {
            font-size: 28px;
            font-weight: 800;
            background: var(--gradient-main);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header h2 i { -webkit-text-fill-color: var(--text-accent); }
        .header .sub {
            color: var(--text-muted);
            font-size: 14px;
            background: var(--bg-input);
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--border-card);
            transition: all var(--transition);
        }

        /* Error */
        .error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--text-danger);
            padding: 12px 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Info Bar */
        .info-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px 30px;
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 14px 24px;
            margin-bottom: 28px;
            font-size: 13px;
            color: var(--text-muted);
            transition: all var(--transition);
        }
        .info-bar strong { color: var(--text-primary); font-weight:600; }
        .info-bar i { margin-right:6px; color: var(--text-accent); }

        /* Map */
        .map-wrapper {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border-card);
            margin-bottom: 28px;
            height: 480px;
            transition: border-color var(--transition);
        }
        #map { width:100%; height:100%; }

        .map-loading {
            position: absolute;
            inset:0;
            background: var(--bg-card);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 14px;
            gap: 16px;
            backdrop-filter: blur(4px);
            transition: opacity 0.6s;
        }
        .map-loading.hidden { opacity:0; pointer-events:none; }
        .map-loading .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--border-card);
            border-top-color: var(--text-accent);
            border-radius:50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Route Info Card */
        .route-info-card {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 16px 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 24px 40px;
            color: var(--text-secondary);
            font-size: 13px;
            pointer-events: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 90%;
            transition: all var(--transition);
        }
        .route-info-card .stat {
            display: flex;
            flex-direction: column;
        }
        .route-info-card .stat .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .route-info-card .stat .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .route-info-card .stat .value.dist { color: var(--text-success); }
        .route-info-card .stat .value.dur { color: var(--text-accent); }
        .route-info-card .stat .value.cost { color: var(--text-warning); }
        .route-info-card .stat .value .unit {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 2px;
        }

        /* Map Controls */
        .map-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 10;
        }
        .map-controls button {
            background: var(--bg-card);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-card);
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .map-controls button:hover {
            background: var(--bg-input);
            border-color: var(--text-accent);
            transform: scale(1.05);
        }
        .map-controls button.active {
            background: rgba(14, 165, 233, 0.15);
            border-color: var(--text-accent);
            color: var(--text-accent);
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }
        .form-group { display:flex; flex-direction:column; gap:4px; }
        .form-group.full { grid-column:1/-1; }
        label {
            font-size:12px;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.6px;
            color: var(--text-muted);
            transition: color var(--transition);
        }
        input, select {
            padding:12px 16px;
            background: var(--bg-input);
            border:1px solid var(--border-input);
            border-radius:14px;
            color: var(--text-primary);
            font-size:14px;
            transition:all 0.3s;
            outline:none;
            width:100%;
            font-family: 'Inter', sans-serif;
        }
        input:focus, select:focus {
            border-color: var(--text-accent);
            box-shadow:0 0 0 3px rgba(14,165,233,0.15);
        }
        input::placeholder { color: var(--text-muted); }
        select option {
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .input-with-icon { position:relative; }
        .input-with-icon i {
            position:absolute;
            left:16px;
            top:50%;
            transform:translateY(-50%);
            color: var(--text-muted);
            font-size:14px;
        }
        .input-with-icon input { padding-left:44px; }

        /* Buttons */
        .btn-group {
            display:flex;
            gap:16px;
            margin-top:28px;
            flex-wrap:wrap;
        }
        .btn {
            padding:12px 32px;
            border:none;
            border-radius:16px;
            font-weight:600;
            font-size:14px;
            cursor:pointer;
            transition:all 0.3s ease;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap:8px;
            justify-content:center;
        }
        .btn-primary {
            background: var(--gradient-main);
            color:#fff;
            box-shadow:0 4px 16px rgba(14,165,233,0.3);
            flex:1;
        }
        .btn-primary:hover {
            transform:translateY(-2px);
            box-shadow:0 8px 28px rgba(14,165,233,0.4);
        }
        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-secondary);
            border:1px solid var(--border-card);
        }
        .btn-secondary:hover {
            background: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--text-accent);
        }

        /* Autocomplete override */
        .pac-container {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 14px;
            margin-top: 6px;
            box-shadow: var(--shadow-card);
        }
        .pac-item {
            color: var(--text-secondary);
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-card);
        }
        .pac-item:hover {
            background: var(--bg-input);
        }
        .pac-item-query {
            color: var(--text-accent);
        }

        /* Click message on map */
        .map-click-hint {
            position: absolute;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            padding: 8px 20px;
            border-radius: 30px;
            border: 1px solid var(--border-card);
            font-size: 12px;
            color: var(--text-muted);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            pointer-events: none;
            white-space: nowrap;
            transition: all var(--transition);
        }
        .map-click-hint i { color: var(--text-accent); margin-right: 6px; }

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
        @media (max-width: 992px) {
            .main { padding: 16px; }
            .container { padding: 24px; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .container { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .map-wrapper { height: 320px; }
            .header h2 { font-size: 22px; }
            .route-info-card {
                left: 10px;
                right: 10px;
                bottom: 10px;
                padding: 12px 16px;
                gap: 12px 20px;
                font-size: 12px;
            }
            .route-info-card .stat .value { font-size: 15px; }
            .map-controls { top: 10px; right: 10px; }
            .map-controls button { width: 34px; height: 34px; font-size: 14px; }
            .info-bar { padding: 12px 16px; gap: 10px 16px; font-size: 12px; }
            .btn-group { flex-direction: column; }
            .btn { padding: 12px 20px; }
            .map-click-hint { font-size: 10px; padding: 6px 14px; bottom: 80px; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px; }
            .container { padding: 16px; }
            .header h2 { font-size: 18px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
            .map-click-hint { display: none; }
        }

        /* Print overrides */
        @media print {
            body { background: #fff !important; color: #111 !important; padding: 10px 15px !important; font-size: 12px !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .map-controls, .route-info-card, .map-click-hint { display: none !important; }
            .container { background: #fff !important; border: 2px solid #d1d5db !important; box-shadow: none !important; padding: 20px !important; }
            .map-wrapper { height: 300px !important; border: 1px solid #d1d5db !important; }
            .header h2 { -webkit-text-fill-color: #111 !important; color: #111 !important; }
            .header .sub { color: #555 !important; background: #f8fafc !important; }
            .info-bar { background: #f8fafc !important; border-color: #d1d5db !important; }
            .info-bar strong { color: #111 !important; }
            .form-grid input, .form-grid select { background: #f8fafc !important; border-color: #d1d5db !important; color: #111 !important; }
            label { color: #374151 !important; }
            .btn { display: none !important; }
            .main { padding: 0 !important; }
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
    <div class="loader-text">Loading Editor</div>
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
            <a href="booking.php" class="active"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
        </div>
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
    </div>
    <a href="../index.php" class="btn btn-danger" style="padding:8px 18px; border-radius:30px; font-size:14px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.2); transition:all 0.3s ease;">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
</a>
</nav>

<!-- ============================================================ -->
<!-- MAIN WRAPPER -->
<!-- ============================================================ -->
<main class="main">

    <div class="container">

        <!-- Header -->
        <div class="header">
            <h2><i class="fas fa-map-marked-alt"></i> Edit Booking</h2>
            <span class="sub">#<?php echo $booking_id; ?> • <?php echo htmlspecialchars($booking['user_name']); ?></span>
        </div>

        <!-- Error -->
        <?php if(isset($error)): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Info Bar -->
        <div class="info-bar">
            <span><i class="fas fa-user"></i> <strong><?php echo htmlspecialchars($booking['user_name']); ?></strong> (UID: <?php echo $booking['user_id']; ?>)</span>
            <span><i class="fas fa-box"></i> <?php echo htmlspecialchars($booking['pkg_name'] ?? 'Custom Plan'); ?></span>
            <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></span>
            <span><i class="fas fa-tag"></i> Current Status: <strong style="color:var(--text-accent);"><?php echo ucfirst($booking['status']); ?></strong></span>
        </div>

        <!-- Map -->
        <div class="map-wrapper">
            <div id="map"></div>
            <div class="map-loading" id="mapLoading">
                <div class="spinner"></div>
                <span>Loading interactive map…</span>
            </div>

            <!-- Click Hint -->
            <div class="map-click-hint">
                <i class="fas fa-mouse-pointer"></i> Click on map to move marker
            </div>

            <!-- Route Info -->
            <div class="route-info-card" id="routeInfo">
                <div class="stat">
                    <span class="label">Distance</span>
                    <span class="value dist" id="distDisplay"><?php echo $booking['calculated_distance'] ?? '0'; ?> <span class="unit">km</span></span>
                </div>
                <div class="stat">
                    <span class="label">Duration</span>
                    <span class="value dur" id="durDisplay">—</span>
                </div>
                <div class="stat">
                    <span class="label">Cost (Rs.)</span>
                    <span class="value cost" id="costDisplay"><?php echo number_format($booking['total_cost'] ?? 0, 2); ?></span>
                </div>
            </div>

            <!-- Map Controls -->
            <div class="map-controls">
                <button id="trafficBtn" title="Toggle traffic layer"><i class="fas fa-car"></i></button>
                <button id="zoomRouteBtn" title="Zoom to route"><i class="fas fa-expand"></i></button>
                <button id="resetViewBtn" title="Reset view"><i class="fas fa-home"></i></button>
                <button id="styleToggleBtn" title="Toggle map style (Dark/Light)"><i class="fas fa-moon"></i></button>
            </div>
        </div>

        <!-- Form -->
        <form method="POST" id="editForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?php echo $booking['status']=='pending'?'selected':''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $booking['status']=='confirmed'?'selected':''; ?>>Confirmed</option>
                        <option value="approved" <?php echo $booking['status']=='approved'?'selected':''; ?>>Approved</option>
                        <option value="cancelled" <?php echo $booking['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $booking['status']=='completed'?'selected':''; ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vehicle ID</label>
                    <input type="number" name="vehicle_id" value="<?php echo $booking['vehicle_id']; ?>" placeholder="Enter vehicle ID">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-flag-checkered" style="color:var(--text-accent);"></i> Pickup Location</label>
                    <div class="input-with-icon">
                        <i class="fas fa-location-dot"></i>
                        <input type="text" id="pickup_input" name="pickup_location" 
                               value="<?php echo htmlspecialchars($booking['pickup_location'] ?? ''); ?>" 
                               placeholder="Search or drag marker">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-location-dot" style="color:var(--text-danger);"></i> Destination Location</label>
                    <div class="input-with-icon">
                        <i class="fas fa-location-dot"></i>
                        <input type="text" id="dest_input" name="destination_location" 
                               value="<?php echo htmlspecialchars($booking['destination_location'] ?? ''); ?>" 
                               placeholder="Search or drag marker">
                    </div>
                </div>
                <div class="form-group full">
                    <label>Hidden values (auto‑calculated)</label>
                    <input type="hidden" name="calculated_distance" id="hiddenDistance" value="<?php echo $booking['calculated_distance'] ?? 0; ?>">
                    <input type="hidden" name="total_cost" id="hiddenCost" value="<?php echo $booking['total_cost'] ?? 0; ?>">
                </div>
            </div>
            <div class="btn-group">
                <a href="booking.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Booking</button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> Intelligence Data Center &bull; All rights reserved.</span>
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
    // -------------------------------------------------------------
    // Loader
    // -------------------------------------------------------------
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        setTimeout(() => { loader.classList.add("hidden"); }, 600);
        createParticles();
    });

    // -------------------------------------------------------------
    // Video Autoplay
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Live Clock
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Theme Toggle
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Custom Cursor
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Scroll to Top
    // -------------------------------------------------------------
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // -------------------------------------------------------------
    // Floating Particles
    // -------------------------------------------------------------
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
    // GOOGLE MAPS - FULLY EDITABLE
    // ============================================================
    let map, geocoder, directionsService, directionsRenderer;
    let pickupMarker, destMarker, trafficLayer;
    let pickupLatLng, destLatLng;
    let infoWindowPickup, infoWindowDest;
    const defaultCenter = { lat: 7.8731, lng: 80.7718 };

    // Map styles
    const darkStyle = [
        { elementType: 'geometry', stylers: [{ color: '#242f3e' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#242f3e' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#746855' }] },
        { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#263c3f' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#746855' }] },
        { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#373d48' }] },
        { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: '#2d333d' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#17263c' }] }
    ];

    const lightStyle = [
        { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#ffffff' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#333333' }] },
        { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#555555' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#e5e5e5' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#d4d4d4' }] },
        { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#c8c8c8' }] },
        { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: '#e0e0e0' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9d9e9' }] }
    ];

    let currentMapStyle = 'dark';

    const pickupInput = document.getElementById('pickup_input');
    const destInput = document.getElementById('dest_input');
    const distDisplay = document.getElementById('distDisplay');
    const durDisplay = document.getElementById('durDisplay');
    const costDisplay = document.getElementById('costDisplay');
    const hiddenDistance = document.getElementById('hiddenDistance');
    const hiddenCost = document.getElementById('hiddenCost');

    const trafficBtn = document.getElementById('trafficBtn');
    const zoomRouteBtn = document.getElementById('zoomRouteBtn');
    const resetViewBtn = document.getElementById('resetViewBtn');
    const styleToggleBtn = document.getElementById('styleToggleBtn');

    // -------------------------------------------------------------
    // INIT MAP
    // -------------------------------------------------------------
    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: defaultCenter,
            zoom: 8,
            mapTypeId: 'roadmap',
            styles: darkStyle,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });

        geocoder = new google.maps.Geocoder();
        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: true,
            polylineOptions: { strokeColor: '#3b82f6', strokeWeight: 4, strokeOpacity: 0.7 }
        });

        trafficLayer = new google.maps.TrafficLayer();

        infoWindowPickup = new google.maps.InfoWindow({ content: 'Pickup' });
        infoWindowDest = new google.maps.InfoWindow({ content: 'Destination' });

        pickupMarker = new google.maps.Marker({
            map: map,
            draggable: true,
            label: { text: 'P', color: '#fff', fontSize: '12px', fontWeight: 'bold' },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: '#3b82f6',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
                scale: 10
            }
        });
        destMarker = new google.maps.Marker({
            map: map,
            draggable: true,
            label: { text: 'D', color: '#fff', fontSize: '12px', fontWeight: 'bold' },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: '#ef4444',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
                scale: 10
            }
        });

        // ============================================================
        // CLICK ON MAP TO SET LOCATION (NEW FEATURE)
        // ============================================================
        map.addListener('click', function(e) {
            const clickedLatLng = e.latLng;
            
            // Determine which marker is closer to click
            const pickupDist = pickupLatLng ? google.maps.geometry.spherical.computeDistanceBetween(clickedLatLng, pickupLatLng) : Infinity;
            const destDist = destLatLng ? google.maps.geometry.spherical.computeDistanceBetween(clickedLatLng, destLatLng) : Infinity;
            
            // If both markers exist, move the closer one; otherwise move the one that exists
            if (pickupLatLng && destLatLng) {
                if (pickupDist < destDist) {
                    movePickup(clickedLatLng);
                } else {
                    moveDest(clickedLatLng);
                }
            } else if (pickupLatLng) {
                movePickup(clickedLatLng);
            } else if (destLatLng) {
                moveDest(clickedLatLng);
            } else {
                // Default: move pickup
                movePickup(clickedLatLng);
            }
        });

        function movePickup(latLng) {
            pickupMarker.setPosition(latLng);
            pickupLatLng = latLng;
            reverseGeocode(latLng, pickupInput);
            updateRouteAndInfo();
        }

        function moveDest(latLng) {
            destMarker.setPosition(latLng);
            destLatLng = latLng;
            reverseGeocode(latLng, destInput);
            updateRouteAndInfo();
        }

        // Drag events
        pickupMarker.addListener('dragend', () => {
            const pos = pickupMarker.getPosition();
            pickupLatLng = pos;
            reverseGeocode(pos, pickupInput);
            updateRouteAndInfo();
        });
        destMarker.addListener('dragend', () => {
            const pos = destMarker.getPosition();
            destLatLng = pos;
            reverseGeocode(pos, destInput);
            updateRouteAndInfo();
        });

        // Click info windows
        pickupMarker.addListener('click', () => {
            infoWindowPickup.setContent('<b>Pickup</b><br>' + (pickupInput.value || 'Unknown'));
            infoWindowPickup.open(map, pickupMarker);
        });
        destMarker.addListener('click', () => {
            infoWindowDest.setContent('<b>Destination</b><br>' + (destInput.value || 'Unknown'));
            infoWindowDest.open(map, destMarker);
        });

        document.getElementById('mapLoading').classList.add('hidden');

        // Geocode existing addresses
        const pickupAddr = pickupInput.value.trim();
        const destAddr = destInput.value.trim();

        const setPickup = (latLng) => {
            if (latLng) {
                pickupMarker.setPosition(latLng);
                pickupLatLng = latLng;
                map.setCenter(latLng);
            }
        };
        const setDest = (latLng) => {
            if (latLng) {
                destMarker.setPosition(latLng);
                destLatLng = latLng;
            }
        };

        if (pickupAddr) {
            geocodeAddress(pickupAddr, (latLng) => {
                setPickup(latLng);
                if (destAddr) {
                    geocodeAddress(destAddr, (ll) => {
                        setDest(ll);
                        updateRouteAndInfo();
                    });
                } else {
                    updateRouteAndInfo();
                }
            });
        } else if (destAddr) {
            geocodeAddress(destAddr, (latLng) => {
                setDest(latLng);
                map.setCenter(latLng);
                updateRouteAndInfo();
            });
        } else {
            pickupMarker.setPosition(defaultCenter);
            destMarker.setPosition(defaultCenter);
            pickupLatLng = defaultCenter;
            destLatLng = defaultCenter;
            updateRouteAndInfo();
        }

        // Autocomplete
        const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput, {
            types: ['geocode', 'establishment'],
            componentRestrictions: { country: 'lk' }
        });
        const destAutocomplete = new google.maps.places.Autocomplete(destInput, {
            types: ['geocode', 'establishment'],
            componentRestrictions: { country: 'lk' }
        });

        pickupAutocomplete.addListener('place_changed', () => {
            const place = pickupAutocomplete.getPlace();
            if (place.geometry) {
                const loc = place.geometry.location;
                pickupMarker.setPosition(loc);
                pickupLatLng = loc;
                map.setCenter(loc);
                updateRouteAndInfo();
            }
        });
        destAutocomplete.addListener('place_changed', () => {
            const place = destAutocomplete.getPlace();
            if (place.geometry) {
                const loc = place.geometry.location;
                destMarker.setPosition(loc);
                destLatLng = loc;
                map.setCenter(loc);
                updateRouteAndInfo();
            }
        });

        // Blur geocode
        pickupInput.addEventListener('blur', () => {
            if (pickupInput.value.trim()) {
                geocodeAddress(pickupInput.value.trim(), (latLng) => {
                    if (latLng) {
                        pickupMarker.setPosition(latLng);
                        pickupLatLng = latLng;
                        updateRouteAndInfo();
                    }
                });
            }
        });
        destInput.addEventListener('blur', () => {
            if (destInput.value.trim()) {
                geocodeAddress(destInput.value.trim(), (latLng) => {
                    if (latLng) {
                        destMarker.setPosition(latLng);
                        destLatLng = latLng;
                        updateRouteAndInfo();
                    }
                });
            }
        });

        // Controls
        trafficBtn.addEventListener('click', () => {
            trafficLayer.setMap(trafficLayer.getMap() ? null : map);
            trafficBtn.classList.toggle('active');
        });

        zoomRouteBtn.addEventListener('click', () => {
            if (pickupLatLng && destLatLng) {
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(pickupLatLng);
                bounds.extend(destLatLng);
                map.fitBounds(bounds);
            }
        });

        resetViewBtn.addEventListener('click', () => {
            map.setCenter(defaultCenter);
            map.setZoom(8);
        });

        styleToggleBtn.addEventListener('click', () => {
            if (currentMapStyle === 'dark') {
                map.setOptions({ styles: lightStyle });
                currentMapStyle = 'light';
                styleToggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
                styleToggleBtn.title = 'Switch to dark mode';
            } else {
                map.setOptions({ styles: darkStyle });
                currentMapStyle = 'dark';
                styleToggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
                styleToggleBtn.title = 'Switch to light mode';
            }
        });

        setTimeout(updateRouteAndInfo, 500);
    }

    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    function geocodeAddress(address, callback) {
        geocoder.geocode({ address: address }, (results, status) => {
            if (status === 'OK' && results[0]) {
                callback(results[0].geometry.location);
            } else {
                callback(null);
            }
        });
    }

    function reverseGeocode(latLng, inputField) {
        geocoder.geocode({ location: latLng }, (results, status) => {
            if (status === 'OK' && results[0]) {
                inputField.value = results[0].formatted_address;
            } else {
                inputField.value = latLng.lat().toFixed(6) + ', ' + latLng.lng().toFixed(6);
            }
        });
    }

    function updateRouteAndInfo() {
        if (!pickupLatLng || !destLatLng) return;

        const request = {
            origin: pickupLatLng,
            destination: destLatLng,
            travelMode: google.maps.TravelMode.DRIVING
        };

        directionsService.route(request, (result, status) => {
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                const leg = result.routes[0].legs[0];
                const distanceKm = leg.distance.value / 1000;
                const durationText = leg.duration.text;
                distDisplay.innerHTML = distanceKm.toFixed(2) + ' <span class="unit">km</span>';
                durDisplay.textContent = durationText;
                const ratePerKm = 150;
                const cost = distanceKm * ratePerKm;
                costDisplay.textContent = cost.toFixed(2);
                hiddenDistance.value = distanceKm.toFixed(2);
                hiddenCost.value = cost.toFixed(2);
            } else {
                const dist = google.maps.geometry.spherical.computeDistanceBetween(pickupLatLng, destLatLng) / 1000;
                distDisplay.innerHTML = dist.toFixed(2) + ' <span class="unit">km (approx)</span>';
                durDisplay.textContent = '—';
                const ratePerKm = 150;
                const cost = dist * ratePerKm;
                costDisplay.textContent = cost.toFixed(2);
                hiddenDistance.value = dist.toFixed(2);
                hiddenCost.value = cost.toFixed(2);
                directionsRenderer.setDirections({ routes: [] });
            }
        });
    }

    // ============================================================
    // FALLBACK
    // ============================================================
    window.initMap = initMap;
    window.onerror = function() {
        document.getElementById('mapLoading').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--text-warning);font-size:24px;"></i><br>Map could not load. Please check API key.';
    };
</script>

</body>
</html>