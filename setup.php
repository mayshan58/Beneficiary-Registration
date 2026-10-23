<?php
/**
 * Database Setup and Seeder Utility
 * NGO Beneficiary Registration System
 */

$pageTitle = "Database Setup & Diagnostics";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Connect without db name first to create database if missing
        $pdoInit = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        if ($_POST['action'] === 'install') {
            $schemaFile = __DIR__ . '/database/schema.sql';
            $seedFile = __DIR__ . '/database/seed.sql';

            if (!file_exists($schemaFile) || !file_exists($seedFile)) {
                throw new Exception("SQL schema or seed files are missing in database/ directory.");
            }

            $schemaSql = file_get_contents($schemaFile);
            $pdoInit->exec($schemaSql);

            $seedSql = file_get_contents($seedFile);
            $pdoInit->exec($seedSql);

            $message = "Database schema and demo dataset successfully installed! You can now log in.";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Check database status
$dbStatus = false;
$tableStats = [];
try {
    $db = getDB();
    $dbStatus = true;
    
    $tables = ['users', 'ngos', 'aid_programs', 'beneficiaries', 'program_enrollments', 'aid_distributions', 'duplicate_logs', 'audit_logs'];
    foreach ($tables as $t) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM `{$t}`");
            $tableStats[$t] = $stmt->fetch()['cnt'];
        } catch (Exception $e) {
            $tableStats[$t] = 'Not Found';
        }
    }
} catch (Exception $e) {
    $dbStatus = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 800px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white p-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-database-gear fs-2 me-3 text-warning"></i>
                <div>
                    <h4 class="mb-1 fw-bold">Database Diagnostic &amp; Setup Tool</h4>
                    <p class="mb-0 text-white-50 small">Automated database initialization for NGO Beneficiary Registration System</p>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mb-4">
                    <?= e($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-white">
                        <small class="text-muted d-block mb-1">Database Connection</small>
                        <?php if ($dbStatus): ?>
                            <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i> Connected to <?= DB_NAME ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i> Not Connected</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 border rounded bg-white">
                        <small class="text-muted d-block mb-1">Host &amp; User</small>
                        <span class="fw-semibold text-dark"><?= DB_USER ?>@<?= DB_HOST ?>:<?= DB_PORT ?></span>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold mb-3"><i class="bi bi-table me-2 text-primary"></i>Database Tables Status</h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Table Name</th>
                            <th>Status / Record Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tableStats)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Database is not yet initialized.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tableStats as $tbl => $cnt): ?>
                                <tr>
                                    <td><code><?= e($tbl) ?></code></td>
                                    <td>
                                        <?php if ($cnt === 'Not Found'): ?>
                                            <span class="badge bg-danger">Missing Table</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $cnt ?> Records</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 bg-light rounded border mb-4">
                <h6 class="fw-bold mb-2">Actions</h6>
                <p class="small text-muted mb-3">
                    Click the button below to initialize or reset the database. This executes <code>database/schema.sql</code> and populates realistic demo records from <code>database/seed.sql</code>.
                </p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to initialize/reset the database? Existing data will be refreshed with seed data.');">
                    <input type="hidden" name="action" value="install">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i> Install / Reset Database &amp; Seed Data
                    </button>
                    <a href="auth/login.php" class="btn btn-outline-secondary px-3 ms-2">
                        Go to Sign In &rarr;
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

