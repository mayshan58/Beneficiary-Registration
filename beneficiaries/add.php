<?php
/**
 * Beneficiary Registration Form
 * Role-restricted: Admin, Registration Officer
 */

$pageTitle = "Register New Beneficiary";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'registration_officer']);
$currentUser = current_user();
$db = getDB();

// Fetch NGOs for dropdown
$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];
$generatedId = generate_beneficiary_id();

// Form default values
$formData = [
    'beneficiary_id'         => $generatedId,
    'full_name'              => '',
    'gender'                 => '',
    'date_of_birth'          => '',
    'estimated_age'          => '',
    'phone'                  => '',
    'id_document_type'       => 'Chief / Local Recommendation Letter',
    'id_document_number'     => '',
    'state'                  => 'Central Equatoria',
    'county'                 => 'Juba',
    'payam_boma'             => '',
    'community_camp'         => '',
    'household_size'         => 1,
    'children_under_5'       => 0,
    'elderly_members'        => 0,
    'disabled_members'       => 0,
    'pregnant_lactating'     => 0,
    'vulnerability_category' => 'Host Community Vulnerable',
    'registration_date'      => date('Y-m-d'),
    'ngo_id'                 => $currentUser['ngo_id'] ?? ($ngos[0]['id'] ?? null),
    'notes'                  => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token expired or invalid. Please resubmit the form.";
    } else {
        // Collect & Sanitize Form Input
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
        $formData['registration_date']      = sanitize($_POST['registration_date'] ?? date('Y-m-d'));
        $formData['ngo_id']                 = !empty($_POST['ngo_id']) ? (int)$_POST['ngo_id'] : ($currentUser['ngo_id'] ?? null);
        $formData['notes']                  = sanitize($_POST['notes'] ?? '');

        // Validation Rules
        if (empty($formData['full_name'])) {
            $errors[] = "Full Name is required.";
        }
        if (empty($formData['gender']) || !in_array($formData['gender'], ['Male', 'Female', 'Other'])) {
            $errors[] = "Valid Gender is required.";
        }
        if (empty($formData['state']) || empty($formData['county'])) {
            $errors[] = "State and County are required for geographical targeting.";
        }
        if (empty($formData['payam_boma'])) {
            $errors[] = "Payam / Boma location is required.";
        }
        if (empty($formData['vulnerability_category'])) {
            $errors[] = "Vulnerability category must be specified.";
        }

        // Calculate age from DOB if DOB provided and age left blank
        if (!empty($formData['date_of_birth']) && empty($formData['estimated_age'])) {
            $birthDate = new DateTime($formData['date_of_birth']);
            $today = new DateTime('today');
            $formData['estimated_age'] = $birthDate->diff($today)->y;
        }

        if (empty($errors)) {
            // Check for potential duplicates
            $detectedDuplicates = detect_duplicates(
                $formData['full_name'],
                $formData['date_of_birth'],
                $formData['phone'],
                $formData['id_document_number'],
                $formData['state'],
                $formData['county']
            );

            $isDuplicateFlag = !empty($detectedDuplicates) ? 1 : 0;
            $newBenId = generate_beneficiary_id();

            $insertSql = "
                INSERT INTO beneficiaries (
                    beneficiary_id, full_name, gender, date_of_birth, estimated_age,
                    phone, id_document_type, id_document_number, state, county,
                    payam_boma, community_camp, household_size, children_under_5,
                    elderly_members, disabled_members, pregnant_lactating,
                    vulnerability_category, registration_date, verification_status,
                    registered_by, ngo_id, is_duplicate_flag, notes, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, 'Pending',
                    ?, ?, ?, ?, NOW()
                )
            ";

            $stmt = $db->prepare($insertSql);
            $stmt->execute([
                $newBenId,
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
                $formData['registration_date'],
                $currentUser['id'],
                $formData['ngo_id'],
                $isDuplicateFlag,
                $formData['notes']
            ]);

            $newRecordId = (int)$db->lastInsertId();

            // Log flagged duplicate relationships if detected
            if ($isDuplicateFlag && !empty($detectedDuplicates)) {
                $dupInsertStmt = $db->prepare("
                    INSERT INTO duplicate_logs (primary_beneficiary_id, matched_beneficiary_id, match_type, confidence_score, details, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'Flagged', NOW())
                ");
                foreach ($detectedDuplicates as $dup) {
                    $dupInsertStmt->execute([
                        $dup['record']['id'], // primary existing record
                        $newRecordId,         // newly registered record
                        $dup['match_type'],
                        $dup['confidence'],
                        $dup['details']
                    ]);
                }
            }

            // System Audit Logging
            log_audit(
                'BENEFICIARY_REGISTERED',
                'Beneficiaries',
                (string)$newRecordId,
                "Registered beneficiary '{$formData['full_name']}' ({$newBenId})" . ($isDuplicateFlag ? " [Flagged Potential Duplicate]" : "")
            );

            if ($isDuplicateFlag) {
                flash_set('warning', "Beneficiary {$formData['full_name']} registered successfully as {$newBenId}, but was FLAGGED for potential duplicate review.");
            } else {
                flash_set('success', "Beneficiary {$formData['full_name']} registered successfully with ID: {$newBenId}.");
            }

            header("Location: view.php?id=" . $newRecordId);
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-plus-fill text-success me-2"></i>Register New Beneficiary</h4>
                <p class="text-muted small mb-0">Complete the standard humanitarian registration form for South Sudan operations.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>Please correct the following errors:</div>
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Dynamic Live Duplicate Warning Container (Filled via AJAX in app.js) -->
        <div id="duplicate_alert_container"></div>

        <form method="POST" action="add.php" autocomplete="off" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Section 1: Identification & Personal Information -->
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-person-badge me-2"></i>Section 1: Identification &amp; Personal Profile
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- Auto ID Preview -->
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">System Generated Beneficiary ID</label>
                        <input type="text" class="form-control bg-light fw-bold text-primary" value="<?= e($generatedId) ?>" readonly>
                        <small class="text-muted">Unique identifier in South Sudan registry</small>
                    </div>

                    <!-- Full Name -->
                    <div class="col-md-5">
                        <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name (First, Middle, Surname) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required placeholder="e.g. Mary Nyibol Deng">
                    </div>

                    <!-- Gender -->
                    <div class="col-md-3">
                        <label for="gender" class="form-label small fw-semibold text-secondary">Gender <span class="text-danger">*</span></label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Female" <?= $formData['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Male" <?= $formData['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Other" <?= $formData['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-3">
                        <label for="date_of_birth" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($formData['date_of_birth'] ?? '') ?>">
                        <small class="text-muted">If exact date is known</small>
                    </div>

                    <!-- Estimated Age -->
                    <div class="col-md-3">
                        <label for="estimated_age" class="form-label small fw-semibold text-secondary">Estimated Age (Years)</label>
                        <input type="number" min="0" max="120" class="form-control" id="estimated_age" name="estimated_age" value="<?= e($formData['estimated_age'] ?? '') ?>" placeholder="e.g. 38">
                        <small class="text-muted">Or enter approximate age</small>
                    </div>

                    <!-- Contact Phone -->
                    <div class="col-md-3">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Contact Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($formData['phone']) ?>" placeholder="e.g. +211 921 000 000">
                    </div>

                    <!-- Registration Date -->
                    <div class="col-md-3">
                        <label for="registration_date" class="form-label small fw-semibold text-secondary">Registration Date</label>
                        <input type="date" class="form-control" id="registration_date" name="registration_date" value="<?= e($formData['registration_date']) ?>" required>
                    </div>

                    <!-- Document Type -->
                    <div class="col-md-6">
                        <label for="id_document_type" class="form-label small fw-semibold text-secondary">Official ID Document Type</label>
                        <select class="form-select" id="id_document_type" name="id_document_type">
                            <?php foreach (ID_DOCUMENT_TYPES as $docType): ?>
                                <option value="<?= e($docType) ?>" <?= $formData['id_document_type'] === $docType ? 'selected' : '' ?>><?= e($docType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Document Number -->
                    <div class="col-md-6">
                        <label for="id_document_number" class="form-label small fw-semibold text-secondary">Document / Recommendation Number</label>
                        <input type="text" class="form-control" id="id_document_number" name="id_document_number" value="<?= e($formData['id_document_number']) ?>" placeholder="e.g. REC/MNK/2026/041 or SS-NAT-123456">
                    </div>
                </div>
            </div>

            <!-- Section 2: Location & Geographic Details (South Sudan Structure) -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-geo-alt me-2"></i>Section 2: Location &amp; Settlement Details (South Sudan)
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- State -->
                    <div class="col-md-6">
                        <label for="state_select" class="form-label small fw-semibold text-secondary">State / Administrative Area <span class="text-danger">*</span></label>
                        <select class="form-select" id="state_select" name="state" required>
                            <option value="">-- Select State --</option>
                            <?php foreach (array_keys(SOUTH_SUDAN_LOCATIONS) as $st): ?>
                                <option value="<?= e($st) ?>" <?= $formData['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- County (Dynamic via JavaScript) -->
                    <div class="col-md-6">
                        <label for="county_select" class="form-label small fw-semibold text-secondary">County <span class="text-danger">*</span></label>
                        <select class="form-select" id="county_select" name="county" data-selected="<?= e($formData['county']) ?>" required>
                            <option value="">-- Select County --</option>
                        </select>
                    </div>

                    <!-- Payam / Boma -->
                    <div class="col-md-6">
                        <label for="payam_boma" class="form-label small fw-semibold text-secondary">Payam &amp; Boma <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="payam_boma" name="payam_boma" value="<?= e($formData['payam_boma']) ?>" required placeholder="e.g. Munuki Payam / Block 2">
                    </div>

                    <!-- Community / Camp / Settlement -->
                    <div class="col-md-6">
                        <label for="community_camp" class="form-label small fw-semibold text-secondary">Community, Village, or IDP Camp Name</label>
                        <input type="text" class="form-control" id="community_camp" name="community_camp" value="<?= e($formData['community_camp']) ?>" placeholder="e.g. Mahad IDP Settlement, PoC Site, or Village">
                    </div>
                </div>
            </div>

            <!-- Section 3: Household Composition & Vulnerability -->
            <div class="card-header bg-white py-3 border-bottom border-top">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="bi bi-people me-2"></i>Section 3: Household Composition &amp; Vulnerability Profile
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- Vulnerability Category -->
                    <div class="col-md-7">
                        <label for="vulnerability_category" class="form-label small fw-semibold text-secondary">Primary Vulnerability Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="vulnerability_category" name="vulnerability_category" required>
                            <option value="">-- Select Vulnerability --</option>
                            <?php foreach (VULNERABILITY_CATEGORIES as $vc): ?>
                                <option value="<?= e($vc) ?>" <?= $formData['vulnerability_category'] === $vc ? 'selected' : '' ?>><?= e($vc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Total Household Size -->
                    <div class="col-md-5">
                        <label for="household_size" class="form-label small fw-semibold text-secondary">Total Household Size (Members) <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="50" class="form-control" id="household_size" name="household_size" value="<?= e($formData['household_size']) ?>" required>
                    </div>

                    <!-- Breakdown Sub-categories -->
                    <div class="col-sm-6 col-md-3">
                        <label for="children_under_5" class="form-label small fw-semibold text-muted">Children Under 5</label>
                        <input type="number" min="0" max="25" class="form-control form-control-sm" id="children_under_5" name="children_under_5" value="<?= e($formData['children_under_5']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="elderly_members" class="form-label small fw-semibold text-muted">Elderly (60+ Years)</label>
                        <input type="number" min="0" max="20" class="form-control form-control-sm" id="elderly_members" name="elderly_members" value="<?= e($formData['elderly_members']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="disabled_members" class="form-label small fw-semibold text-muted">Persons with Disability</label>
                        <input type="number" min="0" max="20" class="form-control form-control-sm" id="disabled_members" name="disabled_members" value="<?= e($formData['disabled_members']) ?>">
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <label for="pregnant_lactating" class="form-label small fw-semibold text-muted">Pregnant / Lactating</label>
                        <input type="number" min="0" max="10" class="form-control form-control-sm" id="pregnant_lactating" name="pregnant_lactating" value="<?= e($formData['pregnant_lactating']) ?>">
                    </div>

                    <!-- Registering NGO Organization -->
                    <div class="col-md-6">
                        <label for="ngo_id" class="form-label small fw-semibold text-secondary">Registering Humanitarian Organization</label>
                        <select class="form-select" id="ngo_id" name="ngo_id" <?= !has_role('admin') ? 'disabled' : '' ?>>
                            <?php foreach ($ngos as $n): ?>
                                <option value="<?= $n['id'] ?>" <?= $formData['ngo_id'] == $n['id'] ? 'selected' : '' ?>>
                                    <?= e($n['acronym']) ?> - <?= e($n['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!has_role('admin')): ?>
                            <input type="hidden" name="ngo_id" value="<?= e($formData['ngo_id']) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Additional Notes -->
                    <div class="col-md-6">
                        <label for="notes" class="form-label small fw-semibold text-secondary">Additional Assessment Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Specific displacement details, shelter conditions, or special protection remarks..."><?= e($formData['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Submit Footer -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted">
                    <i class="bi bi-shield-lock me-1"></i> Beneficiary information is handled with strict humanitarian data protection protocols.
                </span>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Register Beneficiary
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

