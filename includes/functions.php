<?php
/**
 * System Helper and Utility Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Escapes output for safe HTML display.
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Cleans string input.
 */
function sanitize(?string $value): string {
    return trim($value ?? '');
}

/**
 * Generates or retrieves a CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token.
 */
function validate_csrf(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash messages for one-time alerts.
 */
function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Logs system activity to the audit_logs table.
 */
function log_audit(string $action, string $module, ?string $record_id, string $description, ?int $user_id = null): void {
    try {
        $db = getDB();
        $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt = $db->prepare("
            INSERT INTO `audit_logs` (`user_id`, `action`, `module`, `record_id`, `description`, `ip_address`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uid, $action, $module, $record_id, $description, $ip]);
    } catch (Exception $e) {
        // Suppress audit logging error so it does not interrupt user operations
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Generates the next unique Beneficiary ID (e.g. SSD-BEN-2026-0017)
 */
function generate_beneficiary_id(): string {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->query("SELECT MAX(id) as max_id FROM beneficiaries");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    return sprintf("SSD-BEN-%s-%04d", $year, $nextId);
}

/**
 * Generates the next unique Distribution Receipt Number (e.g. DIST-2026-0011)
 */
function generate_receipt_no(): string {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->query("SELECT MAX(id) as max_id FROM aid_distributions");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    return sprintf("DIST-%s-%04d", $year, $nextId);
}

/**
 * Formats date into readable string.
 */
function format_date(?string $date, string $format = 'M d, Y'): string {
    if (!$date || $date === '0000-00-00') return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Format datetime into readable string.
 */
function format_datetime(?string $datetime, string $format = 'M d, Y H:i'): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return 'N/A';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Duplicate Registration Detection Engine.
 * Analyzes records based on Name, Date of Birth, Phone, National ID / Document, and Location.
 * Returns an array of detected potential duplicate records with match details and confidence scores.
 */
function detect_duplicates(
    string $fullName,
    ?string $dob,
    ?string $phone,
    ?string $idDocNumber,
    string $state,
    string $county,
    ?int $excludeId = null
): array {
    $db = getDB();
    $duplicates = [];
    $fullNameTrim = trim($fullName);
    $phoneTrim = trim($phone ?? '');
    $idDocTrim = trim($idDocNumber ?? '');

    // 1. Check ID Document match (if document number is given and not empty)
    if (!empty($idDocTrim)) {
        $sql = "SELECT id, beneficiary_id, full_name, date_of_birth, phone, state, county, verification_status 
                FROM beneficiaries 
                WHERE id_document_number = ? AND id_document_number != ''";
        $params = [$idDocTrim];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            $duplicates[$row['id']] = [
                'record' => $row,
                'match_type' => 'Identical Official Document ID Number',
                'confidence' => 100,
                'details' => "Same document number: {$idDocTrim}"
            ];
        }
    }

    // 2. Check exact Phone Match (if phone is given and has at least 7 digits)
    if (!empty($phoneTrim) && strlen(preg_replace('/\D/', '', $phoneTrim)) >= 7) {
        $cleanPhone = preg_replace('/[^\d+]/', '', $phoneTrim);
        $sql = "SELECT id, beneficiary_id, full_name, date_of_birth, phone, state, county, verification_status 
                FROM beneficiaries 
                WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', '') LIKE ?";
        $params = ['%' . substr($cleanPhone, -8) . '%'];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            if (!isset($duplicates[$row['id']])) {
                $duplicates[$row['id']] = [
                    'record' => $row,
                    'match_type' => 'Matching Contact Phone Number',
                    'confidence' => 85,
                    'details' => "Same contact number: {$phoneTrim}"
                ];
            }
        }
    }

    // 3. Check Exact Full Name + Date of Birth Match
    if (!empty($fullNameTrim) && !empty($dob)) {
        $sql = "SELECT id, beneficiary_id, full_name, date_of_birth, phone, state, county, verification_status 
                FROM beneficiaries 
                WHERE LOWER(full_name) = LOWER(?) AND date_of_birth = ?";
        $params = [$fullNameTrim, $dob];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            if (!isset($duplicates[$row['id']])) {
                $duplicates[$row['id']] = [
                    'record' => $row,
                    'match_type' => 'Identical Full Name & Exact Date of Birth',
                    'confidence' => 95,
                    'details' => "Name '{$fullNameTrim}' and DOB '{$dob}' match exactly."
                ];
            } else {
                $duplicates[$row['id']]['confidence'] = 98;
            }
        }
    }

    // 4. Check Similar / Same Full Name + Same County
    if (!empty($fullNameTrim) && !empty($county)) {
        $sql = "SELECT id, beneficiary_id, full_name, date_of_birth, phone, state, county, verification_status 
                FROM beneficiaries 
                WHERE (LOWER(full_name) = LOWER(?) OR SOUNDEX(full_name) = SOUNDEX(?))
                  AND county = ?";
        $params = [$fullNameTrim, $fullNameTrim, $county];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            if (!isset($duplicates[$row['id']])) {
                $duplicates[$row['id']] = [
                    'record' => $row,
                    'match_type' => 'Matching Name & Geographic County',
                    'confidence' => 70,
                    'details' => "Similar/identical name in same county ({$county})."
                ];
            }
        }
    }

    return array_values($duplicates);
}

