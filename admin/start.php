<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Secure Access Gateway | Enterprise System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ============================================================
                   1.  CSS CUSTOM PROPERTIES — THEME ENGINE
                   ============================================================ */
        :root {
            /* --- DARK THEME (default) --- */
            --bg-primary: #050a10;
            --bg-secondary: #0a1420;
            --bg-card: rgba(10, 20, 32, 0.88);
            --bg-input: rgba(0, 0, 0, 0.45);
            --bg-check: rgba(0, 0, 0, 0.40);
            --bg-terminal: rgba(0, 0, 0, 0.60);

            --text-primary: #ffffff;
            --text-secondary: #e2e8f0;
            --text-muted: #94a3b8;
            --text-accent: #00f2ff;
            --text-success: #10b981;
            --text-dark: #0a1420;

            --border-card: rgba(0, 242, 255, 0.15);
            --border-check: #00f2ff;
            --border-check-ok: #10b981;

            --shadow-card: 0 25px 60px rgba(0, 0, 0, 0.7), 0 0 40px rgba(0, 242, 255, 0.03);
            --shadow-glow: rgba(0, 242, 255, 0.40);
            --shadow-btn: rgba(0, 242, 255, 0.20);

            --bg-glow: radial-gradient(circle, rgba(0, 242, 255, 0.08) 0%, transparent 70%);
            --bg-radial: radial-gradient(circle at center, rgba(16, 185, 129, 0.03) 0%, #050a10 100%);

            --grid-color: rgba(0, 242, 255, 0.03);
            --grid-line: rgba(0, 242, 255, 0.03);

            --btn-bg: transparent;
            --btn-border: rgba(0, 242, 255, 0.30);
            --btn-color: rgba(0, 242, 255, 0.40);

            --btn-unlocked-border: #00f2ff;
            --btn-unlocked-color: #00f2ff;
            --btn-unlocked-bg: rgba(0, 242, 255, 0.05);

            --terminal-text: #10b981;
            --terminal-border: rgba(16, 185, 129, 0.10);

            --hover-border: rgba(0, 242, 255, 0.40);
            --hover-bg: rgba(0, 242, 255, 0.03);
            --hover-shadow: rgba(0, 242, 255, 0.05);

            --footer-opacity: 0.40;

            --transition-speed: 0.45s;
        }

        /* --- LIGHT THEME --- */
        [data-theme="light"] {
            --bg-primary: #f0f4f8;
            --bg-secondary: #e6ecf2;
            --bg-card: rgba(255, 255, 255, 0.92);
            --bg-input: rgba(0, 0, 0, 0.04);
            --bg-check: rgba(0, 0, 0, 0.03);
            --bg-terminal: rgba(0, 0, 0, 0.04);

            --text-primary: #0a1420;
            --text-secondary: #1a2a3a;
            --text-muted: #3a4a5a;
            --text-accent: #0077be;
            --text-success: #0d8b5e;
            --text-dark: #050a10;

            --border-card: rgba(0, 119, 190, 0.20);
            --border-check: #0077be;
            --border-check-ok: #0d8b5e;

            --shadow-card: 0 25px 60px rgba(0, 0, 0, 0.08), 0 0 40px rgba(0, 119, 190, 0.04);
            --shadow-glow: rgba(0, 119, 190, 0.25);
            --shadow-btn: rgba(0, 119, 190, 0.15);

            --bg-glow: radial-gradient(circle, rgba(0, 119, 190, 0.05) 0%, transparent 70%);
            --bg-radial: radial-gradient(circle at center, rgba(13, 139, 94, 0.02) 0%, #f0f4f8 100%);

            --grid-color: rgba(0, 119, 190, 0.04);
            --grid-line: rgba(0, 119, 190, 0.04);

            --btn-bg: transparent;
            --btn-border: rgba(0, 119, 190, 0.25);
            --btn-color: rgba(0, 119, 190, 0.35);

            --btn-unlocked-border: #0077be;
            --btn-unlocked-color: #0077be;
            --btn-unlocked-bg: rgba(0, 119, 190, 0.05);

            --terminal-text: #0d8b5e;
            --terminal-border: rgba(13, 139, 94, 0.10);

            --hover-border: rgba(0, 119, 190, 0.35);
            --hover-bg: rgba(0, 119, 190, 0.02);
            --hover-shadow: rgba(0, 119, 190, 0.04);

            --footer-opacity: 0.50;
        }

        /* ============================================================
                   2.  CORE RESET & BASE
                   ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body,
        html {
            height: 100%;
            width: 100%;
            font-family: 'Rajdhani', sans-serif;
            background-color: var(--bg-primary);
            overflow: hidden;
            color: var(--text-primary);
            transition: background-color var(--transition-speed) ease,
                color var(--transition-speed) ease;
        }

        /* --- Background Overlay --- */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-radial);
            background-image:
                linear-gradient(var(--grid-line) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: -1;
            transition: background var(--transition-speed) ease;
        }

        /* --- Glowing Tech Nodes --- */
        .bg-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            background: var(--bg-glow);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: -1;
            pointer-events: none;
            animation: pulseGlow 4s ease-in-out infinite alternate;
            transition: background var(--transition-speed) ease;
        }

        /* ============================================================
                   3.  THEME TOGGLE — PREMIUM SWITCH
                   ============================================================ */
        .theme-toggle-wrap {
            position: fixed;
            top: 28px;
            right: 32px;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--bg-card);
            padding: 8px 18px 8px 16px;
            border-radius: 60px;
            border: 1px solid var(--border-card);
            backdrop-filter: blur(16px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transition: all var(--transition-speed) ease;
        }

        .theme-toggle-wrap:hover {
            border-color: var(--hover-border);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.20);
        }

        .theme-toggle-wrap .label {
            font-size: 0.70rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-family: 'Orbitron', sans-serif;
            transition: color var(--transition-speed) ease;
        }

        .theme-toggle-wrap .label i {
            margin-right: 6px;
        }

        /* --- The Toggle Switch --- */
        .toggle-switch {
            position: relative;
            width: 56px;
            height: 30px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--bg-input);
            border: 1px solid var(--border-card);
            border-radius: 30px;
            transition: all var(--transition-speed) ease;
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.10);
        }

        .toggle-slider::before {
            content: "\f185";
            /* fa-sun */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 4px;
            bottom: 4px;
            width: 20px;
            height: 20px;
            background: var(--text-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.70rem;
            color: #fff;
            transition: all var(--transition-speed) cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* checked state → moon icon */
        .toggle-switch input:checked+.toggle-slider::before {
            content: "\f186";
            /* fa-moon */
            transform: translateX(26px);
            background: var(--text-success);
        }

        .toggle-switch input:checked+.toggle-slider {
            border-color: var(--border-check-ok);
        }

        /* ============================================================
                   4.  MAIN CONTAINER
                   ============================================================ */
        .gateway-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
            padding: 20px;
        }

        /* ============================================================
                   5.  SYSTEM HEADER
                   ============================================================ */
        .system-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 1s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .system-header span {
            font-size: 0.95rem;
            color: var(--text-success);
            letter-spacing: 6px;
            text-transform: uppercase;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 8px;
            transition: color var(--transition-speed) ease;
        }

        .system-header span i {
            margin-right: 10px;
        }

        .system-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 3.2rem;
            letter-spacing: 10px;
            text-transform: uppercase;
            color: var(--text-primary);
            text-shadow: 0 0 30px var(--shadow-glow);
            margin-bottom: 10px;
            font-weight: 900;
            transition: color var(--transition-speed) ease,
                text-shadow var(--transition-speed) ease;
        }

        .system-header p {
            font-size: 1.2rem;
            color: var(--text-muted);
            letter-spacing: 3px;
            font-weight: 600;
            transition: color var(--transition-speed) ease;
        }

        /* ============================================================
                   6.  VERIFICATION CARD — GLASSMORPHISM
                   ============================================================ */
        .verify-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            padding: 44px 40px 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            backdrop-filter: blur(24px);
            box-shadow: var(--shadow-card);
            animation: cardEntrance 1.2s cubic-bezier(0.19, 1, 0.22, 1);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-speed) ease;
        }

        /* --- Laser Scanning Line --- */
        .scan-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--text-accent), transparent);
            box-shadow: 0 0 20px var(--text-accent);
            animation: scanning 2.5s linear infinite;
            display: none;
            transition: background var(--transition-speed) ease,
                box-shadow var(--transition-speed) ease;
        }

        /* --- Card Title --- */
        .verify-card h3 {
            margin-bottom: 28px;
            font-size: 1.5rem;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 3px;
            text-align: center;
            color: var(--text-primary);
            font-weight: 700;
            transition: color var(--transition-speed) ease;
        }

        /* ============================================================
                   7.  CAPTCHA / ROBOT CHECK
                   ============================================================ */
        .robot-check {
            display: flex;
            align-items: center;
            background: var(--bg-check);
            padding: 20px 24px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.03);
            margin-bottom: 28px;
            position: relative;
        }

        .robot-check:hover {
            border-color: var(--hover-border);
            background: var(--hover-bg);
            box-shadow: 0 0 20px var(--hover-shadow);
        }

        .checkbox-custom {
            width: 32px;
            height: 32px;
            border: 2.5px solid var(--border-check);
            border-radius: 8px;
            margin-right: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.35s ease;
            background: transparent;
            flex-shrink: 0;
        }

        .checkbox-custom i {
            display: none;
            color: var(--text-success);
            font-size: 1.2rem;
        }

        .robot-text {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-secondary);
            transition: color var(--transition-speed) ease;
        }

        /* ============================================================
                   8.  TERMINAL STATUS LOG
                   ============================================================ */
        .terminal-status {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.88rem;
            color: var(--terminal-text);
            background: var(--bg-terminal);
            padding: 16px 18px;
            border-radius: 10px;
            border: 1px solid var(--terminal-border);
            margin-bottom: 28px;
            height: 100px;
            overflow-y: auto;
            display: none;
            line-height: 1.6;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
        }

        .terminal-status::-webkit-scrollbar {
            width: 4px;
        }
        .terminal-status::-webkit-scrollbar-track {
            background: transparent;
        }
        .terminal-status::-webkit-scrollbar-thumb {
            background: var(--text-success);
            border-radius: 10px;
        }

        /* ============================================================
                   9.  CORE ACCESS BUTTON
                   ============================================================ */
        .btn-access {
            width: 100%;
            padding: 18px 20px;
            background: var(--btn-bg);
            border: 2px solid var(--btn-border);
            color: var(--btn-color);
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 5px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
            pointer-events: none;
        }

        .btn-access.unlocked {
            border-color: var(--btn-unlocked-border);
            color: var(--btn-unlocked-color);
            pointer-events: auto;
            background: var(--btn-unlocked-bg);
            text-shadow: 0 0 12px var(--shadow-glow);
            box-shadow: 0 0 30px var(--shadow-btn);
        }

        .btn-access.unlocked:hover {
            background: var(--text-accent);
            color: var(--text-dark);
            box-shadow: 0 0 50px var(--text-accent);
            text-shadow: none;
            transform: translateY(-2px);
            border-color: var(--text-accent);
        }

        /* ============================================================
                   10. FOOTER
                   ============================================================ */
        .gateway-container>footer {
            margin-top: 50px;
            text-align: center;
            opacity: var(--footer-opacity);
            letter-spacing: 4px;
            font-size: 0.80rem;
            font-family: 'Orbitron', sans-serif;
            color: var(--text-muted);
            transition: all var(--transition-speed) ease;
            font-weight: 600;
        }

        /* ============================================================
                   11. LOADING SPINNER
                   ============================================================ */
        .spinner {
            width: 24px;
            height: 24px;
            border: 2.5px solid rgba(0, 242, 255, 0.15);
            border-top: 2.5px solid var(--text-accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
            position: absolute;
            right: 24px;
            transition: border-color var(--transition-speed) ease;
        }

        /* ============================================================
                   12. KEYFRAMES
                   ============================================================ */
        @keyframes scanning {
            0% {
                top: 0;
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                top: 100%;
                opacity: 0;
            }
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(60px) rotateX(-15deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulseGlow {
            from {
                opacity: 0.6;
                width: 450px;
                height: 450px;
            }
            to {
                opacity: 1;
                width: 560px;
                height: 560px;
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* ============================================================
                   13. RESPONSIVE
                   ============================================================ */
        @media (max-width: 600px) {
            .theme-toggle-wrap {
                top: 16px;
                right: 16px;
                padding: 6px 14px 6px 12px;
                gap: 10px;
            }
            .theme-toggle-wrap .label {
                font-size: 0.55rem;
                letter-spacing: 1px;
            }
            .toggle-switch {
                width: 44px;
                height: 24px;
            }
            .toggle-slider::before {
                width: 16px;
                height: 16px;
                font-size: 0.55rem;
                left: 3px;
                bottom: 3px;
            }
            .toggle-switch input:checked+.toggle-slider::before {
                transform: translateX(20px);
            }

            .system-header h1 {
                font-size: 2.2rem;
                letter-spacing: 6px;
            }
            .system-header span {
                font-size: 0.75rem;
                letter-spacing: 4px;
            }
            .verify-card {
                padding: 28px 18px 24px;
            }
            .verify-card h3 {
                font-size: 1.2rem;
                letter-spacing: 2px;
            }
            .robot-text {
                font-size: 1.05rem;
            }
            .checkbox-custom {
                width: 28px;
                height: 28px;
                margin-right: 14px;
            }
            .btn-access {
                font-size: 0.90rem;
                padding: 14px 16px;
                letter-spacing: 3px;
            }
            .terminal-status {
                font-size: 0.75rem;
                height: 80px;
                padding: 12px 14px;
            }
            .gateway-container>footer {
                font-size: 0.60rem;
                margin-top: 32px;
            }
        }

        @media (max-width: 400px) {
            .theme-toggle-wrap .label {
                display: none;
            }
            .system-header h1 {
                font-size: 1.8rem;
                letter-spacing: 4px;
            }
            .verify-card {
                padding: 20px 14px 18px;
            }
            .robot-check {
                padding: 14px 16px;
            }
            .robot-text {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>

    <!-- ===== BACKGROUND LAYERS ===== -->
    <div class="bg-overlay"></div>
    <div class="bg-glow"></div>

    <!-- ===== THEME TOGGLE ===== -->
    <div class="theme-toggle-wrap" title="Toggle Theme">
        <span class="label"><i class="fa-regular fa-circle-half-stroke"></i> Theme</span>
        <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" />
            <span class="toggle-slider"></span>
        </label>
    </div>

    <!-- ===== MAIN GATEWAY ===== -->
    <div class="gateway-container">

        <!-- HEADER -->
        <header class="system-header">
            <span><i class="fa-solid fa-shield-halved"></i> Security Protocol Active</span>
            <h1>Enterprise Hub</h1>
            <p id="live-clock">System Node Loading...</p>
        </header>

        <!-- VERIFICATION CARD -->
        <div class="verify-card">
            <div id="laser-scanner" class="scan-line"></div>

            <h3>Gateway Verification</h3>

            <!-- CAPTCHA -->
            <div class="robot-check" id="captcha-box" onclick="triggerHandshake()">
                <div class="checkbox-custom" id="check-box">
                    <i class="fa-solid fa-check" id="check-icon"></i>
                </div>
                <span class="robot-text">I am not a robot</span>
                <div class="spinner" id="loading-spinner"></div>
            </div>

            <!-- TERMINAL LOG -->
            <div class="terminal-status" id="terminal-box"></div>

            <!-- ACCESS BUTTON -->
            <button class="btn-access" id="access-trigger-btn" onclick="executeRedirect()">
                Initialize Core Session
            </button>
        </div>

        <!-- FOOTER -->
        <footer>
            NODE SEC-AUDIT ID: 94X-GATEWAY-AUTH-SUCCESS
        </footer>

    </div>

    <!-- ============================================================
    JAVASCRIPT — THEME ENGINE + CORE LOGIC
    ============================================================ -->
    <script>
        // ============================================================
        //  1.  THEME TOGGLE ENGINE
        // ============================================================
        (function initTheme() {
            const toggle = document.getElementById('themeToggle');
            const html = document.documentElement;

            // --- Load saved preference ---
            const saved = localStorage.getItem('gateway-theme');
            if (saved === 'light') {
                html.setAttribute('data-theme', 'light');
                toggle.checked = true;
            } else {
                html.setAttribute('data-theme', 'dark');
                toggle.checked = false;
            }

            // --- Toggle handler ---
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    html.setAttribute('data-theme', 'light');
                    localStorage.setItem('gateway-theme', 'light');
                } else {
                    html.setAttribute('data-theme', 'dark');
                    localStorage.setItem('gateway-theme', 'dark');
                }
            });
        })();


        // ============================================================
        //  2.  LIVE CLOCK
        // ============================================================
        function runLiveClock() {
            const d = new Date();
            document.getElementById('live-clock').innerText =
                'NODE TIME: ' + d.toLocaleString();
        }
        setInterval(runLiveClock, 1000);
        runLiveClock();


        // ============================================================
        //  3.  CAPTCHA HANDSHAKE (unchanged logic, but enhanced)
        // ============================================================
        let verificationRunning = false;

        function triggerHandshake() {
            if (verificationRunning) return;
            verificationRunning = true;

            const checkIcon = document.getElementById('check-icon');
            const checkBox = document.getElementById('check-box');
            const spinner = document.getElementById('loading-spinner');
            const terminalBox = document.getElementById('terminal-box');
            const laserScanner = document.getElementById('laser-scanner');

            // UI → loading state
            spinner.style.display = 'block';
            laserScanner.style.display = 'block';
            terminalBox.style.display = 'block';
            terminalBox.innerHTML = '';

            // Security handshake logs
            const logs = [
                '> [INFO] Establishing secure TCP/IP handshake...',
                '> [OK] Anti-Bot heuristic pattern verified.',
                '> [INFO] Injecting dynamically salted session hashes...',
                '> [SUCCESS] Identity integrity verified. Access Unlocked.'
            ];

            let idx = 0;
            const interval = setInterval(() => {
                if (idx < logs.length) {
                    terminalBox.innerHTML += logs[idx] + '<br>';
                    terminalBox.scrollTop = terminalBox.scrollHeight;
                    idx++;
                } else {
                    clearInterval(interval);

                    // Finalize
                    spinner.style.display = 'none';
                    checkIcon.style.display = 'block';
                    checkBox.style.borderColor = 'var(--border-check-ok)';
                    checkBox.style.background = 'rgba(16, 185, 129, 0.12)';
                    laserScanner.style.display = 'none';

                    const mainBtn = document.getElementById('access-trigger-btn');
                    mainBtn.classList.add('unlocked');
                    mainBtn.innerText = 'Access Dashboard Now';
                }
            }, 750);
        }


        // ============================================================
        //  4.  REDIRECT WITH CINEMATIC DISSOLVE
        // ============================================================
        function executeRedirect() {
            document.body.style.transition = 'all 0.6s cubic-bezier(0.55, 0.055, 0.675, 0.19)';
            document.body.style.opacity = '0';
            document.body.style.transform = 'scale(1.05)';

            setTimeout(() => {
                window.location.href = '../index.php';
            }, 600);
        }
    </script>

</body>
</html>