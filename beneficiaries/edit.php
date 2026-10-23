<?php
/**
 * Edit Beneficiary Information
 * Role-restricted: Admin, Registration Officer
 */

$pageTitle = "Edit Beneficiary";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'registration_officer']);
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid beneficiary ID specified.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM beneficiaries WHERE id = ?");
$stmt->execute([$id]);
$beneficiary = $stmt->fetch();

if (!$beneficiary) {
    flash_set('danger', 'Beneficiary record not found.');
    header('Location: index.php');
    exit();
}

// Fetch NGOs
$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];
$formData = $beneficiary;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token expired or invalid. Please resubmit the form.";
    } else {
        $formData['full_name']              = sanitize($_POST['full_name'] ?? '');
        $formData['gender']                 = sanitize($_POST['gender'] ?? '');
        $formData['date_of_birth']          = !empty($_POST['date_of_birth']) ? sanitize($_POST['date_of_birth']) : null;
        $formData['estimated_age']          = !empty($_POST['estimated_age']) ? (int)$_POST['estimated_age'] : null;
        $formData['phone']                  = sanitize($_POST['phone'] ?? '');
        $formData['id_document_type']       = sanitize($_POST['id_document_type'] ?? 'Chief / Local Recommendation Letter');
        $formData['id_document_number']     = sanitize($_POST['id_document_number'] ?? '');
        $formData['state']                  = sanitize($_POST['state'] ?? '');
        $formData['county']                 = sanitize($_POST['county'] ?? '');
        $formData['payam_boma']             = sanitize($_POST['payam_boma'] ?? '');
        $formData['community_camp']         = sanitize($_POST['community_camp'] ?? '');
        $formData['household_size']         = max(1, (int)($_POST['household_size'] ?? 1));
        $formData['children_under_5']       = max(0, (int)($_POST['children_under_5'] ?? 0));
        $formData['elderly_members']        = max(0, (int)($_POST['elderly_members'] ?? 0));
        $formData['disabled_members']       = max(0, (int)($_POST['disabled_members'] ?? 0));
        $formData['pregnant_lactating']     = max(0, (int)($_POST['pregnant_lactating'] ?? 0));
        $formData['vulnerability_category'] = sanitize($_POST['vulnerability_category'] ?? '');
        $formData['ngo_id']                 = !empty($_POST['ngo_id']) ? (int)$_POST['ngo_id'] : $beneficiary['ngo_id'];
        $formData['notes']                  = sanitize($_POST['notes'] ?? '');

        // Validation Rules
        if (empty($formData['full_name'])) {
            $errors[] = "Full Name is required.";
        }
        if (empty($formData['gender']) || !in_array($formData['gender'], ['Male', 'Female', 'Other'])) {
            $errors[] = "Valid Gender is required.";
        }
        if (empty($formData['state']) || empty($formData['county'])) {
            $errors[] = "State and County are required.";
        }
        if (empty($formData['payam_boma'])) {
            $errors[] = "Payam / Boma is required.";
        }

        if (empty($errors)) {
            $updateSql = "
                UPDATE beneficiaries SET
                    full_name = ?, gender = ?, date_of_birth = ?, estimated_age = ?,
                    phone = ?, id_document_type = ?, id_document_number = ?,
                    state = ?, county = ?, payam_boma = ?, community_camp = ?,
                    household_size = ?, children_under_5 = ?, elderly_members = ?,
                    disabled_members = ?, pregnant_lactating = ?,
                    vulnerability_category = ?, ngo_id = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ";

            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                $formData['full_name'],
                $formData['gender'],
                $formData['date_of_birth'],
                $formData['estimated_age'],
                $formData['phone'],
                $formData['id_document_type'],
                $formData['id_document_number'],
                $formData['state'],
                $formData['county'],
                $formData['payam_boma'],
                $formData['community_camp'],
                $formData['household_size'],
                $formData['children_under_5'],
                $formData['elderly_members'],
                $formData['disabled_members'],
                $formData['pregnant_lactating'],
                $formData['vulnerability_category'],
                $formData['ngo_id'],
                $formData['notes'],
                $id
            ]);

            log_audit(
                'BENEFICIARY_UPDATED',
                'Beneficiaries',
                (string)$id,
                "Updated details for {$formData['full_name']} ({$beneficiary['beneficiary_id']})"
            );

            flash_set('success', "Beneficiary details successfully updated.");
            header("Location: view.php?id=" . $id);
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Beneficiary Record</h4>
                <p class="text-muted small mb-0">Updating profile for <strong><?= e($beneficiary['full_name']) ?></strong> (<?= e($beneficiary['beneficiary_id']) ?>)</p>
            </div>
            <a href="view.php?id=<?= $beneficiary['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> View Profile
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Hidden ID for duplicate checker exclusion -->
        <input type="hidden" id="beneficiary_id_exclude" value="<?= $beneficiary['id'] ?>">
        <div id="duplicate_alert_container"></div>

        <form method="POST" action="edit.php?id=<?= $beneficiary['id'] ?>" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Section 1 -->
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-person-badge me-2"></i>Personal &amp; Demographic Profile
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Beneficiary ID</label>
                        <input type="text" class="form-control bg-light fw-bold" value="<?= e($beneficiary['beneficiary_id']) ?>" readonly>
                    </div>

                    <div class="col-md-5">
                        <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label for="gender" class="form-label small fw-semibold text-secondary">Gender <span class="text-danger">*</span></label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="Female" <?= $formData['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Male" <?= $formData['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Other" <?= $formData['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($formData['date_of_birth'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="estimated_age" class="form-label small fw-semibold text-secondary">Estimated Age (Years)</label>
                        <input type="number" min="0" max="120" class="form-control" id="estimated_age" name="estimated_age" value="<?= e($formData['estimated_age'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Contact Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($formData['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="id_document_type" class="form-label small fw-semibold text-secondary">ID Document Type</label>
                        <select class="form-select" id="id_document_type" name="id_document_type">
                            <?php foreach (ID_DOCUMENT_TYPES as $dt): ?>
                                <option value="<?= e($dt) ?>" <?= $formData['id_document_type'] === $dt ? 'selected' : '' ?>><?= e($dt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="id_document_number" class="form-label small fw-semibold text-secondary">Document Number</label>
                        <input type="text" class="form-control" id="id_document_number" name="id_document_number" value="<?= e($formData['id_document_number'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 2 -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-geo-alt me-2"></i>Geographic Location in South Sudan
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="state_select" class="form-label small fw-semibold text-secondary">State / Admin Area <span class="text-danger">*</span></label>
                        <select class="form-select" id="state_select" name="state" required>
                            <?php foreach (array_keys(SOUTH_SUDAN_LOCATIONS) as $st): ?>
                                <option value="<?= e($st) ?>" <?= $formData['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="county_select" class="form-label small fw-semibold text-secondary">County <span class="text-danger">*</span></label>
                        <select class="form-select" id="county_select" name="county" data-selected="<?= e($formData['county']) ?>" required>
                            <option value="<?= e($formData['county']) ?>"><?= e($formData['county']) ?></option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="payam_boma" class="form-label small fw-semibold text-secondary">Payam &amp; Boma <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="payam_boma" name="payam_boma" value="<?= e($formData['payam_boma']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="community_camp" class="form-label small fw-semibold text-secondary">Community / Settlement</label>
                        <input type="text" class="form-control" id="community_camp" name="community_camp" value="<?= e($formData['community_camp'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3 -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-people me-2"></i>Household Vulnerability &amp; Composition
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="vulnerability_category" class="form-label small fw-semibold text-secondary">Vulnerability Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="vulnerability_category" name="vulnerability_category" required>
                            <?php foreach (VULNERABILITY_CATEGORIES as $vc): ?>
                                <option value="<?= e($vc) ?>" <?= $formData['vulnerability_category'] === $vc ? 'selected' : '' ?>><?= e($vc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label for="household_size" class="form-label small fw-semibold text-secondary">Total Household Size</label>
                        <input type="number" min="1" max="50" class="form-control" id="household_size" name="household_size" value="<?= e($formData['household_size']) ?>" required>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="children_under_5" class="form-label small fw-semibold text-muted">Children Under 5</label>
                        <input type="number" min="0" max="25" class="form-control form-control-sm" id="children_under_5" name="children_under_5" value="<?= e($formData['children_under_5']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="elderly_members" class="form-label small fw-semibold text-muted">Elderly (60+)</label>
                        <input type="number" min="0" max="20" class="form-control form-control-sm" id="elderly_members" name="elderly_members" value="<?= e($formData['elderly_members']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="disabled_members" class="form-label small fw-semibold text-muted">Disabled (PWD)</label>
                        <input type="number" min="0" max="20" class="form-control form-control-sm" id="disabled_members" name="disabled_members" value="<?= e($formData['disabled_members']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="pregnant_lactating" class="form-label small fw-semibold text-muted">Pregnant/Lactating</label>
                        <input type="number" min="0" max="10" class="form-control form-control-sm" id="pregnant_lactating" name="pregnant_lactating" value="<?= e($formData['pregnant_lactating']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="notes" class="form-label small fw-semibold text-secondary">Assessment Remarks</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($formData['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="view.php?id=<?= $beneficiary['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Update Beneficiary Record
                </button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

