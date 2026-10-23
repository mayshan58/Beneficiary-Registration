<?php
/**
 * Create New System User
 * Role-restricted: Administrator
 */

$pageTitle = "Add New User";
$activeNav = "users";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];
$preselectedNgo = (int)($_GET['ngo_id'] ?? 0);

$formData = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
    'role'      => 'registration_officer',
    'ngo_id'    => $preselectedNgo ?: ($ngos[0]['id'] ?? null),
    'phone'     => '',
    'status'    => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $formData['full_name'] = sanitize($_POST['full_name'] ?? '');
        $formData['username']  = strtolower(sanitize($_POST['username'] ?? ''));
        $formData['email']     = sanitize($_POST['email'] ?? '');
        $password              = $_POST['password'] ?? '';
        $confirmPassword       = $_POST['confirm_password'] ?? '';
        $formData['role']      = sanitize($_POST['role'] ?? '');
        $formData['ngo_id']    = !empty($_POST['ngo_id']) ? (int)$_POST['ngo_id'] : null;
        $formData['phone']     = sanitize($_POST['phone'] ?? '');
        $formData['status']    = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($formData['full_name'])) $errors[] = "Full Name is required.";
        if (empty($formData['username']) || !preg_match('/^[a-z0-9_]{3,30}$/', $formData['username'])) {
            $errors[] = "Username must be 3-30 characters (letters, numbers, underscore only).";
        }
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid Email address is required.";
        }
        if (empty($formData['role']) || !array_key_exists($formData['role'], ROLES)) {
            $errors[] = "A valid User Role is required.";
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($password !== $confirmPassword) {
            $errors[] = "Password and confirmation do not match.";
        }

        // Uniqueness checks
        if (empty($errors)) {
            $uCheck = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $uCheck->execute([$formData['username'], $formData['email']]);
            if ($uCheck->fetch()) {
                $errors[] = "A user with this username or email already exists.";
            }
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $insert = $db->prepare("
                INSERT INTO users (ngo_id, full_name, email, username, password, role, phone, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([
                $formData['ngo_id'],
                $formData['full_name'],
                $formData['email'],
                $formData['username'],
                $hashedPassword,
                $formData['role'],
                $formData['phone'],
                $formData['status']
            ]);

            $newId = (int)$db->lastInsertId();
            log_audit('USER_CREATED', 'Users', (string)$newId, "Created user '{$formData['username']}' with role '{$formData['role']}'");

            flash_set('success', "User '{$formData['username']}' created successfully.");
            header('Location: index.php');
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-plus-fill text-primary me-2"></i>Create Authorized System User</h4>
                <p class="text-muted small mb-0">Provision credentials with role-based access control (RBAC).</p>
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

        <form method="POST" action="add.php" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?= e($formData['full_name']) ?>" required placeholder="e.g. Samuel Garang Ladu">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Username (Login ID) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" value="<?= e($formData['username']) ?>" required placeholder="e.g. samuel_garang">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= e($formData['email']) ?>" required placeholder="e.g. samuel@wfp.org">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Contact Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?= e($formData['phone']) ?>" placeholder="e.g. +211 920 000 000">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Role &amp; Permissions <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php foreach (ROLES as $key => $title): ?>
                                <option value="<?= e($key) ?>" <?= $formData['role'] === $key ? 'selected' : '' ?>><?= e($title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Assigned Humanitarian NGO</label>
                        <select name="ngo_id" class="form-select">
                            <option value="">-- None (Super Admin / Independent) --</option>
                            <?php foreach ($ngos as $n): ?>
                                <option value="<?= $n['id'] ?>" <?= $formData['ngo_id'] == $n['id'] ? 'selected' : '' ?>>
                                    <?= e($n['acronym']) ?> - <?= e($n['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required placeholder="Minimum 6 characters">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required placeholder="Repeat password">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Create User Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

