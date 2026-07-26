<?php
// ============================================================
// packages.php - Package Management (Display Only)
// ============================================================
session_start();

// ============================================================
// GET ADMIN NAME FROM SESSION
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
// DATABASE CONNECTION (PDO)
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
// ADD IMAGE COLUMN IF NOT EXISTS (harmless)
// ============================================================
try {
    $pdo->query("ALTER TABLE packages ADD COLUMN IF NOT EXISTS image VARCHAR(500) NULL");
} catch (PDOException $e) {
    // Column might already exist
}

// ============================================================
// FETCH ALL LIVE PACKAGES
// ============================================================
$stmt = $pdo->query("SELECT * FROM packages WHERE status != 'Deleted'");
$packages = $stmt->fetchAll();

// ============================================================
// STATS (not displayed, but kept for reference)
// ============================================================
$total_packages = count($packages);
$active_packages = $pdo->query("SELECT COUNT(*) FROM packages WHERE status = 'Active'")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM bookings")->fetchColumn();

// ============================================================
// THEME & GREETING
// ============================================================
$theme = $_COOKIE['dashboard-theme'] ?? 'light';
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Package Manager | Travel Portal</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCMxYP9sYhTYGcDk8b-4ggj07F5P0nnPBw"></script>

    <style>
        /* ============================================================
           THEME VARIABLES (Glassmorphism)
        ============================================================ */
        :root {
            --bg-primary: #f0f4f8;
            --bg-secondary: #e2e8f0;
            --bg-card: rgba(255, 255, 255, 0.92);
            --bg-input: rgba(255, 255, 255, 0.70);
            --bg-modal: rgba(220, 230, 240, 0.90);
            --text-primary: #000000;
            --text-secondary: #1a1a2e;
            --text-muted: #2d3748;
            --text-accent: #0ea5e9;
            --text-success: #10b981;
            --border-card: rgba(0, 0, 0, 0.06);
            --border-input: rgba(0, 0, 0, 0.08);
            --border-focus: #0ea5e9;
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.06);
            --shadow-btn: 0 4px 20px rgba(0, 0, 0, 0.06);
            --nav-bg: rgba(255, 255, 255, 0.85);
            --nav-border: rgba(0, 0, 0, 0.04);
            --blob-1: rgba(14, 165, 233, 0.08);
            --blob-2: rgba(16, 185, 129, 0.06);
            --blob-3: rgba(251, 146, 60, 0.04);
            --overlay: rgba(255, 255, 255, 0.45);
            --footer-color: rgba(0, 0, 0, 0.25);
            --transition: 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            --gradient-main: linear-gradient(135deg, #0ea5e9, #10b981);
            --pkg-price-bg: rgba(16, 185, 129, 0.12);
            --pkg-price-color: #0f7b4a;
            --pkg-btn-bg: linear-gradient(135deg, #0ea5e9, #0284c7);
            --pkg-btn-hover: linear-gradient(135deg, #0284c7, #0369a1);
        }

        [data-theme="dark"] {
            --bg-primary: #0b1120;
            --bg-secondary: #151f33;
            --bg-card: rgba(18, 30, 50, 0.75);
            --bg-input: rgba(0, 0, 0, 0.35);
            --bg-modal: rgba(8, 14, 26, 0.85);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-accent: #38bdf8;
            --text-success: #34d399;
            --border-card: rgba(56, 189, 248, 0.12);
            --border-input: rgba(255, 255, 255, 0.06);
            --border-focus: #38bdf8;
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.6);
            --shadow-btn: 0 4px 20px rgba(0, 0, 0, 0.25);
            --nav-bg: rgba(11, 17, 32, 0.7);
            --nav-border: rgba(56, 189, 248, 0.06);
            --blob-1: rgba(56, 189, 248, 0.15);
            --blob-2: rgba(52, 211, 153, 0.10);
            --blob-3: rgba(251, 146, 60, 0.08);
            --overlay: rgba(5, 10, 20, 0.60);
            --footer-color: rgba(255, 255, 255, 0.25);
            --gradient-main: linear-gradient(135deg, #38bdf8, #34d399);
            --pkg-price-bg: rgba(52, 211, 153, 0.15);
            --pkg-price-color: #34d399;
            --pkg-btn-bg: linear-gradient(135deg, #38bdf8, #34d399);
            --pkg-btn-hover: linear-gradient(135deg, #34d399, #0ea5e9);
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            background: var(--bg-primary);
            overflow-x: hidden;
            transition: background var(--transition), color var(--transition);
            cursor: default;
            font-size: 1.1rem;
            font-weight: 500;
            line-height: 1.6;
        }
        [data-theme="dark"] body {
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
        }

        /* ============================================================
           VIDEO BACKGROUND
        ============================================================ */
        .video-background {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -2;
            overflow: hidden;
            background: var(--bg-primary);
        }
        .video-background video {
            position: absolute; top: 50%; left: 50%;
            min-width: 100%; min-height: 100%;
            width: auto; height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            pointer-events: none;
        }
        .video-background .fallback {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            z-index: -1;
        }
        .video-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            background: var(--overlay);
            backdrop-filter: blur(2px);
            pointer-events: none;
            transition: background var(--transition);
        }

        /* ============================================================
           AMBIENT BLOBS
        ============================================================ */
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

        /* ============================================================
           FLOATING PARTICLES
        ============================================================ */
        .particles {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none; z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            width: 6px; height: 6px;
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

        /* ============================================================
           LOADER
        ============================================================ */
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

        /* ============================================================
           NAVBAR
        ============================================================ */
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
            font-size: 28px;
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

        /* Theme toggle inline */
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

        /* ============================================================
           HERO WRAPPER
        ============================================================ */
        .hero-wrapper {
            min-height: calc(100vh - 80px);
            display: flex; flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 40px 20px 60px;
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 44px;
            animation: fadeInDown 1s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }
        .header .badge {
            display: inline-block;
            padding: 8px 28px;
            border-radius: 50px;
            background: rgba(14, 165, 233, 0.10);
            border: 1px solid rgba(14, 165, 233, 0.15);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase; letter-spacing: 3px;
            color: var(--text-accent);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            transition: all var(--transition);
        }
        .header h1 {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 900;
            line-height: 1.05;
            background: var(--gradient-main);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmerText 4s ease-in-out infinite;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }
        @keyframes shimmerText {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .header p {
            font-size: clamp(1.2rem, 2.5vw, 1.6rem);
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: 1px;
            max-width: 500px;
            margin: 0 auto;
            transition: color var(--transition);
        }

        /* ============================================================
           ACTION BAR (Search only)
        ============================================================ */
        .action-bar {
            display: flex; flex-wrap: wrap; gap: 14px;
            width: 100%;
            max-width: 1200px;
            margin-bottom: 30px;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            backdrop-filter: blur(32px) saturate(1.5);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 14px 24px;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
        }
        .action-bar .search-box {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            background: var(--bg-input);
            border-radius: 16px;
            padding: 0 16px;
            border: 1px solid var(--border-input);
            transition: all var(--transition);
        }
        .action-bar .search-box:focus-within {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 4px rgba(14,165,233,0.08);
            background: var(--bg-card);
        }
        .action-bar .search-box i {
            color: var(--text-muted);
            font-size: 16px;
            margin-right: 10px;
        }
        .action-bar .search-box input {
            background: transparent;
            border: none;
            padding: 12px 0;
            color: var(--text-primary);
            width: 100%;
            outline: none;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 500;
        }
        .action-bar .search-box input::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        /* ============================================================
           PACKAGE GRID
        ============================================================ */
        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 28px;
            width: 100%;
            max-width: 1200px;
        }
        .package-card {
            background: var(--bg-card);
            backdrop-filter: blur(32px) saturate(1.5);
            border: 1px solid var(--border-card);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
            transform: translateY(0);
        }
        .package-card:hover {
            transform: translateY(-10px) scale(1.01);
            border-color: var(--text-accent);
            box-shadow: 0 25px 70px rgba(0,0,0,0.12);
        }
        .package-card .card-image {
            height: 200px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        .package-card .card-image img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .package-card:hover .card-image img { transform: scale(1.05); }
        .package-card .card-image .no-image {
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.12);
            font-size: 56px;
        }
        .package-card .card-image .price-tag {
            position: absolute;
            bottom: 14px; right: 14px;
            padding: 6px 20px;
            background: var(--pkg-price-bg);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            border: 1px solid var(--pkg-price-color);
            font-size: 18px;
            font-weight: 700;
            color: var(--pkg-price-color);
        }
        .package-card .card-image .price-tag small {
            font-size: 12px; font-weight: 400; opacity: 0.7;
        }
        .package-card .card-body {
            padding: 20px 22px 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .package-card .card-body .pkg-title {
            font-size: 20px; font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .package-card .card-body .pkg-title i {
            color: var(--text-accent);
            margin-right: 8px;
        }
        .package-card .card-body .pkg-desc {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.6;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .package-card .card-body .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 6px;
            flex-wrap: wrap;
        }
        .package-card .card-body .btn-map,
        .package-card .card-body .btn-book {
            padding: 10px 18px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            flex: 1;
        }
        .package-card .card-body .btn-map {
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            color: var(--text-secondary);
        }
        .package-card .card-body .btn-map:hover {
            background: var(--text-accent);
            color: #fff;
            border-color: var(--text-accent);
            transform: translateY(-2px);
        }
        .package-card .card-body .btn-book {
            background: var(--pkg-btn-bg);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.2);
        }
        .package-card .card-body .btn-book:hover {
            background: var(--pkg-btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(14,165,233,0.3);
        }
        .package-card .card-body .btn-book i { font-size: 16px; }

        /* ============================================================
           EMPTY STATE
        ============================================================ */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 30px;
            background: var(--bg-card);
            backdrop-filter: blur(32px) saturate(1.5);
            border-radius: 28px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
        }
        .empty-state i {
            font-size: 64px;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 16px;
            display: block;
        }
        .empty-state h3 {
            font-size: 24px; font-weight: 700;
            color: var(--text-primary);
        }
        .empty-state p {
            color: var(--text-muted);
            margin-top: 4px;
            font-size: 16px;
        }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer {
            width: 100%;
            max-width: 1200px;
            text-align: center;
            padding: 30px 20px 14px;
            opacity: 0.6;
            font-size: 13px;
            letter-spacing: 1px;
            font-weight: 500;
            color: var(--footer-color);
            border-top: 1px solid var(--border-card);
            margin-top: 50px;
            transition: all var(--transition);
        }
        .footer .social-icons {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-bottom: 12px;
        }
        .footer .social-icons a {
            color: var(--footer-color);
            font-size: 20px;
            transition: all 0.3s ease;
            opacity: 0.6;
            text-decoration: none;
        }
        .footer .social-icons a:hover {
            color: var(--text-accent);
            opacity: 1;
            transform: translateY(-3px) scale(1.1);
        }
        .footer .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 12px;
            letter-spacing: 2px;
            font-weight: 600;
            color: var(--text-success);
        }
        .footer .status .dot {
            width: 8px; height: 8px;
            background: var(--text-success);
            border-radius: 50%;
            animation: pulseDot 1.5s ease-in-out infinite;
            display: inline-block;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* ============================================================
           MODAL (Map only)
        ============================================================ */
        .modal-overlay {
            position: fixed; inset: 0;
            background: var(--bg-modal);
            backdrop-filter: blur(32px) saturate(1.5);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-overlay .modal-content {
            background: var(--bg-card);
            backdrop-filter: blur(32px) saturate(1.5);
            border: 1px solid var(--border-card);
            border-radius: 40px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-content .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 24px;
            background: rgba(15, 23, 42, 0.9);
            border-bottom: 1px solid var(--border-card);
        }
        .modal-content .modal-header h3 {
            font-size: 18px; font-weight: 700;
            color: #fff;
            display: flex; align-items: center; gap: 10px;
        }
        .modal-content .modal-header h3 i { color: var(--text-accent); }
        .modal-content .modal-header .close-btn {
            width: 36px; height: 36px;
            border-radius: 12px;
            border: none;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-content .modal-header .close-btn:hover {
            background: rgba(239,68,68,0.2);
            color: #f87171;
        }
        .modal-content .modal-body {
            padding: 24px;
        }
        .modal-map-wrapper {
            height: 400px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            background: #1e293b;
        }
        .modal-map-wrapper #map { width: 100%; height: 100%; }
        .modal-map-footer {
            display: flex; justify-content: space-between;
            padding: 12px 20px;
            background: var(--bg-input);
            border-top: 1px solid var(--border-card);
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            border-radius: 0 0 40px 40px;
        }

        /* ============================================================
           SCROLL TO TOP
        ============================================================ */
        .scroll-top {
            position: fixed;
            bottom: 30px; right: 30px;
            width: 54px; height: 54px;
            border-radius: 50%;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-card);
            color: var(--text-accent);
            font-size: 24px;
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

        /* ============================================================
           CUSTOM CURSOR
        ============================================================ */
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

        /* ============================================================
           ANIMATIONS
        ============================================================ */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 992px) {
            .navbar {
                padding: 14px 24px;
                flex-direction: column;
                align-items: stretch;
            }
            .nav-group {
                flex-wrap: wrap;
                justify-content: center;
                gap: 12px;
            }
            .theme-toggle-inline {
                border-left: none;
                padding-left: 0;
                justify-content: center;
            }
            .modal-content { max-width: 90%; }
        }
        @media (max-width: 768px) {
            .hero-wrapper { padding: 20px 16px; }
            .header h1 { font-size: 2.8rem; }
            .package-grid { grid-template-columns: 1fr; }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 14px 18px;
            }
            .modal-content { max-width: 100%; margin: 10px; border-radius: 28px; }
            .modal-map-wrapper { height: 280px; }
            .blob-1 { width: 300px; height: 300px; }
            .blob-2 { width: 250px; height: 250px; }
            .blob-3 { width: 200px; height: 200px; }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 2.2rem; }
            .navbar { padding: 12px 16px; }
            .nav-links a { padding: 6px 12px; font-size: 13px; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    BACKGROUND VIDEO
    ============================================================ -->
    <div class="video-background">
        <div class="fallback"></div>
        <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1920&q=80">
            <source src="videos/19329370-uhd_3840_2160_24fps.mp4" type="video/mp4">
            <source src="videos/background.webm" type="video/webm">
            Your browser does not support video.
        </video>
    </div>
    <div class="video-overlay"></div>

    <!-- AMBIENT BLOBS -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- FLOATING PARTICLES -->
    <div class="particles" id="particles"></div>

    <!-- LOADER -->
    <div class="loader" id="loader">
        <div class="loader-ring"></div>
        <div class="loader-text">Loading Packages</div>
    </div>

    <!-- CUSTOM CURSOR -->
    <div class="cursor-dot" id="cursorDot"></div>
    <div class="cursor-ring" id="cursorRing"></div>

    <!-- ============================================================
    NAVBAR
    ============================================================ -->
    <nav class="navbar">
        <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
        <div class="nav-group">
            <div class="nav-links">
                <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
                <a href="packages.php" class="active"><i class="fa-solid fa-suitcase"></i> Packages</a>
                <a href="about.php"><i class="fa-solid fa-info-circle"></i> About</a>
                <a href="contact.php"><i class="fa-solid fa-envelope"></i> Contact</a>
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
    </nav>

    <!-- ============================================================
    HERO + CONTENT
    ============================================================ -->
    <div class="hero-wrapper">

        <div class="header">
            <span class="badge"><i class="fa-solid fa-sparkles"></i> Explore Our Packages</span>
            <h1 id="typing-heading">Discover Your Next Adventure</h1>
            <p>Curated tours with the best experiences – book now and travel with ease.</p>
        </div>

        <!-- Action Bar (Search only) -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search packages by title or description..." oninput="filterPackages()">
            </div>
        </div>

        <!-- Package Grid -->
        <div class="package-grid" id="packageGrid">
            <?php if (count($packages) > 0): ?>
                <?php foreach ($packages as $index => $pkg):
                    $image_url = $pkg['image'] ?? '';
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
                <div class="package-card" data-title="<?php echo strtolower($pkg['title']); ?>" data-desc="<?php echo strtolower($pkg['description']); ?>">
                    <div class="card-image">
                        <?php if ($has_image && $image_display): ?>
                            <img src="<?php echo htmlspecialchars($image_display); ?>" alt="<?php echo htmlspecialchars($pkg['title']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="no-image" style="display:none;"><i class="fa-solid fa-image"></i></div>
                        <?php else: ?>
                            <div class="no-image"><i class="fa-solid fa-image"></i></div>
                        <?php endif; ?>
                        <div class="price-tag">Rs. <?php echo number_format($pkg['price'], 2); ?> <small>/pax</small></div>
                    </div>
                    <div class="card-body">
                        <div class="pkg-title"><i class="fa-solid fa-map-location-dot"></i> <?php echo htmlspecialchars($pkg['title']); ?></div>
                        <div class="pkg-desc"><?php echo htmlspecialchars($pkg['description']); ?></div>
                        <div class="card-actions">
                            <button class="btn-map" onclick="openMapModal('<?php echo $pkg['latitude']; ?>', '<?php echo $pkg['longitude']; ?>', '<?php echo $pkg['latitude2']; ?>', '<?php echo $pkg['longitude2']; ?>', '<?php echo addslashes(htmlspecialchars($pkg['title'])); ?>')">
                                <i class="fa-solid fa-map-location-dot"></i> Route
                            </button>
                            <a href="index.php" class="btn-book"><i class="fa-solid fa-bookmark"></i> Book Now</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>No Packages Found</h3>
                    <p>There are no packages available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="social-icons">
                <a href="#"><i class="fa-brands fa-facebook"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="#"><i class="fa-brands fa-youtube"></i></a>
                <a href="#"><i class="fa-brands fa-linkedin"></i></a>
            </div>
            <div class="status">
                <span class="dot"></span> All Systems Operational
            </div>
            <div style="margin-top: 8px;">© <?php echo date('Y'); ?> Travel &amp; Tour Management System — Connected Globally</div>
        </div>
    </div>

    <!-- ============================================================
    MAP MODAL (Only the route map remains)
    ============================================================ -->
    <div id="mapModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-route"></i> <span id="modalMapTitle">Package Route</span></h3>
                <button class="close-btn" onclick="closeMapModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-map-wrapper"><div id="map"></div></div>
                <div class="modal-map-footer">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;margin-right:6px;"></span> Start</span>
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3b82f6;margin-right:6px;"></span> Destination</span>
                </div>
            </div>
        </div>
    </div>

    <!-- SCROLL TO TOP -->
    <div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fa-solid fa-arrow-up"></i>
    </div>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script>
        // ============================================================
        // LOADER
        // ============================================================
        window.addEventListener("load", () => {
            const loader = document.getElementById("loader");
            setTimeout(() => loader.classList.add("hidden"), 600);
            createParticles();
        });

        // ============================================================
        // VIDEO AUTOPLAY
        // ============================================================
        document.addEventListener("DOMContentLoaded", function() {
            const video = document.getElementById('bgVideo');
            if (video) {
                video.play().catch(() => {
                    document.addEventListener('click', function playVideo() {
                        video.play();
                        document.removeEventListener('click', playVideo);
                    }, { once: true });
                });
            }
        });

        // ============================================================
        // THEME TOGGLE
        // ============================================================
        (function initTheme() {
            const toggle = document.getElementById('themeToggle');
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const saved = localStorage.getItem('travel-theme');
            if (saved === 'dark') {
                html.setAttribute('data-theme', 'dark');
                toggle.checked = true;
                if (icon) { icon.classList.add('spin'); }
            } else {
                html.removeAttribute('data-theme');
                toggle.checked = false;
                if (icon) { icon.classList.remove('spin'); }
                localStorage.setItem('travel-theme', 'light');
            }
            toggle.addEventListener('change', function() {
                const iconEl = document.getElementById('themeIcon');
                if (this.checked) {
                    html.setAttribute('data-theme', 'dark');
                    localStorage.setItem('travel-theme', 'dark');
                    if (iconEl) { iconEl.classList.add('spin'); }
                } else {
                    html.removeAttribute('data-theme');
                    localStorage.setItem('travel-theme', 'light');
                    if (iconEl) { iconEl.classList.remove('spin'); }
                }
            });
        })();

        // ============================================================
        // CUSTOM CURSOR
        // ============================================================
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

        // ============================================================
        // SCROLL TO TOP
        // ============================================================
        const scrollBtn = document.getElementById('scrollTopBtn');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 400) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        });

        // ============================================================
        // TYPING EFFECT
        // ============================================================
        const heading = document.getElementById('typing-heading');
        const originalText = heading.innerText;
        heading.innerText = '';
        let charIndex = 0;
        function typeWriter() {
            if (charIndex < originalText.length) {
                heading.innerText += originalText.charAt(charIndex);
                charIndex++;
                setTimeout(typeWriter, 80);
            }
        }
        setTimeout(typeWriter, 1000);

        // ============================================================
        // FLOATING PARTICLES
        // ============================================================
        function createParticles() {
            const container = document.getElementById('particles');
            const count = 35;
            for (let i = 0; i < count; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 6 + 3;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
                particle.style.animationDelay = (Math.random() * 20) + 's';
                particle.style.opacity = Math.random() * 0.2 + 0.05;
                container.appendChild(particle);
            }
        }

        // ============================================================
        // SEARCH FILTER
        // ============================================================
        function filterPackages() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.package-card');
            let found = 0;
            cards.forEach(card => {
                const title = card.dataset.title || '';
                const desc = card.dataset.desc || '';
                if (title.includes(query) || desc.includes(query)) {
                    card.style.display = '';
                    found++;
                } else {
                    card.style.display = 'none';
                }
            });
            const empty = document.querySelector('.empty-state');
            if (empty) {
                empty.style.display = found === 0 ? 'block' : 'none';
            }
        }

        // ============================================================
        // MAP MODAL
        // ============================================================
        let map, markers = [], polyline;

        function openMapModal(lat1, lng1, lat2, lng2, title) {
            const modal = document.getElementById('mapModal');
            document.getElementById('modalMapTitle').textContent = title + ' Journey';
            modal.classList.add('active');

            const loc1 = { lat: parseFloat(lat1) || 6.9271, lng: parseFloat(lng1) || 79.8612 };
            const loc2 = { lat: parseFloat(lat2) || 7.2906, lng: parseFloat(lng2) || 80.6337 };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 8, center: loc1, zoomControl: true, mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            markers.forEach(m => m.setMap(null));
            markers = [];
            if (polyline) polyline.setMap(null);

            markers.push(new google.maps.Marker({ position: loc1, map: map, label: { text: 'P', color: 'white', fontWeight: 'bold' } }));
            markers.push(new google.maps.Marker({ position: loc2, map: map, label: { text: 'D', color: 'white', fontWeight: 'bold' } }));

            polyline = new google.maps.Polyline({ path: [loc1, loc2], geodesic: true, strokeColor: '#3b82f6', strokeOpacity: 0.9, strokeWeight: 4 });
            polyline.setMap(map);

            const bounds = new google.maps.LatLngBounds();
            bounds.extend(loc1);
            bounds.extend(loc2);
            map.fitBounds(bounds);
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.remove('active');
        }

        // ============================================================
        // CLOSE MODAL ON OUTSIDE CLICK
        // ============================================================
        window.onclick = function(event) {
            const mapModal = document.getElementById('mapModal');
            if (event.target === mapModal) closeMapModal();
        };

        // ============================================================
        // CONSOLE
        // ============================================================
        console.log('%c📦 Package Manager (Display Only - No Add/Delete)', 'font-size:18px; font-weight:bold; color:#0ea5e9;');
        console.log('%c✅ Features: Browse | Search | View Route | Book Now', 'font-size:13px; color:#10b981;');
    </script>

</body>
</html>