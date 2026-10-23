<?php
/**
 * Detailed Aid Program View
 * Displays program details, enrolled beneficiaries list, and distribution log.
 */

$pageTitle = "Aid Program Profile";
$activeNav = "programs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid program ID.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("
    SELECT p.*, n.name as ngo_name, n.acronym as ngo_acronym, u.full_name as creator_name
    FROM aid_programs p
    JOIN ngos n ON p.ngo_id = n.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$program = $stmt->fetch();

if (!$program) {
    flash_set('danger', 'Aid program not found.');
    header('Location: index.php');
    exit();
}

// Fetch Enrolled Beneficiaries
$enrolledStmt = $db->prepare("
    SELECT pe.*, b.beneficiary_id, b.full_name, b.gender, b.state, b.county, b.verification_status, b.vulnerability_category,
           (SELECT COUNT(*) FROM aid_distributions ad WHERE ad.program_id = pe.program_id AND ad.beneficiary_id = b.id) as received_distributions
    FROM program_enrollments pe
    JOIN beneficiaries b ON pe.beneficiary_id = b.id
    WHERE pe.program_id = ?
    ORDER BY pe.id DESC
");
$enrolledStmt->execute([$id]);
$enrolledBeneficiaries = $enrolledStmt->fetchAll();

// Fetch Aid Distributions for this program
$distStmt = $db->prepare("
    SELECT d.*, b.beneficiary_id, b.full_name as beneficiary_name, u.full_name as officer_name
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE d.program_id = ?
    ORDER BY d.distribution_date DESC, d.id DESC
");
$distStmt->execute([$id]);
$distributions = $distStmt->fetchAll();

$enrolledCount = count($enrolledBeneficiaries);
$distCount = count($distributions);
$coveragePercent = $program['target_beneficiaries'] > 0 ? min(100, round(($enrolledCount / $program['target_beneficiaries']) * 100)) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left me-1"></i> Programs List
        </a>
        <div>
            <h4 class="fw-bold mb-0"><?= e($program['name']) ?></h4>
            <span class="badge bg-secondary"><?= e($program['program_code']) ?></span>
            <span class="badge bg-light text-primary border ms-1"><?= e($program['ngo_acronym']) ?></span>
            <span class="badge bg-success ms-1"><?= e($program['status']) ?></span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?php if (has_role(['admin', 'registration_officer'])): ?>
            <a href="enroll.php?program_id=<?= $program['id'] ?>" class="btn btn-success btn-sm">
                <i class="bi bi-person-plus me-1"></i> Enroll Beneficiary
            </a>
        <?php endif; ?>

        <?php if (has_role(['admin', 'distribution_officer'])): ?>
            <a href="<?= BASE_URL ?>distributions/record.php?program_id=<?= $program['id'] ?>" class="btn btn-warning btn-sm text-dark">
                <i class="bi bi-box-seam me-1"></i> Record Distribution
            </a>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
            <a href="edit.php?id=<?= $program['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Key Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-widget stat-primary">
            <div class="stat-title">Target Goal</div>
            <div class="stat-value"><?= number_format($program['target_beneficiaries']) ?></div>
            <div class="small text-muted mt-2">Target Beneficiaries</div>
            <div class="stat-icon"><i class="bi bi-bullseye"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="stat-widget stat-success">
            <div class="stat-title">Enrolled Beneficiaries</div>
            <div class="stat-value"><?= number_format($enrolledCount) ?></div>
            <div class="small text-muted mt-2"><?= $coveragePercent ?>% of target goal</div>
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="stat-widget stat-warning">
            <div class="stat-title">Aid Distributions</div>
            <div class="stat-value"><?= number_format($distCount) ?></div>
            <div class="small text-muted mt-2">Transactions issued</div>
            <div class="stat-icon"><i class="bi bi-box-seam-fill"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="stat-widget stat-purple">
            <div class="stat-title">Assistance Sector</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= e($program['category']) ?></div>
            <div class="small text-muted mt-2"><?= e($program['ngo_name']) ?></div>
            <div class="stat-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        </div>
    </div>
</div>

<!-- Program Specs Overview -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom">
        <span class="fw-bold text-primary"><i class="bi bi-card-checklist me-2"></i>Program Specifications &amp; Targeting</span>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">Relief Item / Ration Kit Description</small>
                <div class="fw-bold text-dark fs-6"><?= e($program['item_description']) ?></div>
                <div class="small text-muted mt-1">
                    Standard Quota: <strong><?= e($program['allocation_quota']) ?> <?= e($program['unit']) ?></strong> per eligible household
                </div>
            </div>

            <div class="col-md-3">
                <small class="text-muted d-block">Target Operational Area</small>
                <div class="fw-bold text-dark"><?= e($program['target_county']) ?>, <?= e($program['target_state']) ?></div>
                <small class="text-muted">Republic of South Sudan</small>
            </div>

            <div class="col-md-3">
                <small class="text-muted d-block">Operating Timeframe</small>
                <div class="fw-semibold text-dark"><?= format_date($program['start_date']) ?> &rarr; <?= format_date($program['end_date']) ?></div>
                <small class="text-muted">Created by: <?= e($program['creator_name'] ?? 'System') ?></small>
            </div>

            <?php if (!empty($program['description'])): ?>
                <div class="col-12 mt-3 pt-3 border-top">
                    <small class="text-muted d-block mb-1">Humanitarian Context &amp; Objective:</small>
                    <p class="mb-0 text-muted small"><?= nl2br(e($program['description'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Enrolled Beneficiaries Table -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary"><i class="bi bi-person-check-fill me-2"></i>Enrolled Beneficiaries Directory (<?= $enrolledCount ?>)</span>
        <?php if (has_role(['admin', 'registration_officer'])): ?>
            <a href="enroll.php?program_id=<?= $program['id'] ?>" class="btn btn-outline-success btn-sm">+ Enroll Beneficiary</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Beneficiary ID</th>
                        <th>Full Name &amp; Gender</th>
                        <th>Location</th>
                        <th>Vulnerability</th>
                        <th>Enrolled Date</th>
                        <th class="text-center">Aids Received</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrolledBeneficiaries)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No beneficiaries enrolled in this program yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($enrolledBeneficiaries as $ben): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>beneficiaries/view.php?id=<?= $ben['beneficiary_id'] ?>" class="fw-bold text-decoration-none">
                                        <?= e($ben['beneficiary_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ben['full_name']) ?></div>
                                    <small class="text-muted"><?= e($ben['gender']) ?></small>
                                </td>
                                <td>
                                    <div class="small"><?= e($ben['county']) ?></div>
                                    <small class="text-muted"><?= e($ben['state']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border small"><?= e($ben['vulnerability_category']) ?></span></td>
                                <td><?= format_date($ben['enrollment_date']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= (int)$ben['received_distributions'] ?> times</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= BASE_URL ?>distributions/record.php?program_id=<?= $program['id'] ?>&beneficiary_id=<?= $ben['beneficiary_id'] ?>" class="btn btn-sm btn-outline-warning text-dark" title="Issue Aid Distribution">
                                        <i class="bi bi-box-seam me-1"></i> Issue Aid
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

<!-- Distribution History for this Program -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-success"><i class="bi bi-clock-history me-2"></i>Aid Distribution Logs (<?= $distCount ?>)</span>
        <?php if (has_role(['admin', 'distribution_officer'])): ?>
            <a href="<?= BASE_URL ?>distributions/record.php?program_id=<?= $program['id'] ?>" class="btn btn-outline-warning text-dark btn-sm">+ Record Distribution</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Beneficiary</th>
                        <th>Quantity &amp; Items</th>
                        <th>Distribution Point</th>
                        <th>Officer</th>
                        <th>Date</th>
                        <th class="text-end pe-3">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($distributions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No distributions issued under this program yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($distributions as $dst): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= e($dst['receipt_no']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($dst['beneficiary_name']) ?></div>
                                    <small class="text-muted"><?= e($dst['beneficiary_id']) ?></small>
                                </td>
                                <td>
                                    <strong><?= e($dst['quantity']) ?> <?= e($dst['unit']) ?></strong>
                                    <small class="d-block text-muted text-truncate" style="max-width: 200px;"><?= e($dst['item_distributed']) ?></small>
                                </td>
                                <td class="small text-muted"><?= e($dst['distribution_point']) ?></td>
                                <td class="small fw-semibold"><?= e($dst['officer_name'] ?? 'Officer') ?></td>
                                <td><?= format_date($dst['distribution_date']) ?></td>
                                <td class="text-end pe-3">
                                    <a href="<?= BASE_URL ?>distributions/receipt.php?id=<?= $dst['id'] ?>" target="_blank" class="btn btn-sm btn-light border">
                                        <i class="bi bi-printer"></i>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

