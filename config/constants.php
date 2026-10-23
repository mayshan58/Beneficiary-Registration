<?php
/**
 * System Constants and South Sudan Administrative Metadata
 */

if (!defined('APP_NAME')) define('APP_NAME', 'NGO Beneficiary Registration System');
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', 'Humanitarian Beneficiary Information & Aid Distribution Platform');
if (!defined('COUNTRY')) define('COUNTRY', 'South Sudan');

// Calculate base URL automatically
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Normalize root relative to project root
    // Detect if we are in a subfolder like beneficiaries/ or programs/
    $subfolders = ['/beneficiaries', '/programs', '/distributions', '/ngos', '/users', '/reports', '/logs', '/auth', '/config', '/includes'];
    $cleanPath = $scriptDir;
    foreach ($subfolders as $sf) {
        if (str_ends_with($cleanPath, $sf)) {
            $cleanPath = substr($cleanPath, 0, -strlen($sf));
            break;
        }
    }
    $baseUrl = rtrim($protocol . $host . $cleanPath, '/') . '/';
    define('BASE_URL', $baseUrl);
}

// User roles
const ROLES = [
    'admin' => 'Administrator',
    'registration_officer' => 'Registration Officer',
    'distribution_officer' => 'Distribution Officer'
];

// Vulnerability categories
const VULNERABILITY_CATEGORIES = [
    'IDP (Internally Displaced Person)',
    'Returnee',
    'Host Community Vulnerable',
    'Female-Headed Household',
    'Child-Headed Household',
    'Elderly-Headed Household',
    'Person with Disability (PWD)',
    'Chronically Ill'
];

// ID Document Types
const ID_DOCUMENT_TYPES = [
    'Chief / Local Recommendation Letter',
    'National ID',
    'Refugee ID Card',
    'Voter Card',
    'Passport',
    'None'
];

// Verification Statuses
const VERIFICATION_STATUSES = [
    'Pending' => ['badge' => 'warning', 'label' => 'Pending Review'],
    'Verified' => ['badge' => 'success', 'label' => 'Verified'],
    'Requires Correction' => ['badge' => 'danger', 'label' => 'Requires Correction'],
    'Rejected' => ['badge' => 'secondary', 'label' => 'Rejected']
];

// Aid Categories
const AID_CATEGORIES = [
    'Food Assistance',
    'Cash & Voucher (CVA)',
    'WASH',
    'Health & Nutrition',
    'Shelter & NFI',
    'Livelihoods & Agriculture',
    'Protection & Education'
];

// Comprehensive South Sudan States & Counties
const SOUTH_SUDAN_LOCATIONS = [
    'Central Equatoria' => ['Juba', 'Yei', 'Morobo', 'Lainya', 'Kajo-Keji', 'Terekeka'],
    'Eastern Equatoria' => ['Torit', 'Kapoeta East', 'Kapoeta North', 'Kapoeta South', 'Magwi', 'Ikotos', 'Budi', 'Lafon'],
    'Western Equatoria' => ['Yambio', 'Nzara', 'Maridi', 'Mundri West', 'Mundri East', 'Ibba', 'Ezo', 'Nagero', 'Tambura', 'Mvolo'],
    'Jonglei' => ['Bor', 'Akobo', 'Ayod', 'Duk', 'Fangak', 'Nyirol', 'Pigi', 'Twic East', 'Uror'],
    'Unity' => ['Rubkona (Bentiu)', 'Guit', 'Koch', 'Leer', 'Mayendit', 'Mayom', 'Pariang', 'Panyijiar'],
    'Upper Nile' => ['Malakal', 'Renk', 'Manyo', 'Fashoda', 'Maban', 'Maiwut', 'Luakpiny/Nasir', 'Longochuk', 'Melut', 'Panyikang', 'Ulang'],
    'Lakes' => ['Rumbek Centre', 'Rumbek East', 'Rumbek North', 'Wulu', 'Cueibet', 'Yirol West', 'Yirol East', 'Awerial'],
    'Warrap' => ['Kuajok', 'Gogrial West', 'Gogrial East', 'Tonj North', 'Tonj East', 'Tonj South', 'Twic'],
    'Western Bahr el Ghazal' => ['Wau', 'Raja', 'Jur River'],
    'Northern Bahr el Ghazal' => ['Aweil Centre', 'Aweil East', 'Aweil North', 'Aweil South', 'Aweil West'],
    'Abyei Administrative Area' => ['Abyei'],
    'Greater Pibor Administrative Area' => ['Pibor', 'Pochalla'],
    'Ruweng Administrative Area' => ['Pariang', 'Abiemnhom']
];

