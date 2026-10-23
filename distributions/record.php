<?php
/**
 * Record Aid Distribution Transaction
 * Role-restricted: Admin, Distribution Officer
 */

$pageTitle = "Record Aid Distribution";
$activeNav = "distributions";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'distribution_officer']);
$currentUser = current_user();
$db = getDB();

$preProgId = (int)($_GET['program_id'] ?? 0);
$preBenId  = (int)($_GET['beneficiary_id'] ?? 0);

// Fetch Active Aid Programs
$programs = $db->query("
    SELECT p.*, n.acronym as ngo_acronym
    FROM aid_programs p
    JOIN ngos n ON p.ngo_id = n.id
    WHERE p.status = 'Active'
    ORDER BY p.name ASC
")->fetchAll();

// Fetch Verified / Registered Beneficiaries
$beneficiaries = $db->query("
    SELECT b.id, b.beneficiary_id, b.full_name, b.state, b.county, b.verification_status, b.vulnerability_category
    FROM beneficiaries b
    WHERE b.verification_status != 'Rejected'
    ORDER BY b.id DESC
")->fetchAll();

$errors = [];
$generatedReceipt = generate_receipt_no();

$formData = [
    'receipt_no'          => $generatedReceipt,
    'program_id'          => $preProgId,
    'beneficiary_id'      => $preBenId,
    'distribution_date'   => date('Y-m-d'),
    'distribution_cycle'  => 'Cycle 1 (' . date('M Y') . ')',
    'item_distributed'    => '',
    'quantity'            => 1.00,
    'unit'                => 'Kit',
    'distribution_point'  => 'Mahad Distribution Hub, Juba',
    'collection_type'     => 'Direct by Beneficiary',
    'proxy_name'          => '',
    'proxy_relationship'  => '',
    'proxy_phone'         => '',
    'remarks'             => ''
];

// Pre-fill program details if pre-selected
if ($preProgId > 0) {
    foreach ($programs as $p) {
        if ($p['id'] == $preProgId) {
            $formData['item_description'] = $p['item_description'];
            $formData['item_distributed'] = $p['item_description'];
            $formData['quantity'] = $p['allocation_quota'];
            $formData['unit'] = $p['unit'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $formData['program_id']         = (int)($_POST['program_id'] ?? 0);
        $formData['beneficiary_id']     = (int)($_POST['beneficiary_id'] ?? 0);
        $formData['distribution_date']  = sanitize($_POST['distribution_date'] ?? date('Y-m-d'));
        $formData['distribution_cycle'] = sanitize($_POST['distribution_cycle'] ?? 'Cycle 1');
        $formData['item_distributed']   = sanitize($_POST['item_distributed'] ?? '');
        $formData['quantity']           = (float)($_POST['quantity'] ?? 1.0);
        $formData['unit']               = sanitize($_POST['unit'] ?? 'Kit');
        $formData['distribution_point'] = sanitize($_POST['distribution_point'] ?? '');
        $formData['collection_type']    = sanitize($_POST['collection_type'] ?? 'Direct by Beneficiary');
        $formData['proxy_name']         = sanitize($_POST['proxy_name'] ?? '');
        $formData['proxy_relationship'] = sanitize($_POST['proxy_relationship'] ?? '');
        $formData['proxy_phone']        = sanitize($_POST['proxy_phone'] ?? '');
        $formData['remarks']            = sanitize($_POST['remarks'] ?? '');

        if ($formData['program_id'] <= 0) $errors[] = "Please select an Aid Program.";
        if ($formData['beneficiary_id'] <= 0) $errors[] = "Please select a recipient Beneficiary.";
        if (empty($formData['item_distributed'])) $errors[] = "Items Distributed must be specified.";
        if ($formData['quantity'] <= 0) $errors[] = "Distribution quantity must be greater than zero.";
        if (empty($formData['distribution_point'])) $errors[] = "Distribution Point / Location is required.";

        if ($formData['collection_type'] === 'Authorized Proxy') {
            if (empty($formData['proxy_name'])) $errors[] = "Proxy Collector Name is required when collecting by proxy.";
            if (empty($formData['proxy_relationship'])) $errors[] = "Relationship of the proxy collector to the beneficiary is required.";
        }

        // Duplicate Distribution / Double Dipping Detection within the same cycle
        if (empty($errors)) {
            $dupCheck = $db->prepare("
                SELECT id, receipt_no, distribution_date 
                FROM aid_distributions 
                WHERE program_id = ? AND beneficiary_id = ? AND distribution_cycle = ?
                LIMIT 1
            ");
            $dupCheck->execute([$formData['program_id'], $formData['beneficiary_id'], $formData['distribution_cycle']]);
            $existingDist = $dupCheck->fetch();

            if ($existingDist) {
                $errors[] = "DUPLICATE DISTRIBUTION DETECTED: This beneficiary has ALREADY received relief assistance for '{$formData['distribution_cycle']}' under this program (Receipt: {$existingDist['receipt_no']} on {$existingDist['distribution_date']}).";
            }
        }

        if (empty($errors)) {
            $receiptNo = generate_receipt_no();

            $insert = $db->prepare("
                INSERT INTO aid_distributions (
                    receipt_no, program_id, beneficiary_id, distribution_date, distribution_cycle,
                    item_distributed, quantity, unit, distribution_point, collection_type,
                    proxy_name, proxy_relationship, proxy_phone, distributed_by, remarks, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            $insert->execute([
                $receiptNo,
                $formData['program_id'],
                $formData['beneficiary_id'],
                $formData['distribution_date'],
                $formData['distribution_cycle'],
                $formData['item_distributed'],
                $formData['quantity'],
                $formData['unit'],
                $formData['distribution_point'],
                $formData['collection_type'],
                $formData['proxy_name'],
                $formData['proxy_relationship'],
                $formData['proxy_phone'],
                $currentUser['id'],
                $formData['remarks']
            ]);

            $newDistId = (int)$db->lastInsertId();

            // Auto enroll beneficiary in program if not already enrolled
            $chkEnroll = $db->prepare("SELECT id FROM program_enrollments WHERE program_id = ? AND beneficiary_id = ?");
            $chkEnroll->execute([$formData['program_id'], $formData['beneficiary_id']]);
            if (!$chkEnroll->fetch()) {
                $autoEnroll = $db->prepare("
                    INSERT INTO program_enrollments (program_id, beneficiary_id, enrollment_date, status, enrolled_by, notes, created_at)
                    VALUES (?, ?, ?, 'Enrolled', ?, 'Auto-enrolled during aid distribution', NOW())
                ");
                $autoEnroll->execute([$formData['program_id'], $formData['beneficiary_id'], $formData['distribution_date'], $currentUser['id']]);
            }

            log_audit(
                'AID_DISTRIBUTED',
                'Distributions',
                (string)$newDistId,
                "Issued {$formData['quantity']} {$formData['unit']} of '{$formData['item_distributed']}' to Beneficiary #{$formData['beneficiary_id']} (Receipt: {$receiptNo})"
            );

            flash_set('success', "Aid distribution transaction recorded successfully! Receipt: {$receiptNo}");
            header('Location: receipt.php?id=' . $newDistId);
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-box-seam text-warning me-2"></i>Record Aid Distribution Transaction</h4>
                <p class="text-muted small mb-0">Record relief item handovers or cash voucher disbursement with receipt generation.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Distribution Logs
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <div class="fw-bold mb-1"><i class="bi bi-shield-x me-2"></i>Transaction Error:</div>
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="record.php" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Section 1: Beneficiary & Program Selection -->
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-person-check me-2"></i>Target Beneficiary &amp; Assistance Program
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Aid Program <span class="text-danger">*</span></label>
                        <select name="program_id" id="program_select" class="form-select" required>
                            <option value="">-- Select Aid Program --</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?= $prog['id'] ?>" 
                                        data-item="<?= e($prog['item_description']) ?>"
                                        data-quota="<?= e($prog['allocation_quota']) ?>"
                                        data-unit="<?= e($prog['unit']) ?>"
                                        <?= $formData['program_id'] == $prog['id'] ? 'selected' : '' ?>>
                                    [<?= e($prog['ngo_acronym']) ?>] <?= e($prog['program_code']) ?> &bull; <?= e($prog['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Recipient Beneficiary <span class="text-danger">*</span></label>
                        <select name="beneficiary_id" class="form-select" required>
                            <option value="">-- Select Beneficiary --</option>
                            <?php foreach ($beneficiaries as $ben): ?>
                                <option value="<?= $ben['id'] ?>" <?= $formData['beneficiary_id'] == $ben['id'] ? 'selected' : '' ?>>
                                    <?= e($ben['beneficiary_id']) ?> &bull; <?= e($ben['full_name']) ?> (<?= e($ben['county']) ?>, <?= e($ben['state']) ?>) [<?= e($ben['verification_status']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Distribution Details -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-box2-heart me-2"></i>Items, Quota &amp; Distribution Cycle
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Distribution Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="distribution_date" value="<?= e($formData['distribution_date']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Distribution Cycle / Round <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="distribution_cycle" value="<?= e($formData['distribution_cycle']) ?>" required placeholder="e.g. Cycle 1, Cash Tranche 2, October Round">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Distribution Point / Site <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="distribution_point" value="<?= e($formData['distribution_point']) ?>" required placeholder="e.g. Mahad Center, Juba">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Relief Package / Items Distributed <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="item_distributed" name="item_distributed" value="<?= e($formData['item_distributed']) ?>" required placeholder="e.g. Sorghum 50kg, Oil 4.5L or Cash $75 USD">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.1" class="form-control" id="quantity_input" name="quantity" value="<?= e($formData['quantity']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Unit of Measure <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unit_input" name="unit" value="<?= e($formData['unit']) ?>" required placeholder="e.g. Kit, USD Voucher">
                    </div>
                </div>
            </div>

            <!-- Section 3: Collector Verification & Proxy Details -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-person-badge me-2"></i>Collection Verification &amp; Proxy Authority
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Collection Method <span class="text-danger">*</span></label>
                        <select name="collection_type" id="collection_type_select" class="form-select" required>
                            <option value="Direct by Beneficiary" <?= $formData['collection_type'] === 'Direct by Beneficiary' ? 'selected' : '' ?>>Direct by Beneficiary</option>
                            <option value="Authorized Proxy" <?= $formData['collection_type'] === 'Authorized Proxy' ? 'selected' : '' ?>>Authorized Proxy (Representative)</option>
                        </select>
                    </div>

                    <div class="col-md-8" id="proxy_fields" style="<?= $formData['collection_type'] === 'Authorized Proxy' ? '' : 'display: none;' ?>">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold small text-secondary mb-2">Proxy Collector Information:</h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Proxy Full Name</label>
                                    <input type="text" class="form-control form-control-sm" name="proxy_name" value="<?= e($formData['proxy_name']) ?>" placeholder="Proxy Full Name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Relationship to Beneficiary</label>
                                    <input type="text" class="form-control form-control-sm" name="proxy_relationship" value="<?= e($formData['proxy_relationship']) ?>" placeholder="e.g. Son, Daughter, Caregiver">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Proxy Phone Number</label>
                                    <input type="text" class="form-control form-control-sm" name="proxy_phone" value="<?= e($formData['proxy_phone']) ?>" placeholder="Proxy Phone">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Distribution Remarks / Condition of Recipient</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Biometric verified, proxy letter checked, or health status observed..."><?= e($formData['remarks']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-warning btn-sm px-4 fw-bold text-dark shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Issue Aid &amp; Generate Official Receipt
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const progSelect = document.getElementById("program_select");
    const itemInput  = document.getElementById("item_distributed");
    const quotaInput = document.getElementById("quantity_input");
    const unitInput  = document.getElementById("unit_input");
    const colSelect  = document.getElementById("collection_type_select");
    const proxyDiv   = document.getElementById("proxy_fields");

    if (progSelect) {
        progSelect.addEventListener("change", function () {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.value) {
                if (!itemInput.value || itemInput.value === '') {
                    itemInput.value = opt.getAttribute("data-item") || '';
                }
                if (opt.getAttribute("data-quota")) {
                    quotaInput.value = opt.getAttribute("data-quota");
                }
                if (opt.getAttribute("data-unit")) {
                    unitInput.value = opt.getAttribute("data-unit");
                }
            }
        });
    }

    if (colSelect && proxyDiv) {
        colSelect.addEventListener("change", function () {
            if (this.value === 'Authorized Proxy') {
                proxyDiv.style.display = 'block';
            } else {
                proxyDiv.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

