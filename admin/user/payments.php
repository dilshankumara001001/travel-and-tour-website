<?php
date_default_timezone_set('Asia/Colombo');
session_start();
include("../includes/db.php");

// ============================================================
// CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================================
// GET USER INFO
// ============================================================
$user_query = mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_query);
$user_name = $user_data['full_name'] ?? 'User';
$user_email = $user_data['email'] ?? '';

// ============================================================
// ENSURE PAYMENTS TABLE HAS user_id COLUMN
// ============================================================
$check_column = $conn->query("SHOW COLUMNS FROM payments LIKE 'user_id'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN user_id INT(11) NOT NULL DEFAULT 0 AFTER id");
    $conn->query("ALTER TABLE payments ADD INDEX idx_user_id (user_id)");
}

// ============================================================
// PROCESS PAYMENT
// ============================================================
$payment_msg = '';
$payment_success = false;
$last_transaction = '';

if (isset($_POST['pay_now'])) {
    $package_id = (int)$_POST['package_id'];
    $amount = (float)$_POST['amount'];
    $card_name = mysqli_real_escape_string($conn, $_POST['card_name'] ?? '');
    $card_number = mysqli_real_escape_string($conn, $_POST['card_number'] ?? '');
    $expiry = mysqli_real_escape_string($conn, $_POST['expiry'] ?? '');

    if ($package_id > 0 && $amount > 0 && !empty($card_name)) {
        $transaction_id = 'TXN-' . strtoupper(uniqid() . bin2hex(random_bytes(6)));

        $stmt = $conn->prepare("INSERT INTO payments (user_id, package_id, amount, card_name, transaction_id, status, payment_date) VALUES (?, ?, ?, ?, ?, 'Success', NOW())");
        $stmt->bind_param("iisss", $user_id, $package_id, $amount, $card_name, $transaction_id);
        if ($stmt->execute()) {
            $payment_success = true;
            $last_transaction = $transaction_id;
            $payment_msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Payment successful! Transaction ID: ' . htmlspecialchars($transaction_id) . '</div>';
        } else {
            $payment_msg = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Payment failed: ' . $stmt->error . '</div>';
        }
    } else {
        $payment_msg = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Please fill all required fields.</div>';
    }
}

// ============================================================
// FETCH PAYMENT STATS
// ============================================================
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'Success' THEN amount ELSE 0 END) as total_revenue
    FROM payments 
    WHERE user_id = ?
");
$stats_query->bind_param("i", $user_id);
$stats_query->execute();
$stats_result = $stats_query->get_result();
$stats = $stats_result->fetch_assoc();

$total_payments = $stats['total'] ?? 0;
$successful_payments = $stats['successful'] ?? 0;
$pending_payments = $stats['pending'] ?? 0;
$failed_payments = $stats['failed'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;

// ============================================================
// FETCH PAYMENT HISTORY
// ============================================================
$history_query = $conn->prepare("SELECT p.*, pk.title AS package_title FROM payments p LEFT JOIN packages pk ON p.package_id = pk.id WHERE p.user_id = ? ORDER BY p.id DESC");
$history_query->bind_param("i", $user_id);
$history_query->execute();
$history_result = $history_query->get_result();
$payments = [];
while ($row = $history_result->fetch_assoc()) {
    $payments[] = $row;
}

// ============================================================
// GREETING
// ============================================================
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';

// ============================================================
// THEME
// ============================================================
$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payments | Secure Checkout</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&family=Orbitron:wght@400;600;700;800;900&display=swap" rel="stylesheet" />

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
            
            --success-bg: rgba(16, 185, 129, 0.12);
            --success-border: rgba(16, 185, 129, 0.25);
            --success-text: #0f7b4a;
            --error-bg: rgba(239, 68, 68, 0.12);
            --error-border: rgba(239, 68, 68, 0.25);
            --error-text: #b91c1c;
            
            --stats-card-bg: rgba(255, 255, 255, 0.5);
            --stats-border: rgba(0, 0, 0, 0.04);
            --filter-bg: rgba(255, 255, 255, 0.5);
            --history-bg: rgba(255, 255, 255, 0.3);
            --no-results-text: #94a3b8;
            
            --status-success-bg: rgba(16, 185, 129, 0.12);
            --status-success-text: #0f7b4a;
            --status-success-border: rgba(16, 185, 129, 0.15);
            --status-pending-bg: rgba(245, 158, 11, 0.12);
            --status-pending-text: #92400e;
            --status-pending-border: rgba(245, 158, 11, 0.15);
            --status-failed-bg: rgba(239, 68, 68, 0.12);
            --status-failed-text: #991b1b;
            --status-failed-border: rgba(239, 68, 68, 0.15);
            
            --toast-bg: #ffffff;
            --toast-border: rgba(0, 0, 0, 0.06);
            
            --confetti-color-1: #f59e0b;
            --confetti-color-2: #3b82f6;
            --confetti-color-3: #10b981;
            --confetti-color-4: #ef4444;
            --confetti-color-5: #8b5cf6;
            --confetti-color-6: #ec4899;
            
            --gradient-start: #3b82f6;
            --gradient-end: #8b5cf6;
            --live-dot-color: #10b981;
            
            --cost-text: #0f7b4a;
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
            
            --success-bg: rgba(16, 185, 129, 0.15);
            --success-border: rgba(16, 185, 129, 0.30);
            --success-text: #34d399;
            --error-bg: rgba(239, 68, 68, 0.15);
            --error-border: rgba(239, 68, 68, 0.30);
            --error-text: #f87171;
            
            --stats-card-bg: rgba(30, 41, 59, 0.4);
            --stats-border: rgba(255, 255, 255, 0.04);
            --filter-bg: rgba(30, 41, 59, 0.4);
            --history-bg: rgba(30, 41, 59, 0.3);
            --no-results-text: #94a3b8;
            
            --status-success-bg: rgba(16, 185, 129, 0.20);
            --status-success-text: #34d399;
            --status-success-border: rgba(16, 185, 129, 0.25);
            --status-pending-bg: rgba(245, 158, 11, 0.20);
            --status-pending-text: #fbbf24;
            --status-pending-border: rgba(245, 158, 11, 0.25);
            --status-failed-bg: rgba(239, 68, 68, 0.20);
            --status-failed-text: #f87171;
            --status-failed-border: rgba(239, 68, 68, 0.25);
            
            --toast-bg: #1e293b;
            --toast-border: rgba(255, 255, 255, 0.06);
            
            --confetti-color-1: #fbbf24;
            --confetti-color-2: #60a5fa;
            --confetti-color-3: #34d399;
            --confetti-color-4: #f87171;
            --confetti-color-5: #a78bfa;
            --confetti-color-6: #f472b6;
            
            --gradient-start: #60a5fa;
            --gradient-end: #a78bfa;
            --live-dot-color: #34d399;
            
            --cost-text: #34d399;
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
           ALERTS
        ============================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(8px);
            animation: fadeUp 0.5s ease both;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .alert-success {
            border-left: 4px solid #10b981;
            color: var(--success-text);
        }

        .alert-error {
            border-left: 4px solid #ef4444;
            color: var(--error-text);
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           STATS CARDS
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--stats-card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--stats-border);
            border-radius: 16px;
            padding: 18px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .stat-card .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }

        .stat-card .stat-icon {
            font-size: 20px;
            display: block;
            margin-bottom: 4px;
        }

        .stat-card .stat-number.green { color: var(--status-success-text); }
        .stat-card .stat-number.blue { color: var(--icon-color); }
        .stat-card .stat-number.yellow { color: var(--status-pending-text); }
        .stat-card .stat-number.red { color: var(--status-failed-text); }
        .stat-card .stat-number.purple { color: #8b5cf6; }

        .stat-card.total-revenue .stat-number { color: #fbbf24; font-size: 22px; }
        [data-theme="dark"] .stat-card.total-revenue .stat-number { color: #fbbf24; }

        /* ============================================================
           FILTER BAR
        ============================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
            align-items: center;
            justify-content: space-between;
            background: var(--filter-bg);
            backdrop-filter: blur(8px);
            padding: 14px 20px;
            border-radius: 16px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .filter-bar .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1 1 300px;
        }

        .filter-bar .filter-group input,
        .filter-bar .filter-group select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            padding: 8px 14px;
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: 0.3s;
            font-family: 'Inter', sans-serif;
            min-width: 120px;
        }

        .filter-bar .filter-group input:focus,
        .filter-bar .filter-group select:focus {
            border-color: var(--icon-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            background: var(--input-focus);
        }

        .filter-bar .filter-group input::placeholder {
            color: var(--text-muted);
        }

        .filter-bar .filter-actions {
            display: flex;
            gap: 8px;
        }

        .btn-filter-action {
            background: rgba(59, 130, 246, 0.10);
            border: 1px solid rgba(59, 130, 246, 0.15);
            color: var(--icon-color);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-filter-action:hover {
            background: rgba(59, 130, 246, 0.20);
            transform: translateY(-2px);
        }

        .btn-filter-action.clear {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.15);
            color: var(--status-failed-text);
        }

        .btn-filter-action.clear:hover {
            background: rgba(239, 68, 68, 0.18);
        }

        .btn-filter-action.export {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.15);
            color: var(--status-success-text);
        }

        .btn-filter-action.export:hover {
            background: rgba(16, 185, 129, 0.18);
        }

        /* ============================================================
           PAYMENT FORM
        ============================================================ */
        .form-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 28px 28px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .form-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
        }

        .form-card .form-header i {
            font-size: 26px;
            color: var(--icon-color);
        }

        .form-card .form-header h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
            transition: color 0.3s ease;
        }

        .form-group label i {
            margin-right: 6px;
            color: var(--icon-color);
        }

        .form-group select,
        .form-group input {
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            padding: 10px 16px;
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: var(--icon-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            background: var(--input-focus);
        }

        .form-group select option {
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .form-group input:read-only {
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin: 10px 0 15px;
        }

        .btn-group .option-btn {
            flex: 1;
            padding: 10px;
            background: rgba(56, 189, 248, 0.04);
            border: 1.5px solid rgba(56, 189, 248, 0.08);
            border-radius: 12px;
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-group .option-btn:hover {
            background: rgba(56, 189, 248, 0.10);
            border-color: rgba(56, 189, 248, 0.20);
            transform: translateY(-2px);
            color: var(--text-primary);
        }

        .btn-group .option-btn.active {
            background: rgba(56, 189, 248, 0.12);
            border-color: var(--icon-color);
            color: var(--icon-color);
        }

        .hidden-panel {
            display: none;
            margin-top: 12px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #video-preview {
            width: 100%;
            border-radius: 14px;
            border: 2px solid var(--icon-color);
            background: #000;
            max-height: 200px;
        }

        .camera-hint {
            text-align: center;
            font-size: 12px;
            margin-top: 8px;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .btn-pay {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            cursor: pointer;
            transition: all 0.35s ease;
            margin-top: 10px;
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.10);
        }

        .btn-pay:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 8px 50px rgba(56, 189, 248, 0.20);
        }

        /* ============================================================
           PAYMENT HISTORY
        ============================================================ */
        .history-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 24px 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .history-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .history-card .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .history-card .history-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s ease;
        }

        .history-card .history-header h3 i {
            color: var(--icon-color);
        }

        .history-card .history-header .record-count {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .history-card .history-header .record-count span {
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 2px solid var(--card-border);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            transition: color 0.3s ease, border-color 0.3s ease;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-weight: 500;
            transition: color 0.3s ease, border-color 0.3s ease;
        }

        tr:hover td {
            background: var(--card-border);
        }

        /* Transaction ID Badge */
        .txn-id {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: var(--icon-color);
            background: var(--input-bg);
            padding: 2px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-block;
        }

        .txn-id:hover {
            background: var(--icon-color);
            color: #fff;
            transform: scale(1.05);
        }

        .txn-id .copy-hint {
            font-size: 10px;
            opacity: 0.5;
            margin-left: 4px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }

        .status-badge.success {
            background: var(--status-success-bg);
            color: var(--status-success-text);
            border: 1px solid var(--status-success-border);
            animation: pulseSuccess 2s infinite;
        }

        .status-badge.pending {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
            border: 1px solid var(--status-pending-border);
            animation: pulsePending 2s infinite;
        }

        .status-badge.failed {
            background: var(--status-failed-bg);
            color: var(--status-failed-text);
            border: 1px solid var(--status-failed-border);
        }

        @keyframes pulseSuccess {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes pulsePending {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Amount Cell */
        .amount-progress {
            width: 80px;
            height: 4px;
            background: var(--card-border);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }

        .amount-progress .bar {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            transition: width 1.5s ease;
        }

        .amount-cell {
            font-weight: 700;
            color: var(--cost-text);
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .date-cell {
            font-size: 12px;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: var(--no-results-text);
            font-size: 15px;
            display: none;
            transition: color 0.3s ease;
        }

        .no-results i {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        .empty-history {
            text-align: center;
            padding: 50px 20px;
            color: var(--no-results-text);
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .empty-history i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.2;
        }

        .empty-history .sub-text {
            font-size: 14px;
            opacity: 0.6;
            margin-top: 6px;
        }

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
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
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
           CONFETTI
        ============================================================ */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 99999;
            overflow: hidden;
        }

        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            animation: confettiFall linear forwards;
        }

        @keyframes confettiFall {
            0% { transform: translateY(-10px) rotate(0deg) scale(1); opacity: 1; }
            100% { transform: translateY(110vh) rotate(720deg) scale(0.5); opacity: 0; }
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
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 12px; }
            .stat-card .stat-number { font-size: 22px; }
            .filter-bar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .filter-bar .filter-group { flex-direction: column; }
            .filter-bar .filter-group input,
            .filter-bar .filter-group select { width: 100%; min-width: auto; }
            .filter-bar .filter-actions { justify-content: stretch; }
            .filter-bar .filter-actions .btn-filter-action { flex: 1; justify-content: center; }
            .form-card { padding: 20px 16px; }
            .form-card .form-header h2 { font-size: 17px; }
            .btn-group { flex-direction: column; }
            .btn-group .option-btn { flex: none; }
            .table-wrapper table,
            .table-wrapper thead,
            .table-wrapper tbody,
            .table-wrapper th,
            .table-wrapper td,
            .table-wrapper tr { display: block; }
            .table-wrapper thead { display: none; }
            .table-wrapper tbody tr {
                display: block;
                padding: 16px;
                margin-bottom: 16px;
                border: 1px solid var(--card-border);
                border-radius: 16px;
                background: var(--history-bg);
                animation: fadeInUp 0.5s ease forwards;
            }
            .table-wrapper tbody tr td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 0;
                border: none;
                border-bottom: 1px solid var(--card-border);
                font-size: 13px;
            }
            .table-wrapper tbody tr td:last-child { border-bottom: none; }
            .table-wrapper tbody tr td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .amount-progress { width: 60px; }
            .history-card .history-header { flex-direction: column; align-items: flex-start; }
            .history-card .history-header .record-count { font-size: 12px; }
            .toast-container { bottom: 20px; right: 16px; }
            .toast { max-width: 280px; font-size: 13px; padding: 12px 16px; }
            .btn-pay { font-size: 13px; padding: 14px; }
            .form-group select, .form-group input { font-size: 14px; padding: 10px 14px; }
            .header-datetime { font-size: 11px; padding: 4px 10px; }
            .header-datetime .live-dot { width: 7px; height: 7px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 20px; }
            .top-header .greeting-text h2 { font-size: 18px; }
            .page-header .title { font-size: 17px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card .stat-label { font-size: 10px; }
            .form-card { padding: 16px 12px; }
            .form-card .form-header h2 { font-size: 15px; }
            .history-card { padding: 16px 12px; }
            .table-wrapper tbody tr { padding: 12px; }
            .table-wrapper tbody tr td { font-size: 12px; padding: 4px 0; }
            .txn-id { font-size: 10px; padding: 1px 8px; }
            .status-badge { font-size: 10px; padding: 2px 10px; }
            .header-datetime { font-size: 10px; padding: 3px 8px; }
            .header-datetime .live-dot { width: 6px; height: 6px; }
            .btn-back { font-size: 12px; padding: 6px 14px; }
            .empty-history i { font-size: 40px; }
            .empty-history p { font-size: 15px; }
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
            <a href="packages.php">
                <i class="fa-solid fa-suitcase-rolling"></i> Packages
            </a>
            <a href="my_bookings.php">
                <i class="fa-solid fa-box-archive"></i> My Bookings
            </a>
            <a href="feedback_status.php">
                <i class="fa-solid fa-envelope"></i> Feedback
            </a>
            <a href="payments.php" class="active">
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
                    Manage your payments and transactions securely.
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
                <i class="fa-solid fa-credit-card"></i>
                Payments
                <span>· Secure Checkout</span>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- ===== ALERTS ===== -->
        <?php if ($payment_msg): ?>
            <div class="<?php echo strpos($payment_msg, 'success') !== false ? 'alert alert-success' : 'alert alert-error'; ?>" id="paymentAlert">
                <?php echo $payment_msg; ?>
            </div>
        <?php endif; ?>

        <!-- ===== STATS CARDS ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-receipt" style="color:var(--icon-color);"></i></span>
                <div class="stat-number blue" id="totalCount">0</div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-check-circle" style="color:var(--status-success-text);"></i></span>
                <div class="stat-number green" id="successCount">0</div>
                <div class="stat-label">Successful</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-clock" style="color:var(--status-pending-text);"></i></span>
                <div class="stat-number yellow" id="pendingCount">0</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-xmark-circle" style="color:var(--status-failed-text);"></i></span>
                <div class="stat-number red" id="failedCount">0</div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card total-revenue">
                <span class="stat-icon"><i class="fa-solid fa-coins" style="color:#fbbf24;"></i></span>
                <div class="stat-number purple" id="revenueTotal">Rs. 0.00</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- ===== PAYMENT FORM ===== -->
        <div class="form-card">
            <div class="form-header">
                <i class="fa-solid fa-lock"></i>
                <h2>Secure Checkout</h2>
            </div>

            <form method="POST" id="payForm">
                <div class="form-group">
                    <label><i class="fa-solid fa-box"></i> Select Package</label>
                    <select name="package_id" id="packageSelect" required>
                        <option value="">Choose a package</option>
                        <?php
                        $pkg_stmt = $conn->prepare("SELECT id, title, price FROM packages");
                        $pkg_stmt->execute();
                        $pkg_result = $pkg_stmt->get_result();
                        while ($pkg = $pkg_result->fetch_assoc()) {
                            echo "<option value='" . $pkg['id'] . "' data-price='" . $pkg['price'] . "'>" . htmlspecialchars($pkg['title']) . " - $" . number_format($pkg['price'], 2) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-dollar-sign"></i> Amount</label>
                    <input type="text" name="amount" id="amountField" readonly placeholder="Select a package" />
                </div>

                <div class="form-group">
                    <label><i class="fa-regular fa-user"></i> Cardholder Name</label>
                    <input type="text" name="card_name" placeholder="Full name on card" required />
                </div>

                <div class="btn-group">
                    <button type="button" class="option-btn" onclick="togglePanel('scanPanel', this)">
                        <i class="fa-solid fa-camera"></i> Scan Card
                    </button>
                    <button type="button" class="option-btn" onclick="togglePanel('manualPanel', this)">
                        <i class="fa-regular fa-keyboard"></i> Manual Entry
                    </button>
                </div>

                <div id="scanPanel" class="hidden-panel">
                    <video id="video-preview" autoplay playsinline></video>
                    <p class="camera-hint">
                        <i class="fa-regular fa-circle"></i> Camera initializing...
                    </p>
                </div>

                <div id="manualPanel" class="hidden-panel">
                    <div class="form-group">
                        <label><i class="fa-regular fa-credit-card"></i> Card Number</label>
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" />
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-calendar"></i> Expiry (MM/YY)</label>
                        <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" />
                    </div>
                </div>

                <button type="submit" name="pay_now" class="btn-pay">
                    <i class="fa-solid fa-lock"></i> Confirm Payment
                </button>
            </form>
        </div>

        <!-- ===== PAYMENT HISTORY ===== -->
        <div class="history-card">
            <div class="history-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Payment History</h3>
                <span class="record-count">Showing <span id="historyCount">0</span> records</span>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <input type="text" id="searchInput" placeholder="🔍 Search by package or card..." oninput="filterHistory()">
                    <select id="statusFilter" onchange="filterHistory()">
                        <option value="all">All Status</option>
                        <option value="Success">Success</option>
                        <option value="Pending">Pending</option>
                        <option value="Failed">Failed</option>
                    </select>
                    <input type="date" id="dateFrom" onchange="filterHistory()" style="min-width:140px;">
                    <input type="date" id="dateTo" onchange="filterHistory()" style="min-width:140px;">
                </div>
                <div class="filter-actions">
                    <button class="btn-filter-action export" onclick="exportCSV()" title="Export to CSV">
                        <i class="fa-solid fa-file-export"></i> Export
                    </button>
                    <button class="btn-filter-action clear" onclick="clearFilters()" title="Clear all filters">
                        <i class="fa-solid fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <?php if (count($payments) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Card</th>
                                <th>Transaction</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <?php foreach ($payments as $p): 
                                $status_class = strtolower($p['status']);
                            ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-date="<?php echo strtotime($p['payment_date']); ?>"
                                    data-search="<?php echo strtolower(($p['package_title'] ?? '') . ' ' . $p['card_name'] . ' ' . $p['transaction_id']); ?>"
                                    data-amount="<?php echo $p['amount']; ?>">
                                    <td data-label="ID">#<?php echo $p['id']; ?></td>
                                    <td data-label="Package"><?php echo htmlspecialchars($p['package_title'] ?? 'N/A'); ?></td>
                                    <td data-label="Amount">
                                        <span class="amount-cell">$<?php echo number_format($p['amount'], 2); ?></span>
                                        <div class="amount-progress">
                                            <div class="bar" style="width: <?php echo min(($p['amount'] / 1000) * 100, 100); ?>%;"></div>
                                        </div>
                                    </td>
                                    <td data-label="Card"><?php echo htmlspecialchars($p['card_name']); ?></td>
                                    <td data-label="Transaction">
                                        <span class="txn-id" onclick="copyTxn('<?php echo $p['transaction_id']; ?>')" title="Click to copy">
                                            <?php echo htmlspecialchars($p['transaction_id']); ?>
                                            <span class="copy-hint"><i class="fa-regular fa-copy"></i></span>
                                        </span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            ● <?php echo htmlspecialchars($p['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Date" class="date-cell">
                                        <?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="no-results" id="noResults">
                        <i class="fa-regular fa-face-frown"></i>
                        No payments match your filters.
                    </div>
                <?php else: ?>
                    <div class="empty-history">
                        <i class="fa-regular fa-receipt"></i>
                        No payments recorded yet.
                        <div class="sub-text">Complete a payment to see it here.</div>
                    </div>
                <?php endif; ?>
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
        // ===== SIDEBAR TOGGLE (Mobile) =====
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

        // ===== THEME TOGGLE =====
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
        // REAL-TIME CLOCK (Updates every second)
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
        // ANIMATE STATS
        // ============================================================
        function animateStats() {
            const total = <?php echo $total_payments; ?>;
            const success = <?php echo $successful_payments; ?>;
            const pending = <?php echo $pending_payments; ?>;
            const failed = <?php echo $failed_payments; ?>;
            const revenue = <?php echo $total_revenue; ?>;

            animateNumber('totalCount', total);
            animateNumber('successCount', success);
            animateNumber('pendingCount', pending);
            animateNumber('failedCount', failed);
            animateCurrency('revenueTotal', revenue);
            document.getElementById('historyCount').textContent = <?php echo count($payments); ?>;
        }

        function animateNumber(id, target) {
            const el = document.getElementById(id);
            if (!el) return;
            let current = 0;
            const step = Math.max(1, Math.floor(target / 40));
            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = current;
            }, 30);
        }

        function animateCurrency(id, target) {
            const el = document.getElementById(id);
            if (!el) return;
            let current = 0;
            const step = Math.max(1, Math.floor(target / 40));
            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = 'Rs. ' + current.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }, 30);
        }

        // ============================================================
        // AUTO-UPDATE AMOUNT FIELD
        // ============================================================
        document.getElementById('packageSelect').addEventListener('change', function() {
            const price = this.options[this.selectedIndex].getAttribute('data-price');
            document.getElementById('amountField').value = price ? '$ ' + parseFloat(price).toFixed(2) : '';
        });

        // ============================================================
        // PANEL TOGGLE + CAMERA
        // ============================================================
        let activePanel = null;

        async function togglePanel(panelId, btn) {
            const scanPanel = document.getElementById('scanPanel');
            const manualPanel = document.getElementById('manualPanel');
            const video = document.getElementById('video-preview');
            const allBtns = document.querySelectorAll('.option-btn');

            allBtns.forEach(b => b.classList.remove('active'));

            if (panelId === 'scanPanel') {
                scanPanel.style.display = 'block';
                manualPanel.style.display = 'none';
                btn.classList.add('active');
                activePanel = 'scan';
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                    video.srcObject = stream;
                    document.querySelector('.camera-hint').innerHTML = '<i class="fa-regular fa-circle-check" style="color:#4ade80;"></i> Camera active';
                } catch (err) {
                    document.querySelector('.camera-hint').innerHTML = '<i class="fa-regular fa-circle-exclamation" style="color:#f87171;"></i> Camera access denied';
                }
            } else {
                scanPanel.style.display = 'none';
                manualPanel.style.display = 'block';
                btn.classList.add('active');
                activePanel = 'manual';
                if (video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                    document.querySelector('.camera-hint').innerHTML = '<i class="fa-regular fa-circle"></i> Camera stopped';
                }
            }
        }

        // ============================================================
        // AUTO-FORMAT CARD NUMBER
        // ============================================================
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < val.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += val[i];
            }
            this.value = formatted;
        });

        // ============================================================
        // EXPIRY AUTO-SLASH
        // ============================================================
        document.querySelector('input[name="expiry"]')?.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            if (val.length >= 2) {
                this.value = val.slice(0, 2) + '/' + val.slice(2, 4);
            } else {
                this.value = val;
            }
        });

        // ============================================================
        // COPY TRANSACTION ID
        // ============================================================
        function copyTxn(txnId) {
            navigator.clipboard.writeText(txnId).then(() => {
                showToast('Transaction ID copied!', 'success');
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = txnId;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Transaction ID copied!', 'success');
            });
        }

        // ============================================================
        // FILTER HISTORY
        // ============================================================
        function filterHistory() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const rows = document.querySelectorAll('#historyBody tr');
            let visible = 0;

            rows.forEach(row => {
                const searchData = (row.dataset.search || '').toLowerCase();
                const rowStatus = row.dataset.status || '';
                const rowDate = parseInt(row.dataset.date || 0);

                let matchSearch = searchData.includes(search);
                let matchStatus = status === 'all' || rowStatus === status;
                let matchDate = true;

                if (dateFrom) {
                    const fromDate = new Date(dateFrom).setHours(0,0,0,0);
                    if (rowDate < fromDate / 1000) matchDate = false;
                }
                if (dateTo) {
                    const toDate = new Date(dateTo).setHours(23,59,59,999);
                    if (rowDate > toDate / 1000) matchDate = false;
                }

                const show = matchSearch && matchStatus && matchDate;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            const noResults = document.getElementById('noResults');
            const totalRows = rows.length;
            if (visible === 0 && totalRows > 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }

            document.getElementById('historyCount').textContent = visible;
        }

        // ============================================================
        // CLEAR FILTERS
        // ============================================================
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            filterHistory();
            showToast('Filters reset successfully!', 'info');
        }

        // ============================================================
        // EXPORT CSV
        // ============================================================
        function exportCSV() {
            const rows = document.querySelectorAll('#historyBody tr:not([style*="display: none"])');
            if (rows.length === 0) {
                showToast('No payments to export!', 'error');
                return;
            }

            let csv = 'ID,Package,Amount,Card,Transaction ID,Status,Date\n';
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const id = cells[0]?.textContent?.trim() || '';
                const pkg = cells[1]?.textContent?.trim() || '';
                const amount = cells[2]?.textContent?.trim() || '';
                const card = cells[3]?.textContent?.trim() || '';
                const txn = cells[4]?.textContent?.trim() || '';
                const status = cells[5]?.textContent?.trim() || '';
                const date = cells[6]?.textContent?.trim() || '';
                csv += `"${id}","${pkg}","${amount}","${card}","${txn}","${status}","${date}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'payments_history.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            showToast('CSV exported successfully!', 'success');
        }

        // ============================================================
        // TOAST NOTIFICATIONS
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
        // KEYBOARD SHORTCUTS
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportCSV();
            }
            if (e.key === 'Escape') {
                clearFilters();
            }
        });

        // ============================================================
        // CONFETTI ON SUCCESS
        // ============================================================
        <?php if ($payment_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            launchConfetti();
            showToast('Payment Successful!', 'success');
        });
        <?php endif; ?>

        function launchConfetti() {
            const container = document.createElement('div');
            container.className = 'confetti-container';
            document.body.appendChild(container);
            const colors = [
                'var(--confetti-color-1)', 'var(--confetti-color-2)',
                'var(--confetti-color-3)', 'var(--confetti-color-4)',
                'var(--confetti-color-5)', 'var(--confetti-color-6)'
            ];
            for (let i = 0; i < 120; i++) {
                const piece = document.createElement('div');
                piece.className = 'confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.width = (Math.random() * 8 + 4) + 'px';
                piece.style.height = (Math.random() * 8 + 4) + 'px';
                piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
                piece.style.animationDuration = (Math.random() * 2 + 1.5) + 's';
                piece.style.animationDelay = (Math.random() * 1.2) + 's';
                piece.style.transform = `rotate(${Math.random() * 360}deg)`;
                container.appendChild(piece);
            }
            setTimeout(() => container.remove(), 4000);
        }

        // ============================================================
        // AUTO-CLOSE ALERTS
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.alert').forEach(el => {
                setTimeout(() => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-10px)';
                    el.style.transition = 'all 0.5s ease';
                    setTimeout(() => el.remove(), 500);
                }, 5000);
            });

            animateStats();
            filterHistory();

            setTimeout(() => {
                const form = document.querySelector('.form-card');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 400);
        });

        // ============================================================
        // SHARE RECEIPT
        // ============================================================
        <?php if ($payment_success && $last_transaction): ?>
        function shareReceipt() {
            const url = window.location.href.split('?')[0] + '?receipt=' + '<?php echo $last_transaction; ?>';
            if (navigator.share) {
                navigator.share({ title: 'Payment Receipt', text: 'Transaction: <?php echo $last_transaction; ?>', url: url });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Receipt link copied!', 'success');
                });
            }
        }
        <?php endif; ?>

        console.log('✅ Payments - Dashboard Design Applied!');
    </script>

</body>
</html>