<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
$user_info = getUserInfo($conn, (int)$_SESSION['user_id']);

$revenue_data = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as total FROM revenues WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym")->fetch_all(MYSQLI_ASSOC);
$expenses_data = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as total FROM expenses WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym")->fetch_all(MYSQLI_ASSOC);
$products_by_category = $conn->query("SELECT COALESCE(c.name,'غير مصنف') AS category_name, COUNT(*) AS cnt FROM products p LEFT JOIN categories c ON p.category_id=c.id GROUP BY category_name ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$stock_status_data = $conn->query("SELECT 
    SUM(CASE WHEN quantity > min_quantity THEN 1 ELSE 0 END) as normal,
    SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low,
    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out
    FROM products")->fetch_assoc();

$months = [];$revenues=[];$expenses=[];
foreach ($revenue_data as $d){ $months[] = $d['ym']; $revenues[] = (float)$d['total']; }
foreach ($expenses_data as $d){ $expenses[] = (float)$d['total']; }
while(count($months) < 6) { array_unshift($months, date('Y-m', strtotime('-'.(6-count($months)).' months'))); array_unshift($revenues, 0); array_unshift($expenses, 0);} 
$cat_labels = []; $cat_counts = []; foreach($products_by_category as $row){ $cat_labels[] = $row['category_name']; $cat_counts[] = (int)$row['cnt']; }
$month_revenue = array_sum($revenues); $month_expenses = array_sum($expenses); $net_profit = $month_revenue - $month_expenses;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>التقارير والتحليلات</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box} :root{--primary:#2c3e50;--secondary:#3498db;--text:#34495e;--white:#fff}
body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:var(--text);direction:rtl}
.navbar{background:var(--primary);color:#fff;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.container{max-width:1400px;margin:0 auto;padding:30px}
.page-header{background:#fff;border-radius:15px;padding:30px;margin-bottom:30px;box-shadow:0 5px 15px rgba(0,0,0,.1);border-right:5px solid var(--secondary)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:25px;margin-bottom:30px}
.card{background:#fff;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:25px;border:1px solid rgba(0,0,0,.05)}
.charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:25px;margin-bottom:30px}
.chart-container{position:relative;height:300px;background:#fff;border-radius:15px;padding:20px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
.chart-title{color:var(--primary);margin-bottom:15px;text-align:center;font-size:1.1rem;font-weight:600}
</style>
</head>
<body>
<nav class="navbar">
  <div class="nav-brand"><i class="fas fa-chart-bar"></i><span>التقارير</span></div>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link" style="color:#ecf0f1;text-decoration:none;">الرئيسية</a>
    <a href="inventory.php" class="nav-link" style="color:#ecf0f1;text-decoration:none;">المخازن</a>
  </div>
</nav>
<div class="container">
  <div class="page-header"><h1 class="page-title"><i class="fas fa-chart-bar"></i> لوحة التقارير</h1><p>نظرة شاملة على أداء النظام</p></div>
  <div class="grid">
    <div class="card"><div style="display:flex;align-items:center;gap:15px;margin-bottom:10px"><i class="fas fa-boxes"></i><h3 style="margin:0">إجمالي المنتجات</h3></div><div style="font-size:2.2rem;color:#3498db;font-weight:bold;text-align:center;"><?php echo fetchScalar($conn, 'SELECT COUNT(*) FROM products'); ?></div></div>
    <div class="card"><div style="display:flex;align-items:center;gap:15px;margin-bottom:10px"><i class="fas fa-money-bill-wave"></i><h3 style="margin:0">إيرادات الفترة</h3></div><div style="font-size:2.2rem;color:#27ae60;font-weight:bold;text-align:center;"><?php echo number_format($month_revenue,2); ?> <small>ر.س</small></div></div>
    <div class="card"><div style="display:flex;align-items:center;gap:15px;margin-bottom:10px"><i class="fas fa-receipt"></i><h3 style="margin:0">مصروفات الفترة</h3></div><div style="font-size:2.2rem;color:#e74c3c;font-weight:bold;text-align:center;"><?php echo number_format($month_expenses,2); ?> <small>ر.س</small></div></div>
    <div class="card"><div style="display:flex;align-items:center;gap:15px;margin-bottom:10px"><i class="fas fa-chart-line"></i><h3 style="margin:0">صافي الربح</h3></div><div style="font-size:2.2rem;color:#3498db;font-weight:bold;text-align:center;"><?php echo number_format($net_profit,2); ?> <small>ر.س</small></div></div>
  </div>
  <div class="charts-grid">
    <div class="chart-container"><div class="chart-title">📈 الإيرادات والمصروفات (آخر 6 أشهر)</div><canvas id="revenueExpenseChart"></canvas></div>
    <div class="chart-container"><div class="chart-title">🏷️ توزيع المنتجات حسب التصنيف</div><canvas id="productsByCategoryChart"></canvas></div>
    <div class="chart-container"><div class="chart-title">📦 حالة المخزون العام</div><canvas id="stockStatusChart"></canvas></div>
  </div>
</div>
<script>
const months = <?php echo json_encode($months); ?>;
const revenues = <?php echo json_encode($revenues); ?>;
const expenses = <?php echo json_encode($expenses); ?>;
const catLabels = <?php echo json_encode($cat_labels); ?>;
const catCounts = <?php echo json_encode($cat_counts); ?>;
const stock = { normal: <?php echo (int)$stock_status_data['normal']; ?>, low: <?php echo (int)$stock_status_data['low']; ?>, out: <?php echo (int)$stock_status_data['out']; ?> };

new Chart(document.getElementById('revenueExpenseChart').getContext('2d'), { type:'line', data:{ labels: months, datasets:[ {label:'الإيرادات', data: revenues, borderColor:'#27ae60', backgroundColor:'rgba(39,174,96,0.1)', tension:0.4, fill:true}, {label:'المصروفات', data: expenses, borderColor:'#e74c3c', backgroundColor:'rgba(231,76,60,0.1)', tension:0.4, fill:true} ] }, options:{ responsive:true, maintainAspectRatio:false } });
new Chart(document.getElementById('productsByCategoryChart').getContext('2d'), { type:'doughnut', data:{ labels: catLabels, datasets:[{ data: catCounts, backgroundColor:['#3498db','#2ecc71','#e74c3c','#f39c12','#9b59b6','#1abc9c','#34495e','#d35400'], borderWidth:2, borderColor:'#fff'}] }, options:{ responsive:true, maintainAspectRatio:false } });
new Chart(document.getElementById('stockStatusChart').getContext('2d'), { type:'bar', data:{ labels:['طبيعي','منخفض','منتهي'], datasets:[{ label:'عدد المنتجات', data:[stock.normal, stock.low, stock.out], backgroundColor:['rgba(39,174,96,0.8)','rgba(243,156,18,0.8)','rgba(231,76,60,0.8)'], borderColor:['#27ae60','#f39c12','#e74c3c'], borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } } });
</script>
</body>
</html>
