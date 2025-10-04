<?php
session_start();
require_once __DIR__ . '/config/db.php';
checkAuth();

$user = getUserInfo($conn, (int)$_SESSION['user_id']);

$total_workers = fetchScalar($conn, "SELECT COUNT(*) FROM workers");
$workers_present_today = fetchScalar($conn, "SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
$pending_salaries = fetchScalar($conn, "SELECT COUNT(*) FROM salaries WHERE payment_status = 'pending'");

$month_revenue = fetchScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM revenues WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
$month_expenses = fetchScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
$net_profit = $month_revenue - $month_expenses;

$total_products = fetchScalar($conn, "SELECT COUNT(*) FROM products");
$low_stock = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity <= min_quantity AND quantity > 0");
$out_of_stock = fetchScalar($conn, "SELECT COUNT(*) FROM products WHERE quantity = 0");

// Build last 6 months arrays
$monthKeys = [];
$monthLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-{$i} months");
    $monthKeys[] = date('Y-m', $ts);
    $monthLabels[] = date('M Y', $ts);
}
$startFrom = date('Y-m-01', strtotime('-5 months'));

// Revenues
$revMap = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(amount) total FROM revenues WHERE created_at >= '$startFrom' GROUP BY ym");
if ($res) { while ($row = $res->fetch_assoc()) { $revMap[$row['ym']] = (float)$row['total']; } }
$expMap = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(amount) total FROM expenses WHERE created_at >= '$startFrom' GROUP BY ym");
if ($res) { while ($row = $res->fetch_assoc()) { $expMap[$row['ym']] = (float)$row['total']; } }
$revenues = [];
$expenses = [];
foreach ($monthKeys as $k) {
    $revenues[] = $revMap[$k] ?? 0;
    $expenses[] = $expMap[$k] ?? 0;
}

// Products by category
$categories = [];
$category_counts = [];
$res = $conn->query("SELECT COALESCE(c.name,'غير مصنف') category_name, COUNT(*) cnt FROM products p LEFT JOIN categories c ON p.category_id = c.id GROUP BY category_name ORDER BY category_name");
if ($res) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row['category_name']; $category_counts[] = (int)$row['cnt']; }
}

// Stock status
$stockRow = $conn->query("SELECT 
  SUM(CASE WHEN quantity > min_quantity THEN 1 ELSE 0 END) normal,
  SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) low,
  SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) outc
  FROM products")->fetch_assoc();
$stock_status_data = [
  'normal' => (int)($stockRow['normal'] ?? 0),
  'low' => (int)($stockRow['low'] ?? 0),
  'out' => (int)($stockRow['outc'] ?? 0),
];

// Recent workers
$recent_workers = $conn->query("SELECT id, name, job_title, department, hire_date FROM workers ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>لوحة التحكم - نظام المخازن</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root { --primary:#2c3e50; --secondary:#3498db; --accent:#e74c3c; --success:#27ae60; --warning:#f39c12; --info:#17a2b8; --light:#ecf0f1; --dark:#2c3e50; --white:#fff; --text:#34495e; }
    *{box-sizing:border-box; margin:0; padding:0}
    body{font-family:'Cairo',sans-serif; background:#f8f9fa; color:var(--text)}
    .navbar{background:var(--primary); color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .nav-brand{display:flex; gap:10px; align-items:center; font-weight:700}
    .nav-links{display:flex; gap:12px; align-items:center}
    .nav-link{color:#ecf0f1; text-decoration:none; padding:8px 14px; border-radius:8px; font-weight:600}
    .nav-link:hover, .nav-link.active{background:var(--secondary); color:#fff}
    .container{max-width:1400px; margin:0 auto; padding:28px}
    .grid{display:grid; grid-template-columns:repeat(auto-fit, minmax(260px,1fr)); gap:18px}
    .card{background:#fff; border-radius:14px; padding:22px; box-shadow:0 5px 15px rgba(0,0,0,.08)}
    .card-header{display:flex; align-items:center; gap:12px; border-bottom:1px solid #e9ecef; padding-bottom:12px; margin-bottom:14px}
    .card-icon{width:50px; height:50px; display:flex; align-items:center; justify-content:center; border-radius:12px; color:#fff; background: linear-gradient(135deg, var(--secondary), var(--primary));}
    .card-stats{font-size:2rem; color:var(--secondary); text-align:center; font-weight:800}
    .charts-grid{display:grid; grid-template-columns: repeat(auto-fit, minmax(420px,1fr)); gap:18px}
    .chart-container{background:#fff; border-radius:14px; padding:18px; box-shadow:0 5px 15px rgba(0,0,0,.08); height:320px}
    .table{width:100%; border-collapse:collapse}
    .table th{background:var(--primary); color:#fff; padding:12px; text-align:right}
    .table td{border-bottom:1px solid #e9ecef; padding:12px}
    @media(max-width:768px){.container{padding:14px} .charts-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand"><i class="fas fa-warehouse"></i><span>لوحة التحكم</span></div>
    <div class="nav-links">
      <a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
      <a class="nav-link" href="inventory.php"><i class="fas fa-boxes"></i> المخازن</a>
      <a class="nav-link" href="add_product.php"><i class="fas fa-plus"></i> إضافة منتج</a>
      <span class="nav-link" style="background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); border-radius:20px;"> <i class="fas fa-user"></i> <?php echo esc($user['username'] ?? ''); ?> </span>
      <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </nav>

  <div class="container">
    <div class="grid">
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-users"></i></div><h3>إجمالي العمال</h3></div><div class="card-stats"><?php echo (int)$total_workers; ?></div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-user-check"></i></div><h3>حضور اليوم</h3></div><div class="card-stats"><?php echo (int)$workers_present_today; ?></div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-money-bill-wave"></i></div><h3>رواتب معلقة</h3></div><div class="card-stats"><?php echo (int)$pending_salaries; ?></div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-box"></i></div><h3>المنتجات</h3></div><div class="card-stats"><?php echo (int)$total_products; ?></div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-chart-line"></i></div><h3>إيرادات الشهر</h3></div><div class="card-stats"><?php echo number_format($month_revenue,2); ?> ر.س</div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-receipt"></i></div><h3>مصروفات الشهر</h3></div><div class="card-stats"><?php echo number_format($month_expenses,2); ?> ر.س</div></div>
      <div class="card"><div class="card-header"><div class="card-icon"><i class="fas fa-chart-pie"></i></div><h3>صافي الربح</h3></div><div class="card-stats"><?php echo number_format($net_profit,2); ?> ر.س</div></div>
    </div>

    <div class="charts-grid" style="margin: 18px 0;">
      <div class="chart-container">
        <div style="text-align:center; font-weight:700; margin-bottom:8px;">الإيرادات والمصروفات (آخر 6 أشهر)</div>
        <canvas id="revenueExpenseChart"></canvas>
      </div>
      <div class="chart-container">
        <div style="text-align:center; font-weight:700; margin-bottom:8px;">توزيع المنتجات حسب التصنيف</div>
        <canvas id="productsByCategoryChart"></canvas>
      </div>
      <div class="chart-container">
        <div style="text-align:center; font-weight:700; margin-bottom:8px;">حالة المخزون</div>
        <canvas id="stockStatusChart"></canvas>
      </div>
      <div class="chart-container">
        <div style="text-align:center; font-weight:700; margin-bottom:8px;">أحدث العمال</div>
        <table class="table">
          <thead><tr><th>الاسم</th><th>الوظيفة</th><th>القسم</th><th>التعيين</th></tr></thead>
          <tbody>
            <?php if($recent_workers && $recent_workers->num_rows): while($w = $recent_workers->fetch_assoc()): ?>
              <tr>
                <td><?php echo esc($w['name']); ?></td>
                <td><?php echo esc($w['job_title'] ?? ''); ?></td>
                <td><?php echo esc($w['department'] ?? ''); ?></td>
                <td><?php echo esc($w['hire_date'] ?? ''); ?></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" style="text-align:center; color:#6c757d;">لا توجد بيانات</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    const labels = <?php echo json_encode($monthLabels, JSON_UNESCAPED_UNICODE); ?>;
    const revenues = <?php echo json_encode($revenues); ?>;
    const expenses = <?php echo json_encode($expenses); ?>;

    new Chart(document.getElementById('revenueExpenseChart').getContext('2d'), {
      type: 'line',
      data: { labels, datasets: [
        { label: 'الإيرادات', data: revenues, borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,.12)', tension: .35, fill: true },
        { label: 'المصروفات', data: expenses, borderColor: '#e74c3c', backgroundColor: 'rgba(231,76,60,.12)', tension: .35, fill: true }
      ]},
      options: { responsive: true, maintainAspectRatio:false }
    });

    new Chart(document.getElementById('productsByCategoryChart').getContext('2d'), {
      type: 'doughnut',
      data: { labels: <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>, datasets: [{ data: <?php echo json_encode($category_counts); ?>, borderWidth:2, borderColor:'#fff', backgroundColor:['#3498db','#2ecc71','#f39c12','#e74c3c','#9b59b6','#1abc9c','#34495e','#d35400'] }] },
      options: { responsive: true, maintainAspectRatio:false }
    });

    new Chart(document.getElementById('stockStatusChart').getContext('2d'), {
      type: 'bar',
      data: { labels: ['طبيعي','منخفض','منتهي'], datasets: [{ data: [<?php echo $stock_status_data['normal']; ?>, <?php echo $stock_status_data['low']; ?>, <?php echo $stock_status_data['out']; ?>], backgroundColor:['rgba(39,174,96,.85)','rgba(243,156,18,.85)','rgba(231,76,60,.85)'] }] },
      options: { responsive: true, maintainAspectRatio:false, scales: { y: { beginAtZero:true }}}
    });
  </script>
</body>
</html>
