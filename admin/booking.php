<?php
session_start();

// =======================================================
// 1. DATABASE CONNECTION
// =======================================================
class Database {
    private $host = "localhost";
    private $db_name = "travel_tour_db"; 
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Database Connection Error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// =======================================================
// 2. BOOKING CLASS (with auto‑confirm & email)
// =======================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/xampp2/htdocs/travel/phpmailer/src/Exception.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/PHPMailer.php';
require 'C:/xampp2/htdocs/travel/phpmailer/src/SMTP.php';

class Booking {
    private $conn;
    private $table_name = "bookings";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Auto‑confirm pending bookings and send emails.
     * Returns the number of bookings processed (emails sent).
     */
    public function autoConfirmExistingBookings() {
        $processed = 0;
        try {
            // Select all pending bookings
            $select_query = "SELECT b.id AS booking_id, u.email, u.full_name, 
                                    COALESCE(b.manual_package_name, p.title, 'Custom Tour Plan') AS pkg_name 
                             FROM " . $this->table_name . " b
                             INNER JOIN users u ON b.user_id = u.id
                             LEFT JOIN packages p ON b.package_id = p.id
                             WHERE b.status = 'pending'";
            
            $stmt_select = $this->conn->prepare($select_query);
            $stmt_select->execute();
            $pending_bookings = $stmt_select->fetchAll();

            if (count($pending_bookings) > 0) {
                // Update all pending to 'Approved'
                $update_query = "UPDATE " . $this->table_name . " SET status = 'Approved' WHERE status = 'pending'";
                $stmt_update = $this->conn->prepare($update_query);
                $stmt_update->execute();

                // Send email for each
                foreach ($pending_bookings as $row) {
                    $this->sendConfirmationEmail($row['email'], $row['full_name'], $row['pkg_name'], $row['booking_id']);
                    $processed++;
                }
            }
        } catch (Exception $e) {
            // Silent fail
        }
        return $processed;
    }

    public function sendManualEmail($booking_id) {
        try {
            $query = "SELECT b.id AS booking_id, u.email, u.full_name, 
                             COALESCE(b.manual_package_name, p.title, 'Custom Tour Plan') AS pkg_name 
                      FROM " . $this->table_name . " b
                      INNER JOIN users u ON b.user_id = u.id
                      LEFT JOIN packages p ON b.package_id = p.id
                      WHERE b.id = :booking_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row) {
                return $this->sendConfirmationEmail($row['email'], $row['full_name'], $row['pkg_name'], $row['booking_id']);
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    private function sendConfirmationEmail($user_email, $user_name, $package_name, $booking_id) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'dileepadilshan46@gmail.com';               
            $mail->Password   = 'futvipxtqvfukpcw';                  
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587;                                    

            $mail->setFrom('dileepadilshan46@gmail.com', 'Travel Admin');     
            $mail->addAddress($user_email, $user_name);     

            $mail->isHTML(true);                                        
            $mail->Subject = 'Booking Confirmed Successfully! - #' . $booking_id;                  
            
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1);'>
                        <h2 style='color: #4CAF50;'>Your Booking is Confirmed!</h2>
                        <p>Dear <b>{$user_name}</b>,</p>
                        <p>We are glad to inform you that your booking for the tour package <b>" . htmlspecialchars($package_name) . "</b> has been approved and confirmed by our system.</p>
                        <p><b>Booking ID:</b> #{$booking_id}</p>
                        <br>
                        <p>Thank you for choosing us for your journey!</p>
                        <hr style='border: 0; border-top: 1px solid #eee;'>
                        <p style='font-size: 12px; color: #777;'>This is an automated system email. Please do not reply directly to this message.</p>
                    </div>
                </div>
            ";
            
            $mail->AltBody = "Hello {$user_name}, Your booking #{$booking_id} has been confirmed.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // ============================================================
    // readAll() with REAL-TIME STATUS
    // ============================================================
    public function readAll() {
        $query = "SELECT 
                    b.id,
                    b.user_id,
                    b.package_id,
                    b.package_name,
                    b.manual_package_name,
                    b.booking_date,
                    b.status,
                    b.pickup_location,
                    b.destination_location,
                    b.calculated_distance,
                    b.total_cost,
                    b.vehicle_id,
                    b.number_of_days,
                    u.full_name AS user_name,
                    u.email AS user_email,
                    p.title AS pkg_name,
                    p.description AS pkg_description,
                    p.price AS pkg_price,
                    p.latitude AS pkg_lat,
                    p.longitude AS pkg_lng,
                    p.latitude2 AS pkg_lat2,
                    p.longitude2 AS pkg_lng2,
                    p.icon AS pkg_icon,
                    p.status AS pkg_status,
                    p.created_at AS pkg_created,
                    v.vehicle_name,
                    v.price_per_km,
                    v.capacity,
                    v.driver_name,
                    v.driver_phone,
                    v.operator_id,
                    o.name AS operator_name,
                    o.phone AS operator_phone,
                    o.email AS operator_email,
                    COALESCE(b.number_of_days, 1) as total_days,
                    CASE 
                        WHEN CURDATE() BETWEEN DATE(b.booking_date) 
                            AND DATE_ADD(DATE(b.booking_date), INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                        THEN 'Ongoing'
                        WHEN CURDATE() > DATE_ADD(DATE(b.booking_date), INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY) 
                        THEN 'Completed'
                        ELSE 'Upcoming'
                    END as realtime_status,
                    DATEDIFF(CURDATE(), DATE(b.booking_date)) + 1 as day_number,
                    DATEDIFF(DATE_ADD(DATE(b.booking_date), INTERVAL (COALESCE(b.number_of_days, 1) - 1) DAY), CURDATE()) as days_remaining
                  FROM " . $this->table_name . " b
                  INNER JOIN users u ON b.user_id = u.id 
                  LEFT JOIN packages p ON b.package_id = p.id 
                  LEFT JOIN vehicles v ON b.vehicle_id = v.id
                  LEFT JOIN operators o ON v.operator_id = o.id
                  ORDER BY b.id DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}

// =======================================================
// 3. GET ADMIN NAME
// =======================================================
$admin_name = 'Admin';
if (isset($_SESSION['admin_name']) && !empty($_SESSION['admin_name'])) {
    $admin_name = $_SESSION['admin_name'];
} elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $admin_name = $_SESSION['username'];
} elseif (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) {
    $admin_name = $_SESSION['full_name'];
}

// =======================================================
// 4. INIT
// =======================================================
$database = new Database();
$db = $database->getConnection();
$booking = new Booking($db);

$alert_message = "";
$alert_type = "";

// --- Handle manual email send ---
if (isset($_GET['send_mail_id'])) {
    $target_id = intval($_GET['send_mail_id']);
    if ($booking->sendManualEmail($target_id)) {
        $alert_message = "Email sent successfully to the client manually!";
        $alert_type = "success";
    } else {
        $alert_message = "Failed to send manual email. Please check configuration.";
        $alert_type = "error";
    }
}

// --- Auto‑confirm pending bookings & show notification (once per session) ---
$email_sent_count = 0;
if (!isset($_SESSION['auto_confirm_done'])) {
    $email_sent_count = $booking->autoConfirmExistingBookings();
    if ($email_sent_count > 0) {
        $alert_message = "✅ $email_sent_count pending booking(s) were confirmed and confirmation emails were sent.";
        $alert_type = "success";
    }
    $_SESSION['auto_confirm_done'] = true;  // prevent repeated messages on refresh
} else {
    // If the session flag is set, we still need to process pending bookings (in case new ones arrived)
    // but we won't show the alert again. We'll just run it silently.
    $booking->autoConfirmExistingBookings();
}

// ============================================================
// 5. AJAX ENDPOINT
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $stmt = $booking->readAll();
    $rows = $stmt->fetchAll();
    $data = [];
    foreach ($rows as $row) {
        $realtime_status = $row['realtime_status'] ?? 'Upcoming';
        $total_days = (int)($row['total_days'] ?? 1);
        $day_number = (int)($row['day_number'] ?? 1);
        $days_remaining = (int)($row['days_remaining'] ?? 0);
        $progress_percent = min(($day_number / $total_days) * 100, 100);
        
        if ($realtime_status == 'Ongoing') {
            $progress_html = '
                <div style="display:flex;align-items:center;gap:6px;min-width:100px;">
                    <span style="font-weight:600;font-size:11px;">Day '.$day_number.'/'.$total_days.'</span>
                    <div style="flex:1;min-width:50px;height:5px;background:var(--border-card);border-radius:6px;overflow:hidden;">
                        <div style="width:'.$progress_percent.'%;height:100%;background:linear-gradient(90deg,#0ea5e9,#10b981);border-radius:6px;transition:width 0.5s ease;"></div>
                    </div>
                    <span style="font-size:9px;color:var(--text-muted);">'.round($progress_percent).'%</span>
                </div>
            ';
        } elseif ($realtime_status == 'Completed') {
            $progress_html = '<span style="font-size:11px;color:var(--text-success);"><i class="fa-regular fa-circle-check"></i> Done</span>';
        } else {
            $progress_html = '<span style="font-size:11px;color:var(--text-muted);">Not Started</span>';
        }
        
        if ($realtime_status == 'Ongoing') {
            $status_html = '<span class="status-badge ongoing"><span class="dot-sm"></span> Ongoing';
            if ($days_remaining > 0) {
                $status_html .= ' <span style="font-size:9px;opacity:0.7;"> ('.$days_remaining.'d left)</span>';
            }
            $status_html .= '</span>';
        } elseif ($realtime_status == 'Completed') {
            $status_html = '<span class="status-badge completed"><span class="dot-sm"></span> Completed</span>';
        } else {
            $status_html = '<span class="status-badge upcoming"><span class="dot-sm"></span> Upcoming</span>';
        }
        
        $data[] = [
            'id' => $row['id'],
            'progress_html' => $progress_html,
            'status_html' => $status_html
        ];
    }
    echo json_encode(['bookings' => $data]);
    exit;
}

// ============================================================
// 6. RENDER PAGE
// ============================================================
$stmt = $booking->readAll();
$num = $stmt->rowCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Terminal | Premium Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <style>
        /* ===== CSS VARIABLES ===== */
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
            --table-header-bg: rgba(0, 0, 0, 0.04);
            --table-row-hover: rgba(0, 0, 0, 0.02);
            --admin-badge-bg: rgba(14, 165, 233, 0.12);
            --admin-badge-color: #0ea5e9;
            --modal-bg: rgba(0,0,0,0.6);
            --modal-content-bg: var(--bg-card);
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
            --table-header-bg: rgba(255, 255, 255, 0.03);
            --table-row-hover: rgba(255, 255, 255, 0.02);
            --admin-badge-bg: rgba(56, 189, 248, 0.15);
            --admin-badge-color: #38bdf8;
            --modal-bg: rgba(0,0,0,0.8);
            --modal-content-bg: var(--bg-card);
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
        .nav-links .vehicle-manager-btn::after { display: none !important; }
        .nav-links .vehicle-manager-btn i { color: #fff !important; }
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
        }
        .main-wrapper {
            padding: 24px 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            margin-bottom: 28px;
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
            position: relative;
            overflow: hidden;
        }
        .header-actions .btn .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: rippleAnim 0.8s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .header-actions .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .header-actions .btn:hover::before { opacity: 1; }
        .header-actions .btn:hover { transform: translateY(-2px); }
        .btn-ghost {
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-secondary);
            border: 1px solid var(--border-card);
        }
        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }
        .btn-primary {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(14,165,233,0.4);
        }
        .badge-sync {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.12);
            color: var(--text-success);
            font-size: 12px;
            font-weight: 600;
        }
        .badge-sync .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--text-success);
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.6); }
        }
        .badge-total {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-card);
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }
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
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-box.success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--text-success);
        }
        .alert-box.error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--text-danger);
        }
        .alert-box i { font-size: 18px; }
        .table-wrapper {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-card);
            overflow: hidden;
            backdrop-filter: blur(12px);
            transition: all var(--transition);
            box-shadow: var(--shadow-card);
        }
        .table-container {
            overflow-x: auto;
            padding: 4px 0;
        }
        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.dataTable thead th {
            padding: 16px 20px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-card);
            background: var(--table-header-bg);
            transition: all var(--transition);
        }
        table.dataTable tbody td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-card);
            color: var(--text-secondary);
            vertical-align: middle;
            transition: all var(--transition);
        }
        table.dataTable tbody tr:hover td {
            background: var(--table-row-hover);
        }
        .dt-button {
            color: var(--text-secondary) !important;
            background: var(--bg-input) !important;
            border: 1px solid var(--border-card) !important;
            border-radius: 8px !important;
            padding: 6px 14px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        .dt-button:hover {
            background: var(--table-row-hover) !important;
            color: var(--text-primary) !important;
            border-color: var(--text-accent) !important;
        }
        .dt-button.dt-button-active {
            background: var(--gradient-main) !important;
            color: #fff !important;
            border-color: transparent !important;
        }
        .dataTables_info, .dataTables_paginate { color: var(--text-muted) !important; }
        .dataTables_wrapper select option {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background: var(--bg-input) !important;
            border: 1px solid var(--border-input) !important;
            color: var(--text-primary) !important;
            border-radius: 10px !important;
            padding: 6px 12px !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            outline: none !important;
            transition: all var(--transition) !important;
        }
        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--text-accent) !important;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 12px !important;
            border-radius: 8px !important;
            border: 1px solid var(--border-card) !important;
            background: transparent !important;
            color: var(--text-muted) !important;
            transition: all 0.3s ease !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--gradient-main) !important;
            color: #fff !important;
            border-color: transparent !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--table-row-hover) !important;
            color: var(--text-primary) !important;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .status-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; }
        .status-badge.approved { background: rgba(16, 185, 129, 0.12); color: var(--text-success); }
        .status-badge.approved .dot-sm { background: var(--text-success); }
        .status-badge.confirmed { background: rgba(14, 165, 233, 0.12); color: var(--text-accent); }
        .status-badge.confirmed .dot-sm { background: var(--text-accent); }
        .status-badge.pending { background: rgba(245, 158, 11, 0.12); color: var(--text-warning); }
        .status-badge.pending .dot-sm { background: var(--text-warning); }
        .status-badge.cancelled { background: rgba(239, 68, 68, 0.12); color: var(--text-danger); }
        .status-badge.cancelled .dot-sm { background: var(--text-danger); }
        .status-badge.deleted { background: rgba(239, 68, 68, 0.08); color: var(--text-danger); }
        .status-badge.ongoing { 
            background: rgba(251, 191, 36, 0.15); 
            color: #b45309; 
            border: 1px solid rgba(251, 191, 36, 0.2); 
        }
        [data-theme="dark"] .status-badge.ongoing { 
            background: rgba(251, 191, 36, 0.2); 
            color: #fbbf24; 
        }
        .status-badge.ongoing .dot-sm { background: #f59e0b; }
        .status-badge.completed { 
            background: rgba(14, 165, 233, 0.12); 
            color: var(--text-accent); 
        }
        .status-badge.completed .dot-sm { background: var(--text-accent); }
        .status-badge.upcoming { 
            background: rgba(148, 163, 184, 0.12); 
            color: var(--text-muted); 
        }
        .status-badge.upcoming .dot-sm { background: var(--text-muted); }
        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--gradient-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            color: #fff;
            flex-shrink: 0;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            gap: 4px;
        }
        .btn-action-mail {
            background: rgba(245, 158, 11, 0.1);
            color: var(--text-warning);
            border: 1px solid rgba(245, 158, 11, 0.15);
        }
        .btn-action-mail:hover {
            background: rgba(245, 158, 11, 0.2);
            transform: translateY(-2px);
        }
        .btn-action-edit {
            background: rgba(14, 165, 233, 0.1);
            color: var(--text-accent);
            border: 1px solid rgba(14, 165, 233, 0.15);
        }
        .btn-action-edit:hover {
            background: rgba(14, 165, 233, 0.2);
            transform: translateY(-2px);
        }
        .btn-action-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--text-danger);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }
        .btn-action-delete:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
        }
        .btn-action-view {
            background: rgba(14, 165, 233, 0.1);
            color: var(--text-accent);
            border: 1px solid rgba(14, 165, 233, 0.15);
        }
        .btn-action-view:hover {
            background: rgba(14, 165, 233, 0.2);
            transform: translateY(-2px);
        }
        .actions-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .text-muted { color: var(--text-muted); }
        .text-success { color: var(--text-success); }
        .text-danger { color: var(--text-danger); }
        .text-warning { color: var(--text-warning); }
        .text-primary { color: var(--text-accent); }
        .fw-bold { font-weight: 700; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 12px; display: block; }
        .empty-state h3 { font-size: 20px; font-weight: 700; color: var(--text-primary); }
        .empty-state p { font-size: 15px; font-weight: 500; }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: var(--modal-bg);
            backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-container {
            background: var(--modal-content-bg);
            border-radius: 24px;
            max-width: 800px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            border: 1px solid var(--border-card);
            box-shadow: 0 40px 100px rgba(0,0,0,0.3);
            position: relative;
            transition: all var(--transition);
        }
        .modal-container::-webkit-scrollbar { width: 6px; }
        .modal-container::-webkit-scrollbar-thumb {
            background: var(--text-accent);
            border-radius: 10px;
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s ease;
            background: none;
            border: none;
        }
        .modal-close:hover {
            color: var(--text-danger);
            transform: rotate(90deg);
        }
        .modal-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .modal-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 20px;
        }
        .modal-divider {
            height: 1px;
            background: var(--border-card);
            margin: 16px 0;
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .modal-grid .full {
            grid-column: 1 / -1;
        }
        .modal-item {
            background: var(--bg-input);
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-card);
        }
        .modal-item .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .modal-item .value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        .modal-item .value small {
            font-weight: 400;
            color: var(--text-muted);
        }
        .modal-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .modal-badge.active { background: rgba(16, 185, 129, 0.15); color: var(--text-success); }
        .modal-badge.deleted { background: rgba(239, 68, 68, 0.15); color: var(--text-danger); }
        @media (max-width: 600px) {
            .modal-grid { grid-template-columns: 1fr; }
            .modal-container { padding: 20px; }
            .modal-title { font-size: 20px; }
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
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .main-wrapper { padding: 16px; }
            .app-header { padding: 14px 18px; flex-direction: column; align-items: flex-start; }
            .app-header .brand h1 { font-size: 20px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .header-actions .btn { padding: 8px 16px; font-size: 13px; }
            thead th, tbody td { padding: 10px 12px; font-size: 12px; }
            .actions-group { gap: 4px; }
            .btn-action { padding: 4px 8px; font-size: 10px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
        }
        @media (max-width: 480px) {
            thead th, tbody td { padding: 8px 10px; font-size: 11px; }
            .navbar-clock { font-size: 11px; padding: 4px 8px; }
        }
        @media print {
            body { background: #fff !important; color: #111 !important; padding: 10px 15px !important; font-size: 12px !important; }
            .app-header .brand h1 { -webkit-text-fill-color: #111 !important; color: #111 !important; background: none !important; font-size: 18px !important; }
            .app-header .brand span { -webkit-text-fill-color: #555 !important; color: #555 !important; font-size: 10px !important; }
            .app-header { margin-bottom: 12px !important; }
            .table-wrapper { background: #fff !important; border-color: #d1d5db !important; border-radius: 10px !important; }
            thead th { color: #374151 !important; background: #f1f5f9 !important; border-color: #d1d5db !important; font-size: 9px !important; padding: 8px 12px !important; }
            tbody td { color: #1e293b !important; border-color: #e5e7eb !important; font-size: 11px !important; padding: 6px 12px !important; }
            tbody tr:hover td { background: transparent !important; }
            .status-badge { border: 1px solid #d1d5db !important; color: #111 !important; background: #f1f5f9 !important; font-size: 9px !important; padding: 2px 10px !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .navbar-clock { display: none !important; }
            .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { display: none !important; }
            .main-wrapper { padding: 0 !important; }
            .dt-button { display: none !important; }
            .modal-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<!-- BACKGROUND VIDEO -->
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

<div class="loader" id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">Loading Bookings</div>
</div>

<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
    <div class="nav-group">
        <div class="nav-links">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php" class="active"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
        </div>
        <span class="navbar-clock" id="liveClock"><i class="fa-regular fa-clock"></i> Loading...</span>
        <div class="admin-badge">
            <i class="fa-solid fa-user-shield"></i>
            Admin: <?php echo htmlspecialchars($admin_name); ?>
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
    <a href="../index.php" class="btn-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</nav>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">

    <?php if($alert_message): ?>
        <div class="alert-box <?php echo $alert_type; ?>">
            <i class="fa-solid <?php echo $alert_type == 'success' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
            <?php echo htmlspecialchars($alert_message); ?>
        </div>
    <?php endif; ?>

    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-ticket"></i></div>
            <div>
                <h1><i class="fa-solid fa-bolt"></i> Live Booking Terminal</h1>
                <span>Real-time Ongoing / Completed status with progress tracking</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="../phpmailer/index.php" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Manual Booking
            </a>
            <span class="badge-sync"><span class="dot"></span> Sync Active</span>
            <span class="badge-total"><i class="fa-solid fa-database"></i> Logs: <?php echo $num; ?></span>
        </div>
    </header>

    <?php if($num > 0): ?>
        <div class="table-wrapper">
            <div class="table-container">
                <table id="bookingTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Package</th>
                            <th>Vehicle</th>
                            <th>Distance</th>
                            <th>Cost (LKR)</th>
                            <th>Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $realtime_status = $row['realtime_status'] ?? 'Upcoming';
                            $total_days = (int)($row['total_days'] ?? 1);
                            $day_number = (int)($row['day_number'] ?? 1);
                            $days_remaining = (int)($row['days_remaining'] ?? 0);
                            $progress_percent = min(($day_number / $total_days) * 100, 100);
                            
                            if ($realtime_status == 'Ongoing') {
                                $progress_html = '
                                    <div style="display:flex;align-items:center;gap:6px;min-width:100px;">
                                        <span style="font-weight:600;font-size:11px;">Day '.$day_number.'/'.$total_days.'</span>
                                        <div style="flex:1;min-width:50px;height:5px;background:var(--border-card);border-radius:6px;overflow:hidden;">
                                            <div style="width:'.$progress_percent.'%;height:100%;background:linear-gradient(90deg,#0ea5e9,#10b981);border-radius:6px;transition:width 0.5s ease;"></div>
                                        </div>
                                        <span style="font-size:9px;color:var(--text-muted);">'.round($progress_percent).'%</span>
                                    </div>
                                ';
                            } elseif ($realtime_status == 'Completed') {
                                $progress_html = '<span style="font-size:11px;color:var(--text-success);"><i class="fa-regular fa-circle-check"></i> Done</span>';
                            } else {
                                $progress_html = '<span style="font-size:11px;color:var(--text-muted);">Not Started</span>';
                            }
                            
                            if ($realtime_status == 'Ongoing') {
                                $status_html = '<span class="status-badge ongoing"><span class="dot-sm"></span> Ongoing';
                                if ($days_remaining > 0) {
                                    $status_html .= ' <span style="font-size:9px;opacity:0.7;"> ('.$days_remaining.'d left)</span>';
                                }
                                $status_html .= '</span>';
                            } elseif ($realtime_status == 'Completed') {
                                $status_html = '<span class="status-badge completed"><span class="dot-sm"></span> Completed</span>';
                            } else {
                                $status_html = '<span class="status-badge upcoming"><span class="dot-sm"></span> Upcoming</span>';
                            }
                        ?>
                            <tr data-id="<?php echo $row['id']; ?>">
                                <td><span class="fw-bold text-primary">#<?php echo $row['id']; ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="user-avatar"><?php echo substr($row['user_name'] ?? 'U', 0, 1); ?></div>
                                        <div>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($row['user_name'] ?? 'Unknown'); ?></div>
                                            <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($row['user_email'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-primary);">
                                        <?php echo htmlspecialchars($row['pkg_name'] ?? 'Custom Tour'); ?>
                                    </div>
                                    <div style="font-size:11px;color:var(--text-muted);">
                                        <i class="fa-solid fa-tag"></i> Rs. <?php echo number_format($row['pkg_price'] ?? 0, 2); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['vehicle_name']): ?>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($row['vehicle_name']); ?></div>
                                        <div style="font-size:11px;color:var(--text-muted);">
                                            <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($row['driver_name'] ?? 'N/A'); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:12px;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family:monospace;"><?php echo $row['calculated_distance'] ?? '0'; ?> km</td>
                                <td><span class="fw-bold text-success">Rs. <?php echo number_format($row['total_cost'] ?? 0, 2); ?></span></td>
                                <td style="font-size:12px;font-family:monospace;">
                                    <?php echo date('Y-m-d H:i', strtotime($row['booking_date'] ?? 'now')); ?>
                                </td>
                                <td><?php echo $progress_html; ?></td>
                                <td><?php echo $status_html; ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button class="btn-action btn-action-view" onclick="openModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="View Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <a href="booking.php?send_mail_id=<?php echo $row['id']; ?>" 
                                           class="btn-action btn-action-mail" title="Send Mail">
                                            <i class="fa-solid fa-envelope"></i>
                                        </a>
                                        <a href="edit_booking.php?id=<?php echo $row['id']; ?>" 
                                           class="btn-action btn-action-edit" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="delete_booking.php?id=<?php echo $row['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this booking?');" 
                                           class="btn-action btn-action-delete" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <div class="empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <h3>System Terminal Empty</h3>
                <p>No live transmission data packets caught inside the booking layer.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> Intelligence Data Center &bull; All rights reserved.</span>
        <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?></span>
    </div>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal-container">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <div class="modal-title" id="modalTitle">Booking Details</div>
        <div class="modal-subtitle" id="modalSubtitle">Complete information about this booking</div>
        <div class="modal-divider"></div>
        <div class="modal-grid">
            <div class="modal-item full">
                <div class="label"><i class="fa-solid fa-box"></i> Package</div>
                <div class="value" id="modalPackage">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-tag"></i> Price</div>
                <div class="value" id="modalPrice">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-regular fa-circle-check"></i> Status</div>
                <div class="value" id="modalPkgStatus">-</div>
            </div>
            <div class="modal-item full">
                <div class="label"><i class="fa-solid fa-align-left"></i> Description</div>
                <div class="value" id="modalDescription" style="font-weight:400;font-size:14px;color:var(--text-secondary);">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-location-dot"></i> Location (Start)</div>
                <div class="value" id="modalLat1">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-location-dot"></i> Location (End)</div>
                <div class="value" id="modalLat2">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-car"></i> Vehicle</div>
                <div class="value" id="modalVehicle">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-chair"></i> Capacity</div>
                <div class="value" id="modalCapacity">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-user"></i> Driver</div>
                <div class="value" id="modalDriver">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-phone"></i> Driver Phone</div>
                <div class="value" id="modalDriverPhone">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-user-tie"></i> Operator</div>
                <div class="value" id="modalOperator">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-phone"></i> Operator Phone</div>
                <div class="value" id="modalOperatorPhone">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-regular fa-calendar"></i> Booking Date</div>
                <div class="value" id="modalBookingDate">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-flag-checkered"></i> Booking Status</div>
                <div class="value" id="modalBookingStatus">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-map-pin"></i> Pickup</div>
                <div class="value" id="modalPickup">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-map-pin"></i> Destination</div>
                <div class="value" id="modalDestination">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-route"></i> Distance</div>
                <div class="value" id="modalDistance">-</div>
            </div>
            <div class="modal-item">
                <div class="label"><i class="fa-solid fa-money-bill-wave"></i> Total Cost</div>
                <div class="value" id="modalCost">-</div>
            </div>
            <div class="modal-item full">
                <div class="label"><i class="fa-regular fa-user"></i> Client</div>
                <div class="value" id="modalClient">-</div>
            </div>
        </div>
        <div class="modal-divider"></div>
        <div style="display:flex;justify-content:flex-end;gap:12px;">
            <button onclick="closeModal()" class="btn btn-ghost" style="padding:10px 24px;border-radius:12px;">Close</button>
        </div>
    </div>
</div>

<div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // Loader
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        setTimeout(() => { loader.classList.add("hidden"); }, 600);
        createParticles();
    });

    // Video Autoplay
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

    // Live Clock
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

    // Theme Toggle
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

    // DataTables
    $(document).ready(function() {
        $('#bookingTable').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copyHtml5', text: '<i class="fa-regular fa-copy"></i> Copy' },
                { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel"></i> Excel' },
                { extend: 'csvHtml5', text: '<i class="fa-solid fa-file-csv"></i> CSV' },
                { extend: 'print', text: '<i class="fa-solid fa-print"></i> Print' }
            ],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'No entries available',
                infoFiltered: '(filtered from _MAX_ total entries)'
            }
        });
    });

    // Ripple Effect
    document.querySelectorAll('.btn, .btn-sm').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 800);
        });
    });

    // Cursor
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

    // Scroll to Top
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // Particles
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

    // MODAL FUNCTIONS
    function openModal(data) {
        document.getElementById('modalTitle').textContent = 'Booking #' + data.id + ' - ' + (data.pkg_name || 'Custom Tour');
        document.getElementById('modalSubtitle').textContent = 'Complete information about this booking';
        
        document.getElementById('modalPackage').textContent = data.pkg_name || 'Custom Tour Plan';
        document.getElementById('modalPrice').textContent = data.pkg_price ? 'Rs. ' + parseFloat(data.pkg_price).toFixed(2) : 'N/A';
        document.getElementById('modalPkgStatus').innerHTML = data.pkg_status ? 
            '<span class="modal-badge ' + (data.pkg_status === 'Active' ? 'active' : 'deleted') + '">' + data.pkg_status + '</span>' : 'N/A';
        document.getElementById('modalDescription').textContent = data.pkg_description || 'No description available';
        document.getElementById('modalLat1').textContent = data.pkg_lat && data.pkg_lng ? data.pkg_lat + ', ' + data.pkg_lng : 'N/A';
        document.getElementById('modalLat2').textContent = data.pkg_lat2 && data.pkg_lng2 ? data.pkg_lat2 + ', ' + data.pkg_lng2 : 'N/A';
        
        document.getElementById('modalVehicle').textContent = data.vehicle_name || 'Not Assigned';
        document.getElementById('modalCapacity').textContent = data.capacity || 'N/A';
        document.getElementById('modalDriver').textContent = data.driver_name || 'N/A';
        document.getElementById('modalDriverPhone').textContent = data.driver_phone || 'N/A';
        document.getElementById('modalOperator').textContent = data.operator_name || 'N/A';
        document.getElementById('modalOperatorPhone').textContent = data.operator_phone || 'N/A';
        
        document.getElementById('modalBookingDate').textContent = data.booking_date ? new Date(data.booking_date).toLocaleString() : 'N/A';
        const realtime = data.realtime_status || 'Upcoming';
        let statusText = realtime;
        let statusClass = realtime.toLowerCase();
        document.getElementById('modalBookingStatus').innerHTML = 
            '<span class="status-badge ' + statusClass + '"><span class="dot-sm"></span> ' + statusText + '</span>';
        document.getElementById('modalPickup').textContent = data.pickup_location || 'N/A';
        document.getElementById('modalDestination').textContent = data.destination_location || 'N/A';
        document.getElementById('modalDistance').textContent = (data.calculated_distance || '0') + ' km';
        document.getElementById('modalCost').textContent = 'Rs. ' + parseFloat(data.total_cost || 0).toFixed(2);
        document.getElementById('modalClient').textContent = (data.user_name || 'Unknown') + ' (' + (data.user_email || 'No email') + ')';
        
        document.getElementById('bookingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('bookingModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    document.getElementById('bookingModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ============================================================
    // AJAX LIVE UPDATE – instead of full page reload
    // ============================================================
    function updateBookings() {
        fetch('booking.php?ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.bookings) {
                    data.bookings.forEach(booking => {
                        const row = document.querySelector(`tr[data-id="${booking.id}"]`);
                        if (row) {
                            // Progress cell (8th column)
                            const progressCell = row.querySelector('td:nth-child(8)');
                            if (progressCell) {
                                progressCell.innerHTML = booking.progress_html;
                            }
                            // Status cell (9th column)
                            const statusCell = row.querySelector('td:nth-child(9)');
                            if (statusCell) {
                                statusCell.innerHTML = booking.status_html;
                            }
                        }
                    });
                }
            })
            .catch(error => console.warn('AJAX update error:', error));
    }

    // Start live updates every 15 seconds
    setInterval(updateBookings, 15000);

    // Also run once shortly after page load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(updateBookings, 3000);
    });

    // REMOVED the old setInterval(location.reload, 60000)
</script>
</body>
</html>