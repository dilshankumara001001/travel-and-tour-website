<?php
// ============================================================
//  PHPMailer USE STATEMENTS (MUST BE AT TOP)
// ============================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================================
//  SESSION START
// ============================================================
session_start();

// ============================================================
//  DATABASE CONNECTION
// ============================================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'travel_tour_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ============================================================
//  HANDLE FORGOT PASSWORD
// ============================================================
$message = '';
$message_type = '';
$reset_link_display = '';
$entered_email = '';
$token_display = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $entered_email = $email;

    // ============================================================
    //  CHECK EMAIL IN ALL THREE TABLES: users, admin, vehicle_manager
    // ============================================================
    $stmt = $conn->prepare("
        SELECT id, 'users' as table_name FROM users WHERE email = ? 
        UNION 
        SELECT AdminID, 'admin' as table_name FROM admin WHERE Email = ? 
        UNION 
        SELECT id, 'vehicle_manager' as table_name FROM vehicle_manager WHERE email = ? AND status = 'active'
    ");
    $stmt->bind_param("sss", $email, $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = "❌ Email not found in our system.";
        $message_type = 'error';
        $stmt->close();
    } else {
        // Get the table name where email was found (useful for debugging)
        $row = $result->fetch_assoc();
        $found_in_table = $row['table_name'] ?? 'unknown';
        $stmt->close();

        // Delete old tokens for this email
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        // Generate token
        $token = bin2hex(random_bytes(32));
        $token_display = $token;
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        if ($stmt->execute()) {
            $stmt->close();
            
            // Reset link (points to reset_password.php)
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/reset_password.php?token=" . urlencode($token);
            $reset_link_display = $reset_link;

            // ============================================================
            //  SEND EMAIL USING PHPMailer
            // ============================================================
            $email_sent = false;
            $mail_error = '';

            $phpmailer_path = __DIR__ . '/phpmailer/src/PHPMailer.php';
            if (file_exists($phpmailer_path)) {
                require_once $phpmailer_path;
                require_once __DIR__ . '/phpmailer/src/SMTP.php';
                require_once __DIR__ . '/phpmailer/src/Exception.php';
            } else {
                $autoload = __DIR__ . '/vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                } else {
                    $mail_error = "PHPMailer not found.";
                }
            }

            if (empty($mail_error)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'dileepadilshan46@gmail.com';
                    $mail->Password   = 'futvipxtqvfukpcw';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('no-reply@traveltour.com', 'Travel & Tour');
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = '🔐 Password Reset Request';
                    $mail->Body    = "
                        <html>
                        <head>
                            <style>
                                body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #f6f9fc; }
                                .container { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); overflow: hidden; }
                                .header { background: linear-gradient(135deg, #0ea5e9, #3b82f6); padding: 30px 30px 20px; text-align: center; }
                                .header h1 { color: white; font-size: 24px; margin: 0; font-weight: 600; letter-spacing: -0.5px; }
                                .header p { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 15px; }
                                .body { padding: 35px 30px; }
                                .body p { color: #334155; line-height: 1.7; font-size: 15px; margin: 0 0 16px; }
                                .btn { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #0ea5e9, #3b82f6); color: white !important; text-decoration: none; border-radius: 40px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(14,165,233,0.3); transition: 0.2s; }
                                .btn:hover { box-shadow: 0 6px 20px rgba(14,165,233,0.4); transform: translateY(-2px); }
                                .divider { border: none; border-top: 1px solid #e9edf2; margin: 28px 0; }
                                .footer-text { font-size: 13px; color: #94a3b8; text-align: center; margin-top: 10px; }
                                .link-box { background: #f1f5f9; padding: 14px; border-radius: 8px; font-size: 13px; word-break: break-all; color: #1e293b; margin: 10px 0; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>🔐 Reset Your Password</h1>
                                    <p>Secure • Fast • Reliable</p>
                                </div>
                                <div class='body'>
                                    <p>Hello,</p>
                                    <p>We received a request to reset the password for your account. Click the button below to create a new password:</p>
                                    <p style='text-align: center; margin: 30px 0;'>
                                        <a href='{$reset_link}' class='btn'>Reset Password</a>
                                    </p>
                                    <p style='font-size: 14px; color: #475569;'>If the button doesn't work, copy and paste this link into your browser:</p>
                                    <div class='link-box'>{$reset_link}</div>
                                    <hr class='divider'>
                                    <p style='font-size: 13px; color: #64748b;'><span style='font-weight:600;'>⏰</span> This link will expire in <strong>30 minutes</strong>.</p>
                                    <p style='font-size: 13px; color: #64748b;'>If you did not request a password reset, please ignore this email — your account is safe.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    $mail->AltBody = "Password Reset Request\n\n";
                    $mail->AltBody .= "Click the link to reset your password:\n" . $reset_link . "\n\n";
                    $mail->AltBody .= "This link expires in 30 minutes.\n";
                    $mail->AltBody .= "If you didn't request this, ignore this email.";

                    $mail->send();
                    $email_sent = true;
                    $message = "✅ A password reset link has been sent to your email.";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $mail_error = "PHPMailer Error: " . $mail->ErrorInfo;
                }
            }

            if (!$email_sent) {
                $message = "⚠️ Email could not be sent. Error: " . $mail_error . ". Here is your reset link:";
                $message_type = 'info';
            }
        } else {
            $message = "❌ Error: Could not process your request.";
            $message_type = 'error';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password | Travel & Tour</title>

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

        /* ===== FORM STYLES ===== */
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
        .info { background: rgba(14,165,233,0.08); color: #0ea5e9; border-left: 4px solid #0ea5e9; }

        .link-box {
            background: var(--bg-input);
            padding: 12px 14px;
            border-radius: 10px;
            margin-top: 12px;
            word-break: break-all;
            font-size: 13px;
            border: 1px solid var(--border-card);
            color: var(--text-primary);
        }
        .link-box a {
            color: var(--text-accent);
            text-decoration: none;
            font-weight: 500;
        }
        .link-box a:hover {
            text-decoration: underline;
        }

        .token-box {
            background: var(--bg-input);
            padding: 8px 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            border: 1px solid var(--border-card);
            overflow-x: auto;
            white-space: nowrap;
        }

        .expiry-info {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-link {
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }
        .back-link a {
            color: var(--text-accent);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }
        .back-link a:hover {
            text-decoration: underline;
        }

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
            <span class="icon-big"><i class="fa-solid fa-lock"></i></span>
            <h2>Reset Password</h2>
            <p class="sub-text">Enter your email to receive a reset link</p>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fa-solid <?php 
                        echo $message_type === 'success' ? 'fa-check-circle' : 
                            ($message_type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info'); 
                    ?>"></i>
                    <div style="flex:1;">
                        <?php echo $message; ?>
                        <?php if (!empty($reset_link_display) && $message_type !== 'success'): ?>
                            <div class="link-box">
                                <a href="<?php echo htmlspecialchars($reset_link_display); ?>" target="_blank">
                                    <?php echo htmlspecialchars($reset_link_display); ?>
                                </a>
                            </div>
                            <div class="expiry-info">
                                <i class="fa-regular fa-clock"></i> Link expires in 30 minutes
                            </div>
                            <div class="token-box">
                                <i class="fa-solid fa-key" style="margin-right:6px;"></i> Token: <?php echo htmlspecialchars($token_display); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" 
                           placeholder="your@email.com" 
                           value="<?php echo htmlspecialchars($entered_email); ?>" required>
                </div>
                <button type="submit" name="forgot_password" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div class="back-link">
                <a href="login.php"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i> Back to Login</a>
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

        console.log('%c🔐 Forgot Password Page Loaded (All 3 Tables Supported)', 'font-size:16px; font-weight:bold; color:#0ea5e9;');
    </script>

</body>
</html>
<?php $conn->close(); ?>