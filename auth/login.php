<?php
/**
 * User Authentication / Login Page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($submittedToken)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $usernameVal = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usernameVal) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT u.*, n.name as ngo_title, n.acronym as ngo_code
                FROM users u
                LEFT JOIN ngos n ON u.ngo_id = n.id
                WHERE (u.username = ? OR u.email = ?)
                LIMIT 1
            ");
            $stmt->execute([$usernameVal, $usernameVal]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact the system administrator.';
                } else {
                    // Successful authentication
                    login_user($user);
                    flash_set('success', "Welcome back, {$user['full_name']}! You have successfully signed in.");
                    
                    $redirectUrl = $_SESSION['redirect_after_login'] ?? BASE_URL . 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirectUrl);
                    exit();
                }
            } else {
                $error = 'Invalid credentials. Please verify your username and password.';
                log_audit('FAILED_LOGIN_ATTEMPT', 'Authentication', null, "Failed login attempt for identifier: '{$usernameVal}'");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f3d64 0%, #082136 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
        }
        .login-card {
            max-width: 460px;
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .login-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 28px 24px 20px;
            text-align: center;
        }
        .login-body {
            padding: 28px 26px 20px;
        }
        .demo-pill {
            cursor: pointer;
            transition: all 0.2s;
        }
        .demo-pill:hover {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width: 56px; height: 56px;">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Humanitarian Portal</h4>
        <p class="text-muted small mb-0">NGOs Beneficiary Registration System &bull; South Sudan</p>
    </div>

    <div class="login-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center small py-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php $flash = flash_get(); foreach ($flash as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> small py-2">
                <?= e($f['message']) ?>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small text-secondary">Username or Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($usernameVal) ?>" required autofocus placeholder="Enter your username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small text-secondary">Account Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Secure Sign In
            </button>
        </form>

        <!-- Project Demo Credentials Card for Easy Testing -->
        <div class="mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">Demo Project Credentials</small>
                <span class="badge bg-light text-secondary border">Password: Password@123</span>
            </div>
            
            <div class="d-grid gap-2">
                <div class="demo-pill p-2 rounded border bg-light d-flex justify-content-between align-items-center small" onclick="fillCreds('admin')">
                    <div>
                        <strong class="text-dark">admin</strong> 
                        <span class="badge bg-danger ms-1">Administrator</span>
                    </div>
                    <span class="text-primary small">Click to autofill &rarr;</span>
                </div>

                <div class="demo-pill p-2 rounded border bg-light d-flex justify-content-between align-items-center small" onclick="fillCreds('regofficer')">
                    <div>
                        <strong class="text-dark">regofficer</strong> 
                        <span class="badge bg-success ms-1">Registration Officer</span>
                    </div>
                    <span class="text-primary small">Click to autofill &rarr;</span>
                </div>

                <div class="demo-pill p-2 rounded border bg-light d-flex justify-content-between align-items-center small" onclick="fillCreds('distofficer')">
                    <div>
                        <strong class="text-dark">distofficer</strong> 
                        <span class="badge bg-primary ms-1">Distribution Officer</span>
                    </div>
                    <span class="text-primary small">Click to autofill &rarr;</span>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?= BASE_URL ?>setup.php" class="text-decoration-none small text-muted">
                <i class="bi bi-database-check me-1"></i> Database Setup &amp; Reset Utility
            </a>
        </div>
    </div>
</div>

<script>
function fillCreds(username) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = 'Password@123';
}

document.getElementById('togglePasswordBtn').addEventListener('click', function () {
    const pwInput = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (pwInput.type === 'password') {
        pwInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        pwInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>

</body>
</html>

