<?php
/**
 * Beneficiary Verification Workflow Module
 * Role-restricted: Admin, Registration Officer
 */

$pageTitle = "Beneficiary Verification Queue";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'registration_officer']);
$currentUser = current_user();
$db = getDB();

// Handle status verification POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_submit') {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $benId     = (int)($_POST['beneficiary_id'] ?? 0);
        $newStatus = sanitize($_POST['verification_status'] ?? '');
        $notes     = sanitize($_POST['verification_notes'] ?? '');

        if ($benId > 0 && in_array($newStatus, ['Verified', 'Pending', 'Requires Correction', 'Rejected'])) {
            $stmt = $db->prepare("
                UPDATE beneficiaries 
                SET verification_status = ?, verified_by = ?, verified_at = NOW(), verification_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $currentUser['id'], $notes, $benId]);

            log_audit(
                'BENEFICIARY_VERIFIED',
                'Verification',
                (string)$benId,
                "Verification status changed to '{$newStatus}'. Notes: {$notes}"
            );

            flash_set('success', "Beneficiary status updated to '{$newStatus}'.");
            header('Location: verify.php');
            exit();
        }
    }
}

// Filter Status
$filter = sanitize($_GET['filter'] ?? 'Pending');
$allowedFilters = ['Pending', 'Requires Correction', 'Verified', 'All'];
if (!in_array($filter, $allowedFilters)) $filter = 'Pending';

$where = "1=1";
$params = [];
if ($filter !== 'All') {
    $where .= " AND b.verification_status = ?";
    $params[] = $filter;
}

$stmt = $db->prepare("
    SELECT b.*, n.acronym as ngo_acronym, u.full_name as registered_by_name, v.full_name as verified_by_name
    FROM beneficiaries b
    LEFT JOIN ngos n ON b.ngo_id = n.id
    LEFT JOIN users u ON b.registered_by = u.id
    LEFT JOIN users v ON b.verified_by = v.id
    WHERE {$where}
    ORDER BY b.id DESC
");
$stmt->execute($params);
$queue = $stmt->fetchAll();

// Counts for filter pills
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Pending'")->fetchColumn();
$correctionCount = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Requires Correction'")->fetchColumn();
$verifiedCount = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Verified'")->fetchColumn();

// Recent Verification Audit Logs
$recentVerLogs = $db->query("
    SELECT a.*, u.full_name as officer_name 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE a.module = 'Verification' 
    ORDER BY a.id DESC 
    LIMIT 6
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-patch-check-fill text-warning me-2"></i>Beneficiary Verification Queue</h4>
        <p class="text-muted small mb-0">Review pending registrations, validate identity, and record official verification findings.</p>
    </div>

    <!-- Filter Pills -->
    <div class="btn-group btn-group-sm">
        <a href="?filter=Pending" class="btn <?= $filter === 'Pending' ? 'btn-warning fw-bold' : 'btn-outline-secondary' ?>">
            Pending Review <span class="badge bg-dark ms-1"><?= $pendingCount ?></span>
        </a>
        <a href="?filter=Requires Correction" class="btn <?= $filter === 'Requires Correction' ? 'btn-danger fw-bold' : 'btn-outline-secondary' ?>">
            Requires Correction <span class="badge bg-white text-danger ms-1"><?= $correctionCount ?></span>
        </a>
        <a href="?filter=Verified" class="btn <?= $filter === 'Verified' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            Verified <span class="badge bg-white text-success ms-1"><?= $verifiedCount ?></span>
        </a>
        <a href="?filter=All" class="btn <?= $filter === 'All' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
            All Records
        </a>
    </div>
</div>

<!-- Main Table Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID &amp; Date</th>
                        <th>Beneficiary Name</th>
                        <th>Location &amp; Camp</th>
                        <th>Document &amp; Contact</th>
                        <th>Vulnerability</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($queue)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No beneficiary records currently match the '<?= e($filter) ?>' queue.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queue as $item): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $item['id'] ?>" class="fw-bold text-decoration-none">
                                        <?= e($item['beneficiary_id']) ?>
                                    </a>
                                    <small class="d-block text-muted"><?= format_date($item['registration_date']) ?></small>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark"><?= e($item['full_name']) ?></div>
                                    <small class="text-muted"><?= e($item['gender']) ?>, <?= $item['estimated_age'] ? e($item['estimated_age']) . ' yrs' : 'Age N/A' ?></small>
                                </td>

                                <td>
                                    <div class="small fw-semibold"><?= e($item['county']) ?>, <?= e($item['state']) ?></div>
                                    <small class="text-muted"><?= e($item['payam_boma']) ?></small>
                                </td>

                                <td>
                                    <div class="small"><?= e($item['id_document_type']) ?></div>
                                    <small class="text-muted"><?= e($item['id_document_number'] ?: 'No document ID') ?></small>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border small"><?= e($item['vulnerability_category']) ?></span>
                                </td>

                                <td>
                                    <?php
                                        $badgeBg = match($item['verification_status']) {
                                            'Verified' => 'bg-success',
                                            'Pending' => 'bg-warning text-dark',
                                            'Requires Correction' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $badgeBg ?>"><?= e($item['verification_status']) ?></span>
                                    <?php if ($item['verified_by_name']): ?>
                                        <small class="d-block text-muted" style="font-size: 0.72rem;">By <?= e($item['verified_by_name']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-3">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $item['id'] ?>">
                                        <i class="bi bi-shield-check me-1"></i> Verify / Review
                                    </button>
                                </td>
                            </tr>

                            <!-- Verification Modal for each beneficiary -->
                            <div class="modal fade" id="verifyModal<?= $item['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="verify.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="verify_submit">
                                            <input type="hidden" name="beneficiary_id" value="<?= $item['id'] ?>">

                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-patch-check text-primary me-2"></i>Verify Beneficiary
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="p-3 bg-light rounded border mb-3">
                                                    <h6 class="fw-bold mb-1"><?= e($item['full_name']) ?></h6>
                                                    <div class="small text-muted">
                                                        ID: <strong><?= e($item['beneficiary_id']) ?></strong> &bull; 
                                                        <?= e($item['gender']) ?> &bull; 
                                                        <?= e($item['county']) ?>, <?= e($item['state']) ?>
                                                    </div>
                                                    <div class="small text-muted mt-1">
                                                        Document: <strong><?= e($item['id_document_type']) ?> (<?= e($item['id_document_number'] ?: 'None') ?>)</strong>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold small text-secondary">Verification Decision <span class="text-danger">*</span></label>
                                                    <select name="verification_status" class="form-select" required>
                                                        <option value="Verified" <?= $item['verification_status'] === 'Verified' ? 'selected' : '' ?>>Verified &amp; Approved (Eligible for Assistance)</option>
                                                        <option value="Requires Correction" <?= $item['verification_status'] === 'Requires Correction' ? 'selected' : '' ?>>Requires Correction (Documents / Data Incomplete)</option>
                                                        <option value="Pending" <?= $item['verification_status'] === 'Pending' ? 'selected' : '' ?>>Pending Review (Under Investigation)</option>
                                                        <option value="Rejected" <?= $item['verification_status'] === 'Rejected' ? 'selected' : '' ?>>Rejected (Ineligible / Disqualified)</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold small text-secondary">Verification Notes &amp; Verification Method</label>
                                                    <textarea name="verification_notes" class="form-control" rows="3" placeholder="State how identity was validated (e.g. community chief interview, physical check, biometric verification)..."><?= e($item['verification_notes']) ?></textarea>
                                                </div>
                                            </div>

                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                                    <i class="bi bi-check-lg me-1"></i> Save Verification Finding
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Verification Audit Trail Section -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-secondary">
            <i class="bi bi-clock-history me-2"></i>Recent Verification Audit History
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Verifying Officer</th>
                        <th>Beneficiary Record</th>
                        <th>Action Log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentVerLogs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No verification logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentVerLogs as $log): ?>
                            <tr>
                                <td class="small text-muted"><?= format_datetime($log['created_at']) ?></td>
                                <td class="fw-semibold small"><?= e($log['officer_name'] ?? 'System') ?></td>
                                <td>
                                    <a href="view.php?id=<?= $log['record_id'] ?>" class="small fw-semibold">
                                        Beneficiary #<?= e($log['record_id']) ?>
                                    </a>
                                </td>
                                <td class="small text-muted"><?= e($log['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

