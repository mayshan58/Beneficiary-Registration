<?php
/**
 * Beneficiary Detailed Profile View
 * Comprehensive profile, verification controls, program enrollments, and distribution history.
 */

$pageTitle = "Beneficiary Profile";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid beneficiary ID specified.');
    header('Location: index.php');
    exit();
}

// Fetch Beneficiary Profile
$stmt = $db->prepare("
    SELECT b.*, 
           n.name as ngo_name, n.acronym as ngo_acronym,
           reg_user.full_name as registered_by_name,
           ver_user.full_name as verified_by_name
    FROM beneficiaries b
    LEFT JOIN ngos n ON b.ngo_id = n.id
    LEFT JOIN users reg_user ON b.registered_by = reg_user.id
    LEFT JOIN users ver_user ON b.verified_by = ver_user.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$beneficiary = $stmt->fetch();

if (!$beneficiary) {
    flash_set('danger', 'Beneficiary record not found.');
    header('Location: index.php');
    exit();
}

// Handle Verification Update Submission (Admin or Registration Officer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_verification') {
    require_role(['admin', 'registration_officer']);
    
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $newStatus = sanitize($_POST['verification_status'] ?? 'Pending');
        $verNotes  = sanitize($_POST['verification_notes'] ?? '');

        if (in_array($newStatus, ['Pending', 'Verified', 'Requires Correction', 'Rejected'])) {
            $updateStmt = $db->prepare("
                UPDATE beneficiaries 
                SET verification_status = ?, verified_by = ?, verified_at = NOW(), verification_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newStatus, $currentUser['id'], $verNotes, $id]);

            log_audit(
                'BENEFICIARY_VERIFIED',
                'Verification',
                (string)$id,
                "Status for {$beneficiary['full_name']} changed to '{$newStatus}'. Notes: {$verNotes}"
            );

            flash_set('success', "Verification status successfully updated to '{$newStatus}'.");
            header("Location: view.php?id=" . $id);
            exit();
        }
    }
}

// Fetch Program Enrollments
$enrollStmt = $db->prepare("
    SELECT pe.*, p.name as program_name, p.program_code, p.category, p.status as program_status,
           u.full_name as enrolled_by_name
    FROM program_enrollments pe
    JOIN aid_programs p ON pe.program_id = p.id
    LEFT JOIN users u ON pe.enrolled_by = u.id
    WHERE pe.beneficiary_id = ?
    ORDER BY pe.id DESC
");
$enrollStmt->execute([$id]);
$enrollments = $enrollStmt->fetchAll();

// Fetch Aid Distribution Records
$distStmt = $db->prepare("
    SELECT d.*, p.name as program_name, p.program_code,
           u.full_name as distributed_by_name
    FROM aid_distributions d
    JOIN aid_programs p ON d.program_id = p.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE d.beneficiary_id = ?
    ORDER BY d.distribution_date DESC, d.id DESC
");
$distStmt->execute([$id]);
$distributions = $distStmt->fetchAll();

// Check if duplicate logs exist for this beneficiary
$dupStmt = $db->prepare("
    SELECT dl.*, 
           b1.beneficiary_id as b1_id, b1.full_name as b1_name,
           b2.beneficiary_id as b2_id, b2.full_name as b2_name
    FROM duplicate_logs dl
    JOIN beneficiaries b1 ON dl.primary_beneficiary_id = b1.id
    JOIN beneficiaries b2 ON dl.matched_beneficiary_id = b2.id
    WHERE (dl.primary_beneficiary_id = ? OR dl.matched_beneficiary_id = ?)
");
$dupStmt->execute([$id, $id]);
$duplicateLogs = $dupStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">
    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left me-1"></i> Directory
        </a>
        <div>
            <h4 class="fw-bold mb-0"><?= e($beneficiary['full_name']) ?></h4>
            <span class="badge bg-secondary"><?= e($beneficiary['beneficiary_id']) ?></span>
            <span class="badge bg-light text-dark border ms-1"><?= e($beneficiary['state']) ?>, <?= e($beneficiary['county']) ?></span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="id_card.php?id=<?= $beneficiary['id'] ?>" target="_blank" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-card-text me-1"></i> Printable ID Slip
        </a>

        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Profile
        </button>

        <?php if (has_role(['admin', 'registration_officer'])): ?>
            <a href="edit.php?id=<?= $beneficiary['id'] ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Details
            </a>
            <a href="<?= BASE_URL ?>programs/enroll.php?beneficiary_id=<?= $beneficiary['id'] ?>" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Enroll in Aid Program
            </a>
        <?php endif; ?>

        <?php if (has_role(['admin', 'distribution_officer'])): ?>
            <a href="<?= BASE_URL ?>distributions/record.php?beneficiary_id=<?= $beneficiary['id'] ?>" class="btn btn-warning btn-sm text-dark">
                <i class="bi bi-box-seam me-1"></i> Issue Aid Distribution
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Duplicate Flag Notification -->
<?php if ($beneficiary['is_duplicate_flag'] || !empty($duplicateLogs)): ?>
    <div class="alert alert-warning border-warning shadow-sm mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1">Duplicate Record Flagged for Review</h6>
                    <p class="small mb-0">This profile has been detected to share matching biometric, name, phone, or location attributes with other registry records.</p>
                </div>
            </div>
            <a href="duplicates.php" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-search me-1"></i> Open Duplicate Review Hub
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Main Profile Layout -->
<div class="row g-3">
    <!-- Left Column: Core Demographic Details -->
    <div class="col-lg-8">
        <!-- Personal Information Card -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-person-circle me-2"></i>Personal &amp; Demographic Profile</span>
                <span class="badge bg-light text-secondary border">Registered: <?= format_date($beneficiary['registration_date']) ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Full Legal Name</small>
                        <span class="fw-bold fs-6 text-dark"><?= e($beneficiary['full_name']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Gender</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['gender']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Age / Date of Birth</small>
                        <span class="fw-semibold text-dark">
                            <?= $beneficiary['estimated_age'] ? e($beneficiary['estimated_age']) . ' Years' : 'N/A' ?>
                            <?php if ($beneficiary['date_of_birth']): ?>
                                <small class="text-muted">(<?= format_date($beneficiary['date_of_birth']) ?>)</small>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Contact Phone</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['phone'] ?: 'None Recorded') ?></span>
                    </div>

                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Identification Document</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['id_document_type']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">Document Number</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['id_document_number'] ?: 'None') ?></span>
                    </div>
                </div>

                <hr class="my-3 text-muted">

                <!-- Location Details -->
                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-geo-alt-fill me-1 text-danger"></i>Geographic Location in South Sudan</h6>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <small class="text-muted d-block">State / Admin Area</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['state']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <small class="text-muted d-block">County</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['county']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <small class="text-muted d-block">Payam / Boma</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['payam_boma']) ?></span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <small class="text-muted d-block">Community / Camp</small>
                        <span class="fw-semibold text-dark"><?= e($beneficiary['community_camp'] ?: 'Host Area') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Household Composition & Vulnerability -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold text-primary"><i class="bi bi-people-fill me-2"></i>Household Vulnerability Metrics</span>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Primary Vulnerability Status</small>
                    <span class="badge bg-primary px-3 py-2 fs-6"><?= e($beneficiary['vulnerability_category']) ?></span>
                </div>

                <div class="row g-2 text-center mt-2">
                    <div class="col-sm-6 col-md">
                        <div class="p-3 border rounded bg-light">
                            <div class="display-6 fw-bold text-dark"><?= (int)$beneficiary['household_size'] ?></div>
                            <small class="text-muted fw-bold">Total HH Size</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 border rounded bg-light">
                            <div class="display-6 fw-bold text-danger"><?= (int)$beneficiary['children_under_5'] ?></div>
                            <small class="text-muted fw-bold">Children &lt; 5</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 border rounded bg-light">
                            <div class="display-6 fw-bold text-primary"><?= (int)$beneficiary['elderly_members'] ?></div>
                            <small class="text-muted fw-bold">Elderly (60+)</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 border rounded bg-light">
                            <div class="display-6 fw-bold text-warning"><?= (int)$beneficiary['disabled_members'] ?></div>
                            <small class="text-muted fw-bold">Disabled (PWD)</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 border rounded bg-light">
                            <div class="display-6 fw-bold text-info"><?= (int)$beneficiary['pregnant_lactating'] ?></div>
                            <small class="text-muted fw-bold">Pregnant/Lact.</small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($beneficiary['notes'])): ?>
                    <div class="mt-3 p-3 bg-light rounded border">
                        <small class="fw-bold text-secondary d-block mb-1">Field Assessment Remarks:</small>
                        <p class="mb-0 text-muted small"><?= nl2br(e($beneficiary['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enrolled Aid Programs -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-basket3-fill me-2"></i>Enrolled Aid Programs (<?= count($enrollments) ?>)</span>
                <?php if (has_role(['admin', 'registration_officer'])): ?>
                    <a href="<?= BASE_URL ?>programs/enroll.php?beneficiary_id=<?= $beneficiary['id'] ?>" class="btn btn-outline-success btn-sm">
                        + Enroll in Program
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Program Code</th>
                                <th>Program Title</th>
                                <th>Category</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrollments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">This beneficiary is not yet enrolled in any active aid program.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($enrollments as $en): ?>
                                    <tr>
                                        <td><code><?= e($en['program_code']) ?></code></td>
                                        <td class="fw-semibold"><?= e($en['program_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($en['category']) ?></span></td>
                                        <td><?= format_date($en['enrollment_date']) ?></td>
                                        <td><span class="badge bg-success"><?= e($en['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Aid Distribution History -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-success"><i class="bi bi-clock-history me-2"></i>Aid Distribution History (<?= count($distributions) ?>)</span>
                <?php if (has_role(['admin', 'distribution_officer'])): ?>
                    <a href="<?= BASE_URL ?>distributions/record.php?beneficiary_id=<?= $beneficiary['id'] ?>" class="btn btn-outline-warning text-dark btn-sm">
                        + Issue Aid Package
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Receipt</th>
                                <th>Program</th>
                                <th>Items Distributed</th>
                                <th>Qty / Unit</th>
                                <th>Date</th>
                                <th class="text-end">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($distributions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No aid distributions recorded for this beneficiary yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($distributions as $dst): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= e($dst['receipt_no']) ?></td>
                                        <td><?= e($dst['program_name']) ?></td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($dst['item_distributed']) ?></div>
                                            <?php if ($dst['collection_type'] === 'Authorized Proxy'): ?>
                                                <small class="text-muted"><i class="bi bi-person-badge"></i> Proxy: <?= e($dst['proxy_name']) ?> (<?= e($dst['proxy_relationship']) ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= e($dst['quantity']) ?></strong> <?= e($dst['unit']) ?></td>
                                        <td><?= format_date($dst['distribution_date']) ?></td>
                                        <td class="text-end">
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
    </div>

    <!-- Right Column: Verification & Registration Meta -->
    <div class="col-lg-4">
        <!-- Verification Status Panel -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold text-primary"><i class="bi bi-patch-check-fill me-2"></i>Verification Status</span>
            </div>
            <div class="card-body p-3">
                <div class="text-center p-3 mb-3 bg-light rounded border">
                    <div class="fs-6 text-muted mb-1">Current Status</div>
                    <?php
                        $badgeBg = match($beneficiary['verification_status']) {
                            'Verified' => 'bg-success',
                            'Pending' => 'bg-warning text-dark',
                            'Requires Correction' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                    ?>
                    <span class="badge <?= $badgeBg ?> px-4 py-2 fs-5"><?= e($beneficiary['verification_status']) ?></span>

                    <?php if ($beneficiary['verified_at']): ?>
                        <div class="small text-muted mt-2">
                            Validated on <?= format_datetime($beneficiary['verified_at']) ?>
                            <br>By: <strong><?= e($beneficiary['verified_by_name'] ?? 'Authorized Officer') ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($beneficiary['verification_notes'])): ?>
                        <div class="mt-2 text-start p-2 bg-white rounded border small text-muted">
                            <strong>Verification Remarks:</strong><br>
                            <?= nl2br(e($beneficiary['verification_notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Update Status Form (Authorized Staff Only) -->
                <?php if (has_role(['admin', 'registration_officer'])): ?>
                    <form method="POST" action="view.php?id=<?= $beneficiary['id'] ?>" class="border-top pt-3">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="update_verification">

                        <label class="form-label small fw-semibold text-secondary">Change Status</label>
                        <select name="verification_status" class="form-select form-select-sm mb-2" required>
                            <option value="Verified" <?= $beneficiary['verification_status'] === 'Verified' ? 'selected' : '' ?>>Verified (Approve)</option>
                            <option value="Pending" <?= $beneficiary['verification_status'] === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="Requires Correction" <?= $beneficiary['verification_status'] === 'Requires Correction' ? 'selected' : '' ?>>Requires Correction</option>
                            <option value="Rejected" <?= $beneficiary['verification_status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>

                        <label class="form-label small fw-semibold text-secondary">Verification Notes</label>
                        <textarea name="verification_notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Record verification method (chief validation, physical check, biometric)..."><?= e($beneficiary['verification_notes']) ?></textarea>

                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                            <i class="bi bi-save me-1"></i> Update Verification
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Registration Metadata Card -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-bold text-secondary"><i class="bi bi-info-circle-fill me-2"></i>Audit Trail &amp; Metadata</span>
            </div>
            <div class="card-body p-3 small">
                <div class="mb-2">
                    <span class="text-muted d-block">Registering Officer:</span>
                    <strong><?= e($beneficiary['registered_by_name'] ?? 'System Core') ?></strong>
                </div>

                <div class="mb-2">
                    <span class="text-muted d-block">Humanitarian Partner:</span>
                    <strong><?= e($beneficiary['ngo_name'] ?? 'National Authority') ?> (<?= e($beneficiary['ngo_acronym'] ?? 'NGO') ?>)</strong>
                </div>

                <div class="mb-2">
                    <span class="text-muted d-block">Created In Database:</span>
                    <span><?= format_datetime($beneficiary['created_at']) ?></span>
                </div>

                <div>
                    <span class="text-muted d-block">Last Updated:</span>
                    <span><?= format_datetime($beneficiary['updated_at']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

