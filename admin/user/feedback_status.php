<?php
date_default_timezone_set('Asia/Colombo');
session_start();
include("../includes/db.php");

// ============================================================
// CHECK USER LOGIN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;   // fallback if not set

// Use prepared statement
$stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // No user found — redirect to login or show error
    header("Location: ../login.php");
    exit();
}

$user_row = $result->fetch_assoc();
$user_email = $user_row['email'];
$user_name = $user_row['full_name'] ?? 'User';
$stmt->close();
// ============================================================
// DETECT DATE COLUMN
// ============================================================
$date_column = 'created_at';
$check_cols = ['created_at', 'date', 'submitted_at'];
foreach ($check_cols as $col) {
    $check = $conn->query("SHOW COLUMNS FROM contact_feedbacks LIKE '$col'");
    if ($check && $check->num_rows > 0) {
        $date_column = $col;
        break;
    }
}
$has_date_column = ($date_column !== null);

// ============================================================
// HANDLE REPLY SUBMISSION
// ============================================================
$reply_success = '';
$reply_error = '';

if (isset($_POST['reply'])) {
    $original_id = (int)$_POST['feedback_id'];
    $reply_msg = trim(mysqli_real_escape_string($conn, $_POST['reply_message']));
    $original_subject = mysqli_real_escape_string($conn, $_POST['original_subject']);

    if (!empty($reply_msg)) {
        $new_subject = "Re: " . $original_subject;
        if ($has_date_column) {
            $insert = $conn->prepare("INSERT INTO contact_feedbacks (email, subject, message, $date_column) VALUES (?, ?, ?, NOW())");
            $insert->bind_param("sss", $user_email, $new_subject, $reply_msg);
        } else {
            $insert = $conn->prepare("INSERT INTO contact_feedbacks (email, subject, message) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $user_email, $new_subject, $reply_msg);
        }
        if ($insert->execute()) {
            $reply_success = "Your reply has been sent successfully.";
            // Refresh page to show new reply
            header("Location: feedback_status.php?success=1");
            exit();
        } else {
            $reply_error = "Error sending reply: " . $insert->error;
        }
    } else {
        $reply_error = "Please enter a reply message.";
    }
}

// ============================================================
// GREETING
// ============================================================
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';

// ============================================================
// THEME (from cookie)
// ============================================================
$theme = isset($_COOKIE['dashboard-theme']) ? $_COOKIE['dashboard-theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Feedback Status | Travel Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        /* ============================================================
           THEME VARIABLES
        ============================================================ */
        :root {
            --bg-body: #f0f4f8;
            --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1a1a3e 100%);
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(96, 165, 250, 0.15);
            --sidebar-border: rgba(255, 255, 255, 0.06);

            --bg-navbar: rgba(255, 255, 255, 0.72);
            --text-primary: #0b1a33;
            --text-secondary: #1e2f4a;
            --text-muted: #64748b;
            --card-bg: rgba(255, 255, 255, 0.78);
            --card-border: rgba(255, 255, 255, 0.35);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 16px 48px rgba(37, 99, 235, 0.12);

            --icon-color: #2563eb;
            --icon-gradient: linear-gradient(135deg, #3b82f6, #8b5cf6);
            --toggle-bg: #e2e8f0;
            --toggle-dot: #ffffff;
            --scrollbar-track: #f1f5f9;
            --scrollbar-thumb: #3b82f6;

            --input-bg: rgba(255, 255, 255, 0.6);
            --input-border: rgba(0, 0, 0, 0.06);
            --input-focus: rgba(255, 255, 255, 0.85);

            --success-bg: rgba(16, 185, 129, 0.10);
            --success-text: #0b7a4a;
            --error-bg: rgba(239, 68, 68, 0.10);
            --error-text: #b91c1c;

            --admin-reply-bg: rgba(16, 185, 129, 0.06);
            --admin-reply-border: #10b981;
            --admin-reply-text: #0b7a4a;
            --no-reply-color: #94a3b8;

            --status-badge-read: rgba(59, 130, 246, 0.10);
            --status-badge-unread: rgba(245, 158, 11, 0.12);
            --status-badge-replied: rgba(16, 185, 129, 0.10);

            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 22px;
        }

        [data-theme="dark"] {
            --bg-body: #0a0f1e;
            --sidebar-bg: linear-gradient(180deg, #0a0f1e 0%, #141428 100%);
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --sidebar-active: rgba(96, 165, 250, 0.12);

            --bg-navbar: rgba(15, 23, 42, 0.82);
            --text-primary: #f0f4f8;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.55);
            --card-border: rgba(255, 255, 255, 0.06);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 16px 48px rgba(37, 99, 235, 0.18);

            --icon-color: #60a5fa;
            --toggle-bg: #475569;
            --toggle-dot: #f0f4f8;
            --scrollbar-track: #1e293b;
            --scrollbar-thumb: #60a5fa;

            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.06);
            --input-focus: rgba(15, 23, 42, 0.8);

            --success-bg: rgba(16, 185, 129, 0.12);
            --success-text: #6ee7b7;
            --error-bg: rgba(239, 68, 68, 0.12);
            --error-text: #fca5a5;

            --admin-reply-bg: rgba(16, 185, 129, 0.08);
            --admin-reply-border: #22c55e;
            --admin-reply-text: #6ee7b7;
            --no-reply-color: #64748b;

            --status-badge-read: rgba(59, 130, 246, 0.18);
            --status-badge-unread: rgba(245, 158, 11, 0.18);
            --status-badge-replied: rgba(16, 185, 129, 0.18);
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
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 270px;
            background: var(--sidebar-bg);
            padding: 28px 18px 24px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--sidebar-border);
            z-index: 999;
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
            padding-left: 6px;
        }

        .sidebar-brand .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--icon-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
        }

        .sidebar-brand span {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .sidebar .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
        }

        .sidebar .nav-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 16px;
            border-radius: 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        .sidebar .nav-menu a i {
            width: 22px;
            font-size: 17px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar .nav-menu a:hover {
            background: var(--sidebar-active);
            color: var(--sidebar-text-hover);
            transform: translateX(4px);
        }

        .sidebar .nav-menu a.active {
            background: var(--sidebar-active);
            color: #60a5fa;
            box-shadow: inset 3px 0 0 #60a5fa;
        }

        .sidebar .nav-menu .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 16px;
            color: #f87171;
        }

        .sidebar .nav-menu .logout-link:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #fca5a5;
        }

        /* ============================================================
           HAMBURGER (Mobile)
        ============================================================ */
        .hamburger {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1000;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 9px 12px;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-primary);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main-content {
            margin-left: 270px;
            flex: 1;
            padding: 24px 36px 40px;
            min-height: 100vh;
            width: calc(100% - 270px);
            transition: margin-left 0.35s ease, width 0.35s ease;
        }

        /* ============================================================
           TOP HEADER
        ============================================================ */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding: 14px 24px;
            background: var(--bg-navbar);
            backdrop-filter: blur(24px);
            border-radius: var(--radius-lg);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-header .greeting-text h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .top-header .greeting-text h2 span {
            background: var(--icon-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .top-header .greeting-text p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .top-header .header-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .header-datetime {
            font-family: 'Inter', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--card-bg);
            padding: 5px 16px;
            border-radius: 40px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            letter-spacing: 0.2px;
        }

        .header-datetime .datetime-text {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-datetime .datetime-text i {
            color: var(--text-muted);
            font-size: 13px;
        }

        .header-datetime .datetime-text .sep {
            color: var(--text-muted);
            margin: 0 2px;
        }

        .theme-toggle {
            background: var(--toggle-bg);
            border: none;
            border-radius: 40px;
            width: 48px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5px;
            cursor: pointer;
            position: relative;
            transition: background 0.3s ease;
            flex-shrink: 0;
        }
        .theme-toggle .toggle-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--toggle-dot);
            transition: transform 0.35s cubic-bezier(0.68, -0.55, 0.27, 1.55), background 0.3s ease;
            position: absolute;
            left: 4px;
            top: 4px;
        }
        [data-theme="dark"] .theme-toggle .toggle-dot {
            transform: translateX(22px);
        }
        .theme-toggle .toggle-icon {
            font-size: 11px;
            color: var(--text-muted);
            z-index: 1;
        }
        .theme-toggle .toggle-icon.sun {
            margin-right: auto;
        }
        .theme-toggle .toggle-icon.moon {
            margin-left: auto;
        }
        [data-theme="dark"] .theme-toggle .toggle-icon.sun {
            color: #fbbf24;
        }
        [data-theme="dark"] .theme-toggle .toggle-icon.moon {
            color: #94a3b8;
        }

        .profile-avatar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 3px 12px 3px 3px;
            background: var(--card-bg);
            border-radius: 40px;
            border: 1px solid var(--card-border);
        }

        .profile-avatar .avatar-img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--icon-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            flex-shrink: 0;
        }

        .profile-avatar .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            border: 2px solid var(--card-bg);
            margin-left: -6px;
            flex-shrink: 0;
        }

        /* ============================================================
           PAGE HEADER
        ============================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .page-header .title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: color 0.3s ease;
        }

        .page-header .title .title-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: var(--icon-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.20);
        }

        .page-header .title span {
            font-weight: 400;
            font-size: 14px;
            color: var(--text-muted);
            margin-left: 2px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.25s ease;
            backdrop-filter: blur(8px);
            box-shadow: var(--card-shadow);
        }

        .btn-back:hover {
            background: var(--card-border);
            color: var(--text-primary);
            transform: translateX(-4px);
        }

        /* ============================================================
           ALERTS
        ============================================================ */
        .alert {
            padding: 14px 22px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(8px);
            animation: fadeUp 0.5s ease both;
        }

        .alert-success {
            border-left: 4px solid #10b981;
            color: var(--success-text);
        }

        .alert-error {
            border-left: 4px solid #ef4444;
            color: var(--error-text);
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================================
           FILTER BAR
        ============================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: center;
            justify-content: space-between;
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
        }

        .filter-bar .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 200px;
            background: var(--input-bg);
            border-radius: var(--radius-sm);
            padding: 0 14px;
            border: 1px solid var(--input-border);
            transition: all 0.25s ease;
        }

        .filter-bar .search-box:focus-within {
            border-color: var(--icon-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
            background: var(--input-focus);
        }

        .filter-bar .search-box i {
            color: var(--text-muted);
            font-size: 14px;
        }

        .filter-bar .search-box input {
            background: transparent;
            border: none;
            padding: 9px 0;
            color: var(--text-primary);
            width: 100%;
            outline: none;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        .filter-bar .search-box input::placeholder {
            color: var(--text-muted);
        }

        .filter-bar .sort-box select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
        }

        .filter-bar .sort-box select:focus {
            border-color: var(--icon-color);
        }

        .filter-bar .msg-count {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .filter-bar .msg-count span {
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ============================================================
           FEEDBACK CARDS
        ============================================================ */
        .msg-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-md);
            padding: 22px 26px;
            margin-bottom: 16px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            animation: fadeUp 0.6s ease both;
            animation-delay: calc(0.06s * var(--i, 1));
            position: relative;
        }

        .msg-card:nth-child(1) { --i: 1; }
        .msg-card:nth-child(2) { --i: 2; }
        .msg-card:nth-child(3) { --i: 3; }
        .msg-card:nth-child(4) { --i: 4; }
        .msg-card:nth-child(5) { --i: 5; }

        .msg-card:hover {
            transform: translateY(-4px);
            border-color: var(--icon-color);
            box-shadow: var(--shadow-hover);
        }

        .msg-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .msg-card .subject {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .msg-card .subject .subject-icon {
            color: var(--icon-color);
            font-size: 16px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(37, 99, 235, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .unread-dot {
            display: none;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--icon-color);
            flex-shrink: 0;
            animation: pulseDot 2s infinite;
        }

        .msg-card.unread .unread-dot {
            display: inline-block;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.7); }
        }

        .msg-card .status-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .status-badge.read {
            background: var(--status-badge-read);
            color: var(--icon-color);
        }

        .status-badge.unread {
            background: var(--status-badge-unread);
            color: #f59e0b;
            animation: pulseBadge 2s infinite;
        }

        .status-badge.replied {
            background: var(--status-badge-replied);
            color: var(--admin-reply-text);
        }

        @keyframes pulseBadge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .msg-card .category-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 14px;
            border-radius: 20px;
            margin: 4px 0 8px 0;
            background: rgba(59, 130, 246, 0.07);
            color: var(--icon-color);
            border: 1px solid rgba(59, 130, 246, 0.08);
        }

        .msg-card .message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 6px 0 10px 0;
            transition: color 0.3s ease;
            overflow: hidden;
            max-height: 120px;
            transition: max-height 0.5s ease;
        }

        .msg-card .message.expanded {
            max-height: 2000px;
        }

        .msg-card .message .expand-toggle {
            color: var(--icon-color);
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            margin-top: 4px;
            font-size: 13px;
        }

        .msg-card .message .expand-toggle:hover {
            text-decoration: underline;
        }

        .msg-card .meta {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 6px;
            align-items: center;
        }

        .msg-card .meta i {
            margin-right: 4px;
        }

        .msg-card .meta .time-ago {
            font-weight: 400;
        }

        /* Admin Reply */
        .admin-reply-box {
            margin-top: 14px;
            padding: 14px 18px;
            background: var(--admin-reply-bg);
            border-left: 4px solid var(--admin-reply-border);
            border-radius: var(--radius-sm);
        }

        .admin-reply-box .admin-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 13px;
            color: var(--admin-reply-text);
            margin-bottom: 4px;
        }

        .admin-reply-box .admin-title i {
            font-size: 14px;
        }

        .admin-reply-box .reply-text {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        .no-reply {
            color: var(--no-reply-color);
            font-size: 13px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .no-reply i {
            color: #f59e0b;
        }

        .status-dots {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }

        .status-dots .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--text-muted);
            animation: dotPulse 1.5s ease-in-out infinite;
        }

        .status-dots .dot:nth-child(2) { animation-delay: 0.3s; }
        .status-dots .dot:nth-child(3) { animation-delay: 0.6s; }
        .status-dots .dot.active { background: var(--admin-reply-text); }

        @keyframes dotPulse {
            0%, 100% { opacity: 0.25; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* User Reply Section */
        .user-reply-section {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--card-border);
        }

        .btn-reply {
            background: none;
            border: none;
            color: var(--icon-color);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 8px;
            transition: all 0.25s ease;
        }

        .btn-reply:hover {
            background: rgba(59, 130, 246, 0.08);
            transform: translateY(-2px);
        }

        .reply-form {
            display: none;
            margin-top: 12px;
            padding: 16px 20px;
            background: var(--input-bg);
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }

        .reply-form.open {
            display: block;
            animation: slideDownForm 0.4s ease forwards;
        }

        @keyframes slideDownForm {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .reply-form textarea {
            width: 100%;
            padding: 10px 14px;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--text-primary);
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
            outline: none;
        }

        .reply-form textarea:focus {
            border-color: var(--icon-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .reply-form textarea::placeholder {
            color: var(--text-muted);
        }

        .reply-form .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .btn-submit-reply {
            padding: 8px 22px;
            background: var(--icon-gradient);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.20);
        }

        .btn-submit-reply:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.25);
        }

        .btn-cancel-reply {
            padding: 8px 18px;
            background: var(--card-bg);
            color: var(--text-secondary);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-cancel-reply:hover {
            background: var(--card-border);
        }

        /* ============================================================
           EMPTY STATE
        ============================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-md);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
        }

        .empty-state .empty-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
            color: var(--text-muted);
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 17px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .empty-state .sub-text {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 6px;
            opacity: 0.6;
        }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer-bar {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
        }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 12px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--icon-color); }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 18px 18px 30px;
            }
            .hamburger {
                display: block;
            }
            .header-datetime {
                font-size: 12px;
                padding: 4px 12px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 14px 14px 24px;
            }
            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 14px 18px;
            }
            .top-header .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .page-header .title {
                font-size: 19px;
            }
            .page-header .title .title-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 12px 16px;
            }
            .filter-bar .search-box {
                width: 100%;
            }
            .filter-bar .sort-box {
                width: 100%;
            }
            .filter-bar .msg-count {
                text-align: center;
            }
            .msg-card {
                padding: 16px 18px;
            }
            .msg-card .subject {
                font-size: 15px;
            }
            .header-datetime {
                font-size: 11px;
                padding: 4px 10px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px 10px 20px;
            }
            .top-header .greeting-text h2 {
                font-size: 17px;
            }
            .page-header .title {
                font-size: 17px;
            }
            .page-header .title .title-icon {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            .msg-card {
                padding: 14px 14px;
            }
            .msg-card .subject {
                font-size: 14px;
            }
            .msg-card .message {
                font-size: 13px;
            }
            .header-datetime {
                font-size: 10px;
                padding: 3px 8px;
            }
            .btn-back {
                font-size: 12px;
                padding: 6px 14px;
            }
            .reply-form {
                padding: 12px 14px;
            }
            .reply-form .form-actions {
                flex-direction: column;
            }
            .btn-submit-reply,
            .btn-cancel-reply {
                width: 100%;
                justify-content: center;
            }
            .empty-state {
                padding: 40px 16px;
            }
            .empty-state .empty-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            .empty-state p {
                font-size: 15px;
            }
            .msg-card .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .msg-card .status-badge {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- ===== HAMBURGER ===== -->
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fa-solid fa-plane-departure"></i>
            </div>
            <span>Travel Portal</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
            <a href="packages.php">
                <i class="fa-solid fa-suitcase-rolling"></i> Packages
            </a>
            <a href="my_bookings.php">
                <i class="fa-solid fa-box-archive"></i> My Bookings
            </a>
            <a href="feedback_status.php" class="active">
                <i class="fa-solid fa-envelope"></i> Feedback
            </a>
            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i> Payments
            </a>
            <a href="view_package.php?id=1">
                <i class="fa-solid fa-compass"></i> Plan a Trip
            </a>
            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- ===== TOP HEADER ===== -->
        <header class="top-header">
            <div class="greeting-text">
                <h2>
                    <?php echo $greeting; ?>, <span><?php echo htmlspecialchars($user_name); ?></span> 👋
                </h2>
                <p>
                    <i class="fa-regular fa-star" style="color:#f59e0b;"></i>
                    Manage your feedback messages and replies.
                </p>
            </div>
            <div class="header-actions">
                <div class="header-datetime">
                    <span class="datetime-text">
                        <i class="fa-regular fa-calendar"></i>
                        <?php echo date('h:i:s A'); ?>
                        <span class="sep">|</span>
                        <?php echo date('F j, Y'); ?>
                    </span>
                </div>

                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <span class="toggle-icon sun"><i class="fa-regular fa-sun"></i></span>
                    <span class="toggle-dot"></span>
                    <span class="toggle-icon moon"><i class="fa-regular fa-moon"></i></span>
                </button>

                <div class="profile-avatar">
                    <div class="avatar-img">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <span class="status-dot"></span>
                </div>
            </div>
        </header>

        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <div class="title">
                <div class="title-icon">
                    <i class="fa-regular fa-envelope"></i>
                </div>
                Feedback Status
                <span>· <?php echo htmlspecialchars($user_email); ?></span>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- ===== ALERTS ===== -->
        <?php if (isset($_GET['success']) || $reply_success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fa-regular fa-circle-check"></i> <?php echo $reply_success ?: 'Your reply has been sent successfully.'; ?>
            </div>
        <?php endif; ?>

        <?php if ($reply_error): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $reply_error; ?>
            </div>
        <?php endif; ?>

        <?php
        // Fetch feedback messages
        $query = mysqli_query($conn, "SELECT * FROM contact_feedbacks WHERE email = '$user_email' ORDER BY id DESC");

        if (mysqli_num_rows($query) > 0) {
            $total_messages = mysqli_num_rows($query);
            ?>

            <!-- ===== FILTER BAR ===== -->
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search messages..." oninput="filterMessages()">
                </div>
                <div class="sort-box">
                    <select id="sortSelect" onchange="sortMessages()">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="subject-asc">Subject A → Z</option>
                        <option value="subject-desc">Subject Z → A</option>
                    </select>
                </div>
                <div class="msg-count">Showing <span id="visibleCount">0</span> of <?php echo $total_messages; ?> messages</div>
            </div>

            <?php
            $index = 0;
            $categories = ['General', 'Booking', 'Support', 'Payment', 'Other'];
            while ($row = mysqli_fetch_assoc($query)) {
                $index++;
                $feedback_id = $row['id'];
                $subject = $row['subject'];
                $message = $row['message'];
                $admin_reply = $row['admin_reply'] ?? '';

                $category = $categories[array_rand($categories)];

                $date_value = 'N/A';
                $timestamp = null;
                if ($has_date_column && isset($row[$date_column]) && !empty($row[$date_column]) && $row[$date_column] != '0000-00-00 00:00:00' && $row[$date_column] != '0000-00-00') {
                    $timestamp = strtotime($row[$date_column]);
                    $date_value = date('d M Y, h:i A', $timestamp);
                }

                $time_ago = '';
                if ($timestamp) {
                    $diff = time() - $timestamp;
                    if ($diff < 60) $time_ago = 'Just now';
                    elseif ($diff < 3600) $time_ago = floor($diff / 60) . 'm ago';
                    elseif ($diff < 86400) $time_ago = floor($diff / 3600) . 'h ago';
                    elseif ($diff < 604800) $time_ago = floor($diff / 86400) . 'd ago';
                    else $time_ago = date('d M Y', $timestamp);
                }

                $status = 'read';
                $status_label = 'Read';
                if (empty($admin_reply)) {
                    $status = 'unread';
                    $status_label = 'Unread';
                } elseif (!empty($admin_reply)) {
                    $status = 'replied';
                    $status_label = 'Replied';
                }

                $is_long = strlen($message) > 150;

                echo "<div class='msg-card $status' id='feedback-$feedback_id' data-date='$timestamp' data-subject='" . strtolower($subject) . "'>";

                echo "<div class='card-header'>";
                echo "<div class='subject'>";
                echo "<span class='subject-icon'><i class='fa-regular fa-comment'></i></span> " . htmlspecialchars($subject);
                echo "<span class='unread-dot'></span>";
                echo "</div>";
                echo "<span class='status-badge $status'>$status_label</span>";
                echo "</div>";

                echo "<span class='category-tag'><i class='fa-regular fa-tag'></i> $category</span>";

                echo "<div class='message" . ($is_long ? '' : ' expanded') . "' id='msg-$feedback_id'>";
                echo nl2br(htmlspecialchars($message));
                if ($is_long) {
                    echo "<br><span class='expand-toggle' onclick='toggleMessage($feedback_id)'>Show more</span>";
                }
                echo "</div>";

                echo "<div class='meta'>";
                echo "<span><i class='fa-regular fa-calendar'></i> " . $date_value . "</span>";
                if ($time_ago) {
                    echo "<span class='time-ago'><i class='fa-regular fa-clock'></i> $time_ago</span>";
                }
                echo "</div>";

                if (!empty($admin_reply)) {
                    echo "<div class='admin-reply-box'>";
                    echo "<div class='admin-title'><i class='fa-solid fa-check-circle'></i> Admin Response</div>";
                    echo "<div class='reply-text'>" . nl2br(htmlspecialchars($admin_reply)) . "</div>";
                    echo "</div>";
                } else {
                    echo "<div class='no-reply'><i class='fa-regular fa-hourglass-half'></i> Waiting for admin reply...</div>";
                    echo "<div class='status-dots'>";
                    echo "<span class='dot active'></span><span class='dot'></span><span class='dot'></span>";
                    echo "<span style='font-size:12px;color:var(--text-muted);margin-left:6px;'>Admin is reviewing...</span>";
                    echo "</div>";
                }

                echo "<div class='user-reply-section'>";
                echo "<button class='btn-reply' onclick='toggleReplyForm($feedback_id)'><i class='fa-regular fa-reply'></i> Reply</button>";
                echo "<div class='reply-form' id='reply-form-$feedback_id'>";
                echo "<form method='POST' id='replyForm-$feedback_id'>";
                echo "<input type='hidden' name='feedback_id' value='$feedback_id'>";
                echo "<input type='hidden' name='original_subject' value='" . htmlspecialchars($subject) . "'>";
                echo "<textarea name='reply_message' placeholder='Type your reply here...' required></textarea>";
                echo "<div class='form-actions'>";
                echo "<button type='submit' name='reply' class='btn-submit-reply'><i class='fa-regular fa-paper-plane'></i> Send Reply</button>";
                echo "<button type='button' class='btn-cancel-reply' onclick='toggleReplyForm($feedback_id)'>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";

                echo "</div>";
            }
        } else {
            echo "<div class='empty-state'>";
            echo "<div class='empty-icon'><i class='fa-regular fa-inbox'></i></div>";
            echo "<p>No feedback messages found</p>";
            echo "<div class='sub-text'>You haven't sent any feedback yet. Contact us to get started.</div>";
            echo "</div>";
        }
        ?>

        <!-- ===== FOOTER ===== -->
        <footer class="footer-bar">
            <span>© <?php echo date('Y'); ?> Travel Portal &bull; All rights reserved.</span>
        </footer>

    </main>

    <!-- ============================================================
    SCRIPTS (Core functionality only)
    ============================================================ -->
    <script>
        // ===== SIDEBAR TOGGLE (Mobile) =====
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('open')) {
                icon.className = 'fa-solid fa-xmark';
            } else {
                icon.className = 'fa-solid fa-bars';
            }
        });

        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnHamburger = hamburger.contains(event.target);
            if (!isClickInsideSidebar && !isClickOnHamburger && window.innerWidth <= 992) {
                sidebar.classList.remove('open');
                hamburger.querySelector('i').className = 'fa-solid fa-bars';
            }
        });

        // ===== THEME TOGGLE =====
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

        // ===== TOGGLE REPLY FORM =====
        function toggleReplyForm(id) {
            const form = document.getElementById('reply-form-' + id);
            if (form.classList.contains('open')) {
                form.classList.remove('open');
                form.style.display = 'none';
            } else {
                form.style.display = 'block';
                setTimeout(() => form.classList.add('open'), 10);
                const textarea = form.querySelector('textarea');
                if (textarea) setTimeout(() => textarea.focus(), 100);
            }
        }

        // ===== TOGGLE EXPAND =====
        function toggleMessage(id) {
            const msg = document.getElementById('msg-' + id);
            const toggle = msg.querySelector('.expand-toggle');
            if (msg.classList.contains('expanded')) {
                msg.classList.remove('expanded');
                if (toggle) toggle.textContent = 'Show more';
            } else {
                msg.classList.add('expanded');
                if (toggle) toggle.textContent = 'Show less';
            }
        }

        // ===== SEARCH FILTER =====
        function filterMessages() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.msg-card');
            let visible = 0;
            cards.forEach(card => {
                const subject = card.dataset.subject || '';
                const message = card.querySelector('.message')?.textContent?.toLowerCase() || '';
                const match = subject.includes(query) || message.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('visibleCount').textContent = visible;
        }

        // ===== SORT =====
        function sortMessages() {
            const sortVal = document.getElementById('sortSelect').value;
            const cards = Array.from(document.querySelectorAll('.msg-card'));

            cards.sort((a, b) => {
                const dateA = parseInt(a.dataset.date) || 0;
                const dateB = parseInt(b.dataset.date) || 0;
                const subjectA = a.dataset.subject || '';
                const subjectB = b.dataset.subject || '';

                if (sortVal === 'newest') return dateB - dateA;
                if (sortVal === 'oldest') return dateA - dateB;
                if (sortVal === 'subject-asc') return subjectA.localeCompare(subjectB);
                if (sortVal === 'subject-desc') return subjectB.localeCompare(subjectA);
                return 0;
            });

            const container = document.querySelector('.main-content');
            const filterBar = document.querySelector('.filter-bar');

            cards.forEach(card => {
                const parent = card.parentNode;
                if (parent) {
                    parent.removeChild(card);
                    if (filterBar && filterBar.parentNode) {
                        filterBar.parentNode.insertBefore(card, filterBar.nextSibling);
                    }
                }
            });

            updateVisibleCount();
        }

        function updateVisibleCount() {
            const visible = document.querySelectorAll('.msg-card:not([style*="display: none"])');
            document.getElementById('visibleCount').textContent = visible.length;
        }

        // ===== AUTO-HIDE ALERTS =====
        document.addEventListener('DOMContentLoaded', function() {
            updateVisibleCount();

            document.querySelectorAll('.alert').forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>

</body>
</html>