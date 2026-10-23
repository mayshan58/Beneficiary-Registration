<?php
/**
 * Edit Humanitarian Aid Program
 * Role-restricted: Administrator
 */

$pageTitle = "Edit Aid Program";
$activeNav = "programs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid program ID.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM aid_programs WHERE id = ?");
$stmt->execute([$id]);
$program = $stmt->fetch();

if (!$program) {
    flash_set('danger', 'Program record not found.');
    header('Location: index.php');
    exit();
}

$ngos = $db->query("SELECT id, name, acronym FROM ngos WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$errors = [];
$formData = $program;

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
        if (empty($formData['program_code'])) $errors[] = "Program Code is required.";
        if (empty($formData['name'])) $errors[] = "Program Name is required.";
        if (empty($formData['category'])) $errors[] = "Assistance Sector is required.";
        if (empty($formData['item_description'])) $errors[] = "Relief Item description is required.";

        // Unique code check
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM aid_programs WHERE program_code = ? AND id != ? LIMIT 1");
            $chk->execute([$formData['program_code'], $id]);
            if ($chk->fetch()) {
                $errors[] = "Another program is already registered with code '{$formData['program_code']}'.";
            }
        }

        if (empty($errors)) {
            $update = $db->prepare("
                UPDATE aid_programs SET
                    ngo_id = ?, program_code = ?, name = ?, category = ?, description = ?,
                    target_beneficiaries = ?, item_description = ?, allocation_quota = ?, unit = ?,
                    target_state = ?, target_county = ?, start_date = ?, end_date = ?, status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([
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
                $id
            ]);

            log_audit('PROGRAM_UPDATED', 'Programs', (string)$id, "Updated aid program: {$formData['name']}");

            flash_set('success', "Aid Program successfully updated.");
            header('Location: view.php?id=' . $id);
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
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Aid Program</h4>
                <p class="text-muted small mb-0">Modify program parameters for <strong><?= e($program['name']) ?></strong></p>
            </div>
            <a href="view.php?id=<?= $program['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Program
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

        <form method="POST" action="edit.php?id=<?= $program['id'] ?>" class="card shadow-sm border-0">
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
                        <input type="text" class="form-control" name="program_code" value="<?= e($formData['program_code']) ?>" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-secondary">Program Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= e($formData['name']) ?>" required>
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
                        <label class="form-label small fw-semibold text-secondary">Relief Package Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_description" value="<?= e($formData['item_description']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Allocation Quota <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.1" class="form-control" name="allocation_quota" value="<?= e($formData['allocation_quota']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Unit of Measure <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="unit" value="<?= e($formData['unit']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Target Beneficiaries Count <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="target_beneficiaries" value="<?= e($formData['target_beneficiaries']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="state_select" class="form-label small fw-semibold text-secondary">Target State <span class="text-danger">*</span></label>
                        <select class="form-select" id="state_select" name="target_state" required>
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
                            <option value="Active" <?= $formData['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Planned" <?= $formData['status'] === 'Planned' ? 'selected' : '' ?>>Planned</option>
                            <option value="Completed" <?= $formData['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Suspended" <?= $formData['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Detailed Program Description</label>
                        <textarea class="form-control" name="description" rows="3"><?= e($formData['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="view.php?id=<?= $program['id'] ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Update Program
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

