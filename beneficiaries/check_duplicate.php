<?php
/**
 * AJAX Endpoint for Live Duplicate Beneficiary Detection
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$fullName = sanitize($_POST['full_name'] ?? '');
$dob = sanitize($_POST['date_of_birth'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$idDocNumber = sanitize($_POST['id_document_number'] ?? '');
$state = sanitize($_POST['state'] ?? '');
$county = sanitize($_POST['county'] ?? '');
$excludeId = !empty($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

if (empty($fullName) && empty($phone) && empty($idDocNumber)) {
    echo json_encode(['has_duplicates' => false, 'matches' => []]);
    exit();
}

$matches = detect_duplicates($fullName, $dob, $phone, $idDocNumber, $state, $county, $excludeId);

echo json_encode([
    'has_duplicates' => !empty($matches),
    'matches'        => $matches
]);
exit();

