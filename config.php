<?php
// FleetFlow Configuration
define('APP_NAME', 'FleetFlow');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/fleetflow');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fleetflow');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'fleetflow_session');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (set to 0 in production)
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

// ─── Helper Functions ──────────────────────────────────────────────────────────

/**
 * Calculate cost per km for a vehicle
 */
function calculateCostPerKm(int $vehicleId, ?string $startDate = null, ?string $endDate = null): float {
    $db = getDB();
    $params = ['vehicle_id' => $vehicleId];
    $dateCond = '';
    if ($startDate && $endDate) {
        $dateCond = ' AND cost_date BETWEEN :start AND :end';
        $params['start'] = $startDate;
        $params['end']   = $endDate;
    }

    // Total costs
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM vehicle_costs WHERE vehicle_id=:vehicle_id" . $dateCond);
    $stmt->execute($params);
    $totalCost = (float)$stmt->fetchColumn();

    // Total km from completed trips
    $tripParams = ['vehicle_id' => $vehicleId];
    $tripDateCond = '';
    if ($startDate && $endDate) {
        $tripDateCond = " AND actual_arrival BETWEEN :start AND :end";
        $tripParams['start'] = $startDate;
        $tripParams['end']   = $endDate;
    }
    $stmt2 = $db->prepare("SELECT COALESCE(SUM(distance_km),0) as total_km FROM trips WHERE vehicle_id=:vehicle_id AND status='Completed'" . $tripDateCond);
    $stmt2->execute($tripParams);
    $totalKm = (float)$stmt2->fetchColumn();

    return $totalKm > 0 ? round($totalCost / $totalKm, 2) : 0.0;
}

/**
 * Calculate ROI for a vehicle
 * ROI = (Revenue - Cost) / Cost * 100
 */
function calculateROI(int $vehicleId, ?string $startDate = null, ?string $endDate = null): float {
    $db = getDB();
    $params = ['vehicle_id' => $vehicleId];
    $dateCond = '';
    if ($startDate && $endDate) {
        $dateCond = " AND actual_arrival BETWEEN :start AND :end";
        $params['start'] = $startDate;
        $params['end']   = $endDate;
    }

    // Revenue from completed trips
    $stmt = $db->prepare("SELECT COALESCE(SUM(revenue),0) FROM trips WHERE vehicle_id=:vehicle_id AND status='Completed'" . $dateCond);
    $stmt->execute($params);
    $revenue = (float)$stmt->fetchColumn();

    // Total costs
    $costParams = ['vehicle_id' => $vehicleId];
    $costDateCond = '';
    if ($startDate && $endDate) {
        $costDateCond = " AND cost_date BETWEEN :start AND :end";
        $costParams['start'] = $startDate;
        $costParams['end']   = $endDate;
    }
    $stmt2 = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM vehicle_costs WHERE vehicle_id=:vehicle_id" . $costDateCond);
    $stmt2->execute($costParams);
    $cost = (float)$stmt2->fetchColumn();

    if ($cost <= 0) return 0.0;
    return round((($revenue - $cost) / $cost) * 100, 2);
}

/**
 * Generate unique trip number
 */
function generateTripNumber(): string {
    return 'TRP-' . strtoupper(substr(uniqid(), -6)) . '-' . date('ymd');
}

/**
 * Flash message helpers
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize output
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $url): never {
    header("Location: $url");
    exit;
}

/**
 * Check if user has role
 */
function hasRole(string|array $roles): bool {
    if (!isset($_SESSION['user'])) return false;
    $userRole = $_SESSION['user']['role'];
    return is_array($roles) ? in_array($userRole, $roles) : $userRole === $roles;
}

/**
 * Require role or redirect
 */
function requireRole(string|array $roles): void {
    if (!hasRole($roles)) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect('index.php');
    }
}

/**
 * Format currency
 */
function formatCurrency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

/**
 * Status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'Available'   => 'bg-emerald-100 text-emerald-800',
        'On Trip'     => 'bg-blue-100 text-blue-800',
        'In Shop'     => 'bg-amber-100 text-amber-800',
        'Suspended'   => 'bg-red-100 text-red-800',
        'Completed'   => 'bg-green-100 text-green-800',
        'Cancelled'   => 'bg-gray-100 text-gray-700',
        'Draft'       => 'bg-slate-100 text-slate-700',
        'Dispatched'  => 'bg-indigo-100 text-indigo-800',
        'On Duty'     => 'bg-emerald-100 text-emerald-800',
        'Off Duty'    => 'bg-gray-100 text-gray-700',
        'Scheduled'   => 'bg-blue-100 text-blue-700',
        'In Progress' => 'bg-amber-100 text-amber-800',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-700';
    return "<span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $cls'>$status</span>";
}

// Start session
session_name(SESSION_NAME);
session_start();
