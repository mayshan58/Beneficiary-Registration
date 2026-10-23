<?php
/**
 * Main System Dashboard
 * Real-time statistics, metrics, Chart.js visualizations, and recent logs.
 */

$pageTitle = "Dashboard";
$activeNav = "dashboard";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// 1. Core Summary Metrics from Live MySQL Queries
$totalBeneficiaries = (int)$db->query("SELECT COUNT(*) FROM beneficiaries")->fetchColumn();
$totalPrograms      = (int)$db->query("SELECT COUNT(*) FROM aid_programs WHERE status = 'Active'")->fetchColumn();
$totalNGOs          = (int)$db->query("SELECT COUNT(*) FROM ngos WHERE status = 'active'")->fetchColumn();
$totalDistributions = (int)$db->query("SELECT COUNT(*) FROM aid_distributions")->fetchColumn();

// Verification status breakdown
$verifiedCount   = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Verified'")->fetchColumn();
$pendingCount    = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Pending'")->fetchColumn();
$correctionCount = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Requires Correction'")->fetchColumn();
$rejectedCount   = (int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE verification_status = 'Rejected'")->fetchColumn();

// Duplicate flags count
$flaggedDuplicates = (int)$db->query("SELECT COUNT(*) FROM duplicate_logs WHERE status = 'Flagged'")->fetchColumn();

// 2. Beneficiaries by Gender
$genderStmt = $db->query("SELECT gender, COUNT(*) as total FROM beneficiaries GROUP BY gender");
$genderData = ['Male' => 0, 'Female' => 0, 'Other' => 0];
while ($row = $genderStmt->fetch()) {
    $genderData[$row['gender']] = (int)$row['total'];
}

// 3. Beneficiaries by State / Location
$stateStmt = $db->query("
    SELECT state, COUNT(*) as total 
    FROM beneficiaries 
    GROUP BY state 
    ORDER BY total DESC 
    LIMIT 7
");
$stateLabels = [];
$stateCounts = [];
while ($row = $stateStmt->fetch()) {
    $stateLabels[] = $row['state'];
    $stateCounts[] = (int)$row['total'];
}

// 4. Beneficiaries by Vulnerability Category
$vulnStmt = $db->query("
    SELECT vulnerability_category, COUNT(*) as total 
    FROM beneficiaries 
    GROUP BY vulnerability_category 
    ORDER BY total DESC
");
$vulnLabels = [];
$vulnCounts = [];
while ($row = $vulnStmt->fetch()) {
    $vulnLabels[] = $row['vulnerability_category'];
    $vulnCounts[] = (int)$row['total'];
}

// 5. Distribution Activity by Aid Program
$progDistStmt = $db->query("
    SELECT p.name, COUNT(d.id) as dist_count 
    FROM aid_programs p 
    LEFT JOIN aid_distributions d ON p.id = d.program_id 
    GROUP BY p.id 
    ORDER BY dist_count DESC 
    LIMIT 5
");
$progLabels = [];
$progDistCounts = [];
while ($row = $progDistStmt->fetch()) {
    $progLabels[] = strlen($row['name']) > 22 ? substr($row['name'], 0, 20) . '...' : $row['name'];
    $progDistCounts[] = (int)$row['dist_count'];
}

// 6. Recent Beneficiary Registrations (Top 5)
$recentBenStmt = $db->query("
    SELECT b.id, b.beneficiary_id, b.full_name, b.gender, b.state, b.county, b.verification_status, b.registration_date, n.acronym as ngo_acronym
    FROM beneficiaries b
    LEFT JOIN ngos n ON b.ngo_id = n.id
    ORDER BY b.id DESC
    LIMIT 5
");
$recentBeneficiaries = $recentBenStmt->fetchAll();

// 7. Recent Aid Distribution Transactions (Top 5)
$recentDistStmt = $db->query("
    SELECT d.id, d.receipt_no, d.item_distributed, d.quantity, d.unit, d.distribution_date, 
           b.full_name as beneficiary_name, b.beneficiary_id,
           p.name as program_name, u.full_name as officer_name
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    LEFT JOIN users u ON d.distributed_by = u.id
    ORDER BY d.id DESC
    LIMIT 5
");
$recentDistributions = $recentDistStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner with Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 text-white shadow-sm" style="background: linear-gradient(135deg, #0f3d64 0%, #1e588a 100%);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <span class="badge bg-warning text-dark mb-2 fw-semibold px-2 py-1">
                            <i class="bi bi-geo-alt-fill me-1"></i> Republic of South Sudan Operations
                        </span>
                        <h3 class="fw-bold mb-1">Humanitarian Assistance Dashboard</h3>
                        <p class="mb-0 text-white-50">
                            Welcome, <strong><?= e($currentUser['full_name']) ?></strong> (<?= e(ROLES[$currentUser['role']] ?? $currentUser['role']) ?> &bull; <?= e($currentUser['ngo_name']) ?>).
                        </p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <?php if (has_role(['admin', 'registration_officer'])): ?>
                            <a href="<?= BASE_URL ?>beneficiaries/add.php" class="btn btn-success fw-semibold shadow-sm">
                                <i class="bi bi-person-plus-fill me-1"></i> Register Beneficiary
                            </a>
                        <?php endif; ?>

                        <?php if (has_role(['admin', 'distribution_officer'])): ?>
                            <a href="<?= BASE_URL ?>distributions/record.php" class="btn btn-light text-primary fw-semibold shadow-sm">
                                <i class="bi bi-box-seam me-1"></i> Record Aid Distribution
                            </a>
                        <?php endif; ?>

                        <a href="<?= BASE_URL ?>reports/index.php" class="btn btn-outline-light fw-semibold">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Primary Statistic Cards (Live Database Counts) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget stat-primary">
            <div class="stat-title">Total Beneficiaries</div>
            <div class="stat-value"><?= number_format($totalBeneficiaries) ?></div>
            <div class="small text-muted mt-2">
                <span class="text-success fw-bold"><?= $verifiedCount ?> verified</span> &bull; <?= $pendingCount ?> pending
            </div>
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget stat-success">
            <div class="stat-title">Active Aid Programs</div>
            <div class="stat-value"><?= number_format($totalPrograms) ?></div>
            <div class="small text-muted mt-2">
                Food, Cash, WASH &amp; Health
            </div>
            <div class="stat-icon"><i class="bi bi-basket3-fill"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget stat-purple">
            <div class="stat-title">Registered NGOs</div>
            <div class="stat-value"><?= number_format($totalNGOs) ?></div>
            <div class="small text-muted mt-2">
                Active humanitarian partners
            </div>
            <div class="stat-icon"><i class="bi bi-building-fill"></i></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-widget stat-warning">
            <div class="stat-title">Aid Distributions</div>
            <div class="stat-value"><?= number_format($totalDistributions) ?></div>
            <div class="small text-muted mt-2">
                Vouchers &amp; in-kind kits issued
            </div>
            <div class="stat-icon"><i class="bi bi-box-seam-fill"></i></div>
        </div>
    </div>
</div>

<!-- Verification & Duplicate Alert Notification Row -->
<?php if ($flaggedDuplicates > 0): ?>
    <div class="alert alert-warning border-warning shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning me-3"></i>
            <div>
                <strong>Duplicate Registration Attention Needed:</strong>
                <span><?= $flaggedDuplicates ?> potential duplicate record pair(s) are currently flagged for verification review.</span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>beneficiaries/duplicates.php" class="btn btn-warning btn-sm fw-bold">
            <i class="bi bi-search me-1"></i> Review Duplicates
        </a>
    </div>
<?php endif; ?>

<!-- Chart.js Visualizations Row -->
<div class="row g-3 mb-4">
    <!-- Chart 1: Demographic Breakdown by State & Gender -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Beneficiaries by State &amp; Location</span>
                <span class="badge bg-light text-secondary border">Top South Sudan States</span>
            </div>
            <div class="card-body">
                <canvas id="stateChart" style="max-height: 290px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Gender & Vulnerability Demographics -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-pie-chart-fill text-success me-2"></i>Beneficiary Gender Ratio</span>
                <span class="badge bg-light text-secondary border">Protection Metrics</span>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <canvas id="genderChart" style="max-height: 220px;"></canvas>
                <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top">
                    <div>
                        <div class="text-primary fw-bold fs-5"><?= $genderData['Female'] ?></div>
                        <small class="text-muted"><i class="bi bi-gender-female text-danger"></i> Female</small>
                    </div>
                    <div>
                        <div class="text-primary fw-bold fs-5"><?= $genderData['Male'] ?></div>
                        <small class="text-muted"><i class="bi bi-gender-male text-primary"></i> Male</small>
                    </div>
                    <div>
                        <div class="text-primary fw-bold fs-5"><?= $genderData['Other'] ?></div>
                        <small class="text-muted">Other / Unspecified</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities Row (Registrations & Distributions) -->
<div class="row g-3">
    <!-- Recent Registrations -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Recent Beneficiary Registrations</span>
                <a href="<?= BASE_URL ?>beneficiaries/index.php" class="btn btn-sm btn-outline-primary">View All &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Beneficiary ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBeneficiaries)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No beneficiary records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentBeneficiaries as $ben): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>beneficiaries/view.php?id=<?= $ben['id'] ?>" class="fw-semibold text-decoration-none">
                                                <?= e($ben['beneficiary_id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= e($ben['full_name']) ?></div>
                                            <small class="text-muted"><?= e($ben['gender']) ?></small>
                                        </td>
                                        <td>
                                            <small class="d-block"><?= e($ben['county']) ?></small>
                                            <small class="text-muted"><?= e($ben['state']) ?></small>
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
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>beneficiaries/view.php?id=<?= $ben['id'] ?>" class="btn btn-sm btn-light border" title="View Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Aid Distributions -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-clock-history text-success me-2"></i>Recent Aid Distributions</span>
                <a href="<?= BASE_URL ?>distributions/index.php" class="btn btn-sm btn-outline-success">View All &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Beneficiary</th>
                                <th>Aid Item</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentDistributions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No distribution records recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentDistributions as $dist): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>distributions/view.php?id=<?= $dist['id'] ?>" class="fw-semibold text-decoration-none">
                                                <?= e($dist['receipt_no']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= e($dist['beneficiary_name']) ?></div>
                                            <small class="text-muted"><?= e($dist['beneficiary_id']) ?></small>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 170px;" title="<?= e($dist['item_distributed']) ?>">
                                                <?= e($dist['item_distributed']) ?>
                                            </div>
                                            <small class="text-muted"><?= e($dist['quantity']) ?> <?= e($dist['unit']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= format_date($dist['distribution_date']) ?></small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>distributions/receipt.php?id=<?= $dist['id'] ?>" target="_blank" class="btn btn-sm btn-light border" title="Print Receipt">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Initialization Script with Real Database Data -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. State Demographics Bar Chart
    const stateCtx = document.getElementById('stateChart').getContext('2d');
    new Chart(stateCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($stateLabels) ?>,
            datasets: [{
                label: 'Registered Beneficiaries',
                data: <?= json_encode($stateCounts) ?>,
                backgroundColor: '#0284c7',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 2. Gender Ratio Doughnut Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: ['Female', 'Male', 'Other'],
            datasets: [{
                data: [
                    <?= $genderData['Female'] ?>,
                    <?= $genderData['Male'] ?>,
                    <?= $genderData['Other'] ?>
                ],
                backgroundColor: ['#e11d48', '#0284c7', '#94a3b8'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '68%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
