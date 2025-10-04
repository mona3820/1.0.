<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
$user_info = getUserInfo($conn, (int)$_SESSION['user_id']);

$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$supplier_id = $_GET['supplier'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

$where = ["1=1"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}
if ($category_id !== '') {
    $where[] = "p.category_id = ?";
    $params[] = (int)$category_id; $types .= 'i';
}
if ($supplier_id !== '') {
    $where[] = "p.supplier_id = ?";
    $params[] = (int)$supplier_id; $types .= 'i';
}
if ($stock_filter !== '') {
    if ($stock_filter === 'low') { $where[] = "p.quantity <= p.min_quantity AND p.quantity > 0"; }
    elseif ($stock_filter === 'out') { $where[] = "p.quantity = 0"; }
    elseif ($stock_filter === 'normal') { $where[] = "p.quantity > p.min_quantity"; }
}
$where_sql = implode(' AND ', $where);

$sql = "SELECT p.*, s.name AS supplier_name, c.name AS category_name,
               (p.quantity * p.cost_price) AS total_value,
               CASE WHEN p.quantity = 0 THEN 'out_of_stock'
                    WHEN p.quantity <= p.min_quantity THEN 'low_stock'
                    ELSE 'normal' END AS stock_status
        FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $where_sql
        ORDER BY p.quantity ASC, p.name ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$products = $stmt->get_result();

$total_products = fetchScalar($conn, "SELECT COUNT(*) FROM products");
$total_value = (float)fetchScalar($conn, "SELECT COALESCE(SUM(quantity * cost_price),0) FROM products");
$low_stock_count = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity <= min_quantity AND quantity > 0");
$out_of_stock_count = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity = 0");

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movement'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $movement_type = $_POST['movement_type'] === 'out' ? 'out' : 'in';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if ($product_id > 0 && $quantity > 0) {
        logInventoryMovement($conn, $product_id, $movement_type, $quantity, $notes, (int)$_SESSION['user_id']);
        header('Location: inventory.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة المخازن</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box} :root{--primary:#2c3e50;--secondary:#3498db;--accent:#e74c3c;--success:#27ae60;--warning:#f39c12;--light:#ecf0f1;--text:#34495e;--white:#fff}
body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:var(--text);direction:rtl}
.navbar{background:var(--primary);color:var(--white);padding:15px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.nav-link{color:#ecf0f1;text-decoration:none;padding:8px 16px;border-radius:6px;font-weight:600;display:flex;gap:8px;align-items:center}
.nav-link:hover,.nav-link.active{background:var(--secondary);color:var(--white)}
.container{max-width:1800px;margin:0 auto;padding:30px}
.page-header{background:var(--white);border-radius:15px;padding:30px;margin-bottom:30px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--white);border-radius:15px;padding:25px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,.1)}
.table-container{background:var(--white);border-radius:15px;overflow:hidden;box-shadow:0 5px 15px rgba(0,0,0,.1);margin-bottom:30px}
.table{width:100%;border-collapse:collapse}.table th{background:var(--primary);color:#fff;padding:15px;text-align:right;font-weight:600}.table td{padding:12px 15px;border-bottom:1px solid #e9ecef;text-align:right}
.form-control{width:100%;padding:10px 15px;border:2px solid #e9ecef;border-radius:8px;font-size:14px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none}
.btn-primary{background:var(--secondary);color:#fff}.btn-success{background:var(--success);color:#fff}.btn-warning{background:var(--warning);color:#fff}.btn-danger{background:var(--accent);color:#fff}.btn-outline{background:transparent;border:2px solid var(--secondary);color:var(--secondary)}
.stock-status{padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;text-align:center}
.status-normal{background:#d4edda;color:#155724}.status-low{background:#fff3cd;color:#856404}.status-out{background:#f8d7da;color:#721c24}
.quantity-bar{height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;margin-top:5px}
.quantity-fill{height:100%;border-radius:4px}
.fill-normal{background:var(--success)}.fill-low{background:var(--warning)}.fill-out{background:var(--accent)}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center}
.modal-content{background:#fff;border-radius:15px;padding:30px;width:90%;max-width:500px;box-shadow:0 20px 40px rgba(0,0,0,.2)}
</style>
</head>
<body>
<nav class="navbar">
  <div class="nav-brand"><i class="fas fa-warehouse"></i><span>إدارة المخازن</span></div>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> الرئيسية</a>
    <a href="inventory.php" class="nav-link active"><i class="fas fa-warehouse"></i> المخازن</a>
    <a href="products.php" class="nav-link"><i class="fas fa-boxes"></i> المنتجات</a>
    <a href="suppliers.php" class="nav-link"><i class="fas fa-truck-loading"></i> الموردين</a>
    <a href="reports.php?type=inventory" class="nav-link"><i class="fas fa-chart-bar"></i> تقارير المخازن</a>
  </div>
</nav>
<div class="container">
  <div class="page-header">
    <h1 class="page-title"><i class="fas fa-warehouse"></i> نظام إدارة المخازن المتكامل</h1>
    <p>إدارة كاملة للمخزون - تتبع، تحليل، وتقارير مفصلة</p>
  </div>
  <div class="stats-grid">
    <div class="stat-card"><h3>إجمالي المنتجات</h3><div style="font-size:2rem;color:#3498db;font-weight:bold;"><?php echo $total_products; ?></div></div>
    <div class="stat-card"><h3>القيمة الإجمالية</h3><div style="font-size:2rem;color:#27ae60;font-weight:bold;"><?php echo number_format($total_value,2); ?> <small>ر.س</small></div></div>
    <div class="stat-card"><h3>منتجات منخفضة</h3><div style="font-size:2rem;color:#f39c12;font-weight:bold;"><?php echo $low_stock_count; ?></div></div>
    <div class="stat-card"><h3>منتجات منتهية</h3><div style="font-size:2rem;color:#e74c3c;font-weight:bold;"><?php echo $out_of_stock_count; ?></div></div>
  </div>
  <div class="table-container">
    <form method="GET" action="" style="padding:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:15px; align-items:end;">
      <div><label class="form-label">بحث</label><input type="text" name="search" class="form-control" placeholder="ابحث بالاسم، الكود، أو الوصف..." value="<?php echo htmlspecialchars($search); ?>"></div>
      <div><label class="form-label">التصنيف</label><select name="category_id" class="form-control"><option value="">جميع التصنيفات</option><?php while($cat = $categories->fetch_assoc()){ ?><option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option><?php } ?></select></div>
      <div><label class="form-label">المورد</label><select name="supplier" class="form-control"><option value="">جميع الموردين</option><?php while($sup = $suppliers->fetch_assoc()){ ?><option value="<?php echo $sup['id']; ?>" <?php echo ($supplier_id == $sup['id']) ? 'selected' : ''; ?>><?php echo $sup['name']; ?></option><?php } ?></select></div>
      <div><label class="form-label">حالة المخزون</label><select name="stock" class="form-control"><option value="">جميع الحالات</option><option value="normal" <?php echo $stock_filter==='normal'?'selected':''; ?>>طبيعي</option><option value="low" <?php echo $stock_filter==='low'?'selected':''; ?>>منخفض</option><option value="out" <?php echo $stock_filter==='out'?'selected':''; ?>>منتهي</option></select></div>
      <div><button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-search"></i> بحث</button></div>
    </form>
  </div>
  <div class="table-container">
    <table class="table">
      <thead><tr><th>الصورة</th><th>المنتج</th><th>الكود</th><th>التصنيف</th><th>المورد</th><th>المخزون</th><th>سعر الشراء</th><th>سعر البيع</th><th>القيمة</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
      <tbody>
        <?php if ($products->num_rows === 0) { ?>
          <tr><td colspan="11" style="text-align:center; padding:40px; color:#6c757d;"><i class="fas fa-inbox" style="font-size:3rem; margin-bottom:15px; opacity:0.5;"></i><br>لا توجد منتجات لعرضها</td></tr>
        <?php } ?>
        <?php while($p = $products->fetch_assoc()) { $stock_percentage = ($p['min_quantity']>0) ? min(($p['quantity']/$p['min_quantity'])*100, 100) : 100; ?>
          <tr>
            <td><div style="width:50px;height:50px;border-radius:8px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;color:#6c757d;"><i class="fas fa-box"></i></div></td>
            <td><strong><?php echo esc($p['name']); ?></strong><?php if(!empty($p['description'])) { ?><br><small style="color:#6c757d;"><?php echo esc(mb_substr($p['description'],0,50)); ?>...</small><?php } ?></td>
            <td><code><?php echo esc($p['code'] ?? 'N/A'); ?></code></td>
            <td><?php echo esc($p['category_name'] ?? 'غير مصنف'); ?></td>
            <td><?php echo esc($p['supplier_name'] ?? 'غير محدد'); ?></td>
            <td>
              <div style="text-align:center;"><strong><?php echo (int)$p['quantity']; ?></strong><?php if($p['min_quantity']>0){ ?><br><small>/ <?php echo (int)$p['min_quantity']; ?> حد أدنى</small><?php } ?></div>
              <div class="quantity-bar"><div class="quantity-fill fill-<?php echo $p['stock_status']; ?>" style="width: <?php echo $stock_percentage; ?>%"></div></div>
            </td>
            <td><?php echo number_format((float)($p['cost_price'] ?? 0),2); ?> ر.س</td>
            <td><?php echo number_format((float)($p['selling_price'] ?? 0),2); ?> ر.س</td>
            <td><strong style="color:#27ae60;"><?php echo number_format((float)$p['total_value'],2); ?> ر.س</strong></td>
            <td>
              <?php if($p['stock_status']==='normal'){ ?><span class="stock-status status-normal">🟢 طبيعي</span><?php } elseif($p['stock_status']==='low_stock'){ ?><span class="stock-status status-low">🟡 منخفض</span><?php } else { ?><span class="stock-status status-out">🔴 منتهي</span><?php } ?>
            </td>
            <td>
              <div style="display:flex; gap:5px; flex-wrap:wrap;">
                <button onclick="showMovementModal(<?php echo (int)$p['id']; ?>, '<?php echo esc($p['name']); ?>')" class="btn btn-primary" style="padding:5px 8px; font-size:12px;"><i class="fas fa-exchange-alt"></i></button>
                <a href="edit_product.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-warning" style="padding:5px 8px; font-size:12px;"><i class="fas fa-edit"></i></a>
                <a href="delete_product.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-danger" style="padding:5px 8px; font-size:12px;" onclick="return confirm('تأكيد حذف المنتج؟');"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
    <a href="add_product.php" class="btn btn-success"><i class="fas fa-plus-circle"></i> إضافة منتج جديد</a>
    <a href="inventory_movements.php" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> حركات المخزون</a>
    <a href="low_stock_report.php" class="btn btn-warning"><i class="fas fa-exclamation-triangle"></i> تقرير المنتجات المنخفضة</a>
    <a href="export_inventory.php" class="btn btn-outline"><i class="fas fa-file-export"></i> تصدير البيانات</a>
  </div>
</div>
<div id="movementModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #e9ecef;">
      <h3 id="movementModalTitle">حركة مخزون</h3>
      <button class="btn btn-outline" onclick="closeMovementModal()">&times;</button>
    </div>
    <form id="movementForm" method="POST">
      <input type="hidden" name="add_movement" value="1">
      <input type="hidden" id="product_id" name="product_id">
      <div class="form-group"><label class="form-label">المنتج</label><input type="text" id="product_name" class="form-control" readonly></div>
      <div class="form-group"><label class="form-label">نوع الحركة</label><select id="movement_type" name="movement_type" class="form-control" required><option value="in">دخول مخزون</option><option value="out">خروج مخزون</option></select></div>
      <div class="form-group"><label class="form-label">الكمية</label><input type="number" name="quantity" class="form-control" min="1" required></div>
      <div class="form-group"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات حول الحركة..."></textarea></div>
      <div style="display:flex; gap:10px; margin-top:20px;"><button type="submit" class="btn btn-success" style="flex:1"><i class="fas fa-save"></i> حفظ الحركة</button><button type="button" class="btn btn-outline" onclick="closeMovementModal()" style="flex:1"><i class="fas fa-times"></i> إلغاء</button></div>
    </form>
  </div>
</div>
<script>
function showMovementModal(id, name){ document.getElementById('product_id').value = id; document.getElementById('product_name').value = name; document.getElementById('movementModalTitle').textContent = 'حركة مخزون - ' + name; document.getElementById('movementModal').style.display = 'flex'; }
function closeMovementModal(){ document.getElementById('movementModal').style.display = 'none'; document.getElementById('movementForm').reset(); }
</script>
</body>
</html>
