<?php
/**
 * Aid Distribution & Donor Accountability Report
 * Printable HTML & CSV Export
 */

$pageTitle = "Aid Distribution Report";
$activeNav = "reports";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// Filters
$programId = (int)($_GET['program_id'] ?? 0);
$ngoId     = (int)($_GET['ngo_id'] ?? 0);
$dateFrom  = sanitize($_GET['date_from'] ?? '');
$dateTo    = sanitize($_GET['date_to'] ?? '');
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$where = ["1=1"];
$params = [];

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

$query = "
    SELECT d.*, 
           b.beneficiary_id, b.full_name as ben_name, b.gender, b.state, b.county, b.household_size,
           p.name as program_name, p.program_code, p.category,
           n.name as ngo_name, n.acronym as ngo_acronym,
           u.full_name as officer_name
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    JOIN ngos n ON p.ngo_id = n.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE {$whereClause}
    ORDER BY d.distribution_date DESC, d.id DESC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$distributions = $stmt->fetchAll();

// Aggregates
$totalTransactions = count($distributions);
$uniqueBenMap = [];
$totalProxy = 0;
$totalDirect = 0;

foreach ($distributions as $d) {
    $uniqueBenMap[$d['beneficiary_id']] = true;
    if ($d['collection_type'] === 'Authorized Proxy') {
        $totalProxy++;
    } else {
        $totalDirect++;
    }
}
$uniqueBeneficiariesCount = count($uniqueBenMap);

// Handle CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aid_distribution_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Receipt No', 'Distribution Date', 'Program Code', 'Program Title', 'NGO Partner',
        'Beneficiary ID', 'Beneficiary Name', 'Location (State/County)', 'HH Size',
        'Item Distributed', 'Quantity', 'Unit', 'Distribution Site', 'Collection Type', 'Proxy Name', 'Distribution Officer'
    ]);
    foreach ($distributions as $d) {
        fputcsv($output, [
            $d['receipt_no'], $d['distribution_date'], $d['program_code'], $d['program_name'], $d['ngo_acronym'],
            $d['beneficiary_id'], $d['ben_name'], "{$d['county']}, {$d['state']}", $d['household_size'],
            $d['item_distributed'], $d['quantity'], $d['unit'], $d['distribution_point'], $d['collection_type'],
            $d['proxy_name'], $d['officer_name']
        ]);
    }
    fclose($output);
    exit();
}

$programsList = $db->query("SELECT id, name, program_code FROM aid_programs ORDER BY name ASC")->fetchAll();
$ngosList = $db->query("SELECT id, name, acronym FROM ngos ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print-Only Header -->
<div class="print-only mb-4 border-bottom pb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Humanitarian Aid Distribution Accountability Ledger</h4>
            <div class="small">Republic of South Sudan &bull; Food, Cash &amp; Emergency Non-Food Cluster</div>
        </div>
        <div class="text-end small">
            <div><strong>Report Date:</strong> <?= date('d M Y H:i:s') ?></div>
            <div><strong>Audit Officer:</strong> <?= e($currentUser['full_name']) ?></div>
        </div>
    </div>
</div>

<!-- Screen Action Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam-fill text-warning me-2"></i>Aid Distribution &amp; Donor Accountability Report</h4>
        <p class="text-muted small mb-0">Detailed transaction reconciliation for donors, clusters, and humanitarian oversight.</p>
    </div>

    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
            <i class="bi bi-printer me-1"></i> Print Official Report
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm border-0 no-print">
    <div class="card-body p-3">
        <form method="GET" action="distributions.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    <?php foreach ($programsList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['program_code']) ?> - <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="ngo_id" class="form-select form-select-sm">
                    <option value="">All Implementing NGOs</option>
                    <?php foreach ($ngosList as $n): ?>
                        <option value="<?= $n['id'] ?>" <?= $ngoId === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= e($n['acronym']) ?> - <?= e($n['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">From</span>
                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                </div>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="distributions.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics Bar -->
<div class="row g-2 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="p-3 border rounded bg-white text-center shadow-sm">
            <div class="fs-4 fw-bold text-primary"><?= number_format($totalTransactions) ?></div>
            <small class="text-muted fw-bold">Total Distributions Issued</small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="p-3 border rounded bg-white text-center shadow-sm">
            <div class="fs-4 fw-bold text-success"><?= number_format($uniqueBeneficiariesCount) ?></div>
            <small class="text-muted fw-bold">Unique Beneficiaries Served</small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="p-3 border rounded bg-white text-center shadow-sm">
            <div class="fs-4 fw-bold text-dark"><?= number_format($totalDirect) ?></div>
            <small class="text-muted fw-bold">Direct Collections</small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="p-3 border rounded bg-white text-center shadow-sm">
            <div class="fs-4 fw-bold text-warning"><?= number_format($totalProxy) ?></div>
            <small class="text-muted fw-bold">Authorized Proxy Pickups</small>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Program</th>
                        <th>NGO</th>
                        <th>Beneficiary</th>
                        <th>Location</th>
                        <th>Items &amp; Quota</th>
                        <th>Distribution Site</th>
                        <th>Collection Type</th>
                        <th>Officer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($distributions)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No distribution records matched this report criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($distributions as $dst): ?>
                            <tr>
                                <td class="fw-bold font-monospace small text-primary"><?= e($dst['receipt_no']) ?></td>
                                <td class="small"><?= format_date($dst['distribution_date']) ?></td>
                                <td class="small fw-semibold"><?= e($dst['program_name']) ?></td>
                                <td><span class="badge bg-light text-dark border small"><?= e($dst['ngo_acronym']) ?></span></td>
                                <td>
                                    <div class="fw-semibold small text-dark"><?= e($dst['ben_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= e($dst['beneficiary_id']) ?></small>
                                </td>
                                <td class="small text-muted"><?= e($dst['county']) ?>, <?= e($dst['state']) ?></td>
                                <td>
                                    <strong class="small text-dark"><?= e($dst['quantity']) ?> <?= e($dst['unit']) ?></strong>
                                    <small class="d-block text-muted text-truncate" style="max-width: 170px;" title="<?= e($dst['item_distributed']) ?>">
                                        <?= e($dst['item_distributed']) ?>
                                    </small>
                                </td>
                                <td class="small text-muted"><?= e($dst['distribution_point']) ?></td>
                                <td>
                                    <?php if ($dst['collection_type'] === 'Authorized Proxy'): ?>
                                        <span class="badge bg-info text-dark small" title="Proxy: <?= e($dst['proxy_name']) ?> (<?= e($dst['proxy_relationship']) ?>)">Proxy</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border small">Direct</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($dst['officer_name'] ?? 'Officer') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

