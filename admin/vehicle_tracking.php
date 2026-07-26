<?php
session_start();
include("../includes/db.php");

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Admin name
$admin_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 
              (isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin');
$display_admin = "Admin: " . $admin_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Vehicle Tracking | Admin</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
            --map-height: calc(100vh - 420px);
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
           NAVBAR (ඉල්ලූ පරිදි ලින්ක් 7 පමණක්)
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
            flex-wrap: wrap;
        }
        .nav-links {
            display: flex; align-items: center; gap: 4px;
            flex-wrap: wrap;
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

        /* Vehicle Manager Button */
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

        /* Logout Button */
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
            transform: translateY(-2px);
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
        }
        .header-actions .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: var(--gradient-main);
            color: #fff;
            box-shadow: 0 4px 16px rgba(14,165,233,0.3);
        }
        .btn-primary:hover { box-shadow: 0 8px 28px rgba(14,165,233,0.4); }
        .btn-success {
            background: var(--text-success);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16,185,129,0.3);
        }
        .btn-success:hover { box-shadow: 0 8px 28px rgba(16,185,129,0.4); }
        .btn-warning {
            background: var(--text-warning);
            color: #fff;
            box-shadow: 0 4px 16px rgba(245,158,11,0.3);
        }
        .btn-warning:hover { box-shadow: 0 8px 28px rgba(245,158,11,0.4); }

        /* Welcome Section */
        .welcome-section {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            backdrop-filter: blur(12px);
            transition: all var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-section .welcome-text h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            transition: color var(--transition);
        }
        .welcome-section .welcome-text h2 i {
            color: var(--text-warning);
            margin-right: 10px;
        }
        .welcome-section .welcome-text p {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-top: 2px;
            transition: color var(--transition);
        }
        .welcome-section .welcome-text p i {
            color: var(--text-success);
            margin-right: 6px;
        }
        .welcome-section .welcome-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .welcome-section .welcome-stats .stat-item {
            text-align: center;
        }
        .welcome-section .welcome-stats .stat-item .num {
            font-size: 20px;
            font-weight: 900;
            color: var(--text-primary);
            transition: color var(--transition);
        }
        .welcome-section .welcome-stats .stat-item .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            transition: color var(--transition);
        }

        /* ============================================
           MAP CONTAINER
           ============================================ */
        .map-wrapper {
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid var(--border-card);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            position: relative;
            backdrop-filter: blur(12px);
            transition: all var(--transition);
            min-height: 500px;
        }
        .map-wrapper #map {
            width: 100%;
            height: var(--map-height);
            min-height: 450px;
            background: var(--bg-secondary);
        }
        .map-wrapper .map-overlay-label {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 500;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.06);
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .map-wrapper .map-overlay-label i {
            color: #60a5fa;
        }

        /* Leaflet overrides for dark/light */
        .leaflet-control-zoom a {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-card) !important;
        }
        .leaflet-control-zoom a:hover {
            background: var(--bg-input) !important;
        }
        .leaflet-popup-content-wrapper {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border-radius: 12px !important;
            border: 1px solid var(--border-card) !important;
            box-shadow: var(--shadow-card) !important;
        }
        .leaflet-popup-tip {
            background: var(--bg-card) !important;
        }

        /* ============================================
           INFO PANEL (Glassmorphism)
           ============================================ */
        .info-panel {
            position: absolute;
            top: 80px;
            right: 20px;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            padding: 16px 20px;
            border-radius: 16px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            z-index: 500;
            width: 250px;
            max-height: 400px;
            overflow-y: auto;
            transition: all var(--transition);
        }
        .info-panel::-webkit-scrollbar {
            width: 4px;
        }
        .info-panel::-webkit-scrollbar-thumb {
            background: var(--text-accent);
            border-radius: 10px;
        }
        .info-panel h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-panel h4 i {
            color: var(--text-accent);
        }
        .info-panel .vehicle-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 12px;
            border-bottom: 1px solid var(--border-card);
            transition: all 0.3s ease;
        }
        .info-panel .vehicle-item:last-child {
            border-bottom: none;
        }
        .info-panel .vehicle-item .vehicle-name {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .info-panel .vehicle-item .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .status-dot.available { background: #10b981; }
        .status-dot.ontrip { background: #f59e0b; }
        .status-dot.maintenance { background: #ef4444; }
        .status-dot.inactive { background: #94a3b8; }
        .info-panel .vehicle-item .status-text {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(0,0,0,0.04);
        }
        .status-text.available { color: #10b981; background: rgba(16,185,129,0.12); }
        .status-text.ontrip { color: #f59e0b; background: rgba(245,158,11,0.12); }
        .status-text.maintenance { color: #ef4444; background: rgba(239,68,68,0.12); }
        .status-text.inactive { color: #94a3b8; background: rgba(148,163,184,0.12); }

        /* Legend */
        .legend {
            position: absolute;
            bottom: 30px;
            left: 30px;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
            font-size: 12px;
            z-index: 500;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            transition: all var(--transition);
        }
        .legend span {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .legend .dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ============================================
           FOOTER
           ============================================ */
        .footer-bar {
            margin-top: 24px;
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

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .info-panel {
                width: 200px;
                top: 70px;
                right: 12px;
                padding: 12px 14px;
            }
            .info-panel .vehicle-item {
                font-size: 11px;
            }
        }
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .app-header {
                padding: 14px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            .app-header .brand h1 { font-size: 20px; }
            .navbar { padding: 12px 20px; flex-direction: column; align-items: stretch; }
            .nav-group { flex-wrap: wrap; justify-content: center; gap: 12px; }
            .theme-toggle-inline { border-left: none; padding-left: 0; justify-content: center; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .welcome-section .welcome-stats { width: 100%; justify-content: space-around; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .header-actions .btn { padding: 8px 16px; font-size: 13px; }
            .map-wrapper #map {
                height: 350px;
                min-height: 300px;
            }
            .info-panel {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                max-height: 200px;
                margin-top: 12px;
                border-radius: 14px;
            }
            .legend {
                bottom: 80px;
                left: 16px;
                padding: 10px 14px;
                font-size: 10px;
                gap: 8px;
                flex-wrap: wrap;
            }
            .map-wrapper .map-overlay-label {
                top: 12px;
                left: 12px;
                font-size: 10px;
                padding: 6px 12px;
            }
            :root {
                --map-height: 350px;
            }
            .navbar-clock { font-size: 12px; padding: 4px 10px; }
        }
        @media (max-width: 480px) {
            .map-wrapper #map {
                height: 280px;
                min-height: 250px;
            }
            :root {
                --map-height: 280px;
            }
            .navbar-clock { font-size: 11px; padding: 4px 8px; }
            .legend {
                bottom: 70px;
                left: 12px;
                padding: 8px 12px;
                font-size: 9px;
                gap: 6px;
            }
            .legend .dot {
                width: 8px;
                height: 8px;
            }
            .info-panel {
                max-height: 150px;
                padding: 10px 12px;
            }
            .info-panel h4 { font-size: 12px; }
            .info-panel .vehicle-item { font-size: 10px; padding: 4px 0; }
        }

        /* Print overrides */
        @media print {
            body { background: #fff !important; color: #111 !important; }
            .navbar, .video-background, .video-overlay, .blob, .particles, .cursor-dot, .cursor-ring, .scroll-top { display: none !important; }
            .main { padding: 10px !important; }
            .app-header { margin-bottom: 12px !important; padding: 12px 16px !important; }
            .app-header .brand h1 { color: #111 !important; -webkit-text-fill-color: #111 !important; }
            .map-wrapper { border: 1px solid #d1d5db !important; box-shadow: none !important; }
            .info-panel, .legend { border: 1px solid #d1d5db !important; box-shadow: none !important; background: #f8fafc !important; }
            .map-wrapper #map { height: 400px !important; min-height: 400px !important; }
            .welcome-section, .header-actions, .footer-bar { display: none !important; }
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
    <div class="loader-text">Loading Map</div>
</div>

<!-- Custom Cursor -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- ============================================================ -->
<!-- NAVBAR (ඉල්ලූ පරිදි ලින්ක් 7 පමණක්) -->
<!-- ============================================================ -->
<nav class="navbar">
    <div class="logo"><i class="fa-solid fa-earth-asia"></i> TRAVEL &amp; TOUR</div>
    <div class="nav-group">
        <div class="nav-links">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="packages.php"><i class="fa-solid fa-suitcase"></i> Packages</a>
            <a href="booking.php"><i class="fa-solid fa-ticket"></i> Bookings</a>
            <a href="vehicles.php" class="active"><i class="fa-solid fa-car"></i> Vehicles</a>
            <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a href="view_feedbacks.php"><i class="fa-solid fa-comment"></i> Feedback</a>
            <a href="advanced_reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
            <!-- Vehicle Manager Button -->
            <a href="../vehicle_management/dashboard.php" target="_blank" class="vehicle-manager-btn">
                <i class="fa-solid fa-user-tie"></i> Vehicle Manager
            </a>
        </div>
        <!-- Live Clock -->
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
        <!-- Logout -->
        <a href="../index.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ============================================================ -->
<!-- MAIN WRAPPER -->
<!-- ============================================================ -->
<main class="main">

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-map-location-dot"></i></div>
            <div>
                <h1 id="typing-heading">Live Vehicle Tracking</h1>
                <span>Real-time GPS fleet monitoring &bull; Auto-refresh every 2s</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="vehicles.php" class="btn btn-primary"><i class="fa-solid fa-car"></i> Manage Vehicles</a>
            <a href="simulate_gps.php" class="btn btn-warning"><i class="fa-solid fa-satellite-dish"></i> GPS Simulator</a>
        </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fa-regular fa-hand-peace"></i> Welcome Back, <?php echo htmlspecialchars($admin_name); ?></h2>
            <p><i class="fa-regular fa-circle-check"></i> Track all vehicles in real-time on the interactive map below.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="num" id="totalVehicles">0</div>
                <div class="label">Total Vehicles</div>
            </div>
            <div class="stat-item">
                <div class="num" style="color:var(--text-success);" id="onlineVehicles">0</div>
                <div class="label">Online</div>
            </div>
            <div class="stat-item">
                <div class="num" style="color:var(--text-warning);" id="ontripVehicles">0</div>
                <div class="label">On Trip</div>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div class="map-wrapper animate__animated animate__fadeInUp">
        <div class="map-overlay-label">
            <i class="fa-solid fa-satellite-dish fa-spin"></i> Live GPS Feed &bull; Auto-refreshing
        </div>
        <div id="map"></div>

        <!-- Info Panel (Vehicle List) -->
        <div class="info-panel" id="infoPanel">
            <h4><i class="fa-solid fa-car"></i> Vehicles <span id="vehicleCount" style="font-weight:400;font-size:12px;color:var(--text-muted);">(0)</span></h4>
            <div id="vehicleList">
                <p style="font-size:12px; color:var(--text-muted);">Loading vehicles...</p>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend">
            <span><span class="dot" style="background:#10b981;"></span>Available</span>
            <span><span class="dot" style="background:#f59e0b;"></span>On Trip</span>
            <span><span class="dot" style="background:#ef4444;"></span>Maintenance</span>
            <span><span class="dot" style="background:#94a3b8;"></span>Inactive</span>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer-bar">
        <span>© <?php echo date('Y'); ?> GPS Tracking System &bull; All rights reserved.</span>
        <span>Last updated: <span id="lastUpdate"><?php echo date('F j, Y \a\t g:i A'); ?></span></span>
    </div>

</main>

<!-- Scroll to Top -->
<div class="scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== LOADER =====
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        setTimeout(() => { loader.classList.add("hidden"); }, 600);
        createParticles();
        initMap();
    });

    // ===== VIDEO AUTOPLAY =====
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

    // ===== LIVE CLOCK =====
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

    // ===== THEME TOGGLE =====
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

    // ===== TYPING EFFECT =====
    const heading = document.getElementById('typing-heading');
    const originalText = heading.innerText;
    heading.innerText = '';
    let charIndex = 0;
    function typeWriter() {
        if (charIndex < originalText.length) {
            heading.innerText += originalText.charAt(charIndex);
            charIndex++;
            setTimeout(typeWriter, 60);
        }
    }
    setTimeout(typeWriter, 800);

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
    // MAP FUNCTIONALITY
    // ============================================================
    let map, markers = {};
    let vehicleData = [];

    function initMap() {
        // Initialize map centered on Sri Lanka
        map = L.map('map').setView([7.8731, 80.7718], 8);

        // Tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Start fetching vehicles
        fetchVehicles();
        setInterval(fetchVehicles, 2000);
    }

    // Custom icon for vehicle
    function getIcon(status) {
        let color = '#10b981';
        let icon = 'fa-car';
        if (status === 'On Trip') { color = '#f59e0b'; icon = 'fa-car'; }
        else if (status === 'Maintenance') { color = '#ef4444'; icon = 'fa-wrench'; }
        else if (status === 'Inactive') { color = '#94a3b8'; icon = 'fa-car'; }
        return L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color:${color}; width:28px; height:28px; border-radius:50%; border:2px solid white; box-shadow:0 2px 10px rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; font-size:13px; color:white; transition:all 0.3s ease;">
                    <i class="fa-solid ${icon}"></i>
                  </div>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    }

    // Fetch vehicles from API
    function fetchVehicles() {
        fetch('get_vehicle_locations.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                vehicleData = data;
                updateInfoPanel(data);
                updateStats(data);
                updateMarkers(data);
                document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
            })
            .catch(err => {
                console.error('Error fetching vehicles:', err);
                document.getElementById('vehicleList').innerHTML = 
                    '<p style="font-size:12px; color:var(--text-danger);"><i class="fa-solid fa-triangle-exclamation"></i> Error loading vehicles</p>';
            });
    }

    // Update info panel
    function updateInfoPanel(data) {
        const list = document.getElementById('vehicleList');
        const count = document.getElementById('vehicleCount');

        if (!data || data.length === 0) {
            list.innerHTML = '<p style="font-size:12px; color:var(--text-muted);">No vehicles available</p>';
            count.textContent = '(0)';
            return;
        }

        count.textContent = '(' + data.length + ')';
        let html = '';
        data.forEach(v => {
            const statusClass = v.status.toLowerCase().replace(/\s/g, '');
            const statusDisplay = v.status || 'Unknown';
            let dotColor = '#94a3b8';
            if (statusClass === 'available') dotColor = '#10b981';
            else if (statusClass === 'ontrip') dotColor = '#f59e0b';
            else if (statusClass === 'maintenance') dotColor = '#ef4444';
            
            html += `<div class="vehicle-item">
                        <div class="vehicle-name">
                            <span class="status-dot ${statusClass}"></span>
                            ${v.vehicle_name || 'Vehicle #' + v.id}
                        </div>
                        <span class="status-text ${statusClass}">${statusDisplay}</span>
                    </div>`;
        });
        list.innerHTML = html;
    }

    // Update stats in welcome section
    function updateStats(data) {
        const total = data.length;
        const online = data.filter(v => v.status === 'Available' || v.status === 'On Trip').length;
        const ontrip = data.filter(v => v.status === 'On Trip').length;

        document.getElementById('totalVehicles').textContent = total;
        document.getElementById('onlineVehicles').textContent = online;
        document.getElementById('ontripVehicles').textContent = ontrip;
    }

    // Update markers on map
    function updateMarkers(data) {
        const currentIds = new Set(data.map(v => v.id));

        // Remove markers for vehicles that no longer exist
        Object.keys(markers).forEach(key => {
            if (!currentIds.has(parseInt(key))) {
                map.removeLayer(markers[key]);
                delete markers[key];
            }
        });

        // Add or update markers
        data.forEach(v => {
            if (v.last_lat && v.last_lng) {
                const lat = parseFloat(v.last_lat);
                const lng = parseFloat(v.last_lng);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const key = v.id;
                    const popupContent = `
                        <div style="font-family:'Inter',sans-serif;padding:4px 0;">
                            <strong style="font-size:14px;">${v.vehicle_name || 'Vehicle #' + v.id}</strong><br>
                            <span style="font-size:12px;color:var(--text-muted);">
                                <i class="fa-solid fa-circle" style="color:${v.status === 'On Trip' ? '#f59e0b' : '#10b981'};font-size:10px;"></i>
                                Status: <strong>${v.status || 'Unknown'}</strong>
                            </span><br>
                            <span style="font-size:11px;color:var(--text-muted);">
                                <i class="fa-solid fa-user"></i> Driver: ${v.driver_name || 'N/A'}
                            </span><br>
                            <span style="font-size:10px;color:var(--text-muted);">
                                <i class="fa-regular fa-clock"></i> ${v.last_update || 'N/A'}
                            </span>
                            <br>
                            <span style="font-size:10px;color:var(--text-accent);">
                                <i class="fa-solid fa-location-dot"></i> ${lat.toFixed(5)}, ${lng.toFixed(5)}
                            </span>
                        </div>
                    `;

                    if (markers[key]) {
                        markers[key].setLatLng([lat, lng]);
                        markers[key].setIcon(getIcon(v.status));
                        markers[key].getPopup().setContent(popupContent);
                    } else {
                        const marker = L.marker([lat, lng], { icon: getIcon(v.status) })
                            .addTo(map)
                            .bindPopup(popupContent, { maxWidth: 250, className: 'vehicle-popup' });
                        markers[key] = marker;
                    }
                }
            }
        });
    }

    // ===== ESC KEY TO CLOSE POPUPS =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (map) map.closePopup();
        }
    });
</script>

</body>
</html>