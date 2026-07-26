<?php
date_default_timezone_set('Asia/Colombo');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';

include("../includes/db.php");

// ============================================================
// HANDLE BOOKING FORM SUBMISSION
// ============================================================
$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    
    $pid = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $manual_package_name = isset($_POST['manual_package_name']) ? mysqli_real_escape_string($conn, trim($_POST['manual_package_name'])) : '';
    $pickup = isset($_POST['pickup_location']) ? mysqli_real_escape_string($conn, trim($_POST['pickup_location'])) : '';
    $destination = isset($_POST['destination_location']) ? mysqli_real_escape_string($conn, trim($_POST['destination_location'])) : '';
    $distance = isset($_POST['calculated_distance']) ? floatval($_POST['calculated_distance']) : 0;
    $total_cost = isset($_POST['total_cost']) ? floatval($_POST['total_cost']) : 0;
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 1;
    $number_of_days = isset($_POST['number_of_days']) ? intval($_POST['number_of_days']) : 1;
    $number_of_members = isset($_POST['number_of_members']) ? intval($_POST['number_of_members']) : 1;
    $travel_date = isset($_POST['travel_date']) ? mysqli_real_escape_string($conn, trim($_POST['travel_date'])) : '';
    
    $pickup_lat = isset($_POST['pickup_lat']) ? floatval($_POST['pickup_lat']) : 0;
    $pickup_lng = isset($_POST['pickup_lng']) ? floatval($_POST['pickup_lng']) : 0;
    $dest_lat = isset($_POST['dest_lat']) ? floatval($_POST['dest_lat']) : 0;
    $dest_lng = isset($_POST['dest_lng']) ? floatval($_POST['dest_lng']) : 0;
    
    // Get vehicle capacity
    $vehicle_check_query = "SELECT capacity FROM vehicles WHERE id = $vehicle_id";
    $vehicle_check_result = mysqli_query($conn, $vehicle_check_query);
    $vehicle_data = mysqli_fetch_assoc($vehicle_check_result);
    $vehicle_capacity = $vehicle_data['capacity'] ?? 10;
    
    if (empty($travel_date)) {
        $error_msg = "Please select a travel date.";
    } elseif ($number_of_members > $vehicle_capacity) {
        $error_msg = "Members count ($number_of_members) exceeds vehicle capacity ($vehicle_capacity)!";
    } elseif (empty($pickup) || empty($destination)) {
        $error_msg = "Please select both pickup and destination locations on the map!";
    } else {
        // --------------------------------------------
        // CHECK VEHICLE AVAILABILITY FOR THE SELECTED DATE RANGE
        // --------------------------------------------
        $start_date = $travel_date;
        $end_date = date('Y-m-d', strtotime($travel_date . ' + ' . ($number_of_days - 1) . ' days'));
        
        // Query to check if this vehicle is already booked for any overlapping dates
        $availability_query = "
            SELECT COUNT(*) as conflict_count 
            FROM bookings 
            WHERE vehicle_id = $vehicle_id 
              AND status IN ('Approved', 'Confirmed', 'Completed')
              AND (
                  (booking_date <= '$end_date' AND DATE_ADD(booking_date, INTERVAL (number_of_days - 1) DAY) >= '$start_date')
              )
        ";
        $avail_result = mysqli_query($conn, $availability_query);
        $avail_row = mysqli_fetch_assoc($avail_result);
        $conflict_count = $avail_row['conflict_count'] ?? 0;
        
        if ($conflict_count > 0) {
            $error_msg = "⚠️ The selected vehicle is already booked for the requested date range ($start_date to $end_date). Please choose another vehicle or different dates.";
        } else {
            // Proceed with booking
            $insert_query = "INSERT INTO bookings (
                                user_id, package_id, package_name, manual_package_name, 
                                vehicle_id, booking_date, status, pickup_location, 
                                destination_location, calculated_distance, total_cost, number_of_days
                            ) VALUES (
                                '$user_id', '$pid', '$manual_package_name', '$manual_package_name', 
                                '$vehicle_id', '$travel_date', 'Approved', '$pickup', '$destination', 
                                '$distance', '$total_cost', '$number_of_days'
                            )";
            
            if (mysqli_query($conn, $insert_query)) {
                $booking_id = mysqli_insert_id($conn);
                $log_text = mysqli_real_escape_string($conn, "User $user_id booked package '$manual_package_name' for $number_of_days days on $travel_date ($number_of_members members) (#$booking_id)");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, action, log_date) VALUES ('$user_id', '$log_text', NOW())");
                
                // Send email notification
                $user_query = mysqli_query($conn, "SELECT email FROM users WHERE id = $user_id");
                $user_data = mysqli_fetch_assoc($user_query);
                $user_email = $user_data['email'] ?? '';
                
                if (!empty($user_email)) {
                    require_once '../vendor/autoload.php'; 
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'dileepadilshan46@gmail.com';   
                        $mail->Password   = 'futvipxtqvfukpcw';      
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                    
                        $mail->setFrom('your_email@gmail.com', 'Travel & Tour');
                        $mail->addAddress($user_email);
                    
                        $mail->isHTML(true);
                        $mail->Subject = 'Booking Confirmation - #' . $booking_id;
                        $mail->Body    = "Thank you for booking a custom trip!<br><br>
                                          <strong>Package:</strong> $manual_package_name<br>
                                          <strong>Travel Date:</strong> $travel_date<br>
                                          <strong>Days:</strong> $number_of_days<br>
                                          <strong>Members:</strong> $number_of_members<br>
                                          <strong>Total Cost:</strong> Rs. " . number_format($total_cost, 2) . "<br><br>
                                          We will contact you shortly.";
                        $mail->AltBody = "Thank you for booking! Package: $manual_package_name, Date: $travel_date, Days: $number_of_days, Members: $number_of_members, Total: Rs. $total_cost";
                    
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $e->getMessage());
                    }
                }
                
                header("Location: my_bookings.php?success=1");
                exit();
            } else {
                $error_msg = "Database Error: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================================
// FETCH VEHICLES
// ============================================================
$vehicles_list = [];
$v_result = mysqli_query($conn, "SELECT * FROM vehicles");
while ($v = mysqli_fetch_assoc($v_result)) {
    if (!isset($v['petrol_rate'])) $v['petrol_rate'] = 45.00;
    $vehicles_list[] = $v;
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
    <title>Plan a Trip | Travel Portal</title>

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
            
            --filter-bg: rgba(255, 255, 255, 0.5);
            
            --toast-bg: #ffffff;
            --toast-border: rgba(0, 0, 0, 0.06);
            
            --confetti-color-1: #f59e0b;
            --confetti-color-2: #3b82f6;
            --confetti-color-3: #10b981;
            --confetti-color-4: #ef4444;
            --confetti-color-5: #8b5cf6;
            --confetti-color-6: #ec4899;
            
            --live-dot-color: #10b981;
            
            --text-green: #0f7b4a;
            --text-blue: #1e40af;
            --text-amber: #92400e;
            --text-purple: #5b21b6;
            --node-card-bg: rgba(255, 255, 255, 0.4);
            --node-card-border: rgba(0, 0, 0, 0.04);
            --telemetry-bg: rgba(255, 255, 255, 0.4);
            --location-status-error-bg: rgba(239, 68, 68, 0.08);
            --location-status-error-border: rgba(239, 68, 68, 0.12);
            --location-status-error-text: #991b1b;
            --location-status-success-bg: rgba(16, 185, 129, 0.08);
            --location-status-success-border: rgba(16, 185, 129, 0.12);
            --location-status-success-text: #0f7b4a;
            --location-status-info-bg: rgba(59, 130, 246, 0.08);
            --location-status-info-border: rgba(59, 130, 246, 0.12);
            --location-status-info-text: #1e40af;
            --alert-error-bg: rgba(239, 68, 68, 0.08);
            --alert-error-border: rgba(239, 68, 68, 0.12);
            --alert-error-text: #991b1b;
            --alert-success-bg: rgba(16, 185, 129, 0.08);
            --alert-success-border: rgba(16, 185, 129, 0.12);
            --alert-success-text: #0f7b4a;
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
            
            --filter-bg: rgba(30, 41, 59, 0.4);
            
            --toast-bg: #1e293b;
            --toast-border: rgba(255, 255, 255, 0.06);
            
            --confetti-color-1: #fbbf24;
            --confetti-color-2: #60a5fa;
            --confetti-color-3: #34d399;
            --confetti-color-4: #f87171;
            --confetti-color-5: #a78bfa;
            --confetti-color-6: #f472b6;
            
            --live-dot-color: #34d399;
            
            --text-green: #34d399;
            --text-blue: #60a5fa;
            --text-amber: #fbbf24;
            --text-purple: #c084fc;
            --node-card-bg: rgba(30, 41, 59, 0.4);
            --node-card-border: rgba(255, 255, 255, 0.04);
            --telemetry-bg: rgba(30, 41, 59, 0.4);
            --location-status-error-bg: rgba(239, 68, 68, 0.10);
            --location-status-error-border: rgba(239, 68, 68, 0.15);
            --location-status-error-text: #f87171;
            --location-status-success-bg: rgba(16, 185, 129, 0.10);
            --location-status-success-border: rgba(16, 185, 129, 0.15);
            --location-status-success-text: #34d399;
            --location-status-info-bg: rgba(59, 130, 246, 0.10);
            --location-status-info-border: rgba(59, 130, 246, 0.15);
            --location-status-info-text: #60a5fa;
            --alert-error-bg: rgba(239, 68, 68, 0.10);
            --alert-error-border: rgba(239, 68, 68, 0.15);
            --alert-error-text: #f87171;
            --alert-success-bg: rgba(16, 185, 129, 0.10);
            --alert-success-border: rgba(16, 185, 129, 0.15);
            --alert-success-text: #34d399;
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

        .alert-error {
            border-left: 4px solid #ef4444;
            color: var(--alert-error-text);
        }

        .alert-success {
            border-left: 4px solid #10b981;
            color: var(--alert-success-text);
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           MAP & BOOKING PANEL
        ============================================================ */
        .map-wrapper-panel {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            padding: 28px;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .map-wrapper-panel:hover {
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .map-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .map-header-bar .selected-info {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .map-header-bar .selected-info strong {
            color: var(--icon-color);
        }

        .btn-clear {
            background: rgba(239, 68, 68, 0.08);
            color: var(--location-status-error-text);
            border: 1px solid rgba(239, 68, 68, 0.12);
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-clear:hover {
            background: rgba(239, 68, 68, 0.15);
        }

        .btn-refresh {
            background: rgba(59, 130, 246, 0.08);
            color: var(--icon-color);
            border: 1px solid rgba(59, 130, 246, 0.12);
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-refresh:hover {
            background: rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }

        .map-canvas-render {
            width: 100%;
            height: 380px;
            border-radius: 16px;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            margin-bottom: 20px;
            transition: background 0.3s ease, border-color 0.3s ease;
            overflow: hidden;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        .node-display-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .node-card {
            background: var(--node-card-bg);
            border: 1px solid var(--node-card-border);
            padding: 14px 16px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: all 0.3s ease;
        }

        .node-card label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .node-card .loc-text {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .location-status {
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .location-status.error {
            background: var(--location-status-error-bg);
            border: 1px solid var(--location-status-error-border);
            color: var(--location-status-error-text);
        }

        .location-status.success {
            background: var(--location-status-success-bg);
            border: 1px solid var(--location-status-success-border);
            color: var(--location-status-success-text);
        }

        .location-status.info {
            background: var(--location-status-info-bg);
            border: 1px solid var(--location-status-info-border);
            color: var(--location-status-info-text);
        }

        /* Form */
        .form-flex-layout {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .family-counter-node {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .family-counter-node label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .full-width-node {
            grid-column: span 3;
        }

        .input-member-count,
        .select-vehicle-control,
        .input-text-control,
        .input-date-control {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            width: 100%;
            outline: none;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .input-member-count:focus,
        .select-vehicle-control:focus,
        .input-text-control:focus,
        .input-date-control:focus {
            border-color: var(--icon-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
            background: var(--input-focus);
        }

        .input-date-control[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            cursor: pointer;
        }

        [data-theme="dark"] .input-date-control[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        /* Telemetry */
        .telemetry-readout {
            background: var(--telemetry-bg);
            border: 1px solid var(--card-border);
            padding: 18px 20px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .telemetry-row {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 6px;
            width: 100%;
            transition: color 0.3s ease, border-color 0.3s ease;
        }

        .telemetry-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .breakdown-label {
            font-size: 13px;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .breakdown-value {
            font-weight: 600;
            text-align: right;
        }

        .text-green {
            color: var(--text-green);
            font-weight: 700;
            font-size: 18px !important;
        }

        .text-blue {
            color: var(--text-blue);
            font-weight: 600;
        }

        .text-amber {
            color: var(--text-amber);
            font-weight: 600;
        }

        .text-purple {
            color: var(--text-purple);
            font-weight: 600;
        }

        .calculation-detail {
            margin-top: 6px;
            font-size: 11px;
            color: var(--text-muted);
            text-align: center;
            border-top: 1px solid var(--card-border);
            padding-top: 6px;
            transition: color 0.3s ease, border-color 0.3s ease;
        }

        .btn-confirm {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.15);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(16, 185, 129, 0.25);
        }

        .btn-confirm:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
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
            .map-wrapper-panel { padding: 18px 16px; }
            .map-canvas-render { height: 280px; }
            .node-display-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-flex-layout { grid-template-columns: 1fr; }
            .full-width-node { grid-column: span 1; }
            .map-header-bar { flex-direction: column; align-items: stretch; }
            .btn-confirm { width: 100%; }
            .header-datetime { font-size: 11px; padding: 4px 10px; }
            .header-datetime .live-dot { width: 7px; height: 7px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 20px; }
            .top-header .greeting-text h2 { font-size: 18px; }
            .page-header .title { font-size: 17px; }
            .map-wrapper-panel { padding: 14px 12px; }
            .map-canvas-render { height: 220px; }
            .node-card { padding: 10px 12px; }
            .node-card .loc-text { font-size: 12px; }
            .telemetry-readout { padding: 14px 14px; }
            .telemetry-row { font-size: 12px; }
            .btn-confirm { font-size: 14px; padding: 12px; }
            .header-datetime { font-size: 10px; padding: 3px 8px; }
            .header-datetime .live-dot { width: 6px; height: 6px; }
            .btn-back { font-size: 12px; padding: 6px 14px; }
            .form-flex-layout { gap: 12px; }
            .input-member-count, .select-vehicle-control, .input-text-control, .input-date-control { font-size: 13px; padding: 8px 12px; }
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
            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i> Payments
            </a>
            <a href="plan_a_trip.php" class="active">
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
                    Plan your custom trip with our interactive map.
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
                <i class="fa-solid fa-compass"></i>
                Plan a Trip
                <span>· Create your custom journey</span>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- ===== ALERTS ===== -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <!-- ===== MAP & BOOKING PANEL ===== -->
        <div class="map-wrapper-panel">

            <div class="map-header-bar">
                <span class="selected-info">
                    <i class="fa-solid fa-check-circle" style="color: #10b981;"></i>
                    Booking: <strong id="selected-package-name">Custom Tour Plan</strong>
                </span>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-clear" onclick="clearMapMarkers()">
                        <i class="fa-solid fa-trash-can"></i> Clear Markers
                    </button>
                    <button type="button" class="btn-refresh" onclick="resetAll()">
                        <i class="fa-solid fa-rotate"></i> Reset
                    </button>
                </div>
            </div>

            <div class="map-canvas-render" id="map-container">
                <div id="map"></div>
            </div>

            <div class="node-display-grid">
                <div class="node-card" style="border-left: 4px solid #3b82f6;">
                    <label><i class="fa-solid fa-location-dot" style="color:#3b82f6;"></i> Pickup Location</label>
                    <div class="loc-text" id="pickup-display">Not Selected</div>
                </div>
                <div class="node-card" style="border-left: 4px solid #ef4444;">
                    <label><i class="fa-solid fa-flag" style="color:#ef4444;"></i> Destination Location</label>
                    <div class="loc-text" id="dest-display">Not Selected</div>
                </div>
            </div>

            <div class="location-status info" id="location-status">
                <i class="fa-solid fa-circle-info"></i>
                <span id="location-status-text">Click on the map to add pickup and destination locations</span>
            </div>

            <form method="POST" action="plan_a_trip.php" onsubmit="return validateBeforeSubmit()">

                <input type="hidden" name="package_id" id="form-package-id" value="0">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <div class="form-flex-layout">
                    <div class="family-counter-node full-width-node">
                        <label><i class="fa-regular fa-pen-to-square"></i> Trip Name</label>
                        <input type="text" name="manual_package_name" id="package-name"
                               class="input-text-control" value="Custom Tour Plan" required>
                    </div>

                    <div class="family-counter-node">
                        <label><i class="fa-regular fa-user"></i> Members <span id="capacity-hint" style="font-weight:400;color:var(--text-muted);font-size:11px;">(Max: 10)</span></label>
                        <input type="number" id="member-input" class="input-member-count"
                               value="1" min="1" max="10" oninput="handleMemberChange();">
                        <input type="hidden" name="number_of_members" id="members-hidden" value="1">
                    </div>

                    <div class="family-counter-node">
                        <label><i class="fa-regular fa-calendar"></i> Days</label>
                        <input type="number" id="days-input" class="input-member-count"
                               value="1" min="1" oninput="handleDaysChange();">
                    </div>

                    <div class="family-counter-node">
                        <label><i class="fa-solid fa-car"></i> Select Vehicle</label>
                        <select name="vehicle_id" id="vehicle-select" class="select-vehicle-control" onchange="handleVehicleChange();">
                            <?php foreach ($vehicles_list as $v_row) {
                                $selected = ($v_row['id'] == 1) ? 'selected' : '';
                                $petrol = isset($v_row['petrol_rate']) ? $v_row['petrol_rate'] : 45;
                                echo "<option value='".$v_row['id']."' data-rate='".$v_row['price_per_km']."' data-petrol='".$petrol."' data-capacity='".$v_row['capacity']."' $selected>"
                                    .$v_row['vehicle_name']." (Rs. ".number_format($v_row['price_per_km'], 2)."/KM, Petrol: Rs.".$petrol."/KM, Max: ".$v_row['capacity'].")</option>";
                            } ?>
                        </select>
                    </div>
                </div>

                <!-- Travel Date Picker -->
                <div class="form-flex-layout" style="margin-top: -5px;">
                    <div class="family-counter-node full-width-node">
                        <label><i class="fa-regular fa-calendar-plus"></i> Travel Date</label>
                        <input type="date" name="travel_date" id="travel-date" class="input-date-control" required>
                    </div>
                </div>

                <div class="telemetry-readout">
                    <div class="telemetry-row">
                        <b>📏 Route Distance (One Way)</b>
                        <b class="text-blue" id="distance-display">0.00 KM</b>
                    </div>
                    <div class="telemetry-row" id="package-cost-row">
                        <span class="breakdown-label"><i class="fa-regular fa-suitcase" style="color:#3b82f6;"></i> Package Cost</span>
                        <span class="breakdown-value text-blue" id="package-cost-display">Rs. 0.00</span>
                    </div>
                    <div class="telemetry-row" id="vehicle-cost-row">
                        <span class="breakdown-label"><i class="fa-solid fa-car" style="color:#a855f7;"></i> Vehicle Cost</span>
                        <span class="breakdown-value text-purple" id="vehicle-cost-display">Rs. 0.00</span>
                    </div>
                    <div class="telemetry-row" id="petrol-cost-row">
                        <span class="breakdown-label"><i class="fa-solid fa-gas-pump" style="color:#f59e0b;"></i> Petrol Cost</span>
                        <span class="breakdown-value text-amber" id="petrol-cost-display">Rs. 0.00</span>
                    </div>
                    <div class="telemetry-row" style="border-bottom: 2px solid var(--card-border);">
                        <span class="breakdown-label" style="font-weight:700;color:var(--text-secondary);">👤 Per Person</span>
                        <span class="breakdown-value" style="font-weight:700;color:var(--text-primary);font-size:16px;" id="subtotal-display">Rs. 0.00</span>
                    </div>
                    <div class="telemetry-row" style="border-bottom: none; padding-bottom: 0;">
                        <b style="font-size:16px;color:var(--text-primary);">💳 Total Grand Charge</b>
                        <b class="text-green" style="font-size:20px;" id="total-display">Rs. 0.00</b>
                    </div>

                    <div class="calculation-detail">
                        <i class="fa-regular fa-circle-info"></i>
                        <span id="calculation-detail">📍 Select 2 locations on the map</span>
                    </div>
                </div>

                <input type="hidden" name="pickup_location" id="pickup-value">
                <input type="hidden" name="destination_location" id="dest-value">
                <input type="hidden" name="calculated_distance" id="distance-value" value="0">
                <input type="hidden" name="total_cost" id="cost-value" value="0">
                <input type="hidden" name="number_of_days" id="days-value" value="1">

                <input type="hidden" name="pickup_lat" id="pickup-lat" value="0">
                <input type="hidden" name="pickup_lng" id="pickup-lng" value="0">
                <input type="hidden" name="dest_lat" id="dest-lat" value="0">
                <input type="hidden" name="dest_lng" id="dest-lng" value="0">

                <button type="submit" name="confirm_booking" class="btn-confirm" id="confirm-btn">
                    <i class="fa-solid fa-circle-check"></i> Finalize Order & Book
                </button>
            </form>
        </div>

        <!-- ===== FOOTER ===== -->
        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        </footer>

    </main>

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
        });

        // ============================================================
        // GLOBAL VARIABLES
        // ============================================================
        var map;
        var geocoder;
        var directionsService;
        var pickupMarker = null;
        var destMarker = null;
        var pickupLatLng = null;
        var destLatLng = null;
        var polyline = null;
        var currentDistance = 0;

        var packageBasePrice = 0;
        var hasPackage = false;

        var COMPANY_LAT = 6.0217;
        var COMPANY_LNG = 80.7917;

        var sriLankaBounds = {
            north: 10.2,
            south: 5.5,
            west: 79.0,
            east: 82.2
        };

        function formatCurrency(amount) {
            if (isNaN(amount) || amount === 0) return 'Rs. 0.00';
            return 'Rs. ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function setText(id, value) {
            var el = document.getElementById(id);
            if (el) el.innerText = value;
        }

        function setHTML(id, html) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = html;
        }

        function isWithinSriLanka(latLng) {
            var lat = latLng.lat();
            var lng = latLng.lng();
            return lat >= sriLankaBounds.south && lat <= sriLankaBounds.north &&
                   lng >= sriLankaBounds.west && lng <= sriLankaBounds.east;
        }

        function getHaversineDistance(lat1, lon1, lat2, lon2) {
            var R = 6371;
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLon = (lon2 - lon1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function getShortPlaceName(addressComponents) {
            if (!addressComponents) return null;
            var priorityTypes = ['locality', 'administrative_area_level_2', 'administrative_area_level_1'];
            for (var i = 0; i < addressComponents.length; i++) {
                var comp = addressComponents[i];
                if (comp.types) {
                    for (var j = 0; j < priorityTypes.length; j++) {
                        if (comp.types.indexOf(priorityTypes[j]) !== -1) {
                            return comp.long_name;
                        }
                    }
                }
            }
            return null;
        }

        // ============================================================
        // HANDLER FUNCTIONS
        // ============================================================
        function handleMemberChange() {
            var memberInput = document.getElementById('member-input');
            document.getElementById('members-hidden').value = memberInput.value;
            updateMemberLimit();
            updateAllCalculations();
        }

        function handleDaysChange() {
            document.getElementById('days-value').value = document.getElementById('days-input').value;
            updateAllCalculations();
        }

        function handleVehicleChange() {
            updateMemberLimit();
            updateAllCalculations();
        }

        // ============================================================
        // DOM READY
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            var today = new Date().toISOString().split('T')[0];
            document.getElementById('travel-date').setAttribute('min', today);
            document.getElementById('travel-date').value = today;

            document.getElementById('days-input').addEventListener('input', function() {
                document.getElementById('days-value').value = this.value;
                updateAllCalculations();
            });
            document.getElementById('member-input').addEventListener('input', function() {
                document.getElementById('members-hidden').value = this.value;
                updateMemberLimit();
                updateAllCalculations();
            });
            document.getElementById('vehicle-select').addEventListener('change', function() {
                updateMemberLimit();
                updateAllCalculations();
            });

            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                initializeMap();
            } else {
                var checkInterval = setInterval(function() {
                    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                        clearInterval(checkInterval);
                        initializeMap();
                    }
                }, 200);
                setTimeout(function() {
                    clearInterval(checkInterval);
                    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                        var mapElement = document.getElementById('map');
                        if (mapElement) {
                            mapElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;flex-direction:column;gap:10px;padding:20px;text-align:center;"><i class="fa-solid fa-map" style="font-size:48px;opacity:0.2;"></i><p style="color:#f87171;">⚠️ Google Maps failed to load</p></div>';
                        }
                    }
                }, 8000);
            }
        });

        function initializeMap() {
            geocoder = new google.maps.Geocoder();
            directionsService = new google.maps.DirectionsService();
            initMap();
            updateMemberLimit();
            updateAllCalculations();
            updateLocationStatus();
        }

        function updateMemberLimit() {
            var select = document.getElementById('vehicle-select');
            var memberInput = document.getElementById('member-input');
            var hintSpan = document.getElementById('capacity-hint');
            if (!select || !memberInput) return;
            var capacity = parseInt(select.options[select.selectedIndex].getAttribute('data-capacity')) || 10;
            memberInput.max = capacity;
            if (hintSpan) hintSpan.innerText = '(Max: ' + capacity + ')';
            var currentVal = parseInt(memberInput.value) || 1;
            if (currentVal > capacity) { memberInput.value = capacity; currentVal = capacity; }
            if (currentVal < 1) { memberInput.value = 1; currentVal = 1; }
            document.getElementById('members-hidden').value = currentVal;
        }

        function updateLocationStatus() {
            var statusEl = document.getElementById('location-status');
            var textEl = document.getElementById('location-status-text');
            var confBtn = document.getElementById('confirm-btn');
            var hasPickup = pickupLatLng !== null && pickupLatLng !== undefined;
            var hasDest = destLatLng !== null && destLatLng !== undefined;
            var hasDate = document.getElementById('travel-date').value !== '';
            if (hasPickup && hasDest && hasDate) {
                statusEl.className = 'location-status success';
                textEl.innerHTML = '✅ All details ready! Costs updated automatically.';
                confBtn.disabled = false;
            } else if (hasPickup && hasDest) {
                statusEl.className = 'location-status info';
                textEl.innerHTML = '📍 Locations selected. Please select a travel date.';
                confBtn.disabled = true;
            } else {
                statusEl.className = 'location-status error';
                textEl.innerHTML = '⚠️ Please select both pickup and destination locations on the map (Click on map to add)';
                confBtn.disabled = true;
            }
        }

        // ============================================================
        // MAP INIT
        // ============================================================
        function initMap() {
            var mapElement = document.getElementById('map');
            if (!mapElement) return;
            var defaultCenter = { lat: 7.8731, lng: 80.7718 };
            try {
                map = new google.maps.Map(mapElement, {
                    center: defaultCenter,
                    zoom: 8,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    zoomControl: true,
                    disableDefaultUI: false,
                    restriction: { latLngBounds: sriLankaBounds, strictBounds: false }
                });
                google.maps.event.addListener(map, 'click', function(e) {
                    if (!isWithinSriLanka(e.latLng)) {
                        var statusEl = document.getElementById('location-status');
                        statusEl.className = 'location-status error';
                        document.getElementById('location-status-text').innerHTML = '❌ Location is outside Sri Lanka! Please select a location within Sri Lanka.';
                        setTimeout(function() { updateLocationStatus(); }, 3000);
                        return;
                    }
                    if (pickupMarker !== null && destMarker !== null) {
                        clearSingleMarker('pickup');
                        handleMapClick(e.latLng);
                        return;
                    }
                    handleMapClick(e.latLng);
                });
            } catch (e) {
                console.log('Map error:', e);
            }
        }

        // ============================================================
        // HANDLE MAP CLICK
        // ============================================================
        function handleMapClick(latLng) {
            if (!isWithinSriLanka(latLng)) {
                var statusEl = document.getElementById('location-status');
                statusEl.className = 'location-status error';
                document.getElementById('location-status-text').innerHTML = '❌ Location is outside Sri Lanka!';
                setTimeout(function() { updateLocationStatus(); }, 3000);
                return;
            }
            var type = (pickupMarker === null) ? 'pickup' : 'dest';
            addMarker(latLng, type);
            if (type === 'pickup') {
                document.getElementById('pickup-lat').value = latLng.lat();
                document.getElementById('pickup-lng').value = latLng.lng();
            } else {
                document.getElementById('dest-lat').value = latLng.lat();
                document.getElementById('dest-lng').value = latLng.lng();
            }
            geocoder.geocode({ 'location': latLng }, function(results, status) {
                var address = 'Lat: ' + latLng.lat().toFixed(4) + ', Lng: ' + latLng.lng().toFixed(4);
                var displayName = address;
                if (status === 'OK' && results[0]) {
                    address = results[0].formatted_address;
                    var shortName = getShortPlaceName(results[0].address_components);
                    displayName = shortName || results[0].formatted_address.split(',')[0] || address;
                }
                if (type === 'pickup') {
                    document.getElementById('pickup-value').value = address;
                    document.getElementById('pickup-display').innerText = displayName;
                } else {
                    document.getElementById('dest-value').value = address;
                    document.getElementById('dest-display').innerText = displayName;
                }
            });
            if (pickupMarker !== null && destMarker !== null) { calculateDistance(); }
            updateLocationStatus();
            setTimeout(autoFillPackageName, 300);
        }

        // ============================================================
        // ADD MARKER
        // ============================================================
        function addMarker(latLng, type) {
            if (!isWithinSriLanka(latLng)) {
                var statusEl = document.getElementById('location-status');
                statusEl.className = 'location-status error';
                document.getElementById('location-status-text').innerHTML = '❌ Location is outside Sri Lanka!';
                setTimeout(function() { updateLocationStatus(); }, 3000);
                return;
            }
            var icon = (type === 'pickup') ? 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' : 'https://maps.google.com/mapfiles/ms/icons/red-dot.png';
            var labelText = (type === 'pickup') ? 'P' : 'D';
            var marker = new google.maps.Marker({
                position: latLng,
                map: map,
                draggable: true,
                icon: icon,
                title: (type === 'pickup') ? 'Pickup Location' : 'Destination',
                label: { text: labelText, color: 'white', fontWeight: 'bold', fontSize: '12px' }
            });
            var infoWindow = new google.maps.InfoWindow({
                content: (type === 'pickup') ? '<b>📍 Pickup Location</b>' : '<b>🏁 Destination</b>'
            });
            marker.addListener('click', function() { infoWindow.open(map, marker); });
            marker.addListener('dragend', function() {
                var pos = marker.getPosition();
                if (!isWithinSriLanka(pos)) {
                    marker.setPosition((type === 'pickup') ? pickupLatLng : destLatLng);
                    var statusEl = document.getElementById('location-status');
                    statusEl.className = 'location-status error';
                    document.getElementById('location-status-text').innerHTML = '❌ Cannot place marker outside Sri Lanka!';
                    setTimeout(function() { updateLocationStatus(); }, 3000);
                    return;
                }
                if (type === 'pickup') {
                    pickupLatLng = pos;
                    document.getElementById('pickup-lat').value = pos.lat();
                    document.getElementById('pickup-lng').value = pos.lng();
                } else {
                    destLatLng = pos;
                    document.getElementById('dest-lat').value = pos.lat();
                    document.getElementById('dest-lng').value = pos.lng();
                }
                geocoder.geocode({ 'location': pos }, function(results, status) {
                    var address = 'Lat: ' + pos.lat().toFixed(4) + ', Lng: ' + pos.lng().toFixed(4);
                    var displayName = address;
                    if (status === 'OK' && results[0]) {
                        address = results[0].formatted_address;
                        var shortName = getShortPlaceName(results[0].address_components);
                        displayName = shortName || results[0].formatted_address.split(',')[0] || address;
                    }
                    if (type === 'pickup') {
                        document.getElementById('pickup-value').value = address;
                        document.getElementById('pickup-display').innerText = displayName;
                    } else {
                        document.getElementById('dest-value').value = address;
                        document.getElementById('dest-display').innerText = displayName;
                    }
                });
                if (pickupMarker !== null && destMarker !== null) { calculateDistance(); }
                updateLocationStatus();
                autoFillPackageName();
            });
            if (type === 'pickup') {
                if (pickupMarker) { pickupMarker.setMap(null); }
                pickupMarker = marker;
                pickupLatLng = latLng;
            } else {
                if (destMarker) { destMarker.setMap(null); }
                destMarker = marker;
                destLatLng = latLng;
            }
            updateLocationStatus();
        }

        // ============================================================
        // CLEAR SINGLE MARKER
        // ============================================================
        function clearSingleMarker(type) {
            if (type === 'pickup' && pickupMarker) {
                pickupMarker.setMap(null);
                pickupMarker = null;
                pickupLatLng = null;
                document.getElementById('pickup-display').innerText = 'Not Selected';
                document.getElementById('pickup-value').value = '';
                document.getElementById('pickup-lat').value = 0;
                document.getElementById('pickup-lng').value = 0;
            } else if (type === 'dest' && destMarker) {
                destMarker.setMap(null);
                destMarker = null;
                destLatLng = null;
                document.getElementById('dest-display').innerText = 'Not Selected';
                document.getElementById('dest-value').value = '';
                document.getElementById('dest-lat').value = 0;
                document.getElementById('dest-lng').value = 0;
            }
            if (pickupMarker === null || destMarker === null) {
                if (polyline) { polyline.setMap(null); polyline = null; }
                currentDistance = 0;
                document.getElementById('distance-value').value = 0;
                document.getElementById('distance-display').innerText = '0.00 KM';
            }
            updateLocationStatus();
            updateAllCalculations();
        }

        // ============================================================
        // CALCULATE DISTANCE
        // ============================================================
        function calculateDistance() {
            if (pickupLatLng === null || destLatLng === null) {
                currentDistance = 0;
                if (polyline) { polyline.setMap(null); polyline = null; }
                document.getElementById('distance-display').innerText = '0.00 KM';
                document.getElementById('distance-value').value = 0;
                updateAllCalculations();
                return;
            }
            var request = { origin: pickupLatLng, destination: destLatLng, travelMode: google.maps.TravelMode.DRIVING };
            directionsService.route(request, function(result, status) {
                if (status === google.maps.DirectionsStatus.OK) {
                    var route = result.routes[0];
                    var distance = route.legs[0].distance.value / 1000;
                    currentDistance = distance;
                    document.getElementById('distance-value').value = distance.toFixed(2);
                    updateAllCalculationsWithDistance(distance);
                    if (polyline) { polyline.setMap(null); }
                    polyline = new google.maps.Polyline({
                        path: route.overview_path,
                        geodesic: true,
                        strokeColor: '#3b82f6',
                        strokeOpacity: 0.9,
                        strokeWeight: 4
                    });
                    polyline.setMap(map);
                    var bounds = new google.maps.LatLngBounds();
                    bounds.extend(pickupLatLng);
                    bounds.extend(destLatLng);
                    map.fitBounds(bounds);
                    updateAllCalculations();
                } else {
                    var mainDist = getHaversineDistance(pickupLatLng.lat(), pickupLatLng.lng(), destLatLng.lat(), destLatLng.lng());
                    currentDistance = mainDist;
                    document.getElementById('distance-value').value = mainDist.toFixed(2);
                    updateAllCalculationsWithDistance(mainDist);
                    updateAllCalculations();
                }
            });
        }

        function updateAllCalculationsWithDistance(mainDist) {
            var hasPickup = pickupLatLng !== null && pickupLatLng !== undefined;
            var hasDest = destLatLng !== null && destLatLng !== undefined;
            if (hasPickup && hasDest) {
                var pickupPos = pickupLatLng;
                var destPos = destLatLng;
                var distEmptyToPickup = getHaversineDistance(COMPANY_LAT, COMPANY_LNG, pickupPos.lat(), pickupPos.lng());
                var distReturnEmpty = getHaversineDistance(destPos.lat(), destPos.lng(), COMPANY_LAT, COMPANY_LNG);
                var totalOperationalDistance = distEmptyToPickup + mainDist + distReturnEmpty;
                document.getElementById('distance-display').innerHTML = totalOperationalDistance.toFixed(2) + ' KM <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(Empty: ' + distEmptyToPickup.toFixed(1) + ' + Trip: ' + mainDist.toFixed(1) + ' + Return: ' + distReturnEmpty.toFixed(1) + ')</span>';
                document.getElementById('distance-value').value = totalOperationalDistance.toFixed(2);
            }
        }

        // ============================================================
        // CLEAR MAP MARKERS
        // ============================================================
        function clearMapMarkers() {
            if (pickupMarker) { pickupMarker.setMap(null); pickupMarker = null; pickupLatLng = null; }
            if (destMarker) { destMarker.setMap(null); destMarker = null; destLatLng = null; }
            if (polyline) { polyline.setMap(null); polyline = null; }
            currentDistance = 0;
            document.getElementById('distance-value').value = 0;
            document.getElementById('distance-display').innerText = '0.00 KM';
            document.getElementById('pickup-lat').value = 0;
            document.getElementById('pickup-lng').value = 0;
            document.getElementById('dest-lat').value = 0;
            document.getElementById('dest-lng').value = 0;
            document.getElementById('pickup-value').value = '';
            document.getElementById('dest-value').value = '';
            document.getElementById('pickup-display').innerText = 'Not Selected';
            document.getElementById('dest-display').innerText = 'Not Selected';
            map.setCenter({ lat: 7.8731, lng: 80.7718 });
            map.setZoom(8);
            var pkgNameInput = document.getElementById('package-name');
            if (pkgNameInput.value.startsWith('Trip from')) {
                pkgNameInput.value = 'Custom Tour Plan';
            }
            updateAllCalculations();
        }

        // ============================================================
        // RESET ALL
        // ============================================================
        function resetAll() {
            if (pickupMarker) { pickupMarker.setMap(null); pickupMarker = null; }
            if (destMarker) { destMarker.setMap(null); destMarker = null; }
            if (polyline) { polyline.setMap(null); polyline = null; }
            pickupLatLng = null;
            destLatLng = null;
            currentDistance = 0;
            map.setCenter({ lat: 7.8731, lng: 80.7718 });
            map.setZoom(8);
            document.getElementById('member-input').value = 1;
            document.getElementById('members-hidden').value = 1;
            document.getElementById('days-input').value = 1;
            document.getElementById('days-value').value = 1;
            document.getElementById('vehicle-select').selectedIndex = 0;
            var today = new Date().toISOString().split('T')[0];
            document.getElementById('travel-date').value = today;
            document.getElementById('package-name').value = 'Custom Tour Plan';
            document.getElementById('pickup-display').innerText = 'Not Selected';
            document.getElementById('dest-display').innerText = 'Not Selected';
            document.getElementById('pickup-value').value = '';
            document.getElementById('dest-value').value = '';
            document.getElementById('pickup-lat').value = 0;
            document.getElementById('pickup-lng').value = 0;
            document.getElementById('dest-lat').value = 0;
            document.getElementById('dest-lng').value = 0;
            document.getElementById('distance-display').innerText = '0.00 KM';
            document.getElementById('distance-value').value = 0;
            document.getElementById('calculation-detail').innerText = '📍 Select 2 locations on map to include vehicle round‑trip costs. Base calculations updated.';
            updateLocationStatus();
            updateAllCalculations();
            showToast('All fields reset!', 'info');
        }

        function autoFillPackageName() {
            var pkgNameInput = document.getElementById('package-name');
            var pickup = document.getElementById('pickup-display').innerText;
            var dest = document.getElementById('dest-display').innerText;
            if ((pkgNameInput.value === 'Custom Tour Plan' || pkgNameInput.value === '') &&
                pickup !== 'Not Selected' && dest !== 'Not Selected') {
                var shortPickup = pickup.length > 20 ? pickup.substring(0, 20) + '...' : pickup;
                var shortDest = dest.length > 20 ? dest.substring(0, 20) + '...' : dest;
                pkgNameInput.value = 'Trip from ' + shortPickup + ' to ' + shortDest;
            }
        }

        // ============================================================
        // UPDATE ALL CALCULATIONS
        // ============================================================
        function updateAllCalculations() {
            var displayText = document.getElementById('distance-display').innerText;
            var match = displayText.match(/(\d+\.?\d*)/);
            var mainDistance = match ? parseFloat(match[1]) : 0;
            if (mainDistance === 0) {
                mainDistance = parseFloat(document.getElementById('distance-value').value) || 0;
            }
            if (mainDistance > 0) currentDistance = mainDistance;

            var hasPickup = pickupLatLng !== null && pickupLatLng !== undefined;
            var hasDest = destLatLng !== null && destLatLng !== undefined;

            var totalOperationalDistance = 0;
            var distEmptyToPickup = 0;
            var distReturnEmpty = 0;

            if (hasPickup && hasDest) {
                var pickupPos = pickupLatLng;
                var destPos = destLatLng;
                if (mainDistance === 0 || isNaN(mainDistance)) {
                    mainDistance = getHaversineDistance(pickupPos.lat(), pickupPos.lng(), destPos.lat(), destPos.lng());
                }
                distEmptyToPickup = getHaversineDistance(COMPANY_LAT, COMPANY_LNG, pickupPos.lat(), pickupPos.lng());
                distReturnEmpty = getHaversineDistance(destPos.lat(), destPos.lng(), COMPANY_LAT, COMPANY_LNG);
                totalOperationalDistance = distEmptyToPickup + mainDistance + distReturnEmpty;
                document.getElementById('distance-display').innerHTML = totalOperationalDistance.toFixed(2) + ' KM <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(Empty: ' + distEmptyToPickup.toFixed(1) + ' + Trip: ' + mainDistance.toFixed(1) + ' + Return: ' + distReturnEmpty.toFixed(1) + ')</span>';
                document.getElementById('distance-value').value = totalOperationalDistance.toFixed(2);
            } else {
                if (mainDistance > 0) {
                    document.getElementById('distance-display').innerText = mainDistance.toFixed(2) + ' KM';
                    document.getElementById('distance-value').value = mainDistance.toFixed(2);
                } else {
                    document.getElementById('distance-display').innerText = '0.00 KM';
                    document.getElementById('distance-value').value = 0;
                }
                totalOperationalDistance = mainDistance;
            }

            updateMemberLimit();

            var days = parseInt(document.getElementById('days-input').value) || 1;
            var select = document.getElementById('vehicle-select');
            var vehicleRate = 0, petrolRate = 45, capacity = 10;
            if (select) {
                var opt = select.options[select.selectedIndex];
                vehicleRate = parseFloat(opt.getAttribute('data-rate')) || 0;
                petrolRate = parseFloat(opt.getAttribute('data-petrol')) || 45;
                capacity = parseInt(opt.getAttribute('data-capacity')) || 10;
            }

            var memberInput = document.getElementById('member-input');
            var members = parseInt(memberInput ? memberInput.value : 1) || 1;
            if (members > capacity) { members = capacity; if (memberInput) memberInput.value = capacity; }
            document.getElementById('members-hidden').value = members;

            var discountPercent = 0;
            if (members >= 4 && members <= 6) discountPercent = 0.10;
            else if (members >= 7 && members <= 9) discountPercent = 0.15;
            else if (members >= 10) discountPercent = 0.20;

            var grossPackageCost = packageBasePrice * members * days;
            var discountAmount = grossPackageCost * discountPercent;
            var netPackageCost = grossPackageCost - discountAmount;
            var vehicleCostTotal = totalOperationalDistance * vehicleRate * days * members;
            var petrolCostTotal = totalOperationalDistance * petrolRate * days * members;
            var grandTotal = netPackageCost + vehicleCostTotal + petrolCostTotal;
            var perPersonCost = members > 0 ? (grandTotal / members) : 0;

            netPackageCost = Math.round(netPackageCost * 100) / 100;
            vehicleCostTotal = Math.round(vehicleCostTotal * 100) / 100;
            petrolCostTotal = Math.round(petrolCostTotal * 100) / 100;
            grandTotal = Math.round(grandTotal * 100) / 100;
            perPersonCost = Math.round(perPersonCost * 100) / 100;

            document.getElementById('days-value').value = days;
            document.getElementById('cost-value').value = grandTotal.toFixed(2);

            setText('package-cost-display', formatCurrency(netPackageCost));
            setText('vehicle-cost-display', formatCurrency(vehicleCostTotal));
            setText('petrol-cost-display', formatCurrency(petrolCostTotal));
            setText('subtotal-display', formatCurrency(perPersonCost));
            setText('total-display', formatCurrency(grandTotal));

            var pkgLabel = '<span class="breakdown-label"><i class="fa-regular fa-suitcase" style="color:#3b82f6;"></i> Package Cost (Rs.' + packageBasePrice + ' × ' + members + 'p × ' + days + 'd';
            if (discountPercent > 0) pkgLabel += ' - ' + (discountPercent * 100) + '% off';
            pkgLabel += ')</span><span class="breakdown-value text-blue">' + formatCurrency(netPackageCost) + '</span>';
            setHTML('package-cost-row', pkgLabel);

            var distanceDisplayForLabel = totalOperationalDistance.toFixed(2);
            setHTML('vehicle-cost-row', '<span class="breakdown-label"><i class="fa-solid fa-car" style="color:#a855f7;"></i> Vehicle Cost (' + distanceDisplayForLabel + 'KM [Total Ops] × Rs.' + vehicleRate + ' × ' + days + 'd × ' + members + 'p)</span><span class="breakdown-value text-purple">' + formatCurrency(vehicleCostTotal) + '</span>');
            setHTML('petrol-cost-row', '<span class="breakdown-label"><i class="fa-solid fa-gas-pump" style="color:#f59e0b;"></i> Petrol Cost (' + distanceDisplayForLabel + 'KM [Total Ops] × Rs.' + petrolRate + ' × ' + days + 'd × ' + members + 'p)</span><span class="breakdown-value text-amber">' + formatCurrency(petrolCostTotal) + '</span>');
            setHTML('subtotal-row', '<span class="breakdown-label" style="font-weight:700;color:var(--text-secondary);">👤 Per Person</span><span class="breakdown-value" style="font-weight:700;color:var(--text-primary);font-size:16px;">' + formatCurrency(perPersonCost) + '</span>');
            setHTML('total-row', '<b style="font-size:16px;color:var(--text-primary);">💳 Total Grand Charge (' + members + ' members)</b><b class="text-green" style="font-size:20px;">' + formatCurrency(grandTotal) + '</b>');

            if (hasPickup && hasDest && totalOperationalDistance > 0) {
                setText('calculation-detail', '📊 ' + members + ' members | ' + (discountPercent*100) + '% group discount | Per head: ' + formatCurrency(perPersonCost) + ' | Total Vehicle KM: ' + totalOperationalDistance.toFixed(1));
            } else {
                setText('calculation-detail', '📍 Select 2 locations on map to include vehicle round‑trip costs. Base calculations updated.');
            }

            updateLocationStatus();
        }

        function validateBeforeSubmit() {
            var plat = parseFloat(document.getElementById('pickup-lat').value);
            var dlat = parseFloat(document.getElementById('dest-lat').value);
            var date = document.getElementById('travel-date').value;
            if (isNaN(plat) || plat === 0 || isNaN(dlat) || dlat === 0) {
                showToast('Please select both pickup and destination locations on the map first!', 'error');
                return false;
            }
            if (!date) {
                showToast('Please select a travel date!', 'error');
                return false;
            }
            return true;
        }

        console.log('✅ Plan a Trip - Dashboard Design Applied!');
    </script>

</body>
</html>