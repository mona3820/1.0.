<?php
session_start();
require_once __DIR__ . '/config/db.php';
checkAuth();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
  $stmt->bind_param('i', $id);
  $stmt->execute();
}
header('Location: inventory.php?deleted=1');
exit;
