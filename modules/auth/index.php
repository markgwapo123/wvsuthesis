<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Load theme settings
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
    ? $settingsQuery->fetch_assoc()
    : ['theme' => 'light'];

// Handle redirection logic
if (isset($_SESSION['role']) && (!isset($_GET['error']) || $_GET['error'] !== 'Unauthorized')) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /SUPPLY_OFFICE_first/Admindashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'procurement') {
        header("Location: /SUPPLY_OFFICE_first/procurementdashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'subadmin') {
        header("Location: /SUPPLY_OFFICE_first/userdashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'end_user') {
        header("Location: /SUPPLY_OFFICE_first/endUserDashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'DAA' || $_SESSION['role'] === 'inst_admin') {
        header("Location: /SUPPLY_OFFICE_first/daa_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOIMS Login</title>
    <link rel="stylesheet" href="../../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --text-muted: #64748b;
        }

        body { font-family: 'Inter', sans-serif; }

        .password-wrapper { 
            position: relative; 
            display: flex;
            align-items: center;
            width: 100%; 
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            background: none;
            border: none;
            padding: 0;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .forgot {
            margin-top: 10px;
            text-align: right;
        }
        
        .forgot a {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
        }

        .forgot a:hover { text-decoration: underline; }

        .error-text {
            color: #ef4444;
            font-size: 0.875rem;
            background: #fef2f2;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
    <div class="container">
        <div class="left-panel">
            <div class="logo">
                <h1>SOIMS</h1>
                <p>Supply Office Inventory<br>Management System</p>
            </div>
        </div>

        <div class="right-panel">
            <div class="login-card">
                <h3>Welcome to <br><strong>SOIMS</strong></h3>
                <h4 class="admin-label">Log in to <b>Your Account</b></h4>

                <?php if (isset($_GET['error'])): ?>
                    <p class="error-text"><?= htmlspecialchars($_GET['error']) ?></p>
                <?php endif; ?>

                <form method="POST" action="/SUPPLY_OFFICE_first/modules/auth/login_process.php">
                    <div class="input-group">
                        <span class="icon">👤</span>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>

                    <div class="input-group password-wrapper">
                        <span class="icon">🔒</span>
                        <input type="password" id="passwordField" name="password" placeholder="Password" required>
                        
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>

                    <div class="forgot">
                        <a href="/SUPPLY_OFFICE_first/modules/auth/forgot_password.php">Forgot Password?</a>
                    </div>

                    <button type="submit" class="login-btn">Log In</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const passwordField = document.getElementById("passwordField");
        const togglePassword = document.getElementById("togglePassword");
        const eyeIcon = document.getElementById("eyeIcon");

        // SVG Path for Visible Eye
        const eyePath = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        `;
        
        // SVG Path for Eye with Slash (Hidden)
        const eyeOffPath = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        `;

        togglePassword.addEventListener("click", () => {
            const isPassword = passwordField.getAttribute("type") === "password";
            passwordField.setAttribute("type", isPassword ? "text" : "password");
            eyeIcon.innerHTML = isPassword ? eyeOffPath : eyePath;
        });
    </script>
</body>
</html>