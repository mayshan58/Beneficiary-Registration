<?php
/**
 * Edit NGO Information
 * Role-restricted: Administrator
 */

$pageTitle = "Edit NGO Partner";
$activeNav = "ngos";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'Invalid NGO ID.');
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$id]);
$ngo = $stmt->fetch();

if (!$ngo) {
    flash_set('danger', 'NGO record not found.');
    header('Location: index.php');
    exit();
}

$errors = [];
$formData = $ngo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $formData['name']                = sanitize($_POST['name'] ?? '');
        $formData['acronym']             = strtoupper(sanitize($_POST['acronym'] ?? ''));
        $formData['registration_number'] = sanitize($_POST['registration_number'] ?? '');
        $formData['contact_person']      = sanitize($_POST['contact_person'] ?? '');
        $formData['email']               = sanitize($_POST['email'] ?? '');
        $formData['phone']               = sanitize($_POST['phone'] ?? '');
        $formData['office_address']      = sanitize($_POST['office_address'] ?? '');
        $formData['headquarters']        = sanitize($_POST['headquarters'] ?? 'Juba, South Sudan');
        $formData['status']              = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($formData['name'])) $errors[] = "NGO Name is required.";
        if (empty($formData['acronym'])) $errors[] = "NGO Acronym is required.";
        if (empty($formData['registration_number'])) $errors[] = "RRC Registration Number is required.";
        if (empty($formData['contact_person'])) $errors[] = "Contact Person is required.";
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($formData['phone'])) $errors[] = "Phone number is required.";
        if (empty($formData['office_address'])) $errors[] = "Office address is required.";

        // Unique check
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM ngos WHERE registration_number = ? AND id != ? LIMIT 1");
            $chk->execute([$formData['registration_number'], $id]);
            if ($chk->fetch()) {
                $errors[] = "Another NGO is already using registration number '{$formData['registration_number']}'.";
            }
        }

        if (empty($errors)) {
            $update = $db->prepare("
                UPDATE ngos 
                SET name = ?, acronym = ?, registration_number = ?, contact_person = ?, email = ?, phone = ?, office_address = ?, headquarters = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([
                $formData['name'],
                $formData['acronym'],
                $formData['registration_number'],
                $formData['contact_person'],
                $formData['email'],
                $formData['phone'],
                $formData['office_address'],
                $formData['headquarters'],
                $formData['status'],
                $id
            ]);

            log_audit('NGO_UPDATED', 'NGOs', (string)$id, "Updated NGO profile for {$formData['name']}");

            flash_set('success', "NGO details successfully updated.");
            header('Location: view.php?id=' . $id);
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Edit NGO Information</h4>
                <p class="text-muted small mb-0">Modify details for <strong><?= e($ngo['name']) ?></strong> (<?= e($ngo['acronym']) ?>)</p>
            </div>
            <a href="view.php?id=<?= $ngo['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> View Profile
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

        <form method="POST" action="edit.php?id=<?= $ngo['id'] ?>" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-secondary">NGO Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= e($formData['name']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Acronym <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="acronym" value="<?= e($formData['acronym']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">RRC Registration Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="registration_number" value="<?= e($formData['registration_number']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Headquarters Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="headquarters" value="<?= e($formData['headquarters']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="contact_person" value="<?= e($formData['contact_person']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" value="<?= e($formData['phone']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Official Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= e($formData['email']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Operational Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Office Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="office_address" rows="2" required><?= e($formData['office_address']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="view.php?id=<?= $ngo['id'] ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Update NGO Record
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

