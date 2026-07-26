<div class="nav-buttons">
    <button onclick="history.back()" class="btn-back">⬅ Back</button>
    
    <a href="../user/login.php" class="btn-login">🔒 Back to Login</a>
</div>

<style>
    /* Styling the buttons to look modern and professional */
    .nav-buttons {
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1000; /* Keeps buttons on top of everything */
        display: flex;
        gap: 10px;
    }

    .btn-back, .btn-login {
        background: rgba(255, 255, 255, 0.05); /* Transparent background */
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        padding: 10px 20px;
        border-radius: 12px;
        cursor: pointer;
        backdrop-filter: blur(10px); /* Creates the glass effect */
        text-decoration: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        transition: 0.3s;
    }

    .btn-back:hover, .btn-login:hover {
        background: #3b82f6; /* Blue color on hover */
    }
</style>