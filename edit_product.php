<?php
require_once __DIR__ . '/config/db.php';
checkAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: inventory.php'); exit; }

// Load product
$stmt = $conn->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) { header('Location: inventory.php'); exit; }

$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $supplier_id = $_POST['supplier_id'] !== '' ? (int)$_POST['supplier_id'] : null;
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $min_quantity = (int)($_POST['min_quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'قطعة');

    if ($name === '' || $cost_price < 0 || $selling_price < 0 || $min_quantity < 0) {
        $error = 'يرجى ملء البيانات بشكل صحيح';
    } else {
        // Unique code check excluding current product
        if ($code !== '') {
            $codeStmt = $conn->prepare('SELECT id FROM products WHERE code = ? AND id <> ? LIMIT 1');
            $codeStmt->bind_param('si', $code, $id);
            $codeStmt->execute();
            if ($codeStmt->get_result()->num_rows > 0) { $error = 'كود المنتج مستخدم مسبقاً'; }
        }
        if ($error === '') {
            $stmt = $conn->prepare('UPDATE products SET name=?, code=?, description=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, min_quantity=?, unit=? WHERE id=?');
            $stmt->bind_param('sssiiddisi', $name, $code, $description, $category_id, $supplier_id, $cost_price, $selling_price, $min_quantity, $unit, $id);
            if ($stmt->execute()) { $success = 'تم تحديث المنتج بنجاح'; } else { $error = 'حدث خطأ أثناء التحديث'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تعديل المنتج</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box} :root{--primary:#2c3e50;--secondary:#3498db;--success:#27ae60;--light:#ecf0f1;--text:#34495e;--white:#fff}
body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:var(--text);direction:rtl}
.navbar{background:var(--primary);color:#fff;padding:15px 30px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:800px;margin:30px auto;padding:0 20px}
.card{background:#fff;border-radius:15px;padding:30px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
.form-group{margin-bottom:20px}.form-label{display:block;margin-bottom:8px;color:var(--primary);font-weight:600}
.form-control{width:100%;padding:12px 15px;border:2px solid #e9ecef;border-radius:8px;font-size:16px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 25px;border:none;border-radius:8px;font-weight:600;cursor:pointer}
.btn-primary{background:var(--secondary);color:#fff}
.alert{padding:15px;border-radius:8px;margin-bottom:20px}.alert-error{background:#f8d7da;color:#721c24}.alert-success{background:#d4edda;color:#155724}
</style>
</head>
<body>
<nav class="navbar">
  <div class="nav-brand"><i class="fas fa-warehouse"></i><span>تعديل المنتج</span></div>
  <div class="nav-links">
    <a href="inventory.php" class="nav-link" style="color:#ecf0f1;text-decoration:none;">المخازن</a>
  </div>
</nav>
<div class="container">
  <div class="card">
    <h1 style="margin-bottom:20px;">تعديل المنتج: <?php echo esc($product['name']); ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group"><label class="form-label">اسم المنتج *</label><input type="text" name="name" class="form-control" required value="<?php echo esc($product['name']); ?>"></div>
      <div class="form-group"><label class="form-label">كود المنتج</label><input type="text" name="code" class="form-control" value="<?php echo esc($product['code']); ?>"></div>
      <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-control" rows="3"><?php echo esc($product['description']); ?></textarea></div>
      <div class="form-group"><label class="form-label">التصنيف</label><select name="category_id" class="form-control"><option value="">اختر التصنيف</option><?php while($category = $categories->fetch_assoc()): ?><option value="<?php echo $category['id']; ?>" <?php echo ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>><?php echo $category['name']; ?></option><?php endwhile; ?></select></div>
      <?php $suppliers->data_seek(0); ?>
      <div class="form-group"><label class="form-label">المورد</label><select name="supplier_id" class="form-control"><option value="">اختر المورد</option><?php while($supplier = $suppliers->fetch_assoc()): ?><option value="<?php echo $supplier['id']; ?>" <?php echo ($product['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>><?php echo $supplier['name']; ?></option><?php endwhile; ?></select></div>
      <div class="form-group"><label class="form-label">سعر التكلفة *</label><input type="number" step="0.01" name="cost_price" class="form-control" required value="<?php echo (float)$product['cost_price']; ?>"></div>
      <div class="form-group"><label class="form-label">سعر البيع *</label><input type="number" step="0.01" name="selling_price" class="form-control" required value="<?php echo (float)$product['selling_price']; ?>"></div>
      <div class="form-group"><label class="form-label">الحد الأدنى للكمية *</label><input type="number" name="min_quantity" class="form-control" required value="<?php echo (int)$product['min_quantity']; ?>"></div>
      <div class="form-group"><label class="form-label">الوحدة</label><input type="text" name="unit" class="form-control" value="<?php echo esc($product['unit']); ?>"></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
    </form>
  </div>
</div>
</body>
</html>
