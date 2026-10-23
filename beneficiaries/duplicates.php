<?php
/**
 * Duplicate Registration Review Hub
 * Side-by-side comparison, audit review, and resolution workflows.
 * Role-restricted: Admin, Registration Officer
 */

$pageTitle = "Duplicate Detection & Review Center";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'registration_officer']);
$currentUser = current_user();
$db = getDB();

// Handle Resolution Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_duplicate') {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $logId      = (int)($_POST['log_id'] ?? 0);
        $resolution = sanitize($_POST['resolution'] ?? '');
        $notes      = sanitize($_POST['resolution_notes'] ?? '');

        if ($logId > 0 && in_array($resolution, ['Reviewed - Distinct', 'Reviewed - Confirmed Duplicate'])) {
            $logStmt = $db->prepare("SELECT * FROM duplicate_logs WHERE id = ?");
            $logStmt->execute([$logId]);
            $dupLog = $logStmt->fetch();

            if ($dupLog) {
                // Update duplicate log record
                $updateLog = $db->prepare("
                    UPDATE duplicate_logs 
                    SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW() 
                    WHERE id = ?
                ");
                $updateLog->execute([$resolution, $currentUser['id'], $notes, $logId]);

                // If distinct, clear the duplicate flag from matched beneficiary if no other active flags exist
                if ($resolution === 'Reviewed - Distinct') {
                    $db->prepare("UPDATE beneficiaries SET is_duplicate_flag = 0 WHERE id = ?")->execute([$dupLog['matched_beneficiary_id']]);
                } else if ($resolution === 'Reviewed - Confirmed Duplicate') {
                    // Update notes on the duplicate record
                    $updateBen = $db->prepare("
                        UPDATE beneficiaries 
                        SET verification_status = 'Requires Correction', 
                            verification_notes = CONCAT(IFNULL(verification_notes, ''), '\n[CONFIRMED DUPLICATE] Matches record ID #', ?)
                        WHERE id = ?
                    ");
                    $updateBen->execute([$dupLog['primary_beneficiary_id'], $dupLog['matched_beneficiary_id']]);
                }

                log_audit(
                    'DUPLICATE_REVIEWED',
                    'Duplicates',
                    (string)$logId,
                    "Duplicate case #{$logId} reviewed: '{$resolution}'. Notes: {$notes}"
                );

                flash_set('success', "Duplicate case #{$logId} successfully updated to '{$resolution}'.");
                header('Location: duplicates.php');
                exit();
            }
        }
    }
}

// Fetch all duplicate logs with side-by-side beneficiary data
$query = "
    SELECT dl.*,
           b1.beneficiary_id as b1_id_str, b1.full_name as b1_name, b1.gender as b1_gender,
           b1.date_of_birth as b1_dob, b1.estimated_age as b1_age, b1.phone as b1_phone,
           b1.id_document_number as b1_doc_no, b1.state as b1_state, b1.county as b1_county,
           b1.payam_boma as b1_payam, b1.household_size as b1_hh, b1.verification_status as b1_status,
           
           b2.beneficiary_id as b2_id_str, b2.full_name as b2_name, b2.gender as b2_gender,
           b2.date_of_birth as b2_dob, b2.estimated_age as b2_age, b2.phone as b2_phone,
           b2.id_document_number as b2_doc_no, b2.state as b2_state, b2.county as b2_county,
           b2.payam_boma as b2_payam, b2.household_size as b2_hh, b2.verification_status as b2_status,
           
           u.full_name as reviewer_name
    FROM duplicate_logs dl
    JOIN beneficiaries b1 ON dl.primary_beneficiary_id = b1.id
    JOIN beneficiaries b2 ON dl.matched_beneficiary_id = b2.id
    LEFT JOIN users u ON dl.reviewed_by = u.id
    ORDER BY dl.status = 'Flagged' DESC, dl.id DESC
";
$duplicates = $db->query($query)->fetchAll();

$pendingFlagCount = 0;
foreach ($duplicates as $d) {
    if ($d['status'] === 'Flagged') $pendingFlagCount++;
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-copy text-danger me-2"></i>Duplicate Registration Detection &amp; Review Hub</h4>
        <p class="text-muted small mb-0">Humanitarian de-duplication audit to maintain registry integrity without automated record loss.</p>
    </div>

    <div>
        <span class="badge bg-warning text-dark px-3 py-2 fs-6">
            <i class="bi bi-flag-fill me-1"></i> <?= $pendingFlagCount ?> Unresolved Cases
        </span>
    </div>
</div>

<!-- Explanatory Alert -->
<div class="alert alert-info border-info shadow-sm mb-4">
    <div class="d-flex">
        <i class="bi bi-shield-exclamation fs-3 text-info me-3"></i>
        <div class="small">
            <strong>Humanitarian Protection Guideline:</strong> In accordance with project requirements, the system strictly avoids automatically merging or deleting records based solely on a name match. Authorized staff must compare the identifying details side-by-side, verify community recommendations, and record a formal finding.
        </div>
    </div>
</div>

<!-- Duplicate Cases List -->
<?php if (empty($duplicates)): ?>
    <div class="card shadow-sm border-0 text-center p-5 text-muted">
        <i class="bi bi-check-circle fs-1 text-success mb-2"></i>
        <h5 class="fw-bold">No Duplicate Registrations Flagged</h5>
        <p class="small text-muted mb-0">All registered beneficiaries in South Sudan appear unique with no conflicting identifiers.</p>
    </div>
<?php else: ?>
    <?php foreach ($duplicates as $case): ?>
        <div class="card shadow-sm border-0 mb-4 <?= $case['status'] === 'Flagged' ? 'border-warning border-2' : '' ?>">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="badge bg-danger me-2">Case #<?= $case['id'] ?></span>
                    <strong class="text-dark"><?= e($case['match_type']) ?></strong>
                    <span class="badge bg-warning text-dark ms-2"><?= $case['confidence_score'] ?>% Confidence</span>
                </div>

                <div>
                    <?php if ($case['status'] === 'Flagged'): ?>
                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock me-1"></i> Awaiting Review</span>
                    <?php elseif ($case['status'] === 'Reviewed - Distinct'): ?>
                        <span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i> Confirmed Distinct Persons</span>
                    <?php else: ?>
                        <span class="badge bg-danger px-2 py-1"><i class="bi bi-exclamation-octagon me-1"></i> <?= e($case['status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Detection Reason -->
                <div class="p-2 mb-3 bg-light rounded border small text-muted">
                    <strong class="text-secondary">Matching Analysis:</strong> <?= e($case['details']) ?>
                </div>

                <!-- Side by Side Beneficiary Comparison -->
                <div class="row g-3">
                    <!-- Primary Record -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <span class="badge bg-primary">Record A (Existing in Registry)</span>
                                <a href="view.php?id=<?= $case['primary_beneficiary_id'] ?>" target="_blank" class="small fw-bold text-decoration-none">
                                    <?= e($case['b1_id_str']) ?> <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>

                            <table class="table table-sm table-borderless small mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 35%;">Full Name:</td>
                                    <td class="fw-bold text-dark"><?= e($case['b1_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Gender &amp; Age:</td>
                                    <td><?= e($case['b1_gender']) ?> &bull; <?= $case['b1_age'] ? e($case['b1_age']) . ' yrs' : 'N/A' ?> (DOB: <?= $case['b1_dob'] ?: 'N/A' ?>)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phone Number:</td>
                                    <td><?= e($case['b1_phone'] ?: 'None') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Document No:</td>
                                    <td><code><?= e($case['b1_doc_no'] ?: 'None') ?></code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Location:</td>
                                    <td><?= e($case['b1_county']) ?>, <?= e($case['b1_state']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payam / Settlement:</td>
                                    <td><?= e($case['b1_payam']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Household Size:</td>
                                    <td><?= (int)$case['b1_hh'] ?> Members</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Current Status:</td>
                                    <td><span class="badge bg-secondary"><?= e($case['b1_status']) ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Matched Duplicate Record -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <span class="badge bg-secondary">Record B (Newer / Flagged Record)</span>
                                <a href="view.php?id=<?= $case['matched_beneficiary_id'] ?>" target="_blank" class="small fw-bold text-decoration-none">
                                    <?= e($case['b2_id_str']) ?> <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>

                            <table class="table table-sm table-borderless small mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 35%;">Full Name:</td>
                                    <td class="fw-bold <?= strtolower($case['b1_name']) === strtolower($case['b2_name']) ? 'text-danger' : 'text-dark' ?>">
                                        <?= e($case['b2_name']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Gender &amp; Age:</td>
                                    <td><?= e($case['b2_gender']) ?> &bull; <?= $case['b2_age'] ? e($case['b2_age']) . ' yrs' : 'N/A' ?> (DOB: <?= $case['b2_dob'] ?: 'N/A' ?>)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phone Number:</td>
                                    <td class="<?= !empty($case['b1_phone']) && $case['b1_phone'] === $case['b2_phone'] ? 'text-danger fw-bold' : '' ?>">
                                        <?= e($case['b2_phone'] ?: 'None') ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Document No:</td>
                                    <td><code><?= e($case['b2_doc_no'] ?: 'None') ?></code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Location:</td>
                                    <td><?= e($case['b2_county']) ?>, <?= e($case['b2_state']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payam / Settlement:</td>
                                    <td><?= e($case['b2_payam']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Household Size:</td>
                                    <td><?= (int)$case['b2_hh'] ?> Members</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Current Status:</td>
                                    <td><span class="badge bg-secondary"><?= e($case['b2_status']) ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Review / Resolution History or Form -->
                <?php if ($case['status'] === 'Flagged'): ?>
                    <form method="POST" action="duplicates.php" class="mt-4 p-3 bg-light rounded border">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="resolve_duplicate">
                        <input type="hidden" name="log_id" value="<?= $case['id'] ?>">

                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary mb-1">Audit Determination <span class="text-danger">*</span></label>
                                <select name="resolution" class="form-select form-select-sm" required>
                                    <option value="">-- Choose Determination --</option>
                                    <option value="Reviewed - Distinct">Distinct Individuals (False Positive - Keep Both Active)</option>
                                    <option value="Reviewed - Confirmed Duplicate">Confirmed Duplicate (Flag Record B for Correction / Hold)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary mb-1">Review Notes &amp; Justification <span class="text-danger">*</span></label>
                                <input type="text" name="resolution_notes" class="form-control form-control-sm" placeholder="Provide reason based on physical check, family lineage, or chief statement" required>
                            </div>

                            <div class="col-md-2 mt-md-4">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                    <i class="bi bi-save me-1"></i> Resolve Case
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="mt-3 p-3 bg-light rounded border small text-muted">
                        <i class="bi bi-person-check-fill text-success me-1"></i>
                        Reviewed by <strong><?= e($case['reviewer_name'] ?? 'Authorized Officer') ?></strong> on <?= format_datetime($case['reviewed_at']) ?>.
                        <div class="mt-1 text-dark"><strong>Resolution Findings:</strong> <?= e($case['review_notes']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

