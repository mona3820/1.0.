<?php
session_start();
require_once 'config/db.php';

// التحقق من تسجيل الدخول
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

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

// العملاء والموردين
$total_customers = $conn->query("SELECT COUNT(*) as total FROM customers")->fetch_assoc()['total'];
$total_suppliers = $conn->query("SELECT COUNT(*) as total FROM suppliers")->fetch_assoc()['total'];

// أحدث العمال
$recent_workers = $conn->query("SELECT * FROM workers ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - النظام المتكامل</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
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

        /* === الشريط العلوي === */
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

        .nav-brand i {
            color: var(--secondary);
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

        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 20px;
            color: var(--light);
            font-weight: 500;
        }

        /* === الحاوية الرئيسية === */
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

        .page-subtitle {
            color: var(--text);
            font-size: 1.1rem;
            opacity: 0.8;
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

        .card-description {
            text-align: center;
            color: var(--text);
            opacity: 0.8;
            margin-bottom: 15px;
        }

        /* === الأزرار === */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--secondary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-info {
            background: var(--info);
            color: var(--white);
        }

        .btn-danger {
            background: var(--accent);
            color: var(--white);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: var(--white);
        }

        /* === الجداول === */
        .table-container {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--primary);
            color: var(--white);
            padding: 15px;
            text-align: right;
            font-weight: 600;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: right;
        }

        .table tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* === النظام اللوني للحالات === */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-warning { background: #f8d7da; color: #721c24; }

        /* === الإجراءات السريعة === */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
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
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }
            
            .page-title {
                font-size: 1.6rem;
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
            <a href="workers.php" class="nav-link">
                <i class="fas fa-users"></i> العمال
            </a>
            <a href="revenues.php" class="nav-link">
                <i class="fas fa-money-bill-wave"></i> الإيرادات
            </a>
            <a href="expenses.php" class="nav-link">
                <i class="fas fa-receipt"></i> المصروفات
            </a>
            <a href="inventory.php" class="nav-link">
                <i class="fas fa-warehouse"></i> المخازن
            </a>
            <div class="user-info">
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
                لوحة التحكم الرئيسية
            </h1>
            <p class="page-subtitle">مرحباً بك <?php echo $user_info['username']; ?> - آخر تحديث: <?php echo date('Y-m-d H:i'); ?></p>
        </div>

        <!-- الإحصائيات السريعة -->
        <div class="grid">
            <!-- العمال -->
            <div class="card fade-in-up" style="animation-delay: 0.1s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="card-title">إجمالي العمال</h3>
                </div>
                <div class="card-stats"><?php echo $total_workers; ?></div>
                <p class="card-description">عامل مسجل في النظام</p>
                <a href="workers.php" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-list"></i> إدارة العمال
                </a>
            </div>

            <!-- الحضور -->
            <div class="card fade-in-up" style="animation-delay: 0.2s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="card-title">حضور اليوم</h3>
                </div>
                <div class="card-stats"><?php echo $workers_present_today; ?></div>
                <p class="card-description">عامل حضر اليوم</p>
                <a href="attendance.php" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-calendar-check"></i> تسجيل الحضور
                </a>
            </div>

            <!-- الرواتب -->
            <div class="card fade-in-up" style="animation-delay: 0.3s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="card-title">رواتب معلقة</h3>
                </div>
                <div class="card-stats"><?php echo $pending_salaries; ?></div>
                <p class="card-description">راتب يحتاج للمعالجة</p>
                <a href="salaries.php" class="btn btn-warning" style="width: 100%;">
                    <i class="fas fa-cog"></i> معالجة الرواتب
                </a>
            </div>

            <!-- الإيرادات -->
            <div class="card fade-in-up" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="card-title">إيرادات الشهر</h3>
                </div>
                <div class="card-stats"><?php echo number_format($month_revenue, 2); ?> <small>ر.س</small></div>
                <p class="card-description">إجمالي الإيرادات</p>
                <a href="revenues.php" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-eye"></i> عرض التفاصيل
                </a>
            </div>

            <!-- المصروفات -->
            <div class="card fade-in-up" style="animation-delay: 0.5s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 class="card-title">مصروفات الشهر</h3>
                </div>
                <div class="card-stats"><?php echo number_format($month_expenses, 2); ?> <small>ر.س</small></div>
                <p class="card-description">إجمالي المصروفات</p>
                <a href="expenses.php" class="btn btn-danger" style="width: 100%;">
                    <i class="fas fa-eye"></i> عرض التفاصيل
                </a>
            </div>

            <!-- الأرباح -->
            <div class="card fade-in-up" style="animation-delay: 0.6s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3 class="card-title">صافي الربح</h3>
                </div>
                <div class="card-stats"><?php echo number_format($net_profit, 2); ?> <small>ر.س</small></div>
                <p class="card-description">أرباح الشهر الحالي</p>
                <a href="reports.php" class="btn btn-info" style="width: 100%;">
                    <i class="fas fa-chart-bar"></i> التقارير المالية
                </a>
            </div>

            <!-- المخازن -->
            <div class="card fade-in-up" style="animation-delay: 0.7s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3 class="card-title">المنتجات</h3>
                </div>
                <div class="card-stats"><?php echo $total_products; ?></div>
                <p class="card-description">منتج في المخازن</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0;">
                    <span class="status-badge status-warning">ناقص: <?php echo $low_stock; ?></span>
                    <span class="status-badge status-pending">منتهي: <?php echo $out_of_stock; ?></span>
                </div>
                <a href="inventory.php" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-boxes"></i> إدارة المخازن
                </a>
            </div>

            <!-- العملاء والموردين -->
            <div class="card fade-in-up" style="animation-delay: 0.8s;">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="card-title">العملاء والموردين</h3>
                </div>
                <div style="text-align: center; margin: 15px 0;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: var(--success);">
                                <?php echo $total_customers; ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text);">عميل</div>
                        </div>
                        <div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: var(--info);">
                                <?php echo $total_suppliers; ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text);">مورد</div>
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="customers.php" class="btn btn-success">
                        <i class="fas fa-user-tie"></i> العملاء
                    </a>
                    <a href="suppliers.php" class="btn btn-info">
                        <i class="fas fa-truck"></i> الموردين
                    </a>
                </div>
            </div>
        </div>

        <!-- الإجراءات السريعة -->
        <div class="card fade-in-up" style="animation-delay: 0.9s;">
            <div class="card-header">
                <i class="fas fa-bolt" style="color: var(--warning); font-size: 1.5rem;"></i>
                <h3 class="card-title">إجراءات سريعة</h3>
            </div>
            <div class="quick-actions">
                <a href="add_worker.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> إضافة عامل
                </a>
                <a href="add_revenue.php" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> إضافة إيراد
                </a>
                <a href="add_expense.php" class="btn btn-danger">
                    <i class="fas fa-plus-circle"></i> إضافة مصروف
                </a>
                <a href="add_product.php" class="btn btn-info">
                    <i class="fas fa-box"></i> إضافة منتج
                </a>
                <a href="add_attendance.php" class="btn btn-warning">
                    <i class="fas fa-user-check"></i> تسجيل حضور
                </a>
                <a href="reports.php" class="btn btn-outline">
                    <i class="fas fa-chart-pie"></i> التقارير
                </a>
            </div>
        </div>

        <!-- أحدث العمال -->
        <div class="card fade-in-up" style="animation-delay: 1s;">
            <div class="card-header">
                <i class="fas fa-user-clock" style="color: var(--secondary); font-size: 1.5rem;"></i>
                <h3 class="card-title">أحدث العمال المسجلين</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الوظيفة</th>
                            <th>القسم</th>
                            <th>تاريخ التعيين</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($worker = $recent_workers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($worker['name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($worker['job_title'] ?? 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($worker['department'] ?? 'غير محدد'); ?></td>
                            <td><?php echo $worker['hire_date'] ?? 'غير محدد'; ?></td>
                            <td>
                                <span class="status-badge status-active">نشط</span>
                            </td>
                            <td>
                                <a href="manage_worker.php?id=<?php echo $worker['id']; ?>" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;">
                                    <i class="fas fa-cog"></i> إدارة
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="workers.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> عرض جميع العمال
                </a>
            </div>
        </div>

        <!-- معلومات النظام -->
        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card fade-in-up">
                <div class="card-header">
                    <i class="fas fa-info-circle" style="color: var(--info); font-size: 1.5rem;"></i>
                    <h3 class="card-title">معلومات النظام</h3>
                </div>
                <div style="padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>إصدار النظام:</span>
                        <strong>v2.0.0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>آخر تحديث:</span>
                        <strong>2024</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>حالة الخادم:</span>
                        <span class="status-badge status-active">شغال</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>المستخدم الحالي:</span>
                        <strong><?php echo $user_info['username']; ?></strong>
                    </div>
                </div>
            </div>

            <div class="card fade-in-up">
                <div class="card-header">
                    <i class="fas fa-headset" style="color: var(--success); font-size: 1.5rem;"></i>
                    <h3 class="card-title">الدعم الفني</h3>
                </div>
                <div style="padding: 20px;">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-phone"></i> 
                        <strong style="margin-right: 10px;">+966 50 000 0000</strong>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-envelope"></i> 
                        <strong style="margin-right: 10px;">support@system.com</strong>
                    </div>
                    <div>
                        <i class="fas fa-clock"></i> 
                        <strong style="margin-right: 10px;">متاح 24/7</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تأثيرات تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            // تأثيرات للكروت
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // تحديث الوقت
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleString('ar-SA', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                const timeElement = document.querySelector('.page-subtitle');
                if(timeElement) {
                    const baseText = timeElement.textContent.split(' - ')[0];
                    timeElement.textContent = baseText + ' - آخر تحديث: ' + timeString;
                }
            }
            
            // تحديث الوقت كل ثانية
            setInterval(updateTime, 1000);
        });
    </script>
</body>
</html>