<?php
/**
 * Official Printable Beneficiary ID Card & Ration Slip
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid beneficiary ID specified.");
}

$stmt = $db->prepare("
    SELECT b.*, n.name as ngo_name, n.acronym as ngo_acronym
    FROM beneficiaries b
    LEFT JOIN ngos n ON b.ngo_id = n.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$beneficiary = $stmt->fetch();

if (!$beneficiary) {
    die("Beneficiary record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beneficiary ID Slip - <?= e($beneficiary['beneficiary_id']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background-color: #f1f5f9;
            padding: 25px 15px;
        }
        .print-btn-bar {
            max-width: 650px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .id-slip {
            max-width: 650px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f3d64;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .id-slip-header {
            background: #0f3d64;
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .id-photo-box {
            width: 105px;
            height: 125px;
            border: 2px dashed #94a3b8;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #64748b;
            font-size: 0.72rem;
            text-align: center;
            padding: 5px;
        }
        .barcode-sim {
            font-family: 'Courier New', monospace;
            letter-spacing: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 6px;
            text-align: center;
            border-radius: 4px;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .print-btn-bar {
                display: none !important;
            }
            .id-slip {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                max-width: 100% !important;
            }
            .id-slip-header {
                background: #0f3d64 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="print-btn-bar no-print">
    <a href="view.php?id=<?= $beneficiary['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Profile
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
        <i class="bi bi-printer me-1"></i> Print Beneficiary Slip
    </button>
</div>

<div class="id-slip">
    <!-- Header -->
    <div class="id-slip-header">
        <div>
            <div class="text-uppercase fw-bold small text-warning" style="letter-spacing: 1px;">Humanitarian Assistance Registry</div>
            <h5 class="mb-0 fw-bold">Republic of South Sudan</h5>
            <small class="text-white-50"><?= e($beneficiary['ngo_name'] ?? 'Humanitarian Core') ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border fw-bold fs-6">
                <?= e($beneficiary['verification_status']) ?>
            </span>
            <div class="small text-white-50 mt-1">Reg: <?= format_date($beneficiary['registration_date']) ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="p-4">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-auto">
                <div class="id-photo-box">
                    <i class="bi bi-person-bounding-box fs-1 mb-1"></i>
                    <span>Official Photo / Biometric</span>
                </div>
            </div>

            <div class="col">
                <div class="badge bg-primary text-uppercase mb-1" style="font-size: 0.72rem;">Beneficiary ID Number</div>
                <div class="fs-4 fw-bold text-dark font-monospace"><?= e($beneficiary['beneficiary_id']) ?></div>
                <h4 class="fw-bold text-primary mb-1"><?= e($beneficiary['full_name']) ?></h4>
                <div class="text-muted small">
                    <strong><?= e($beneficiary['gender']) ?></strong> &bull; 
                    <?= $beneficiary['estimated_age'] ? e($beneficiary['estimated_age']) . ' Years' : 'Age N/A' ?> &bull; 
                    Phone: <strong><?= e($beneficiary['phone'] ?: 'N/A') ?></strong>
                </div>
            </div>
        </div>

        <div class="barcode-sim mb-3">
            ||| | |||| | ||||| |||| | |||| ||| <?= e($beneficiary['beneficiary_id']) ?>
        </div>

        <!-- Demographic Details -->
        <div class="border rounded p-3 bg-light mb-3">
            <div class="row g-2 small">
                <div class="col-6">
                    <span class="text-muted">State / Admin Area:</span>
                    <strong class="text-dark d-block"><?= e($beneficiary['state']) ?></strong>
                </div>

                <div class="col-6">
                    <span class="text-muted">County / Payam:</span>
                    <strong class="text-dark d-block"><?= e($beneficiary['county']) ?>, <?= e($beneficiary['payam_boma']) ?></strong>
                </div>

                <div class="col-6">
                    <span class="text-muted">Settlement / Camp:</span>
                    <strong class="text-dark d-block"><?= e($beneficiary['community_camp'] ?: 'Host Community') ?></strong>
                </div>

                <div class="col-6">
                    <span class="text-muted">Vulnerability Category:</span>
                    <strong class="text-primary d-block"><?= e($beneficiary['vulnerability_category']) ?></strong>
                </div>

                <div class="col-6">
                    <span class="text-muted">Total Household Size:</span>
                    <strong class="text-dark d-block"><?= (int)$beneficiary['household_size'] ?> Members</strong>
                </div>

                <div class="col-6">
                    <span class="text-muted">Official Document:</span>
                    <strong class="text-dark d-block"><?= e($beneficiary['id_document_type']) ?> (<?= e($beneficiary['id_document_number'] ?: 'None') ?>)</strong>
                </div>
            </div>
        </div>

        <!-- Official Stamps and Signatures -->
        <div class="row g-2 pt-2 text-center align-items-end">
            <div class="col-4">
                <div class="border-top pt-2 small text-muted">
                    Beneficiary Thumbprint / Sign
                </div>
            </div>

            <div class="col-4">
                <div class="stamp-box mx-auto mb-1">
                    [ Official NGO Seal / Stamp ]
                </div>
                <div class="small text-muted" style="font-size: 0.72rem;">Verification Authority</div>
            </div>

            <div class="col-4">
                <div class="border-top pt-2 small text-muted">
                    Registration Officer Signature
                </div>
            </div>
        </div>
    </div>

    <!-- Card Bottom Strip -->
    <div class="bg-light p-2 border-top text-center text-muted" style="font-size: 0.72rem;">
        This document remains the property of the Humanitarian Registry in South Sudan. Non-transferable. Valid for relief assistance distribution.
    </div>
</div>

</body>
</html>

