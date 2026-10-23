<?php
/**
 * Global Header Component
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Humanitarian Registration Platform';
$activeNav = $activeNav ?? '';
$flashMessages = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Bootstrap 5 CSS (Local with CDN fallback) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

    <script>
        window.APP_BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body>

<!-- Main Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom no-print sticky-top py-2">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>index.php">
            <span class="fs-4 me-2"><i class="bi bi-shield-check text-warning"></i></span>
            <div>
                <span class="fw-bold tracking-tight">NGO Beneficiary System</span>
                <span class="brand-badge ms-2 d-none d-sm-inline-block">South Sudan</span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>

                <!-- Beneficiaries Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $activeNav === 'beneficiaries' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-people-fill me-1"></i> Beneficiaries
                    </a>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>beneficiaries/add.php">
                                <i class="bi bi-person-plus-fill text-success me-2"></i> Register New Beneficiary
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>beneficiaries/index.php">
                                <i class="bi bi-card-list text-primary me-2"></i> All Beneficiaries Directory
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>beneficiaries/verify.php">
                                <i class="bi bi-patch-check-fill text-warning me-2"></i> Verification Queue
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>beneficiaries/duplicates.php">
                                <i class="bi bi-copy text-danger me-2"></i> Duplicate Detection Center
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Aid Programs -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'programs' ? 'active' : '' ?>" href="<?= BASE_URL ?>programs/index.php">
                        <i class="bi bi-basket3-fill me-1"></i> Aid Programs
                    </a>
                </li>

                <!-- Aid Distribution -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $activeNav === 'distributions' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-box-seam-fill me-1"></i> Aid Distribution
                    </a>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>distributions/record.php">
                                <i class="bi bi-plus-circle-fill text-success me-2"></i> Record New Distribution
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>distributions/index.php">
                                <i class="bi bi-clock-history text-primary me-2"></i> Distribution History & Logs
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Reports -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>reports/index.php">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Reports
                    </a>
                </li>

                <!-- Admin Only: NGOs, Users, Audit Logs -->
                <?php if (has_role('admin')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($activeNav, ['ngos', 'users', 'logs']) ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill me-1"></i> Administration
                        </a>
                        <ul class="dropdown-menu shadow-sm border-0">
                            <li>
                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>ngos/index.php">
                                    <i class="bi bi-building-fill text-info me-2"></i> Manage NGOs
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>users/index.php">
                                    <i class="bi bi-person-gear text-primary me-2"></i> User Accounts & Roles
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>logs/index.php">
                                    <i class="bi bi-journal-text text-secondary me-2"></i> System Audit Logs
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side User Authentication / Status -->
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center text-white" href="#" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-white text-dark d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: bold;">
                                <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                            </div>
                            <div class="text-start d-none d-sm-block">
                                <div class="lh-1 fw-bold"><?= e($currentUser['full_name']) ?></div>
                                <small class="text-white-50" style="font-size: 0.75rem;"><?= e(ROLES[$currentUser['role']] ?? $currentUser['role']) ?></small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold"><?= e($currentUser['full_name']) ?></div>
                                <div class="small text-muted"><?= e($currentUser['email']) ?></div>
                                <span class="badge bg-primary mt-1"><?= e(ROLES[$currentUser['role']] ?? $currentUser['role']) ?></span>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>users/profile.php">
                                    <i class="bi bi-person-circle me-2"></i> My Profile & Security
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>auth/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-light btn-sm px-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Context Subnav -->
<?php if ($currentUser): ?>
<div class="top-subnav no-print">
    <div class="container-fluid px-lg-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted d-flex align-items-center">
            <i class="bi bi-building me-1 text-primary"></i> 
            <span class="fw-semibold me-2"><?= e($currentUser['ngo_name'] ?? 'Humanitarian Core') ?></span>
            <span class="text-secondary">|</span>
            <span class="ms-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> South Sudan Operational Hub</span>
        </div>
        <div class="text-muted small">
            <span><i class="bi bi-clock me-1"></i> <?= date('l, d F Y') ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Flash Message Notifications -->
<div class="container-fluid px-lg-4 mt-3 no-print">
    <?php foreach ($flashMessages as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php if ($msg['type'] === 'success'): ?>
                <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
            <?php elseif ($msg['type'] === 'danger' || $msg['type'] === 'error'): ?>
                <i class="bi bi-x-circle-fill me-2 fs-5 align-middle"></i>
            <?php elseif ($msg['type'] === 'warning'): ?>
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
            <?php else: ?>
                <i class="bi bi-info-circle-fill me-2 fs-5 align-middle"></i>
            <?php endif; ?>
            <?= e($msg['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
</div>

<!-- Main Page Container -->
<main class="container-fluid px-lg-4 py-3 flex-grow-1">

