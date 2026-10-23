<?php
/**
 * Reports & Humanitarian Analytics Hub
 */

$pageTitle = "Humanitarian Reports Hub";
$activeNav = "reports";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$currentUser = current_user();
$db = getDB();

// Quick Aggregates for report cards
$totalBen = (int)$db->query("SELECT COUNT(*) FROM beneficiaries")->fetchColumn();
$totalIndividuals = (int)$db->query("SELECT SUM(household_size) FROM beneficiaries")->fetchColumn();
$totalDist = (int)$db->query("SELECT COUNT(*) FROM aid_distributions")->fetchColumn();
$totalProg = (int)$db->query("SELECT COUNT(*) FROM aid_programs WHERE status = 'Active'")->fetchColumn();

// State-by-state Beneficiary Totals for Bar Chart
$stateAgg = $db->query("
    SELECT state, COUNT(*) as hh_count, SUM(household_size) as ind_count 
    FROM beneficiaries 
    GROUP BY state 
    ORDER BY hh_count DESC
")->fetchAll();

$stateLabels = [];
$stateHH = [];
$stateInd = [];
foreach ($stateAgg as $sa) {
    $stateLabels[] = $sa['state'];
    $stateHH[] = (int)$sa['hh_count'];
    $stateInd[] = (int)$sa['ind_count'];
}

// Distribution by Sector
$sectorAgg = $db->query("
    SELECT p.category, COUNT(d.id) as dist_count
    FROM aid_programs p
    LEFT JOIN aid_distributions d ON p.id = d.program_id
    GROUP BY p.category
")->fetchAll();

$sectorLabels = [];
$sectorCounts = [];
foreach ($sectorAgg as $sc) {
    $sectorLabels[] = $sc['category'];
    $sectorCounts[] = (int)$sc['dist_count'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Humanitarian Reports &amp; Analytics Hub</h4>
        <p class="text-muted small mb-0">Decision-making metrics, cluster reporting, donor accountability, and printable rosters.</p>
    </div>

    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i> Print Overview
    </button>
</div>

<!-- Primary Report Access Cards -->
<div class="row g-3 mb-4">
    <!-- Report 1: Beneficiary Demographics -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                        <span class="badge bg-primary"><?= number_format($totalBen) ?> Households</span>
                    </div>

                    <h5 class="fw-bold mb-2">Beneficiary Demographics Report</h5>
                    <p class="small text-muted mb-3">
                        Comprehensive disaggregated data by State, County, Gender, Vulnerability category, Children &lt;5, and Persons with Disabilities (PWD).
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <a href="beneficiaries.php" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> View Report
                    </a>
                    <a href="beneficiaries.php?export=csv" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Report 2: Aid Distribution & Donor Accountability -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3">
                            <i class="bi bi-box-seam-fill fs-3"></i>
                        </div>
                        <span class="badge bg-warning text-dark"><?= number_format($totalDist) ?> Disbursed</span>
                    </div>

                    <h5 class="fw-bold mb-2">Aid Distribution &amp; Audit Report</h5>
                    <p class="small text-muted mb-3">
                        Auditable transaction ledger of relief food, cash vouchers, and emergency kits disbursed by implementing NGO, program, date range, and site.
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <a href="distributions.php" class="btn btn-warning btn-sm text-dark flex-grow-1 fw-semibold">
                        <i class="bi bi-eye me-1"></i> View Report
                    </a>
                    <a href="distributions.php?export=csv" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Report 3: Program Coverage & Targets -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-success">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                            <i class="bi bi-graph-up-arrow fs-3"></i>
                        </div>
                        <span class="badge bg-success"><?= number_format($totalProg) ?> Programs</span>
                    </div>

                    <h5 class="fw-bold mb-2">Program Coverage &amp; Performance</h5>
                    <p class="small text-muted mb-3">
                        Humanitarian response progress monitoring target goals vs actual enrollment and distribution fulfillment rates.
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <a href="programs.php" class="btn btn-success btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> View Report
                    </a>
                    <a href="programs.php?export=csv" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Charts Row -->
<div class="row g-3 mb-4">
    <!-- Chart 1: State Population Reach -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Household vs Individual Reach by State</span>
                <span class="badge bg-light text-secondary border">Cluster Analysis</span>
            </div>
            <div class="card-body">
                <canvas id="stateReachChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Distribution by Assistance Sector -->
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-success"><i class="bi bi-pie-chart-fill me-2"></i>Aid Delivery by Sector</span>
                <span class="badge bg-light text-secondary border">Operations</span>
            </div>
            <div class="card-body">
                <canvas id="sectorChart" style="max-height: 240px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. State Reach Double Bar Chart
    const reachCtx = document.getElementById('stateReachChart').getContext('2d');
    new Chart(reachCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($stateLabels) ?>,
            datasets: [
                {
                    label: 'Households (HH)',
                    data: <?= json_encode($stateHH) ?>,
                    backgroundColor: '#0284c7',
                    borderRadius: 4
                },
                {
                    label: 'Estimated Individuals',
                    data: <?= json_encode($stateInd) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 2. Sector Pie Chart
    const sectorCtx = document.getElementById('sectorChart').getContext('2d');
    new Chart(sectorCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($sectorLabels) ?>,
            datasets: [{
                data: <?= json_encode($sectorCounts) ?>,
                backgroundColor: ['#0284c7', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

