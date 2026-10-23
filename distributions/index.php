<?php
/**
 * Aid Distribution History & Transaction Logs
 * Role-accessible: All authenticated roles
 */

$pageTitle = "Aid Distribution Logs";
$activeNav = "distributions";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// Filters
$search      = sanitize($_GET['q'] ?? '');
$programId   = (int)($_GET['program_id'] ?? 0);
$ngoId       = (int)($_GET['ngo_id'] ?? 0);
$dateFrom    = sanitize($_GET['date_from'] ?? '');
$dateTo      = sanitize($_GET['date_to'] ?? '');
$exportCsv   = isset($_GET['export']) && $_GET['export'] === 'csv';

// Base Query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(d.receipt_no LIKE ? OR b.full_name LIKE ? OR b.beneficiary_id LIKE ? OR d.distribution_point LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($programId > 0) {
    $where[] = "d.program_id = ?";
    $params[] = $programId;
}

if ($ngoId > 0) {
    $where[] = "p.ngo_id = ?";
    $params[] = $ngoId;
}

if (!empty($dateFrom)) {
    $where[] = "d.distribution_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "d.distribution_date <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(" AND ", $where);

// Handle CSV Export
if ($exportCsv) {
    $exportSql = "
        SELECT d.receipt_no, b.beneficiary_id, b.full_name as beneficiary_name,
               p.program_code, p.name as program_name, n.acronym as ngo_acronym,
               d.distribution_date, d.distribution_cycle, d.item_distributed,
               d.quantity, d.unit, d.distribution_point, d.collection_type,
               d.proxy_name, d.proxy_relationship, u.full_name as officer_name
        FROM aid_distributions d
        JOIN beneficiaries b ON d.beneficiary_id = b.id
        JOIN aid_programs p ON d.program_id = p.id
        JOIN ngos n ON p.ngo_id = n.id
        LEFT JOIN users u ON d.distributed_by = u.id
        WHERE {$whereClause}
        ORDER BY d.id DESC
    ";
    $stmt = $db->prepare($exportSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="distribution_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Receipt No', 'Beneficiary ID', 'Beneficiary Name', 'Program Code', 'Program Name',
        'NGO', 'Distribution Date', 'Cycle', 'Item Distributed', 'Quantity', 'Unit',
        'Distribution Point', 'Collection Type', 'Proxy Name', 'Proxy Relationship', 'Distribution Officer'
    ]);
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['receipt_no'], $r['beneficiary_id'], $r['beneficiary_name'], $r['program_code'], $r['program_name'],
            $r['ngo_acronym'], $r['distribution_date'], $r['distribution_cycle'], $r['item_distributed'],
            $r['quantity'], $r['unit'], $r['distribution_point'], $r['collection_type'],
            $r['proxy_name'], $r['proxy_relationship'], $r['officer_name']
        ]);
    }
    fclose($output);
    exit();
}

// Pagination
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Total Count
$countSql = "
    SELECT COUNT(*) 
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    WHERE {$whereClause}
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch Paginated Logs
$sql = "
    SELECT d.*, 
           b.beneficiary_id as ben_code, b.full_name as ben_name, b.gender, b.state, b.county,
           p.name as program_name, p.program_code, n.acronym as ngo_acronym,
           u.full_name as officer_name
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    JOIN ngos n ON p.ngo_id = n.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE {$whereClause}
    ORDER BY d.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$distributions = $stmt->fetchAll();

// Dropdowns data
$programsList = $db->query("SELECT id, name, program_code FROM aid_programs ORDER BY name ASC")->fetchAll();
$ngosList = $db->query("SELECT id, name, acronym FROM ngos ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam-fill text-warning me-2"></i>Aid Distribution History &amp; Logs</h4>
        <p class="text-muted small mb-0">Auditable transactions of humanitarian rations and cash assistance distributed to beneficiaries.</p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>

        <?php if (has_role(['admin', 'distribution_officer'])): ?>
            <a href="record.php" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-plus-circle-fill me-1"></i> Record Aid Distribution
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search receipt, beneficiary, location..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Aid Programs</option>
                    <?php foreach ($programsList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['program_code']) ?> - <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="ngo_id" class="form-select form-select-sm">
                    <option value="">All NGOs</option>
                    <?php foreach ($ngosList as $n): ?>
                        <option value="<?= $n['id'] ?>" <?= $ngoId === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= e($n['acronym']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" placeholder="From Date" value="<?= e($dateFrom) ?>">
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Distributions Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Beneficiary</th>
                        <th>Program</th>
                        <th>Relief Package &amp; Quota</th>
                        <th>Distribution Point</th>
                        <th>Collection</th>
                        <th>Date &amp; Officer</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($distributions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No distribution records matched the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($distributions as $dst): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $dst['id'] ?>" class="fw-bold text-primary text-decoration-none">
                                        <?= e($dst['receipt_no']) ?>
                                    </a>
                                    <small class="d-block text-muted"><?= e($dst['distribution_cycle']) ?></small>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark"><?= e($dst['ben_name']) ?></div>
                                    <a href="<?= BASE_URL ?>beneficiaries/view.php?id=<?= $dst['beneficiary_id'] ?>" class="small text-muted text-decoration-none">
                                        <?= e($dst['ben_code']) ?>
                                    </a>
                                </td>

                                <td>
                                    <div class="small fw-semibold"><?= e($dst['program_name']) ?></div>
                                    <span class="badge bg-light text-secondary border"><?= e($dst['ngo_acronym']) ?></span>
                                </td>

                                <td>
                                    <div class="fw-bold small text-dark"><?= e($dst['quantity']) ?> <?= e($dst['unit']) ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 180px;" title="<?= e($dst['item_distributed']) ?>">
                                        <?= e($dst['item_distributed']) ?>
                                    </small>
                                </td>

                                <td class="small text-muted"><?= e($dst['distribution_point']) ?></td>

                                <td>
                                    <?php if ($dst['collection_type'] === 'Authorized Proxy'): ?>
                                        <span class="badge bg-info text-dark" title="Proxy: <?= e($dst['proxy_name']) ?>">
                                            <i class="bi bi-person-badge me-1"></i> Proxy
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-person-check me-1"></i> Direct
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="small fw-semibold"><?= format_date($dst['distribution_date']) ?></div>
                                    <small class="text-muted"><?= e($dst['officer_name'] ?? 'Officer') ?></small>
                                </td>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $dst['id'] ?>" class="btn btn-outline-primary" title="View Transaction">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="receipt.php?id=<?= $dst['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Print Distribution Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
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
                Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRecords) ?> distributions)
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

