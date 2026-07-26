<?php
include("../includes/db.php");
session_start();

// Only admin can access this page
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_vehicle_manager'])) {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if email already exists in vehicle_manager table
        $stmt = $conn->prepare("SELECT id FROM vehicle_manager WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email already registered as a Vehicle Manager.";
        } else {
            $stmt->close();
            // Hash password using sha1 (matching vehicle_manager_login.php)
            $hashed_password = sha1($password);
            $status = 'active';
            
            $stmt = $conn->prepare("INSERT INTO vehicle_manager (name, email, password, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $status);
            
            if ($stmt->execute()) {
                $success = "Vehicle Manager account created successfully! They can now login.";
            } else {
                $error = "Failed to create account. Please try again.";
            }
        }
        $stmt->close();
    }
}

// Admin name for display
$admin_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin';
$display_admin = "Admin: " . $admin_name;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Vehicle Manager | Travel & Tour</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* ============================================================
           CSS VARIABLES – SAME AS DASHBOARD
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

        /* ===== NAVBAR (Match Dashboard) ===== */
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

        /* ===== MAIN CONTENT ===== */
        .main {
            padding: 24px 32px 40px;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .form-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 40px 48px;
            box-shadow: var(--shadow-card);
            animation: fadeInUp 0.8s ease forwards;
            transition: all var(--transition);
        }
        .form-card:hover {
            border-color: rgba(14,165,233,0.2);
        }
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
            border-bottom: 1px solid var(--border-card);
            padding-bottom: 18px;
        }
        .form-card .form-header .icon {
            font-size: 32px;
            color: var(--text-accent);
            background: rgba(14,165,233,0.08);
            padding: 12px;
            border-radius: 16px;
        }
        .form-card .form-header h2 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            transition: color var(--transition);
        }
        .form-card .form-header p {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-top: 2px;
            transition: color var(--transition);
        }

        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 6px;
            transition: color var(--transition);
        }
        .form-group label .required {
            color: var(--text-danger);
            margin-left: 4px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: var(--bg-input);
            border: 1.5px solid var(--border-input);
            border-radius: 14px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus {
            border-color: var(--text-accent);
            outline: none;
            box-shadow: 0 0 0 5px rgba(14,165,233,0.06);
        }
        .form-group input::placeholder {
            color: var(--text-muted);
            opacity: 0.4;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 16px;
            background: var(--gradient-main);
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 30px rgba(14,165,233,0.25);
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(14,165,233,0.35);
        }
        .btn-submit:active {
            transform: scale(0.97);
        }

        .message {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message.error {
            background: rgba(239,68,68,0.08);
            color: var(--text-danger);
            border-left: 4px solid var(--text-danger);
        }
        .message.success {
            background: rgba(16,185,129,0.08);
            color: var(--text-success);
            border-left: 4px solid var(--text-success);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: var(--text-accent);
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity:0; transform:translateY(-30px); }
            to { opacity:1; transform:translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(40px); }
            to { opacity:1; transform:translateY(0); }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width:8px; height:8px; }
        ::-webkit-scrollbar-track { background:rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background:linear-gradient(var(--text-accent), var(--text-success));
            border-radius:10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .form-card { padding: 24px 20px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left:none; padding-left:0; justify-content:center; }
        }
        @media (max-width: 480px) {
            .form-card .form-header h2 { font-size: 22px; }
            .btn-submit { font-size:16px; }
        }
    </style>
</head>
<body>

<!-- ===== BACKGROUND ===== -->
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
    <div class="loader-text">Loading</div>
</div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
    <div class="nav-group">
        <div class="nav-links">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9); color:#fff !important; padding:8px 20px; border-radius:30px; font-weight:700; box-shadow:0 4px 15px rgba(139,92,246,0.3); text-decoration:none;">
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

<!-- ===== MAIN FORM ===== -->
<main class="main">
    <div class="form-card">
        <div class="form-header">
            <span class="icon"><i class="fa-solid fa-truck"></i></span>
            <div>
                <h2>Create Vehicle Manager</h2>
                <p>Add a new vehicle manager account to the system</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="message success"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" placeholder="e.g. Nimal Perera" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" name="email" id="email" placeholder="manager@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" placeholder="Min 6 characters" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Retype password" required>
            </div>
            <button type="submit" name="create_vehicle_manager" class="btn-submit">
                <i class="fa-solid fa-user-plus"></i> Create Vehicle Manager
            </button>
        </form>

        <a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</main>

<!-- ===== SCRIPTS ===== -->
<script>
    // Loader
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        setTimeout(() => { loader.classList.add("hidden"); }, 600);
        createParticles();
    });

    // Video autoplay
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

    // Theme toggle
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
</script>

</body>
</html> 