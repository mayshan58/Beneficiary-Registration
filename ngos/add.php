<?php
/**
 * Register New NGO Partner
 * Role-restricted: Administrator
 */

$pageTitle = "Register Humanitarian NGO";
$activeNav = "ngos";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$currentUser = current_user();
$db = getDB();

$errors = [];
$formData = [
    'name'                => '',
    'acronym'             => '',
    'registration_number' => '',
    'contact_person'      => '',
    'email'               => '',
    'phone'               => '',
    'office_address'      => '',
    'headquarters'        => 'Juba, South Sudan',
    'status'              => 'active'
];

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
        if (empty($formData['contact_person'])) $errors[] = "Contact Person name is required.";
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid official email is required.";
        if (empty($formData['phone'])) $errors[] = "Contact Phone number is required.";
        if (empty($formData['office_address'])) $errors[] = "Office address is required.";

        // Check if registration number already exists
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM ngos WHERE registration_number = ? LIMIT 1");
            $chk->execute([$formData['registration_number']]);
            if ($chk->fetch()) {
                $errors[] = "An NGO with this registration number ({$formData['registration_number']}) is already registered.";
            }
        }

        if (empty($errors)) {
            $insert = $db->prepare("
                INSERT INTO ngos (name, acronym, registration_number, contact_person, email, phone, office_address, headquarters, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([
                $formData['name'],
                $formData['acronym'],
                $formData['registration_number'],
                $formData['contact_person'],
                $formData['email'],
                $formData['phone'],
                $formData['office_address'],
                $formData['headquarters'],
                $formData['status']
            ]);

            $newId = $db->lastInsertId();
            log_audit('NGO_REGISTERED', 'NGOs', (string)$newId, "Registered NGO: {$formData['name']} ({$formData['acronym']})");

            flash_set('success', "NGO '{$formData['name']}' registered successfully.");
            header('Location: index.php');
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
                <h4 class="fw-bold mb-1"><i class="bi bi-building-add text-primary me-2"></i>Register Humanitarian NGO</h4>
                <p class="text-muted small mb-0">Record a new implementing agency or UN partner operating in South Sudan.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to NGOs
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
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-secondary">Official NGO Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= e($formData['name']) ?>" required placeholder="e.g. World Vision South Sudan">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Acronym / Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="acronym" value="<?= e($formData['acronym']) ?>" required placeholder="e.g. WVSS">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">RRC Registration Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="registration_number" value="<?= e($formData['registration_number']) ?>" required placeholder="e.g. RRC/NGO/2026/099">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Headquarters Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="headquarters" value="<?= e($formData['headquarters']) ?>" required placeholder="e.g. Juba, South Sudan">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Focal Contact Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="contact_person" value="<?= e($formData['contact_person']) ?>" required placeholder="e.g. Mary Chol Deng">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Contact Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" value="<?= e($formData['phone']) ?>" required placeholder="e.g. +211 920 000 000">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Official Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= e($formData['email']) ?>" required placeholder="e.g. info@ngo-ss.org">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Operational Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Authorized)</option>
                            <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Physical Office Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="office_address" rows="2" required placeholder="e.g. Plot 15, Hai Malakal, Ministries Road, Juba"><?= e($formData['office_address']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Register NGO
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

