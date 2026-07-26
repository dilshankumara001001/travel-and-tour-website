<?php
// ============================================================
// 🚗 VEHICLE MANAGER LOGIN
// ============================================================
session_start();

// Login වෙලා ඉන්නවා නම් කෙලින්ම Dashboard එකට යන්න
if (isset($_SESSION['manager_id'])) {
    header("Location: dashboard.php");
    exit;
}

// ============================================================
// DATABASE CONNECTION
// ============================================================
$conn = new mysqli('localhost', 'root', '', 'travel_tour_db');
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$error = '';

// ============================================================
// LOGIN FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = sha1($_POST['password']); // Dashboard එකේ තියෙන SHA1 එකම භාවිතා කරමු

    $query = "SELECT * FROM vehicle_manager WHERE email = '$email' AND password = '$password' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows === 1) {
        $manager = $result->fetch_assoc();
        
        // Session variables set කරන්න (Dashboard එක බලාපොරොත්තු වෙනවා)
        $_SESSION['manager_id'] = $manager['id'];
        $_SESSION['manager_name'] = $manager['name'];
        $_SESSION['manager_email'] = $manager['email'];

        // Dashboard එකට Redirect කරන්න
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "❌ Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Manager Login | Travel & Tour</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
            background: #0b1120;
            background-image: radial-gradient(ellipse at 10% 20%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
                              radial-gradient(ellipse at 90% 80%, rgba(16, 185, 129, 0.10) 0%, transparent 50%),
                              linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(24px) saturate(1.8);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 32px;
            padding: 40px 36px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo .icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
            box-shadow: 0 12px 40px rgba(37, 99, 235, 0.3);
            margin-bottom: 16px;
        }
        .login-logo h1 {
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .login-logo p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 14px;
            font-weight: 500;
            margin-top: 4px;
        }
        .login-logo .badge-vehicle {
            display: inline-block;
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 30px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .form-group .input-wrap {
            position: relative;
        }
        .form-group .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.2);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            outline: none;
        }
        .form-group input:focus {
            border-color: rgba(37, 99, 235, 0.5);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        .form-group input:focus + i,
        .form-group input:focus ~ i {
            color: #2563eb;
        }
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.15);
            font-weight: 400;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border: none;
            border-radius: 16px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 8px;
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.2);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(37, 99, 235, 0.4);
        }
        .btn-login:active {
            transform: scale(0.97);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message i {
            font-size: 18px;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: rgba(255, 255, 255, 0.15);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .login-footer a {
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .login-footer a:hover {
            color: #fff;
        }
        /* Responsive */
        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-logo">
        <div class="icon"><i class="fas fa-user-tie"></i></div>
        <h1>Vehicle Manager</h1>
        <p>Sign in to manage your fleet</p>
        <span class="badge-vehicle"><i class="fas fa-car-side"></i> Fleet Control</span>
    </div>

    <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label><i class="fas fa-envelope"></i> Email Address</label>
            <div class="input-wrap">
                <input type="email" name="email" placeholder="manager@travel.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <i class="fas fa-envelope" style="left:14px; top:50%; transform:translateY(-50%); position:absolute; color:rgba(255,255,255,0.2);"></i>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-lock"></i> Password</label>
            <div class="input-wrap">
                <input type="password" name="password" placeholder="Enter your password" required>
                <i class="fas fa-lock" style="left:14px; top:50%; transform:translateY(-50%); position:absolute; color:rgba(255,255,255,0.2);"></i>
            </div>
        </div>

        <button type="submit" name="login" class="btn-login">
            <i class="fas fa-right-to-bracket"></i> Sign In
        </button>
    </form>

    <div class="login-footer">
        <p>🔒 Secure Management Portal</p>
        <p style="margin-top:6px; font-size:10px; text-transform:none; color:rgba(255,255,255,0.08);">
            Demo: manager@travel.com / password123
        </p>
    </div>
</div>

</body>
</html>