<?php
session_start();
include("../includes/db.php");

// ============================================================
// CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
// IF NO ID, REDIRECT
// ============================================================
if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

// ============================================================
// FETCH BOOKING TO VERIFY OWNERSHIP
// ============================================================
$query = "SELECT id, package_id FROM bookings WHERE id = $booking_id AND user_id = $user_id";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: my_bookings.php");
    exit();
}

// ============================================================
// PERFORM DELETION
// ============================================================
$stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$deleted = ($stmt->affected_rows > 0);
$stmt->close();

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
    <title>Delete Booking | Travel Portal</title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet" />

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ============================================================
           THEME VARIABLES (SAME AS DASHBOARD)
        ============================================================ */
        :root {
            /* Light theme (default) */
            --bg-body: #f1f5f9;
            --bg-navbar: rgba(255, 255, 255, 0.85);
            --border-navbar: rgba(226, 232, 240, 0.6);
            --text-primary: #0f172a;
            --text-secondary: #1e293b;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --badge-bg: #f1f5f9;
            --badge-text: #334155;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.02);
            --shadow-hover: 0 16px 32px -8px rgba(37, 99, 235, 0.12);
            --icon-bg: #eff6ff;
            --icon-color: #2563eb;
            --toggle-bg: #e2e8f0;
            --toggle-dot: #fff;
            --scrollbar-track: #f1f5f9;
            --scrollbar-thumb: #2563eb;
            --box-shadow-nav: 0 2px 20px rgba(0, 0, 0, 0.03);
            --danger-color: #ef4444;
            --danger-light: rgba(239, 68, 68, 0.08);
            --danger-border: rgba(239, 68, 68, 0.15);
            --success-color: #10b981;
            --success-light: rgba(16, 185, 129, 0.08);
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-navbar: rgba(15, 23, 42, 0.90);
            --border-navbar: rgba(51, 65, 85, 0.4);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --card-bg: #1e293b;
            --card-border: #334155;
            --badge-bg: #334155;
            --badge-text: #cbd5e1;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 16px 32px -8px rgba(37, 99, 235, 0.3);
            --icon-bg: #1e293b;
            --icon-color: #60a5fa;
            --toggle-bg: #475569;
            --toggle-dot: #f1f5f9;
            --scrollbar-track: #1e293b;
            --scrollbar-thumb: #60a5fa;
            --box-shadow-nav: 0 2px 20px rgba(0, 0, 0, 0.3);
            --danger-color: #f87171;
            --danger-light: rgba(239, 68, 68, 0.12);
            --danger-border: rgba(239, 68, 68, 0.2);
            --success-color: #34d399;
            --success-light: rgba(16, 185, 129, 0.12);
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            opacity: 0;
            animation: fadeInBody 0.8s ease forwards;
            transition: background 0.3s ease, color 0.3s ease;
        }

        @keyframes fadeInBody {
            to { opacity: 1; }
        }

        /* ============================================================
           HEADER / NAVBAR (SAME AS DASHBOARD)
        ============================================================ */
        .navbar {
            background: var(--bg-navbar);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid var(--border-navbar);
            padding: 16px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--box-shadow-nav);
            animation: slideDown 0.6s ease forwards;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .navbar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            transition: color 0.3s ease;
        }

        .navbar .logo i {
            color: var(--icon-color);
            font-size: 28px;
            filter: drop-shadow(0 2px 8px rgba(37, 99, 235, 0.25));
            transition: color 0.3s ease;
        }

        .navbar .nav-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar .nav-actions .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card-bg);
            padding: 6px 16px 6px 12px;
            border-radius: 40px;
            border: 1px solid var(--card-border);
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }

        .navbar .nav-actions .user-badge i {
            font-size: 18px;
            color: var(--icon-color);
            transition: color 0.3s ease;
        }

        /* ----- THEME TOGGLE BUTTON ----- */
        .theme-toggle {
            background: var(--toggle-bg);
            border: none;
            border-radius: 40px;
            width: 52px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6px;
            cursor: pointer;
            position: relative;
            transition: background 0.3s ease;
            flex-shrink: 0;
        }

        .theme-toggle .toggle-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--toggle-dot);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55), background 0.3s ease;
            position: absolute;
            left: 4px;
            top: 4px;
        }

        [data-theme="dark"] .theme-toggle .toggle-dot {
            transform: translateX(24px);
        }

        .theme-toggle .toggle-icon {
            font-size: 12px;
            color: var(--text-muted);
            transition: color 0.3s ease;
            z-index: 1;
        }

        .theme-toggle .toggle-icon.sun { margin-right: auto; }
        .theme-toggle .toggle-icon.moon { margin-left: auto; }

        [data-theme="dark"] .theme-toggle .toggle-icon.sun { color: #fbbf24; }
        [data-theme="dark"] .theme-toggle .toggle-icon.moon { color: #94a3b8; }

        .navbar .nav-actions .logout-btn {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 8px 22px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.15);
        }

        .navbar .nav-actions .logout-btn:hover {
            background: #dc2626;
            transform: scale(1.04);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.25);
        }

        /* ============================================================
           MAIN CONTAINER
        ============================================================ */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 32px 60px;
            width: 100%;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================================================
           DELETE CARD
        ============================================================ */
        .delete-card {
            background: var(--card-bg);
            border-radius: 28px;
            border: 1px solid var(--card-border);
            padding: 48px 40px 40px;
            text-align: center;
            box-shadow: var(--shadow-light);
            width: 100%;
            max-width: 480px;
            transition: all 0.3s ease;
            animation: fadeUp 0.8s ease forwards;
            position: relative;
            overflow: hidden;
        }

        .delete-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--danger-color);
            opacity: 0.6;
        }

        .delete-card:hover {
            border-color: var(--danger-color);
            box-shadow: var(--shadow-hover);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ----- ICON ----- */
        .delete-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--danger-light);
            border: 2px solid var(--danger-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: var(--danger-color);
            transition: all 0.3s ease;
        }

        .delete-card:hover .delete-icon {
            transform: scale(1.06);
            box-shadow: 0 0 40px rgba(239, 68, 68, 0.1);
        }

        /* ----- HEADINGS ----- */
        .delete-card h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
            transition: color 0.3s ease;
        }

        .delete-card .sub {
            font-size: 16px;
            color: var(--text-muted);
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        .delete-card .booking-id {
            font-family: 'Inter', monospace;
            font-size: 18px;
            font-weight: 700;
            color: var(--danger-color);
            background: var(--danger-light);
            display: inline-block;
            padding: 4px 20px;
            border-radius: 40px;
            border: 1px solid var(--danger-border);
            margin: 8px 0 16px;
        }

        /* ----- STATUS ----- */
        .status-text {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-muted);
            margin: 16px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .status-text .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid var(--danger-light);
            border-top: 3px solid var(--danger-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .status-text.success {
            color: var(--success-color);
        }

        .status-text.success .spinner {
            border-top-color: var(--success-color);
        }

        /* ----- PROGRESS BAR ----- */
        .progress-track {
            width: 100%;
            height: 4px;
            background: var(--card-border);
            border-radius: 4px;
            margin-top: 20px;
            overflow: hidden;
        }

        .progress-track .bar {
            height: 100%;
            width: 0%;
            background: var(--danger-color);
            border-radius: 4px;
            animation: progressAnim 1.5s ease-in-out forwards;
        }

        @keyframes progressAnim {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        /* ----- REDIRECT INFO ----- */
        .redirect-info {
            margin-top: 18px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }

        .redirect-info i {
            margin-right: 4px;
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 768px) {
            .navbar {
                padding: 12px 20px;
            }
            .navbar .logo {
                font-size: 18px;
            }
            .navbar .nav-actions .user-badge {
                font-size: 12px;
                padding: 4px 12px 4px 8px;
            }
            .navbar .nav-actions .logout-btn {
                font-size: 12px;
                padding: 6px 16px;
            }
            .container {
                padding: 24px 16px 40px;
            }
            .delete-card {
                padding: 32px 24px 28px;
            }
            .delete-card h2 {
                font-size: 22px;
            }
            .delete-icon {
                width: 64px;
                height: 64px;
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 12px 16px;
            }
            .navbar .nav-actions {
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .delete-card {
                padding: 24px 16px 20px;
            }
            .delete-card h2 {
                font-size: 20px;
            }
            .delete-card .booking-id {
                font-size: 15px;
            }
        }

        /* ============================================================
           SCROLLBAR STYLING
        ============================================================ */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
            transition: background 0.3s ease;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 12px;
            transition: background 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--icon-color);
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR (SAME AS DASHBOARD) ===== -->
    <header class="navbar">
        <div class="logo">
            <i class="fa-solid fa-plane-departure"></i>
            Travel Portal
        </div>
        <div class="nav-actions">
            <div class="user-badge">
                <i class="fa-regular fa-circle-user"></i>
                <?php echo htmlspecialchars($user_name); ?>
            </div>

            <!-- THEME TOGGLE -->
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                <span class="toggle-icon sun"><i class="fa-regular fa-sun"></i></span>
                <span class="toggle-dot"></span>
                <span class="toggle-icon moon"><i class="fa-regular fa-moon"></i></span>
            </button>

            <a href="../logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </header>

    <!-- ===== MAIN ===== -->
    <main class="container">

        <div class="delete-card">
            <!-- Icon -->
            <div class="delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>

            <h2>Delete Booking</h2>
            <p class="sub">This action cannot be undone</p>

            <div class="booking-id">
                #<?php echo $booking_id; ?>
            </div>

            <!-- Status -->
            <div class="status-text" id="statusText">
                <span class="spinner"></span>
                Removing from database ...
            </div>

            <!-- Progress Bar -->
            <div class="progress-track">
                <div class="bar"></div>
            </div>

            <div class="redirect-info">
                <i class="fa-regular fa-clock"></i> redirecting in 1.5s
            </div>
        </div>

    </main>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script>
        // ===== THEME TOGGLE (SAME AS DASHBOARD) =====
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

        // ===== STATUS UPDATE =====
        document.addEventListener('DOMContentLoaded', function() {
            const statusText = document.getElementById('statusText');

            <?php if ($deleted): ?>
                setTimeout(function() {
                    statusText.className = 'status-text success';
                    statusText.innerHTML = '<i class="fa-solid fa-check-circle" style="font-size:20px;"></i> Deletion complete!';
                    setTimeout(function() {
                        window.location.href = 'my_bookings.php?msg=deleted';
                    }, 600);
                }, 800);
            <?php else: ?>
                statusText.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="color:var(--danger-color);font-size:20px;"></i> Deletion failed. Redirecting...';
                setTimeout(function() {
                    window.location.href = 'my_bookings.php?msg=error';
                }, 1500);
            <?php endif; ?>
        });
    </script>

</body>
</html>