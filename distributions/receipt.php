<?php
/**
 * Official Printable Aid Distribution Voucher / Receipt
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid distribution transaction ID.");
}

$stmt = $db->prepare("
    SELECT d.*, 
           b.beneficiary_id, b.full_name as ben_name, b.gender, b.state, b.county, b.payam_boma, b.community_camp, b.household_size,
           p.name as program_name, p.program_code, p.category,
           n.name as ngo_name, n.acronym as ngo_acronym, n.registration_number as ngo_reg,
           u.full_name as officer_name, u.role as officer_role
    FROM aid_distributions d
    JOIN beneficiaries b ON d.beneficiary_id = b.id
    JOIN aid_programs p ON d.program_id = p.id
    JOIN ngos n ON p.ngo_id = n.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$dist = $stmt->fetch();

if (!$dist) {
    die("Distribution record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Receipt - <?= e($dist['receipt_no']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background-color: #f1f5f9;
            padding: 30px 15px;
        }
        .receipt-container {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f3d64;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .receipt-header {
            background: #0f3d64;
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stamp-box {
            width: 140px;
            height: 70px;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 0.75rem;
            text-align: center;
            border-radius: 6px;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                max-width: 100% !important;
            }
            .receipt-header {
                background: #0f3d64 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="no-print d-flex justify-content-between align-items-center mb-3" style="max-width: 720px; margin: 0 auto 15px;">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Distribution Logs
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
        <i class="bi bi-printer me-1"></i> Print Distribution Voucher
    </button>
</div>

<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <div>
            <div class="text-uppercase small fw-bold text-warning" style="letter-spacing: 1px;">Humanitarian Aid Distribution Voucher</div>
            <h4 class="mb-0 fw-bold">Republic of South Sudan</h4>
            <small class="text-white-50"><?= e($dist['ngo_name']) ?> (<?= e($dist['ngo_acronym']) ?> &bull; RRC: <?= e($dist['ngo_reg']) ?>)</small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark fw-bold fs-6 font-monospace">
                <?= e($dist['receipt_no']) ?>
            </span>
            <div class="small text-white-50 mt-1"><?= format_date($dist['distribution_date']) ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="p-4">
        <!-- Program & Location Meta -->
        <div class="row g-2 mb-3 pb-3 border-bottom small">
            <div class="col-6">
                <span class="text-muted d-block">Program Name:</span>
                <strong class="text-dark fs-6"><?= e($dist['program_name']) ?></strong> (<code><?= e($dist['program_code']) ?></code>)
            </div>

            <div class="col-6">
                <span class="text-muted d-block">Distribution Site:</span>
                <strong class="text-dark"><?= e($dist['distribution_point']) ?></strong>
                <span class="text-muted d-block"><?= e($dist['county']) ?>, <?= e($dist['state']) ?></span>
            </div>

            <div class="col-6 mt-2">
                <span class="text-muted d-block">Cycle / Round:</span>
                <strong class="text-dark"><?= e($dist['distribution_cycle']) ?></strong>
            </div>

            <div class="col-6 mt-2">
                <span class="text-muted d-block">Sector:</span>
                <span class="badge bg-secondary"><?= e($dist['category']) ?></span>
            </div>
        </div>

        <!-- Beneficiary Identity Box -->
        <div class="p-3 bg-light rounded border mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-person-fill me-1"></i>Registered Beneficiary Information</h6>
            <div class="row g-2 small">
                <div class="col-sm-6">
                    <span class="text-muted">Full Legal Name:</span>
                    <strong class="text-dark d-block fs-6"><?= e($dist['ben_name']) ?></strong>
                </div>

                <div class="col-sm-6">
                    <span class="text-muted">Beneficiary ID:</span>
                    <strong class="text-primary d-block font-monospace fs-6"><?= e($dist['beneficiary_id']) ?></strong>
                </div>

                <div class="col-sm-6">
                    <span class="text-muted">Settlement / Payam:</span>
                    <span class="text-dark d-block"><?= e($dist['community_camp'] ?: 'Host Community') ?> (<?= e($dist['payam_boma']) ?>)</span>
                </div>

                <div class="col-sm-6">
                    <span class="text-muted">Household Size:</span>
                    <span class="text-dark d-block"><strong><?= (int)$dist['household_size'] ?></strong> Registered Family Members</span>
                </div>
            </div>
        </div>

        <!-- Relief Items Handed Over Table -->
        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-box-seam me-1 text-success"></i>Relief Items / Assistance Disbursed</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Description</th>
                        <th class="text-center" style="width: 25%;">Allocated Quantity</th>
                        <th class="text-center" style="width: 25%;">Unit of Measure</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold text-dark"><?= e($dist['item_distributed']) ?></td>
                        <td class="text-center fw-bold fs-5 text-primary"><?= e($dist['quantity']) ?></td>
                        <td class="text-center fw-semibold text-secondary"><?= e($dist['unit']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Collection Verification Box -->
        <div class="p-3 bg-light rounded border mb-4 small">
            <div class="row g-2">
                <div class="col-sm-6">
                    <span class="text-muted">Collection Method:</span>
                    <strong class="text-dark d-block"><?= e($dist['collection_type']) ?></strong>
                </div>

                <?php if ($dist['collection_type'] === 'Authorized Proxy'): ?>
                    <div class="col-sm-6">
                        <span class="text-muted">Authorized Proxy Collector:</span>
                        <strong class="text-dark d-block"><?= e($dist['proxy_name']) ?> (<?= e($dist['proxy_relationship']) ?>)</strong>
                        <small class="text-muted">Contact: <?= e($dist['proxy_phone'] ?: 'N/A') ?></small>
                    </div>
                <?php else: ?>
                    <div class="col-sm-6">
                        <span class="text-muted">Recipient Verification:</span>
                        <span class="text-success fw-bold d-block"><i class="bi bi-check-circle me-1"></i> Beneficiary In-Person</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($dist['remarks'])): ?>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="text-muted">Field Observations:</span>
                        <span class="text-dark d-block"><?= nl2br(e($dist['remarks'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Official Signatures / Acknowledgement -->
        <div class="row g-3 text-center align-items-end pt-3">
            <div class="col-4">
                <div class="border-top pt-2 small text-muted">
                    Recipient / Proxy Thumbprint or Sign
                </div>
            </div>

            <div class="col-4">
                <div class="stamp-box mx-auto mb-1">
                    [ Official NGO Relief Stamp ]
                </div>
                <div class="small text-muted" style="font-size: 0.72rem;">Official Stamp</div>
            </div>

            <div class="col-4">
                <div class="border-top pt-2 small">
                    <strong class="d-block text-dark"><?= e($dist['officer_name'] ?? 'Authorized Officer') ?></strong>
                    <span class="text-muted" style="font-size: 0.72rem;">Distribution Officer</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Footer -->
    <div class="bg-light p-2 border-top text-center text-muted small" style="font-size: 0.72rem;">
        Official Humanitarian Record &bull; Generated by NGO Beneficiary Registration System (South Sudan) &bull; <?= date('d M Y H:i:s') ?>
    </div>
</div>

</body>
</html>

