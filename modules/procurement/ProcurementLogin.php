<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SOIMS Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
  <div class="container">
    <div class="left-panel">
      <div class="logo">
        <h1>️ SOIMS</h1>
        <p>Supply Office Inventory<br>Management System</p>
      </div>
    </div>
    <div class="right-panel">
      <div class="login-card">
        <h3>Welcome to <br><strong>SOIMS</strong></h3>
        <h4 class="admin-label">Procurement Officer Log In</h4>
        <form id="loginForm">
          <div class="input-group">
            <span class="icon">👤</span>
            <input type="text" placeholder="Username" required>
          </div>
          <div class="input-group">
            <span class="icon">🔒</span>
            <input type="password" placeholder="Password" required>
          </div>
          <div class="forgot-password">
            <a href="#">Forgot Password</a>
          </div>
          <button type="submit" class="login-btn">Log In</button>
        </form>
        <div class="links">
          <a href="index.html">Log In As Admin</a> |
          <a href="sublogin.html">Log In As  Sub Admin</a>
        </div>
        <div class="create-account">
          <p>Don't Have An Account Yet? <a href="signup.php">Create Account</a></p>
        </div>
      </div>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>
