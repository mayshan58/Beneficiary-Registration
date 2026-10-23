<?php
/**
 * NGO Management Module
 * Role-restricted: Administrator
 */

$pageTitle = "Humanitarian NGO Partners";
$activeNav = "ngos";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

// Handle Status Toggle Action (Activate / Deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $ngoId = (int)($_POST['ngo_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, status FROM ngos WHERE id = ?");
        $stmt->execute([$ngoId]);
        $ngo = $stmt->fetch();

        if ($ngo) {
            $newStatus = $ngo['status'] === 'active' ? 'inactive' : 'active';
            $update = $db->prepare("UPDATE ngos SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $ngoId]);

            log_audit(
                'NGO_STATUS_TOGGLED',
                'NGOs',
                (string)$ngoId,
                "NGO '{$ngo['name']}' status changed to '{$newStatus}'."
            );

            flash_set('success', "NGO '{$ngo['name']}' is now {$newStatus}.");
            header('Location: index.php');
            exit();
        }
    }
}

// Search and Filter
$search = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(n.name LIKE ? OR n.acronym LIKE ? OR n.registration_number LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if (!empty($statusFilter)) {
    $where[] = "n.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(" AND ", $where);

$query = "
    SELECT n.*,
           (SELECT COUNT(*) FROM aid_programs p WHERE p.ngo_id = n.id) as programs_count,
           (SELECT COUNT(*) FROM users u WHERE u.ngo_id = n.id) as staff_count,
           (SELECT COUNT(*) FROM beneficiaries b WHERE b.ngo_id = n.id) as beneficiaries_count
    FROM ngos n
    WHERE {$whereClause}
    ORDER BY n.name ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$ngos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-building-fill text-info me-2"></i>Humanitarian NGO Partners</h4>
        <p class="text-muted small mb-0">Manage registered humanitarian agencies, Relief &amp; Rehabilitation Commission (RRC) licenses, and programs.</p>
    </div>

    <a href="add.php" class="btn btn-primary btn-sm fw-semibold">
        <i class="bi bi-plus-circle-fill me-1"></i> Register New NGO
    </a>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, acronym, or registration number..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm px-3">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>NGO &amp; Acronym</th>
                        <th>Registration No</th>
                        <th>Contact Person</th>
                        <th>Phone &amp; Email</th>
                        <th>HQ / Location</th>
                        <th class="text-center">Programs</th>
                        <th class="text-center">Staff</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ngos)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-building-exclamation fs-1 d-block mb-2 text-secondary"></i>
                                No NGO records found matching criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ngos as $ngo): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $ngo['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= e($ngo['name']) ?>
                                    </a>
                                    <span class="badge bg-light text-primary border ms-1"><?= e($ngo['acronym']) ?></span>
                                </td>

                                <td><code><?= e($ngo['registration_number']) ?></code></td>
                                <td class="fw-semibold small"><?= e($ngo['contact_person']) ?></td>

                                <td>
                                    <div class="small"><?= e($ngo['phone']) ?></div>
                                    <small class="text-muted"><?= e($ngo['email']) ?></small>
                                </td>

                                <td class="small text-muted"><?= e($ngo['headquarters']) ?></td>

                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?= (int)$ngo['programs_count'] ?></span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill"><?= (int)$ngo['staff_count'] ?></span>
                                </td>

                                <td>
                                    <span class="badge <?= $ngo['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst(e($ngo['status'])) ?>
                                    </span>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $ngo['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <a href="edit.php?id=<?= $ngo['id'] ?>" class="btn btn-outline-secondary" title="Edit NGO">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form method="POST" action="index.php" class="d-inline" onsubmit="return confirm('Toggle status for <?= e($ngo['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="ngo_id" value="<?= $ngo['id'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $ngo['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $ngo['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>
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

