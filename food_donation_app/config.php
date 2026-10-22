<?php
// Configuration for Food Donation & Distribution System
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'FoodDonationDistributionDB');
define('DB_USER', 'root');
define('DB_PASS', 'Root@123'); // Set your MySQL password here if configured

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("<div style='padding:20px; font-family:sans-serif; background:#f8d7da; color:#721c24;'>
        <h3>Database Connection Failed</h3>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Make sure MySQL service is running in XAMPP or Workbench and the database name <code>" . DB_NAME . "</code> exists.</p>
    </div>");
}

// Security & Helper Functions
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: dashboard.php?error=Unauthorized+Access");
        exit;
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>