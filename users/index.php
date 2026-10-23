<?php
/**
 * User Accounts & Roles Management
 * Role-restricted: Administrator
 */

$pageTitle = "System Users";
$activeNav = "users";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

// Handle Status Toggle (Activate / Deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        // Prevent admin from deactivating themselves
        if ($userId === $currentUser['id']) {
            flash_set('danger', 'You cannot deactivate your own administrative account.');
            header('Location: index.php');
            exit();
        }

        $stmt = $db->prepare("SELECT id, username, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $newStatus = $targetUser['status'] === 'active' ? 'inactive' : 'active';
            $update = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $userId]);

            log_audit(
                'USER_STATUS_TOGGLED',
                'Users',
                (string)$userId,
                "User '{$targetUser['username']}' status set to '{$newStatus}'."
            );

            flash_set('success', "User '{$targetUser['username']}' is now {$newStatus}.");
            header('Location: index.php');
            exit();
        }
    }
}

// Filters
$search = sanitize($_GET['q'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if (!empty($roleFilter)) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $where[] = "u.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(" AND ", $where);

$query = "
    SELECT u.*, n.name as ngo_name, n.acronym as ngo_acronym
    FROM users u
    LEFT JOIN ngos n ON u.ngo_id = n.id
    WHERE {$whereClause}
    ORDER BY u.id ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>System User Accounts &amp; RBAC</h4>
        <p class="text-muted small mb-0">Manage authorized staff, assign humanitarian roles, and monitor authentication activity.</p>
    </div>

    <a href="add.php" class="btn btn-primary btn-sm fw-semibold">
        <i class="bi bi-person-plus-fill me-1"></i> Add New User
    </a>
</div>

<!-- Filter Bar -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, username, or email..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <?php foreach (ROLES as $rk => $rv): ?>
                        <option value="<?= e($rk) ?>" <?= $roleFilter === $rk ? 'selected' : '' ?>><?= e($rv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User &amp; Username</th>
                        <th>Email &amp; Phone</th>
                        <th>Assigned Role</th>
                        <th>Affiliated NGO</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No users found matching search criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                    <small class="text-muted">@<?= e($u['username']) ?></small>
                                </td>

                                <td>
                                    <div class="small"><?= e($u['email']) ?></div>
                                    <small class="text-muted"><?= e($u['phone'] ?: 'No Phone') ?></small>
                                </td>

                                <td>
                                    <?php
                                        $roleBadge = match($u['role']) {
                                            'admin' => 'bg-danger',
                                            'registration_officer' => 'bg-success',
                                            'distribution_officer' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $roleBadge ?>"><?= e(ROLES[$u['role']] ?? $u['role']) ?></span>
                                </td>

                                <td>
                                    <?php if ($u['ngo_name']): ?>
                                        <span class="fw-semibold small"><?= e($u['ngo_name']) ?></span>
                                        <small class="text-muted d-block"><?= e($u['ngo_acronym']) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Super Admin / Core</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <small class="text-muted"><?= format_datetime($u['last_login']) ?></small>
                                </td>

                                <td>
                                    <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst(e($u['status'])) ?>
                                    </span>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-secondary" title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <?php if ($u['id'] !== $currentUser['id']): ?>
                                            <form method="POST" action="index.php" class="d-inline" onsubmit="return confirm('Toggle status for <?= e($u['username']) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

