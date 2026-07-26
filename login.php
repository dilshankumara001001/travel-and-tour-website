<?php
// Start session to manage logged-in users
session_start();

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'travel_tour_db';

$error = null;    // To store error messages
$success = null;  // To store success messages

// ---------- LOGIN ----------
// This runs when the login form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Connect to the database
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    // Get email and password from the form
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $logged_in = false;  // Will be true if credentials match
    $user_id   = null;
    $full_name = null;
    $role      = null;

    // Step 1: Check the 'users' table (passwords are hashed with password_hash)
    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Verify the entered password against the stored hash
        if (password_verify($password, $row['password'])) {
            $logged_in = true;
            $user_id   = $row['id'];
            $full_name = $row['full_name'];
            $role      = $row['role'];
        }
    }
    $stmt->close();

    // Step 2: If not found in 'users', check the 'admin' table (plain text passwords)
    if (!$logged_in) {
        $stmt = $conn->prepare("SELECT AdminID, FullName, Password FROM admin WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Plain text comparison for admin
            if ($password === $row['Password']) {
                $logged_in = true;
                $user_id   = $row['AdminID'];
                $full_name = $row['FullName'];
                $role      = 'admin';
            }
        }
        $stmt->close();
    }

    // Step 3: If not found in 'users' or 'admin', check 'vehicle_manager' table (SHA1 hashed passwords)
    if (!$logged_in) {
        $stmt = $conn->prepare("SELECT id, name, password, role FROM vehicle_manager WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // SHA1 comparison for vehicle manager (matches vehicle_manager_login.php)
            if (sha1($password) === $row['password']) {
                $logged_in = true;
                $user_id   = $row['id'];
                $full_name = $row['name'];
                $role      = 'vehicle_manager';
            }
        }
        $stmt->close();
    }

    $conn->close();

    // If login successful, store user info in session and redirect
    if ($logged_in) {
        $_SESSION['user_id']  = $user_id;
        $_SESSION['fullname'] = $full_name;
        $_SESSION['username'] = $full_name;
        $_SESSION['role']     = $role;

        // Redirect based on role
        if ($role === 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($role === 'vehicle_manager') {
            // Vehicle Manager goes to their dashboard
            $_SESSION['manager_id']   = $user_id;
            $_SESSION['manager_name'] = $full_name;
            $_SESSION['manager_email'] = $email;
            header("Location: vehicle_management/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}

// ---------- SIGNUP ----------
// This runs when the signup form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    // Connect to the database
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    // Get form data
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if the email is already registered (unique constraint)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $stmt->close();
            // Hash the password and insert new user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role   = 'user';  // default role
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hashed, $role);
            if ($stmt->execute()) {
                $success = "Account created successfully! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login / Sign Up | Travel & Tour</title>

    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ============================================================
           YOUR ORIGINAL INDEX.PHP CSS — UNCHANGED
           ============================================================ */
        :root {
            --bg-primary: #f0f4f8;
            --bg-secondary: #e2e8f0;
            --bg-card: rgba(255, 255, 255, 0.88);
            --bg-input: rgba(255, 255, 255, 0.65);
            --bg-modal: rgba(220, 230, 240, 0.80);
            --text-primary: #0a0a1a;
            --text-secondary: #1a1a2e;
            --text-muted: #2d3748;
            --text-accent: #0ea5e9;
            --text-success: #10b981;
            --border-card: rgba(0, 0, 0, 0.06);
            --border-input: rgba(0, 0, 0, 0.08);
            --border-focus: #0ea5e9;
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.07);
            --shadow-btn: 0 4px 20px rgba(0, 0, 0, 0.06);
            --nav-bg: rgba(255, 255, 255, 0.80);
            --nav-border: rgba(0, 0, 0, 0.04);
            --blob-1: rgba(14, 165, 233, 0.10);
            --blob-2: rgba(16, 185, 129, 0.07);
            --blob-3: rgba(251, 146, 60, 0.05);
            --overlay: rgba(255, 255, 255, 0.30);
            --footer-color: rgba(0, 0, 0, 0.25);
            --transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            --gradient-main: linear-gradient(135deg, #0ea5e9, #10b981);
            --feature-icon-color: #0ea5e9;
            --card-radius: 40px;
            --input-radius: 18px;
        }

        [data-theme="dark"] {
            --bg-primary: #0b1120;
            --bg-secondary: #151f33;
            --bg-card: rgba(18, 30, 50, 0.78);
            --bg-input: rgba(0, 0, 0, 0.35);
            --bg-modal: rgba(8, 14, 26, 0.85);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-accent: #38bdf8;
            --text-success: #34d399;
            --border-card: rgba(56, 189, 248, 0.10);
            --border-input: rgba(255, 255, 255, 0.06);
            --border-focus: #38bdf8;
            --shadow-card: 0 30px 80px rgba(0, 0, 0, 0.6);
            --shadow-btn: 0 4px 20px rgba(0, 0, 0, 0.25);
            --nav-bg: rgba(11, 17, 32, 0.70);
            --nav-border: rgba(56, 189, 248, 0.06);
            --blob-1: rgba(56, 189, 248, 0.15);
            --blob-2: rgba(52, 211, 153, 0.10);
            --blob-3: rgba(251, 146, 60, 0.08);
            --overlay: rgba(5, 10, 20, 0.50);
            --footer-color: rgba(255, 255, 255, 0.20);
            --gradient-main: linear-gradient(135deg, #38bdf8, #34d399);
            --feature-icon-color: #38bdf8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            background: var(--bg-primary);
            overflow-x: hidden;
            transition: background var(--transition), color var(--transition);
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.6;
            cursor: default;
        }

        /* ===== VIDEO ===== */
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

        /* ===== BLOBS ===== */
        .blob {
            position: fixed; border-radius: 50%; filter: blur(120px);
            pointer-events: none; z-index: -1;
            animation: blobFloat 18s ease-in-out infinite alternate;
            transition: background var(--transition);
        }
        .blob-1 { width: 700px; height: 700px; background: var(--blob-1); top: -250px; left: -250px; }
        .blob-2 { width: 550px; height: 550px; background: var(--blob-2); bottom: -200px; right: -200px; animation-delay: 6s; }
        .blob-3 { width: 450px; height: 450px; background: var(--blob-3); top: 40%; left: 70%; animation-delay: 11s; }
        @keyframes blobFloat {
            0% { transform: translate(0,0) scale(1); }
            33% { transform: translate(70px,-60px) scale(1.15); }
            66% { transform: translate(-50px,70px) scale(0.9); }
            100% { transform: translate(60px,-50px) scale(1.05); }
        }

        /* ===== PARTICLES ===== */
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
            opacity: 0.12;
            animation: floatParticle linear infinite;
            transition: background var(--transition);
        }
        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg) scale(0); opacity: 0; }
            10% { opacity: 0.12; }
            90% { opacity: 0.12; }
            100% { transform: translateY(-10vh) rotate(720deg) scale(1); opacity: 0; }
        }

        /* ===== LOADER ===== */
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
            animation: spin 1s cubic-bezier(0.68,-0.55,0.27,1.55) infinite;
            position: relative;
        }
        .loader-ring::after {
            content: ''; position: absolute; inset: 10px;
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
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulseText { 0%,100% { opacity:0.4; } 50% { opacity:1; } }

        /* ===== NAVBAR (EXACTLY FROM INDEX) ===== */
        .navbar {
            position: sticky; top: 0; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 48px;
            background: var(--nav-bg);
            backdrop-filter: blur(28px) saturate(1.8);
            border-bottom: 1px solid var(--nav-border);
            box-shadow: 0 4px 40px rgba(0,0,0,0.08);
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
            filter: drop-shadow(0 0 20px rgba(14,165,233,0.2));
        }
        .nav-group {
            display: flex; align-items: center; gap: 24px;
        }
        .nav-links {
            display: flex; align-items: center; gap: 4px;
        }
        .nav-links a {
            padding: 8px 18px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            transition: all var(--transition);
            position: relative;
        }
        .nav-links a::after {
            content: ''; position: absolute; bottom: 2px; left: 50%;
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
            background: rgba(14,165,233,0.08);
        }
        .nav-links a i { margin-right: 6px; font-size: 13px; }

        .theme-toggle-inline {
            display: flex; align-items: center; gap: 12px;
            padding-left: 16px;
            border-left: 1px solid var(--border-card);
        }
        .theme-toggle-inline .toggle-icon {
            color: var(--text-muted);
            font-size: 18px;
            transition: all 0.5s cubic-bezier(0.68,-0.55,0.27,1.55);
        }
        .theme-toggle-inline .toggle-icon.spin { transform: rotate(360deg) scale(1.2); color: var(--text-accent); }
        .theme-toggle-inline .toggle-switch {
            position: relative; width: 54px; height: 28px; flex-shrink: 0; cursor: pointer;
        }
        .theme-toggle-inline .toggle-switch input { opacity:0; width:0; height:0; }
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
            position: absolute; left: 3px; bottom: 3px;
            width: 20px; height: 20px;
            background: var(--text-accent);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            color: #fff;
            transition: all 0.5s cubic-bezier(0.68,-0.55,0.27,1.55);
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

        /* ===== HERO WRAPPER ===== */
        .hero-wrapper {
            min-height: calc(100vh - 80px);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 40px 20px 60px;
            position: relative;
            z-index: 1;
        }

        /* ===== CARD (EXACTLY FROM INDEX) ===== */
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(40px) saturate(1.6);
            padding: 50px 45px 45px;
            border-radius: var(--card-radius);
            width: 520px;
            max-width: 100%;
            text-align: center;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card), inset 0 1px 0 rgba(255,255,255,0.05);
            animation: fadeInUp 1s cubic-bezier(0.23,1,0.32,1) forwards;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
        }
        .card::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 30% 20%, rgba(14,165,233,0.04), transparent 70%);
            pointer-events: none;
        }
        .card::after {
            content: '';
            position: absolute; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient(from 0deg at 50% 50%, transparent, rgba(14,165,233,0.02), transparent 60%);
            animation: rotateGlow 30s linear infinite;
            pointer-events: none;
        }
        @keyframes rotateGlow { to { transform: rotate(360deg); } }
        .card:hover {
            transform: translateY(-6px) rotateX(1.5deg) rotateY(1.5deg);
            border-color: rgba(14,165,233,0.15);
            box-shadow: 0 40px 100px rgba(0,0,0,0.3), 0 0 60px rgba(14,165,233,0.03);
        }

        .card .icon-big {
            font-size: 56px;
            color: var(--text-accent);
            margin-bottom: 12px;
            display: block;
            filter: drop-shadow(0 0 30px rgba(14,165,233,0.12));
        }
        .card h2 {
            font-size: 34px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }
        .card .sub-text {
            color: var(--text-muted);
            font-size: 16px;
            font-weight: 500;
            position: relative;
            z-index: 1;
            margin-bottom: 4px;
        }

        /* ===== LOGIN / SIGNUP FORM STYLES (MERGED) ===== */
        .form-group {
            position: relative;
            margin-bottom: 18px;
            text-align: left;
            z-index: 1;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1.5px solid var(--border-input);
            border-radius: var(--input-radius);
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        .form-group input:focus {
            border-color: var(--border-focus);
            outline: none;
            box-shadow: 0 0 0 5px rgba(14,165,233,0.06);
            transform: translateY(-1px);
        }
        .form-group input::placeholder {
            color: var(--text-muted);
            opacity: 0.4;
            font-weight: 400;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            bottom: 14px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
            transition: color 0.3s ease;
            z-index: 2;
            opacity: 0.5;
        }
        .toggle-password:hover {
            opacity: 1;
            color: var(--text-accent);
        }

        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 18px;
            z-index: 1;
            position: relative;
        }
        .forgot-password a {
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            opacity: 0.7;
        }
        .forgot-password a:hover {
            color: var(--text-accent);
            opacity: 1;
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 18px 28px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex; justify-content: center; align-items: center;
            gap: 12px;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
            box-shadow: var(--shadow-btn);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            z-index: 1;
        }
        .btn .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            transform: scale(0);
            animation: rippleAnim 0.8s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
        .btn::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.10), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .btn:hover::before { opacity: 1; }
        .btn:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
        .btn:active { transform: scale(0.97) translateY(0); }

        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7, #0369a1);
            background-size: 200% 200%;
            animation: gradientShift 4s ease-in-out infinite;
        }
        .btn-primary:hover { box-shadow: 0 12px 40px rgba(14,165,233,0.35); }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            background-size: 200% 200%;
            animation: gradientShift 4s ease-in-out infinite;
        }
        .btn-success:hover { box-shadow: 0 12px 40px rgba(16,185,129,0.35); }

        @keyframes gradientShift { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: left;
            position: relative;
            z-index: 1;
        }
        .error { background: rgba(239,68,68,0.08); color: #ef4444; border-left: 4px solid #ef4444; }
        .success { background: rgba(16,185,129,0.08); color: var(--text-success); border-left: 4px solid var(--text-success); }

        .toggle-link {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }
        .toggle-link a {
            color: var(--text-accent);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.3s;
        }
        .toggle-link a:hover {
            text-decoration: underline;
        }
        .hidden { display: none; }

        /* ===== FOOTER (FROM INDEX) ===== */
        .footer {
            width: 100%; max-width: 920px;
            text-align: center;
            padding: 30px 20px 14px;
            opacity: 0.5;
            font-size: 13px;
            letter-spacing: 1px;
            font-weight: 500;
            color: var(--footer-color);
            border-top: 1px solid var(--border-card);
            margin-top: 50px;
        }
        .footer .social-icons {
            display: flex; justify-content: center; gap: 18px;
            margin-bottom: 12px;
        }
        .footer .social-icons a {
            color: var(--footer-color);
            font-size: 20px;
            transition: all 0.3s ease;
            opacity: 0.5;
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
        @keyframes pulseDot { 0%,100% { opacity:0.3; transform:scale(0.8); } 50% { opacity:1; transform:scale(1.2); } }

        /* ===== SCROLL TO TOP ===== */
        .scroll-top {
            position: fixed; bottom: 30px; right: 30px;
            width: 54px; height: 54px;
            border-radius: 50%;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-card);
            color: var(--text-accent);
            font-size: 22px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            opacity: 0; visibility: hidden;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            z-index: 999;
        }
        .scroll-top.visible { opacity: 1; visibility: visible; }
        .scroll-top:hover {
            transform: translateY(-6px) scale(1.1);
            border-color: var(--text-accent);
            box-shadow: 0 15px 40px rgba(14,165,233,0.15);
        }

        /* ===== CUSTOM CURSOR ===== */
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
            opacity: 0.6;
        }
        .cursor-ring {
            position: fixed;
            width: 40px; height: 40px;
            border: 2px solid rgba(14,165,233,0.2);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transition: width 0.3s, height 0.3s, border-color 0.3s, transform 0.15s ease;
            transform: translate(-50%, -50%);
            mix-blend-mode: difference;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInDown { from { opacity:0; transform:translateY(-30px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(40px); } to { opacity:1; transform:translateY(0); } }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.03); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .card { padding: 32px 24px; width: 100%; }
            .card h2 { font-size: 28px; }
            .blob-1 { width: 300px; height: 300px; }
            .blob-2 { width: 250px; height: 250px; }
            .blob-3 { width: 200px; height: 200px; }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 2.4rem; }
            .btn { font-size: 14px; padding: 14px 18px; }
            .card { padding: 24px 18px; }
        }
    </style>
</head>
<body>

    <!-- ===== VIDEO BACKGROUND ===== -->
    <div class="video-background">
        <div class="fallback"></div>
        <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1920&q=80">
            <source src="videos/19329370-uhd_3840_2160_24fps.mp4" type="video/mp4">
            <source src="videos/background.webm" type="video/webm">
        </video>
    </div>
    <div class="video-overlay"></div>

    <!-- ===== BLOBS ===== -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- ===== PARTICLES ===== -->
    <div class="particles" id="particles"></div>

    <!-- ===== LOADER ===== -->
    <div class="loader" id="loader">
        <div class="loader-ring"></div>
        <div class="loader-text">Loading Experience</div>
    </div>

    <!-- ===== CUSTOM CURSOR ===== -->
    <div class="cursor-dot" id="cursorDot"></div>
    <div class="cursor-ring" id="cursorRing"></div>

    <!-- ===== NAVBAR (EXACTLY FROM INDEX) ===== -->
    <nav class="navbar">
        <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
        <div class="nav-group">
            <div class="nav-links">
                <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
                <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
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

    <!-- ===== HERO WRAPPER ===== -->
    <div class="hero-wrapper">

        <!-- ===== CARD ===== -->
        <div class="card" id="tilt-card">
            <span class="icon-big" id="formIcon"><i class="fa-solid fa-right-to-bracket"></i></span>
            <h2 id="formTitle">Welcome Back</h2>
            <p class="sub-text" id="formSub">Login to your account</p>

            <!-- Show error or success messages -->
            <?php if (isset($error)): ?>
                <div class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="message success"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <!-- ===== LOGIN FORM ===== -->
            <div id="loginForm">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" name="email" id="loginEmail" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" name="password" id="loginPassword" placeholder="Enter password" required>
                        <button type="button" class="toggle-password" id="toggleLoginPassword">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>

                    <!-- Forgot Password Link -->
                    <div class="forgot-password">
                        <a href="forgot_password.php"><i class="fa-regular fa-circle-question"></i> Forgot Password?</a>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fa-solid fa-right-to-bracket"></i> Login
                    </button>
                </form>
            </div>

            <!-- ===== SIGNUP FORM ===== -->
            <div id="signupForm" class="hidden">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="signupName">Full Name</label>
                        <input type="text" name="full_name" id="signupName" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="signupEmail">Email Address</label>
                        <input type="email" name="email" id="signupEmail" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="signupPassword">Password (min 6 chars)</label>
                        <input type="password" name="password" id="signupPassword" placeholder="Create password" required>
                        <button type="button" class="toggle-password" id="toggleSignupPassword">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-group">
                        <label for="signupConfirm">Confirm Password</label>
                        <input type="password" name="confirm_password" id="signupConfirm" placeholder="Retype password" required>
                        <button type="button" class="toggle-password" id="toggleSignupConfirm">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <button type="submit" name="signup" class="btn btn-success">
                        <i class="fa-solid fa-user-plus"></i> Create Account
                    </button>
                </form>
            </div>

            <!-- Toggle between Login and Signup -->
            <div class="toggle-link" id="toggleLink">
                Don't have an account? <a id="toggleSwitch">Sign up</a>
            </div>
        </div>

        <!-- ===== FOOTER (FROM INDEX) ===== -->
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
            <div style="margin-top: 8px;">© 2026 Travel &amp; Tour Management System — Designed for Discovery</div>
        </div>
    </div>

    <!-- ===== SCROLL TO TOP ===== -->
    <div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fa-solid fa-arrow-up"></i>
    </div>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script>
        // ===== LOADER =====
        window.addEventListener("load", () => {
            const loader = document.getElementById("loader");
            setTimeout(() => loader.classList.add("hidden"), 600);
            createParticles();
        });

        // ===== VIDEO AUTOPLAY =====
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

        // ===== THEME TOGGLE =====
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

        // ===== BUTTON RIPPLE =====
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                const size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 800);
            });
        });

        // ===== 3D TILT =====
        const tiltCard = document.getElementById('tilt-card');
        if (tiltCard) {
            tiltCard.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -6;
                const rotateY = ((x - centerX) / centerX) * 6;
                this.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
            });
            tiltCard.addEventListener('mouseleave', function() {
                this.style.transform = 'perspective(800px) rotateX(0) rotateY(0) translateY(0)';
            });
        }

        // ===== CUSTOM CURSOR =====
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

        // ===== SCROLL TO TOP =====
        const scrollBtn = document.getElementById('scrollTopBtn');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 400) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        });

        // ===== PARTICLES =====
        function createParticles() {
            const container = document.getElementById('particles');
            const count = 40;
            for (let i = 0; i < count; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 6 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 22 + 14) + 's';
                particle.style.animationDelay = (Math.random() * 22) + 's';
                particle.style.opacity = Math.random() * 0.18 + 0.03;
                container.appendChild(particle);
            }
        }

        // ============================================================
        // LOGIN / SIGNUP TOGGLE (FROM YOUR ORIGINAL login.php)
        // ============================================================
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');
        const toggleLink = document.getElementById('toggleSwitch');
        const formTitle = document.getElementById('formTitle');
        const formSub = document.getElementById('formSub');
        const formIcon = document.getElementById('formIcon');

        let isLogin = true;

        toggleLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (isLogin) {
                // Switch to Signup
                loginForm.classList.add('hidden');
                signupForm.classList.remove('hidden');
                formTitle.textContent = 'Create Account';
                formSub.textContent = 'Join us and start your journey!';
                formIcon.innerHTML = '<i class="fa-solid fa-user-plus"></i>';
                toggleLink.textContent = 'Already have an account? Login';
                isLogin = false;
            } else {
                // Switch to Login
                loginForm.classList.remove('hidden');
                signupForm.classList.add('hidden');
                formTitle.textContent = 'Welcome Back';
                formSub.textContent = 'Login to your account';
                formIcon.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i>';
                toggleLink.textContent = "Don't have an account? Sign up";
                isLogin = true;
            }
        });

        // ============================================================
        // PASSWORD SHOW / HIDE TOGGLE
        // ============================================================
        function togglePasswordVisibility(inputId, buttonId) {
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            if (!input || !button) return;
            const icon = button.querySelector('i');

            button.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fa-regular fa-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'fa-regular fa-eye';
                }
            });
        }

        togglePasswordVisibility('loginPassword', 'toggleLoginPassword');
        togglePasswordVisibility('signupPassword', 'toggleSignupPassword');
        togglePasswordVisibility('signupConfirm', 'toggleSignupConfirm');

        // ============================================================
        // KEYBOARD SHORTCUT: Ctrl + Shift + P
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                const loginPassword = document.getElementById('loginPassword');
                const toggleBtn = document.getElementById('toggleLoginPassword');
                if (loginPassword && !loginPassword.closest('.hidden')) {
                    toggleBtn.click();
                }
            }
        });

        console.log('%c🔐 Login Page Loaded', 'font-size:16px; font-weight:bold; color:#0ea5e9;');
        console.log('%c💡 Tip: Press Ctrl+Shift+P to toggle password visibility', 'font-size:12px; color:#6c7a8a;');
    </script>

</body>
</html>