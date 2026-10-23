<?php
/**
 * Database Connection Configuration
 * Uses PHP Data Objects (PDO) for secure, prepared SQL interactions.
 */

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'ngo_beneficiary_db');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database doesn't exist yet, guide to setup
            die("
                <div style='font-family: Arial, sans-serif; max-width: 650px; margin: 50px auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
                    <h2 style='color: #b91c1c; margin-top: 0;'>Database Connection Error</h2>
                    <p style='color: #475569;'>Could not connect to the database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
                    <p style='background: #f1f5f9; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #0f172a;'>" . htmlspecialchars($e->getMessage()) . "</p>
                    <p>If this is the first time running the system, please initialize the database:</p>
                    <a href='setup.php' style='display: inline-block; background: #0284c7; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Run System Setup & Seeder</a>
                </div>
            ");
        }
    }

    return $pdo;
}

