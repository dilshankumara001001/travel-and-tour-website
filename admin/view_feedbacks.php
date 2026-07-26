<?php
session_start();
include("../includes/db.php");
require '../vendor/autoload.php'; // PHPMailer load

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!$conn) {
    die("Connection Failed.");
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ============================================================
// DETECT DATE COLUMN for stats
// ============================================================
$date_col = 'created_at';
$check_cols = ['created_at', 'date', 'submitted_at'];
foreach ($check_cols as $col) {
    $check = $conn->query("SHOW COLUMNS FROM contact_feedbacks LIKE '$col'");
    if ($check && $check->num_rows > 0) {
        $date_col = $col;
        break;
    }
}

// ============================================================
// UPDATE REPLY LOGIC & EMAIL
// ============================================================
if (isset($_POST['update_reply'])) {
    $fid = (int) $_POST['feedback_id'];
    $reply = mysqli_real_escape_string($conn, $_POST['reply_text']);

    // 1. Database update
    $conn->query("UPDATE contact_feedbacks SET admin_reply = '$reply' WHERE id = '$fid'");

    // 2. Get user email and subject
    $user_query = $conn->query("SELECT email, subject FROM contact_feedbacks WHERE id = '$fid'");
    $user_data = $user_query->fetch_assoc();
    $user_email = $user_data['email'];

    // 3. Send email if reply not empty
    if ($user_email && !empty($reply)) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dileepadilshan46@gmail.com';
            $mail->Password   = 'futvipxtqvfukpcw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('dileepadilshan46@gmail.com', 'Admin Support');
            $mail->addAddress($user_email);
            $mail->isHTML(true);
            $mail->Subject = 'Reply to your feedback: ' . $user_data['subject'];
            $mail->Body    = "Hello,<br><br>Admin has replied to your feedback:<br><b>" . nl2br($reply) . "</b><br><br>Thank you.";

            $mail->send();
        } catch (Exception $e) {
            // Silent fail
        }
    }

    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

// ============================================================
// DELETE LOGIC
// ============================================================
if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM contact_feedbacks WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

// ============================================================
// STATS
// ============================================================
$total = $conn->query("SELECT COUNT(*) as cnt FROM contact_feedbacks")->fetch_assoc()['cnt'];
$today = 0;
$today_query = $conn->query("SELECT COUNT(*) as cnt FROM contact_feedbacks WHERE DATE($date_col) = CURDATE()");
if ($today_query) {
    $today = $today_query->fetch_assoc()['cnt'];
}
$stats = ['total' => $total, 'today' => $today];

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
    <title>View Feedback | Admin Dashboard</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT/DARK (DASHBOARD STYLE)
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
           NAVBAR (DASHBOARD STYLE)
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
           STATS GRID (KPI CARDS)
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
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
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--gradient-main);
            opacity: 0.6;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(14, 165, 233, 0.20);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }
        .stat-card .stat-label {
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
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            line-height: 1.2;
            transition: color var(--transition);
        }
        .stat-card .stat-icon {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 22px;
            opacity: 0.08;
            color: var(--text-accent);
            transition: all 0.4s ease;
        }
        .stat-card:hover .stat-icon {
            opacity: 0.15;
            transform: scale(1.1) rotate(5deg);
        }
        .stat-card.green .stat-icon { color: var(--text-success); }
        .stat-card.blue .stat-icon { color: var(--text-accent); }

        /* ============================================
           TABLE (GLASSMORPHISM)
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

        .table-scroll {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
        }
        thead th i {
            margin-right: 6px;
            color: var(--text-accent);
        }
        tbody td {
            padding: 12px 12px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
            transition: color var(--transition);
            vertical-align: middle;
        }
        tbody tr:hover td {
            background: var(--bg-input);
        }

        .user-cell .name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .user-cell .email {
            font-size: 12px;
            color: var(--text-muted);
        }
        .msg-cell .subject {
            font-weight: 600;
            color: var(--text-primary);
        }
        .msg-cell .msg-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.5;
        }

        .reply-form {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .reply-form textarea {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-input);
            border: 1.5px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            resize: vertical;
            min-height: 50px;
            transition: border-color 0.3s ease;
            outline: none;
        }
        .reply-form textarea:focus {
            border-color: var(--text-accent);
            box-shadow: 0 0 30px rgba(14,165,233,0.06);
        }

        .btn {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 8px;
        }
        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .action-cell {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-state p {
            font-size: 16px;
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
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .app-header {
                padding: 14px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .app-header .brand h1 { font-size: 20px; }
            .stat-card .stat-value { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-icon { font-size: 20px; top: 12px; right: 12px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .welcome-section .welcome-stats { width: 100%; justify-content: space-around; }
            .table-section .table-header { flex-direction: column; align-items: stretch; }
            thead th, tbody td { padding: 8px 10px; font-size: 12px; }
            .reply-form textarea { font-size: 12px; min-height: 40px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .stat-card .stat-value { font-size: 20px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
            thead th, tbody td { padding: 6px 8px; font-size: 11px; }
            .user-cell .email { font-size: 10px; }
            .msg-cell .msg-text { font-size: 11px; }
            .action-cell { flex-direction: column; gap: 4px; }
            .btn { font-size: 10px; padding: 4px 10px; }
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
    <div class="loader-text">Loading Feedback</div>
</div>

<!-- Custom Cursor -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- ============================================================ -->
<!-- NAVBAR (DASHBOARD STYLE) -->
<!-- ============================================================ -->
<nav class="navbar">
    <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
    <div class="nav-group">
        <div class="nav-links">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php" class="active"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <!-- ===== VEHICLE MANAGER BUTTON ===== -->
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
            <div class="logo-icon"><i class="fa-solid fa-comment"></i></div>
            <div>
                <h1 id="typing-heading">View Feedback</h1>
                <span>Customer Feedback Management &bull; v4.0</span>
            </div>
        </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fa-regular fa-hand-peace"></i> Welcome Back, <?php echo htmlspecialchars($display_admin); ?></h2>
            <p><i class="fa-regular fa-circle-check"></i> Manage customer feedback and replies.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="num" data-count="<?php echo $stats['total']; ?>">0</div>
                <div class="label">Total</div>
            </div>
            <div class="stat-item">
                <div class="num" data-count="<?php echo $stats['today']; ?>">0</div>
                <div class="label">Today</div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fa-solid fa-message"></i></div>
            <div class="stat-label"><i class="fa-regular fa-user" style="color:var(--text-accent);"></i> Total Feedback</div>
            <div class="stat-value" data-count="<?php echo $stats['total']; ?>">0</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fa-regular fa-calendar-day"></i></div>
            <div class="stat-label"><i class="fa-regular fa-circle-check" style="color:var(--text-success);"></i> Today</div>
            <div class="stat-value" data-count="<?php echo $stats['today']; ?>">0</div>
        </div>
    </div>

    <!-- Feedback Table -->
    <div class="table-section">
        <div class="table-header">
            <h4><i class="fa-regular fa-comments"></i> Customer Feedback</h4>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-regular fa-user"></i> User</th>
                        <th><i class="fa-regular fa-comment"></i> Message</th>
                        <th><i class="fa-regular fa-reply"></i> Admin Reply</th>
                        <th><i class="fa-regular fa-trash-can"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = mysqli_query($conn, "SELECT * FROM contact_feedbacks ORDER BY id DESC");
                    if (mysqli_num_rows($res) > 0) {
                        while ($row = mysqli_fetch_assoc($res)) {
                            $name = htmlspecialchars($row['name'] ?? 'Guest');
                            $email = htmlspecialchars($row['email'] ?? '');
                            $subject = htmlspecialchars($row['subject'] ?? '');
                            $message = nl2br(htmlspecialchars($row['message'] ?? ''));
                            $reply_text = htmlspecialchars($row['admin_reply'] ?? '');
                            $fid = $row['id'];
                            ?>
                            <tr>
                                <td class="user-cell">
                                    <div class="name"><?php echo $name; ?></div>
                                    <div class="email"><i class="fa-regular fa-envelope"></i> <?php echo $email; ?></div>
                                </td>
                                <td class="msg-cell">
                                    <div class="subject"><?php echo $subject; ?></div>
                                    <div class="msg-text"><?php echo $message; ?></div>
                                </td>
                                <td>
                                    <form method="POST" class="reply-form">
                                        <input type="hidden" name="feedback_id" value="<?php echo $fid; ?>">
                                        <textarea name="reply_text" placeholder="Type your reply..."><?php echo $reply_text; ?></textarea>
                                        <button type="submit" name="update_reply" class="btn btn-primary">
                                            <i class="fa-regular fa-paper-plane"></i> Save & Email
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <a href="?delete_id=<?php echo $fid; ?>" class="btn btn-danger" onclick="return confirm('Permanently delete this feedback?')">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="4"><div class="empty-state"><i class="fa-regular fa-inbox"></i><p>No feedback messages found.</p></div></td></tr>';
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
        document.querySelectorAll('.stat-value[data-count]').forEach(el => {
            const target = parseFloat(el.dataset.count);
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
                el.textContent = Math.floor(current).toLocaleString();
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