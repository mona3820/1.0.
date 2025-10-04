<?php
session_start();
require_once __DIR__ . '/config/db.php';
checkAuth();

$user = getUserInfo($conn, (int)$_SESSION['user_id']);

$search = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$supplier_id = trim($_GET['supplier_id'] ?? '');
$stock_filter = trim($_GET['stock'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';

if ($search !== '') {
  $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)';
  $like = '%' . $search . '%';
  $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}
if ($category_id !== '') {
  $where[] = 'p.category_id = ?';
  $params[] = (int)$category_id; $types .= 'i';
}
if ($supplier_id !== '') {
  $where[] = 'p.supplier_id = ?';
  $params[] = (int)$supplier_id; $types .= 'i';
}
if ($stock_filter !== '') {
  if ($stock_filter === 'low') {
    $where[] = 'p.quantity <= p.min_quantity AND p.quantity > 0';
  } elseif ($stock_filter === 'out') {
    $where[] = 'p.quantity = 0';
  } elseif ($stock_filter === 'normal') {
    $where[] = 'p.quantity > p.min_quantity';
  }
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT p.*, COALESCE(c.name,'غير مصنف') AS category_name, s.name AS supplier_name,
               (p.quantity * p.cost_price) AS total_value,
               CASE WHEN p.quantity = 0 THEN 'out' WHEN p.quantity <= p.min_quantity THEN 'low' ELSE 'normal' END AS stock_status
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE $whereSql
        ORDER BY p.quantity ASC, p.name ASC";
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$products = $stmt->get_result();

$total_products = fetchScalar($conn, 'SELECT COUNT(*) FROM products');
$total_value = fetchScalar($conn, 'SELECT COALESCE(SUM(quantity*cost_price),0) FROM products');
$low_stock_count = fetchScalar($conn, 'SELECT COUNT(*) FROM products WHERE quantity <= min_quantity AND quantity > 0');
$out_of_stock_count = fetchScalar($conn, 'SELECT COUNT(*) FROM products WHERE quantity = 0');

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name');
$suppliers = $conn->query('SELECT id, name FROM suppliers ORDER BY name');

// Inventory movement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movement'])) {
  $product_id = (int)($_POST['product_id'] ?? 0);
  $movement_type = ($_POST['movement_type'] ?? 'in') === 'out' ? 'out' : 'in';
  $quantity = (int)($_POST['quantity'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');
  if ($product_id > 0 && $quantity > 0) {
    if (logInventoryMovement($conn, $product_id, $movement_type, $quantity, $notes, (int)$_SESSION['user_id'])) {
      header('Location: inventory.php?success=1');
      exit;
    }
  }
  header('Location: inventory.php?error=1');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>إدارة المخازن</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary:#2c3e50; --secondary:#3498db; --accent:#e74c3c; --success:#27ae60; --warning:#f39c12; --light:#ecf0f1; --text:#34495e; --white:#fff; }
    *{box-sizing:border-box; margin:0; padding:0}
    body{font-family:'Cairo',sans-serif; background:#f8f9fa; color:var(--text)}
    .navbar{background:var(--primary); color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .nav-brand{display:flex; gap:10px; align-items:center; font-weight:700}
    .nav-links{display:flex; gap:12px; align-items:center}
    .nav-link{color:#ecf0f1; text-decoration:none; padding:8px 14px; border-radius:8px; font-weight:600}
    .nav-link:hover, .nav-link.active{background:var(--secondary); color:#fff}
    .container{max-width:1800px; margin:0 auto; padding:28px}
    .stats{display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:16px; margin-bottom:18px}
    .card{background:#fff; border-radius:14px; padding:18px; box-shadow:0 5px 15px rgba(0,0,0,.08)}
    .filter{background:#fff; border-radius:14px; padding:18px; box-shadow:0 5px 15px rgba(0,0,0,.08); margin-bottom:18px}
    .filter form{display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:12px; align-items:end}
    label{font-weight:700; color:#2c3e50; margin-bottom:6px; display:block}
    input, select { width:100%; border:2px solid #e9ecef; border-radius:10px; padding:10px 12px; font-family:'Cairo', sans-serif; }
    .btn{display:inline-flex; gap:8px; align-items:center; justify-content:center; padding:10px 14px; border:none; border-radius:10px; font-weight:700; cursor:pointer}
    .btn-primary{background:var(--secondary); color:#fff}
    .btn-success{background:var(--success); color:#fff}
    .btn-outline{background:transparent; border:2px solid var(--secondary); color:var(--secondary)}
    table{width:100%; border-collapse:collapse}
    th{background:var(--primary); color:#fff; padding:12px; text-align:right}
    td{padding:10px 12px; border-bottom:1px solid #e9ecef}
    .status{padding:6px 10px; border-radius:14px; font-weight:700; font-size:12px}
    .st-normal{background:#d4edda; color:#155724}
    .st-low{background:#fff3cd; color:#856404}
    .st-out{background:#f8d7da; color:#721c24}
    .modal{display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); align-items:center; justify-content:center; z-index:1000}
    .modal .content{background:#fff; border-radius:14px; padding:20px; width:90%; max-width:480px}
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand"><i class="fas fa-warehouse"></i><span>إدارة المخازن</span></div>
    <div class="nav-links">
      <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
      <a class="nav-link active" href="inventory.php"><i class="fas fa-boxes"></i> المخازن</a>
      <a class="nav-link" href="add_product.php"><i class="fas fa-plus"></i> إضافة منتج</a>
      <span class="nav-link" style="background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); border-radius:20px;"> <i class="fas fa-user"></i> <?php echo esc($user['username'] ?? ''); ?> </span>
      <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </nav>

  <div class="container">
    <div class="stats">
      <div class="card"><strong>إجمالي المنتجات</strong><div style="font-size:1.8rem; color:#3498db; font-weight:800"><?php echo (int)$total_products; ?></div></div>
      <div class="card"><strong>القيمة الإجمالية</strong><div style="font-size:1.8rem; color:#27ae60; font-weight:800"><?php echo number_format($total_value,2); ?> ر.س</div></div>
      <div class="card"><strong>منخفض</strong><div style="font-size:1.8rem; color:#f39c12; font-weight:800"><?php echo (int)$low_stock_count; ?></div></div>
      <div class="card"><strong>منتهي</strong><div style="font-size:1.8rem; color:#e74c3c; font-weight:800"><?php echo (int)$out_of_stock_count; ?></div></div>
    </div>

    <div class="filter">
      <form method="GET" action="">
        <div>
          <label>بحث</label>
          <input type="text" name="search" placeholder="الاسم، الكود، الوصف..." value="<?php echo esc($search); ?>" />
        </div>
        <div>
          <label>التصنيف</label>
          <select name="category_id">
            <option value="">جميع التصنيفات</option>
            <?php if ($categories) while($c=$categories->fetch_assoc()): ?>
              <option value="<?php echo (int)$c['id']; ?>" <?php echo ($category_id!=='' && (int)$category_id===(int)$c['id'])?'selected':''; ?>><?php echo esc($c['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label>المورد</label>
          <select name="supplier_id">
            <option value="">جميع الموردين</option>
            <?php if ($suppliers) while($s=$suppliers->fetch_assoc()): ?>
              <option value="<?php echo (int)$s['id']; ?>" <?php echo ($supplier_id!=='' && (int)$supplier_id===(int)$s['id'])?'selected':''; ?>><?php echo esc($s['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label>حالة المخزون</label>
          <select name="stock">
            <option value="">جميع الحالات</option>
            <option value="normal" <?php echo $stock_filter==='normal'?'selected':''; ?>>طبيعي</option>
            <option value="low" <?php echo $stock_filter==='low'?'selected':''; ?>>منخفض</option>
            <option value="out" <?php echo $stock_filter==='out'?'selected':''; ?>>منتهي</option>
          </select>
        </div>
        <div>
          <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> بحث</button>
        </div>
        <div>
          <a class="btn btn-outline" href="inventory.php"><i class="fas fa-rotate-left"></i> إعادة ضبط</a>
        </div>
      </form>
    </div>

    <div class="card" style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>المنتج</th>
            <th>الكود</th>
            <th>التصنيف</th>
            <th>المورد</th>
            <th>الكمية</th>
            <th>حد أدنى</th>
            <th>سعر الشراء</th>
            <th>سعر البيع</th>
            <th>القيمة</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products && $products->num_rows): while($p = $products->fetch_assoc()): ?>
            <tr>
              <td><strong><?php echo esc($p['name']); ?></strong><br><small style="color:#6c757d;"><?php echo esc(mb_strimwidth($p['description'] ?? '', 0, 60, '...','UTF-8')); ?></small></td>
              <td><code><?php echo esc($p['code'] ?? ''); ?></code></td>
              <td><?php echo esc($p['category_name']); ?></td>
              <td><?php echo esc($p['supplier_name'] ?? ''); ?></td>
              <td><?php echo (int)$p['quantity']; ?></td>
              <td><?php echo (int)$p['min_quantity']; ?></td>
              <td><?php echo number_format((float)$p['cost_price'],2); ?> ر.س</td>
              <td><?php echo number_format((float)$p['selling_price'],2); ?> ر.س</td>
              <td><strong style="color:#27ae60;"><?php echo number_format((float)$p['total_value'],2); ?> ر.س</strong></td>
              <td>
                <?php if ($p['stock_status']==='normal'): ?>
                  <span class="status st-normal">طبيعي</span>
                <?php elseif ($p['stock_status']==='low'): ?>
                  <span class="status st-low">منخفض</span>
                <?php else: ?>
                  <span class="status st-out">منتهي</span>
                <?php endif; ?>
              </td>
              <td style="display:flex; gap:6px;">
                <button class="btn btn-primary" onclick="openMovement(<?php echo (int)$p['id']; ?>, '<?php echo esc($p['name']); ?>')"><i class="fas fa-exchange-alt"></i></button>
                <a class="btn btn-success" href="edit_product.php?id=<?php echo (int)$p['id']; ?>"><i class="fas fa-edit"></i></a>
                <a class="btn" style="background:#e74c3c; color:#fff" href="delete_product.php?id=<?php echo (int)$p['id']; ?>" onclick="return confirm('تأكيد حذف المنتج؟');"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="11" style="text-align:center; color:#6c757d; padding:26px">لا توجد منتجات</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="mv" class="modal">
    <div class="content">
      <h3 style="margin:0 0 10px; color:#2c3e50" id="mvTitle">حركة مخزون</h3>
      <form method="POST">
        <input type="hidden" name="add_movement" value="1" />
        <input type="hidden" id="mvProductId" name="product_id" />
        <div style="margin-bottom:12px"><label>المنتج</label><input id="mvProductName" type="text" readonly /></div>
        <div style="margin-bottom:12px"><label>نوع الحركة</label>
          <select name="movement_type"><option value="in">دخول مخزون</option><option value="out">خروج مخزون</option></select>
        </div>
        <div style="margin-bottom:12px"><label>الكمية</label><input type="number" name="quantity" min="1" required /></div>
        <div style="margin-bottom:12px"><label>ملاحظات</label><input type="text" name="notes" placeholder="اختياري" /></div>
        <div style="display:flex; gap:10px;">
          <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> حفظ</button>
          <button class="btn btn-outline" type="button" onclick="closeMovement()"><i class="fas fa-times"></i> إغلاق</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openMovement(id, name){
      document.getElementById('mvProductId').value = id;
      document.getElementById('mvProductName').value = name;
      document.getElementById('mvTitle').textContent = 'حركة مخزون - ' + name;
      document.getElementById('mv').style.display='flex';
    }
    function closeMovement(){ document.getElementById('mv').style.display='none'; }
    window.addEventListener('click', function(e){ if(e.target === document.getElementById('mv')) closeMovement(); });
  </script>
</body>
</html>
