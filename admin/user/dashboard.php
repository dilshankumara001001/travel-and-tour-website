<?php
// ============================================================
// 0. SET TIMEZONE TO SRI LANKA (FOR CORRECT GREETING)
// ============================================================
date_default_timezone_set('Asia/Colombo');

session_start();

// ============================================================
// 1. CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Default to 'Admin' if no name found in session
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? '';

// ============================================================
// 2. INCLUDE DATABASE
// ============================================================
include("../includes/db.php");

// ============================================================
// 3. FETCH REAL STATS
// ============================================================
$pkg_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM packages WHERE status != 'Deleted'");
$pkg_data = mysqli_fetch_assoc($pkg_query);
$total_packages = $pkg_data['count'] ?? 0;

$book_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id");
$book_data = mysqli_fetch_assoc($book_query);
$total_bookings = $book_data['count'] ?? 0;

$fb_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM contact_feedbacks WHERE email = '$user_email'");
$fb_data = mysqli_fetch_assoc($fb_query);
$total_feedbacks = $fb_data['count'] ?? 0;

$pay_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE user_id = $user_id");
$pay_data = mysqli_fetch_assoc($pay_query);
$total_payments = $pay_data['count'] ?? 0;

$approved_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = 'Approved'");
$approved_data = mysqli_fetch_assoc($approved_query);
$approved_count = $approved_data['count'] ?? 0;

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = 'pending'");
$pending_data = mysqli_fetch_assoc($pending_query);
$pending_count = $pending_data['count'] ?? 0;

$spend_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE user_id = $user_id AND status = 'Success'");
$spend_data = mysqli_fetch_assoc($spend_query);
$total_spent = $spend_data['total'] ?? 0;

// ============================================================
// 4. FETCH RECENT BOOKINGS (for Activity Feed)
// ============================================================
$recent_query = "SELECT b.*, p.title AS package_name 
                 FROM bookings b 
                 LEFT JOIN packages p ON b.package_id = p.id 
                 WHERE b.user_id = $user_id 
                 ORDER BY b.id DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_query);
$recent_bookings = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_bookings[] = $row;
}

// ============================================================
// 5. FETCH DATA FOR CHARTS
// ============================================================
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M', strtotime("-$i months"));
    $query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND DATE_FORMAT(booking_date, '%Y-%m') = '$month'");
    $row = mysqli_fetch_assoc($query);
    $monthly_data[$month_label] = $row['count'] ?? 0;
}

$statuses = ['Approved', 'pending', 'Confirmed', 'Cancelled'];
$status_counts = [];
foreach ($statuses as $status) {
    $query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = '$status'");
    $row = mysqli_fetch_assoc($query);
    $status_counts[$status] = $row['count'] ?? 0;
}

// ============================================================
// 6. GREETING (Now uses Sri Lanka Time)
// ============================================================
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';

// ============================================================
// 7. THEME (stored in cookie)
// ============================================================
$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard | Travel Portal</title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet" />

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        /* ============================================================
           THEME VARIABLES (Glassmorphism + Modern)
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
            
            --status-approved: #10b981;
            --status-pending: #f59e0b;
            --status-cancelled: #ef4444;
            --status-confirmed: #3b82f6;
            
            --chart-grid: rgba(0,0,0,0.05);
            --chart-text: #64748b;
            
            --glass-edge: rgba(255, 255, 255, 0.5);
            --live-dot-color: #10b981;
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
            
            --status-approved: #34d399;
            --status-pending: #fbbf24;
            --status-cancelled: #f87171;
            --status-confirmed: #60a5fa;
            
            --chart-grid: rgba(255,255,255,0.05);
            --chart-text: #94a3b8;
            
            --glass-edge: rgba(255, 255, 255, 0.02);
            --live-dot-color: #34d399;
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
           SIDEBAR (Fixed)
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
           TOP HEADER (Navbar inside main) - Real-Time Clock
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

        /* Theme Toggle (inside header) */
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
           STATS ROW
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 20px 22px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 4px 0 0 4px;
        }

        .stat-card:nth-child(1)::before { background: #3b82f6; }
        .stat-card:nth-child(2)::before { background: #10b981; }
        .stat-card:nth-child(3)::before { background: #f59e0b; }
        .stat-card:nth-child(4)::before { background: #8b5cf6; }

        .stat-card:hover {
            transform: translateY(-6px);
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }

        .stat-card .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-card .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card .stat-icon.yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .stat-card .stat-info h3 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }

        .stat-card .stat-info p {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-card .stat-trend {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 30px;
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        /* Auto-refresh indicator */
        .refresh-indicator {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        .refresh-indicator i {
            font-size: 12px;
        }

        /* ============================================================
           DASHBOARD GRID (Activity + Charts)
        ============================================================ */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 32px;
        }

        /* ----- Activity Feed (Left) ----- */
        .activity-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 24px 24px 16px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .activity-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-card .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-card .card-header h3 i {
            color: var(--icon-color);
        }

        .activity-card .card-header a {
            font-size: 13px;
            font-weight: 600;
            color: var(--icon-color);
            text-decoration: none;
            transition: 0.2s;
        }

        .activity-card .card-header a:hover {
            text-decoration: underline;
        }

        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feed-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: var(--bg-body);
            border-radius: 14px;
            border-left: 3px solid var(--icon-color);
            transition: all 0.25s ease;
        }

        .feed-item:hover {
            transform: translateX(4px);
            background: var(--card-border);
        }

        .feed-item .feed-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--icon-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--icon-color);
            font-size: 16px;
            flex-shrink: 0;
        }

        .feed-item .feed-content {
            flex: 1;
        }

        .feed-item .feed-content .feed-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .feed-item .feed-content .feed-title small {
            font-weight: 400;
            color: var(--text-muted);
            font-size: 12px;
        }

        .feed-item .feed-content .feed-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .feed-item .feed-status {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 30px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .feed-status.approved { background: rgba(16, 185, 129, 0.12); color: var(--status-approved); }
        .feed-status.pending { background: rgba(245, 158, 11, 0.12); color: var(--status-pending); }
        .feed-status.confirmed { background: rgba(59, 130, 246, 0.12); color: var(--status-confirmed); }
        .feed-status.cancelled { background: rgba(239, 68, 68, 0.12); color: var(--status-cancelled); }

        .empty-feed {
            text-align: center;
            padding: 30px 10px;
            color: var(--text-muted);
        }

        /* ----- Charts (Right) ----- */
        .charts-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .charts-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .charts-card .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .charts-card .card-header h3 i {
            color: var(--icon-color);
        }

        .charts-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            flex: 1;
        }

        .chart-container {
            position: relative;
            height: 180px;
        }

        /* ============================================================
           FOOTER (Time removed)
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
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

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
            .dashboard-grid { grid-template-columns: 1fr; }
            .charts-wrapper { grid-template-columns: 1fr 1fr; }
            .header-datetime { font-size: 12px; padding: 4px 12px; }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .stat-card .stat-info h3 { font-size: 20px; }
            .stat-card .stat-trend { display: none; }
            .charts-wrapper { grid-template-columns: 1fr; }
            .chart-container { height: 200px; }
            .top-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .top-header .header-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .feed-item { flex-wrap: wrap; }
            .header-datetime { font-size: 11px; padding: 4px 10px; }
            .header-datetime .live-dot { width: 8px; height: 8px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 10px 12px; border-radius: 14px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; border-radius: 10px; }
            .stat-card .stat-info h3 { font-size: 17px; }
            .sidebar { width: 260px; }
            .top-header .greeting-text h2 { font-size: 18px; }
            .header-datetime { font-size: 10px; padding: 3px 8px; }
            .header-datetime .live-dot { width: 6px; height: 6px; }
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
            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
            <a href="packages.php">
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
            <a href="view_package.php?id=1">
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
                    Welcome back! Here's your travel activity snapshot.
                    <span class="refresh-indicator" id="lastUpdated">
                        <i class="fa-regular fa-clock"></i> 
                        Updated: <span id="updateTime"><?php echo date('h:i:s A'); ?></span>
                    </span>
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

        <!-- ===== STATS ===== -->
        <section class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-box-archive"></i></div>
                <div class="stat-info">
                    <h3 id="statBookings"><?php echo $total_bookings; ?></h3>
                    <p>Total Bookings</p>
                </div>
                <span class="stat-trend"><i class="fa-regular fa-check-circle"></i> <?php echo $approved_count; ?> approved</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3 id="statApproved"><?php echo $approved_count; ?></h3>
                    <p>Approved</p>
                </div>
                <span class="stat-trend" style="background:rgba(16,185,129,0.12);color:#10b981;"><i class="fa-regular fa-circle-check"></i> Active</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <h3 id="statPending"><?php echo $pending_count; ?></h3>
                    <p>Pending</p>
                </div>
                <?php if ($pending_count > 0): ?>
                    <span class="stat-trend" style="background:rgba(239,68,68,0.12);color:#ef4444;"><i class="fa-regular fa-hourglass-half"></i> Awaiting</span>
                <?php else: ?>
                    <span class="stat-trend" style="background:rgba(16,185,129,0.12);color:#10b981;"><i class="fa-regular fa-circle-check"></i> All clear</span>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-info">
                    <h3 id="statSpent">Rs. <?php echo number_format($total_spent, 0); ?></h3>
                    <p>Total Spent</p>
                </div>
                <span class="stat-trend" style="background:rgba(139,92,246,0.12);color:#8b5cf6;"><i class="fa-regular fa-arrow-up"></i> Lifetime</span>
            </div>
        </section>

        <!-- ===== DASHBOARD GRID (Activity + Charts) ===== -->
        <section class="dashboard-grid">

            <!-- LEFT: Activity Feed -->
            <div class="activity-card" id="activityCard">
                <div class="card-header">
                    <h3><i class="fa-regular fa-clock"></i> Recent Activity</h3>
                    <a href="my_bookings.php">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="activity-feed" id="activityFeed">
                    <?php if (count($recent_bookings) > 0): ?>
                        <?php foreach ($recent_bookings as $booking):
                            $status = strtolower($booking['status'] ?? 'pending');
                            $statusClass = $status;
                            if ($status == 'approved') $statusClass = 'approved';
                            elseif ($status == 'pending') $statusClass = 'pending';
                            elseif ($status == 'cancelled') $statusClass = 'cancelled';
                            elseif ($status == 'confirmed') $statusClass = 'confirmed';
                            else $statusClass = 'pending';

                            $displayName = $booking['package_name'] ?? $booking['manual_package_name'] ?? 'Custom Tour';
                            $cost = number_format($booking['total_cost'] ?? 0, 2);
                            $date = isset($booking['booking_date']) && $booking['booking_date'] != '0000-00-00'
                                ? date('d M Y', strtotime($booking['booking_date']))
                                : 'N/A';
                        ?>
                            <div class="feed-item" data-id="<?php echo $booking['id']; ?>">
                                <div class="feed-icon"><i class="fa-solid fa-suitcase"></i></div>
                                <div class="feed-content">
                                    <div class="feed-title">
                                        Booking #<?php echo $booking['id']; ?> <small>• <?php echo htmlspecialchars($displayName); ?></small>
                                    </div>
                                    <div class="feed-meta">
                                        <span><i class="fa-regular fa-calendar"></i> <?php echo $date; ?></span>
                                        <span><i class="fa-regular fa-credit-card"></i> Rs. <?php echo $cost; ?></span>
                                    </div>
                                </div>
                                <span class="feed-status <?php echo $statusClass; ?>">● <?php echo ucfirst($status); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-feed">
                            <i class="fa-regular fa-calendar-circle-plus" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.3;"></i>
                            <p>No recent bookings found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Charts -->
            <div class="charts-card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-chart-simple"></i> Analytics Overview</h3>
                </div>
                <div class="charts-wrapper">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

        </section>

        <!-- ===== FOOTER ===== -->
        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        </footer>

    </main>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script>
        // ============================================================
        // 1. SIDEBAR TOGGLE (Mobile)
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
        // 2. THEME TOGGLE
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
        // 3. REAL-TIME CLOCK (Updates every second)
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

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // ============================================================
        // 4. AUTO-REFRESH STATS & ACTIVITY (Every 30 seconds)
        // ============================================================
        function refreshDashboard() {
            fetch(window.location.href + '?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update Stats
                        document.getElementById('statBookings').textContent = data.total_bookings;
                        document.getElementById('statApproved').textContent = data.approved_count;
                        document.getElementById('statPending').textContent = data.pending_count;
                        document.getElementById('statSpent').textContent = 'Rs. ' + data.total_spent.toLocaleString('en-IN');

                        // Update Activity Feed
                        const feedContainer = document.getElementById('activityFeed');
                        if (data.recent_bookings && data.recent_bookings.length > 0) {
                            let html = '';
                            data.recent_bookings.forEach(booking => {
                                const statusClass = booking.status.toLowerCase();
                                const statusDisplay = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
                                html += `
                                    <div class="feed-item" data-id="${booking.id}">
                                        <div class="feed-icon"><i class="fa-solid fa-suitcase"></i></div>
                                        <div class="feed-content">
                                            <div class="feed-title">
                                                Booking #${booking.id} <small>• ${booking.package_name || 'Custom Tour'}</small>
                                            </div>
                                            <div class="feed-meta">
                                                <span><i class="fa-regular fa-calendar"></i> ${booking.booking_date_formatted}</span>
                                                <span><i class="fa-regular fa-credit-card"></i> Rs. ${booking.total_cost_formatted}</span>
                                            </div>
                                        </div>
                                        <span class="feed-status ${statusClass}">● ${statusDisplay}</span>
                                    </div>
                                `;
                            });
                            feedContainer.innerHTML = html;
                        }

                        // Update last updated time
                        const updateTimeEl = document.getElementById('updateTime');
                        if (updateTimeEl) {
                            const now = new Date();
                            updateTimeEl.textContent = now.toLocaleTimeString('en-US', { 
                                hour12: true, 
                                hour: '2-digit', 
                                minute: '2-digit', 
                                second: '2-digit' 
                            });
                        }
                    }
                })
                .catch(err => console.log('Refresh error:', err));
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);

        // ============================================================
        // 5. CHARTS (Chart.js) - Only load on initial page load
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const textColor = isDark ? '#94a3b8' : '#64748b';

            // Monthly Bar Chart
            const monthlyLabels = <?php echo json_encode(array_keys($monthly_data)); ?>;
            const monthlyValues = <?php echo json_encode(array_values($monthly_data)); ?>;

            const ctx1 = document.getElementById('monthlyChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Bookings',
                        data: monthlyValues,
                        backgroundColor: isDark ? 'rgba(96, 165, 250, 0.7)' : 'rgba(37, 99, 235, 0.7)',
                        borderColor: isDark ? '#60a5fa' : '#2563eb',
                        borderWidth: 2,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#ffffff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#cbd5e1' : '#1e293b'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: textColor },
                            grid: { color: gridColor }
                        },
                        x: {
                            ticks: { color: textColor },
                            grid: { color: gridColor }
                        }
                    }
                }
            });

            // Status Doughnut Chart
            const statusLabels = <?php echo json_encode(array_keys($status_counts)); ?>;
            const statusValues = <?php echo json_encode(array_values($status_counts)); ?>;
            const colors = {
                'Approved': isDark ? '#34d399' : '#10b981',
                'pending': isDark ? '#fbbf24' : '#f59e0b',
                'Confirmed': isDark ? '#60a5fa' : '#3b82f6',
                'Cancelled': isDark ? '#f87171' : '#ef4444'
            };
            const bgColors = statusLabels.map(label => colors[label] || '#64748b');

            const ctx2 = document.getElementById('statusChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: bgColors,
                        borderColor: isDark ? '#1e293b' : '#ffffff',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                padding: 8,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#ffffff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#cbd5e1' : '#1e293b',
                            callbacks: {
                                label: function(context) {
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

</body>
</html>