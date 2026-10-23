<?php
/**
 * Beneficiaries Directory & Management
 * Supports search, multi-attribute filtering, pagination, and CSV export.
 */

$pageTitle = "Beneficiaries Directory";
$activeNav = "beneficiaries";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// Handle Filter and Search Parameters
$search       = sanitize($_GET['q'] ?? '');
$filterState  = sanitize($_GET['state'] ?? '');
$filterGender = sanitize($_GET['gender'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$filterVuln   = sanitize($_GET['vulnerability'] ?? '');
$exportCsv    = isset($_GET['export']) && $_GET['export'] === 'csv';

// Base Query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(b.beneficiary_id LIKE ? OR b.full_name LIKE ? OR b.phone LIKE ? OR b.id_document_number LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if (!empty($filterState)) {
    $where[] = "b.state = ?";
    $params[] = $filterState;
}

if (!empty($filterGender)) {
    $where[] = "b.gender = ?";
    $params[] = $filterGender;
}

if (!empty($filterStatus)) {
    $where[] = "b.verification_status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterVuln)) {
    $where[] = "b.vulnerability_category = ?";
    $params[] = $filterVuln;
}

$whereClause = implode(" AND ", $where);

// Handle CSV Export
if ($exportCsv) {
    $exportSql = "
        SELECT b.beneficiary_id, b.full_name, b.gender, b.date_of_birth, b.estimated_age,
               b.phone, b.id_document_type, b.id_document_number, b.state, b.county,
               b.payam_boma, b.community_camp, b.household_size, b.children_under_5,
               b.elderly_members, b.disabled_members, b.pregnant_lactating,
               b.vulnerability_category, b.verification_status, b.registration_date,
               n.acronym as ngo_acronym
        FROM beneficiaries b
        LEFT JOIN ngos n ON b.ngo_id = n.id
        WHERE {$whereClause}
        ORDER BY b.id DESC
    ";
    $stmt = $db->prepare($exportSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="beneficiaries_export_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Beneficiary ID', 'Full Name', 'Gender', 'Date of Birth', 'Age', 'Phone',
        'ID Document Type', 'ID Document Number', 'State', 'County', 'Payam/Boma',
        'Community/Camp', 'Household Size', 'Children <5', 'Elderly (60+)', 'Disabled',
        'Pregnant/Lactating', 'Vulnerability', 'Verification Status', 'Registration Date', 'Registered NGO'
    ]);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['beneficiary_id'], $r['full_name'], $r['gender'], $r['date_of_birth'], $r['estimated_age'],
            $r['phone'], $r['id_document_type'], $r['id_document_number'], $r['state'], $r['county'],
            $r['payam_boma'], $r['community_camp'], $r['household_size'], $r['children_under_5'],
            $r['elderly_members'], $r['disabled_members'], $r['pregnant_lactating'],
            $r['vulnerability_category'], $r['verification_status'], $r['registration_date'], $r['ngo_acronym']
        ]);
    }
    fclose($output);
    exit();
}

// Pagination Logic
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Total Count
$countSql = "SELECT COUNT(*) FROM beneficiaries b WHERE {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch Paginated Records
$sql = "
    SELECT b.*, n.acronym as ngo_acronym, u.full_name as registered_by_name
    FROM beneficiaries b
    LEFT JOIN ngos n ON b.ngo_id = n.id
    LEFT JOIN users u ON b.registered_by = u.id
    WHERE {$whereClause}
    ORDER BY b.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$beneficiaries = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Beneficiaries Directory</h4>
        <p class="text-muted small mb-0">Total of <strong><?= number_format($totalRecords) ?></strong> registered humanitarian beneficiaries matching filters</p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <!-- Export Button -->
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>

        <?php if (has_role(['admin', 'registration_officer'])): ?>
            <a href="add.php" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-person-plus-fill me-1"></i> + Register Beneficiary
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <!-- Search Text -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, ID, phone..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- State Filter -->
            <div class="col-md-2">
                <select name="state" class="form-select form-select-sm">
                    <option value="">All States</option>
                    <?php foreach (array_keys(SOUTH_SUDAN_LOCATIONS) as $st): ?>
                        <option value="<?= e($st) ?>" <?= $filterState === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Gender Filter -->
            <div class="col-md-2">
                <select name="gender" class="form-select form-select-sm">
                    <option value="">All Genders</option>
                    <option value="Female" <?= $filterGender === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Male" <?= $filterGender === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Other" <?= $filterGender === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Verification Status Filter -->
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="Verified" <?= $filterStatus === 'Verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="Requires Correction" <?= $filterStatus === 'Requires Correction' ? 'selected' : '' ?>>Requires Correction</option>
                    <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <!-- Vulnerability Filter -->
            <div class="col-md-2">
                <select name="vulnerability" class="form-select form-select-sm">
                    <option value="">All Vulnerabilities</option>
                    <?php foreach (VULNERABILITY_CATEGORIES as $vc): ?>
                        <option value="<?= e($vc) ?>" <?= $filterVuln === $vc ? 'selected' : '' ?>><?= e($vc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filters">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Beneficiary ID</th>
                        <th>Full Name &amp; Contact</th>
                        <th>Demographics</th>
                        <th>Location (State / County)</th>
                        <th>Vulnerability</th>
                        <th>HH Size</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($beneficiaries)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                No beneficiaries matched the current search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($beneficiaries as $ben): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $ben['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                        <?= e($ben['beneficiary_id']) ?>
                                    </a>
                                    <?php if ($ben['is_duplicate_flag']): ?>
                                        <span class="badge bg-danger ms-1" title="Flagged as potential duplicate">
                                            <i class="bi bi-exclamation-triangle"></i> Flagged
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ben['full_name']) ?></div>
                                    <small class="text-muted">
                                        <i class="bi bi-telephone me-1"></i><?= e($ben['phone'] ?: 'No Phone') ?>
                                    </small>
                                </td>

                                <td>
                                    <small class="d-block fw-semibold"><?= e($ben['gender']) ?></small>
                                    <small class="text-muted"><?= $ben['estimated_age'] ? e($ben['estimated_age']) . ' yrs' : format_date($ben['date_of_birth']) ?></small>
                                </td>

                                <td>
                                    <div class="fw-semibold small"><?= e($ben['county']) ?></div>
                                    <small class="text-muted"><?= e($ben['state']) ?></small>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border small"><?= e($ben['vulnerability_category']) ?></span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill px-2"><?= (int)$ben['household_size'] ?></span>
                                </td>

                                <td>
                                    <?php
                                        $badgeClass = match($ben['verification_status']) {
                                            'Verified' => 'bg-success',
                                            'Pending' => 'bg-warning text-dark',
                                            'Requires Correction' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= e($ben['verification_status']) ?></span>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $ben['id'] ?>" class="btn btn-outline-primary" title="View Profile">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <a href="id_card.php?id=<?= $ben['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Print Beneficiary ID Card">
                                            <i class="bi bi-card-text"></i>
                                        </a>

                                        <?php if (has_role(['admin', 'registration_officer'])): ?>
                                            <a href="edit.php?id=<?= $ben['id'] ?>" class="btn btn-outline-secondary" title="Edit Beneficiary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRecords) ?>)
            </small>

            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

