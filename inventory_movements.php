<?php
require_once __DIR__ . '/config/db.php';
checkAuth();

$q = $conn->query("SELECT im.*, p.name AS product_name, u.username AS created_by_name FROM inventory_movements im LEFT JOIN products p ON im.product_id=p.id LEFT JOIN users u ON im.created_by=u.id ORDER BY im.id DESC LIMIT 200");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>حركات المخزون</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:#34495e;direction:rtl}
.container{max-width:1200px;margin:30px auto;padding:0 20px}
.table{width:100%;border-collapse:collapse}
.table th{background:#2c3e50;color:#fff;padding:12px;text-align:right}
.table td{padding:10px;border-bottom:1px solid #e9ecef}
.card{background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:20px}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h2 style="margin-top:0;margin-bottom:15px"><i class="fas fa-exchange-alt"></i> آخر حركات المخزون</h2>
    <table class="table">
      <thead><tr><th>الرقم</th><th>المنتج</th><th>النوع</th><th>الكمية</th><th>الملاحظات</th><th>بواسطة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php while($row = $q->fetch_assoc()) { ?>
          <tr>
            <td><?php echo (int)$row['id']; ?></td>
            <td><?php echo esc($row['product_name']); ?></td>
            <td><?php echo $row['type'] === 'in' ? 'دخول' : 'خروج'; ?></td>
            <td><?php echo (int)$row['quantity']; ?></td>
            <td><?php echo esc($row['notes']); ?></td>
            <td><?php echo esc($row['created_by_name']); ?></td>
            <td><?php echo esc($row['created_at']); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
