<?php
session_start();

// ============================================================
// 1. CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';

// ============================================================
// 2. INCLUDE DATABASE
// ============================================================
include("../includes/db.php");

// ============================================================
// 3. GET PACKAGE ID FROM URL
// ============================================================
$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($package_id == 0) {
    header("Location: packages.php");
    exit();
}

// ============================================================
// 4. FETCH PACKAGE DETAILS
// ============================================================
$query = "SELECT * FROM packages WHERE id = $package_id AND status != 'Deleted'";
$result = mysqli_query($conn, $query);
$package = mysqli_fetch_assoc($result);

if (!$package) {
    header("Location: packages.php");
    exit();
}

// ============================================================
// 5. GET ICON FOR PACKAGE
// ============================================================
$iconMap = [
    'adventure' => 'fa-mountain-sun',
    'beach' => 'fa-umbrella-beach',
    'safari' => 'fa-paw',
    'tea' => 'fa-leaf-heart',
    'diving' => 'fa-person-swimming',
    'cultural' => 'fa-gopuram'
];

$icon = 'fa-map-location-dot';
$titleLower = strtolower($package['title']);
$descLower = strtolower($package['description']);

foreach ($iconMap as $keyword => $iconClass) {
    if (strpos($titleLower, $keyword) !== false || strpos($descLower, $keyword) !== false) {
        $icon = $iconClass;
        break;
    }
}

// ============================================================
// 6. GREETING
// ============================================================
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';

// ============================================================
// 7. THEME
// ============================================================
$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($package['title']); ?> | Travel Portal</title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet" />

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ============================================================
           THEME VARIABLES (Matches Dashboard)
        ============================================================ */
        :root {
            --bg-body: #f0f4f8;
            --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --sidebar-text: #cbd5e1;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(255, 255, 255, 0.08);
            --sidebar-border: rgba(255, 255, 255, 0.05);
            
            --bg-navbar: rgba(255, 255, 255, 0.7);
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --card-bg: rgba(255, 255, 255, 0.75);
            --card-border: rgba(255, 255, 255, 0.3);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 16px 48px rgba(37, 99, 235, 0.15);
            
            --icon-color: #2563eb;
            --toggle-bg: #e2e8f0;
            --toggle-dot: #ffffff;
            --scrollbar-track: #f1f5f9;
            --scrollbar-thumb: #2563eb;
            
            --input-bg: rgba(255, 255, 255, 0.6);
            --input-border: rgba(0, 0, 0, 0.08);
            --input-focus: rgba(255, 255, 255, 0.8);
            
            --toast-bg: #ffffff;
            --toast-border: rgba(0, 0, 0, 0.06);
            
            --confetti-color-1: #f59e0b;
            --confetti-color-2: #3b82f6;
            --confetti-color-3: #10b981;
            --confetti-color-4: #ef4444;
            --confetti-color-5: #8b5cf6;
            --confetti-color-6: #ec4899;
            
            --live-dot-color: #10b981;
            --badge-bg: rgba(16, 185, 129, 0.12);
            --badge-text: #0f7b4a;
            --badge-border: rgba(16, 185, 129, 0.15);
        }

        [data-theme="dark"] {
            --bg-body: #0b1120;
            --sidebar-bg: linear-gradient(180deg, #0b1120 0%, #1a1a2e 100%);
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(255, 255, 255, 0.06);
            
            --bg-navbar: rgba(15, 23, 42, 0.8);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.6);
            --card-border: rgba(255, 255, 255, 0.05);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 16px 48px rgba(37, 99, 235, 0.2);
            
            --icon-color: #60a5fa;
            --toggle-bg: #475569;
            --toggle-dot: #f1f5f9;
            --scrollbar-track: #1e293b;
            --scrollbar-thumb: #60a5fa;
            
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.06);
            --input-focus: rgba(15, 23, 42, 0.8);
            
            --toast-bg: #1e293b;
            --toast-border: rgba(255, 255, 255, 0.06);
            
            --confetti-color-1: #fbbf24;
            --confetti-color-2: #60a5fa;
            --confetti-color-3: #34d399;
            --confetti-color-4: #f87171;
            --confetti-color-5: #a78bfa;
            --confetti-color-6: #f472b6;
            
            --live-dot-color: #34d399;
            --badge-bg: rgba(16, 185, 129, 0.20);
            --badge-text: #34d399;
            --badge-border: rgba(16, 185, 129, 0.25);
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ============================================================
           SIDEBAR (Matches Dashboard)
        ============================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 270px;
            background: var(--sidebar-bg);
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--sidebar-border);
            z-index: 999;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 40px;
            padding-left: 8px;
        }

        .sidebar-brand i {
            font-size: 28px;
            color: #60a5fa;
            filter: drop-shadow(0 4px 12px rgba(37, 99, 235, 0.3));
        }

        .sidebar-brand span {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .sidebar .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .sidebar .nav-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.25s ease;
            position: relative;
        }

        .sidebar .nav-menu a i {
            width: 22px;
            font-size: 18px;
            text-align: center;
        }

        .sidebar .nav-menu a:hover {
            background: var(--sidebar-active);
            color: var(--sidebar-text-hover);
            transform: translateX(4px);
        }

        .sidebar .nav-menu a.active {
            background: rgba(96, 165, 250, 0.12);
            color: #60a5fa;
            box-shadow: inset 3px 0 0 #60a5fa;
        }

        .sidebar .nav-menu .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 16px;
            color: #f87171;
        }

        .sidebar .nav-menu .logout-link:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }

        /* Mobile Hamburger */
        .hamburger {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 10px 12px;
            cursor: pointer;
            font-size: 22px;
            color: var(--text-primary);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main-content {
            margin-left: 270px;
            flex: 1;
            padding: 28px 36px 40px;
            min-height: 100vh;
            width: calc(100% - 270px);
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        /* ============================================================
           TOP HEADER (Matches Dashboard with Real-Time Clock)
        ============================================================ */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding: 16px 24px;
            background: var(--bg-navbar);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .top-header .greeting-text h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .top-header .greeting-text h2 span {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .top-header .greeting-text p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .top-header .header-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        /* Real-Time Date/Time Badge with Live Dot */
        .header-datetime {
            font-family: 'Inter', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--card-bg);
            padding: 6px 18px;
            border-radius: 30px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            letter-spacing: 0.3px;
        }

        .header-datetime .live-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--live-dot-color);
            animation: pulseDot 1.5s ease-in-out infinite;
            flex-shrink: 0;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.7); }
        }

        .header-datetime .datetime-text {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-datetime .datetime-text i {
            color: var(--text-muted);
            font-size: 14px;
            margin-right: 2px;
        }

        .header-datetime .datetime-text .time-separator {
            color: var(--text-muted);
            margin: 0 4px;
        }

        .top-header .profile-avatar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 12px 4px 4px;
            background: var(--card-bg);
            border-radius: 40px;
            border: 1px solid var(--card-border);
        }

        .top-header .profile-avatar img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .top-header .profile-avatar .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            border: 2px solid var(--card-bg);
            margin-left: -6px;
        }

        /* Theme Toggle */
        .theme-toggle {
            background: var(--toggle-bg);
            border: none;
            border-radius: 40px;
            width: 48px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5px;
            cursor: pointer;
            position: relative;
            transition: background 0.3s ease;
            flex-shrink: 0;
        }
        .theme-toggle .toggle-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--toggle-dot);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55), background 0.3s ease;
            position: absolute;
            left: 4px;
            top: 4px;
        }
        [data-theme="dark"] .theme-toggle .toggle-dot { transform: translateX(22px); }
        .theme-toggle .toggle-icon { font-size: 11px; color: var(--text-muted); z-index: 1; }
        .theme-toggle .toggle-icon.sun { margin-right: auto; }
        .theme-toggle .toggle-icon.moon { margin-left: auto; }
        [data-theme="dark"] .theme-toggle .toggle-icon.sun { color: #fbbf24; }
        [data-theme="dark"] .theme-toggle .toggle-icon.moon { color: #94a3b8; }

        /* ============================================================
           PAGE HEADER
        ============================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .page-header .title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: color 0.3s ease;
        }

        .page-header .title i {
            color: var(--icon-color);
            font-size: 24px;
        }

        .page-header .title span {
            font-weight: 400;
            font-size: 14px;
            color: var(--text-muted);
            margin-left: 4px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
            box-shadow: var(--card-shadow);
        }

        .btn-back:hover {
            background: var(--card-border);
            color: var(--text-primary);
            transform: translateX(-4px);
        }

        /* ============================================================
           PACKAGE DETAILS CARD
        ============================================================ */
        .package-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            border: 1px solid var(--card-border);
            padding: 32px 28px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .package-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .package-card .pkg-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .package-card .pkg-header .icon-box {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), rgba(16, 185, 129, 0.10));
            border: 1px solid rgba(59, 130, 246, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--icon-color);
            flex-shrink: 0;
        }

        .package-card .pkg-header .pkg-meta h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }

        .package-card .pkg-header .pkg-meta .pkg-price-tag {
            display: inline-block;
            font-size: 20px;
            font-weight: 700;
            color: var(--badge-text);
            background: var(--badge-bg);
            border: 1px solid var(--badge-border);
            padding: 4px 18px;
            border-radius: 30px;
            margin-top: 4px;
        }

        .package-card .pkg-description {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 24px;
            transition: color 0.3s ease;
        }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .detail-item {
            background: var(--input-bg);
            border-radius: 12px;
            padding: 14px 18px;
            border: 1px solid var(--input-border);
            transition: all 0.3s ease;
        }

        .detail-item .label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .detail-item .value {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 600;
            margin-top: 2px;
            transition: color 0.3s ease;
        }

        .detail-item .value i {
            margin-right: 6px;
            color: var(--icon-color);
        }

        /* Map */
        .map-container {
            width: 100%;
            height: 320px;
            border-radius: 16px;
            overflow: hidden;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        /* Actions */
        .pkg-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pkg-actions .btn {
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-book {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25);
        }

        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(16, 185, 129, 0.35);
        }

        .btn-outline {
            background: rgba(59, 130, 246, 0.08);
            color: var(--icon-color);
            border: 1px solid rgba(59, 130, 246, 0.12);
        }

        .btn-outline:hover {
            background: rgba(59, 130, 246, 0.18);
            transform: translateY(-3px);
        }

        /* Share Section */
        .share-section {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
            transition: border-color 0.3s ease;
        }

        .share-section .share-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: block;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .share-buttons {
            display: flex;
            gap: 10px;
        }

        .share-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s ease;
            border: 1px solid var(--card-border);
        }

        .share-btn:hover {
            transform: translateY(-3px);
            border-color: var(--icon-color);
        }

        .share-btn.fb { background: #1877f2; color: white; border-color: #1877f2; }
        .share-btn.tw { background: #000000; color: white; border-color: #000000; }
        .share-btn.wa { background: #25d366; color: white; border-color: #25d366; }
        .share-btn.mail { background: #ea4335; color: white; border-color: #ea4335; }

        /* ============================================================
           TOAST NOTIFICATIONS
        ============================================================ */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            padding: 14px 20px;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
            animation: slideInRight 0.4s ease, fadeOut 0.4s ease 2.6s forwards;
            max-width: 350px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
        }

        .toast.error { border-left-color: #ef4444; }
        .toast.info { border-left-color: #3b82f6; }

        @keyframes slideInRight {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-20px); }
        }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer-bar {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
            transition: all 0.3s ease;
            text-align: center;
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 20px 30px;
            }
            .hamburger {
                display: block;
            }
            .header-datetime { font-size: 12px; padding: 4px 12px; }
            .header-datetime .live-dot { width: 8px; height: 8px; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 16px 16px 24px; }
            .top-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .top-header .header-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .page-header .title { font-size: 19px; }
            .package-card { padding: 22px 16px; }
            .package-card .pkg-header .pkg-meta h1 { font-size: 22px; }
            .details-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .map-container { height: 240px; }
            .pkg-actions { flex-direction: column; }
            .pkg-actions .btn { justify-content: center; }
            .header-datetime { font-size: 11px; padding: 4px 10px; }
            .header-datetime .live-dot { width: 7px; height: 7px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 20px; }
            .top-header .greeting-text h2 { font-size: 18px; }
            .page-header .title { font-size: 17px; }
            .package-card { padding: 16px 12px; }
            .package-card .pkg-header { gap: 12px; }
            .package-card .pkg-header .icon-box { width: 48px; height: 48px; font-size: 20px; }
            .package-card .pkg-header .pkg-meta h1 { font-size: 19px; }
            .package-card .pkg-header .pkg-meta .pkg-price-tag { font-size: 16px; padding: 2px 14px; }
            .package-card .pkg-description { font-size: 14px; }
            .details-grid { grid-template-columns: 1fr; gap: 10px; }
            .detail-item { padding: 10px 14px; }
            .map-container { height: 200px; }
            .header-datetime { font-size: 10px; padding: 3px 8px; }
            .header-datetime .live-dot { width: 6px; height: 6px; }
            .btn-back { font-size: 12px; padding: 6px 14px; }
            .share-btn { width: 36px; height: 36px; font-size: 14px; }
        }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 12px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--icon-color); }
    </style>
</head>
<body>

    <!-- ===== HAMBURGER (Mobile) ===== -->
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-plane-departure"></i>
            <span>Travel Portal</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
            <a href="packages.php" class="active">
                <i class="fa-solid fa-suitcase-rolling"></i> Packages
            </a>
            <a href="my_bookings.php">
                <i class="fa-solid fa-box-archive"></i> My Bookings
            </a>
            <a href="feedback_status.php">
                <i class="fa-solid fa-envelope"></i> Feedback
            </a>
            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i> Payments
            </a>
            <a href="plan_a_trip.php">
                <i class="fa-solid fa-compass"></i> Plan a Trip
            </a>
            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- ===== TOP HEADER ===== -->
        <header class="top-header">
            <div class="greeting-text">
                <h2>
                    <?php echo $greeting; ?>, <span><?php echo htmlspecialchars($user_name); ?></span> 👋
                </h2>
                <p>
                    <i class="fa-regular fa-star" style="color:#f59e0b;"></i> 
                    Explore package details and book your adventure.
                </p>
            </div>
            <div class="header-actions">
                <!-- Real-Time Date/Time with Live Dot -->
                <div class="header-datetime">
                    <span class="live-dot"></span>
                    <span class="datetime-text">
                        <i class="fa-regular fa-calendar"></i>
                        <span id="liveTime"><?php echo date('h:i:s A'); ?></span>
                        <span class="time-separator">|</span>
                        <span id="liveDate"><?php echo date('F j, Y'); ?></span>
                    </span>
                </div>

                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <span class="toggle-icon sun"><i class="fa-regular fa-sun"></i></span>
                    <span class="toggle-dot"></span>
                    <span class="toggle-icon moon"><i class="fa-regular fa-moon"></i></span>
                </button>
                <div class="profile-avatar">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' rx='20' fill='%233b82f6'/%3E%3Ctext x='20' y='25' font-size='18' text-anchor='middle' fill='white' font-weight='bold' font-family='Inter'%3E<?php echo strtoupper(substr($user_name, 0, 1)); ?>%3C/text%3E%3C/svg%3E" alt="Avatar">
                    <span class="status-dot"></span>
                </div>
            </div>
        </header>

        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <div class="title">
                <i class="fa-solid fa-suitcase-rolling"></i>
                Package Details
                <span>· <?php echo htmlspecialchars($package['title']); ?></span>
            </div>
            <a href="packages.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Packages
            </a>
        </div>

        <!-- ===== PACKAGE CARD ===== -->
        <div class="package-card">

            <!-- Header -->
            <div class="pkg-header">
                <div class="icon-box">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>
                <div class="pkg-meta">
                    <h1><?php echo htmlspecialchars($package['title']); ?></h1>
                    <span class="pkg-price-tag">$<?php echo number_format($package['price'], 2); ?></span>
                </div>
                <span style="margin-left:auto; font-size:12px; font-weight:600; color:var(--text-muted); background:var(--input-bg); padding:4px 16px; border-radius:30px; border:1px solid var(--card-border);">
                    <i class="fa-regular fa-clock"></i> Active
                </span>
            </div>

            <!-- Description -->
            <div class="pkg-description">
                <?php echo htmlspecialchars($package['description']); ?>
            </div>

            <!-- Details Grid -->
            <div class="details-grid">
                <div class="detail-item">
                    <div class="label"><i class="fa-solid fa-location-dot" style="color:#3b82f6;"></i> Pickup Location</div>
                    <div class="value">
                        <?php 
                        if (!empty($package['latitude']) && !empty($package['longitude'])) {
                            echo "Lat: " . htmlspecialchars($package['latitude']) . ", Lng: " . htmlspecialchars($package['longitude']);
                        } else {
                            echo "Not specified";
                        }
                        ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fa-solid fa-flag" style="color:#10b981;"></i> Destination</div>
                    <div class="value">
                        <?php 
                        if (!empty($package['latitude2']) && !empty($package['longitude2'])) {
                            echo "Lat: " . htmlspecialchars($package['latitude2']) . ", Lng: " . htmlspecialchars($package['longitude2']);
                        } else {
                            echo "Not specified";
                        }
                        ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fa-regular fa-calendar" style="color:#f59e0b;"></i> Created</div>
                    <div class="value"><?php echo date('F j, Y', strtotime($package['created_at'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fa-solid fa-hashtag" style="color:#8b5cf6;"></i> Package ID</div>
                    <div class="value">#<?php echo $package['id']; ?></div>
                </div>
            </div>

            <!-- Map -->
            <?php if (!empty($package['latitude']) && !empty($package['longitude']) && !empty($package['latitude2']) && !empty($package['longitude2'])): ?>
                <div class="map-container" id="map-container">
                    <div id="map"></div>
                </div>
            <?php else: ?>
                <div class="map-container" style="display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
                    <span><i class="fa-solid fa-map"></i> Map coordinates not available for this package</span>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="pkg-actions">
                <!-- ===== BOOK NOW - Redirects to packages.php with book_now=1 ===== -->
                <a href="packages.php?book_now=1&id=<?php echo $package['id']; ?>" class="btn btn-book" onclick="showToast('Booking initiated!', 'success');">
                    <i class="fa-solid fa-circle-check"></i> Book Now
                </a>
                <a href="packages.php" class="btn btn-outline">
                    <i class="fa-solid fa-list"></i> View All Packages
                </a>
            </div>

            <!-- Share Section -->
            <div class="share-section">
                <span class="share-label"><i class="fa-solid fa-share-nodes"></i> Share this package:</span>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode("http://localhost/travel/user/view_package.php?id=" . $package['id']); ?>" target="_blank" class="share-btn fb"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode("Check out " . $package['title'] . "! "); ?>&url=<?php echo urlencode("http://localhost/travel/user/view_package.php?id=" . $package['id']); ?>" target="_blank" class="share-btn tw"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($package['title'] . " - " . "http://localhost/travel/user/view_package.php?id=" . $package['id']); ?>" target="_blank" class="share-btn wa"><i class="fa-brands fa-whatsapp"></i></a>
                    <a href="mailto:?subject=<?php echo urlencode($package['title']); ?>&body=<?php echo urlencode("Check out this amazing package: " . "http://localhost/travel/user/view_package.php?id=" . $package['id']); ?>" class="share-btn mail"><i class="fa-solid fa-envelope"></i></a>
                </div>
            </div>

        </div>

        <!-- ===== FOOTER ===== -->
        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        </footer>

    </main>

    <!-- ===== TOAST CONTAINER ===== -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script>
        // ============================================================
        // SIDEBAR TOGGLE (Mobile)
        // ============================================================
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('open')) {
                icon.className = 'fa-solid fa-xmark';
            } else {
                icon.className = 'fa-solid fa-bars';
            }
        });

        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnHamburger = hamburger.contains(event.target);
            if (!isClickInsideSidebar && !isClickOnHamburger && window.innerWidth <= 992) {
                sidebar.classList.remove('open');
                hamburger.querySelector('i').className = 'fa-solid fa-bars';
            }
        });

        // ============================================================
        // THEME TOGGLE
        // ============================================================
        (function initTheme() {
            const toggle = document.getElementById('themeToggle');
            const html = document.documentElement;
            const saved = localStorage.getItem('dashboard-theme');

            if (saved === 'dark') {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
                localStorage.setItem('dashboard-theme', 'light');
            }

            toggle.addEventListener('click', function() {
                const current = html.getAttribute('data-theme');
                if (current === 'dark') {
                    html.removeAttribute('data-theme');
                    localStorage.setItem('dashboard-theme', 'light');
                } else {
                    html.setAttribute('data-theme', 'dark');
                    localStorage.setItem('dashboard-theme', 'dark');
                }
            });
        })();

        // ============================================================
        // REAL-TIME CLOCK
        // ============================================================
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            const dateString = now.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });

            const timeEl = document.getElementById('liveTime');
            const dateEl = document.getElementById('liveDate');
            
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
        }

        setInterval(updateClock, 1000);
        updateClock();

        // ============================================================
        // TOAST
        // ============================================================
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // ============================================================
        // MAP - Google Maps
        // ============================================================
        <?php if (!empty($package['latitude']) && !empty($package['longitude']) && !empty($package['latitude2']) && !empty($package['longitude2'])): ?>
        function initMap() {
            const loc1 = { lat: parseFloat(<?php echo $package['latitude']; ?>), lng: parseFloat(<?php echo $package['longitude']; ?>) };
            const loc2 = { lat: parseFloat(<?php echo $package['latitude2']; ?>), lng: parseFloat(<?php echo $package['longitude2']; ?>) };
            
            const map = new google.maps.Map(document.getElementById('map'), {
                zoom: 8,
                center: loc1,
                mapId: 'DEMO_MAP_ID'
            });
            
            new google.maps.Marker({ position: loc1, map: map, title: 'Pickup Location', label: { text: 'P', color: 'white', fontWeight: 'bold' } });
            new google.maps.Marker({ position: loc2, map: map, title: 'Destination', label: { text: 'D', color: 'white', fontWeight: 'bold' } });
            
            const path = new google.maps.Polyline({
                path: [loc1, loc2],
                geodesic: true,
                strokeColor: '#3b82f6',
                strokeOpacity: 0.8,
                strokeWeight: 4
            });
            path.setMap(map);
            
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(loc1);
            bounds.extend(loc2);
            map.fitBounds(bounds);
        }

        window.onload = function() {
            if (typeof google !== 'undefined' && google.maps) {
                initMap();
            }
        };
        <?php endif; ?>

        console.log('✅ View Package - Dashboard Design Applied!');
    </script>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw"></script>

</body>
</html>