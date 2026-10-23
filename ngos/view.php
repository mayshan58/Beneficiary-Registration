<?php
/**
 * Detailed NGO Partner Profile
 * Role-restricted: Administrator
 */

$pageTitle = "NGO Profile";
$activeNav = "ngos";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid NGO ID.');
    header('Location: index.php');
    exit();
}

// Fetch NGO details
$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$id]);
$ngo = $stmt->fetch();

if (!$ngo) {
    flash_set('danger', 'NGO record not found.');
    header('Location: index.php');
    exit();
}

// Fetch Associated Aid Programs
$progStmt = $db->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id = p.id) as enrolled_count,
           (SELECT COUNT(*) FROM aid_distributions ad WHERE ad.program_id = p.id) as dist_count
    FROM aid_programs p
    WHERE p.ngo_id = ?
    ORDER BY p.id DESC
");
$progStmt->execute([$id]);
$programs = $progStmt->fetchAll();

// Fetch Associated Staff Users
$userStmt = $db->prepare("SELECT * FROM users WHERE ngo_id = ? ORDER BY full_name ASC");
$userStmt->execute([$id]);
$staff = $userStmt->fetchAll();

// Count registered beneficiaries
$benCountStmt = $db->prepare("SELECT COUNT(*) FROM beneficiaries WHERE ngo_id = ?");
$benCountStmt->execute([$id]);
$beneficiaryCount = (int)$benCountStmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left me-1"></i> NGOs List
        </a>
        <div>
            <h4 class="fw-bold mb-0"><?= e($ngo['name']) ?> (<?= e($ngo['acronym']) ?>)</h4>
            <span class="badge <?= $ngo['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                <?= ucfirst(e($ngo['status'])) ?>
            </span>
            <span class="badge bg-light text-dark border ms-1">RRC: <?= e($ngo['registration_number']) ?></span>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="edit.php?id=<?= $ngo['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit NGO
        </a>
        <a href="<?= BASE_URL ?>programs/add.php?ngo_id=<?= $ngo['id'] ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Aid Program
        </a>
        <a href="<?= BASE_URL ?>users/add.php?ngo_id=<?= $ngo['id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i> Add NGO Staff
        </a>
    </div>
</div>

<!-- Row 1: Profile Summary & Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold text-primary"><i class="bi bi-building me-2"></i>Official Partner Details</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Contact Focal Person</small>
                        <strong class="text-dark fs-6"><?= e($ngo['contact_person']) ?></strong>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Official Email</small>
                        <span class="text-dark fw-semibold"><?= e($ngo['email']) ?></span>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Phone Contact</small>
                        <span class="text-dark fw-semibold"><?= e($ngo['phone']) ?></span>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Headquarters / Country Office</small>
                        <span class="text-dark fw-semibold"><?= e($ngo['headquarters']) ?></span>
                    </div>

                    <div class="col-12">
                        <small class="text-muted d-block">Office Address in South Sudan</small>
                        <span class="text-dark"><?= nl2br(e($ngo['office_address'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold text-secondary"><i class="bi bi-graph-up me-2"></i>Operational Summary</span>
            </div>
            <div class="card-body p-3 d-flex flex-column justify-content-around">
                <div class="d-flex align-items-center p-2 rounded bg-light border mb-2">
                    <div class="rounded-circle bg-primary text-white p-3 me-3">
                        <i class="bi bi-basket3 fs-4"></i>
                    </div>
                    <div>
                        <div class="display-6 fw-bold text-dark"><?= count($programs) ?></div>
                        <small class="text-muted">Humanitarian Programs</small>
                    </div>
                </div>

                <div class="d-flex align-items-center p-2 rounded bg-light border mb-2">
                    <div class="rounded-circle bg-success text-white p-3 me-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <div class="display-6 fw-bold text-dark"><?= number_format($beneficiaryCount) ?></div>
                        <small class="text-muted">Registered Beneficiaries</small>
                    </div>
                </div>

                <div class="d-flex align-items-center p-2 rounded bg-light border">
                    <div class="rounded-circle bg-info text-white p-3 me-3">
                        <i class="bi bi-person-badge fs-4"></i>
                    </div>
                    <div>
                        <div class="display-6 fw-bold text-dark"><?= count($staff) ?></div>
                        <small class="text-muted">Assigned System Staff</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Programs and Staff Tabs/Cards -->
<div class="row g-3">
    <!-- Associated Programs -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-basket3-fill me-2"></i>Aid Programs Managed by <?= e($ngo['acronym']) ?></span>
                <a href="<?= BASE_URL ?>programs/add.php?ngo_id=<?= $ngo['id'] ?>" class="btn btn-outline-success btn-sm">+ Add Program</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Program Name</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Target</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($programs)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No programs created for this NGO yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($programs as $prog): ?>
                                    <tr>
                                        <td><code><?= e($prog['program_code']) ?></code></td>
                                        <td class="fw-semibold">
                                            <a href="<?= BASE_URL ?>programs/view.php?id=<?= $prog['id'] ?>" class="text-decoration-none">
                                                <?= e($prog['name']) ?>
                                            </a>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($prog['category']) ?></span></td>
                                        <td><small><?= e($prog['target_county']) ?>, <?= e($prog['target_state']) ?></small></td>
                                        <td><?= number_format($prog['target_beneficiaries']) ?></td>
                                        <td><span class="badge bg-success"><?= e($prog['status']) ?></span></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>programs/view.php?id=<?= $prog['id'] ?>" class="btn btn-sm btn-light border">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Staff Users -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-people-fill me-2"></i>Authorized NGO Staff</span>
                <a href="<?= BASE_URL ?>users/add.php?ngo_id=<?= $ngo['id'] ?>" class="btn btn-outline-primary btn-sm">+ Add User</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($staff)): ?>
                        <div class="p-4 text-center text-muted small">No staff accounts assigned to this NGO yet.</div>
                    <?php else: ?>
                        <?php foreach ($staff as $u): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                    <small class="text-muted d-block"><?= e($u['email']) ?></small>
                                    <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= e(ROLES[$u['role']] ?? $u['role']) ?></span>
                                </div>
                                <a href="<?= BASE_URL ?>users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-light border">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

