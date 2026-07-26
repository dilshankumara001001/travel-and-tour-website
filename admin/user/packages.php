<?php

date_default_timezone_set('Asia/Colombo');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// ============================================================
// PHPMailer Autoload (Absolute Path - Matches other files)
// ============================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/xampp2/htdocs/travel/phpmailer/src/Exception.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/PHPMailer.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/SMTP.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User';
// Ensure email is fetched from session or fallback to database
$user_email = $_SESSION['email'] ?? '';

include("../includes/db.php");

// If email not in session, fetch from DB
if (empty($user_email)) {
    $email_query = mysqli_query($conn, "SELECT email FROM users WHERE id = $user_id");
    if ($email_row = mysqli_fetch_assoc($email_query)) {
        $user_email = $email_row['email'];
        $_SESSION['email'] = $user_email; // store for future use
    }
}

// ============================================================
// 1. HANDLE "BOOK NOW" BUTTON CLICK - SAVE BOOKING & SEND EMAIL
// ============================================================
if (isset($_GET['book_now']) && isset($_GET['id'])) {
    $pkg_id = intval($_GET['id']);
    
    $pkg_query = mysqli_query($conn, "SELECT * FROM packages WHERE id = $pkg_id AND status != 'Deleted'");
    $pkg = mysqli_fetch_assoc($pkg_query);
    
    if ($pkg) {
        $pkg_name = mysqli_real_escape_string($conn, $pkg['title']);
        $pkg_price = $pkg['price'] > 0 ? $pkg['price'] : 0;
        $pickup = isset($pkg['latitude']) && isset($pkg['longitude']) ? $pkg['latitude'].','.$pkg['longitude'] : '';
        $dest = isset($pkg['latitude2']) && isset($pkg['longitude2']) ? $pkg['latitude2'].','.$pkg['longitude2'] : '';
        
        $vehicle_id = 1; // default vehicle
        $today = date('Y-m-d');
        
        $insert_query = "INSERT INTO bookings (
                            user_id, package_id, package_name, manual_package_name, 
                            vehicle_id, booking_date, status, pickup_location, 
                            destination_location, calculated_distance, total_cost, number_of_days
                        ) VALUES (
                            '$user_id', '$pkg_id', '$pkg_name', '$pkg_name', 
                            '$vehicle_id', '$today', 'Approved', '$pickup', '$dest', 
                            0, '$pkg_price', 1
                        )";
        
        if (mysqli_query($conn, $insert_query)) {
            $booking_id = mysqli_insert_id($conn);
            $log_text = mysqli_real_escape_string($conn, "User $user_id booked package '$pkg_name' directly (#$booking_id)");
            mysqli_query($conn, "INSERT INTO system_logs (user_id, action, log_date) VALUES ('$user_id', '$log_text', NOW())");
            
            // ============================================================
            // 🟢 AUTO SEND EMAIL (Matches plan_a_trip.php & my_bookings.php)
            // ============================================================
            if (!empty($user_email)) {
                $mail = new PHPMailer(true);
                try {
                    // SMTP Configuration (Port 465 SSL)
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'dileepadilshan46@gmail.com';
                    $mail->Password   = 'futvipxtqvfukpcw';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
                    $mail->Port       = 465;

                    $mail->setFrom('dileepadilshan46@gmail.com', 'Travel Matrix');
                    $mail->addAddress($user_email, $user_name);

                    // Professional HTML Email Content
                    $mail->isHTML(true);
                    $mail->Subject = '✅ Booking Confirmed! - #' . $booking_id;
                    $mail->Body    = "
                        <html>
                        <head><style>body{font-family:Arial,sans-serif;color:#333;} .container{max-width:600px;margin:0 auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;} h2{color:#10b981;} .details{background:#f9fafb;padding:15px;border-radius:6px;margin:15px 0;} .footer{font-size:12px;color:#888;margin-top:20px;border-top:1px solid #eee;padding-top:10px;}
</style></head>
<body>
<div class='container'>
    <h2>🎉 Thank you for booking, {$user_name}!</h2>
    <p>Your booking has been successfully confirmed.</p>
    <div class='details'>
        <p><strong>📦 Package:</strong> {$pkg_name}</p>
        <p><strong>📅 Booking Date:</strong> {$today}</p>
        <p><strong>⏳ Duration:</strong> 1 Day</p>
        <p><strong>👤 Members:</strong> 1</p>
        <p><strong>💰 Total Cost:</strong> Rs. " . number_format($pkg_price, 2) . "</p>
        <p><strong>🆔 Booking ID:</strong> #{$booking_id}</p>
    </div>
    <p>We will contact you shortly with further details.</p>
    <div class='footer'>Travel Matrix - Your Adventure Awaits!</div>
</div>
</body>
</html>
";
                    $mail->AltBody = "Thank you for booking! Package: $pkg_name, Total: Rs. $pkg_price, ID: #$booking_id";
                    
                    $mail->send();
                    error_log("Booking email sent successfully to: " . $user_email);
                    
                } catch (Exception $e) {
                    error_log("Email sending failed for booking #$booking_id: " . $e->getMessage());
                }
            } else {
                error_log("User email not found for user ID: $user_id");
            }
            
            // Redirect to My Bookings with success flag
            header("Location: my_bookings.php?success=1");
            exit();
        } else {
            header("Location: packages.php?error=booking_failed");
            exit();
        }
    } else {
        header("Location: packages.php?error=package_not_found");
        exit();
    }
}

// ============================================================
// 2. HANDLE "UNSELECT" PACKAGE (For compatibility)
// ============================================================
if (isset($_GET['unselect'])) {
    header("Location: packages.php");
    exit();
}

// ============================================================
// 3. BOOKING FORM SUBMISSION HANDLER (MANUAL MAP BOOKING) - WITH EMAIL TOO
// ============================================================
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
    
    $vehicle_check_query = "SELECT capacity FROM vehicles WHERE id = $vehicle_id";
    $vehicle_check_result = mysqli_query($conn, $vehicle_check_query);
    $vehicle_data = mysqli_fetch_assoc($vehicle_check_result);
    $vehicle_capacity = $vehicle_data['capacity'] ?? 10;
    
    $error_msg = "";
    if (empty($travel_date)) {
        $error_msg = "Please select a travel date.";
    } elseif ($number_of_members > $vehicle_capacity) {
        $error_msg = "Members count ($number_of_members) exceeds vehicle capacity ($vehicle_capacity)!";
    } elseif (empty($pickup) || empty($destination)) {
        $error_msg = "Please select both pickup and destination locations on the map!";
    } elseif ($pid == 0 && empty($manual_package_name)) {
        $error_msg = "Invalid package details.";
    }
    
    if (empty($error_msg)) {
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
            $log_text = mysqli_real_escape_string($conn, "User $user_id booked package '$manual_package_name' for $number_of_days days on $travel_date (#$booking_id)");
            mysqli_query($conn, "INSERT INTO system_logs (user_id, action, log_date) VALUES ('$user_id', '$log_text', NOW())");
            
            // Send email for manual booking too (Same logic as above)
            if (!empty($user_email)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'dileepadilshan46@gmail.com';
                    $mail->Password   = 'futvipxtqvfukpcw';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    $mail->setFrom('dileepadilshan46@gmail.com', 'Travel Matrix');
                    $mail->addAddress($user_email, $user_name);

                    $mail->isHTML(true);
                    $mail->Subject = '✅ Custom Trip Confirmed! - #' . $booking_id;
                    $mail->Body    = "
                        <html>
                        <head><style>body{font-family:Arial,sans-serif;color:#333;} .container{max-width:600px;margin:0 auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;} h2{color:#10b981;} .details{background:#f9fafb;padding:15px;border-radius:6px;margin:15px 0;} .footer{font-size:12px;color:#888;margin-top:20px;border-top:1px solid #eee;padding-top:10px;}
</style></head>
<body>
<div class='container'>
    <h2>🎉 Custom Trip Booking Confirmed, {$user_name}!</h2>
    <p>Your custom trip has been successfully booked.</p>
    <div class='details'>
        <p><strong>📦 Trip Name:</strong> {$manual_package_name}</p>
        <p><strong>📅 Travel Date:</strong> {$travel_date}</p>
        <p><strong>⏳ Duration:</strong> {$number_of_days} Day(s)</p>
        <p><strong>👤 Members:</strong> {$number_of_members}</p>
        <p><strong>💰 Total Cost:</strong> Rs. " . number_format($total_cost, 2) . "</p>
        <p><strong>🆔 Booking ID:</strong> #{$booking_id}</p>
    </div>
    <p>We will contact you shortly with further details.</p>
    <div class='footer'>Travel Matrix - Your Adventure Awaits!</div>
</div>
</body>
</html>
";
                    $mail->AltBody = "Custom Trip Booking! Name: $manual_package_name, Date: $travel_date, Total: Rs. $total_cost, ID: #$booking_id";
                    
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Manual booking email failed: " . $e->getMessage());
                }
            }
            
            header("Location: my_bookings.php?success=1");
            exit();
        } else {
            $error_msg = "Database Error: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// 4. FETCH ALL PACKAGES
// ============================================================
$packages_result = mysqli_query($conn, "SELECT * FROM packages WHERE status != 'Deleted' ORDER BY id DESC");

// ============================================================
// 5. FETCH VEHICLES (FOR MANUAL BOOKING PANEL)
// ============================================================
$vehicles_list = [];
$v_result = mysqli_query($conn, "SELECT * FROM vehicles");
while ($v = mysqli_fetch_assoc($v_result)) {
    if (!isset($v['petrol_rate'])) $v['petrol_rate'] = 45.00;
    $vehicles_list[] = $v;
}

// ============================================================
// GREETING & THEME
// ============================================================
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';
$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Packages | Travel Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet" />
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
            --badge-bg: rgba(16, 185, 129, 0.20);
            --badge-text: #34d399;
            --badge-border: rgba(16, 185, 129, 0.25);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-primary); min-height: 100vh; display: flex; transition: background 0.3s ease, color 0.3s ease; }

        /* ===== SIDEBAR ===== */
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 270px; background: var(--sidebar-bg); padding: 28px 20px; display: flex; flex-direction: column; border-right: 1px solid var(--sidebar-border); z-index: 999; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); overflow-y: auto; }
        .sidebar-brand { display: flex; align-items: center; gap: 14px; margin-bottom: 40px; padding-left: 8px; }
        .sidebar-brand i { font-size: 28px; color: #60a5fa; filter: drop-shadow(0 4px 12px rgba(37, 99, 235, 0.3)); }
        .sidebar-brand span { font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
        .sidebar .nav-menu { display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .sidebar .nav-menu a { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 14px; color: var(--sidebar-text); text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.25s ease; position: relative; }
        .sidebar .nav-menu a i { width: 22px; font-size: 18px; text-align: center; }
        .sidebar .nav-menu a:hover { background: var(--sidebar-active); color: var(--sidebar-text-hover); transform: translateX(4px); }
        .sidebar .nav-menu a.active { background: rgba(96, 165, 250, 0.12); color: #60a5fa; box-shadow: inset 3px 0 0 #60a5fa; }
        .sidebar .nav-menu .logout-link { margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 16px; color: #f87171; }
        .sidebar .nav-menu .logout-link:hover { background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1000; background: var(--card-bg); backdrop-filter: blur(12px); border: 1px solid var(--card-border); border-radius: 12px; padding: 10px 12px; cursor: pointer; font-size: 22px; color: var(--text-primary); box-shadow: var(--card-shadow); transition: all 0.3s ease; }

        .main-content { margin-left: 270px; flex: 1; padding: 28px 36px 40px; min-height: 100vh; width: calc(100% - 270px); transition: margin-left 0.3s ease, width 0.3s ease; }

        /* ===== TOP HEADER ===== */
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding: 16px 24px; background: var(--bg-navbar); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid var(--card-border); box-shadow: var(--card-shadow); transition: all 0.3s ease; }
        .top-header .greeting-text h2 { font-size: 22px; font-weight: 700; color: var(--text-primary); }
        .top-header .greeting-text h2 span { background: linear-gradient(135deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .top-header .greeting-text p { font-size: 14px; color: var(--text-muted); margin-top: 2px; }
        .top-header .header-actions { display: flex; align-items: center; gap: 18px; }
        .header-datetime { font-family: 'Inter', monospace; font-size: 13px; font-weight: 600; color: var(--text-primary); background: var(--card-bg); padding: 6px 18px; border-radius: 30px; border: 1px solid var(--card-border); box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 10px; white-space: nowrap; letter-spacing: 0.3px; }
        .header-datetime .live-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--live-dot-color); animation: pulseDot 1.5s ease-in-out infinite; flex-shrink: 0; }
        @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(0.7); } }
        .header-datetime .datetime-text { display: flex; align-items: center; gap: 6px; }
        .header-datetime .datetime-text i { color: var(--text-muted); font-size: 14px; margin-right: 2px; }
        .header-datetime .datetime-text .time-separator { color: var(--text-muted); margin: 0 4px; }
        .top-header .profile-avatar { display: flex; align-items: center; gap: 12px; padding: 4px 12px 4px 4px; background: var(--card-bg); border-radius: 40px; border: 1px solid var(--card-border); }
        .top-header .profile-avatar img { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .top-header .profile-avatar .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #10b981; border: 2px solid var(--card-bg); margin-left: -6px; }
        .theme-toggle { background: var(--toggle-bg); border: none; border-radius: 40px; width: 48px; height: 26px; display: flex; align-items: center; justify-content: space-between; padding: 0 5px; cursor: pointer; position: relative; transition: background 0.3s ease; flex-shrink: 0; }
        .theme-toggle .toggle-dot { width: 18px; height: 18px; border-radius: 50%; background: var(--toggle-dot); transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55), background 0.3s ease; position: absolute; left: 4px; top: 4px; }
        [data-theme="dark"] .theme-toggle .toggle-dot { transform: translateX(22px); }
        .theme-toggle .toggle-icon { font-size: 11px; color: var(--text-muted); z-index: 1; }
        .theme-toggle .toggle-icon.sun { margin-right: auto; }
        .theme-toggle .toggle-icon.moon { margin-left: auto; }
        [data-theme="dark"] .theme-toggle .toggle-icon.sun { color: #fbbf24; }
        [data-theme="dark"] .theme-toggle .toggle-icon.moon { color: #94a3b8; }

        /* ===== PAGE HEADER ===== */
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .page-header .title { font-size: 22px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 12px; transition: color 0.3s ease; }
        .page-header .title i { color: var(--icon-color); font-size: 24px; }
        .page-header .title span { font-weight: 400; font-size: 14px; color: var(--text-muted); margin-left: 4px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; color: var(--text-secondary); font-weight: 600; font-size: 13px; text-decoration: none; transition: all 0.3s ease; backdrop-filter: blur(8px); box-shadow: var(--card-shadow); }
        .btn-back:hover { background: var(--card-border); color: var(--text-primary); transform: translateX(-4px); }

        /* ===== FILTER BAR ===== */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; align-items: center; justify-content: space-between; background: var(--filter-bg); backdrop-filter: blur(8px); padding: 14px 22px; border-radius: 16px; border: 1px solid var(--card-border); box-shadow: var(--card-shadow); transition: background 0.3s ease, border-color 0.3s ease; }
        .filter-bar .search-box { display: flex; align-items: center; gap: 10px; flex: 1 1 200px; }
        .filter-bar .search-box input { background: var(--input-bg); border: 1px solid var(--input-border); padding: 8px 16px; border-radius: 10px; color: var(--text-primary); width: 100%; outline: none; transition: 0.3s; font-family: 'Inter', sans-serif; font-size: 14px; }
        .filter-bar .search-box input:focus { border-color: var(--icon-color); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); background: var(--input-focus); }
        .filter-bar .search-box input::placeholder { color: var(--text-muted); }
        .filter-bar .sort-box select { background: var(--input-bg); border: 1px solid var(--input-border); padding: 8px 16px; border-radius: 10px; color: var(--text-primary); outline: none; cursor: pointer; font-family: 'Inter', sans-serif; font-size: 14px; }
        .filter-bar .sort-box select:focus { border-color: var(--icon-color); }
        .filter-bar .pkg-count { font-size: 13px; color: var(--text-muted); }
        .filter-bar .pkg-count span { font-weight: 700; color: var(--text-primary); }

        /* ===== PACKAGES GRID ===== */
        .packages-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .pkg-card { background: var(--card-bg); backdrop-filter: blur(8px); border-radius: 20px; border: 1px solid var(--card-border); padding: 22px 20px; box-shadow: var(--card-shadow); transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); position: relative; overflow: hidden; }
        .pkg-card:hover { transform: translateY(-6px); border-color: var(--icon-color); box-shadow: var(--shadow-hover); }
        .pkg-card::after { content: ''; position: absolute; inset: -2px; border-radius: 20px; padding: 2px; background: conic-gradient(from var(--angle, 0deg), #3b82f6, #10b981, #f59e0b, #8b5cf6, #3b82f6); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; opacity: 0; transition: opacity 0.4s ease; pointer-events: none; z-index: 0; }
        .pkg-card:hover::after { opacity: 1; animation: rotateBorder 3s linear infinite; }
        @property --angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
        @keyframes rotateBorder { to { --angle: 360deg; } }
        .pkg-card .pkg-img { width: 100%; height: 80px; border-radius: 12px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08)); display: flex; align-items: center; justify-content: center; font-size: 32px; color: var(--text-muted); opacity: 0.3; margin-bottom: 12px; }
        .pkg-card .pkg-title { font-size: 17px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; transition: color 0.3s ease; }
        .pkg-card .pkg-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 8px; transition: color 0.3s ease; }
        .pkg-card .pkg-rating { display: flex; align-items: center; gap: 4px; color: #f59e0b; font-size: 13px; margin-bottom: 6px; }
        .pkg-card .pkg-rating span { color: var(--text-muted); font-size: 12px; margin-left: 4px; }
        .pkg-card .pkg-price { font-size: 20px; font-weight: 700; color: var(--badge-text); display: inline-block; transition: transform 0.3s ease, color 0.3s ease; margin: 6px 0 4px; }
        .pkg-card:hover .pkg-price { transform: scale(1.08); }
        .pkg-card .pkg-progress { width: 100%; height: 4px; background: var(--card-border); border-radius: 4px; overflow: hidden; margin: 8px 0 4px; }
        .pkg-card .pkg-progress .bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--icon-color), #10b981); transition: width 1.5s ease; }
        .pkg-card .pkg-availability { font-size: 11px; color: var(--text-muted); text-align: right; transition: color 0.3s ease; }
        .pkg-card .pkg-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .pkg-card .pkg-actions a { padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-align: center; flex: 1; position: relative; }
        .pkg-card .pkg-actions a::after { content: attr(data-tooltip); position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%) scale(0.8); background: var(--card-bg); color: var(--text-primary); padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 500; white-space: nowrap; opacity: 0; pointer-events: none; transition: all 0.25s ease; box-shadow: var(--card-shadow); border: 1px solid var(--card-border); }
        .pkg-card .pkg-actions a:hover::after { opacity: 1; transform: translateX(-50%) scale(1); }
        .btn-view { background: rgba(59, 130, 246, 0.08); color: var(--icon-color); border: 1px solid rgba(59, 130, 246, 0.12); }
        .btn-view:hover { background: rgba(59, 130, 246, 0.18); transform: translateY(-2px); }
        .btn-book { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; }
        .btn-book:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25); }
        .pkg-badge { position: absolute; top: 14px; right: 14px; font-size: 10px; font-weight: 700; padding: 3px 12px; border-radius: 20px; background: var(--badge-bg); color: var(--badge-text); border: 1px solid var(--badge-border); z-index: 2; text-transform: uppercase; letter-spacing: 0.3px; }
        .pkg-badge.pulse { animation: pulseBadge 2s infinite; }
        @keyframes pulseBadge { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .pkg-card .fav-btn { position: absolute; top: 12px; right: 60px; background: rgba(0, 0, 0, 0.3); border: none; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; transition: all 0.3s ease; backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 2; font-size: 14px; }
        .pkg-card .fav-btn:hover { transform: scale(1.15); }
        .pkg-card .fav-btn.active { color: #ef4444; background: rgba(239, 68, 68, 0.2); }
        .pkg-card .share-btn { position: absolute; top: 12px; right: 95px; background: rgba(59, 130, 246, 0.25); border: none; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; transition: all 0.3s ease; backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 2; font-size: 14px; }
        .pkg-card .share-btn:hover { transform: scale(1.15); background: rgba(59, 130, 246, 0.4); }
        .empty-state { text-align: center; padding: 60px 30px; background: var(--card-bg); backdrop-filter: blur(8px); border-radius: 20px; border: 1px solid var(--card-border); box-shadow: var(--card-shadow); grid-column: 1 / -1; }
        .empty-state i { font-size: 52px; color: var(--text-muted); opacity: 0.2; display: block; margin-bottom: 16px; }
        .empty-state p { font-size: 17px; color: var(--text-muted); font-weight: 500; }
        .empty-state .sub-text { font-size: 14px; color: var(--text-muted); margin-top: 6px; opacity: 0.6; }

        .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 99999; display: flex; flex-direction: column; gap: 12px; }
        .toast { background: var(--card-bg); backdrop-filter: blur(12px); padding: 14px 20px; border-radius: 12px; border-left: 4px solid #10b981; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); color: var(--text-primary); font-weight: 500; font-size: 14px; animation: slideInRight 0.4s ease, fadeOut 0.4s ease 2.6s forwards; max-width: 350px; display: flex; align-items: center; gap: 10px; font-family: 'Inter', sans-serif; }
        .toast.error { border-left-color: #ef4444; }
        .toast.info { border-left-color: #3b82f6; }
        @keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(-20px); } }

        .footer-bar { margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--card-border); display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; font-size: 13px; color: var(--text-muted); transition: all 0.3s ease; text-align: center; }

        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); width: 280px; } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; width: 100%; padding: 20px 20px 30px; } .hamburger { display: block; } .header-datetime { font-size: 12px; padding: 4px 12px; } .header-datetime .live-dot { width: 8px; height: 8px; } }
        @media (max-width: 768px) { .main-content { padding: 16px 16px 24px; } .top-header { flex-direction: column; align-items: flex-start; gap: 12px; } .top-header .header-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; } .page-header { flex-direction: column; align-items: flex-start; gap: 12px; } .page-header .title { font-size: 19px; } .filter-bar { flex-direction: column; align-items: stretch; padding: 14px 16px; } .filter-bar .search-box { width: 100%; } .filter-bar .sort-box { width: 100%; } .filter-bar .pkg-count { text-align: center; } .packages-grid { grid-template-columns: 1fr; gap: 16px; } .pkg-card { padding: 18px 16px; } .pkg-card .pkg-title { font-size: 16px; } .pkg-card .pkg-actions { flex-direction: column; } .pkg-card .pkg-actions a { flex: none; } .header-datetime { font-size: 11px; padding: 4px 10px; } .header-datetime .live-dot { width: 7px; height: 7px; } }
        @media (max-width: 480px) { .main-content { padding: 12px 12px 20px; } .top-header .greeting-text h2 { font-size: 18px; } .page-header .title { font-size: 17px; } .packages-grid { grid-template-columns: 1fr; gap: 14px; } .pkg-card { padding: 14px 12px; } .pkg-card .pkg-title { font-size: 15px; } .pkg-card .pkg-price { font-size: 18px; } .pkg-card .pkg-actions a { font-size: 12px; padding: 6px 12px; } .pkg-card .fav-btn, .pkg-card .share-btn { width: 26px; height: 26px; font-size: 12px; right: 50px; } .pkg-card .share-btn { right: 80px; } .header-datetime { font-size: 10px; padding: 3px 8px; } .header-datetime .live-dot { width: 6px; height: 6px; } .btn-back { font-size: 12px; padding: 6px 14px; } .empty-state { padding: 40px 16px; } .empty-state i { font-size: 40px; } .empty-state p { font-size: 15px; } }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 12px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--icon-color); }
    </style>
</head>
<body>

    <!-- ===== HAMBURGER ===== -->
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
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php" class="active"><i class="fa-solid fa-suitcase-rolling"></i> Packages</a>
            <a href="my_bookings.php"><i class="fa-solid fa-box-archive"></i> My Bookings</a>
            <a href="feedback_status.php"><i class="fa-solid fa-envelope"></i> Feedback</a>
            <a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a>
            <a href="plan_a_trip.php"><i class="fa-solid fa-compass"></i> Plan a Trip</a>
            <a href="../logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <header class="top-header">
            <div class="greeting-text">
                <h2><?php echo $greeting; ?>, <span><?php echo htmlspecialchars($user_name); ?></span> 👋</h2>
                <p><i class="fa-regular fa-star" style="color:#f59e0b;"></i> Browse and book our exclusive tour packages.</p>
            </div>
            <div class="header-actions">
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

        <div class="page-header">
            <div class="title"><i class="fa-solid fa-suitcase-rolling"></i> Tour Packages <span>· Choose your adventure</span></div>
            <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fa-solid fa-search" style="color:var(--text-muted);"></i>
                <input type="text" id="pkgSearch" placeholder="Search packages..." oninput="filterPackages()">
            </div>
            <div class="sort-box">
                <select id="pkgSort" onchange="sortPackages()">
                    <option value="default">Sort by: Default</option>
                    <option value="price-asc">Price: Low → High</option>
                    <option value="price-desc">Price: High → Low</option>
                    <option value="name">Name: A → Z</option>
                </select>
            </div>
            <div class="pkg-count">Showing <span id="visibleCount">0</span> packages</div>
        </div>

        <!-- PACKAGES GRID -->
        <div class="packages-grid" id="packagesGrid">
            <?php
            $has_packages = false;
            $counter = 0;
            while ($pkg = mysqli_fetch_assoc($packages_result)) {
                $has_packages = true;
                $counter++;
                $pkg_id = $pkg['id'];
                $icons = ['fa-mountain', 'fa-umbrella-beach', 'fa-tree', 'fa-ship', 'fa-hiking', 'fa-campground', 'fa-water', 'fa-cloud-sun'];
                $icon = $icons[array_rand($icons)];
                $rating = (4 + (mt_rand(0, 10) / 10));
                $rating = number_format($rating, 1);
                $availability = mt_rand(60, 100);
                $is_featured = ($counter == 1);
            ?>
                <div class="pkg-card" data-pkg-id="<?php echo $pkg_id; ?>" data-price="<?php echo $pkg['price']; ?>" data-name="<?php echo htmlspecialchars($pkg['title']); ?>">
                    
                    <?php if ($is_featured): ?>
                        <span class="pkg-badge" style="background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;border:none;">⭐ Featured</span>
                    <?php else: ?>
                        <span class="pkg-badge pulse">Available</span>
                    <?php endif; ?>

                    <button class="fav-btn" onclick="toggleFavorite(this, <?php echo $pkg_id; ?>)" title="Add to favorites">
                        <i class="fa-regular fa-heart"></i>
                    </button>
                    <button class="share-btn" onclick="sharePackage(<?php echo $pkg_id; ?>)" title="Share package">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>

                    <div class="pkg-img"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                    <div class="pkg-title"><?php echo htmlspecialchars($pkg['title']); ?></div>
                    <div class="pkg-desc"><?php echo htmlspecialchars($pkg['description']); ?></div>

                    <div class="pkg-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-solid fa-star" style="color: <?php echo ($i <= floor($rating)) ? '#f59e0b' : 'rgba(255,255,255,0.15)'; ?>;"></i>
                        <?php endfor; ?>
                        <span>(<?php echo $rating; ?>)</span>
                    </div>

                    <span class="pkg-price">$<?php echo number_format($pkg['price'], 2); ?></span>

                    <div class="pkg-progress"><div class="bar" style="width: <?php echo $availability; ?>%;"></div></div>
                    <div class="pkg-availability"><?php echo $availability; ?>% available</div>

                    <div class="pkg-actions">
                        <a href="view_package.php?id=<?php echo $pkg_id; ?>" class="btn-view" data-tooltip="View full details"><i class="fa-solid fa-eye"></i> Details</a>
                        <!-- ============================================================ -->
                        <!-- BOOK NOW BUTTON - Triggers PHP Email & DB Save -->
                        <!-- ============================================================ -->
                        <a href="packages.php?book_now=1&id=<?php echo $pkg_id; ?>" class="btn-book" data-tooltip="Book this package now" onclick="showToast('Booking initiated!', 'success');">
                            <i class="fa-solid fa-circle-check"></i> Book Now
                        </a>
                    </div>
                </div>
            <?php
            }
            if (!$has_packages) {
                echo '<div class="empty-state">
                        <i class="fa-regular fa-suitcase-rolling"></i>
                        <p>No packages available</p>
                        <div class="sub-text">Check back later for new adventures.</div>
                      </div>';
            }
            ?>
        </div>

        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        </footer>

    </main>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ===== SIDEBAR TOGGLE =====
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

        // ===== REAL-TIME CLOCK =====
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateString = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            const timeEl = document.getElementById('liveTime');
            const dateEl = document.getElementById('liveDate');
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ===== FAVORITES =====
        function toggleFavorite(btn, pkgId) {
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            const icon = btn.querySelector('i');
            if (favorites.includes(pkgId)) {
                favorites = favorites.filter(id => id !== pkgId);
                icon.className = 'fa-regular fa-heart';
                btn.classList.remove('active');
                showToast('Removed from favorites', 'info');
            } else {
                favorites.push(pkgId);
                icon.className = 'fa-solid fa-heart';
                btn.classList.add('active');
                showToast('Added to favorites', 'success');
            }
            localStorage.setItem('favorites', JSON.stringify(favorites));
        }

        // ===== SHARE =====
        function sharePackage(pkgId) {
            const url = window.location.href.split('?')[0] + '?booking=' + pkgId;
            if (navigator.share) {
                navigator.share({ title: 'Check out this package!', url: url });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link copied to clipboard!', 'success');
                });
            }
        }

        // ===== FILTER & SORT =====
        function filterPackages() {
            const query = document.getElementById('pkgSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.pkg-card');
            cards.forEach(card => {
                const title = card.dataset.name.toLowerCase();
                card.style.display = title.includes(query) ? '' : 'none';
            });
            updateVisibleCount();
        }

        function sortPackages() {
            const sortVal = document.getElementById('pkgSort').value;
            const grid = document.getElementById('packagesGrid');
            const cards = Array.from(grid.querySelectorAll('.pkg-card'));
            cards.sort((a, b) => {
                if (sortVal === 'price-asc') return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                if (sortVal === 'price-desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                if (sortVal === 'name') return a.dataset.name.localeCompare(b.dataset.name);
                return 0;
            });
            cards.forEach(card => grid.appendChild(card));
            updateVisibleCount();
        }

        function updateVisibleCount() {
            const visible = document.querySelectorAll('.pkg-card:not([style*="display: none"])');
            document.getElementById('visibleCount').textContent = visible.length;
        }

        // ===== TOAST =====
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // ===== KEYBOARD SHORTCUTS =====
        document.addEventListener('keydown', function(e) {
            if ((e.key === 's' || e.key === 'S') && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                document.getElementById('pkgSearch').focus();
            }
            if ((e.key === 'r' || e.key === 'R') && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                document.getElementById('pkgSearch').value = '';
                filterPackages();
                document.getElementById('pkgSort').value = 'default';
                sortPackages();
                showToast('Filters reset!', 'info');
            }
        });

        // ===== LOAD FAVORITES =====
        document.addEventListener('DOMContentLoaded', function() {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            document.querySelectorAll('.fav-btn').forEach(btn => {
                const card = btn.closest('.pkg-card');
                if (card) {
                    const pkgId = parseInt(card.dataset.pkgId);
                    if (favorites.includes(pkgId)) {
                        btn.querySelector('i').className = 'fa-solid fa-heart';
                        btn.classList.add('active');
                    }
                }
            });
            updateVisibleCount();
        });

        console.log('✅ Packages - Book Now with Email Sending Applied!');
    </script>

</body>
</html>