<?php
session_start();
require_once 'config/db.php';
checkAuth();

$user_info = getUserInfo($conn, $_SESSION['user_id']);

// إحصائيات العمال
$total_workers = $conn->query("SELECT COUNT(*) as total FROM workers")->fetch_assoc()['total'];
$workers_present_today = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetch_assoc()['total'];
$pending_salaries = $conn->query("SELECT COUNT(*) as total FROM salaries WHERE payment_status = 'pending'")->fetch_assoc()['total'];

// إحصائيات مالية
$month_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM revenues WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];
$month_expenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];
$net_profit = $month_revenue - $month_expenses;

// إحصائيات المخازن
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE quantity <= min_quantity AND quantity > 0")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE quantity = 0")->fetch_assoc()['total'];

// بيانات للرسوم البيانية
// إيرادات آخر 6 أشهر
$revenue_data = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           SUM(amount) as total 
    FROM revenues 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

// مصروفات آخر 6 أشهر
$expenses_data = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           SUM(amount) as total 
    FROM expenses 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

// توزيع المنتجات حسب التصنيف
$products_by_category = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM products 
    WHERE category IS NOT NULL 
    GROUP BY category
")->fetch_all(MYSQLI_ASSOC);

// حالة المخزون
$stock_status_data = $conn->query("
    SELECT 
        SUM(CASE WHEN quantity > min_quantity THEN 1 ELSE 0 END) as normal,
        SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out
    FROM products
")->fetch_assoc();

// تحضير بيانات الرسوم البيانية
$months = [];
$revenues = [];
$expenses = [];

foreach($revenue_data as $data) {
    $months[] = date('M Y', strtotime($data['month']));
    $revenues[] = $data['total'];
}

foreach($expenses_data as $data) {
    $expenses[] = $data['total'];
}

// ملء البيانات المفقودة لضمان تطابق المصفوفات
while(count($months) < 6) {
    array_unshift($months, date('M Y', strtotime('-' . (6 - count($months)) . ' months')));
    array_unshift($revenues, 0);
    array_unshift($expenses, 0);
}

$categories = [];
$category_counts = [];
foreach($products_by_category as $cat) {
    $categories[] = $cat['category'];
    $category_counts[] = $cat['count'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - النظام المتكامل</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --info: #17a2b8;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --white: #ffffff;
            --text: #34495e;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: #f8f9fa;
            color: var(--text);
            direction: rtl;
            line-height: 1.6;
        }

        .navbar {
            background: var(--primary);
            color: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            color: var(--light);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--secondary);
            color: var(--white);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            background: var(--white);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-right: 5px solid var(--secondary);
        }

        .page-title {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* === نظام الكروت === */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .card-title {
            font-size: 1.2rem;
            color: var(--primary);
            margin: 0;
        }

        .card-stats {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--secondary);
            text-align: center;
            margin: 15px 0;
        }

        /* === شبكة الرسوم البيانية === */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .chart-title {
            color: var(--primary);
            margin-bottom: 15px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* === متجاوب === */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .grid, .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }
            
            .chart-container {
                height: 250px;
            }
        }

        /* === أنيميشن === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- الشريط العلوي -->
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-tachometer-alt"></i>
            <span>لوحة التحكم</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-home"></i> الرئيسية
            </a>
            <a href="inventory.php" class="nav-link">
                <i class="fas fa-warehouse"></i> المخازن
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i> التقارير
            </a>
            <div class="user-info" style="color: var(--light);">
                <i class="fas fa-user"></i>
                <?php echo $user_info['username']; ?>
            </div>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> خروج
            </a>
        </div>
    </nav>

    <!-- المحتوى الرئيسي -->
    <div class="container">
        <!-- رأس الصفحة -->
        <div class="page-header fade-in-up">
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i>
                لوحة التحكم - التحليلات الشاملة
            </h1>
            <p>نظرة شاملة على أداء النظام مع رسوم بيانية تفاعلية</p>
        </div>

        <!-- الإحصائيات السريعة -->
        <div class="grid">
            <div class="card fade-in-up">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="card-title">إجمالي المنتجات</h3>
                </div>
                <div class="card-stats"><?php echo $total_products; ?></div>
            </div>

            <div class="card fade-in-up">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="card-title">إيرادات الشهر</h3>
                </div>
                <div class="card-stats"><?php echo number_format($month_revenue, 2); ?> <small>ر.س</small></div>
            </div>

            <div class="card fade-in-up">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 class="card-title">مصروفات الشهر</h3>
                </div>
                <div class="card-stats"><?php echo number_format($month_expenses, 2); ?> <small>ر.س</small></div>
            </div>

            <div class="card fade-in-up">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="card-title">صافي الربح</h3>
                </div>
                <div class="card-stats"><?php echo number_format($net_profit, 2); ?> <small>ر.س</small></div>
            </div>
        </div>

        <!-- الرسوم البيانية الرئيسية -->
        <div class="charts-grid">
            <!-- رسم الإيرادات والمصروفات -->
            <div class="chart-container fade-in-up">
                <div class="chart-title">📈 الإيرادات والمصروفات (آخر 6 أشهر)</div>
                <canvas id="revenueExpenseChart"></canvas>
            </div>

            <!-- رسم توزيع المنتجات -->
            <div class="chart-container fade-in-up">
                <div class="chart-title">🏷️ توزيع المنتجات حسب التصنيف</div>
                <canvas id="productsByCategoryChart"></canvas>
            </div>

            <!-- رسم حالة المخزون -->
            <div class="chart-container fade-in-up">
                <div class="chart-title">📦 حالة المخزون العام</div>
                <canvas id="stockStatusChart"></canvas>
            </div>

            <!-- رسم الأداء الشهري -->
            <div class="chart-container fade-in-up">
                <div class="chart-title">📊 أداء المخزون الشهري</div>
                <canvas id="monthlyPerformanceChart"></canvas>
            </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card fade-in-up">
                <div class="card-header">
                    <i class="fas fa-info-circle" style="color: var(--info);"></i>
                    <h3 class="card-title">معلومات سريعة</h3>
                </div>
                <div style="padding: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>المنتجات المنخفضة:</span>
                        <strong style="color: var(--warning);"><?php echo $low_stock; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>المنتجات المنتهية:</span>
                        <strong style="color: var(--accent);"><?php echo $out_of_stock; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>إجمالي العمال:</span>
                        <strong style="color: var(--secondary);"><?php echo $total_workers; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>الحضور اليوم:</span>
                        <strong style="color: var(--success);"><?php echo $workers_present_today; ?></strong>
                    </div>
                </div>
            </div>

            <div class="card fade-in-up">
                <div class="card-header">
                    <i class="fas fa-chart-pie" style="color: var(--success);"></i>
                    <h3 class="card-title">نسب الأداء</h3>
                </div>
                <div style="padding: 15px;">
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>معدل الربحية:</span>
                            <strong><?php echo $month_revenue > 0 ? number_format(($net_profit / $month_revenue) * 100, 1) : 0; ?>%</strong>
                        </div>
                        <div style="height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; background: var(--success); width: <?php echo $month_revenue > 0 ? ($net_profit / $month_revenue) * 100 : 0; ?>%; border-radius: 4px;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>نسبة المخزون الصحي:</span>
                            <strong><?php echo $total_products > 0 ? number_format((($total_products - $low_stock - $out_of_stock) / $total_products) * 100, 1) : 0; ?>%</strong>
                        </div>
                        <div style="height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; background: var(--info); width: <?php echo $total_products > 0 ? (($total_products - $low_stock - $out_of_stock) / $total_products) * 100 : 0; ?>%; border-radius: 4px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تهيئة الرسوم البيانية
        document.addEventListener('DOMContentLoaded', function() {
            // رسم الإيرادات والمصروفات
            const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
            new Chart(revenueExpenseCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: <?php echo json_encode($revenues); ?>,
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'المصروفات',
                            data: <?php echo json_encode($expenses); ?>,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            rtl: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });

            // رسم توزيع المنتجات حسب التصنيف
            const productsByCategoryCtx = document.getElementById('productsByCategoryChart').getContext('2d');
            new Chart(productsByCategoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($categories); ?>,
                    datasets: [{
                        data: <?php echo json_encode($category_counts); ?>,
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', 
                            '#9b59b6', '#1abc9c', '#34495e', '#d35400'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'left',
                            rtl: true
                        }
                    }
                }
            });

            // رسم حالة المخزون
            const stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
            new Chart(stockStatusCtx, {
                type: 'bar',
                data: {
                    labels: ['طبيعي', 'منخفض', 'منتهي'],
                    datasets: [{
                        label: 'عدد المنتجات',
                        data: [
                            <?php echo $stock_status_data['normal']; ?>,
                            <?php echo $stock_status_data['low']; ?>,
                            <?php echo $stock_status_data['out']; ?>
                        ],
                        backgroundColor: [
                            'rgba(39, 174, 96, 0.8)',
                            'rgba(243, 156, 18, 0.8)',
                            'rgba(231, 76, 60, 0.8)'
                        ],
                        borderColor: [
                            '#27ae60',
                            '#f39c12',
                            '#e74c3c'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // رسم الأداء الشهري (مثال)
            const monthlyPerformanceCtx = document.getElementById('monthlyPerformanceChart').getContext('2d');
            new Chart(monthlyPerformanceCtx, {
                type: 'radar',
                data: {
                    labels: ['المخزون', 'المبيعات', 'الربحية', 'العملاء', 'الموردين'],
                    datasets: [{
                        label: 'أداء الشهر الحالي',
                        data: [85, 72, 68, 90, 75],
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: '#3498db',
                        pointBackgroundColor: '#3498db',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0,
                            suggestedMax: 100
                        }
                    }
                }
            });

            // تحديث الرسوم البيانية تلقائياً كل 30 ثانية
            setInterval(() => {
                // هنا يمكن إضافة AJAX لجلب بيانات محدثة
                console.log('تحديث الرسوم البيانية...');
            }, 30000);
        });

        // تأثيرات تفاعلية للكروت
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>