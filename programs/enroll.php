<?php
/**
 * Beneficiary Program Enrollment Module
 * Role-restricted: Admin, Registration Officer
 */

$pageTitle = "Enroll Beneficiary into Aid Program";
$activeNav = "programs";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'registration_officer']);
$currentUser = current_user();
$db = getDB();

$preselectedProg = (int)($_GET['program_id'] ?? 0);
$preselectedBen  = (int)($_GET['beneficiary_id'] ?? 0);

// Fetch Active Programs
$programs = $db->query("
    SELECT p.id, p.name, p.program_code, p.category, p.target_county, p.target_state, n.acronym
    FROM aid_programs p
    JOIN ngos n ON p.ngo_id = n.id
    WHERE p.status = 'Active'
    ORDER BY p.name ASC
")->fetchAll();

// Fetch Beneficiaries (prioritize verified)
$beneficiaries = $db->query("
    SELECT b.id, b.beneficiary_id, b.full_name, b.state, b.county, b.verification_status, b.vulnerability_category
    FROM beneficiaries b
    ORDER BY b.id DESC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $progId  = (int)($_POST['program_id'] ?? 0);
        $benId   = (int)($_POST['beneficiary_id'] ?? 0);
        $date    = sanitize($_POST['enrollment_date'] ?? date('Y-m-d'));
        $notes   = sanitize($_POST['notes'] ?? '');

        if ($progId <= 0) $errors[] = "Please select an active Aid Program.";
        if ($benId <= 0) $errors[] = "Please select a registered Beneficiary.";

        // Check if already enrolled
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM program_enrollments WHERE program_id = ? AND beneficiary_id = ? LIMIT 1");
            $chk->execute([$progId, $benId]);
            if ($chk->fetch()) {
                $errors[] = "This beneficiary is ALREADY enrolled in the selected program.";
            }
        }

        if (empty($errors)) {
            $insert = $db->prepare("
                INSERT INTO program_enrollments (program_id, beneficiary_id, enrollment_date, status, enrolled_by, notes, created_at)
                VALUES (?, ?, ?, 'Enrolled', ?, ?, NOW())
            ");
            $insert->execute([$progId, $benId, $date, $currentUser['id'], $notes]);

            log_audit(
                'PROGRAM_ENROLLMENT',
                'Programs',
                (string)$progId,
                "Enrolled Beneficiary #{$benId} into Program #{$progId}"
            );

            flash_set('success', "Beneficiary successfully enrolled in the humanitarian aid program.");
            header('Location: view.php?id=' . $progId);
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-check-fill text-success me-2"></i>Enroll Beneficiary into Aid Program</h4>
                <p class="text-muted small mb-0">Assign verified households to authorized humanitarian relief distribution cycles.</p>
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

        <form method="POST" action="enroll.php" class="card shadow-sm border-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Select Aid Program <span class="text-danger">*</span></label>
                    <select name="program_id" class="form-select" required>
                        <option value="">-- Choose Program --</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $preselectedProg == $p['id'] ? 'selected' : '' ?>>
                                [<?= e($p['acronym']) ?>] <?= e($p['program_code']) ?> &bull; <?= e($p['name']) ?> (<?= e($p['target_county']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Select Beneficiary <span class="text-danger">*</span></label>
                    <select name="beneficiary_id" class="form-select" required>
                        <option value="">-- Choose Beneficiary --</option>
                        <?php foreach ($beneficiaries as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $preselectedBen == $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['beneficiary_id']) ?> &bull; <?= e($b['full_name']) ?> (<?= e($b['county']) ?>, <?= e($b['state']) ?>) [<?= e($b['verification_status']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Enrollment Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Enrollment &amp; Allocation Notes</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Eligibility justification, proxy collection permissions, or special instructions..."></textarea>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Complete Enrollment
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

