<?php
/**
 * 👤 USER MANAGEMENT – FULL DETAILS ADD USER
 */
session_start();
include("../includes/db.php");

if (!$conn) {
    die("<div style='color:white; text-align:center; padding:20px; background:rgba(255,0,0,0.2);'>Connection Failed.</div>");
}

$current_page = basename($_SERVER['PHP_SELF']);

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ============================================================
// ADD MISSING COLUMNS TO users TABLE (phone, address)
// ============================================================
$check_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($check_phone->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
}
$check_address = $conn->query("SHOW COLUMNS FROM users LIKE 'address'");
if ($check_address->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN address TEXT NULL AFTER phone");
}
// Ensure role column exists (it should already)
$check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER password");
}

// ============================================================
// ADD USER (UPDATED WITH FULL DETAILS)
// ============================================================
if (isset($_POST['add_user'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $role = mysqli_real_escape_string($conn, $_POST['role'] ?? 'user');
    $default_password = password_hash("User123*", PASSWORD_DEFAULT);
    
    $insert_query = "INSERT INTO users (full_name, email, phone, address, role, password) 
                     VALUES ('$full_name', '$email', '$phone', '$address', '$role', '$default_password')";
    if (mysqli_query($conn, $insert_query)) {
        header("Location: " . $current_page);
        exit();
    }
}

// DELETE USER
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM users WHERE id = '$delete_id'");
    header("Location: " . $current_page);
    exit();
}

// CLEAR ALL
if (isset($_GET['clear_all']) && $_GET['clear_all'] == 'true') {
    mysqli_query($conn, "TRUNCATE TABLE users");
    header("Location: " . $current_page);
    exit();
}

// ============================================================
// FETCH USERS
// ============================================================
$users = [];
$user_query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($user_query)) {
    $users[] = $row;
}

// ============================================================
// FETCH BOOKINGS BY USER
// ============================================================
$bookings_by_user = [];
$booking_query = "SELECT * FROM bookings ORDER BY booking_date DESC";
$result = mysqli_query($conn, $booking_query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $user_id = $row['user_id'];
        if (!isset($bookings_by_user[$user_id])) {
            $bookings_by_user[$user_id] = [];
        }
        $bookings_by_user[$user_id][] = $row;
    }
}

// ============================================================
// STATS
// ============================================================
$total_users = count($users);
$users_with_bookings = 0;
$total_bookings = 0;
$total_revenue = 0;
$today_bookings = 0;
$pending_bookings = 0;
$approved_bookings = 0;

foreach ($bookings_by_user as $uid => $bks) {
    if (count($bks) > 0) $users_with_bookings++;
    $total_bookings += count($bks);
    foreach ($bks as $b) {
        $total_revenue += $b['total_cost'] ?? 0;
        if (isset($b['status'])) {
            $status = strtolower($b['status']);
            if ($status == 'pending') $pending_bookings++;
            if (in_array($status, ['approved', 'confirmed', 'completed'])) $approved_bookings++;
        }
        if (isset($b['booking_date']) && substr($b['booking_date'], 0, 10) == date('Y-m-d')) {
            $today_bookings++;
        }
    }
}
$avg_cost = ($total_bookings > 0) ? $total_revenue / $total_bookings : 0;

// ============================================================
// CHART DATA
// ============================================================
$trend_query = "SELECT DATE_FORMAT(booking_date, '%b') as month, COALESCE(SUM(total_cost), 0) as rev 
                FROM bookings 
                WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                  AND status IN ('Approved','approved','Confirmed','confirmed','Completed','completed') 
                GROUP BY YEAR(booking_date), MONTH(booking_date) 
                ORDER BY MIN(booking_date) ASC";
$trend_res = mysqli_query($conn, $trend_query);
$trend_labels = [];
$trend_values = [];
while ($row = mysqli_fetch_assoc($trend_res)) {
    $trend_labels[] = $row['month'];
    $trend_values[] = (float) $row['rev'];
}

$status_query = "SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status";
$status_res = mysqli_query($conn, $status_query);
$status_labels = [];
$status_values = [];
while ($row = mysqli_fetch_assoc($status_res)) {
    $status_labels[] = $row['status'];
    $status_values[] = (int) $row['cnt'];
}

$pop_query = "SELECT p.title, COUNT(b.id) as cnt 
              FROM packages p 
              LEFT JOIN bookings b ON p.id = b.package_id 
              WHERE b.status IN ('Approved','approved','Confirmed','confirmed','Completed','completed') 
              GROUP BY p.id 
              ORDER BY cnt DESC 
              LIMIT 5";
$pop_res = mysqli_query($conn, $pop_query);
$pop_labels = [];
$pop_values = [];
while ($row = mysqli_fetch_assoc($pop_res)) {
    $pop_labels[] = $row['title'];
    $pop_values[] = (int) $row['cnt'];
}

// Admin name
$admin_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 
              (isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin');
$display_admin = "Admin: " . $admin_name;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin Dashboard</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT/DARK (SAME AS DASHBOARD)
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

        /* Logout Button */
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
        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: translateY(-2px);
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

        /* Header */
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

        /* Welcome Section */
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
           KPI GRID (8 CARDS)
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
        .kpi-card.purple .kpi-icon { color: #8b5cf6; }
        .kpi-card.orange .kpi-icon { color: #f59e0b; }
        .kpi-card.rose .kpi-icon { color: #f43f5e; }
        .kpi-card.teal .kpi-icon { color: #14b8a6; }
        .kpi-card.indigo .kpi-icon { color: #6366f1; }
        .kpi-card.blue .kpi-icon { color: var(--text-accent); }

        /* ============================================
           CHARTS GRID (3 CHARTS)
           ============================================ */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
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
           USER TABLE
           ============================================ */
        .table-section {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 20px 24px;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease forwards;
            box-shadow: var(--shadow-card);
            margin-bottom: 28px;
        }
        .table-section:hover {
            border-color: rgba(14,165,233,0.15);
        }
        .table-section .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-section .table-header h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color var(--transition);
        }
        .table-section .table-header h4 i {
            color: var(--text-accent);
        }
        .table-section .table-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--text-accent);
            color: #fff;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14,165,233,0.3);
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--text-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }
        .btn-info {
            background: rgba(14, 165, 233, 0.1);
            color: var(--text-accent);
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        .btn-info:hover {
            background: var(--text-accent);
            color: white;
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .user-table thead th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
        }
        .user-table tbody td {
            padding: 12px 12px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
            transition: color var(--transition);
        }
        .user-table tbody tr:hover td {
            background: var(--bg-input);
        }
        .user-id {
            color: var(--text-accent);
            font-weight: 700;
        }
        .user-email {
            color: var(--text-muted);
        }

        /* ============================================
           MODALS
           ============================================ */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        .modal-close {
            float: right;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 28px;
            cursor: pointer;
            transition: 0.3s;
        }
        .modal-close:hover {
            color: var(--text-primary);
        }
        .modal-table {
            width: 100%;
            border-collapse: collapse;
        }
        .modal-table th {
            background: var(--bg-secondary);
            padding: 12px;
            text-align: left;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
        }
        .modal-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
        }
        .badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-approved { background: #10b981; color: #fff; }
        .badge-confirmed { background: #0ea5e9; color: #fff; }
        .badge-cancelled { background: #ef4444; color: #fff; }
        .badge-pending { background: #f59e0b; color: #fff; }
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        /* UPDATED: Full Details Form */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-secondary);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-input);
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color var(--transition);
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--text-accent);
        }
        .form-group select {
            appearance: auto;
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

        /* Cursor */
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
            .charts-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 992px) {
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
            .charts-grid { grid-template-columns: 1fr 1fr; }
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
            .kpi-card .kpi-icon { font-size: 20px; top: 12px; right: 12px; }
            .charts-grid { grid-template-columns: 1fr; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .welcome-section .welcome-stats { width: 100%; justify-content: space-around; }
            .table-section .table-header { flex-direction: column; align-items: stretch; }
            .modal-content { max-width: 95%; padding: 20px; }
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .kpi-card .kpi-value { font-size: 20px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- BACKGROUND VIDEO, BLOBS, PARTICLES -->
<!-- ============================================ -->
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
    <div class="loader-text">Loading Users</div>
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
            <a href="users.php" class="active"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
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
        <a href="../index.php" class="btn-logout">
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
            <div class="logo-icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <h1 id="typing-heading">User Management</h1>
                <span>Control Center &bull; v4.0</span>
            </div>
        </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fa-regular fa-hand-peace"></i> Welcome Back, <?php echo htmlspecialchars($display_admin); ?></h2>
            <p><i class="fa-regular fa-circle-check"></i> Your user directory is up to date and secured.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="num" data-count="<?php echo $total_users; ?>">0</div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-item">
                <div class="num" data-count="<?php echo $users_with_bookings; ?>">0</div>
                <div class="label">Travellers</div>
            </div>
            <div class="stat-item">
                <div class="num" data-count="<?php echo $total_bookings; ?>">0</div>
                <div class="label">Bookings</div>
            </div>
        </div>
    </div>

    <!-- KPI Grid (8 Cards) -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-user" style="color:var(--text-accent);"></i> Total Users</div>
            <div class="kpi-value" data-count="<?php echo $total_users; ?>">0</div>
            <div class="kpi-sub">Registered accounts</div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fa-solid fa-user-check"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:var(--text-success);"></i> Travellers</div>
            <div class="kpi-value" data-count="<?php echo $users_with_bookings; ?>">0</div>
            <div class="kpi-sub">Users with bookings</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fa-solid fa-ticket"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-calendar" style="color:var(--text-accent);"></i> Total Bookings</div>
            <div class="kpi-value" data-count="<?php echo $total_bookings; ?>">0</div>
            <div class="kpi-sub">All time bookings</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-clock" style="color:#f59e0b;"></i> Today</div>
            <div class="kpi-value" data-count="<?php echo $today_bookings; ?>">0</div>
            <div class="kpi-sub">Bookings today</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-clock" style="color:#8b5cf6;"></i> Pending</div>
            <div class="kpi-value" data-count="<?php echo $pending_bookings; ?>">0</div>
            <div class="kpi-sub">Awaiting approval</div>
        </div>
        <div class="kpi-card teal">
            <div class="kpi-icon"><i class="fa-solid fa-check-circle"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-check-circle" style="color:#14b8a6;"></i> Approved</div>
            <div class="kpi-value" data-count="<?php echo $approved_bookings; ?>">0</div>
            <div class="kpi-sub">Confirmed bookings</div>
        </div>
        <div class="kpi-card rose">
            <div class="kpi-icon"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-circle-check" style="color:#f43f5e;"></i> Revenue</div>
            <div class="kpi-value" data-count="<?php echo $total_revenue; ?>" data-currency="true">Rs. 0</div>
            <div class="kpi-sub">Total earnings</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="kpi-label"><i class="fa-regular fa-chart-simple" style="color:#6366f1;"></i> Avg. Cost</div>
            <div class="kpi-value" data-count="<?php echo $avg_cost; ?>" data-currency="true">Rs. 0</div>
            <div class="kpi-sub">Average per booking</div>
        </div>
    </div>

    <!-- Charts Grid (3 Charts) -->
    <div class="charts-grid">
        <div class="chart-box">
            <div class="chart-header">
                <h4><i class="fa-regular fa-chart-bar"></i> Revenue Trend</h4>
                <span>Last 6 months</span>
            </div>
            <canvas id="trendChart"></canvas>
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
                <h4><i class="fa-regular fa-chart-bar"></i> Popular Packages</h4>
                <span>Top 5</span>
            </div>
            <canvas id="popularChart"></canvas>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- USER TABLE - UPDATED ADD USER MODAL WITH FULL DETAILS -->
    <!-- ============================================================ -->
    <div class="table-section">
        <div class="table-header">
            <h4><i class="fa-regular fa-users"></i> Registered Users</h4>
            <div class="table-actions">
                <button onclick="toggleModal(true)" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add User</button>
                <a href="?clear_all=true" onclick="return confirm('WARNING: This will delete ALL users! Continue?');" class="btn btn-danger"><i class="fa-solid fa-trash-alt"></i> Clear All</a>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>System ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th style="text-align:center;">Bookings</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($users as $user) {
                        $user_id = $user['id'];
                        $booking_count = isset($bookings_by_user[$user_id]) ? count($bookings_by_user[$user_id]) : 0;
                        echo "<tr>
                                <td class='user-id'>#USR-{$user['id']}</td>
                                <td>" . htmlspecialchars($user['full_name']) . "</td>
                                <td class='user-email'>" . htmlspecialchars($user['email']) . "</td>
                                <td style='text-align:center;'>
                                    <button class='btn btn-info btn-sm' onclick='showBookings({$user_id})'>
                                        <i class='fa-regular fa-eye'></i> View ({$booking_count})
                                    </button>
                                </td>
                                <td style='text-align:right;'>
                                    <a href='?delete_id={$user['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this user?\")'>
                                        <i class='fa-solid fa-times'></i> Delete
                                    </a>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
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
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Add User Modal - UPDATED WITH FULL DETAILS -->
<div id="userModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <button class="modal-close" onclick="toggleModal(false)">&times;</button>
        <h3><i class="fa-solid fa-user-plus" style="color:var(--text-accent); margin-right:10px;"></i>Add New User</h3>
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color:var(--text-danger);">*</span></label>
                <input type="text" name="full_name" id="full_name" placeholder="e.g. John Doe" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address <span style="color:var(--text-danger);">*</span></label>
                <input type="email" name="email" id="email" placeholder="your@email.com" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" placeholder="071-2345678">
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" name="address" id="address" placeholder="No. 123, Main Street, City">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary" style="width:100%; justify-content:center;">Add User</button>
        </form>
    </div>
</div>

<!-- Booking Report Modal -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeBookingModal()">&times;</button>
        <h3><i class="fa-regular fa-calendar-alt" style="color:var(--text-accent); margin-right:10px;"></i>Complete Booking History</h3>
        <div id="bookingDetails">
            <div class="no-bookings">Loading...</div>
        </div>
    </div>
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
        animateNumbers();
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
        initCharts();
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
    // Typing Effect
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Animated Number Counters
    // -------------------------------------------------------------
    function animateNumbers() {
        document.querySelectorAll('.kpi-value[data-count]').forEach(el => {
            const target = parseFloat(el.dataset.count);
            const isCurrency = el.dataset.currency === 'true';
            let current = 0;
            const duration = 1500;
            const steps = 60;
            const increment = target / steps;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                if (isCurrency) {
                    el.textContent = 'Rs. ' + current.toFixed(2);
                } else {
                    el.textContent = Math.floor(current).toLocaleString();
                }
            }, duration / steps);
        });

        document.querySelectorAll('.welcome-stats .num[data-count]').forEach(el => {
            const target = parseFloat(el.dataset.count);
            let current = 0;
            const duration = 1200;
            const steps = 50;
            const increment = target / steps;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = Math.floor(current).toLocaleString();
            }, duration / steps);
        });
    }

    // -------------------------------------------------------------
    // Charts
    // -------------------------------------------------------------
    function initCharts() {
        // 1. Revenue Trend
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode($trend_labels); ?>;
        const trendValues = <?php echo json_encode($trend_values); ?>;
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels.length ? trendLabels : ['No Data'],
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: trendValues.length ? trendValues : [0],
                    backgroundColor: 'rgba(14, 165, 233, 0.5)',
                    borderColor: 'rgba(14, 165, 233, 1)',
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
                                return 'Rs. ' + ctx.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: { color: '#64748b', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 10 } }
                    }
                }
            }
        });

        // 2. Booking Status
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusLabels = <?php echo json_encode($status_labels); ?>;
        const statusValues = <?php echo json_encode($status_values); ?>;
        const hasStatusData = statusValues.some(v => v > 0);
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: hasStatusData ? statusLabels : ['No Data'],
                datasets: [{
                    data: hasStatusData ? statusValues : [1],
                    backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6'],
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

        // 3. Popular Packages
        const popCtx = document.getElementById('popularChart').getContext('2d');
        const popLabels = <?php echo json_encode($pop_labels); ?>;
        const popValues = <?php echo json_encode($pop_values); ?>;
        const hasPopData = popValues.some(v => v > 0);
        new Chart(popCtx, {
            type: 'bar',
            data: {
                labels: hasPopData ? popLabels : ['No Data'],
                datasets: [{
                    label: 'Bookings',
                    data: hasPopData ? popValues : [0],
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
                                return ctx.parsed.y + ' bookings';
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

    // -------------------------------------------------------------
    // Modal Functions
    // -------------------------------------------------------------
    const bookingsData = <?php echo json_encode($bookings_by_user); ?>;

    function toggleModal(show) {
        document.getElementById('userModal').style.display = show ? 'flex' : 'none';
    }

    function showBookings(userId) {
        const modal = document.getElementById('bookingModal');
        const container = document.getElementById('bookingDetails');
        const bookings = bookingsData[userId] || [];

        if (bookings.length === 0) {
            container.innerHTML = `<div class="no-bookings"><i class="fa-regular fa-info-circle"></i> This user has no bookings yet.</div>`;
        } else {
            const first = bookings[0];
            const columns = Object.keys(first).filter(key => key !== 'user_id');

            let html = `<table class="modal-table"><thead><tr>`;
            columns.forEach(col => {
                const label = col.replace(/_/g, ' ').toUpperCase();
                html += `<th>${label}</th>`;
            });
            html += `</tr></thead><tbody>`;

            bookings.forEach(b => {
                html += `<tr>`;
                columns.forEach(col => {
                    let value = b[col] || 'N/A';
                    if (col === 'status') {
                        let badgeClass = 'badge-pending';
                        const statusLower = value.toLowerCase();
                        if (statusLower === 'approved') badgeClass = 'badge-approved';
                        else if (statusLower === 'confirmed') badgeClass = 'badge-confirmed';
                        else if (statusLower === 'cancelled') badgeClass = 'badge-cancelled';
                        value = `<span class="badge ${badgeClass}">${value}</span>`;
                    }
                    html += `<td>${value}</td>`;
                });
                html += `</tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;
        }

        modal.style.display = 'flex';
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').style.display = 'none';
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const userModal = document.getElementById('userModal');
        const bookingModal = document.getElementById('bookingModal');
        if (event.target == userModal) userModal.style.display = 'none';
        if (event.target == bookingModal) bookingModal.style.display = 'none';
    }

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
    // Particles
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
</script>

</body>
</html>