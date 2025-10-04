<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
$user_info = getUserInfo($conn, (int)$_SESSION['user_id']);

$total_workers = fetchScalar($conn, "SELECT COUNT(*) FROM workers");
$workers_present_today = fetchScalar($conn, "SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
$pending_salaries = fetchScalar($conn, "SELECT COUNT(*) FROM salaries WHERE payment_status = 'pending'");
$month_revenue = fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM revenues WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
$month_expenses = fetchScalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
$net_profit = $month_revenue - $month_expenses;
$total_products = fetchScalar($conn, "SELECT COUNT(*) FROM products");
$low_stock = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity <= min_quantity AND quantity > 0");
$out_of_stock = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity = 0");
$total_customers = fetchScalar($conn, "SELECT COUNT(*) FROM customers");
$total_suppliers = fetchScalar($conn, "SELECT COUNT(*) FROM suppliers");
$recent_workers = $conn->query("SELECT id, name, job_title, department, hire_date FROM workers ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>لوحة التحكم</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --primary:#2c3e50; --secondary:#3498db; --success:#27ae60; --warning:#f39c12; --accent:#e74c3c; --light:#ecf0f1; --text:#34495e; --white:#fff; }
    body { font-family: 'Cairo', sans-serif; background: #f8f9fa; color: var(--text); direction: rtl; }
    .navbar { background: var(--primary); color: var(--white); padding: 15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);} 
    .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:bold; }
    .nav-links { display:flex; gap: 20px; align-items:center; }
    .nav-link { color: var(--light); text-decoration:none; padding:8px 16px; border-radius:6px; font-weight:600; display:flex; align-items:center; gap:8px; }
    .nav-link:hover, .nav-link.active { background: var(--secondary); color: var(--white); }
    .container { max-width: 1400px; margin: 0 auto; padding: 30px; }
    .page-header { background: var(--white); border-radius: 15px; padding: 30px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-right: 5px solid var(--secondary); }
    .page-title { color: var(--primary); font-size: 2rem; margin-bottom: 10px; display:flex; align-items:center; gap:15px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom:30px; }
    .card { background: var(--white); border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); padding:25px; border:1px solid rgba(0,0,0,0.05);} 
    .card-header { display:flex; align-items:center; gap:15px; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #e9ecef; }
    .card-icon { width: 60px; height: 60px; background: linear-gradient(135deg, var(--secondary), var(--primary)); color: var(--white); border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
    .card-title { font-size: 1.2rem; color: var(--primary); margin:0; }
    .card-stats { font-size: 2.2rem; font-weight:bold; color: var(--secondary); text-align:center; margin: 15px 0; }
    .status-badge { padding:6px 12px; border-radius: 20px; font-size:12px; font-weight:600; text-align:center; }
    .status-active { background:#d4edda; color:#155724; }
    .status-pending { background:#fff3cd; color:#856404; }
    .status-warning { background:#f8d7da; color:#721c24; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; justify-content:center; }
    .btn-primary { background: var(--secondary); color:var(--white);} .btn-success{background:var(--success); color:var(--white);} .btn-warning{background:var(--warning); color:var(--white);} .btn-info{background:#17a2b8; color:var(--white);} .btn-danger{background:var(--accent); color:var(--white);} .btn-outline{ background:transparent; border:2px solid var(--secondary); color:var(--secondary);} .btn-outline:hover{background:var(--secondary); color:var(--white);} 
    .table-container { background: var(--white); border-radius:15px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.1); }
    .table { width:100%; border-collapse: collapse; }
    .table th { background: var(--primary); color:var(--white); padding:15px; text-align:right; font-weight:600; }
    .table td { padding:15px; border-bottom:1px solid #e9ecef; text-align:right; }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand"><i class="fas fa-tachometer-alt"></i><span>لوحة التحكم</span></div>
    <div class="nav-links">
      <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> الرئيسية</a>
      <a href="inventory.php" class="nav-link"><i class="fas fa-warehouse"></i> المخازن</a>
      <a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> التقارير</a>
      <div class="user-info" style="background: rgba(255,255,255,0.1); padding:8px 15px; border-radius:20px; color:#ecf0f1; font-weight:500;">
        <i class="fas fa-user"></i> <?php echo esc($user_info['username']); ?>
      </div>
      <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </nav>
  <div class="container">
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> لوحة التحكم الرئيسية</h1>
      <p class="page-subtitle">مرحباً بك <?php echo esc($user_info['username']); ?> - آخر تحديث: <?php echo date('Y-m-d H:i'); ?></p>
    </div>
    <div class="grid">
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-users"></i></div><h3 class="card-title">إجمالي العمال</h3></div><div class="card-stats"><?php echo $total_workers; ?></div><a href="workers.php" class="btn btn-primary" style="width:100%"><i class="fas fa-list"></i> إدارة العمال</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-user-check"></i></div><h3 class="card-title">حضور اليوم</h3></div><div class="card-stats"><?php echo $workers_present_today; ?></div><a href="attendance.php" class="btn btn-success" style="width:100%"><i class="fas fa-calendar-check"></i> تسجيل الحضور</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-money-bill-wave"></i></div><h3 class="card-title">رواتب معلقة</h3></div><div class="card-stats"><?php echo $pending_salaries; ?></div><a href="salaries.php" class="btn btn-warning" style="width:100%"><i class="fas fa-cog"></i> معالجة الرواتب</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-chart-line"></i></div><h3 class="card-title">إيرادات الشهر</h3></div><div class="card-stats"><?php echo number_format($month_revenue,2); ?> <small>ر.س</small></div><a href="revenues.php" class="btn btn-success" style="width:100%"><i class="fas fa-eye"></i> عرض التفاصيل</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-receipt"></i></div><h3 class="card-title">مصروفات الشهر</h3></div><div class="card-stats"><?php echo number_format($month_expenses,2); ?> <small>ر.س</small></div><a href="expenses.php" class="btn btn-danger" style="width:100%"><i class="fas fa-eye"></i> عرض التفاصيل</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-chart-pie"></i></div><h3 class="card-title">صافي الربح</h3></div><div class="card-stats"><?php echo number_format($net_profit,2); ?> <small>ر.س</small></div><a href="reports.php" class="btn btn-info" style="width:100%"><i class="fas fa-chart-bar"></i> التقارير المالية</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-warehouse"></i></div><h3 class="card-title">المنتجات</h3></div><div class="card-stats"><?php echo $total_products; ?></div><div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:10px 0;"><span class="status-badge status-warning">ناقص: <?php echo $low_stock; ?></span><span class="status-badge status-pending">منتهي: <?php echo $out_of_stock; ?></span></div><a href="inventory.php" class="btn btn-primary" style="width:100%"><i class="fas fa-boxes"></i> إدارة المخازن</a></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-handshake"></i></div><h3 class="card-title">العملاء والموردين</h3></div><div style="text-align:center; margin:15px 0; display:grid; grid-template-columns:1fr 1fr; gap:15px;">
        <div><div style="font-size:1.8rem; font-weight:bold; color: var(--success);"><?php echo $total_customers; ?></div><div style="font-size:0.9rem; color: var(--text);">عميل</div></div>
        <div><div style="font-size:1.8rem; font-weight:bold; color: #17a2b8; "><?php echo $total_suppliers; ?></div><div style="font-size:0.9rem; color: var(--text);">مورد</div></div>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;"><a href="customers.php" class="btn btn-success"><i class="fas fa-user-tie"></i> العملاء</a><a href="suppliers.php" class="btn btn-info"><i class="fas fa-truck"></i> الموردين</a></div></div>
    </div>

    <div class="card">
      <div class="card-header"><i class="fas fa-user-clock" style="color: var(--secondary);"></i><h3 class="card-title">أحدث العمال المسجلين</h3></div>
      <div class="table-container">
        <table class="table">
          <thead><tr><th>الاسم</th><th>الوظيفة</th><th>القسم</th><th>تاريخ التعيين</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
          <tbody>
          <?php while($worker = $recent_workers && $recent_workers->fetch_assoc()) { ?>
            <tr>
              <td><i class="fas fa-user"></i> <?php echo esc($worker['name']); ?></td>
              <td><?php echo esc($worker['job_title'] ?? 'غير محدد'); ?></td>
              <td><?php echo esc($worker['department'] ?? 'غير محدد'); ?></td>
              <td><?php echo esc($worker['hire_date'] ?? 'غير محدد'); ?></td>
              <td><span class="status-badge status-active">نشط</span></td>
              <td><a href="manage_worker.php?id=<?php echo (int)$worker['id']; ?>" class="btn btn-outline" style="padding:5px 10px; font-size:12px;"><i class="fas fa-cog"></i> إدارة</a></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <div style="text-align:center; margin-top:15px;"><a href="workers.php" class="btn btn-primary"><i class="fas fa-eye"></i> عرض جميع العمال</a></div>
    </div>
  </div>
</body>
</html>
