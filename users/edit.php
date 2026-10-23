<?php
/**
 * Edit User Account & Roles
 * Role-restricted: Administrator
 */

$pageTitle = "Edit User";
$activeNav = "users";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid user ID.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash_set('danger', 'User not found.');
    header('Location: index.php');
    exit();
}

$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$errors = [];
$formData = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $formData['full_name'] = sanitize($_POST['full_name'] ?? '');
        $formData['email']     = sanitize($_POST['email'] ?? '');
        $formData['role']      = sanitize($_POST['role'] ?? '');
        $formData['ngo_id']    = !empty($_POST['ngo_id']) ? (int)$_POST['ngo_id'] : null;
        $formData['phone']     = sanitize($_POST['phone'] ?? '');
        $formData['status']    = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $newPassword           = $_POST['password'] ?? '';

        if (empty($formData['full_name'])) $errors[] = "Full Name is required.";
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid Email address is required.";
        }
        if (empty($formData['role']) || !array_key_exists($formData['role'], ROLES)) {
            $errors[] = "A valid User Role is required.";
        }

        // Prevent admin from deactivating themselves
        if ($id === $currentUser['id'] && $formData['status'] === 'inactive') {
            $errors[] = "You cannot deactivate your own account.";
        }

        // Email uniqueness
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$formData['email'], $id]);
            if ($chk->fetch()) {
                $errors[] = "Another user is already using this email address.";
            }
        }

        if (empty($errors)) {
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $errors[] = "New password must be at least 6 characters.";
                } else {
                    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                    $update = $db->prepare("
                        UPDATE users SET
                            full_name = ?, email = ?, role = ?, ngo_id = ?, phone = ?, status = ?, password = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update->execute([
                        $formData['full_name'], $formData['email'], $formData['role'], $formData['ngo_id'],
                        $formData['phone'], $formData['status'], $hashed, $id
                    ]);
                }
            } else {
                $update = $db->prepare("
                    UPDATE users SET
                        full_name = ?, email = ?, role = ?, ngo_id = ?, phone = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([
                    $formData['full_name'], $formData['email'], $formData['role'], $formData['ngo_id'],
                    $formData['phone'], $formData['status'], $id
                ]);
            }

            if (empty($errors)) {
                log_audit('USER_UPDATED', 'Users', (string)$id, "Updated user profile for '{$user['username']}'");

                flash_set('success', "User account successfully updated.");
                header('Location: index.php');
                exit();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Edit User Account</h4>
                <p class="text-muted small mb-0">Modify permissions and account settings for <strong>@<?= e($user['username']) ?></strong></p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Users
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= $user['id'] ?>" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Username (Fixed)</label>
                        <input type="text" class="form-control bg-light" value="<?= e($user['username']) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?= e($formData['full_name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= e($formData['email']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Contact Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?= e($formData['phone']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Assigned Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php foreach (ROLES as $key => $title): ?>
                                <option value="<?= e($key) ?>" <?= $formData['role'] === $key ? 'selected' : '' ?>><?= e($title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Assigned NGO</label>
                        <select name="ngo_id" class="form-select">
                            <option value="">-- None (Core Admin) --</option>
                            <?php foreach ($ngos as $n): ?>
                                <option value="<?= $n['id'] ?>" <?= $formData['ngo_id'] == $n['id'] ? 'selected' : '' ?>>
                                    <?= e($n['acronym']) ?> - <?= e($n['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Reset Password (Leave blank to keep unchanged)</label>
                        <input type="password" class="form-control" name="password" placeholder="New password if resetting">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

