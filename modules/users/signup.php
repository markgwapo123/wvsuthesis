<?php
session_start(); 
require_once __DIR__ . '/db_connect.php';

// Verify authentication state
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName   = trim($_POST['firstName']);
    $middleName  = trim($_POST['middleName']); 
    $lastName    = trim($_POST['lastName']);
    $dob         = $_POST['dob'];
    $contact     = trim($_POST['contact']);
    $address     = trim($_POST['address']);
    $email       = trim($_POST['email']);
    $password    = $_POST['password'];
    $role        = $_POST['role'];
    $affiliation = $_POST['affiliation']; 

    // Comprehensive payload structural safety check
    if (empty($firstName) || empty($lastName) || empty($dob) || 
        empty($contact) || empty($address) || empty($email) || 
        empty($password) || empty($role) || empty($affiliation)) {
        $_SESSION['error_flash'] = "Required fields are missing.";
        header("Location: signup.php");
        exit();
    }
    
    $department = ($role === 'end_user') ? ($_POST['department_select'] === 'Others' ? trim($_POST['department_text']) : $_POST['department_select']) : null;

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, dob, contact, address, email, password, role, department, affiliation, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssssssss", $firstName, $middleName, $lastName, $dob, $contact, $address, $email, $hashedPassword, $role, $department, $affiliation);

    if ($stmt->execute()) {
        $_SESSION['success_flash'] = "Account successfully created for the new profile entry.";
        header("Location: signup.php");
        exit();
    } else {
        $_SESSION['error_flash'] = "Database insertion processing anomaly encountered.";
        header("Location: signup.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --error: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: var(--card-bg);
            width: 100%;
            max-width: 600px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 32px;
        }

        .alert-message {
            padding: 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .msg-error { background: #fef2f2; color: var(--error); border: 1px solid #fecaca; }
        .msg-success { background: #ecfdf5; color: var(--success); border: 1px solid #a7f3d0; }

        .grid-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 20px;
            display: block !important;
            visibility: visible !important;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Modern, Interactive Password Eye-Toggle Styles */
        .password-wrapper { 
            position: relative; 
            display: flex;
            align-items: center;
            width: 100%; 
        }
        
        .password-wrapper input {
            padding-right: 42px;
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

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover { background-color: var(--primary-hover); }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            font-size: 0.875rem;
            color: var(--primary);
            text-decoration: none;
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>

<div class="container">
    <h1>Create User Account</h1>
    <p class="subtitle">Enter the details below to register a new system profile user.</p>

    <?php if (isset($_SESSION['success_flash'])): ?>
        <div class="alert-message msg-success">ðŸŽ‰ <?= $_SESSION['success_flash']; unset($_SESSION['success_flash']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_flash'])): ?>
        <div class="alert-message msg-error">âš ï¸ <?= $_SESSION['error_flash']; unset($_SESSION['error_flash']); ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
        
        <input type="text" name="prevent_autofill_username" style="display:none;" aria-hidden="true">
        <input type="password" name="prevent_autofill_password" style="display:none;" aria-hidden="true">

        <div class="grid-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="firstName" placeholder="John" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Middle Name <small style="color:var(--text-muted)">(Optional)</small></label>
                <input type="text" name="middleName" placeholder="Quincy" autocomplete="off">
            </div>
        </div>

        <div class="grid-row">
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lastName" placeholder="Doe" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" required max="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="grid-row">
            <div class="form-group">
                <label>Email Address</label>
                <input type="text" name="email" placeholder="email@example.com" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Affiliation Classification</label>
                <select name="affiliation" required>
                    <option value="" disabled selected>Select Classification</option>
                    <option value="Academic">Academic</option>
                    <option value="Non-Academic">Non-Academic</option>
                </select>
            </div>
        </div>

        <div class="grid-row">
            <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" name="contact" placeholder="09123456789" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="roleSelect" required onchange="toggleDepartment()">
                    <option value="" disabled selected>Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="subadmin">Sub Admin</option>
                    <option value="inst_admin">School Administration (Inst Admin)</option>
                    <option value="DAA">DAA</option>
                    <option value="end_user">End User</option>
                </select>
            </div>
        </div>

        <div id="deptSection" class="hidden">
            <div class="form-group">
                <label>Department</label>
                <select name="department_select" id="deptSelect">
                    <option value="" disabled selected>Select Department</option>
                    <option value="IT">IT</option>
                    <option value="Math">Math</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="form-group hidden" id="otherDeptInput">
                <label>Specify Department</label>
                <input type="text" name="department_text" id="deptOtherText" placeholder="Name of Department">
            </div>
        </div>

        <div class="form-group">
            <label>Physical Address</label>
            <textarea name="address" rows="2" placeholder="Street, City, Province" required autocomplete="off"></textarea>
        </div>

        <div class="form-group">
            <label>Temporary Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="passwordField" required autocomplete="new-password">
                <button type="button" id="togglePassword" class="toggle-password" title="Toggle visibility">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Register Account</button>
    </form>

    <a href="users.php" class="back-link">â† Back to User Management</a>
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

    function toggleDepartment() {
        const role = document.getElementById('roleSelect').value;
        const deptSection = document.getElementById('deptSection');
        const deptSelect = document.getElementById('deptSelect');

        if (role === 'end_user') {
            deptSection.classList.remove('hidden');
            deptSelect.setAttribute('required', 'required');
        } else {
            deptSection.classList.add('hidden');
            deptSelect.removeAttribute('required');
            deptSelect.value = "";
            document.getElementById('otherDeptInput').classList.add('hidden');
            document.getElementById('deptOtherText').removeAttribute('required');
        }
    }

    document.getElementById('deptSelect').addEventListener('change', function() {
        const otherDiv = document.getElementById('otherDeptInput');
        const otherInput = document.getElementById('deptOtherText');
        if (this.value === 'Others') {
            otherDiv.classList.remove('hidden');
            otherInput.setAttribute('required', 'required');
        } else {
            otherDiv.classList.add('hidden');
            otherInput.removeAttribute('required');
        }
    });
</script>
</body>
</html>
