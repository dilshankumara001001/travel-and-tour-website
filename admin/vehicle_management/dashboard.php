<?php
// ============================================================
// 🚗 ULTIMATE MEGA FLEET DASHBOARD (MANAGER)
// Version: 3.2.1 - Fixed reserved word alias
// ============================================================

session_start();

// ============================================================
// CONFIGURATION
// ============================================================
$config = [
    'app_name' => 'Fleet Management',
    'app_version' => '3.2.1',
    'timezone' => 'Asia/Colombo',
    'auto_refresh_interval' => 30,
    'google_maps_api_key' => 'AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw',
    'dark_mode_default' => 'light',
];
date_default_timezone_set($config['timezone']);

// ============================================================
// DATABASE CONNECTION
// ============================================================
$conn = new mysqli('localhost', 'root', '', 'travel_tour_db');
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);
$conn->set_charset("utf8");

// ============================================================
// CHECK LOGIN
// ============================================================
if (!isset($_SESSION['manager_id'])) {
    header("Location: vehicle_manager_login.php");
    exit;
}
$manager_id = $_SESSION['manager_id'];

// Fetch manager name from database (ensures correct name display)
$manager_name = 'Manager';
$name_query = $conn->query("SELECT name FROM vehicle_manager WHERE id = $manager_id");
if ($name_query && $name_query->num_rows > 0) {
    $row = $name_query->fetch_assoc();
    $manager_name = $row['name'];
    // Update session for consistency
    $_SESSION['manager_name'] = $manager_name;
} else {
    // Fallback to session if available
    $manager_name = $_SESSION['manager_name'] ?? 'Manager';
}

// ============================================================
// 📋 SIR'S REQUEST: GET ALL USERS' BOOKINGS, CHECK STATUS,
//    AND NOTIFY ADMIN IF TRIPS ARE FINISHED
// ============================================================

$completed_trips_query = "
    SELECT b.id as booking_id, b.vehicle_id, b.user_id, b.number_of_days, b.booking_date,
           v.vehicle_name, u.full_name as customer_name
    FROM bookings b
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.status IN ('Approved', 'Confirmed', 'Completed')
      AND b.vehicle_id IS NOT NULL
      AND CURDATE() > DATE_ADD(b.booking_date, INTERVAL (b.number_of_days - 1) DAY)
      AND v.status = 'Booked'
";

$completed_result = mysqli_query($conn, $completed_trips_query);
$completed_count = mysqli_num_rows($completed_result);

if ($completed_count > 0) {
    while ($trip = mysqli_fetch_assoc($completed_result)) {
        $vehicle_id = $trip['vehicle_id'];
        $vehicle_name = $trip['vehicle_name'] ?? 'Unknown Vehicle';
        $customer_name = $trip['customer_name'] ?? 'Guest';
        $booking_id = $trip['booking_id'];

        $update_vehicle = mysqli_query($conn, "
            UPDATE vehicles 
            SET status = 'Available' 
            WHERE id = $vehicle_id AND status = 'Booked'
        ");

        if ($update_vehicle && mysqli_affected_rows($conn) > 0) {
            $notif_message = "🚗 Vehicle '<strong>$vehicle_name</strong>' is now <span style='color:#10b981;font-weight:700;'>Available</span>! (Trip #$booking_id completed by $customer_name)";
            $check_notif = mysqli_query($conn, "
                SELECT id FROM admin_notifications 
                WHERE message LIKE '%$vehicle_name%' 
                  AND DATE(created_at) = CURDATE()
                  AND type = 'vehicle_available'
                LIMIT 1
            ");
            if (mysqli_num_rows($check_notif) == 0) {
                mysqli_query($conn, "
                    INSERT INTO admin_notifications (message, type, link, is_read, created_at) 
                    VALUES ('$notif_message', 'vehicle_available', 'vehicles.php', 0, NOW())
                ");
            }
        }
    }
}

// ============================================================
// HANDLE POST REQUESTS (CRUD + UPDATE STATUS)
// ============================================================
$msg = '';
$error = '';

if (isset($_POST['add_expense'])) {
    $v_id = (int)$_POST['vehicle_id'];
    $type = $conn->real_escape_string($_POST['expense_type']);
    $amount = (float)$_POST['amount'];
    $date = $conn->real_escape_string($_POST['expense_date']);
    $note = $conn->real_escape_string($_POST['note'] ?? '');
    if ($conn->query("INSERT INTO vehicle_expenses (vehicle_id, type, amount, expense_date, note) VALUES ($v_id, '$type', $amount, '$date', '$note')")) {
        $msg = "Expense added successfully!";
        $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ($manager_id, 'Added expense for vehicle $v_id', '".$_SERVER['REMOTE_ADDR']."')");
    } else {
        $error = "Failed to add expense: " . $conn->error;
    }
}

if (isset($_POST['update_maintenance'])) {
    $v_id = (int)$_POST['vehicle_id'];
    $date = $conn->real_escape_string($_POST['next_maintenance']);
    if ($conn->query("UPDATE vehicles SET next_maintenance = '$date' WHERE id = $v_id")) {
        $msg = "Maintenance date updated!";
        $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ($manager_id, 'Updated maintenance for vehicle $v_id', '".$_SERVER['REMOTE_ADDR']."')");
    } else {
        $error = "Failed to update: " . $conn->error;
    }
}

if (isset($_POST['add_driver'])) {
    $name = $conn->real_escape_string($_POST['driver_name']);
    $phone = $conn->real_escape_string($_POST['driver_phone']);
    $license = $conn->real_escape_string($_POST['license_number']);
    $license_expiry = $conn->real_escape_string($_POST['license_expiry']);
    $address = $conn->real_escape_string($_POST['driver_address']);
    $status = $conn->real_escape_string($_POST['driver_status']);
    if ($conn->query("INSERT INTO drivers (name, phone, license_number, license_expiry, address, status) VALUES ('$name', '$phone', '$license', '$license_expiry', '$address', '$status')")) {
        $msg = "Driver added successfully!";
    } else {
        $error = "Failed to add driver: " . $conn->error;
    }
}

if (isset($_POST['assign_driver'])) {
    $v_id = (int)$_POST['vehicle_id'];
    $d_id = (int)$_POST['driver_id'];
    if ($conn->query("UPDATE vehicles SET driver_id = $d_id WHERE id = $v_id") && $conn->query("UPDATE drivers SET assigned_vehicle_id = $v_id WHERE id = $d_id")) {
        $msg = "Driver assigned successfully!";
    } else {
        $error = "Failed to assign driver: " . $conn->error;
    }
}

if (isset($_POST['add_document']) && isset($_FILES['doc_file'])) {
    $v_id = (int)$_POST['vehicle_id'];
    $doc_type = $conn->real_escape_string($_POST['doc_type']);
    $doc_name = $conn->real_escape_string($_POST['doc_name']);
    $expiry = $conn->real_escape_string($_POST['doc_expiry']);
    $target_dir = "uploads/documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = time() . '_' . basename($_FILES['doc_file']['name']);
    $target_file = $target_dir . $file_name;
    if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_file)) {
        if ($conn->query("INSERT INTO vehicle_documents (vehicle_id, doc_type, doc_name, file_path, expiry_date) VALUES ($v_id, '$doc_type', '$doc_name', '$target_file', '$expiry')")) {
            $msg = "Document uploaded successfully!";
        } else {
            $error = "Failed to save: " . $conn->error;
        }
    } else {
        $error = "Failed to upload file.";
    }
}

if (isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['manager_name']);
    $email = $conn->real_escape_string($_POST['manager_email']);
    if ($conn->query("UPDATE vehicle_manager SET name='$name', email='$email' WHERE id=$manager_id")) {
        $_SESSION['manager_name'] = $name;
        $manager_name = $name;
        $msg = "Profile updated!";
    } else {
        $error = "Failed to update: " . $conn->error;
    }
}

if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $res = $conn->query("SELECT password FROM vehicle_manager WHERE id=$manager_id");
    $row = $res->fetch_assoc();
    if (sha1($old) === $row['password']) {
        $new_hash = sha1($new);
        if ($conn->query("UPDATE vehicle_manager SET password='$new_hash' WHERE id=$manager_id")) {
            $msg = "Password changed!";
        } else {
            $error = "Failed to update: " . $conn->error;
        }
    } else {
        $error = "Old password incorrect!";
    }
}

// 🆕 UPDATE VEHICLE STATUS
if (isset($_POST['update_vehicle_status'])) {
    $v_id = (int)$_POST['vehicle_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    if (in_array($new_status, ['Available', 'Booked', 'Maintenance'])) {
        if ($conn->query("UPDATE vehicles SET status = '$new_status' WHERE id = $v_id")) {
            $msg = "Vehicle status updated to '$new_status'!";
            $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ($manager_id, 'Updated vehicle $v_id status to $new_status', '".$_SERVER['REMOTE_ADDR']."')");
        } else {
            $error = "Failed to update status: " . $conn->error;
        }
    } else {
        $error = "Invalid status selected.";
    }
}

// ============================================================
// AJAX HANDLER
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $s = ['Available' => 0, 'Booked' => 0, 'Maintenance' => 0];
    $r = $conn->query("SELECT status, COUNT(*) c FROM vehicles GROUP BY status");
    while ($row = $r->fetch_assoc()) $s[$row['status']] = $row['c'];
    $total = array_sum($s);
    $rev = $conn->query("SELECT SUM(total_cost) t FROM bookings")->fetch_assoc()['t'] ?? 0;
    $exp = $conn->query("SELECT SUM(amount) t FROM vehicle_expenses")->fetch_assoc()['t'] ?? 0;
    $notif_c = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id = $manager_id AND is_read = 0")->fetch_assoc()['c'] ?? 0;
    $rb = [];
    $rq = $conn->query("SELECT b.id, b.booking_date, b.total_cost, b.number_of_members, v.vehicle_name FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id ORDER BY b.id DESC LIMIT 5");
    while ($row = $rq->fetch_assoc()) $rb[] = $row;
    $drivers = $conn->query("SELECT COUNT(*) c FROM drivers")->fetch_assoc()['c'] ?? 0;
    echo json_encode([
        'success' => true,
        'available' => $s['Available'],
        'booked' => $s['Booked'],
        'maintenance' => $s['Maintenance'],
        'total' => $total,
        'revenue' => $rev,
        'expenses' => $exp,
        'notifications' => $notif_c,
        'drivers' => $drivers,
        'recent_bookings' => $rb
    ]);
    exit;
}

// ============================================================
// FETCH ALL DATA
// ============================================================

$status = ['Available' => 0, 'Booked' => 0, 'Maintenance' => 0];
$res = $conn->query("SELECT status, COUNT(*) c FROM vehicles GROUP BY status");
while ($row = $res->fetch_assoc()) $status[$row['status']] = $row['c'];
$total_vehicles = array_sum($status);

$op_data = [];
$res = $conn->query("SELECT operator_id, COUNT(*) c FROM vehicles GROUP BY operator_id");
while ($row = $res->fetch_assoc()) $op_data[$row['operator_id'] ?? 'Unassigned'] = $row['c'];

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $l = date('M', strtotime("-$i months"));
    $d = $conn->query("SELECT COUNT(*) b, COALESCE(SUM(total_cost),0) r FROM bookings WHERE DATE_FORMAT(booking_date, '%Y-%m') = '$m'")->fetch_assoc();
    $monthly[$l] = ['bookings' => $d['b'], 'revenue' => $d['r']];
}

$today = date('Y-m-d');
$td = $conn->query("SELECT COUNT(*) b, COALESCE(SUM(total_cost),0) r FROM bookings WHERE DATE(booking_date) = '$today'")->fetch_assoc();
$today_bookings = $td['b'] ?? 0;
$today_revenue = $td['r'] ?? 0;

$month_start = date('Y-m-01');
$md = $conn->query("SELECT COUNT(*) b, COALESCE(SUM(total_cost),0) r FROM bookings WHERE booking_date >= '$month_start'")->fetch_assoc();
$month_bookings = $md['b'] ?? 0;
$month_revenue = $md['r'] ?? 0;

$tot_rev = $conn->query("SELECT SUM(total_cost) t FROM bookings")->fetch_assoc()['t'] ?? 0;
$tot_exp = $conn->query("SELECT SUM(amount) t FROM vehicle_expenses")->fetch_assoc()['t'] ?? 0;
$profit = $tot_rev - $tot_exp;
$avg_price = round($conn->query("SELECT AVG(price_per_km) a FROM vehicles")->fetch_assoc()['a'] ?? 0, 2);
$drivers_count = $conn->query("SELECT COUNT(*) c FROM drivers")->fetch_assoc()['c'] ?? 0;

$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total_cost) as total FROM bookings WHERE status IN ('Approved','Confirmed','Completed')")->fetch_assoc()['total'] ?? 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM vehicle_expenses")->fetch_assoc()['total'] ?? 0;
$profit_report = $total_revenue - $total_expenses;

$recent_bookings = [];
$res = $conn->query("SELECT 
    b.id, b.booking_date, b.total_cost, 
    b.number_of_members, 
    b.pickup_lat, b.pickup_lng, b.dest_lat, b.dest_lng,
    b.petrol_cost, b.vehicle_cost,
    v.vehicle_name,
    u.full_name as user_name
    FROM bookings b 
    LEFT JOIN vehicles v ON b.vehicle_id = v.id 
    LEFT JOIN users u ON b.user_id = u.id
    ORDER BY b.id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) $recent_bookings[] = $row;

$maintenance_alerts = [];
$res = $conn->query("SELECT id, vehicle_name, next_maintenance FROM vehicles WHERE next_maintenance IS NOT NULL AND next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY next_maintenance ASC LIMIT 5");
while ($row = $res->fetch_assoc()) $maintenance_alerts[] = $row;

$recent_expenses = [];
$res = $conn->query("SELECT e.*, v.vehicle_name FROM vehicle_expenses e LEFT JOIN vehicles v ON e.vehicle_id = v.id ORDER BY e.id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) $recent_expenses[] = $row;

$vehicles_list = [];
$res = $conn->query("SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name");
while ($row = $res->fetch_assoc()) $vehicles_list[] = $row;

$drivers_list = [];
$res = $conn->query("SELECT id, name FROM drivers ORDER BY name");
while ($row = $res->fetch_assoc()) $drivers_list[] = $row;

$notif_count = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id = $manager_id AND is_read = 0")->fetch_assoc()['c'] ?? 0;
$recent_activities = [];
$res = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 5");
while ($row = $res->fetch_assoc()) $recent_activities[] = $row;

// 🆕 FIXED: Use 'assigned_user' instead of 'current_user' (reserved word)
$all_vehicles_status = [];
$res = $conn->query("SELECT v.id, v.vehicle_name, v.status, 
    (SELECT u.full_name FROM bookings b JOIN users u ON b.user_id = u.id 
     WHERE b.vehicle_id = v.id AND b.status IN ('Approved','Confirmed','Completed') 
     ORDER BY b.id DESC LIMIT 1) as assigned_user
    FROM vehicles v
    ORDER BY v.vehicle_name");
while ($row = $res->fetch_assoc()) $all_vehicles_status[] = $row;

$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$theme = $_COOKIE['vehicle_theme'] ?? $config['dark_mode_default'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app_name'] ?> - Ultimate Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ============================================================
           THEME & GLOBAL STYLES (Full Professional UI)
           ============================================================ */
        :root {
            --bg-body: #f0f4f8;
            --bg-sidebar: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --bg-card: rgba(255,255,255,0.7);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-color: rgba(255,255,255,0.3);
            --shadow: 0 8px 32px rgba(0,0,0,0.04);
            --shadow-hover: 0 16px 48px rgba(37,99,235,0.12);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            --color-blue: #2563eb;
            --color-green: #10b981;
            --color-yellow: #f59e0b;
            --color-red: #ef4444;
            --color-purple: #8b5cf6;
            --color-teal: #14b8a6;
            --color-pink: #ec4899;
            --color-orange: #f97316;
            --gradient-primary: linear-gradient(135deg, #2563eb, #7c3aed);
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
        }
        [data-theme="dark"] {
            --bg-body: #0b1120;
            --bg-card: rgba(30,41,59,0.5);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: rgba(255,255,255,0.05);
            --shadow: 0 8px 32px rgba(0,0,0,0.3);
            --shadow-hover: 0 16px 48px rgba(37,99,235,0.2);
            --color-blue: #60a5fa;
            --color-green: #34d399;
            --color-yellow: #fbbf24;
            --color-red: #f87171;
            --color-purple: #a78bfa;
            --color-teal: #2dd4bf;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg-body); color:var(--text-primary); font-family:'Inter',sans-serif; font-size:13px; font-weight:400; transition:var(--transition); min-height:100vh; display:flex; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg-body); }
        ::-webkit-scrollbar-thumb { background:var(--color-blue); border-radius:8px; }

        /* SIDEBAR */
        .sidebar { position:fixed; left:0; top:0; height:100vh; width:260px; background:var(--bg-sidebar); padding:20px 16px; display:flex; flex-direction:column; z-index:1000; transition:var(--transition); border-right:1px solid rgba(255,255,255,0.04); overflow-y:auto; }
        .sidebar-brand { display:flex; align-items:center; gap:12px; padding-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.04); margin-bottom:16px; }
        .sidebar-brand i { font-size:28px; color:var(--color-blue); filter:drop-shadow(0 4px 12px rgba(37,99,235,0.3)); }
        .sidebar-brand span { color:#fff; font-size:18px; font-weight:800; }
        .sidebar-brand small { color:rgba(255,255,255,0.25); font-size:8px; display:block; text-transform:uppercase; letter-spacing:1px; }
        .sidebar-nav { flex:1; }
        .sidebar-nav .nav-label { color:rgba(255,255,255,0.12); font-size:8px; text-transform:uppercase; letter-spacing:1.5px; padding:12px 12px 4px; font-weight:600; }
        .sidebar-nav a { display:flex; align-items:center; gap:12px; padding:8px 12px; border-radius:10px; color:rgba(255,255,255,0.35); text-decoration:none; font-size:13px; font-weight:500; transition:var(--transition); margin-bottom:1px; }
        .sidebar-nav a i { width:20px; font-size:15px; text-align:center; }
        .sidebar-nav a:hover { color:#fff; background:rgba(255,255,255,0.05); transform:translateX(4px); }
        .sidebar-nav a.active { color:#fff; background:rgba(59,130,246,0.12); box-shadow:inset 2px 0 0 var(--color-blue); }
        .sidebar-nav a .badge-nav { margin-left:auto; background:var(--color-red); color:#fff; font-size:8px; padding:1px 8px; border-radius:20px; }
        .sidebar-nav .logout-link { margin-top:auto; border-top:1px solid rgba(255,255,255,0.04); padding-top:12px; color:rgba(255,255,255,0.2); }
        .sidebar-nav .logout-link:hover { color:var(--color-red); background:rgba(239,68,68,0.08); }
        .hamburger { display:none; position:fixed; top:12px; left:12px; z-index:1001; background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:10px; padding:6px 10px; cursor:pointer; font-size:18px; color:var(--text-primary); box-shadow:var(--shadow); }

        /* MAIN CONTENT */
        .main-content { margin-left:260px; flex:1; padding:16px 24px 24px; min-height:100vh; width:calc(100% - 260px); transition:var(--transition); }
        .top-header { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; background:var(--bg-card); backdrop-filter:blur(20px); border:1px solid var(--border-color); border-radius:var(--radius); box-shadow:var(--shadow); margin-bottom:16px; flex-wrap:wrap; gap:10px; }
        .top-header .greeting h4 { font-weight:800; font-size:16px; color:var(--text-primary); margin:0; }
        .top-header .greeting h4 span { background:var(--gradient-primary); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .top-header .greeting p { font-size:11px; color:var(--text-muted); margin:0; }
        .header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

        /* CLOCK & BUTTONS */
        .premium-clock { display:flex; align-items:center; gap:8px; padding:4px 12px 4px 8px; background:var(--bg-body); border-radius:30px; border:1px solid var(--border-color); font-size:11px; }
        .premium-clock .dot { width:6px; height:6px; border-radius:50%; background:var(--color-green); animation:pulse-dot 1.5s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.3;transform:scale(0.6)} }
        .premium-clock .time { font-weight:700; font-size:12px; color:var(--text-primary); }
        .premium-clock .date { color:var(--text-muted); font-size:10px; }
        .notif-bell { position:relative; font-size:18px; color:var(--text-muted); cursor:pointer; transition:0.3s; padding:4px; }
        .notif-bell:hover { color:var(--text-primary); transform:scale(1.1); }
        .notif-bell .badge-count { position:absolute; top:-4px; right:-4px; background:var(--color-red); color:#fff; font-size:7px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--bg-card); }
        .theme-toggle-premium { width:36px; height:18px; background:var(--bg-body); border:1px solid var(--border-color); border-radius:9px; cursor:pointer; position:relative; transition:var(--transition); flex-shrink:0; }
        .theme-toggle-premium .dot { position:absolute; top:1px; left:1px; width:14px; height:14px; background:var(--color-blue); border-radius:50%; transition:var(--transition); box-shadow:0 2px 8px rgba(37,99,235,0.2); }
        .theme-toggle-premium.active .dot { left:19px; }
        .theme-toggle-premium .icon { position:absolute; top:50%; transform:translateY(-50%); font-size:7px; color:var(--text-muted); }
        .theme-toggle-premium .icon-left { left:3px; }
        .theme-toggle-premium .icon-right { right:3px; }
        .theme-toggle-premium.active .icon-left { opacity:0.3; }
        .theme-toggle-premium.active .icon-right { color:var(--color-yellow); }
        .profile-avatar-premium { width:32px; height:32px; border-radius:50%; background:var(--gradient-primary); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:12px; cursor:pointer; border:2px solid var(--border-color); transition:var(--transition); position:relative; }
        .profile-avatar-premium .status-dot { position:absolute; bottom:-1px; right:-1px; width:8px; height:8px; border-radius:50%; background:var(--color-green); border:2px solid var(--bg-card); }

        /* STATS GRID */
        .stat-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:16px; }
        .stat-card-premium { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:var(--radius); padding:14px 16px; box-shadow:var(--shadow); transition:var(--transition); display:flex; align-items:center; gap:12px; position:relative; overflow:hidden; }
        .stat-card-premium::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; border-radius:3px 0 0 3px; }
        .stat-card-premium:hover { transform:translateY(-3px); border-color:var(--color-blue); box-shadow:var(--shadow-hover); }
        .stat-card-premium .icon-wrap { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; flex-shrink:0; }
        .stat-card-premium .icon-wrap.green { background:var(--gradient-success); }
        .stat-card-premium .icon-wrap.yellow { background:var(--gradient-warning); }
        .stat-card-premium .icon-wrap.red { background:var(--gradient-danger); }
        .stat-card-premium .icon-wrap.blue { background:var(--gradient-primary); }
        .stat-card-premium .icon-wrap.purple { background:linear-gradient(135deg, #ec4899, #f472b6); }
        .stat-card-premium .icon-wrap.teal { background:linear-gradient(135deg, #14b8a6, #2dd4bf); }
        .stat-card-premium .info .number { font-size:1.4rem; font-weight:800; color:var(--text-primary); line-height:1.2; }
        .stat-card-premium .info .label { font-size:10px; color:var(--text-muted); font-weight:500; }

        /* SUMMARY ROW */
        .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
        .summary-card { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:var(--radius); padding:12px 16px; text-align:center; box-shadow:var(--shadow); transition:var(--transition); }
        .summary-card:hover { transform:translateY(-2px); border-color:var(--color-blue); }
        .summary-card small { color:var(--text-muted); font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .summary-card h5 { font-weight:800; font-size:1.1rem; margin:4px 0 0; }
        .text-green { color:var(--color-green); } .text-blue { color:var(--color-blue); } .text-red { color:var(--color-red); }

        /* ============================================================
           📊 REPORTS OVERVIEW CARD STYLES
        ============================================================ */
        .report-card-mini {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .report-card-mini:hover {
            border-color: var(--color-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        .report-card-mini .number {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.2;
        }
        .report-card-mini .label {
            font-size: 9px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .report-card-mini .icon-mini {
            font-size: 24px;
            opacity: 0.06;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* MAIN GRID */
        .main-grid { display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px; }
        .card-premium { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); border-radius:var(--radius); padding:16px 18px; box-shadow:var(--shadow); transition:var(--transition); }
        .card-premium:hover { border-color:rgba(37,99,235,0.12); box-shadow:var(--shadow-hover); }
        .card-premium .head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .card-premium .head h5 { font-weight:700; font-size:13px; color:var(--text-primary); margin:0; }
        .card-premium .head h5 i { margin-right:6px; color:var(--color-blue); }
        .card-premium .head small { font-size:10px; color:var(--text-muted); }
        .chart-container { height:110px; background:rgba(255,255,255,0.1); border-radius:10px; padding:4px; }

        .financial-item { display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid var(--border-color); }
        .financial-item:last-child { border-bottom:none; }
        .financial-item .label { font-size:11px; color:var(--text-muted); }
        .financial-item .value { font-weight:700; font-size:12px; color:var(--text-primary); }
        .financial-item .value.green { color:var(--color-green); }
        .financial-item .value.blue { color:var(--color-blue); }
        .financial-item .value.red { color:var(--color-red); }

        /* FEED ITEMS (Recent Bookings) */
        .feed-item { display:flex; align-items:center; gap:10px; padding:6px 10px; border-radius:8px; background:rgba(0,0,0,0.02); margin-bottom:4px; border-left:3px solid var(--color-blue); transition:0.2s; }
        .feed-item:hover { background:rgba(0,0,0,0.04); }
        .feed-item .icon-sm { width:26px; height:26px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; font-size:10px; }
        .feed-item .content { flex:1; min-width:0; }
        .feed-item .content .title { font-weight:600; font-size:12px; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .feed-item .content .meta { font-size:10px; color:var(--text-muted); display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
        .feed-item .content .meta .badge-sm { background:var(--border-color); padding:1px 6px; border-radius:4px; font-size:8px; font-weight:700; color:var(--text-secondary); }
        .btn-map-sm { background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.15); color:var(--color-blue); font-size:8px; padding:2px 8px; border-radius:6px; cursor:pointer; transition:var(--transition); font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:3px; }
        .btn-map-sm:hover { background:rgba(59,130,246,0.2); transform:scale(1.05); }

        /* QUICK ACTIONS */
        .quick-grid-premium { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-top:4px; }
        .quick-btn-premium { background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; padding:10px 8px; text-align:center; text-decoration:none; color:var(--text-primary); transition:var(--transition); box-shadow:var(--shadow); }
        .quick-btn-premium:hover { transform:translateY(-3px); border-color:var(--color-blue); box-shadow:var(--shadow-hover); }
        .quick-btn-premium i { font-size:18px; display:block; margin-bottom:3px; }
        .quick-btn-premium i.blue { color:var(--color-blue); }
        .quick-btn-premium i.green { color:var(--color-green); }
        .quick-btn-premium i.yellow { color:var(--color-yellow); }
        .quick-btn-premium i.purple { color:var(--color-purple); }
        .quick-btn-premium i.orange { color:var(--color-orange); }
        .quick-btn-premium i.pink { color:var(--color-pink); }
        .quick-btn-premium span { font-size:10px; font-weight:600; }

        /* MAP MODAL */
        .map-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter:blur(12px); z-index:9999; justify-content:center; align-items:center; }
        .map-modal.active { display:flex; }
        .map-modal-content { background:var(--bg-card); backdrop-filter:blur(12px); border-radius:24px; border:1px solid var(--border-color); width:92%; max-width:880px; max-height:92vh; overflow:hidden; box-shadow:0 50px 100px rgba(0,0,0,0.4); }
        .map-modal-header { padding:20px 24px 16px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; }
        .map-modal-header h3 { font-size:18px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:12px; }
        .map-modal-header h3 i { color:var(--color-blue); }
        .map-modal-close { background:rgba(239,68,68,0.1); border:none; color:var(--color-red); width:36px; height:36px; border-radius:50%; font-size:18px; cursor:pointer; transition:var(--transition); display:flex; align-items:center; justify-content:center; }
        .map-modal-close:hover { background:rgba(239,68,68,0.2); transform:rotate(90deg) scale(1.05); }
        .map-modal-body { padding:20px 24px 24px; }
        .map-loc-info { display:flex; gap:20px; margin-bottom:16px; flex-wrap:wrap; font-size:14px; color:var(--text-secondary); }
        .map-loc-info span { background:var(--bg-body); padding:6px 16px; border-radius:8px; border:1px solid var(--border-color); font-size:13px; }
        .map-container-modal { width:100%; height:380px; border-radius:16px; overflow:hidden; background:var(--bg-body); border:1px solid var(--border-color); }
        #map-modal-canvas { width:100%; height:100%; }

        .footer-premium { margin-top:16px; padding:10px 0; border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:10px; color:var(--text-muted); }
        .footer-premium .heart { color:var(--color-red); }

        /* MODALS */
        .modal-content { background:var(--bg-card); backdrop-filter:blur(12px); border:1px solid var(--border-color); color:var(--text-primary); }
        .modal-header { border-bottom:1px solid var(--border-color); }
        .modal-footer { border-top:1px solid var(--border-color); }
        .form-control, .form-select { background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-primary); font-size:12px; }
        .form-control:focus, .form-select:focus { box-shadow:0 0 0 3px rgba(37,99,235,0.12); border-color:var(--color-blue); }
        .form-label { font-size:11px; font-weight:600; color:var(--text-secondary); }
        .btn-primary { background:var(--gradient-primary); border:none; color:#fff; font-weight:600; font-size:12px; padding:8px 20px; border-radius:10px; transition:var(--transition); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(37,99,235,0.25); }
        .btn-success { background:var(--gradient-success); border:none; color:#fff; font-weight:600; font-size:12px; padding:8px 20px; border-radius:10px; transition:var(--transition); }
        .btn-success:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.25); }
        .btn-danger { background:var(--gradient-danger); border:none; color:#fff; font-weight:600; font-size:12px; padding:8px 20px; border-radius:10px; transition:var(--transition); }
        .btn-danger:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(239,68,68,0.25); }

        /* 🆕 Vehicle Status Table (within dashboard) */
        .vehicle-status-table { width:100%; font-size:11px; }
        .vehicle-status-table td { padding:4px 6px; vertical-align:middle; border-bottom:1px solid var(--border-color); }
        .vehicle-status-table .status-badge { padding:2px 10px; border-radius:12px; font-size:9px; font-weight:700; color:#fff; display:inline-block; }
        .status-badge.available { background:var(--color-green); }
        .status-badge.booked { background:var(--color-blue); }
        .status-badge.maintenance { background:var(--color-yellow); color:#000; }
        .status-badge.unknown { background:var(--text-muted); }

        @media (max-width:1200px) { .stat-grid { grid-template-columns:repeat(3,1fr); } .quick-grid-premium { grid-template-columns:repeat(3,1fr); } .summary-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:992px) { .sidebar { transform:translateX(-100%); width:280px; } .sidebar.open { transform:translateX(0); } .main-content { margin-left:0; width:100%; padding:12px; } .hamburger { display:block; } .stat-grid { grid-template-columns:repeat(2,1fr); } .main-grid { grid-template-columns:1fr; } }
        @media (max-width:576px) { .stat-grid { grid-template-columns:1fr; } .top-header { flex-direction:column; align-items:stretch; } .header-actions { justify-content:space-between; } .summary-grid { grid-template-columns:1fr; } .quick-grid-premium { grid-template-columns:1fr 1fr; } .map-container-modal { height:280px; } }
    </style>
</head>
<body>

<!-- ============================================================
SIDEBAR
============================================================ -->
<button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-car-side"></i>
        <div><span><?= $config['app_name'] ?></span><small>v<?= $config['app_version'] ?></small></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Navigation</div>
        <a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="vehicles.php"><i class="fas fa-list"></i> Vehicles <span class="badge-nav"><?= $total_vehicles ?></span></a>
        <a href="filter.php"><i class="fas fa-filter"></i> Filter</a>
        <a href="update.php"><i class="fas fa-edit"></i> Update</a>
        <a href="add_vehicle.php"><i class="fas fa-plus-circle"></i> Add Vehicle</a>
        <a href="drivers.php"><i class="fas fa-user"></i> Drivers <span class="badge-nav"><?= $drivers_count ?></span></a>
        <div class="nav-label" style="margin-top:8px;">Management</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-coins"></i> Add Expense</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal"><i class="fas fa-tools"></i> Maintenance</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#documentModal"><i class="fas fa-file"></i> Documents</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="fas fa-sync-alt"></i> Update Status</a>
        <div class="nav-label" style="margin-top:8px;">Tools</div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user-cog"></i> Profile</a>
        <a href="#" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="#" onclick="toggleFullscreen()"><i class="fas fa-expand"></i> Full Screen</a>
        <a href="#" onclick="refreshData()"><i class="fas fa-sync"></i> Refresh</a>
        <a href="../index.php?action=logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ============================================================
MAIN CONTENT
============================================================ -->
<main class="main-content" id="dashboardContent">

    <!-- TOP HEADER -->
    <header class="top-header">
        <div class="greeting">
            <h4><span><?= $greeting ?></span>, <?= htmlspecialchars($manager_name) ?> 👋</h4>
            <p><i class="fas fa-flag-checkered"></i> Ultimate Fleet Command Center</p>
        </div>
        <div class="header-actions">
            <div class="premium-clock"><span class="dot"></span><span class="time" id="liveTime">--:--:--</span><span class="date" id="liveDate">-- --- ----</span></div>
            <div class="notif-bell" onclick="showNotifications()"><i class="fas fa-bell"></i><span class="badge-count" id="notifCount"><?= $notif_count ?></span></div>
            <div class="theme-toggle-premium" id="themeToggle"><span class="icon icon-left"><i class="fas fa-sun"></i></span><span class="icon icon-right"><i class="fas fa-moon"></i></span><div class="dot"></div></div>
            <div class="profile-avatar-premium" id="profileToggle"><?= strtoupper(substr($manager_name,0,1)) ?><span class="status-dot"></span></div>
        </div>
    </header>

    <!-- STATS CARDS -->
    <div class="stat-grid" id="statsGrid">
        <div class="stat-card-premium"><div class="icon-wrap green"><i class="fas fa-check-circle"></i></div><div class="info"><div class="number" id="statAvailable"><?= $status['Available'] ?? 0 ?></div><div class="label">Available</div></div></div>
        <div class="stat-card-premium"><div class="icon-wrap yellow"><i class="fas fa-bookmark"></i></div><div class="info"><div class="number" id="statBooked"><?= $status['Booked'] ?? 0 ?></div><div class="label">Booked</div></div></div>
        <div class="stat-card-premium"><div class="icon-wrap red"><i class="fas fa-tools"></i></div><div class="info"><div class="number" id="statMaintenance"><?= $status['Maintenance'] ?? 0 ?></div><div class="label">Maintenance</div></div></div>
        <div class="stat-card-premium"><div class="icon-wrap blue"><i class="fas fa-flag-checkered"></i></div><div class="info"><div class="number" id="statTotal"><?= $total_vehicles ?></div><div class="label">Total Fleet</div></div></div>
        <div class="stat-card-premium"><div class="icon-wrap purple"><i class="fas fa-user"></i></div><div class="info"><div class="number" id="statDrivers"><?= $drivers_count ?></div><div class="label">Drivers</div></div></div>
        <div class="stat-card-premium"><div class="icon-wrap teal"><i class="fas fa-credit-card"></i></div><div class="info"><div class="number" id="statRevenue">LKR <?= number_format($tot_rev,0) ?></div><div class="label">Revenue</div></div></div>
    </div>

    <!-- SUMMARY ROW -->
    <div class="summary-grid">
        <div class="summary-card"><small class="text-muted">Today</small><h5 class="text-green">LKR <?= number_format($today_revenue,0) ?></h5><small><?= $today_bookings ?> bookings</small></div>
        <div class="summary-card"><small class="text-muted">This Month</small><h5 class="text-blue">LKR <?= number_format($month_revenue,0) ?></h5><small><?= $month_bookings ?> bookings</small></div>
        <div class="summary-card"><small class="text-muted">Total Revenue</small><h5 class="text-green">LKR <?= number_format($tot_rev,0) ?></h5></div>
        <div class="summary-card"><small class="text-muted">Profit/Loss</small><h5 class="<?= $profit >= 0 ? 'text-green' : 'text-red' ?>">LKR <?= number_format($profit,0) ?></h5></div>
    </div>

    <!-- ============================================================
    📊 REPORTS OVERVIEW
    ============================================================ -->
    <div class="card-premium" style="margin-bottom:16px;">
        <div class="head">
            <h5><i class="fas fa-chart-line" style="color:var(--color-purple);"></i> Reports Overview</h5>
            <small>Fleet analytics at a glance</small>
        </div>
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="report-card-mini">
                    <div class="icon-mini"><i class="fas fa-credit-card" style="color:var(--color-green);"></i></div>
                    <div class="number" style="color:var(--color-green);">LKR <?= number_format($total_revenue, 0) ?></div>
                    <div class="label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="report-card-mini">
                    <div class="icon-mini"><i class="fas fa-wallet" style="color:var(--color-red);"></i></div>
                    <div class="number" style="color:var(--color-red);">LKR <?= number_format($total_expenses, 0) ?></div>
                    <div class="label">Total Expenses</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="report-card-mini">
                    <div class="icon-mini"><i class="fas fa-chart-line" style="color:<?= $profit_report >= 0 ? 'var(--color-green)' : 'var(--color-red)' ?>;"></i></div>
                    <div class="number" style="color:<?= $profit_report >= 0 ? 'var(--color-green)' : 'var(--color-red)' ?>;">LKR <?= number_format($profit_report, 0) ?></div>
                    <div class="label">Profit / Loss</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="report-card-mini">
                    <div class="icon-mini"><i class="fas fa-ticket" style="color:var(--color-blue);"></i></div>
                    <div class="number" style="color:var(--color-blue);"><?= $total_bookings ?></div>
                    <div class="label">Total Bookings</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    🆕 VEHICLE STATUS OVERVIEW (NEW SECTION)
    ============================================================ -->
    <div class="card-premium" style="margin-bottom:16px;">
        <div class="head">
            <h5><i class="fas fa-car" style="color:var(--color-blue);"></i> Vehicle Status & Assigned Users</h5>
            <small>Click status to update</small>
        </div>
        <div style="max-height:200px; overflow-y:auto;">
            <table class="vehicle-status-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Current User (if Booked)</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_vehicles_status as $v): 
                        $statusClass = strtolower($v['status']);
                        // 🔥 FIXED: use 'assigned_user' instead of 'current_user'
                        $userDisplay = ($v['status'] == 'Booked' && !empty($v['assigned_user'])) ? htmlspecialchars($v['assigned_user']) : '—';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['vehicle_name']) ?></strong></td>
                        <td><span class="status-badge <?= $statusClass ?>"><?= $v['status'] ?></span></td>
                        <td><?= $userDisplay ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-outline-primary" style="font-size:9px; padding:1px 10px;" onclick="openStatusModal(<?= $v['id'] ?>, '<?= $v['status'] ?>')">
                                <i class="fas fa-edit"></i> Change
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($all_vehicles_status)): ?>
                    <tr><td colspan="4" class="text-muted text-center">No vehicles found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MAIN GRID: CHART + FINANCIALS -->
    <div class="main-grid">
        <div class="card-premium">
            <div class="head"><h5><i class="fas fa-chart-area"></i> Monthly Revenue</h5><small>Last 6 months</small></div>
            <div class="chart-container"><canvas id="revenueChart"></canvas></div>
        </div>
        <div class="card-premium">
            <div class="head"><h5><i class="fas fa-coins" style="color:var(--color-yellow);"></i> Financial Overview</h5></div>
            <div class="financial-item"><span class="label"><i class="fas fa-receipt"></i> Revenue</span><span class="value green">LKR <?= number_format($tot_rev,0) ?></span></div>
            <div class="financial-item"><span class="label"><i class="fas fa-wallet"></i> Expenses</span><span class="value red">LKR <?= number_format($tot_exp,0) ?></span></div>
            <div class="financial-item"><span class="label"><i class="fas fa-chart-line"></i> Profit</span><span class="value <?= $profit >= 0 ? 'green' : 'red' ?>">LKR <?= number_format($profit,0) ?></span></div>
            <div class="financial-item"><span class="label"><i class="fas fa-car"></i> Avg Price/KM</span><span class="value blue">LKR <?= number_format($avg_price,2) ?></span></div>
            <div class="mt-1"><small class="text-muted">Operators</small>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <?php foreach ($op_data as $op=>$c): $col = is_numeric($op) ? ['#10b981','#3b82f6','#f59e0b'][($op-1)%3] : '#ef4444'; ?>
                        <span class="badge" style="background:<?= $col ?>;color:#fff;font-size:8px;padding:1px 8px;"><?= is_numeric($op)?'Op '.$op:'Unassigned' ?>: <?= $c ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    ACTIVITY FEED (3 COLUMN)
    ============================================================ -->
    <div class="row g-3 mb-3">
        <!-- Expenses -->
        <div class="col-md-4">
            <div class="card-premium">
                <div class="head"><h5><i class="fas fa-receipt" style="color:var(--color-purple);"></i> Expenses</h5><a href="#" data-bs-toggle="modal" data-bs-target="#expenseModal" class="text-decoration-none" style="font-size:10px;">+ Add</a></div>
                <?php if(count($recent_expenses)>0): foreach($recent_expenses as $e): ?>
                <div class="feed-item" style="border-left-color:var(--color-red);">
                    <div class="icon-sm" style="background:var(--gradient-danger);"><i class="fas fa-receipt"></i></div>
                    <div class="content"><div class="title"><?= htmlspecialchars($e['vehicle_name']??'N/A') ?></div><div class="meta">LKR <?= number_format($e['amount'],2) ?> · <?= date('d M',strtotime($e['expense_date'])) ?></div></div>
                    <span class="badge bg-danger" style="font-size:8px;"><?= $e['type'] ?></span>
                </div>
                <?php endforeach; else: ?><p class="text-muted text-center py-2" style="font-size:11px;">No expenses</p><?php endif; ?>
            </div>
        </div>

        <!-- Maintenance -->
        <div class="col-md-4">
            <div class="card-premium">
                <div class="head"><h5><i class="fas fa-tools" style="color:var(--color-yellow);"></i> Maintenance</h5><a href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal" class="text-decoration-none" style="font-size:10px;">Schedule</a></div>
                <?php if(count($maintenance_alerts)>0): foreach($maintenance_alerts as $a): 
                    $days = (strtotime($a['next_maintenance']) - time()) / 86400;
                    $color = $days < 0 ? 'var(--color-red)' : ($days < 3 ? 'var(--color-yellow)' : 'var(--color-green)');
                ?>
                <div class="feed-item" style="border-left-color:<?= $color ?>;">
                    <div class="icon-sm" style="background:<?= $color ?>;"><i class="fas fa-wrench"></i></div>
                    <div class="content"><div class="title"><?= htmlspecialchars($a['vehicle_name']) ?></div><div class="meta">Due: <?= date('d M',strtotime($a['next_maintenance'])) ?></div></div>
                    <span class="badge" style="background:<?= $color ?>;color:#fff;font-size:8px;"><?= $days < 0 ? 'Overdue' : ($days < 3 ? 'Soon' : 'Upcoming') ?></span>
                </div>
                <?php endforeach; else: ?><p class="text-muted text-center py-2" style="font-size:11px;"><i class="fas fa-check-circle text-green"></i> All good</p><?php endif; ?>
            </div>
        </div>

        <!-- RECENT BOOKINGS (Displays User Name) -->
        <div class="col-md-4">
            <div class="card-premium">
                <div class="head"><h5><i class="fas fa-clock text-green"></i> Recent Bookings</h5><a href="vehicles.php" class="text-decoration-none" style="font-size:10px;">View</a></div>
                <?php if(count($recent_bookings)>0): foreach($recent_bookings as $b):
                    $has_coords = (!empty($b['pickup_lat']) && !empty($b['pickup_lng']) && !empty($b['dest_lat']) && !empty($b['dest_lng']));
                ?>
                <div class="feed-item" style="border-left-color:var(--color-green);">
                    <div class="icon-sm" style="background:var(--gradient-success);"><i class="fas fa-check"></i></div>
                    <div class="content">
                        <div class="title">#<?= $b['id'] ?> - <?= htmlspecialchars($b['vehicle_name']??'N/A') ?></div>
                        <div class="meta">
                            <span>👤 <?= htmlspecialchars($b['user_name'] ?? 'Guest') ?></span>
                            <span>👥 <?= $b['number_of_members'] ?? 1 ?></span>
                            <span class="badge-sm">LKR <?= number_format($b['total_cost']??0,0) ?></span>
                            <span class="badge-sm"><?= date('d M',strtotime($b['booking_date'])) ?></span>
                            <?php if($has_coords): ?>
                                <button class="btn-map-sm" onclick="openMapModal(
                                    '<?= $b['pickup_lat'] ?>','<?= $b['pickup_lng'] ?>',
                                    '<?= $b['dest_lat'] ?>','<?= $b['dest_lng'] ?>',
                                    '<?= htmlspecialchars($b['vehicle_name']??'Booking') ?>',
                                    <?= $b['id'] ?>
                                )"><i class="fas fa-map-pin"></i> Map</button>
                            <?php endif; ?>
                            <?php if(isset($b['petrol_cost']) && $b['petrol_cost'] > 0): ?>
                                <span class="badge-sm" style="background:rgba(245,158,11,0.2);color:var(--color-yellow);">⛽ LKR <?= number_format($b['petrol_cost'],0) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge bg-primary" style="font-size:8px;">#<?= $b['id'] ?></span>
                </div>
                <?php endforeach; else: ?><p class="text-muted text-center py-2" style="font-size:11px;">No bookings</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS (added Update Status button) -->
    <div class="quick-grid-premium">
        <a href="vehicles.php" class="quick-btn-premium"><i class="fas fa-list blue"></i><span>Vehicles</span></a>
        <a href="add_vehicle.php" class="quick-btn-premium"><i class="fas fa-plus-circle green"></i><span>Add</span></a>
        <a href="drivers.php" class="quick-btn-premium"><i class="fas fa-user purple"></i><span>Drivers</span></a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#expenseModal" class="quick-btn-premium"><i class="fas fa-coins orange"></i><span>Expense</span></a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal" class="quick-btn-premium"><i class="fas fa-tools yellow"></i><span>Service</span></a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#statusModal" class="quick-btn-premium"><i class="fas fa-sync-alt" style="color:var(--color-blue);"></i><span>Status</span></a>
        <a href="reports.php" class="quick-btn-premium"><i class="fas fa-file-alt" style="color:var(--color-blue);"></i><span>Reports</span></a>
    </div>

    <footer class="footer-premium">
        <span>© <?= date('Y') ?> <?= $config['app_name'] ?> · v<?= $config['app_version'] ?></span>
        <span><i class="fas fa-heart heart"></i> <span id="footerDateTime"></span></span>
    </footer>

</main>

<!-- ============================================================
MODALS (including new Status Update Modal)
============================================================ -->
<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-coins"></i> Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Type</label><select name="expense_type" class="form-select"><option value="fuel">Fuel</option><option value="repair">Repair</option><option value="insurance">Insurance</option><option value="tax">Tax</option><option value="other">Other</option></select></div>
        <div class="mb-2"><label class="form-label">Amount (LKR)</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-2"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
        <input type="hidden" name="add_expense" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form>
</div></div></div>

<!-- Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-tools"></i> Schedule Maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" class="form-control" required></div>
        <input type="hidden" name="update_maintenance" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-warning">Update</button></div></form>
</div></div></div>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file"></i> Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select" required><?php foreach($vehicles_list as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Document Type</label><select name="doc_type" class="form-select"><option value="insurance">Insurance</option><option value="registration">Registration</option><option value="emission">Emission Test</option><option value="other">Other</option></select></div>
        <div class="mb-2"><label class="form-label">Document Name</label><input type="text" name="doc_name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Expiry Date</label><input type="date" name="doc_expiry" class="form-control"></div>
        <div class="mb-2"><label class="form-label">File</label><input type="file" name="doc_file" class="form-control" required></div>
        <input type="hidden" name="add_document" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Upload</button></div></form>
</div></div></div>

<!-- 🆕 UPDATE VEHICLE STATUS MODAL -->
<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-sync-alt"></i> Update Vehicle Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" id="statusForm">
        <div class="modal-body">
            <div class="mb-2"><label class="form-label">Select Vehicle</label>
                <select name="vehicle_id" id="statusVehicleSelect" class="form-select" required>
                    <?php foreach($vehicles_list as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vehicle_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2"><label class="form-label">New Status</label>
                <select name="new_status" class="form-select" required>
                    <option value="Available">Available</option>
                    <option value="Booked">Booked</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
            <input type="hidden" name="update_vehicle_status" value="1">
            <div class="alert alert-info" style="font-size:10px; margin-top:8px;">
                <i class="fas fa-info-circle"></i> Changing status will update the vehicle record. For 'Booked', ensure a booking exists.
            </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Update Status</button></div>
    </form>
</div></div></div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-cog"></i> Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <div class="mb-2"><label class="form-label">Name</label><input type="text" name="manager_name" class="form-control" value="<?= htmlspecialchars($manager_name) ?>" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="manager_email" class="form-control" value="<?= htmlspecialchars($_SESSION['manager_email'] ?? '') ?>" required></div>
        <hr><h6>Change Password</h6>
        <div class="mb-2"><label class="form-label">Old Password</label><input type="password" name="old_password" class="form-control" placeholder="Enter old"></div>
        <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Enter new"></div>
        <input type="hidden" name="update_profile" value="1">
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form>
</div></div></div>

<!-- Notification Modal -->
<div class="modal fade" id="notifModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-bell"></i> Notifications</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="notifBody">
        <div class="feed-item" style="border-left-color:var(--color-blue);"><div class="icon-sm" style="background:var(--gradient-primary);"><i class="fas fa-info"></i></div><div class="content"><div class="title">Welcome to Ultimate Dashboard!</div><div class="meta">All features loaded</div></div></div>
        <div class="feed-item" style="border-left-color:var(--color-green);"><div class="icon-sm" style="background:var(--gradient-success);"><i class="fas fa-check"></i></div><div class="content"><div class="title">System Operational</div><div class="meta">No critical issues</div></div></div>
        <?php if(count($maintenance_alerts)>0): ?>
        <div class="feed-item" style="border-left-color:var(--color-yellow);"><div class="icon-sm" style="background:var(--gradient-warning);"><i class="fas fa-tools"></i></div><div class="content"><div class="title">Maintenance Alerts</div><div class="meta"><?= count($maintenance_alerts) ?> vehicles due</div></div></div>
        <?php endif; ?>
    </div>
</div></div></div>

<!-- MAP MODAL -->
<div class="map-modal" id="mapModal">
    <div class="map-modal-content">
        <div class="map-modal-header">
            <h3><i class="fas fa-map"></i> <span id="modal-map-title">Route Map</span></h3>
            <button class="map-modal-close" onclick="closeMapModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="map-modal-body">
            <div class="map-loc-info">
                <span><i class="fas fa-location-dot" style="color:var(--color-blue);"></i> <strong>Pickup:</strong> <span id="modal-pickup-coord">-</span></span>
                <span><i class="fas fa-flag" style="color:#ef4444;"></i> <strong>Destination:</strong> <span id="modal-dest-coord">-</span></span>
            </div>
            <div class="map-container-modal"><div id="map-modal-canvas"></div></div>
        </div>
    </div>
</div>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['google_maps_api_key'] ?>"></script>
<script>
// ============================================================
// SIDEBAR TOGGLE
// ============================================================
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
hamburger.addEventListener('click', function() {
    sidebar.classList.toggle('open');
    this.querySelector('i').className = sidebar.classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
});
document.addEventListener('click', function(e) {
    if (!sidebar.contains(e.target) && !hamburger.contains(e.target) && window.innerWidth <= 992) {
        sidebar.classList.remove('open');
        hamburger.querySelector('i').className = 'fas fa-bars';
    }
});

// ============================================================
// CLOCK
// ============================================================
function updateClock() {
    const n = new Date();
    document.getElementById('liveDate').textContent = n.toLocaleDateString('en-US', {day:'2-digit',month:'short',year:'numeric'});
    document.getElementById('liveTime').textContent = n.toLocaleTimeString('en-US', {hour12:false,hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('footerDateTime').textContent = '🕐 ' + n.toLocaleDateString('en-US', {day:'2-digit',month:'short'}) + ' · ' + n.toLocaleTimeString('en-US', {hour12:false,hour:'2-digit',minute:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);

// ============================================================
// THEME
// ============================================================
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;
let saved = localStorage.getItem('vehicle_theme') || '<?= $config['dark_mode_default'] ?>';
html.setAttribute('data-theme', saved);
if (saved === 'dark') themeToggle.classList.add('active');

let chartInstance = null;
function initChart() {
    const isDark = html.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#94a3b8' : '#475569';
    const labels = <?= json_encode(array_keys($monthly)) ?>;
    const bookings = <?= json_encode(array_column($monthly, 'bookings')) ?>;
    const revenues = <?= json_encode(array_column($monthly, 'revenue')) ?>;
    const ctx = document.getElementById('revenueChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Bookings', data: bookings, backgroundColor: isDark ? 'rgba(96,165,250,0.4)' : 'rgba(37,99,235,0.4)', borderColor: isDark ? '#60a5fa' : '#2563eb', borderWidth: 2, borderRadius: 4, yAxisID: 'y' },
                { label: 'Revenue (LKR)', data: revenues, type: 'line', borderColor: isDark ? '#34d399' : '#10b981', backgroundColor: isDark ? 'rgba(52,211,153,0.05)' : 'rgba(16,185,129,0.05)', borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor, font: { size: 8 }, boxWidth: 8, padding: 6, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor, font: { size: 7 } }, grid: { color: gridColor } },
                y1: { position: 'right', ticks: { callback: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(1)+'K' : v, color: textColor, font: { size: 7 } }, grid: { drawOnChartArea: false } },
                x: { ticks: { color: textColor, font: { size: 7 } }, grid: { color: gridColor } }
            }
        }
    });
}
setTimeout(initChart, 200);

themeToggle.addEventListener('click', function() {
    const cur = html.getAttribute('data-theme');
    const next = cur === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    this.classList.toggle('active');
    localStorage.setItem('vehicle_theme', next);
    initChart();
});

// ============================================================
// AJAX REFRESH
// ============================================================
function refreshData() {
    fetch(window.location.href + '?ajax=1')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statAvailable').textContent = data.available;
                document.getElementById('statBooked').textContent = data.booked;
                document.getElementById('statMaintenance').textContent = data.maintenance;
                document.getElementById('statTotal').textContent = data.total;
                document.getElementById('statDrivers').textContent = data.drivers;
                document.getElementById('statRevenue').textContent = 'LKR ' + data.revenue.toLocaleString();
                document.getElementById('notifCount').textContent = data.notifications;
            }
        }).catch(() => {});
}
setInterval(refreshData, <?= $config['auto_refresh_interval'] * 1000 ?>);

// ============================================================
// OTHER FUNCTIONS
// ============================================================
function showNotifications() {
    const modal = new bootstrap.Modal(document.getElementById('notifModal'));
    modal.show();
    document.getElementById('notifCount').textContent = '0';
}
document.getElementById('profileToggle')?.addEventListener('click', function() {
    if (confirm('Logout from Fleet Manager?')) window.location.href = '../index.php?action=logout';
});
function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
}
function exportPDF() {
    const element = document.getElementById('dashboardContent');
    html2pdf().set({ margin: 0.5, filename: 'Fleet_Dashboard_Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'in', format: 'a3', orientation: 'landscape' } }).from(element).save();
}

// ============================================================
// 🆕 STATUS MODAL HELPER
// ============================================================
function openStatusModal(vehicleId, currentStatus) {
    const select = document.getElementById('statusVehicleSelect');
    for (let opt of select.options) {
        if (opt.value == vehicleId) { opt.selected = true; break; }
    }
    const statusSelect = document.querySelector('select[name="new_status"]');
    for (let opt of statusSelect.options) {
        if (opt.value == currentStatus) { opt.selected = true; break; }
    }
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// ============================================================
// MAP MODAL
// ============================================================
var modalMap = null;
function openMapModal(pickupLat, pickupLng, destLat, destLng, title, id) {
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        alert('Google Maps not loaded. Please check API key.');
        return;
    }
    if (modalMap) { modalMap = null; }
    var modal = document.getElementById('mapModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-map-title').textContent = 'Booking #' + id + ' - ' + title;
    document.getElementById('modal-pickup-coord').textContent = pickupLat + ', ' + pickupLng;
    document.getElementById('modal-dest-coord').textContent = destLat + ', ' + destLng;

    var mapCanvas = document.getElementById('map-modal-canvas');
    while (mapCanvas.firstChild) { mapCanvas.removeChild(mapCanvas.firstChild); }

    var pickupPos = new google.maps.LatLng(parseFloat(pickupLat), parseFloat(pickupLng));
    var destPos = new google.maps.LatLng(parseFloat(destLat), parseFloat(destLng));

    modalMap = new google.maps.Map(mapCanvas, { center: { lat: 7.8731, lng: 80.7718 }, zoom: 8, mapTypeId: google.maps.MapTypeId.ROADMAP, zoomControl: true });
    new google.maps.Marker({ position: pickupPos, map: modalMap, title: 'Pickup', icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' }, label: { text: 'P', color: 'white', fontWeight: 'bold' } });
    new google.maps.Marker({ position: destPos, map: modalMap, title: 'Destination', icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' }, label: { text: 'D', color: 'white', fontWeight: 'bold' } });
    var path = new google.maps.Polyline({ path: [pickupPos, destPos], geodesic: true, strokeColor: '#3b82f6', strokeOpacity: 0.9, strokeWeight: 4 });
    path.setMap(modalMap);
    var bounds = new google.maps.LatLngBounds(); bounds.extend(pickupPos); bounds.extend(destPos);
    modalMap.fitBounds(bounds);
    var zoom = modalMap.getZoom(); if (zoom > 12) modalMap.setZoom(Math.min(zoom, 15));
}
function closeMapModal() {
    var modal = document.getElementById('mapModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    if (modalMap) { modalMap = null; }
    var canvas = document.getElementById('map-modal-canvas');
    if (canvas) { while (canvas.firstChild) { canvas.removeChild(canvas.firstChild); } }
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMapModal(); });
document.getElementById('mapModal').addEventListener('click', function(e) { if (e.target === this) closeMapModal(); });

// ============================================================
// MESSAGES
// ============================================================
<?php if (isset($msg) && $msg): ?>
alert('✅ <?= addslashes($msg) ?>');
<?php endif; ?>
<?php if (isset($error) && $error): ?>
alert('❌ <?= addslashes($error) ?>');
<?php endif; ?>

console.log('%c🚗 Ultimate Fleet Dashboard v3.2.1 (with Vehicle Status Update)', 'font-size:20px; font-weight:bold; color:#2563eb;');
console.log('✅ All features loaded successfully.');
</script>
</body>
</html>