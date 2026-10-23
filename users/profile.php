<?php
/**
 * User Profile & Password Security
 */

$pageTitle = "My Profile";
$activeNav = "";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone    = sanitize($_POST['phone'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($fullName)) $errors[] = "Full Name is required.";

        // Password change logic
        $passwordChanged = false;
        if (!empty($newPass)) {
            if (empty($currentPass) || !password_verify($currentPass, $user['password'])) {
                $errors[] = "Current password was incorrect.";
            } elseif (strlen($newPass) < 6) {
                $errors[] = "New password must be at least 6 characters.";
            } elseif ($newPass !== $confirmPass) {
                $errors[] = "New password and confirmation do not match.";
            } else {
                $passwordChanged = true;
            }
        }

        if (empty($errors)) {
            if ($passwordChanged) {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $update = $db->prepare("UPDATE users SET full_name = ?, phone = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$fullName, $phone, $newHash, $user['id']]);
            } else {
                $update = $db->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$fullName, $phone, $user['id']]);
            }

            $_SESSION['full_name'] = $fullName;
            log_audit('PROFILE_UPDATED', 'Users', (string)$user['id'], "User updated their personal profile / password.");

            flash_set('success', "Profile details successfully updated.");
            header('Location: profile.php');
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <h4 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary me-2"></i>My Profile &amp; Security</h4>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px; font-size: 1.5rem; font-weight: bold;">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><?= e($user['full_name']) ?></h5>
                        <div class="small text-muted">@<?= e($user['username']) ?> &bull; <?= e(ROLES[$user['role']] ?? $user['role']) ?></div>
                        <span class="badge bg-light text-primary border mt-1"><?= e($currentUser['ngo_name']) ?></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Email Address (Read-only)</label>
                    <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Contact Phone Number</label>
                    <input type="text" class="form-control" name="phone" value="<?= e($user['phone']) ?>">
                </div>

                <h6 class="fw-bold text-dark pt-3 border-top mb-3">
                    <i class="bi bi-key-fill text-warning me-1"></i> Change Account Password
                </h6>
                <p class="small text-muted">Leave password fields blank if you do not wish to change your current password.</p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Current Password</label>
                    <input type="password" class="form-control" name="current_password" placeholder="Enter current password to verify">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Minimum 6 characters">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Repeat new password">
                </div>
            </div>

            <div class="card-footer bg-light p-3 text-end">
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

