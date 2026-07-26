<?php
session_start();
include("../includes/db.php");

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

// Get all vehicles
$result = $conn->query("SELECT id, vehicle_name FROM vehicles WHERE status != 'Inactive'");

// Bulk update handling
if (isset($_POST['bulk_update'])) {
    $vehicles = $conn->query("SELECT id FROM vehicles WHERE status != 'Inactive'");
    while ($v = $vehicles->fetch_assoc()) {
        $lat = 6.9271 + (rand(-100, 100) / 10000);
        $lng = 79.8612 + (rand(-100, 100) / 10000);
        $conn->query("UPDATE vehicles SET last_lat = $lat, last_lng = $lng, last_update = NOW() WHERE id = {$v['id']}");
    }
    echo "<script>alert('All vehicles updated!'); window.location.href='simulate_gps.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Simulate GPS | Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ============================================================
           CSS VARIABLES (Light / Dark)
           ============================================================ */
        :root {
            --bg-body: #f1f5f9;
            --bg-sidebar: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            --sidebar-text: #94a3b8;
            --sidebar-hover: rgba(255,255,255,0.06);
            --sidebar-active: rgba(14,165,233,0.15);
            --sidebar-active-text: #0ea5e9;
            --card-bg: #ffffff;
            --card-border: rgba(0,0,0,0.04);
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --table-header-bg: #f8fafc;
            --table-border: #e2e8f0;
            --table-row-hover: #f8fafc;
            --code-bg: #f1f5f9;
            --topbar-border: #e2e8f0;
            --footer-border: #e2e8f0;
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.06);
            --transition: 0.3s ease;
        }

        body.dark {
            --bg-body: #0f172a;
            --bg-sidebar: linear-gradient(180deg, #020617 0%, #0f172a 100%);
            --sidebar-text: #94a3b8;
            --sidebar-hover: rgba(255,255,255,0.08);
            --sidebar-active: rgba(14,165,233,0.25);
            --sidebar-active-text: #38bdf8;
            --card-bg: #1e293b;
            --card-border: rgba(255,255,255,0.05);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --table-header-bg: #1e293b;
            --table-border: #334155;
            --table-row-hover: #1e293b;
            --code-bg: #334155;
            --topbar-border: #334155;
            --footer-border: #334155;
            --shadow: 0 2px 8px rgba(0,0,0,0.3);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.5);
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
            min-height: 100vh;
            display: flex;
            transition: background var(--transition), color var(--transition);
            color: var(--text-primary);
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--bg-sidebar);
            color: var(--sidebar-text);
            padding: 24px 20px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: all var(--transition);
            z-index: 1000;
        }

        .sidebar .brand {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 32px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 24px;
        }

        .sidebar .brand i {
            color: #0ea5e9;
            font-size: 24px;
        }

        .sidebar .brand span {
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar .nav-section {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #475569;
            margin: 20px 0 10px;
            font-weight: 600;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .sidebar a:hover {
            background: var(--sidebar-hover);
            color: #ffffff;
        }

        .sidebar a.active {
            background: var(--sidebar-active);
            color: var(--sidebar-active-text);
        }

        .sidebar a i {
            width: 20px;
            font-size: 16px;
            text-align: center;
        }

        .sidebar a .badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 24px 32px 40px;
            min-height: 100vh;
            transition: background var(--transition);
        }

        /* Top Bar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--topbar-border);
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
            transition: border var(--transition);
        }

        .topbar .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .topbar .page-title p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 2px;
        }

        .topbar .right-group {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        /* Live Clock */
        .clock {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-secondary);
            font-variant-numeric: tabular-nums;
            background: var(--card-bg);
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
            letter-spacing: 0.5px;
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s;
            box-shadow: var(--shadow);
        }

        .theme-toggle:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-hover);
        }

        .theme-toggle i {
            transition: transform 0.3s;
        }

        .theme-toggle:hover i {
            transform: rotate(30deg);
        }

        /* Logout Link (text style) */
        .logout-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .logout-link:hover {
            background: var(--table-row-hover);
            color: #ef4444;
        }

        .logout-link i {
            font-size: 15px;
        }

        /* Admin info (optional – keep it) */
        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-info .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .admin-info .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .admin-info .role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* ============================================================
           STATS CARDS
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 20px 24px;
            border-radius: 14px;
            box-shadow: var(--shadow);
            border: 1px solid var(--card-border);
            transition: all 0.2s;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .stat-card .label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 4px;
        }

        .stat-card .icon {
            font-size: 22px;
            color: #0ea5e9;
            margin-bottom: 6px;
        }

        /* ============================================================
           TABLE CARD
           ============================================================ */
        .table-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--card-border);
            overflow: hidden;
            margin-top: 24px;
            transition: background var(--transition), border var(--transition);
        }

        .table-card .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--table-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-card .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table-card .card-header .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-card .table-wrap {
            overflow-x: auto;
            padding: 0 24px 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table thead {
            background: var(--table-header-bg);
        }

        table thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--table-border);
        }

        table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--table-border);
            color: var(--text-primary);
        }

        table tbody tr:hover {
            background: var(--table-row-hover);
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        code {
            background: var(--code-bg);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: var(--text-primary);
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: #0ea5e9;
            color: white;
        }

        .btn-primary:hover {
            background: #0284c7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--btn-outline-border);
            color: var(--btn-outline-text);
        }

        .btn-outline:hover {
            background: var(--btn-outline-hover-bg);
            border-color: #94a3b8;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
                padding: 16px 14px;
            }
            .main-content {
                margin-left: 220px;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding: 12px 8px;
            }
            .sidebar .brand span,
            .sidebar .nav-section,
            .sidebar a span {
                display: none;
            }
            .sidebar .brand {
                justify-content: center;
                padding-bottom: 16px;
            }
            .sidebar a {
                justify-content: center;
                padding: 10px;
            }
            .sidebar a i {
                font-size: 18px;
                margin: 0;
            }
            .sidebar a .badge {
                display: none;
            }
            .main-content {
                margin-left: 60px;
                padding: 16px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .topbar .right-group {
                width: 100%;
                justify-content: flex-start;
            }
            .admin-info .name,
            .admin-info .role {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .table-card .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-body);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
    </style>
</head>
<body>

    <!-- ============================================================
    SIDEBAR (Admin Dashboard)
    ============================================================ -->
    <nav class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-earth-asia"></i>
            <span>TRAVEL &amp; TOUR</span>
        </div>

        <div class="nav-section">Main</div>
        <a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
        <a href="packages.php"><i class="fa-solid fa-box"></i><span>Packages</span></a>
        <a href="bookings.php"><i class="fa-solid fa-calendar-check"></i><span>Bookings</span></a>
        <a href="vehicles.php"><i class="fa-solid fa-truck"></i><span>Vehicles</span></a>

        <div class="nav-section">Management</div>
        <a href="users.php"><i class="fa-solid fa-users"></i><span>Users</span></a>
        <a href="feedback.php"><i class="fa-solid fa-comment"></i><span>Feedback</span></a>
        <a href="reports.php"><i class="fa-solid fa-chart-bar"></i><span>Reports</span></a>

        <div class="nav-section">System</div>
        <a href="simulate_gps.php" class="active"><i class="fa-solid fa-satellite-dish"></i><span>GPS Simulator</span></a>
        <a href="vehicle_tracking.php"><i class="fa-solid fa-map-location-dot"></i><span>Live Map</span></a>
        <a href="../logout.php"><i class="fa-solid fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="main-content">

        <!-- Top Bar -->
        <div class="topbar">
            <div class="page-title">
                <h1><i class="fa-solid fa-satellite-dish" style="color:#0ea5e9; margin-right:10px;"></i> GPS Simulator</h1>
                <p>Simulate vehicle GPS locations for testing</p>
            </div>
            <div class="right-group">
                <!-- Admin Info (optional) -->
                <div class="admin-info">
                    <div>
                        <div class="name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?></div>
                        <div class="role">Administrator</div>
                    </div>
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)); ?></div>
                </div>

                <!-- Live Clock -->
                <span class="clock" id="liveClock">00:00:00</span>

                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>

                <!-- Logout Link (text style) -->
                <a href="../logout.php" class="logout-link">
                    <i class="fa-solid fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-truck"></i></div>
                <div class="label">Total Vehicles</div>
                <div class="value"><?php echo $result->num_rows; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-check-circle" style="color:#10b981;"></i></div>
                <div class="label">Active</div>
                <div class="value"><?php echo $result->num_rows; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-sync" style="color:#f59e0b;"></i></div>
                <div class="label">Last Update</div>
                <div class="value" style="font-size:14px;"><?php echo date('H:i:s'); ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-map" style="color:#0ea5e9;"></i></div>
                <div class="label">Map Status</div>
                <div class="value" style="font-size:14px;">Ready</div>
            </div>
        </div>

        <!-- Bulk Update Button -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
            <form method="POST" style="display:inline;">
                <button type="submit" name="bulk_update" value="1" class="btn btn-warning">
                    <i class="fa-solid fa-sync"></i> Update All Locations (Random)
                </button>
            </form>
            <a href="vehicle_tracking.php" class="btn btn-success">
                <i class="fa-solid fa-map-location-dot"></i> Go to Live Map
            </a>
        </div>

        <!-- Vehicles Table -->
        <div class="table-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-list" style="color:#0ea5e9; margin-right:8px;"></i> Vehicle Locations</h3>
                <div class="actions">
                    <span style="font-size:13px; color:var(--text-secondary);">
                        <i class="fa-regular fa-clock"></i> Click "Update" to simulate GPS
                    </span>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle Name</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset result pointer
                        $result = $conn->query("SELECT id, vehicle_name FROM vehicles WHERE status != 'Inactive'");
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $lat = 6.9271 + (rand(-100, 100) / 10000);
                                $lng = 79.8612 + (rand(-100, 100) / 10000);
                        ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['vehicle_name']); ?></td>
                            <td><code><?php echo number_format($lat, 6); ?></code></td>
                            <td><code><?php echo number_format($lng, 6); ?></code></td>
                            <td style="text-align:center;">
                                <form method="POST" action="update_location.php" style="display:inline;">
                                    <input type="hidden" name="api_key" value="YOUR_SECRET_API_KEY">
                                    <input type="hidden" name="vehicle_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="lat" value="<?php echo $lat; ?>">
                                    <input type="hidden" name="lng" value="<?php echo $lng; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-location-dot"></i> Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">
                                <i class="fa-solid fa-car" style="font-size:40px; display:block; margin-bottom:10px; opacity:0.3;"></i>
                                No active vehicles found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer Info -->
        <div style="margin-top: 24px; text-align: center; font-size: 13px; color: var(--text-muted); padding: 16px 0; border-top: 1px solid var(--footer-border);">
            <i class="fa-regular fa-copyright"></i> <?php echo date('Y'); ?> Travel &amp; Tour Management System — GPS Simulator
        </div>

    </div>

    <!-- ============================================================
    JAVASCRIPT: Theme Toggle + Clock
    ============================================================ -->
    <script>
        (function() {
            // --- Theme Toggle ---
            const toggle = document.getElementById('themeToggle');
            const icon = toggle.querySelector('i');

            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }

            toggle.addEventListener('click', function() {
                document.body.classList.toggle('dark');
                const isDark = document.body.classList.contains('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                if (isDark) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
            });

            // --- Live Clock ---
            function updateClock() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                document.getElementById('liveClock').textContent = hours + ':' + minutes + ':' + seconds;
            }
            updateClock();
            setInterval(updateClock, 1000);
        })();
    </script>

</body>
</html>