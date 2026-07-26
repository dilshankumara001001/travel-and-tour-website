<?php
// ============================================================
//  BACKEND LOGIC — UNCHANGED (exactly as you provided)
// ============================================================
include("includes/db.php"); 

$submission_success = false;

if (isset($_POST['submit'])) {
    
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO contact_feedbacks (name, email, subject, message) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_stmt, "ssss", $name, $email, $subject, $message);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $submission_success = true;
    } else {
        echo "<script>alert('Transmission failed. Please try again.');</script>";
    }
    
    mysqli_stmt_close($insert_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Travel & Tour Premium</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        /* ============================================================
           1.  CSS VARIABLES — LIGHT is DEFAULT, DARK is toggle
           ============================================================ */
        :root {
            /* LIGHT (default) — with BOLD & LARGE fonts */
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
            --feature-icon-color: #0ea5e9;
        }

        /* DARK THEME (optional toggle) */
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
            --feature-icon-color: #38bdf8;
        }

        /* ============================================================
           2.  RESET & BASE — with LARGE & BOLD fonts in light mode
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
           3.  BACKGROUND VIDEO (fixed)
           ============================================================ */
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

        /* ============================================================
           4.  AMBIENT BLOBS
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
           5.  FLOATING PARTICLES
           ============================================================ */
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

        /* ============================================================
           6.  LOADER
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
           7.  NAVBAR (Theme toggle integrated)
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

        /* ============================================================
           8.  HERO WRAPPER
           ============================================================ */
        .hero-wrapper {
            min-height: calc(100vh - 80px);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
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
            font-size: clamp(3.5rem, 9vw, 5.5rem);
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
           9.  CONTACT FORM CARD (Glassmorphism + 3D Tilt)
           ============================================================ */
        .container {
            background: var(--bg-card);
            backdrop-filter: blur(32px) saturate(1.5);
            padding: 50px 45px 45px;
            border-radius: 40px;
            width: 100%;
            max-width: 650px;
            text-align: center;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card), inset 0 1px 0 rgba(255,255,255,0.04);
            animation: fadeInUp 1s cubic-bezier(0.23, 1, 0.32, 1) forwards;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            will-change: transform;
        }
        .container::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 30% 20%, rgba(14,165,233,0.05), transparent 70%);
            pointer-events: none;
        }
        .container::after {
            content: '';
            position: absolute; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient(from 0deg at 50% 50%, transparent, rgba(14,165,233,0.03), transparent 60%);
            animation: rotateGlow 30s linear infinite;
            pointer-events: none;
        }
        @keyframes rotateGlow { to { transform: rotate(360deg); } }
        .container:hover {
            transform: translateY(-8px) rotateX(2deg) rotateY(2deg);
            border-color: rgba(14, 165, 233, 0.20);
            box-shadow: 0 40px 90px rgba(0,0,0,0.4), 0 0 40px rgba(14, 165, 233, 0.04);
        }

        .container .form-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        .container .form-header .icon-big {
            font-size: 60px;
            color: var(--text-accent);
            margin-bottom: 12px;
            display: block;
            filter: drop-shadow(0 0 25px rgba(14,165,233,0.15));
            transition: color var(--transition);
        }
        .container .form-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
            transition: color var(--transition);
        }
        .container .form-header p {
            color: var(--text-muted);
            font-size: 18px;
            font-weight: 500;
            transition: color var(--transition);
        }

        /* --- FORM ELEMENTS --- */
        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: relative;
            z-index: 1;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            transition: all 0.3s ease;
            pointer-events: none;
            opacity: 0.5;
        }

        .input-group textarea + i {
            top: 20px;
            transform: none;
        }

        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 16px 16px 16px 46px;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 18px;
            outline: none;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            resize: vertical;
            font-family: 'Poppins', sans-serif;
        }

        .input-group textarea {
            padding-left: 16px;
            min-height: 130px;
        }

        .input-group input:focus,
        .input-group textarea:focus {
            border-color: var(--border-focus);
            background: rgba(255,255,255,0.03);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.08);
            transform: translateY(-2px);
        }

        .input-group input:focus + i,
        .input-group textarea:focus + i {
            color: var(--text-accent);
            opacity: 1;
        }

        .input-group input::placeholder,
        .input-group textarea::placeholder {
            color: var(--text-muted);
            opacity: 0.5;
        }

        /* --- BUTTONS --- */
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 14px;
            margin-top: 6px;
        }

        .btn {
            padding: 18px 22px;
            border-radius: 18px;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-btn);
            position: relative;
            overflow: hidden;
        }
        .btn .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: rippleAnim 0.8s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim {
            to { transform: scale(4); opacity: 0; }
        }
        .btn::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.12), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .btn:hover::before { opacity: 1; }
        .btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .btn:active { transform: scale(0.97) translateY(0); }

        .btn-send {
            background: linear-gradient(135deg, #0ea5e9, #0284c7, #0369a1);
            background-size: 200% 200%;
            animation: gradientShift 4s ease-in-out infinite;
        }
        .btn-send:hover { box-shadow: 0 12px 35px rgba(14, 165, 233, 0.35); }

        .btn-clear {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            color: var(--text-secondary);
        }
        .btn-clear:hover {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ============================================================
           10. SUCCESS OVERLAY (UPGRADED)
           ============================================================ */
        .success-overlay {
            position: fixed; inset: 0;
            background: var(--bg-modal);
            backdrop-filter: blur(32px) saturate(1.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeInOverlay 0.5s ease forwards;
        }

        .success-card {
            background: var(--bg-card);
            padding: 50px 45px 45px;
            border-radius: 40px;
            border: 1px solid var(--text-success);
            text-align: center;
            max-width: 420px;
            width: 100%;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-card);
            transform: scale(0.9) translateY(20px);
            animation: successPop 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards;
        }

        .success-ring-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.08);
            border: 2px solid var(--text-success);
            color: var(--text-success);
            font-size: 35px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 22px auto;
            box-shadow: 0 0 40px rgba(16, 185, 129, 0.15);
            animation: bounceScale 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.4s both;
            transition: border-color var(--transition), color var(--transition);
        }

        .success-card h3 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
            transition: color var(--transition);
        }
        .success-card p {
            color: var(--text-muted);
            font-size: 16px;
            font-weight: 500;
            line-height: 1.6;
            transition: color var(--transition);
        }

        @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
        @keyframes successPop {
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        @keyframes bounceScale {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            70% { transform: scale(0.95); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ============================================================
           11. FOOTER (with social icons & status)
           ============================================================ */
        .footer {
            width: 100%;
            max-width: 650px;
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
           12. SCROLL TO TOP
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
           13. CUSTOM CURSOR
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
           14. ANIMATIONS
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
           15. SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.04); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--text-accent), var(--text-success));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-accent); }

        /* ============================================================
           16. RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .navbar {
                padding: 14px 20px;
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
            .header h1 { font-size: 2.8rem; }
            .container {
                padding: 32px 24px 28px;
                margin: 0 15px;
            }
            .container .form-header h2 { font-size: 26px; }
            .container .form-header p { font-size: 16px; }
            .btn-group {
                grid-template-columns: 1fr;
            }
            .btn { padding: 16px; font-size: 15px; }
            .success-card { padding: 32px 24px; }
            .success-card h3 { font-size: 22px; }
            .blob-1 { width: 300px; height: 300px; }
            .blob-2 { width: 250px; height: 250px; }
            .blob-3 { width: 200px; height: 200px; }
            body { font-size: 1rem; }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 2.2rem; }
            .container { padding: 24px 16px 20px; }
            .container .form-header h2 { font-size: 22px; }
            .input-group input,
            .input-group textarea { padding: 14px 14px 14px 40px; font-size: 15px; }
            .btn { font-size: 14px; padding: 14px; }
            body { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    BACKGROUND VIDEO
    ============================================================ -->
    <div class="video-background">
        <div class="fallback"></div>
        <video id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=1920&q=80">
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
        <div class="loader-text">Loading Experience</div>
    </div>

    <!-- CUSTOM CURSOR -->
    <div class="cursor-dot" id="cursorDot"></div>
    <div class="cursor-ring" id="cursorRing"></div>

    <!-- ============================================================
    SUCCESS OVERLAY (if submission success)
    ============================================================ -->
    <?php if ($submission_success): ?>
        <div class="success-overlay">
            <div class="success-card">
                <div class="success-ring-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3>Message Sent! 🎉</h3>
                <p>Thank you for reaching out. Our team will get back to you shortly.</p>
            </div>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = 'contact.php';
            }, 3000);
        </script>
    <?php endif; ?>

    <!-- ============================================================
    NAVBAR (Theme toggle integrated)
    ============================================================ -->
    <nav class="navbar">
        <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
        <div class="nav-group">
            <div class="nav-links">
                <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
                <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
                <a href="about.php"><i class="fa-solid fa-info-circle"></i> About</a>
                <a href="contact.php" class="active"><i class="fa-solid fa-envelope"></i> Contact</a>
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
    HERO + CONTACT FORM
    ============================================================ -->
    <div class="hero-wrapper">

        <div class="header">
            <span class="badge"><i class="fa-solid fa-sparkles"></i> Get In Touch</span>
            <h1 id="typing-heading">Contact Our Team</h1>
            <p>Have questions or need a custom tour? We'd love to hear from you.</p>
        </div>

        <div class="container" id="tilt-card">
            <div class="form-header">
                <span class="icon-big"><i class="fa-solid fa-paper-plane"></i></span>
                <h2>Send Us a Message</h2>
                <p>We'll respond within 24 hours</p>
            </div>

            <form action="contact.php" method="POST">
                
                <div class="input-group">
                    <input type="text" name="name" placeholder="Your Full Name" required>
                    <i class="fa-solid fa-user"></i>
                </div>
                
                <div class="input-group">
                    <input type="email" name="email" placeholder="Your Email Address" required>
                    <i class="fa-solid fa-envelope"></i>
                </div>
                
                <div class="input-group">
                    <input type="text" name="subject" placeholder="Inquiry Subject / Topic" required>
                    <i class="fa-solid fa-tag"></i>
                </div>
                
                <div class="input-group">
                    <textarea name="message" rows="5" placeholder="How can we help you?" required></textarea>
                    <i class="fa-solid fa-comment"></i>
                </div>
                
                <div class="btn-group">
                    <button type="reset" class="btn btn-clear">
                        <i class="fa-solid fa-broom"></i> Clear
                    </button>
                    <button type="submit" name="submit" class="btn btn-send">
                        <i class="fa-solid fa-paper-plane"></i> Send Message
                    </button>
                </div>
            </form>
        </div>

        <!-- FOOTER -->
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
            <div style="margin-top: 8px;">© 2026 Travel &amp; Tour Management System — Connected Globally</div>
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
        //  LOADER
        // ============================================================
        window.addEventListener("load", () => {
            const loader = document.getElementById("loader");
            setTimeout(() => loader.classList.add("hidden"), 600);
            createParticles();
        });

        // ============================================================
        //  VIDEO AUTOPLAY
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
        //  THEME TOGGLE — LIGHT is DEFAULT
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
        //  BUTTON RIPPLE EFFECT
        // ============================================================
        document.querySelectorAll('.btn').forEach(btn => {
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

        // ============================================================
        //  3D TILT CARD
        // ============================================================
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
                this.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-6px)`;
            });
            tiltCard.addEventListener('mouseleave', function() {
                this.style.transform = 'perspective(800px) rotateX(0) rotateY(0) translateY(0)';
            });
        }

        // ============================================================
        //  CUSTOM CURSOR
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
        //  SCROLL TO TOP
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
        //  TYPING EFFECT
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
        //  FLOATING PARTICLES
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
    </script>

</body>
</html>