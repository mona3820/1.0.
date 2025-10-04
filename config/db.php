<?php
// Database connection and common helpers
// Uses environment variables if available, otherwise sensible defaults
// DB_HOST, DB_NAME, DB_USER, DB_PASS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getEnvOrDefault(string $key, string $default): string {
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

$DB_HOST = getEnvOrDefault('DB_HOST', '127.0.0.1');
$DB_NAME = getEnvOrDefault('DB_NAME', 'inventory_app');
$DB_USER = getEnvOrDefault('DB_USER', 'root');
$DB_PASS = getEnvOrDefault('DB_PASS', '');

// Connect, creating database if needed (when permissions allow)
$__conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($__conn->connect_errno) {
    if ($__conn->connect_errno === 1049) { // Unknown database
        $tmp = @new mysqli($DB_HOST, $DB_USER, $DB_PASS);
        if ($tmp->connect_errno) {
            die('Database connection failed: ' . $tmp->connect_error);
        }
        $dbNameEsc = $tmp->real_escape_string($DB_NAME);
        $tmp->query("CREATE DATABASE IF NOT EXISTS `$dbNameEsc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tmp->close();
        $__conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($__conn->connect_errno) {
            die('Database connection failed after create: ' . $__conn->connect_error);
        }
    } else {
        die('Database connection failed: ' . $__conn->connect_error);
    }
}

$__conn->set_charset('utf8mb4');

// Expose as $conn
$conn = $__conn;

function checkAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function redirectIfAuthenticated(): void {
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
}

function esc(?string $value): string {
    if ($value === null) return '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getUserInfo(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare('SELECT id, username, role, last_login, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row ?: ['id' => 0, 'username' => 'Guest', 'role' => 'user'];
}

function logInventoryMovement(mysqli $conn, int $productId, string $type, int $quantity, string $notes, int $userId): bool {
    if ($quantity <= 0) return false;
    $type = $type === 'out' ? 'out' : 'in';

    $conn->begin_transaction();
    try {
        if ($type === 'in') {
            $stmt = $conn->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?');
        } else {
            $stmt = $conn->prepare('UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ?');
        }
        $stmt->bind_param('ii', $quantity, $productId);
        $stmt->execute();

        $stmt2 = $conn->prepare('INSERT INTO inventory_movements (product_id, type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt2->bind_param('isisi', $productId, $type, $quantity, $notes, $userId);
        $stmt2->execute();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

// Convenience: fetch scalar value safely
function fetchScalar(mysqli $conn, string $sql): int|float {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_row();
    if (!$row) return 0;
    return is_numeric($row[0]) ? ($row[0] + 0) : 0;
}
