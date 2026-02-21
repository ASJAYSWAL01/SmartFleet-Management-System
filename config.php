<?php
// FleetFlow Configuration
define('APP_NAME', 'FleetFlow');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/fleetflow/fleetflow');

// Database Configuration (TEAM SAFE VERSION)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fleetflow');
define('DB_USER', 'fleetuser');      // ✅ Changed
define('DB_PASS', 'fleet123');       // ✅ Changed
define('DB_CHARSET', 'utf8mb4');

// Session Configuration
define('SESSION_LIFETIME', 3600); 
define('SESSION_NAME', 'fleetflow_session');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (Development Mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PDO Connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Start session
session_name(SESSION_NAME);
session_start();