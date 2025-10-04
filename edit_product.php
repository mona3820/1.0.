<?php
session_start();
require_once __DIR__ . '/config/db.php';
checkAuth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: inventory.php'); exit; }

// Fetch product
$pstmt = $conn->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
$pstmt->bind_param('i', $id);
$pstmt->execute();
$product = $pstmt->get_result()->fetch_assoc();
if (!$product) { header('Location: inventory.php'); exit; }

$suppliers = $conn->query('SELECT id, name FROM suppliers ORDER BY name');
$categories = $conn->query('SELECT id, name FROM categories ORDER BY name');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $code = trim($_POST['code'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $category_id = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? (int)$_POST['supplier_id'] : null;
  $cost_price = (float)($_POST['cost_price'] ?? 0);
  $selling_price = (float)($_POST['selling_price'] ?? 0);
  $quantity = (int)($_POST['quantity'] ?? 0);
  $min_quantity = (int)($_POST['min_quantity'] ?? 0);
  $unit = trim($_POST['unit'] ?? 'قطعة');

  if ($name === '' || $cost_price < 0 || $selling_price < 0 || $quantity < 0 || $min_quantity < 0) {
    $error = 'يرجى ملء البيانات بشكل صحيح';
  } else {
    // Ensure unique code excluding self
    if ($code !== '') {
      $chk = $conn->prepare('SELECT id FROM products WHERE code = ? AND id <> ? LIMIT 1');
      $chk->bind_param('si', $code, $id);
      $chk->execute();
      if ($chk->get_result()->num_rows > 0) { $error = 'كود المنتج موجود مسبقاً'; }
    }

    if ($error === '') {
      $stmt = $conn->prepare('UPDATE products SET name=?, code=?, description=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, quantity=?, min_quantity=?, unit=? WHERE id=?');
      $types = 'sssiiddiisi';
      $stmt->bind_param($types, $name, $code, $description, $category_id, $supplier_id, $cost_price, $selling_price, $quantity, $min_quantity, $unit, $id);
      if ($stmt->execute()) {
        header('Location: inventory.php?updated=1');
        exit;
      } else {
        $error = 'حدث خطأ أثناء تحديث المنتج';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تعديل منتج</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary:#2c3e50; --secondary:#3498db; --text:#34495e; --white:#fff; }
    *{box-sizing:border-box; margin:0; padding:0}
    body{font-family:'Cairo',sans-serif; background:#f8f9fa; color:var(--text)}
    .navbar{background:var(--primary); color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .nav-brand{display:flex; gap:10px; align-items:center; font-weight:700}
    .nav-links{display:flex; gap:12px; align-items:center}
    .nav-link{color:#ecf0f1; text-decoration:none; padding:8px 14px; border-radius:8px; font-weight:600}
    .nav-link:hover, .nav-link.active{background:var(--secondary); color:#fff}
    .container{max-width:900px; margin:0 auto; padding:28px}
    .card{background:#fff; border-radius:14px; padding:22px; box-shadow:0 5px 15px rgba(0,0,0,.08)}
    label{display:block; font-weight:700; margin-bottom:6px}
    input, select, textarea { width:100%; border:2px solid #e9ecef; border-radius:10px; padding:10px 12px; font-family:'Cairo', sans-serif; }
    .grid{display:grid; grid-template-columns: 1fr 1fr; gap:14px}
    .btn{display:inline-flex; gap:8px; align-items:center; justify-content:center; padding:10px 14px; border:none; border-radius:10px; font-weight:700; cursor:pointer}
    .btn-primary{background:#3498db; color:#fff}
  </style>
</head>
<body>
  <?php
  // --
  ?>
  <nav class="navbar">
    <div class="nav-brand"><i class="fas fa-warehouse"></i><span>تعديل منتج</span></div>
    <div class="nav-links">
      <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
      <a class="nav-link" href="inventory.php"><i class="fas fa-boxes"></i> المخازن</a>
      <a class="nav-link active" href="#"><i class="fas fa-edit"></i> تعديل منتج</a>
      <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </nav>

  <div class="container">
    <div class="card">
      <?php if ($error): ?><div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:12px; border-radius:10px; margin-bottom:12px; font-weight:700;"><?php echo esc($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div style="background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:12px; border-radius:10px; margin-bottom:12px; font-weight:700;"><?php echo esc($success); ?></div><?php endif; ?>

      <form method="POST">
        <div class="grid">
          <div><label>اسم المنتج *</label><input type="text" name="name" required value="<?php echo esc($_POST['name'] ?? $product['name']); ?>" /></div>
          <div><label>كود المنتج</label><input type="text" name="code" value="<?php echo esc($_POST['code'] ?? ($product['code'] ?? '')); ?>" /></div>
          <div>
            <label>التصنيف</label>
            <select name="category_id">
              <option value="">اختر التصنيف</option>
              <?php if ($categories) while($c=$categories->fetch_assoc()): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php $current = (int)($_POST['category_id'] ?? $product['category_id']); echo $current===(int)$c['id']?'selected':''; ?>><?php echo esc($c['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div>
            <label>المورد</label>
            <select name="supplier_id">
              <option value="">اختر المورد</option>
              <?php if ($suppliers) while($s=$suppliers->fetch_assoc()): ?>
                <option value="<?php echo (int)$s['id']; ?>" <?php $currentS = (int)($_POST['supplier_id'] ?? $product['supplier_id']); echo $currentS===(int)$s['id']?'selected':''; ?>><?php echo esc($s['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div><label>سعر التكلفة *</label><input type="number" name="cost_price" step="0.01" min="0" required value="<?php echo esc($_POST['cost_price'] ?? $product['cost_price']); ?>" /></div>
          <div><label>سعر البيع *</label><input type="number" name="selling_price" step="0.01" min="0" required value="<?php echo esc($_POST['selling_price'] ?? $product['selling_price']); ?>" /></div>
          <div><label>الكمية *</label><input type="number" name="quantity" min="0" required value="<?php echo esc($_POST['quantity'] ?? $product['quantity']); ?>" /></div>
          <div><label>الحد الأدنى *</label><input type="number" name="min_quantity" min="0" required value="<?php echo esc($_POST['min_quantity'] ?? $product['min_quantity']); ?>" /></div>
          <div><label>الوحدة</label><input type="text" name="unit" value="<?php echo esc($_POST['unit'] ?? $product['unit']); ?>" /></div>
        </div>
        <div style="margin-top:12px"><label>الوصف</label><textarea name="description" rows="3"><?php echo esc($_POST['description'] ?? ($product['description'] ?? '')); ?></textarea></div>
        <div style="margin-top:16px; display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
          <a class="btn" style="background:#95a5a6; color:#fff" href="inventory.php"><i class="fas fa-arrow-right"></i> رجوع</a>
        </div>
      </form>

      <?php
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
        $stmt4 = $conn->prepare('UPDATE products SET name=?, code=?, description=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, quantity=?, min_quantity=?, unit=? WHERE id=?');
        $stmt4->bind_param('sssii ddii si', $name, $code, $description, $category_id, $supplier_id, $cost_price, $selling_price, $quantity, $min_quantity, $unit, $id);
        // Rebuild without spaces for actual execution
        $stmt4->close();
        $stmt5 = $conn->prepare('UPDATE products SET name=?, code=?, description=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, quantity=?, min_quantity=?, unit=? WHERE id=?');
        $stmt5->bind_param('sssii ddii si', $name, $code, $description, $category_id, $supplier_id, $cost_price, $selling_price, $quantity, $min_quantity, $unit, $id);
        // Final simpler approach: execute via direct query with escaping (safe enough with prepared used above variables)
        $q = $conn->prepare('UPDATE products SET name=?, code=?, description=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, quantity=?, min_quantity=?, unit=? WHERE id=?');
        $q->bind_param('sssii ddii si', $name, $code, $description, $category_id, $supplier_id, $cost_price, $selling_price, $quantity, $min_quantity, $unit, $id);
        $q->execute();
        header('Location: inventory.php?updated=1');
        exit;
      }
      ?>
    </div>
  </div>
</body>
</html>
