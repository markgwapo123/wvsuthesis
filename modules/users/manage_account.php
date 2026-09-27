<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db_connect.php';

// Block unauthenticated users
$user = current_user();
if (!$user) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

$user_id = $user['id'];

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userdata = $stmt->get_result()->fetch_assoc();

$profileImageSrc = $userdata['profile_img'] ?? '';
if (empty($profileImageSrc) || preg_match('/^uploads\//i', $profileImageSrc)) {
    $profileImageSrc = '/SUPPLY_OFFICE_first/' . ltrim($profileImageSrc, '/');
}
if (empty($profileImageSrc) || !preg_match('/^(https?:)?\/\//i', $profileImageSrc)) {
    $profileImageSrc = '/SUPPLY_OFFICE_first/assets/img/default_profile.svg';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account | Security & Profile Workspace</title>
    <link rel="stylesheet" href="admindasboard.css">
    <link rel="stylesheet" href="assets/bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-navy: #003366;
            --accent-blue: #004aad;
            --slate-gray: #475569;
            --light-slate: #f8fafc;
            --border-color: #cbd5e1;
            --success-green: #10b981;
            --error-red: #ef4444;
        }

        body {
            background-color: #f4f7fc;
            color: #1e293b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Responsive Dashboard Container Structure */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-workspace {
            flex: 1;
            padding: 40px;
            box-sizing: border-box;
            background-color: #173a75;
        }

        .account-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeInWorkspace 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInWorkspace {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Top Module Header Strip */
        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #ffffff;
            padding: 24px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            border: 1px solid #e2e8f0;
        }

        .header-area h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-navy);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-area p {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: var(--slate-gray);
        }

        /* Clean Content Card Blocks */
        .content-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .card-heading {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-navy);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        /* Profile Avatar System Layout */
        .profile-section-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            align-items: center;
        }

        .avatar-container {
            text-align: center;
            position: relative;
        }

        .profile-img {
            width: 145px;
            height: 145px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #ffffff;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        /* Professional Input Fields Form Elements */
        label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            padding: 10px 14px;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            color: #1e293b;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 74, 173, 0.12);
            outline: none;
        }

        /* Action Controls Buttons Structure */
        .btn-custom {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-navy {
            background-color: var(--primary-navy);
            color: #ffffff;
        }

        .btn-navy:hover {
            background-color: #002244;
            color: #ffffff;
        }

        .btn-slate-outline {
            background-color: #ffffff;
            color: var(--slate-gray);
            border: 1px solid var(--border-color);
        }

        .btn-slate-outline:hover {
            background-color: var(--light-slate);
            color: #0f172a;
        }

        /* Dynamic Password Pipeline Components */
        .input-group-text.toggle-pass {
            background: #ffffff;
            border-left: none;
            color: var(--slate-gray);
            cursor: pointer;
            transition: color 0.2s;
        }

        .input-group-text.toggle-pass:hover {
            color: #0f172a;
        }

        .progress {
            height: 6px;
            border-radius: 10px;
            background-color: #e2e8f0;
        }

        .strength-text {
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        /* Toast Component Notification Layer */
        #toastMessage {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 9999;
            display: none;
            padding: 14px 24px;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .shake {
            animation: formControlShake 0.2s ease-in-out 0s 2;
        }

        @keyframes formControlShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @media (max-width: 768px) {
            .profile-section-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .main-workspace { padding: 20px; }
        }
    </style>
</head>

<body>

    <div class="dashboard-container">
        <?php 
            include __DIR__ . '/../../sidebar.php';
        ?>

        <main class="main-workspace">
            <div id="toastMessage"></div>

            <div class="account-wrapper">

                <div class="header-area">
                    <div>
                        <h2><i class="fa-solid fa-user-gear" style="color: var(--accent-blue);"></i> Account Configuration</h2>
                        <p>Maintain your identity authentication options, contact nodes, and password hashes</p>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-heading"><i class="fa-solid fa-camera"></i> Display Image Profile</div>
                    <div class="profile-section-grid">
                        <div class="avatar-container">
                            <img src="<?= htmlspecialchars($profileImageSrc) ?>" 
                                 class="profile-img" id="profilePreview" alt="Profile View" onerror="this.src='/SUPPLY_OFFICE_first/assets/img/default_profile.svg';">
                        </div>
                        <div>
                            <form id="profilePicForm" enctype="multipart/form-data">
                                <label>Upload New Photo Asset</label>
                                <div class="d-flex gap-2 align-items-center max-w-md">
                                    <input type="file" name="profile_img" accept="image/*" class="form-control" style="max-width: 350px;" required>
                                    <button class="btn-custom btn-navy"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
                                </div>
                                <div class="form-text text-muted mt-2">Supports JPG, PNG, or GIF format assets. Max payload 2MB.</div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-heading"><i class="fa-solid fa-id-card"></i> Personal Information Registry</div>
                    <form id="updateInfoForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label>Given Name</label>
                                <input type="text" class="form-control" name="first_name"
                                    value="<?= htmlspecialchars($userdata['first_name']) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label>Middle Name</label>
                                <input type="text" class="form-control" name="middle_name"
                                    value="<?= htmlspecialchars($userdata['middle_name']) ?>">
                            </div>

                            <div class="col-md-4">
                                <label>Surname Family Name</label>
                                <input type="text" class="form-control" name="last_name"
                                    value="<?= htmlspecialchars($userdata['last_name']) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control" name="dob"
                                    value="<?= htmlspecialchars($userdata['dob']) ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label>Mobile Contact Number</label>
                                <input type="text" class="form-control" name="contact"
                                    value="<?= htmlspecialchars($userdata['contact']) ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label>Email Communication Node</label>
                                <input type="email" class="form-control" name="email"
                                    value="<?= htmlspecialchars($userdata['email']) ?>" required>
                            </div>
                        </div>

                        <div class="mt-4 pt-2 border-top">
                            <button class="btn-custom btn-navy"><i class="fa-solid fa-floppy-disk"></i> Save Profile Registry Changes</button>
                        </div>
                    </form>
                </div>

                <div class="content-card">
                    <div class="card-heading"><i class="fa-solid fa-shield-halved"></i> Security & Cryptographic Password Update</div>
                    
                    <form id="verifyPasswordForm" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Current Authentication Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="current_password" id="currentPasswordInput" required>
                                    <span class="input-group-text toggle-pass" data-target="#currentPasswordInput"><i class="fa-solid fa-eye"></i></span>
                                </div>
                                <button class="btn-custom btn-slate-outline mt-3"><i class="fa-solid fa-key"></i> Verify Gate Credentials</button>
                            </div>
                        </div>
                    </form>

                    <div id="directChangeSection" class="pt-3 border-top" style="display:none;">
                        <h5 class="text-dark font-size-15 font-weight-700 mb-3">Define New Authorization Hash</h5>
                        <form id="directChangeForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label>New Secret Key Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="directNewPassword" required>
                                        <span class="input-group-text toggle-pass" data-target="#directNewPassword"><i class="fa-solid fa-eye"></i></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="progress"><div id="directStrengthBar" class="progress-bar" style="width:0%;"></div></div>
                                        <div id="directStrengthText" class="strength-text"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label>Confirm Target Password Entry</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="directConfirmPassword" required>
                                        <span class="input-group-text toggle-pass" data-target="#directConfirmPassword"><i class="fa-solid fa-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-custom btn-navy mt-4"><i class="fa-solid fa-square-check"></i> Commit Changes</button>
                        </form>
                    </div>

                    <div id="otpChoiceSection" class="pt-3 border-top text-center p-4 bg-light rounded-3" style="display:none;">
                        <p class="text-muted small mb-3">The provided confirmation verification step does not match our current authentication states. You can initiate a secured token verification process to clear registration properties:</p>
                        <button type="button" class="btn-custom btn-navy mx-auto" id="btnOtpEmail">
                            <i class="fa-solid fa-envelope-open-text"></i> Dispatched OTP Token -> (<?= htmlspecialchars($userdata['email']) ?>)
                        </button>
                    </div>

                    <div class="otp-section pt-3 border-top" id="otpSection" style="display:none;">
                        <h5 class="text-dark font-weight-700 mb-3"><i class="fa-solid fa-unlock-keyhole"></i> Token Response Control</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control font-monospace text-center" name="otp" id="otpInput" placeholder="000000" style="font-size: 16px; letter-spacing: 4px;">
                                    <button class="btn btn-primary btn-custom font-weight-600" type="button" id="btnSubmitOtp">Validate Token</button>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small id="otpTimerText" class="text-muted font-weight-500"></small>
                                    <button type="button" class="btn btn-link text-decoration-none p-0 small" id="btnResendOtp" style="display:none;">Resend OTP</button>
                                </div>
                                <div id="otpError" class="alert alert-danger p-2 small mb-3" style="display:none; border-radius:6px;"></div>
                            </div>
                        </div>

                        <div id="newPasswordArea" class="pt-3 border-top" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label>New Cipher Password Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="otpNewPassword">
                                        <span class="input-group-text toggle-pass" data-target="#otpNewPassword"><i class="fa-solid fa-eye"></i></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="progress"><div id="otpStrengthBar" class="progress-bar" style="width:0%;"></div></div>
                                        <div id="otpStrengthText" class="strength-text"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label>Verify Target Match Entry</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="otpConfirmPassword">
                                        <span class="input-group-text toggle-pass" data-target="#otpConfirmPassword"><i class="fa-solid fa-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-custom btn-navy mt-4" id="btnSubmitNewPassword"><i class="fa-solid fa-circle-check"></i> Rewrite Security Authorization</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content style-radius" style="border-radius:12px; border:none; overflow:hidden;">
                <div class="modal-header bg-light border-0" style="padding: 20px 24px;">
                    <h5 class="modal-title font-weight-700 text-dark" style="font-size:16px;">Confirm Security Modification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-secondary" style="padding: 24px; font-size:14px; background: #ffffff;">
                    Are you absolutely sure you want to write these credential modifications over the current profile database registration row properties?
                </div>
                <div class="modal-footer bg-light border-0" style="padding:16px 24px;">
                    <button type="button" class="btn-custom btn-slate-outline" data-bs-dismiss="modal">Cancel Action</button>
                    <button type="button" class="btn-custom btn-navy" id="confirmChangeBtn">Commit Registry Overwrite</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="admindashboard.js" defer></script>
    
    <script>
        let lastVerifiedPassword = null;
        let pendingChangeMode = null;
        let pendingChangeData = {};
        let confirmModal = null;

        let otpAttempts = 0;
        const maxOtpAttempts = 5;
        let otpExpireTimer = null;
        let otpResendCooldownTimer = null;
        let otpExpireAt = null;
        let otpCooldownRemaining = 0;

        function showToast(msg, type = "success") {
            let toast = $("#toastMessage");
            toast.css("background", type === "error" ? "var(--error-red)" : "var(--success-green)");
            toast.html((type === "error" ? "<i class='fa-solid fa-triangle-exclamation'></i> " : "<i class='fa-solid fa-circle-check'></i> ") + msg).fadeIn();
            setTimeout(() => toast.fadeOut(), 3000);
        }

        function evaluateStrength(pwd) {
            let score = 0;
            if (!pwd) return { score: 0, label: "", color: "", width: 0 };
            if (pwd.length >= 8) score++;
            if (/[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;

            let label = "Weak Criteria Profile";
            let color = "bg-danger";
            let width = 25;

            if (score >= 3) {
                label = "Intermediate Security Matrix";
                color = "bg-warning";
                width = 65;
            }
            if (score >= 4) {
                label = "Compliant Cryptographic Hash Standard";
                color = "bg-success";
                width = 100;
            }

            return { score, label, color, width };
        }

        function bindStrengthMeter(inputSelector, barSelector, textSelector) {
            $(inputSelector).on('input', function() {
                const val = $(this).val();
                const res = evaluateStrength(val);
                const bar = $(barSelector);
                const txt = $(textSelector);

                bar.removeClass("bg-danger bg-warning bg-success");
                if (res.color) bar.addClass(res.color);
                bar.css("width", (res.width || 0) + "%");
                txt.text(res.label ? (res.label) : "");
                txt.css("color", res.score >= 4 ? "var(--success-green)" : (res.score >= 3 ? "#d97706" : "var(--error-red)"));
            });
        }

        function formatTime(seconds) {
            seconds = Math.max(0, seconds);
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
        }

        function startOtpExpiryCountdown() {
            if (otpExpireTimer) clearInterval(otpExpireTimer);
            otpExpireTimer = setInterval(function() {
                if (!otpExpireAt) return;
                const remainingSec = Math.round((otpExpireAt - Date.now()) / 1000);
                if (remainingSec <= 0) {
                    $("#otpTimerText").text("Token context window timeline has elapsed. Re-request token validation.");
                    clearInterval(otpExpireTimer);
                    otpExpireTimer = null;
                    return;
                }
                $("#otpTimerText").html("<i class='fa-regular fa-clock'></i> Token active lifespan window: " + formatTime(remainingSec));
            }, 1000);
        }

        function startResendCooldown(seconds) {
            otpCooldownRemaining = seconds;
            $("#btnResendOtp").prop("disabled", true);
            if (otpResendCooldownTimer) clearInterval(otpResendCooldownTimer);
            otpResendCooldownTimer = setInterval(function() {
                otpCooldownRemaining--;
                if (otpCooldownRemaining <= 0) {
                    clearInterval(otpResendCooldownTimer);
                    otpResendCooldownTimer = null;
                    $("#btnResendOtp").prop("disabled", false).text("Resend Operational Token");
                } else {
                    $("#btnResendOtp").text("Throttle Timeout Blocked (" + otpCooldownRemaining + "s)");
                }
            }, 1000);
        }

        $(function() {
            const modalEl = document.getElementById('confirmModal');
            if (modalEl) confirmModal = new bootstrap.Modal(modalEl);

            bindStrengthMeter("#directNewPassword", "#directStrengthBar", "#directStrengthText");
            bindStrengthMeter("#otpNewPassword", "#otpStrengthBar", "#otpStrengthText");

            $("#otpInput").on("input", function() {
                const v = $(this).val().trim();
                if (v.length === 6) $("#btnSubmitOtp").click();
            });
        });

        /* PROFILE FORM ENGINE DISPATCH FILE ASSET */
        $("#profilePicForm").submit(function(e) {
            e.preventDefault();
            let fileInput = $(this).find('input[name="profile_img"]')[0];
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                showToast("Please choose an image file first.", "error");
                return;
            }

            let formData = new FormData(this);
            $.ajax({
                url: "/SUPPLY_OFFICE_first/update_profile_backend.php",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function(res) {
                    if (res.toLowerCase().includes("success")) {
                        showToast("Profile image context row updated successfully.");
                        setTimeout(() => location.reload(), 800);
                    } else showToast(res, "error");
                },
                error: function(xhr) {
                    showToast("Upload request failed: " + (xhr.responseText || "Unknown error"), "error");
                }
            });
        });

        /* STRUCTURAL ROW SAVES EXECUTION BLOCK */
        $("#updateInfoForm").submit(function(e) {
            e.preventDefault();
            $.post("/SUPPLY_OFFICE_first/update_profile_backend.php", $(this).serialize(), function(res) {
                if (res.toLowerCase().includes("success")) showToast("Registration indices saved securely.");
                else showToast(res, "error");
            });
        });

        /* AUTH SECTOR GATE CHALLENGE STEP 1 */
        $("#verifyPasswordForm").submit(function(e) {
            e.preventDefault();
            const curr = $("input[name='current_password']").val();
            $.post("/SUPPLY_OFFICE_first/verify_password.php", { current_password: curr }, function(res) {
                if (res === "OK") {
                    lastVerifiedPassword = curr;
                    showToast("Identity target structure checked and cleared.");
                    $("#directChangeSection").slideDown();
                    $("#otpChoiceSection").hide();
                    $("#otpSection").hide();
                } else {
                    showToast("Authentication sequence failed.", "error");
                    $("#directChangeSection").hide();
                    $("#otpChoiceSection").slideDown();
                }
            });
        });

        /* SECURED TRANSACTION DISPATCH TIMELINES FOR OUTBOUND SMS/EMAIL OTP */
        $("#btnOtpEmail, #btnResendOtp").click(function() {
            if ($(this).attr('id') === 'btnResendOtp' && $("#btnResendOtp").prop("disabled")) return;
            $.post("/SUPPLY_OFFICE_first/send_otp.php", { email: "<?= htmlspecialchars($userdata['email'], ENT_QUOTES) ?>" }, function(res) {
                if (res === "OTP_SENT") {
                    showToast("Verification tracking token dispatched successfully.");
                    $("#otpSection").slideDown();
                    $("#btnResendOtp").show();
                    otpAttempts = 0;
                    $("#otpInput").prop("disabled", false).val("");
                    $("#btnSubmitOtp").prop("disabled", false).show();
                    $("#otpError").hide();
                    otpExpireAt = Date.now() + (5 * 60 * 1000);
                    startOtpExpiryCountdown();
                    startResendCooldown(30);
                } else {
                    showToast(res, "error");
                }
            });
        });

        /* SYMMETRIC DIRECT KEY CONTROL FORM ROUTING CONTEXT */
        $("#directChangeForm").submit(function(e) {
            e.preventDefault();
            const newP = $("#directNewPassword").val();
            const confP = $("#directConfirmPassword").val();
            if (!newP || !confP) return showToast("Target values cannot be parsed.", "error");
            if (newP !== confP) return showToast("Confirm dynamic synchronization values match fields.", "error");

            pendingChangeMode = "direct";
            pendingChangeData = {
                mode: "direct",
                current_password: lastVerifiedPassword,
                new_password: newP,
                confirm_password: confP
            };
            confirmModal.show();
        });

        /* SECURE OTP PACKET HANDSHAKE EVALUATION STEP */
        $("#btnSubmitOtp").click(function() {
            const otp = $("#otpInput").val().trim();
            if (!otp) {
                $("#otpError").text("Input field buffer context contains empty string allocations.").show();
                $("#otpInput").addClass("shake");
                setTimeout(() => $("#otpInput").removeClass("shake"), 300);
                return;
            }
            if (otpAttempts >= maxOtpAttempts) {
                $("#otpError").text("Security structural lockout has thrown exceptions due to attempt quantities.").show();
                $("#btnSubmitOtp").prop("disabled", true);
                return;
            }

            $.post("verify_otp.php", { otp: otp }, function(res) {
                if (res === "OTP_OK") {
                    $("#otpError").hide();
                    $("#otpInput").prop("disabled", true);
                    $("#btnSubmitOtp").hide();
                    $("#newPasswordArea").slideDown();
                    showToast("Token parameter verification verified.");
                } else {
                    otpAttempts++;
                    $("#otpError").text("The code entry value mismatches token property vectors. Attempts logged: " + otpAttempts).show();
                    $("#otpInput").addClass("shake");
                    setTimeout(() => $("#otpInput").removeClass("shake"), 300);
                    if (otpAttempts >= maxOtpAttempts) {
                        $("#otpError").text("System verification properties locked down due to attempt overflow vectors.").show();
                        $("#btnSubmitOtp").prop("disabled", true);
                    }
                }
            });
        });

        /* TRANSACTION POST-OTP OVERWRITE ENGINE PIPELINE SUBMIT */
        $("#btnSubmitNewPassword").click(function() {
            const newP = $("#otpNewPassword").val();
            const confP = $("#otpConfirmPassword").val();
            if (!newP || !confP) return showToast("Fields are missing parameters.", "error");
            if (newP !== confP) return showToast("Mismatch across validation inputs detected.", "error");

            pendingChangeMode = "otp";
            pendingChangeData = {
                mode: "otp",
                otp: $("#otpInput").val().trim(),
                new_password: newP,
                confirm_password: confP,
                contact: "<?= htmlspecialchars($userdata['contact'], ENT_QUOTES) ?>",
                dob: "<?= htmlspecialchars($userdata['dob'], ENT_QUOTES) ?>",
                address: "<?= htmlspecialchars($userdata['address'], ENT_QUOTES) ?>"
            };
            confirmModal.show();
        });

        /* DIALOG COMMIT ROUTINES TRANSACTION OVERLAY EXECUTION LAYER */
        $("#confirmChangeBtn").click(function() {
            $.post("change_password_backend.php", pendingChangeData, function(res) {
                if (res === "PASSWORD_CHANGED") {
                    showToast("Authentication security variables overwritten.");
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(res, "error");
                }
            });
            pendingChangeMode = null;
            pendingChangeData = {};
            confirmModal.hide();
        });

        /* EYE ICON VIEW STATE DOM ACCESSIBILITY CONTROLS */
        $(document).on("click", ".toggle-pass", function() {
            let target = $(this).data("target");
            let input = $(target);
            let icon = $(this).find('i');

            if (input.attr("type") === "password") {
                input.attr("type", "text");
                icon.removeClass("fa-eye").addClass("fa-eye-slash");
            } else {
                input.attr("type", "password");
                icon.removeClass("fa-eye-slash").addClass("fa-eye");
            }
        });
    </script>
</body>

</html>
