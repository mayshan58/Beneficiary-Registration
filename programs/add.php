<?php
/**
 * Create Humanitarian Aid Program
 * Role-restricted: Administrator
 */

$pageTitle = "Create Aid Program";
$activeNav = "programs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

// Fetch NGOs
$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];
$preselectedNgo = (int)($_GET['ngo_id'] ?? ($ngos[0]['id'] ?? 0));

$formData = [
    'ngo_id'               => $preselectedNgo,
    'program_code'         => '',
    'name'                 => '',
    'category'             => 'Food Assistance',
    'description'          => '',
    'target_beneficiaries' => 1000,
    'item_description'     => 'Sorghum 50kg, Split Peas 5kg, Cooking Oil 4.5L, Salt 0.5kg',
    'allocation_quota'     => 1.00,
    'unit'                 => 'Ration Kit',
    'target_state'         => 'Central Equatoria',
    'target_county'        => 'Juba',
    'start_date'           => date('Y-m-d'),
    'end_date'             => date('Y-m-d', strtotime('+6 months')),
    'status'               => 'Active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $formData['ngo_id']               = (int)($_POST['ngo_id'] ?? 0);
        $formData['program_code']         = strtoupper(sanitize($_POST['program_code'] ?? ''));
        $formData['name']                 = sanitize($_POST['name'] ?? '');
        $formData['category']             = sanitize($_POST['category'] ?? '');
        $formData['description']          = sanitize($_POST['description'] ?? '');
        $formData['target_beneficiaries'] = max(1, (int)($_POST['target_beneficiaries'] ?? 1));
        $formData['item_description']     = sanitize($_POST['item_description'] ?? '');
        $formData['allocation_quota']     = (float)($_POST['allocation_quota'] ?? 1.0);
        $formData['unit']                 = sanitize($_POST['unit'] ?? 'Kit');
        $formData['target_state']         = sanitize($_POST['target_state'] ?? '');
        $formData['target_county']        = sanitize($_POST['target_county'] ?? '');
        $formData['start_date']           = sanitize($_POST['start_date'] ?? date('Y-m-d'));
        $formData['end_date']             = sanitize($_POST['end_date'] ?? date('Y-m-d'));
        $formData['status']               = in_array($_POST['status'] ?? '', ['Active', 'Planned', 'Completed', 'Suspended']) ? $_POST['status'] : 'Active';

        if (empty($formData['ngo_id'])) $errors[] = "Implementing NGO is required.";
        if (empty($formData['program_code'])) $errors[] = "Unique Program Code is required.";
        if (empty($formData['name'])) $errors[] = "Program Name is required.";
        if (empty($formData['category'])) $errors[] = "Assistance Sector is required.";
        if (empty($formData['item_description'])) $errors[] = "Relief Item description is required.";
        if (empty($formData['target_state']) || empty($formData['target_county'])) $errors[] = "Target State and County are required.";

        // Unique program_code check
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM aid_programs WHERE program_code = ? LIMIT 1");
            $chk->execute([$formData['program_code']]);
            if ($chk->fetch()) {
                $errors[] = "A program with code '{$formData['program_code']}' already exists.";
            }
        }

        if (empty($errors)) {
            $insert = $db->prepare("
                INSERT INTO aid_programs (
                    ngo_id, program_code, name, category, description,
                    target_beneficiaries, item_description, allocation_quota, unit,
                    target_state, target_county, start_date, end_date, status,
                    created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");
            $insert->execute([
                $formData['ngo_id'],
                $formData['program_code'],
                $formData['name'],
                $formData['category'],
                $formData['description'],
                $formData['target_beneficiaries'],
                $formData['item_description'],
                $formData['allocation_quota'],
                $formData['unit'],
                $formData['target_state'],
                $formData['target_county'],
                $formData['start_date'],
                $formData['end_date'],
                $formData['status'],
                $currentUser['id']
            ]);

            $newId = (int)$db->lastInsertId();
            log_audit('PROGRAM_CREATED', 'Programs', (string)$newId, "Created program '{$formData['name']}' ({$formData['program_code']})");

            flash_set('success', "Aid Program '{$formData['name']}' created successfully.");
            header('Location: view.php?id=' . $newId);
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
                <h4 class="fw-bold mb-1"><i class="bi bi-basket-fill text-primary me-2"></i>Create Humanitarian Aid Program</h4>
                <p class="text-muted small mb-0">Define aid assistance sector, ration kits, targeting parameters, and quotas.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Programs
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="add.php" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Implementing NGO Partner <span class="text-danger">*</span></label>
                        <select name="ngo_id" class="form-select" required>
                            <?php foreach ($ngos as $ngo): ?>
                                <option value="<?= $ngo['id'] ?>" <?= $formData['ngo_id'] == $ngo['id'] ? 'selected' : '' ?>>
                                    <?= e($ngo['acronym']) ?> - <?= e($ngo['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Program Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_code" value="<?= e($formData['program_code']) ?>" required placeholder="e.g. WFP-GFD-2026-08">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-secondary">Program Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= e($formData['name']) ?>" required placeholder="e.g. Flood Emergency General Food Assistance">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Assistance Sector / Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <?php foreach (AID_CATEGORIES as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= $formData['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Relief Items / Package Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_description" value="<?= e($formData['item_description']) ?>" required placeholder="e.g. Sorghum 50kg, Split Peas 5kg, Oil 4.5L, Salt 0.5kg or $75 USD Cash Voucher">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Allocation Quota per Distribution <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.1" class="form-control" name="allocation_quota" value="<?= e($formData['allocation_quota']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Unit of Measure <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="unit" value="<?= e($formData['unit']) ?>" required placeholder="e.g. Full Family Ration Kit, USD Voucher">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Target Beneficiaries Count <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="target_beneficiaries" value="<?= e($formData['target_beneficiaries']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="state_select" class="form-label small fw-semibold text-secondary">Target State / Admin Area <span class="text-danger">*</span></label>
                        <select class="form-select" id="state_select" name="target_state" required>
                            <option value="">-- Select State --</option>
                            <?php foreach (array_keys(SOUTH_SUDAN_LOCATIONS) as $st): ?>
                                <option value="<?= e($st) ?>" <?= $formData['target_state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="county_select" class="form-label small fw-semibold text-secondary">Target County <span class="text-danger">*</span></label>
                        <select class="form-select" id="county_select" name="target_county" data-selected="<?= e($formData['target_county']) ?>" required>
                            <option value="<?= e($formData['target_county']) ?>"><?= e($formData['target_county']) ?></option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="start_date" value="<?= e($formData['start_date']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="end_date" value="<?= e($formData['end_date']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Program Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= $formData['status'] === 'Active' ? 'selected' : '' ?>>Active (Ongoing)</option>
                            <option value="Planned" <?= $formData['status'] === 'Planned' ? 'selected' : '' ?>>Planned (Upcoming)</option>
                            <option value="Completed" <?= $formData['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Suspended" <?= $formData['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Detailed Program Description / Objectives</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Explain the humanitarian context, funding donor, and target vulnerability criteria..."><?= e($formData['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Create Aid Program
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

