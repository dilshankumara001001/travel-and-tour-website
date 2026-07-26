<?php
date_default_timezone_set('Asia/Colombo');
// 1. Start the session at the very top to track the logged-in user
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../includes/db.php");

// 2. Get the logged-in user ID from session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; 

// Fetch User Name for UI
$user_query = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_query);
$user_name = $user_data['full_name'] ?? 'Traveler';

// =======================================================
// BACKGROUND AUTOMATIC EMAIL LAYER (WITH ONE-TIME ALERT)
// =======================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/xampp2/htdocs/travel/phpmailer/src/Exception.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/PHPMailer.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/SMTP.php';

$auto_confirm_message = "";
$auto_confirm_type = "";

// Only run auto-confirm once per session (prevents repeated messages on refresh)
if (!isset($_SESSION['auto_confirm_user_done'])) {
    try {
        // Step 1: Check if this specific logged-in user has any 'pending' bookings
        $check_pending_query = "SELECT b.id AS booking_id, u.email, u.full_name, p.title AS database_package_name, b.manual_package_name 
                                FROM bookings b
                                INNER JOIN users u ON b.user_id = u.id
                                LEFT JOIN packages p ON b.package_id = p.id
                                WHERE b.user_id = $user_id AND b.status = 'pending'";
        
        $pending_result = mysqli_query($conn, $check_pending_query);
        $email_sent_count = 0;

        if ($pending_result && mysqli_num_rows($pending_result) > 0) {
            
            // Step 2: Automatically update status to 'Approved' for this user's pending records
            $update_status_query = "UPDATE bookings SET status = 'Approved' WHERE user_id = $user_id AND status = 'pending'";
            mysqli_query($conn, $update_status_query);

            // Step 3: Loop through each updated booking record and send a notification email
            while ($mail_row = mysqli_fetch_assoc($pending_result)) {
                $final_package_name = $mail_row['manual_package_name'] ?? $mail_row['database_package_name'] ?? "Custom Tour Plan";
                $user_email = $mail_row['email'];
                $mail_user_name = $mail_row['full_name'];
                $b_id = $mail_row['booking_id'];

                $mail = new PHPMailer(true);
                
                try {
                    // SMTP Server Settings Configuration
                    $mail->isSMTP();                                            
                    $mail->Host       = 'smtp.gmail.com';                     
                    $mail->SMTPAuth   = true;                                   
                    $mail->Username   = 'dileepadilshan46@gmail.com';               
                    $mail->Password   = 'futvipxtqvfukpcw';                  
                    
                    // Using SSL Port 465 for better stability on local XAMPP environments
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         
                    $mail->Port       = 465;                                    

                    $mail->setFrom('dileepadilshan46@gmail.com', 'Travel Matrix');     
                    $mail->addAddress($user_email, $mail_user_name);     

                    // Setup email content and structure
                    $mail->isHTML(true);                                        
                    $mail->Subject = 'Booking Approved Successfully! - #' . $b_id;                  
                    $mail->Body = "Greetings {$mail_user_name}, Your booking #{$b_id} for {$final_package_name} is approved.";
                    
                    $mail->send();
                    $email_sent_count++;
                    
                } catch (Exception $e) {
                    // Silently fail – we don't want to break the page
                }
            }
        }

        // Set session flag so this block runs only once
        $_SESSION['auto_confirm_user_done'] = true;

        // Prepare the alert message if any emails were sent
        if ($email_sent_count > 0) {
            $auto_confirm_message = "✅ $email_sent_count pending booking(s) were confirmed and confirmation emails were sent to you.";
            $auto_confirm_type = "success";
        }
    } catch (Exception $e) {
        // Database or other error – silently ignore
    }
}

// ============================================================
// FETCH STATS FOR CARDS (UPDATED WITH REAL-TIME)
// ============================================================
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE user_id = $user_id");
$total_data = mysqli_fetch_assoc($total_query);
$total_bookings = $total_data['total'] ?? 0;

$approved_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = 'Approved'");
$approved_data = mysqli_fetch_assoc($approved_query);
$approved_count = $approved_data['count'] ?? 0;

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = 'pending'");
$pending_data = mysqli_fetch_assoc($pending_query);
$pending_count = $pending_data['count'] ?? 0;

$cancelled_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id AND status = 'Cancelled'");
$cancelled_data = mysqli_fetch_assoc($cancelled_query);
$cancelled_count = $cancelled_data['count'] ?? 0;

$cost_query = mysqli_query($conn, "SELECT SUM(total_cost) as total FROM bookings WHERE user_id = $user_id");
$cost_data = mysqli_fetch_assoc($cost_query);
$total_cost_sum = $cost_data['total'] ?? 0;

// ============================================================
// REAL-TIME STATS: ONGOING & COMPLETED
// ============================================================
$ongoing_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings 
                                      WHERE user_id = $user_id 
                                      AND status IN ('Approved', 'Confirmed')
                                      AND CURDATE() BETWEEN booking_date AND DATE_ADD(booking_date, INTERVAL (number_of_days - 1) DAY)");
$ongoing_data = mysqli_fetch_assoc($ongoing_query);
$ongoing_count = $ongoing_data['count'] ?? 0;

$completed_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings 
                                        WHERE user_id = $user_id 
                                        AND status IN ('Approved', 'Confirmed', 'Completed')
                                        AND CURDATE() > DATE_ADD(booking_date, INTERVAL (number_of_days - 1) DAY)");
$completed_data = mysqli_fetch_assoc($completed_query);
$completed_count = $completed_data['count'] ?? 0;

// ============================================================
// FETCH RECENT BOOKINGS WITH REAL-TIME STATUS
// ============================================================
$query = "SELECT 
            b.*, 
            p.title AS database_package_name,
            COALESCE(b.number_of_days, 1) as total_days,
            CASE 
                WHEN CURDATE() BETWEEN b.booking_date AND DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                THEN 'Ongoing'
                WHEN CURDATE() > DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                THEN 'Completed'
                ELSE 'Upcoming'
            END as realtime_status,
            DATEDIFF(CURDATE(), b.booking_date) + 1 as day_number,
            DATEDIFF(DATE_ADD(b.booking_date, INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY), CURDATE()) as days_remaining
          FROM bookings b 
          LEFT JOIN packages p ON b.package_id = p.id 
          WHERE b.user_id = $user_id
          ORDER BY b.id DESC";
$result = mysqli_query($conn, $query);
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
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
    <title>My Bookings | Travel Portal</title>

    <!-- Google Fonts -->
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
            
            --status-approved: #10b981;
            --status-pending: #f59e0b;
            --status-cancelled: #ef4444;
            --status-confirmed: #3b82f6;
            
            --stats-card-bg: rgba(255, 255, 255, 0.5);
            --stats-border: rgba(0, 0, 0, 0.04);
            
            --input-bg: rgba(255, 255, 255, 0.6);
            --input-border: rgba(0, 0, 0, 0.08);
            --input-focus: rgba(255, 255, 255, 0.8);
            
            --filter-bg: rgba(255, 255, 255, 0.5);
            --history-bg: rgba(255, 255, 255, 0.3);
            
            --badge-approved-bg: rgba(16, 185, 129, 0.12);
            --badge-approved-text: #0f7b4a;
            --badge-approved-border: rgba(16, 185, 129, 0.15);
            --badge-confirmed-bg: rgba(59, 130, 246, 0.12);
            --badge-confirmed-text: #1e40af;
            --badge-confirmed-border: rgba(59, 130, 246, 0.15);
            --badge-pending-bg: rgba(245, 158, 11, 0.12);
            --badge-pending-text: #92400e;
            --badge-pending-border: rgba(245, 158, 11, 0.15);
            --badge-cancelled-bg: rgba(239, 68, 68, 0.12);
            --badge-cancelled-text: #991b1b;
            --badge-cancelled-border: rgba(239, 68, 68, 0.15);
            
            --toast-bg: #ffffff;
            --toast-border: rgba(0, 0, 0, 0.06);
            
            --confetti-color-1: #f59e0b;
            --confetti-color-2: #3b82f6;
            --confetti-color-3: #10b981;
            --confetti-color-4: #ef4444;
            --confetti-color-5: #8b5cf6;
            --confetti-color-6: #ec4899;
            
            --live-dot-color: #10b981;
            --cost-text: #0f7b4a;
            
            /* NEW Real-time status colors */
            --badge-ongoing-bg: rgba(251, 191, 36, 0.15);
            --badge-ongoing-text: #b45309;
            --badge-ongoing-border: rgba(251, 191, 36, 0.2);
            --badge-completed-bg: rgba(14, 165, 233, 0.12);
            --badge-completed-text: #0ea5e9;
            --badge-completed-border: rgba(14, 165, 233, 0.15);
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
            
            --stats-card-bg: rgba(30, 41, 59, 0.4);
            --stats-border: rgba(255, 255, 255, 0.04);
            
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.06);
            --input-focus: rgba(15, 23, 42, 0.8);
            
            --filter-bg: rgba(30, 41, 59, 0.4);
            --history-bg: rgba(30, 41, 59, 0.3);
            
            --badge-approved-bg: rgba(16, 185, 129, 0.20);
            --badge-approved-text: #34d399;
            --badge-approved-border: rgba(16, 185, 129, 0.25);
            --badge-confirmed-bg: rgba(59, 130, 246, 0.20);
            --badge-confirmed-text: #60a5fa;
            --badge-confirmed-border: rgba(59, 130, 246, 0.25);
            --badge-pending-bg: rgba(245, 158, 11, 0.20);
            --badge-pending-text: #fbbf24;
            --badge-pending-border: rgba(245, 158, 11, 0.25);
            --badge-cancelled-bg: rgba(239, 68, 68, 0.20);
            --badge-cancelled-text: #f87171;
            --badge-cancelled-border: rgba(239, 68, 68, 0.25);
            
            --toast-bg: #1e293b;
            --toast-border: rgba(255, 255, 255, 0.06);
            
            --confetti-color-1: #fbbf24;
            --confetti-color-2: #60a5fa;
            --confetti-color-3: #34d399;
            --confetti-color-4: #f87171;
            --confetti-color-5: #a78bfa;
            --confetti-color-6: #f472b6;
            
            --live-dot-color: #34d399;
            --cost-text: #34d399;
            
            /* NEW Real-time status colors - Dark */
            --badge-ongoing-bg: rgba(251, 191, 36, 0.2);
            --badge-ongoing-text: #fbbf24;
            --badge-ongoing-border: rgba(251, 191, 36, 0.3);
            --badge-completed-bg: rgba(56, 189, 248, 0.15);
            --badge-completed-text: #38bdf8;
            --badge-completed-border: rgba(56, 189, 248, 0.2);
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
        .alert-box {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(12px);
            animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            border: 1px solid transparent;
        }
        .alert-box.success {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--status-approved);
        }
        .alert-box.error {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--status-cancelled);
        }
        .alert-box i { font-size: 18px; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

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
            color: var(--badge-approved-text);
        }

        .alert-error {
            border-left: 4px solid #ef4444;
            color: var(--badge-cancelled-text);
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           STATS CARDS (UPDATED WITH ONGOING & COMPLETED)
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 14px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--stats-card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--stats-border);
            border-radius: 16px;
            padding: 16px 18px;
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
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }

        .stat-card .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }

        .stat-card .stat-icon {
            font-size: 18px;
            display: block;
            margin-bottom: 4px;
        }

        .stat-card .stat-number.green { color: var(--badge-approved-text); }
        .stat-card .stat-number.blue { color: var(--icon-color); }
        .stat-card .stat-number.yellow { color: var(--badge-pending-text); }
        .stat-card .stat-number.red { color: var(--badge-cancelled-text); }
        .stat-card .stat-number.purple { color: #8b5cf6; }
        .stat-card .stat-number.orange { color: #b45309; }
        [data-theme="dark"] .stat-card .stat-number.orange { color: #fbbf24; }
        .stat-card .stat-number.cyan { color: #0ea5e9; }
        [data-theme="dark"] .stat-card .stat-number.cyan { color: #38bdf8; }

        .stat-card.total-cost .stat-number { color: #fbbf24; font-size: 20px; }
        [data-theme="dark"] .stat-card.total-cost .stat-number { color: #fbbf24; }

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
            color: var(--badge-cancelled-text);
        }

        .btn-filter-action.clear:hover {
            background: rgba(239, 68, 68, 0.18);
        }

        .btn-filter-action.export {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.15);
            color: var(--badge-approved-text);
        }

        .btn-filter-action.export:hover {
            background: rgba(16, 185, 129, 0.18);
        }

        /* ============================================================
           BOOKINGS TABLE (UPDATED WITH PROGRESS & REAL-TIME STATUS)
        ============================================================ */
        .bookings-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            padding: 24px 20px;
        }

        .bookings-card:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
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

        th i {
            margin-right: 6px;
            color: var(--icon-color);
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-weight: 500;
            transition: color 0.3s ease, border-color 0.3s ease;
            vertical-align: middle;
        }

        tr:hover td {
            background: var(--card-border);
        }

        /* ID Cell */
        .id-cell {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: var(--icon-color);
            font-size: 14px;
            background: var(--input-bg);
            padding: 3px 10px;
            border-radius: 6px;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .id-cell:hover {
            background: var(--icon-color);
            color: #fff;
            transform: scale(1.05);
        }

        .id-cell .copy-hint {
            font-size: 10px;
            opacity: 0.5;
            margin-left: 4px;
        }

        /* Package Info */
        .pkg-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .pkg-sub {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
            transition: color 0.3s ease;
        }

        /* Date Cells */
        .date-cell {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: var(--icon-color);
            background: var(--input-bg);
            padding: 3px 12px;
            border-radius: 6px;
            display: inline-block;
            letter-spacing: 0.02em;
            transition: all 0.3s ease;
        }

        .end-date-cell {
            color: var(--badge-pending-text);
            background: rgba(245, 158, 11, 0.08);
        }

        /* Duration */
        .duration-cell {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14px;
            padding: 3px 10px;
            background: var(--input-bg);
            border-radius: 6px;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .duration-progress {
            width: 60px;
            height: 4px;
            background: var(--card-border);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }

        .duration-progress .bar {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--icon-color), #10b981);
            transition: width 1s ease;
        }

        /* Cost */
        .cost-cell {
            font-weight: 700;
            color: var(--cost-text);
            font-family: 'Courier New', monospace;
            font-size: 14px;
            background: rgba(16, 185, 129, 0.06);
            padding: 3px 14px;
            border-radius: 6px;
            display: inline-block;
            transition: all 0.3s ease;
        }

        /* Route */
        .loc-box {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
            max-width: 220px;
            word-wrap: break-word;
            transition: color 0.3s ease;
        }

        .loc-box strong {
            color: var(--text-primary);
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .btn-view-map {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--icon-color);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.08);
            padding: 5px 14px;
            border-radius: 8px;
            border: 1px solid rgba(59, 130, 246, 0.12);
            transition: all 0.25s ease;
            margin-top: 4px;
            cursor: pointer;
        }

        .btn-view-map:hover {
            background: rgba(59, 130, 246, 0.18);
            transform: translateY(-2px);
        }

        /* ============================================================
           UPDATED STATUS BADGES (includes Ongoing & Completed)
        ============================================================ */
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        .badge-approved {
            background: var(--badge-approved-bg);
            color: var(--badge-approved-text);
            border-color: var(--badge-approved-border);
        }

        .badge-confirmed {
            background: var(--badge-confirmed-bg);
            color: var(--badge-confirmed-text);
            border-color: var(--badge-confirmed-border);
        }

        .badge-pending {
            background: var(--badge-pending-bg);
            color: var(--badge-pending-text);
            border-color: var(--badge-pending-border);
            animation: pulsePending 2s infinite;
        }

        .badge-cancelled {
            background: var(--badge-cancelled-bg);
            color: var(--badge-cancelled-text);
            border-color: var(--badge-cancelled-border);
        }

        /* NEW Real-time badges */
        .badge-ongoing {
            background: var(--badge-ongoing-bg);
            color: var(--badge-ongoing-text);
            border-color: var(--badge-ongoing-border);
        }

        .badge-completed {
            background: var(--badge-completed-bg);
            color: var(--badge-completed-text);
            border-color: var(--badge-completed-border);
        }

        .badge-upcoming {
            background: rgba(148, 163, 184, 0.12);
            color: var(--text-muted);
            border-color: rgba(148, 163, 184, 0.15);
        }

        @keyframes pulsePending {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Progress Cell - NEW */
        .progress-cell {
            min-width: 120px;
        }
        .progress-bar-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .progress-bar-wrap .day-label {
            font-weight: 600;
            font-size: 11px;
            color: var(--text-primary);
            white-space: nowrap;
        }
        .progress-bar-track {
            flex: 1;
            min-width: 50px;
            height: 5px;
            background: var(--card-border);
            border-radius: 6px;
            overflow: hidden;
        }
        .progress-bar-track .fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, #0ea5e9, #10b981);
            transition: width 0.5s ease;
        }
        .progress-bar-wrap .pct {
            font-size: 9px;
            color: var(--text-muted);
            min-width: 30px;
            text-align: right;
        }
        .done-badge {
            font-size: 11px;
            color: var(--text-success);
        }
        .done-badge i {
            margin-right: 4px;
        }
        .not-started {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Action Buttons */
        .btn-action {
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            margin-right: 4px;
            display: inline-block;
            font-weight: 600;
            transition: all 0.25s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.10);
            color: var(--icon-color);
            border-color: rgba(59, 130, 246, 0.12);
        }

        .btn-edit:hover {
            background: rgba(59, 130, 246, 0.20);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.10);
            color: var(--badge-cancelled-text);
            border-color: rgba(239, 68, 68, 0.12);
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.20);
            transform: translateY(-2px);
        }

        .btn-feedback {
            background: rgba(245, 158, 11, 0.10);
            color: var(--badge-pending-text);
            border-color: rgba(245, 158, 11, 0.12);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            display: inline-block;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .btn-feedback:hover {
            background: rgba(245, 158, 11, 0.20);
            transform: translateY(-2px);
        }

        .btn-share {
            background: rgba(139, 92, 246, 0.10);
            color: #a78bfa;
            border-color: rgba(139, 92, 246, 0.12);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            display: inline-block;
            border: 1px solid transparent;
            font-weight: 600;
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .btn-share:hover {
            background: rgba(139, 92, 246, 0.20);
            transform: translateY(-2px);
        }

        /* Empty States */
        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
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

        .no-bookings {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .no-bookings i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.2;
        }

        /* ============================================================
           MAP MODAL
        ============================================================ */
        .map-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(12px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .map-modal.active {
            display: flex;
        }

        .map-modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            border: 1px solid var(--card-border);
            width: 92%;
            max-width: 880px;
            max-height: 92vh;
            overflow: hidden;
            box-shadow: 0 50px 100px rgba(0, 0, 0, 0.4);
        }

        .map-modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.3s ease;
        }

        .map-modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: color 0.3s ease;
        }

        .map-modal-header h3 i {
            color: var(--icon-color);
        }

        .map-modal-close {
            background: rgba(239, 68, 68, 0.10);
            border: none;
            color: var(--badge-cancelled-text);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-modal-close:hover {
            background: rgba(239, 68, 68, 0.20);
            transform: rotate(90deg) scale(1.05);
        }

        .map-modal-body {
            padding: 20px 24px 24px;
        }

        .map-modal-body .map-loc-info {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }

        .map-modal-body .map-loc-info span {
            background: var(--input-bg);
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .map-modal-body .map-loc-info .pickup-color i { color: var(--icon-color); }
        .map-modal-body .map-loc-info .dest-color i { color: #ef4444; }

        .map-container-modal {
            width: 100%;
            height: 380px;
            border-radius: 16px;
            overflow: hidden;
            background: var(--bg-body);
            border: 1px solid var(--card-border);
        }

        #map-modal-canvas {
            width: 100%;
            height: 100%;
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
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 20px; }
            .filter-bar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .filter-bar .filter-group { flex-direction: column; }
            .filter-bar .filter-group input,
            .filter-bar .filter-group select { width: 100%; min-width: auto; }
            .filter-bar .filter-actions { justify-content: stretch; }
            .filter-bar .filter-actions .btn-filter-action { flex: 1; justify-content: center; }
            .bookings-card { padding: 18px 14px; }
            
            /* Table to cards on mobile */
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
            .loc-box { max-width: 100%; }
            .map-container-modal { height: 280px; }
            .map-modal-body .map-loc-info { flex-direction: column; gap: 8px; }
            .toast-container { bottom: 20px; right: 16px; }
            .toast { max-width: 280px; font-size: 13px; padding: 12px 16px; }
            .header-datetime { font-size: 11px; padding: 4px 10px; }
            .header-datetime .live-dot { width: 7px; height: 7px; }
            .progress-bar-wrap { flex-wrap: wrap; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 20px; }
            .top-header .greeting-text h2 { font-size: 18px; }
            .page-header .title { font-size: 17px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card .stat-label { font-size: 10px; }
            .bookings-card { padding: 14px 10px; }
            .table-wrapper tbody tr { padding: 12px; }
            .table-wrapper tbody tr td { font-size: 12px; padding: 4px 0; }
            .btn-action { font-size: 11px; padding: 4px 8px; }
            .btn-view-map { font-size: 11px; padding: 4px 10px; }
            .header-datetime { font-size: 10px; padding: 3px 8px; }
            .header-datetime .live-dot { width: 6px; height: 6px; }
            .map-container-modal { height: 220px; }
            .no-bookings i { font-size: 40px; }
            .no-bookings p { font-size: 15px; }
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
            <a href="my_bookings.php" class="active">
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
                    Manage all your travel bookings in one place.
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
                <i class="fa-solid fa-box-archive"></i>
                My Bookings
                <span>· Real-time trip status</span>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- ===== AUTO-CONFIRM ALERT (styled like booking.php) ===== -->
        <?php if ($auto_confirm_message): ?>
            <div class="alert-box <?php echo $auto_confirm_type; ?>">
                <i class="fa-solid <?php echo $auto_confirm_type == 'success' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                <?php echo htmlspecialchars($auto_confirm_message); ?>
            </div>
        <?php endif; ?>

        <!-- ===== SUCCESS BANNER ===== -->
        <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success" id="successBanner">
                <i class="fa-solid fa-circle-check"></i> 🎉 Your booking has been updated successfully!
            </div>
        <?php endif; ?>

        <!-- ===== STATS CARDS (UPDATED) ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-calendar-check" style="color:var(--icon-color);"></i></span>
                <div class="stat-number blue" id="totalCount">0</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-check-circle" style="color:var(--badge-approved-text);"></i></span>
                <div class="stat-number green" id="approvedCount">0</div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-clock" style="color:var(--badge-pending-text);"></i></span>
                <div class="stat-number yellow" id="pendingCount">0</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-xmark-circle" style="color:var(--badge-cancelled-text);"></i></span>
                <div class="stat-number red" id="cancelledCount">0</div>
                <div class="stat-label">Cancelled</div>
            </div>
            <!-- NEW Ongoing & Completed -->
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-spinner" style="color:#f59e0b;"></i></span>
                <div class="stat-number orange" id="ongoingCount">0</div>
                <div class="stat-label">Ongoing</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><i class="fa-solid fa-flag-checkered" style="color:#0ea5e9;"></i></span>
                <div class="stat-number cyan" id="completedCount">0</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card total-cost">
                <span class="stat-icon"><i class="fa-solid fa-coins" style="color:#fbbf24;"></i></span>
                <div class="stat-number purple" id="totalCost">Rs. 0.00</div>
                <div class="stat-label">Total Cost</div>
            </div>
        </div>

        <!-- ===== FILTER BAR ===== -->
        <div class="filter-bar">
            <div class="filter-group">
                <input type="text" id="searchInput" placeholder="🔍 Search by package..." oninput="filterTable()">
                <select id="statusFilter" onchange="filterTable()">
                    <option value="all">All Status</option>
                    <option value="Approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Ongoing">Ongoing (Real-time)</option>
                    <option value="Completed">Completed (Real-time)</option>
                </select>
                <select id="sortSelect" onchange="sortTable()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="cost-high">Cost: High → Low</option>
                    <option value="cost-low">Cost: Low → High</option>
                </select>
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

        <!-- ===== BOOKINGS TABLE (UPDATED) ===== -->
        <div class="bookings-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-hashtag"></i> ID</th>
                            <th><i class="fa-solid fa-box"></i> Package</th>
                            <th><i class="fa-regular fa-calendar"></i> Travel Date</th>
                            <th><i class="fa-regular fa-clock"></i> Duration</th>
                            <th><i class="fa-regular fa-calendar-check"></i> End Date</th>
                            <th><i class="fa-solid fa-route"></i> Route</th>
                            <th><i class="fa-solid fa-ruler"></i> Distance</th>
                            <th><i class="fa-solid fa-coins"></i> Cost</th>
                            <th><i class="fa-solid fa-chart-simple"></i> Progress</th> <!-- NEW -->
                            <th><i class="fa-solid fa-circle"></i> Status</th>
                            <th><i class="fa-solid fa-gear"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsBody">
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $row): 
                                if (!empty($row['manual_package_name'])) {
                                    $display_name = $row['manual_package_name'];
                                    $sub_text = "Custom User Assigned";
                                } elseif (!empty($row['database_package_name'])) {
                                    $display_name = $row['database_package_name'];
                                    $sub_text = "Standard Catalog Plan";
                                } else {
                                    $display_name = "Custom Tour Package";
                                    $sub_text = "Package ID: #" . ($row['package_id'] ?? '0');
                                }
                                
                                $distance_val = isset($row['calculated_distance']) ? number_format((float)$row['calculated_distance'], 2) . " KM" : "0.00 KM";
                                $total_cost_val = isset($row['total_cost']) ? (float)$row['total_cost'] : 0;
                                $total_cost_display = "Rs. " . number_format($total_cost_val, 2);
                                
                                $travel_date = isset($row['booking_date']) && $row['booking_date'] != '0000-00-00' ? date('Y-m-d', strtotime($row['booking_date'])) : 'N/A';
                                $duration = isset($row['number_of_days']) ? (int)$row['number_of_days'] : 1;
                                
                                $end_date_display = 'N/A';
                                if ($travel_date != 'N/A' && $duration > 0) {
                                    $end_date_display = date('Y-m-d', strtotime($travel_date . ' + ' . ($duration - 1) . ' days'));
                                }
                                
                                $raw_pickup = $row['pickup_location'] ?? '';
                                $raw_dest = $row['destination_location'] ?? '';
                                
                                // Real-time data
                                $realtime_status = $row['realtime_status'] ?? 'Upcoming';
                                $total_days = $row['total_days'] ?? 1;
                                $day_number = $row['day_number'] ?? 1;
                                $days_remaining = $row['days_remaining'] ?? 0;
                                $progress_percent = min(($day_number / $total_days) * 100, 100);
                                
                                // Progress HTML
                                $progress_html = '';
                                if ($realtime_status == 'Ongoing') {
                                    $progress_html = '
                                        <div class="progress-bar-wrap">
                                            <span class="day-label">Day '.$day_number.'/'.$total_days.'</span>
                                            <div class="progress-bar-track">
                                                <div class="fill" style="width:'.$progress_percent.'%;"></div>
                                            </div>
                                            <span class="pct">'.round($progress_percent).'%</span>
                                        </div>
                                    ';
                                } elseif ($realtime_status == 'Completed') {
                                    $progress_html = '<span class="done-badge"><i class="fa-regular fa-circle-check"></i> Done</span>';
                                } else {
                                    $progress_html = '<span class="not-started">Not Started</span>';
                                }
                                
                                // Badge class
                                if ($realtime_status == 'Ongoing') {
                                    $badge_class = 'badge-ongoing';
                                } elseif ($realtime_status == 'Completed') {
                                    $badge_class = 'badge-completed';
                                } elseif ($realtime_status == 'Upcoming') {
                                    $badge_class = 'badge-upcoming';
                                } else {
                                    // Fallback to DB status
                                    $current_status = $row['status'] ?? 'pending';
                                    if ($current_status == 'Approved') $badge_class = 'badge-approved';
                                    else if ($current_status == 'Confirmed') $badge_class = 'badge-confirmed';
                                    else if ($current_status == 'Cancelled') $badge_class = 'badge-cancelled';
                                    else $badge_class = 'badge-pending';
                                }
                            ?>
                                <tr data-id="<?php echo $row['id']; ?>"
                                    data-status="<?php echo $realtime_status; ?>"
                                    data-cost="<?php echo $total_cost_val; ?>"
                                    data-date="<?php echo strtotime($travel_date); ?>"
                                    data-name="<?php echo htmlspecialchars($display_name); ?>">
                                    
                                    <td data-label="ID">
                                        <span class="id-cell" onclick="copyId(<?php echo $row['id']; ?>)" title="Click to copy ID">
                                            #<?php echo $row['id']; ?>
                                            <span class="copy-hint"><i class="fa-regular fa-copy"></i></span>
                                        </span>
                                    </td>
                                    <td data-label="Package">
                                        <span class="pkg-title"><?php echo htmlspecialchars($display_name); ?></span>
                                        <span class="pkg-sub"><?php echo $sub_text; ?></span>
                                    </td>
                                    <td data-label="Travel Date"><span class="date-cell"><?php echo $travel_date; ?></span></td>
                                    <td data-label="Duration">
                                        <span class="duration-cell"><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></span>
                                        <div class="duration-progress">
                                            <div class="bar" style="width: <?php echo min($duration * 20, 100); ?>%;"></div>
                                        </div>
                                    </td>
                                    <td data-label="End Date"><span class="date-cell end-date-cell"><?php echo $end_date_display; ?></span></td>
                                    <td data-label="Route">
                                        <div class="loc-box">
                                            <strong>From:</strong> <?php echo htmlspecialchars($raw_pickup ? $raw_pickup : 'N/A'); ?><br>
                                            <strong>To:</strong> <?php echo htmlspecialchars($raw_dest ? $raw_dest : 'N/A'); ?>
                                        </div>
                                        <button class="btn-view-map" onclick="openMapModal(
                                            '<?php echo htmlspecialchars($raw_pickup); ?>',
                                            '<?php echo htmlspecialchars($raw_dest); ?>',
                                            '<?php echo htmlspecialchars($display_name); ?>',
                                            <?php echo $row['id']; ?>
                                        )">
                                            <i class="fa-solid fa-map-location-dot"></i> View Map
                                        </button>
                                    </td>
                                    <td data-label="Distance" style="font-family: monospace; color: var(--text-muted);"><?php echo $distance_val; ?></td>
                                    <td data-label="Cost"><span class="cost-cell"><?php echo $total_cost_display; ?></span></td>
                                    <td data-label="Progress" class="progress-cell"><?php echo $progress_html; ?></td>
                                    <td data-label="Status">
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($realtime_status); ?>
                                            <?php if ($realtime_status == 'Ongoing' && $days_remaining > 0): ?>
                                                <span style="font-size:9px;opacity:0.7;"> (<?php echo $days_remaining; ?>d left)</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="edit_booking.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit" title="Edit Booking">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </a>
                                        <a href="delete_booking.php?id=<?php echo $row['id']; ?>" class="btn-action btn-delete" title="Delete Booking" onclick="return confirm('Are you sure you want to delete this booking?');">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                        <?php if ($realtime_status == 'Completed' || $row['status'] == 'Approved'): ?>
                                            <a href="feedback_status.php" class="btn-feedback" title="Leave feedback">
                                                <i class="fa-regular fa-star"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn-share" onclick="shareBooking(<?php echo $row['id']; ?>)" title="Share booking">
                                            <i class="fa-solid fa-share-nodes"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="11" class="no-bookings">
                                <i class="fa-regular fa-calendar-circle-plus"></i>
                                <p>No bookings found under your profile.</p>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="no-results" id="noResults">
                    <i class="fa-regular fa-face-frown"></i>
                    No bookings match your filters.
                </div>
            </div>
        </div>

        <!-- ===== FOOTER ===== -->
        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
            <span>Real-time status updated automatically</span>
        </footer>

    </main>

    <!-- ===== MAP MODAL ===== -->
    <div class="map-modal" id="mapModal">
        <div class="map-modal-content">
            <div class="map-modal-header">
                <h3><i class="fa-solid fa-map"></i> <span id="modal-map-title">Route Map</span></h3>
                <button class="map-modal-close" onclick="closeMapModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="map-modal-body">
                <div class="map-loc-info">
                    <span class="pickup-color"><i class="fa-solid fa-location-dot"></i> <strong>Pickup:</strong> <span id="modal-pickup-addr">-</span></span>
                    <span class="dest-color"><i class="fa-solid fa-flag"></i> <strong>Destination:</strong> <span id="modal-dest-addr">-</span></span>
                </div>
                <div class="map-container-modal">
                    <div id="map-modal-canvas"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TOAST CONTAINER ===== -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw"></script>
    
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
        // STATS ANIMATION (UPDATED)
        // ============================================================
        function animateStats() {
            const total = <?php echo $total_bookings; ?>;
            const approved = <?php echo $approved_count; ?>;
            const pending = <?php echo $pending_count; ?>;
            const cancelled = <?php echo $cancelled_count; ?>;
            const ongoing = <?php echo $ongoing_count; ?>;
            const completed = <?php echo $completed_count; ?>;
            const cost = <?php echo $total_cost_sum; ?>;

            animateNumber('totalCount', total);
            animateNumber('approvedCount', approved);
            animateNumber('pendingCount', pending);
            animateNumber('cancelledCount', cancelled);
            animateNumber('ongoingCount', ongoing);
            animateNumber('completedCount', completed);
            animateCurrency('totalCost', cost);
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
        // FILTER, SORT, SEARCH (UPDATED for real-time status)
        // ============================================================
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#bookingsBody tr[data-id]');
            let visible = 0;

            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const id = row.dataset.id || '';
                const rowStatus = row.dataset.status || '';
                const matchSearch = name.includes(search) || id.includes(search);
                const matchStatus = status === 'all' || rowStatus === status;
                const show = matchSearch && matchStatus;
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
        }

        function sortTable() {
            const sortVal = document.getElementById('sortSelect').value;
            const tbody = document.getElementById('bookingsBody');
            const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));

            rows.sort((a, b) => {
                const dateA = parseInt(a.dataset.date) || 0;
                const dateB = parseInt(b.dataset.date) || 0;
                const costA = parseFloat(a.dataset.cost) || 0;
                const costB = parseFloat(b.dataset.cost) || 0;

                if (sortVal === 'newest') return dateB - dateA;
                if (sortVal === 'oldest') return dateA - dateB;
                if (sortVal === 'cost-high') return costB - costA;
                if (sortVal === 'cost-low') return costA - costB;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
            filterTable();
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('sortSelect').value = 'newest';
            filterTable();
            sortTable();
            showToast('Filters reset successfully!', 'info');
        }

        // ============================================================
        // COPY ID
        // ============================================================
        function copyId(id) {
            navigator.clipboard.writeText('#' + id).then(() => {
                showToast('Booking ID #' + id + ' copied!', 'success');
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = '#' + id;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Booking ID #' + id + ' copied!', 'success');
            });
        }

        // ============================================================
        // EXPORT CSV
        // ============================================================
        function exportCSV() {
            const rows = document.querySelectorAll('#bookingsBody tr[data-id]:not([style*="display: none"])');
            if (rows.length === 0) {
                showToast('No bookings to export!', 'error');
                return;
            }

            let csv = 'ID,Package,Travel Date,Duration,End Date,Pickup,Destination,Distance,Total Cost,Status\n';
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const id = cells[0]?.textContent?.trim() || '';
                const pkg = cells[1]?.textContent?.trim().replace(/\s+/g, ' ') || '';
                const date = cells[2]?.textContent?.trim() || '';
                const duration = cells[3]?.textContent?.trim() || '';
                const endDate = cells[4]?.textContent?.trim() || '';
                const route = cells[5]?.textContent?.trim().replace(/\s+/g, ' ') || '';
                const distance = cells[6]?.textContent?.trim() || '';
                const cost = cells[7]?.textContent?.trim() || '';
                const status = cells[9]?.textContent?.trim() || '';
                csv += `"${id}","${pkg}","${date}","${duration}","${endDate}","${route}","${distance}","${cost}","${status}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'my_bookings.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            showToast('CSV exported successfully!', 'success');
        }

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
        // SHARE BOOKING
        // ============================================================
        function shareBooking(id) {
            const url = window.location.href.split('?')[0] + '?share=' + id;
            if (navigator.share) {
                navigator.share({ title: 'My Booking', url: url });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Booking link copied!', 'success');
                });
            }
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
        // CONFETTI
        // ============================================================
        <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
        document.addEventListener('DOMContentLoaded', function() {
            launchConfetti();
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
        // MAP MODAL
        // ============================================================
        var modalMap = null;
        var modalMapInitialized = false;

        function openMapModal(pickupAddr, destAddr, packageName, bookingId) {
            if (modalMap) {
                modalMap = null;
                modalMapInitialized = false;
            }
            var modal = document.getElementById('mapModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('modal-map-title').textContent = 'Booking #' + bookingId + ' - ' + packageName;
            document.getElementById('modal-pickup-addr').textContent = pickupAddr || 'Not specified';
            document.getElementById('modal-dest-addr').textContent = destAddr || 'Not specified';
            initModalMap(pickupAddr, destAddr);
        }

        function closeMapModal() {
            var modal = document.getElementById('mapModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            if (modalMap) {
                if (window.modalMarkers) {
                    window.modalMarkers.forEach(function(m) { m.setMap(null); });
                    window.modalMarkers = [];
                }
                if (window.modalPolyline) {
                    window.modalPolyline.setMap(null);
                    window.modalPolyline = null;
                }
                modalMap = null;
                modalMapInitialized = false;
            }
            var canvas = document.getElementById('map-modal-canvas');
            if (canvas) {
                while (canvas.firstChild) {
                    canvas.removeChild(canvas.firstChild);
                }
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMapModal();
        });
        document.getElementById('mapModal').addEventListener('click', function(e) {
            if (e.target === this) closeMapModal();
        });

        function parseCoordsFromString(str) {
            if (!str) return null;
            var latMatch = str.match(/lat:\s*([\d.-]+)/i);
            var lngMatch = str.match(/lng:\s*([\d.-]+)/i);
            if (latMatch && lngMatch) {
                var lat = parseFloat(latMatch[1]);
                var lng = parseFloat(lngMatch[1]);
                if (!isNaN(lat) && !isNaN(lng)) {
                    return new google.maps.LatLng(lat, lng);
                }
            }
            var parts = str.split(',').map(s => s.trim());
            if (parts.length >= 2) {
                var lat = parseFloat(parts[0]);
                var lng = parseFloat(parts[1]);
                if (!isNaN(lat) && !isNaN(lng)) {
                    return new google.maps.LatLng(lat, lng);
                }
            }
            return null;
        }

        var geocoder;

        function initModalMap(pickupAddr, destAddr) {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                console.error('Google Maps not loaded');
                return;
            }
            if (modalMap) { modalMap = null; }
            geocoder = new google.maps.Geocoder();
            var mapCanvas = document.getElementById('map-modal-canvas');
            while (mapCanvas.firstChild) { mapCanvas.removeChild(mapCanvas.firstChild); }
            modalMap = new google.maps.Map(mapCanvas, {
                center: { lat: 7.8731, lng: 80.7718 },
                zoom: 8,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            });
            modalMapInitialized = true;
            window.modalMarkers = [];
            window.modalPolyline = null;
            if (pickupAddr && destAddr) { geocodeAndShowRoute(pickupAddr, destAddr); }
        }

        function geocodeAndShowRoute(pickupAddr, destAddr) {
            if (!geocoder || !modalMap) return;
            if (window.modalMarkers) { window.modalMarkers.forEach(function(m) { m.setMap(null); }); }
            if (window.modalPolyline) { window.modalPolyline.setMap(null); }
            window.modalMarkers = [];
            window.modalPolyline = null;
            var pickupCoords = null;
            var destCoords = null;
            function checkAndShowRoute() {
                if (pickupCoords && destCoords) { showRouteOnModal(pickupCoords, destCoords); }
            }
            function tryParseFallback(addr, callback) {
                var coords = parseCoordsFromString(addr);
                if (coords) { callback(coords); return true; }
                return false;
            }
            geocoder.geocode({ address: pickupAddr }, function(results, status) {
                if (status === 'OK' && results[0]) { pickupCoords = results[0].geometry.location; }
                else { if (!tryParseFallback(pickupAddr, function(coords) { pickupCoords = coords; })) { console.warn('Could not parse pickup address:', pickupAddr); } }
                checkAndShowRoute();
            });
            geocoder.geocode({ address: destAddr }, function(results, status) {
                if (status === 'OK' && results[0]) { destCoords = results[0].geometry.location; }
                else { if (!tryParseFallback(destAddr, function(coords) { destCoords = coords; })) { console.warn('Could not parse destination address:', destAddr); } }
                checkAndShowRoute();
            });
            setTimeout(function() {
                if (pickupCoords && destCoords) { showRouteOnModal(pickupCoords, destCoords); }
                else {
                    var pCoords = parseCoordsFromString(pickupAddr);
                    var dCoords = parseCoordsFromString(destAddr);
                    if (pCoords && dCoords) { pickupCoords = pCoords; destCoords = dCoords; showRouteOnModal(pickupCoords, destCoords); }
                    else { modalMap.setCenter({ lat: 7.8731, lng: 80.7718 }); modalMap.setZoom(7); }
                }
            }, 3000);
        }

        function showRouteOnModal(pickupPos, destPos) {
            if (!modalMap) return;
            var marker1 = new google.maps.Marker({
                position: pickupPos, map: modalMap, title: 'Pickup Location',
                icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' },
                label: { text: 'P', color: 'white', fontWeight: 'bold' }
            });
            var marker2 = new google.maps.Marker({
                position: destPos, map: modalMap, title: 'Destination',
                icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' },
                label: { text: 'D', color: 'white', fontWeight: 'bold' }
            });
            window.modalMarkers = [marker1, marker2];
            var path = new google.maps.Polyline({
                path: [pickupPos, destPos], geodesic: true,
                strokeColor: '#3b82f6', strokeOpacity: 0.9, strokeWeight: 4
            });
            path.setMap(modalMap);
            window.modalPolyline = path;
            var bounds = new google.maps.LatLngBounds();
            bounds.extend(pickupPos);
            bounds.extend(destPos);
            modalMap.fitBounds(bounds);
            var zoom = modalMap.getZoom();
            if (zoom > 12) modalMap.setZoom(Math.min(zoom, 15));
        }

        // ============================================================
        // DOM READY
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.alert, .success-banner').forEach(el => {
                setTimeout(() => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-10px)';
                    el.style.transition = 'all 0.5s ease';
                    setTimeout(() => el.remove(), 500);
                }, 5000);
            });

            animateStats();
            filterTable();
        });

        // Auto refresh every 60 seconds to keep real-time status updated
        setInterval(function() {
            location.reload();
        }, 60000);

        console.log('✅ My Bookings - Real-time Ongoing/Completed Applied!');
    </script>

</body>
</html>