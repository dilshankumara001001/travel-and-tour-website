<?php
session_start();

// ============================================================
// 0. GET ADMIN NAME FROM SESSION
// ============================================================
$admin_name = 'Admin';
if (isset($_SESSION['admin_name']) && !empty($_SESSION['admin_name'])) {
    $admin_name = $_SESSION['admin_name'];
} elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $admin_name = $_SESSION['username'];
} elseif (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) {
    $admin_name = $_SESSION['full_name'];
}
$display_admin = "Admin: " . $admin_name;

// ============================================================
// 1. DATABASE CONNECTION (PDO)
// ============================================================
$host = 'localhost';
$db   = 'travel_tour_db';
$user = 'root'; 
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================================
// 1.5 ADD IMAGE COLUMN IF NOT EXISTS
// ============================================================
try {
    $pdo->query("ALTER TABLE packages ADD COLUMN IF NOT EXISTS image VARCHAR(500) NULL");
} catch (PDOException $e) {
    // Column might already exist
}

// ============================================================
// 1.6 CREATE VEHICLES TABLE IF NOT EXISTS (to avoid errors)
// ============================================================
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS vehicles (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        vehicle_name VARCHAR(100) NOT NULL,
        price_per_km DECIMAL(10,2) NOT NULL DEFAULT 40.00
    )");
    
    // Insert default vehicles if table is empty
    $check = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    if ($check == 0) {
        $pdo->query("INSERT INTO vehicles (vehicle_name, price_per_km) VALUES 
            ('Mini Car', 40.00),
            ('SUV', 60.00),
            ('Van', 80.00),
            ('Luxury Car', 100.00)
        ");
    }
} catch (PDOException $e) {
    // Table might already exist or error
}

// ============================================================
// 2. CREATE UPLOADS DIRECTORY IF NOT EXISTS
// ============================================================
$upload_dir = __DIR__ . '/uploads/packages/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ============================================================
// Fetch Vehicles for the Dropdown from Database Dynamically
// ============================================================
try {
    $vStmt = $pdo->query('SELECT id, vehicle_name, price_per_km FROM vehicles');
    $vehiclesList = $vStmt->fetchAll();
} catch (PDOException $e) {
    $vehiclesList = [];
}

// ============================================================
// 3. HANDLE CRUD OPERATIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: DELETE PACKAGE (Soft Delete)
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE packages SET status = ? WHERE id = ?');
        $stmt->execute(['Deleted', $_POST['id']]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ACTION: UPDATE/EDIT PACKAGE (with image)
    if ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        // Get existing image to keep if no new image is uploaded
        $stmt = $pdo->prepare('SELECT image FROM packages WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $existing = $stmt->fetch();
        $current_image = $existing ? $existing['image'] : null;
        
        // Check if new image is provided via URL
        $image_value = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
        
        // Check if file is uploaded
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'package_' . time() . '_' . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Delete old image if exists and is a local file
                    if ($current_image && !filter_var($current_image, FILTER_VALIDATE_URL)) {
                        $old_path = __DIR__ . '/' . ltrim($current_image, '/');
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $image_value = 'uploads/packages/' . $filename;
                }
            }
        }
        
        // If no new image provided, keep existing
        if (empty($image_value) && $current_image) {
            $image_value = $current_image;
        }
        
        $stmt = $pdo->prepare('UPDATE packages SET title=?, description=?, price=?, latitude=?, longitude=?, latitude2=?, longitude2=?, image=? WHERE id=?');
        $stmt->execute([
            $_POST['title'], 
            $_POST['description'], 
            $_POST['price'],
            $_POST['latitude'], 
            $_POST['longitude'], 
            $_POST['latitude2'], 
            $_POST['longitude2'],
            $image_value,
            $_POST['id']
        ]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ACTION: ADD NEW PACKAGE (with image)
    if ($_POST['action'] === 'add') {
        $image_value = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
        
        // Check if file is uploaded
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'package_' . time() . '_' . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $image_value = 'uploads/packages/' . $filename;
                }
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO packages (title, description, price, latitude, longitude, latitude2, longitude2, status, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['title'], 
            $_POST['description'], 
            $_POST['price'],
            $_POST['latitude'], 
            $_POST['longitude'], 
            $_POST['latitude2'], 
            $_POST['longitude2'],
            'Active',
            $image_value
        ]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ============================================================
// 4. FETCH ALL LIVE PACKAGES
// ============================================================
$stmt = $pdo->query("SELECT * FROM packages WHERE status != 'Deleted'");
$packages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Tour & Travel Packages Manager</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw"></script>

    <style>
        /* ============================================================
           CSS VARIABLES – LIGHT is DEFAULT, DARK toggle
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
           NAVBAR
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

        /* ===== VEHICLE MANAGER BUTTON ===== */
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
        .theme-toggle-inline .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
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

        /* ============================================
           MAIN WRAPPER
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

        /* ============================================
           SEARCH & ADD BAR
           ============================================ */
        .search-bar {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: 16px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            margin-bottom: 32px;
            backdrop-filter: blur(12px);
            transition: all var(--transition);
        }
        .search-bar .search-input {
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-bar .search-input i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 16px;
        }
        .search-bar .search-input input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            outline: none;
            transition: all var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .search-bar .search-input input:focus {
            border-color: var(--text-accent);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .search-bar .search-input input::placeholder {
            color: var(--text-muted);
        }
        .search-bar .btn-add {
            padding: 12px 24px;
            background: var(--gradient-main);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(14,165,233,0.2);
        }
        .search-bar .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(14,165,233,0.3);
        }

        /* ============================================
           PACKAGE CARDS (Updated with Image)
           ============================================ */
        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .package-card-item {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(12px);
        }
        .package-card-item:hover {
            transform: translateY(-6px);
            border-color: rgba(14, 165, 233, 0.2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .package-card-item .card-image {
            width: 100%;
            height: 180px;
            overflow: hidden;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            position: relative;
        }
        .package-card-item .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .package-card-item:hover .card-image img {
            transform: scale(1.05);
        }
        .package-card-item .card-image .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.15);
            font-size: 48px;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        .package-card-item .card-image .no-image i {
            filter: drop-shadow(0 4px 20px rgba(0,0,0,0.3));
        }
        .package-card-item .card-image .card-price {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.08);
            z-index: 2;
        }
        .package-card-item .card-image .card-price small {
            font-size: 10px;
            font-weight: 400;
            opacity: 0.7;
        }
        .package-card-item .card-image .card-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(16, 185, 129, 0.85);
            backdrop-filter: blur(8px);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .package-card-item .card-header {
            padding: 16px 20px 12px;
            background: var(--bg-card);
            transition: background var(--transition);
        }
        .package-card-item .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .package-card-item .card-body {
            padding: 0 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            transition: background var(--transition);
        }
        .package-card-item .card-body .description {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 14px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .package-card-item .card-body .btn-map {
            width: 100%;
            padding: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .package-card-item .card-body .btn-map:hover {
            background: var(--text-accent);
            color: #fff;
            border-color: var(--text-accent);
        }
        .package-card-item .card-body .btn-map:hover i {
            color: #fff;
        }
        .package-card-item .card-body .action-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border-card);
        }
        .package-card-item .card-body .action-row .btn-edit {
            padding: 8px 12px;
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 10px;
            background: rgba(245, 158, 11, 0.08);
            color: var(--text-warning);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .package-card-item .card-body .action-row .btn-edit:hover {
            background: rgba(245, 158, 11, 0.2);
            transform: translateY(-2px);
        }
        .package-card-item .card-body .action-row .btn-delete {
            padding: 8px 12px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.08);
            color: var(--text-danger);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .package-card-item .card-body .action-row .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
        }

        /* ============================================
           EMPTY STATE
           ============================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            display: none;
        }
        .empty-state.visible {
            display: block;
        }
        .empty-state .icon {
            font-size: 56px;
            color: var(--text-muted);
            opacity: 0.2;
            margin-bottom: 12px;
        }
        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }

        /* ============================================
           MODALS (Map & Form)
           ============================================ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .modal-overlay .modal-content {
            background: var(--bg-card);
            border-radius: 28px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.4);
            border: 1px solid var(--border-card);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        .modal-content .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #0f172a;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .modal-content .modal-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-content .modal-header h3 i {
            color: var(--text-accent);
        }
        .modal-content .modal-header .close-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content .modal-header .close-btn:hover {
            background: rgba(239,68,68,0.2);
            color: #f87171;
        }
        .modal-content .modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }

        /* Map Modal */
        .modal-map-wrapper {
            height: 420px;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            background: #1e293b;
        }
        .modal-map-wrapper #map {
            width: 100%;
            height: 100%;
        }
        .modal-map-footer {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            background: var(--bg-input);
            border-top: 1px solid var(--border-card);
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
            border-radius: 0 0 28px 28px;
        }

        /* Form Modal - Grid Layout */
        .form-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .form-modal-grid .form-left {
            padding-right: 8px;
        }
        .form-modal-grid .form-right {
            position: relative;
            min-height: 400px;
            background: var(--bg-secondary);
            border-radius: 16px;
            overflow: hidden;
        }
        .form-modal-grid .form-right #pickerMap {
            width: 100%;
            height: 100%;
            min-height: 400px;
        }
        .form-modal-grid .form-right .map-picker-label {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 10;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 6px;
            pointer-events: none;
        }
        .form-modal-grid .form-right .map-picker-label i {
            color: #60a5fa;
        }

        /* Form Fields */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            outline: none;
            transition: all var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--text-accent);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ===== UPDATED: Image Upload Section ===== */
        .image-upload-section {
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .image-upload-section .upload-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .image-upload-section .upload-label i {
            color: var(--text-accent);
        }
        .image-upload-section .upload-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .image-upload-section .upload-row .url-group input {
            width: 100%;
            padding: 8px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: all var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .image-upload-section .upload-row .url-group input:focus {
            border-color: var(--text-accent);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .image-upload-section .upload-row .url-group input::placeholder {
            color: var(--text-muted);
            font-size: 12px;
        }
        .image-upload-section .upload-row .file-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .image-upload-section .upload-row .file-group input[type="file"] {
            flex: 1;
            padding: 8px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 12px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .image-upload-section .upload-row .file-group input[type="file"]::-webkit-file-upload-button {
            background: var(--gradient-main);
            color: #fff;
            border: none;
            padding: 4px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .image-upload-section .upload-row .file-group input[type="file"]::-webkit-file-upload-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14,165,233,0.3);
        }
        .image-upload-section .upload-hint {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 4px;
            opacity: 0.6;
        }
        .image-upload-section .current-image-preview {
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px dashed var(--border-card);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .image-upload-section .current-image-preview img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-card);
        }
        .image-upload-section .current-image-preview .preview-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Vehicle & Distance Box */
        .vehicle-box {
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .vehicle-box .vehicle-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .distance-display {
            background: rgba(99, 102, 241, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .distance-display span {
            color: var(--text-accent);
            font-family: monospace;
            font-size: 16px;
        }

        /* Coordinates Box */
        .coords-box {
            background: rgba(14, 165, 233, 0.04);
            border: 1px solid rgba(14, 165, 233, 0.08);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .coords-box .coords-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .coords-box .coords-label i {
            color: var(--text-accent);
        }
        .coords-box .coords-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .coords-box .coords-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .coords-box .coord-btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }
        .coords-box .coord-btn-group button {
            padding: 8px;
            border: 1px solid var(--border-card);
            border-radius: 10px;
            background: var(--bg-input);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .coords-box .coord-btn-group button.active {
            background: var(--text-accent);
            color: #fff;
            border-color: var(--text-accent);
        }
        .coords-box .coord-btn-group button:hover:not(.active) {
            background: var(--bg-card);
            border-color: var(--text-accent);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--border-card);
        }
        .form-actions .btn-cancel {
            padding: 10px 24px;
            border: 1px solid var(--border-card);
            border-radius: 12px;
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .form-actions .btn-cancel:hover {
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-actions .btn-save {
            padding: 10px 28px;
            border: none;
            border-radius: 12px;
            background: var(--gradient-main);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(14,165,233,0.2);
        }
        .form-actions .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(14,165,233,0.3);
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

        /* Scroll to top */
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
        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }
        .scroll-top:hover {
            transform: translateY(-6px) scale(1.1);
            border-color: var(--text-accent);
            box-shadow: 0 15px 40px rgba(14,165,233,0.2);
        }

        /* Custom cursor */
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

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        /* Responsive */
        @media (max-width: 992px) {
            .search-bar { grid-template-columns: 1fr; }
            .form-modal-grid { grid-template-columns: 1fr; }
            .form-modal-grid .form-right { min-height: 300px; }
            .form-modal-grid .form-right #pickerMap { min-height: 300px; }
            .modal-content { max-width: 600px; }
            .image-upload-section .upload-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .main { padding: 16px; }
            .package-grid { grid-template-columns: 1fr; }
            .search-bar { grid-template-columns: 1fr; padding: 14px; }
            .modal-content { max-width: 100%; margin: 10px; border-radius: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .form-row-2 { grid-template-columns: 1fr; }
            .vehicle-box .vehicle-row { grid-template-columns: 1fr; }
            .coords-box .coords-row { grid-template-columns: 1fr; }
            .coords-box .coords-row-2 { grid-template-columns: 1fr; }
            .modal-map-wrapper { height: 280px; }
        }
        @media (max-width: 480px) {
            .navbar { padding: 10px 16px; }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
            .main { padding: 12px; }
            .modal-content .modal-header { padding: 14px 16px; }
            .modal-content .modal-body { padding: 14px 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-cancel, .form-actions .btn-save { width: 100%; justify-content: center; }
        }

        /* Print */
        @media print {
            body { background: #fff !important; color: #111 !important; padding: 10px 15px !important; font-size: 12px !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top, .search-bar { display: none !important; }
            .main { padding: 0 !important; }
            .package-card-item { break-inside: avoid; border: 1px solid #d1d5db !important; box-shadow: none !important; }
            .package-card-item .card-image { height: 150px !important; }
            .package-card-item .card-header h3 { color: #111 !important; }
            .package-card-item .card-body .btn-map { display: none; }
            .package-card-item .card-body .action-row { display: none; }
            .modal-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- BACKGROUND VIDEO -->
<!-- ============================================ -->
<div class="video-background">
    <div class="fallback"></div>
    <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1920&q=80">
        <source src="videos/19329370-uhd_3840_2160_24fps.mp4" type="video/mp4">
        <source src="videos/background.webm" type="video/webm">
    </video>
</div>
<div class="video-overlay"></div>

<!-- Ambient Blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Particles -->
<div class="particles" id="particles"></div>

<!-- Loader -->
<div class="loader" id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">Loading Packages</div>
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
            <a href="packages.php" class="active"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php" class=""><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php" class=""><i class="fa-solid fa-comment"></i> Feedback</a>
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
    </div>
    <a href="../index.php" class="btn btn-danger" style="padding:8px 18px; border-radius:30px; font-size:14px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.2); transition:all 0.3s ease;">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</nav>

<!-- ============================================================ -->
<!-- MAIN WRAPPER -->
<!-- ============================================================ -->
<main class="main">

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-suitcase"></i></div>
            <div>
                <h1 id="typing-heading">Tour Packages Manager</h1>
                <span>Premium Travel Experiences &bull; Manage your offerings</span>
            </div>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- SEARCH & ADD BAR -->
    <!-- ============================================================ -->
    <div class="search-bar">
        <div class="search-input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchBar" onkeyup="searchPackages()" placeholder="Where do you want to go? (e.g. Safari, Beach...)" />
        </div>
        <button onclick="openFormModal('add')" class="btn-add">
            <i class="fa-solid fa-circle-plus"></i> Add New Package
        </button>
    </div>

    <!-- ============================================================ -->
    <!-- PACKAGE GRID -->
    <!-- ============================================================ -->
    <div id="packageGrid" class="package-grid">
        
        <?php foreach ($packages as $index => $package): 
            $iconClass = 'fa-map-location-dot'; 
            $titleLower = strtolower($package['title']);
            $descLower = strtolower($package['description']);
            
            if (strpos($titleLower, 'adventure') !== false || strpos($titleLower, 'mountain') !== false) {
                $iconClass = 'fa-mountain-sun';
            } elseif (strpos($titleLower, 'beach') !== false || strpos($titleLower, 'escape') !== false || strpos($descLower, 'sand') !== false) {
                $iconClass = 'fa-umbrella-beach';
            } elseif (strpos($titleLower, 'safari') !== false || strpos($titleLower, 'wildlife') !== false) {
                $iconClass = 'fa-paw';
            } elseif (strpos($titleLower, 'tea') !== false || strpos($titleLower, 'plantation') !== false) {
                $iconClass = 'fa-leaf-heart';
            } elseif (strpos($titleLower, 'diving') !== false || strpos($titleLower, 'sea') !== false) {
                $iconClass = 'fa-person-swimming';
            } elseif (strpos($titleLower, 'cultural') !== false || strpos($titleLower, 'tour') !== false) {
                $iconClass = 'fa-gopuram';
            }
            
            $delayTime = $index * 0.08;
            
            // Image handling
            $image_url = $package['image'] ?? '';
            $has_image = !empty($image_url);
            $image_display = '';
            if ($has_image) {
                if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $image_display = $image_url;
                } elseif (file_exists(__DIR__ . '/' . ltrim($image_url, '/'))) {
                    $image_display = $image_url;
                } else {
                    $has_image = false;
                }
            }
        ?>
            <div class="package-card-item animate__animated animate__fadeInUp" style="animation-delay: <?php echo $delayTime; ?>s">
                <div class="card-image">
                    <?php if ($has_image && $image_display): ?>
                        <img src="<?php echo htmlspecialchars($image_display); ?>" alt="<?php echo htmlspecialchars($package['title']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="no-image" style="display:none;"><i class="fa-solid fa-image"></i></div>
                    <?php else: ?>
                        <div class="no-image"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                    <div class="card-price"><small>Rs.</small> <?php echo htmlspecialchars(number_format($package['price'], 2)); ?></div>
                    <div class="card-badge"><i class="fa-regular fa-star"></i> Featured</div>
                </div>
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($package['title']); ?></h3>
                </div>
                <div class="card-body">
                    <p class="description"><?php echo htmlspecialchars($package['description']); ?></p>
                    <button onclick="openMapModal('<?php echo $package['latitude']; ?>', '<?php echo $package['longitude']; ?>', '<?php echo $package['latitude2']; ?>', '<?php echo $package['longitude2']; ?>', '<?php echo addslashes(htmlspecialchars($package['title'])); ?>')" 
                            class="btn-map">
                        <i class="fa-solid fa-map-location-dot"></i> View Route on Map
                    </button>
                    <div class="action-row">
                        <button onclick='openFormModal("edit", <?php echo htmlspecialchars(json_encode($package), ENT_QUOTES, 'UTF-8'); ?>)' 
                                class="btn-edit">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Package
                        </button>
                        <button onclick="triggerDelete(<?php echo $package['id']; ?>, '<?php echo addslashes(htmlspecialchars($package['title'])); ?>')" 
                                class="btn-delete">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
    
    <div id="emptyState" class="empty-state">
        <div class="icon"><i class="fa-solid fa-map-route"></i></div>
        <h3>No Packages Found</h3>
        <p>We couldn't find any tour matching your search criteria. Try typing something else!</p>
    </div>

</main>

<!-- ============================================================ -->
<!-- MAP MODAL -->
<!-- ============================================================ -->
<div id="mapModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-route"></i> <span id="modalTitle">Package Route</span></h3>
            <button class="close-btn" onclick="closeMapModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-map-wrapper"><div id="map"></div></div>
            <div class="modal-map-footer">
                <span><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse" style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block;margin-right:6px;"></span> Marker [1]: Departure Point</span>
                <span><span class="w-2 h-2 rounded-full bg-blue-500 inline-block animate-pulse" style="width:10px;height:10px;border-radius:50%;background:#3b82f6;display:inline-block;margin-right:6px;"></span> Marker [2]: Destination Point</span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM MODAL (UPDATED WITH IMAGE UPLOAD) -->
<!-- ============================================================ -->
<div id="formModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i id="formIcon" class="fa-solid fa-circle-plus"></i> <span id="formModalTitle">Add New Package</span></h3>
            <button class="close-btn" onclick="closeFormModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="packageForm" method="POST" enctype="multipart/form-data" class="form-modal-grid">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="packageId" value="">
                
                <!-- Left Column - Form Fields -->
                <div class="form-left">
                    <div class="form-group">
                        <label>Package Title</label>
                        <input type="text" name="title" id="formTitle" required placeholder="Enter package title">
                    </div>
                    <div class="form-group">
                        <label>Price (LKR)</label>
                        <input type="number" step="0.01" name="price" id="formPrice" required placeholder="0.00">
                    </div>
                    
                    <div class="vehicle-box">
                        <div class="vehicle-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Select Vehicle Type</label>
                                <select id="formVehicle" onchange="calculateAutoPrice()">
                                    <?php foreach($vehiclesList as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['price_per_km']); ?>">
                                            <?php echo htmlspecialchars($vehicle['vehicle_name']); ?> (Rs. <?php echo htmlspecialchars($vehicle['price_per_km']); ?> / km)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Calculated Distance</label>
                                <div class="distance-display">
                                    <span>Total Route Distance:</span>
                                    <span id="distanceReadout">0.00 km</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ===== UPDATED: Image Upload Section ===== -->
                    <div class="image-upload-section">
                        <div class="upload-label"><i class="fa-regular fa-image"></i> Package Image</div>
                        <div class="upload-row">
                            <div class="url-group">
                                <input type="text" name="image_url" id="formImageUrl" placeholder="Paste image URL here..." oninput="previewImageUrl(this.value)">
                                <div style="font-size:9px; color:var(--text-muted); margin-top:2px;">Enter a direct image URL</div>
                            </div>
                            <div class="file-group">
                                <input type="file" name="image_file" id="formImageFile" accept="image/*" onchange="previewImageFile(this)">
                                <div style="font-size:9px; color:var(--text-muted); margin-top:2px;">Or browse from your PC</div>
                            </div>
                        </div>
                        <div class="current-image-preview" id="imagePreviewContainer" style="display:none;">
                            <img id="imagePreviewThumb" src="" alt="Preview">
                            <span class="preview-text" id="imagePreviewText">No image selected</span>
                            <button type="button" onclick="clearImagePreview()" style="background:none;border:none;color:var(--text-danger);cursor:pointer;font-size:14px;">
                                <i class="fa-solid fa-times-circle"></i>
                            </button>
                        </div>
                        <div class="upload-hint"><i class="fa-regular fa-circle-info"></i> Supports JPG, PNG, GIF, WebP. Max 5MB.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Package Description</label>
                        <textarea name="description" id="formDescription" rows="2" required placeholder="Describe your package..."></textarea>
                    </div>

                    <div class="coords-box">
                        <div class="coords-label"><i class="fa-solid fa-map-pin"></i> Click map on the right to auto-fill coordinates dynamically!</div>
                        <div class="coord-btn-group">
                            <button type="button" id="setLoc1Btn" onclick="setActivePicker(1)" class="active">1. Edit Start Location</button>
                            <button type="button" id="setLoc2Btn" onclick="setActivePicker(2)">2. Edit End Location</button>
                        </div>
                        <div class="coords-row">
                            <div class="form-group" style="margin-bottom:4px;">
                                <label style="font-size:9px;">Start Latitude (1)</label>
                                <input type="text" name="latitude" id="formLat1" oninput="calculateAutoPrice(); syncPickerMapElements();" required style="font-size:12px;padding:6px 10px;">
                            </div>
                            <div class="form-group" style="margin-bottom:4px;">
                                <label style="font-size:9px;">Start Longitude (1)</label>
                                <input type="text" name="longitude" id="formLng1" oninput="calculateAutoPrice(); syncPickerMapElements();" required style="font-size:12px;padding:6px 10px;">
                            </div>
                        </div>
                        <div class="coords-row-2">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:9px;">End Latitude (2)</label>
                                <input type="text" name="latitude2" id="formLat2" oninput="calculateAutoPrice(); syncPickerMapElements();" required style="font-size:12px;padding:6px 10px;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:9px;">End Longitude (2)</label>
                                <input type="text" name="longitude2" id="formLng2" oninput="calculateAutoPrice(); syncPickerMapElements();" required style="font-size:12px;padding:6px 10px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" onclick="closeFormModal()" class="btn-cancel">Cancel</button>
                        <button type="submit" id="formSubmitBtn" class="btn-save">Save Changes</button>
                    </div>
                </div>

                <!-- Right Column - Map Picker -->
                <div class="form-right">
                    <div class="map-picker-label">
                        <i class="fa-solid fa-crosshairs"></i> Map Coordinate Picker
                    </div>
                    <div id="pickerMap"></div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer-bar">
    <span>© <?php echo date('Y'); ?> Intelligence Data Center &bull; All rights reserved.</span>
    <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?></span>
</div>

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
            setTimeout(typeWriter, 50);
        }
    }
    setTimeout(typeWriter, 800);

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
    // Floating Particles
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

    // ============================================================
    // PACKAGE PAGE SPECIFIC SCRIPTS
    // ============================================================
    let map, pickerMap;
    let markers = [];
    let pickerMarkers = { 1: null, 2: null };
    let polyline, pickerPolyline;
    let activePickerTarget = 1;

    // ===== UPDATED: Image Preview Functions =====
    function previewImageUrl(url) {
        const container = document.getElementById('imagePreviewContainer');
        const thumb = document.getElementById('imagePreviewThumb');
        const text = document.getElementById('imagePreviewText');
        
        if (url && url.trim() !== '') {
            thumb.src = url.trim();
            container.style.display = 'flex';
            text.textContent = 'Image from URL';
            // Check if image loads
            thumb.onerror = function() {
                thumb.src = '';
                text.textContent = '⚠️ Invalid image URL';
                container.style.borderColor = 'var(--text-danger)';
            };
            thumb.onload = function() {
                text.textContent = '✅ Image loaded from URL';
                container.style.borderColor = 'var(--text-success)';
            };
        } else {
            // Check if there's a file input value
            const fileInput = document.getElementById('formImageFile');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                // File is already handling preview
                return;
            }
            container.style.display = 'none';
        }
    }

    function previewImageFile(input) {
        const container = document.getElementById('imagePreviewContainer');
        const thumb = document.getElementById('imagePreviewThumb');
        const text = document.getElementById('imagePreviewText');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                thumb.src = e.target.result;
                container.style.display = 'flex';
                text.textContent = '📷 ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(0) + ' KB)';
                container.style.borderColor = 'var(--text-success)';
                // Clear URL field
                document.getElementById('formImageUrl').value = '';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            container.style.display = 'none';
        }
    }

    function clearImagePreview() {
        const container = document.getElementById('imagePreviewContainer');
        const thumb = document.getElementById('imagePreviewThumb');
        const text = document.getElementById('imagePreviewText');
        const fileInput = document.getElementById('formImageFile');
        const urlInput = document.getElementById('formImageUrl');
        
        fileInput.value = '';
        urlInput.value = '';
        thumb.src = '';
        container.style.display = 'none';
        text.textContent = 'No image selected';
        container.style.borderColor = 'var(--border-card)';
    }

    // Search
    function searchPackages() {
        const input = document.getElementById('searchBar').value.toLowerCase();
        const cards = document.querySelectorAll('.package-card-item');
        let foundCount = 0;

        cards.forEach(card => {
            const title = card.querySelector('.card-header h3').textContent.toLowerCase();
            const desc = card.querySelector('.description').textContent.toLowerCase();
            if (title.includes(input) || desc.includes(input)) {
                card.style.display = ""; foundCount++;
            } else {
                card.style.display = "none";
            }
        });

        const emptyState = document.getElementById('emptyState');
        if (foundCount === 0) {
            emptyState.classList.add('visible');
        } else {
            emptyState.classList.remove('visible');
        }
    }

    // Price Calculator (Now in LKR)
    function calculateAutoPrice() {
        const lat1 = parseFloat(document.getElementById('formLat1').value);
        const lng1 = parseFloat(document.getElementById('formLng1').value);
        const lat2 = parseFloat(document.getElementById('formLat2').value);
        const lng2 = parseFloat(document.getElementById('formLng2').value);

        if (!isNaN(lat1) && !isNaN(lng1) && !isNaN(lat2) && !isNaN(lng2)) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLng / 2) * Math.sin(dLng / 2);
            
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const distance = R * c;

            document.getElementById('distanceReadout').textContent = distance.toFixed(2) + " km";

            const vehicleSelect = document.getElementById('formVehicle');
            const rate = parseFloat(vehicleSelect.value) || 40;
            const totalPrice = distance * rate;

            if (!isNaN(totalPrice)) {
                document.getElementById('formPrice').value = totalPrice.toFixed(2);
            }
        }
    }

    // Map Modal
    function openMapModal(lat1, lng1, lat2, lng2, title) {
        const modal = document.getElementById('mapModal');
        document.getElementById('modalTitle').textContent = title + ' Journey';
        modal.classList.add('active');

        const loc1 = { lat: parseFloat(lat1) || 6.9271, lng: parseFloat(lng1) || 79.8612 };
        const loc2 = { lat: parseFloat(lat2) || 7.2906, lng: parseFloat(lng2) || 80.6337 };

        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 8, center: loc1, disableDefaultUI: false, zoomControl: true
        });

        markers.forEach(m => m.setMap(null)); markers = [];
        if(polyline) polyline.setMap(null);

        markers.push(new google.maps.Marker({ position: loc1, map: map, title: "Start Location", label: { text: "1", color: "white", fontWeight: "bold" } }));
        markers.push(new google.maps.Marker({ position: loc2, map: map, title: "End Location", label: { text: "2", color: "white", fontWeight: "bold" } }));

        polyline = new google.maps.Polyline({ path: [loc1, loc2], geodesic: true, strokeColor: "#0ea5e9", strokeOpacity: 0.9, strokeWeight: 5 });
        polyline.setMap(map);

        const bounds = new google.maps.LatLngBounds(); bounds.extend(loc1); bounds.extend(loc2);
        map.fitBounds(bounds);
    }

    function closeMapModal() {
        document.getElementById('mapModal').classList.remove('active');
    }

    // Form Modal (Updated with image handling)
    function openFormModal(mode, data = null) {
        const modal = document.getElementById('formModal');
        const previewContainer = document.getElementById('imagePreviewContainer');
        
        // Reset image preview
        previewContainer.style.display = 'none';
        document.getElementById('imagePreviewThumb').src = '';
        document.getElementById('imagePreviewText').textContent = 'No image selected';
        document.getElementById('formImageUrl').value = '';
        document.getElementById('formImageFile').value = '';
        
        if (mode === 'edit' && data) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('packageId').value = data.id;
            document.getElementById('formTitle').value = data.title;
            document.getElementById('formPrice').value = data.price;
            document.getElementById('formDescription').value = data.description;
            document.getElementById('formLat1').value = data.latitude;
            document.getElementById('formLng1').value = data.longitude;
            document.getElementById('formLat2').value = data.latitude2;
            document.getElementById('formLng2').value = data.longitude2;

            // Show existing image preview
            if (data.image) {
                let imgUrl = data.image;
                // Check if it's a valid URL or local path
                if (!imgUrl.startsWith('http://') && !imgUrl.startsWith('https://')) {
                    imgUrl = imgUrl;
                }
                document.getElementById('imagePreviewThumb').src = imgUrl;
                document.getElementById('imagePreviewText').textContent = '📷 Current image';
                previewContainer.style.display = 'flex';
                previewContainer.style.borderColor = 'var(--text-accent)';
                document.getElementById('formImageUrl').value = imgUrl;
            }

            document.getElementById('formModalTitle').textContent = "Edit Package Fields";
            document.getElementById('formSubmitBtn').textContent = "Update Package";
            document.getElementById('formIcon').className = "fa-solid fa-pen-to-square";
        } else {
            document.getElementById('packageForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('packageId').value = '';
            document.getElementById('formLat1').value = '6.9271';
            document.getElementById('formLng1').value = '79.8612';
            document.getElementById('formLat2').value = '7.2906';
            document.getElementById('formLng2').value = '80.6337';

            document.getElementById('formModalTitle').textContent = "Add New Package";
            document.getElementById('formSubmitBtn').textContent = "Save Package";
            document.getElementById('formIcon').className = "fa-solid fa-circle-plus";
        }

        setActivePicker(1);
        modal.classList.add('active');
        setTimeout(() => {
            initPickerMap();
            calculateAutoPrice();
        }, 100);
    }

    function closeFormModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    // Map Picker
    function initPickerMap() {
        const lat1 = parseFloat(document.getElementById('formLat1').value) || 6.9271;
        const lng1 = parseFloat(document.getElementById('formLng1').value) || 79.8612;
        const centerLoc = { lat: lat1, lng: lng1 };

        if (!pickerMap) {
            pickerMap = new google.maps.Map(document.getElementById("pickerMap"), {
                zoom: 7, center: centerLoc, disableDefaultUI: true, zoomControl: true
            });

            pickerMap.addListener("click", (e) => {
                const clickedPos = e.latLng.toJSON();
                if (activePickerTarget === 1) {
                    document.getElementById('formLat1').value = clickedPos.lat.toFixed(6);
                    document.getElementById('formLng1').value = clickedPos.lng.toFixed(6);
                } else {
                    document.getElementById('formLat2').value = clickedPos.lat.toFixed(6);
                    document.getElementById('formLng2').value = clickedPos.lng.toFixed(6);
                }
                syncPickerMapElements();
                calculateAutoPrice();
            });
        } else {
            pickerMap.setCenter(centerLoc);
        }
        syncPickerMapElements();
    }

    function setActivePicker(targetId) {
        activePickerTarget = targetId;
        document.getElementById('setLoc1Btn').classList.toggle('active', targetId === 1);
        document.getElementById('setLoc2Btn').classList.toggle('active', targetId === 2);
    }

    function syncPickerMapElements() {
        if (!pickerMap) return;
        const loc1 = { lat: parseFloat(document.getElementById('formLat1').value) || 6.9271, lng: parseFloat(document.getElementById('formLng1').value) || 79.8612 };
        const loc2 = { lat: parseFloat(document.getElementById('formLat2').value) || 7.2906, lng: parseFloat(document.getElementById('formLng2').value) || 80.6337 };

        if (pickerMarkers[1]) pickerMarkers[1].setMap(null);
        if (pickerMarkers[2]) pickerMarkers[2].setMap(null);
        if (pickerPolyline) pickerPolyline.setMap(null);

        pickerMarkers[1] = new google.maps.Marker({ position: loc1, map: pickerMap, label: { text: "1", color: "white", fontWeight: "bold" } });
        pickerMarkers[2] = new google.maps.Marker({ position: loc2, map: pickerMap, label: { text: "2", color: "white", fontWeight: "bold" } });

        pickerPolyline = new google.maps.Polyline({ path: [loc1, loc2], geodesic: true, strokeColor: "#3b82f6", strokeOpacity: 0.7, strokeWeight: 3 });
        pickerPolyline.setMap(pickerMap);
    }

    // Delete
    function triggerDelete(id, title) {
        if (confirm(`Are you absolutely sure you want to permanently delete "${title}" package?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const actInput = document.createElement('input');
            actInput.type = 'hidden'; actInput.name = 'action'; actInput.value = 'delete';
            form.appendChild(actInput);
            const idInput = document.createElement('input');
            idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const mapModal = document.getElementById('mapModal');
        const formModal = document.getElementById('formModal');
        if (event.target === mapModal) closeMapModal();
        if (event.target === formModal) closeFormModal();
    }

    // Initial search on load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(searchPackages, 100);
    });
</script>

</body>
</html>