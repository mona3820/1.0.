<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
$r = $conn->query("SELECT p.id, p.name, p.quantity, p.min_quantity FROM products p WHERE p.quantity <= p.min_quantity AND p.quantity > 0 ORDER BY p.quantity ASC, p.name ASC");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تقرير المنتجات المنخفضة</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:#34495e;direction:rtl}
.container{max-width:1000px;margin:30px auto;padding:0 20px}
.table{width:100%;border-collapse:collapse}
.table th{background:#2c3e50;color:#fff;padding:12px;text-align:right}
.table td{padding:10px;border-bottom:1px solid #e9ecef}
.badge{display:inline-block;padding:4px 10px;border-radius:12px;background:#fff3cd;color:#856404}
</style>
</head>
<body>
<div class="container">
  <h2><i class="fas fa-exclamation-triangle"></i> المنتجات ذات المخزون المنخفض</h2>
  <table class="table">
    <thead><tr><th>المنتج</th><th>الكمية</th><th>الحد الأدنى</th><th>الحالة</th></tr></thead>
    <tbody>
      <?php if ($r->num_rows === 0) { ?><tr><td colspan="4" style="text-align:center;color:#6c757d;">لا توجد منتجات منخفضة حالياً</td></tr><?php } ?>
      <?php while($row = $r->fetch_assoc()) { ?>
        <tr>
          <td><?php echo esc($row['name']); ?></td>
          <td><?php echo (int)$row['quantity']; ?></td>
          <td><?php echo (int)$row['min_quantity']; ?></td>
          <td><span class="badge">منخفض</span></td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
</body>
</html>
