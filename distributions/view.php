<?php
/**
 * View Distribution Transaction
 */

$pageTitle = "Distribution Details";
$activeNav = "distributions";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid distribution ID.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("
    SELECT d.*, 
           b.beneficiary_id, b.full_name as ben_name, b.gender, b.state, b.county, b.payam_boma, b.community_camp, b.household_size,
           p.name as program_name, p.program_code, p.category,
           n.name as ngo_name, n.acronym as ngo_acronym,
           u.full_name as officer_name
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    JOIN ngos n ON p.ngo_id = n.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$dist = $stmt->fetch();

if (!$dist) {
    flash_set('danger', 'Distribution transaction not found.');
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Distribution Transaction &bull; <code><?= e($dist['receipt_no']) ?></code></h4>
                <p class="text-muted small mb-0">Issued on <?= format_date($dist['distribution_date']) ?> at <?= e($dist['distribution_point']) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Logs
                </a>
                <a href="receipt.php?id=<?= $dist['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-box-seam me-2"></i>Relief Handover Record</span>
                <span class="badge bg-success">Completed</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Aid Program</small>
                        <a href="<?= BASE_URL ?>programs/view.php?id=<?= $dist['program_id'] ?>" class="fw-bold text-decoration-none">
                            <?= e($dist['program_name']) ?> (<?= e($dist['program_code']) ?>)
                        </a>
                        <small class="text-muted d-block"><?= e($dist['ngo_name']) ?></small>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Beneficiary Recipient</small>
                        <a href="<?= BASE_URL ?>beneficiaries/view.php?id=<?= $dist['beneficiary_id'] ?>" class="fw-bold text-decoration-none">
                            <?= e($dist['ben_name']) ?> (<?= e($dist['beneficiary_id']) ?>)
                        </a>
                        <small class="text-muted d-block"><?= e($dist['county']) ?>, <?= e($dist['state']) ?></small>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Item Distributed</small>
                        <div class="fw-bold text-dark fs-6"><?= e($dist['item_distributed']) ?></div>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Disbursed Quantity &amp; Unit</small>
                        <div class="fw-bold text-primary fs-5"><?= e($dist['quantity']) ?> <?= e($dist['unit']) ?></div>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Collection Method</small>
                        <span class="badge bg-light text-dark border"><?= e($dist['collection_type']) ?></span>
                        <?php if ($dist['collection_type'] === 'Authorized Proxy'): ?>
                            <div class="small text-muted mt-1">
                                Proxy: <strong><?= e($dist['proxy_name']) ?></strong> (<?= e($dist['proxy_relationship']) ?>) &bull; Phone: <?= e($dist['proxy_phone']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-6">
                        <small class="text-muted d-block">Distribution Officer</small>
                        <span class="fw-semibold text-dark"><?= e($dist['officer_name'] ?? 'Authorized Officer') ?></span>
                    </div>

                    <?php if (!empty($dist['remarks'])): ?>
                        <div class="col-12 mt-2 pt-2 border-top">
                            <small class="text-muted d-block">Officer Remarks:</small>
                            <p class="small text-dark mb-0"><?= nl2br(e($dist['remarks'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

