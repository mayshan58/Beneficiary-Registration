<?php
/**
 * Aid Programs Directory
 * Role-accessible: All authenticated roles
 */

$pageTitle = "Aid Programs";
$activeNav = "programs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// Filters
$search   = sanitize($_GET['q'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$category = sanitize($_GET['category'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.program_code LIKE ? OR n.name LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if (!empty($status)) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $where[] = "p.category = ?";
    $params[] = $category;
}

$whereClause = implode(" AND ", $where);

$query = "
    SELECT p.*, n.name as ngo_name, n.acronym as ngo_acronym,
           (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id = p.id) as enrolled_count,
           (SELECT COUNT(*) FROM aid_distributions ad WHERE ad.program_id = p.id) as distribution_count
    FROM aid_programs p
    JOIN ngos n ON p.ngo_id = n.id
    WHERE {$whereClause}
    ORDER BY p.id DESC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$programs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-basket3-fill text-primary me-2"></i>Humanitarian Aid Programs</h4>
        <p class="text-muted small mb-0">Food assistance, cash transfers, emergency WASH, and nutrition programs across South Sudan.</p>
    </div>

    <div class="d-flex gap-2">
        <?php if (has_role(['admin', 'registration_officer'])): ?>
            <a href="enroll.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-person-check me-1"></i> Enroll Beneficiary
            </a>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
            <a href="add.php" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> Create Aid Program
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by program code, name, or NGO..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Assistance Sectors</option>
                    <?php foreach (AID_CATEGORIES as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Planned" <?= $status === 'Planned' ? 'selected' : '' ?>>Planned</option>
                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Suspended" <?= $status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Programs Grid -->
<div class="row g-3">
    <?php if (empty($programs)): ?>
        <div class="col-12">
            <div class="card p-5 text-center text-muted shadow-sm border-0">
                <i class="bi bi-basket fs-1 text-secondary mb-2"></i>
                <h5 class="fw-bold">No Aid Programs Found</h5>
                <p class="small text-muted mb-0">No programs matched the current query.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($programs as $prog): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm border-0 d-flex flex-column">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-primary border"><code><?= e($prog['program_code']) ?></code></span>
                        <?php
                            $badgeStatus = match($prog['status']) {
                                'Active' => 'bg-success',
                                'Planned' => 'bg-info text-dark',
                                'Completed' => 'bg-secondary',
                                default => 'bg-danger'
                            };
                        ?>
                        <span class="badge <?= $badgeStatus ?>"><?= e($prog['status']) ?></span>
                    </div>

                    <div class="card-body p-3 flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-secondary me-2" style="font-size: 0.72rem;"><?= e($prog['category']) ?></span>
                            <small class="text-muted fw-bold"><?= e($prog['ngo_acronym']) ?></small>
                        </div>

                        <h5 class="fw-bold mb-2">
                            <a href="view.php?id=<?= $prog['id'] ?>" class="text-dark text-decoration-none">
                                <?= e($prog['name']) ?>
                            </a>
                        </h5>

                        <div class="small text-muted mb-3 text-truncate" title="<?= e($prog['item_description']) ?>">
                            <i class="bi bi-box-seam me-1 text-primary"></i> <strong>Ration:</strong> <?= e($prog['item_description']) ?>
                        </div>

                        <div class="p-2 bg-light rounded border mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Target Area:</span>
                                <strong><?= e($prog['target_county']) ?>, <?= e($prog['target_state']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Target Goal:</span>
                                <span><?= number_format($prog['target_beneficiaries']) ?> Beneficiaries</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Enrolled:</span>
                                <strong class="text-success"><?= number_format($prog['enrolled_count']) ?> Enrolled</strong>
                            </div>
                        </div>

                        <!-- Mini progress bar -->
                        <?php 
                            $percent = $prog['target_beneficiaries'] > 0 ? min(100, round(($prog['enrolled_count'] / $prog['target_beneficiaries']) * 100)) : 0;
                        ?>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Enrollment Coverage</span>
                            <span><?= $percent ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= $percent ?>%;"></div>
                        </div>
                    </div>

                    <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i> <?= format_date($prog['start_date']) ?> - <?= format_date($prog['end_date']) ?>
                        </small>

                        <div class="btn-group btn-group-sm">
                            <a href="view.php?id=<?= $prog['id'] ?>" class="btn btn-outline-primary" title="View Program">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <?php if (has_role('admin')): ?>
                                <a href="edit.php?id=<?= $prog['id'] ?>" class="btn btn-outline-secondary" title="Edit Program">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

