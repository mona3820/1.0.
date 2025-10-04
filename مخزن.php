<?php
session_start();
require_once 'config/db.php';
checkAuth();

$user_info = getUserInfo($conn, $_SESSION['user_id']);

// معالجة البحث والتصفية
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

// بناء استعلام البحث
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "sss";
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if (!empty($supplier_filter)) {
    $where_conditions[] = "p.supplier_id = ?";
    $params[] = $supplier_filter;
    $param_types .= "i";
}

if (!empty($stock_filter)) {
    if ($stock_filter == 'low') {
        $where_conditions[] = "p.quantity <= p.min_quantity AND p.quantity > 0";
    } elseif ($stock_filter == 'out') {
        $where_conditions[] = "p.quantity = 0";
    } elseif ($stock_filter == 'normal') {
        $where_conditions[] = "p.quantity > p.min_quantity";
    }
}

$where_sql = implode(" AND ", $where_conditions);

// جلب المنتجات
$products_sql = "SELECT p.*, s.name as supplier_name, 
                        (p.quantity * p.cost_price) as total_value,
                        CASE 
                            WHEN p.quantity = 0 THEN 'out_of_stock'
                            WHEN p.quantity <= p.min_quantity THEN 'low_stock' 
                            ELSE 'normal' 
                        END as stock_status
                 FROM products p 
                 LEFT JOIN suppliers s ON p.supplier_id = s.id 
                 WHERE $where_sql 
                 ORDER BY p.quantity ASC, p.name ASC";

$stmt = $conn->prepare($products_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// إحصائيات المخازن
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$total_value = $conn->query("SELECT SUM(quantity * cost_price) as total FROM products")->fetch_assoc()['total'];
$low_stock_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE quantity <= min_quantity AND quantity > 0")->fetch_assoc()['total'];
$out_of_stock_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE quantity = 0")->fetch_assoc()['total'];

// جلب التصنيفات والموردين للفلتر
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

// معالجة إضافة حركة مخزون
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_movement'])) {
    $product_id = intval($_POST['product_id']);
    $movement_type = $_POST['movement_type'];
    $quantity = intval($_POST['quantity']);
    $notes = trim($_POST['notes']);
    
    if ($product_id > 0 && $quantity > 0) {
        // تحديث كمية المنتج
        if ($movement_type == 'in') {
            $conn->query("UPDATE products SET quantity = quantity + $quantity WHERE id = $product_id");
        } else {
            $conn->query("UPDATE products SET quantity = GREATEST(0, quantity - $quantity) WHERE id = $product_id");
        }
        
        // تسجيل الحركة
        $stmt = $conn->prepare("INSERT INTO inventory_movements (product_id, type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isisi", $product_id, $movement_type, $quantity, $notes, $_SESSION['user_id']);
        $stmt->execute();
        
        header("Location: inventory.php?success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخازن - النظام المتكامل</title>
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
            --purple: #9b59b6;
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
            max-width: 1800px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            background: var(--white);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .page-title {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* === إحصائيات المخازن === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total { border-top: 4px solid var(--secondary); }
        .stat-card.value { border-top: 4px solid var(--success); }
        .stat-card.low { border-top: 4px solid var(--warning); }
        .stat-card.out { border-top: 4px solid var(--accent); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: var(--white);
        }

        .icon-total { background: var(--secondary); }
        .icon-value { background: var(--success); }
        .icon-low { background: var(--warning); }
        .icon-out { background: var(--accent); }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .total .stat-value { color: var(--secondary); }
        .value .stat-value { color: var(--success); }
        .low .stat-value { color: var(--warning); }
        .out .stat-value { color: var(--accent); }

        /* === نموذج البحث === */
        .filter-card {
            background: var(--white);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }

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
        }

        .btn-primary { background: var(--secondary); color: var(--white); }
        .btn-success { background: var(--success); color: var(--white); }
        .btn-warning { background: var(--warning); color: var(--white); }
        .btn-danger { background: var(--accent); color: var(--white); }
        .btn-purple { background: var(--purple); color: var(--white); }
        .btn-outline { background: transparent; border: 2px solid var(--secondary); color: var(--secondary); }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        /* === أزرار الإجراءات === */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        /* === جدول المنتجات === */
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
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: right;
        }

        .table tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* === حالة المخزون === */
        .stock-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .status-normal { background: #d4edda; color: #155724; }
        .status-low { background: #fff3cd; color: #856404; }
        .status-out { background: #f8d7da; color: #721c24; }

        .quantity-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .quantity-fill {
            height: 100%;
            border-radius: 4px;
        }

        .fill-normal { background: var(--success); }
        .fill-low { background: var(--warning); }
        .fill-out { background: var(--accent); }

        /* === مودال حركة المخزون === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-title {
            color: var(--primary);
            font-size: 1.3rem;
            margin: 0;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text);
        }

        /* === متجاوب === */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th, .table td {
                padding: 8px;
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

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- الشريط العلوي -->
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-warehouse"></i>
            <span>إدارة المخازن</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> الرئيسية</a>
            <a href="inventory.php" class="nav-link active"><i class="fas fa-warehouse"></i> المخازن</a>
            <a href="products.php" class="nav-link"><i class="fas fa-boxes"></i> المنتجات</a>
            <a href="suppliers.php" class="nav-link"><i class="fas fa-truck-loading"></i> الموردين</a>
            <a href="reports.php?type=inventory" class="nav-link"><i class="fas fa-chart-bar"></i> تقارير المخازن</a>
        </div>
    </nav>

    <!-- المحتوى الرئيسي -->
    <div class="container">
        <!-- رأس الصفحة -->
        <div class="page-header fade-in-up">
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i>
                نظام إدارة المخازن المتكامل
            </h1>
            <p>إدارة كاملة للمخزون - تتبع، تحليل، وتقارير مفصلة</p>
        </div>

        <!-- إحصائيات المخازن -->
        <div class="stats-grid">
            <div class="stat-card total fade-in-up">
                <div class="stat-icon icon-total">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3>إجمالي المنتجات</h3>
                <div class="stat-value"><?php echo $total_products; ?></div>
                <p>منتج في المخازن</p>
            </div>
            
            <div class="stat-card value fade-in-up">
                <div class="stat-icon icon-value">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3>القيمة الإجمالية</h3>
                <div class="stat-value"><?php echo number_format($total_value, 2); ?> <small>ر.س</small></div>
                <p>قيمة المخزون الكلية</p>
            </div>
            
            <div class="stat-card low fade-in-up">
                <div class="stat-icon icon-low">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>منتجات منخفضة</h3>
                <div class="stat-value"><?php echo $low_stock_count; ?></div>
                <p>تحتاج لإعادة طلب</p>
            </div>
            
            <div class="stat-card out fade-in-up">
                <div class="stat-icon icon-out">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3>منتجات منتهية</h3>
                <div class="stat-value"><?php echo $out_of_stock_count; ?></div>
                <p>نفذت من المخزون</p>
            </div>
        </div>

        <!-- نموذج البحث والتصفية -->
        <div class="filter-card fade-in-up">
            <form method="GET" action="">
                <div class="filter-form">
                    <div class="form-group">
                        <label class="form-label">بحث في المنتجات</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم، الكود، أو الوصف..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" class="form-control">
                            <option value="">جميع التصنيفات</option>
                            <?php while($category = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $category['category']; ?>" 
                                    <?php echo $category_filter == $category['category'] ? 'selected' : ''; ?>>
                                    <?php echo $category['category']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">المورد</label>
                        <select name="supplier" class="form-control">
                            <option value="">جميع الموردين</option>
                            <?php while($supplier = $suppliers->fetch_assoc()): ?>
                                <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo $supplier['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">حالة المخزون</label>
                        <select name="stock" class="form-control">
                            <option value="">جميع الحالات</option>
                            <option value="normal" <?php echo $stock_filter == 'normal' ? 'selected' : ''; ?>>طبيعي</option>
                            <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>منخفض</option>
                            <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>منتهي</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> بحث
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons fade-in-up">
            <a href="add_product.php" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> إضافة منتج جديد
            </a>
            <a href="inventory_movements.php" class="btn btn-primary">
                <i class="fas fa-exchange-alt"></i> حركات المخزون
            </a>
            <a href="low_stock_report.php" class="btn btn-warning">
                <i class="fas fa-exclamation-triangle"></i> تقرير المنتجات المنخفضة
            </a>
            <a href="export_inventory.php" class="btn btn-purple">
                <i class="fas fa-file-export"></i> تصدير البيانات
            </a>
            <button onclick="showInventoryMovementModal()" class="btn btn-outline">
                <i class="fas fa-arrows-alt-h"></i> حركة مخزون سريعة
            </button>
        </div>

        <!-- جدول المنتجات -->
        <div class="table-container fade-in-up">
            <table class="table">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>المنتج</th>
                        <th>الكود</th>
                        <th>التصنيف</th>
                        <th>المورد</th>
                        <th>المخزون</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>القيمة</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($product = $products->fetch_assoc()): 
                        $stock_percentage = $product['min_quantity'] > 0 ? ($product['quantity'] / $product['min_quantity']) * 100 : 100;
                        $stock_percentage = min($stock_percentage, 100);
                    ?>
                    <tr>
                        <td>
                            <div class="product-image">
                                <i class="fas fa-box"></i>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <?php if(!empty($product['description'])): ?>
                                <br><small style="color: #6c757d;"><?php echo substr($product['description'], 0, 50); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo $product['code'] ?? 'N/A'; ?></code></td>
                        <td><?php echo $product['category'] ?? 'غير مصنف'; ?></td>
                        <td><?php echo $product['supplier_name'] ?? 'غير محدد'; ?></td>
                        <td>
                            <div style="text-align: center;">
                                <strong><?php echo $product['quantity']; ?></strong>
                                <?php if($product['min_quantity'] > 0): ?>
                                    <br><small>/ <?php echo $product['min_quantity']; ?> حد أدنى</small>
                                <?php endif; ?>
                            </div>
                            <div class="quantity-bar">
                                <div class="quantity-fill <?php echo 'fill-' . $product['stock_status']; ?>" 
                                     style="width: <?php echo $stock_percentage; ?>%"></div>
                            </div>
                        </td>
                        <td><?php echo number_format($product['cost_price'] ?? 0, 2); ?> ر.س</td>
                        <td><?php echo number_format($product['selling_price'] ?? 0, 2); ?> ر.س</td>
                        <td>
                            <strong style="color: var(--success);">
                                <?php echo number_format($product['total_value'], 2); ?> ر.س
                            </strong>
                        </td>
                        <td>
                            <?php if($product['stock_status'] == 'normal'): ?>
                                <span class="stock-status status-normal">🟢 طبيعي</span>
                            <?php elseif($product['stock_status'] == 'low_stock'): ?>
                                <span class="stock-status status-low">🟡 منخفض</span>
                            <?php else: ?>
                                <span class="stock-status status-out">🔴 منتهي</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <button onclick="showProductModal(<?php echo $product['id']; ?>)" 
                                        class="btn btn-outline" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="showMovementModal(<?php echo $product['id']; ?>, '<?php echo $product['name']; ?>')" 
                                        class="btn btn-primary" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-warning" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo $product['name']; ?>')" 
                                        class="btn btn-danger" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if($products->num_rows == 0): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 40px; color: #6c757d;">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <br>
                            لا توجد منتجات لعرضها
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- معلومات التقرير -->
        <div class="filter-card fade-in-up">
            <h3 style="margin-bottom: 15px; color: var(--primary);">
                <i class="fas fa-chart-bar"></i> ملخص تقرير المخزون
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>تاريخ التقرير:</strong> <?php echo date('Y-m-d H:i'); ?>
                </div>
                <div>
                    <strong>عدد المنتجات:</strong> <?php echo $products->num_rows; ?> منتج
                </div>
                <div>
                    <strong>القيمة الإجمالية:</strong> <?php echo number_format($total_value, 2); ?> ر.س
                </div>
                <div>
                    <strong>مسؤول النظام:</strong> <?php echo $user_info['username']; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال حركة المخزون -->
    <div id="movementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="movementModalTitle">حركة مخزون</h3>
                <button class="close" onclick="closeMovementModal()">&times;</button>
            </div>
            <form id="movementForm" method="POST">
                <input type="hidden" name="add_movement" value="1">
                <input type="hidden" id="product_id" name="product_id">
                
                <div class="form-group">
                    <label class="form-label">المنتج</label>
                    <input type="text" id="product_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">نوع الحركة</label>
                    <select id="movement_type" name="movement_type" class="form-control" required>
                        <option value="in">دخول مخزون</option>
                        <option value="out">خروج مخزون</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الكمية</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات حول الحركة..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">
                        <i class="fas fa-save"></i> حفظ الحركة
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeMovementModal()" style="flex: 1;">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // فتح وإغلاق مودال حركة المخزون
        function showMovementModal(productId, productName) {
            document.getElementById('product_id').value = productId;
            document.getElementById('product_name').value = productName;
            document.getElementById('movementModalTitle').textContent = 'حركة مخزون - ' + productName;
            document.getElementById('movementModal').style.display = 'flex';
        }

        function showInventoryMovementModal() {
            document.getElementById('product_id').value = '';
            document.getElementById('product_name').value = '';
            document.getElementById('movementModalTitle').textContent = 'حركة مخزون سريعة';
            document.getElementById('movementModal').style.display = 'flex';
        }

        function closeMovementModal() {
            document.getElementById('movementModal').style.display = 'none';
            document.getElementById('movementForm').reset();
        }

        // حذف منتج مع التأكيد
        function deleteProduct(productId, productName) {
            if(confirm(`هل أنت متأكد من حذف المنتج "${productName}"؟`)) {
                window.location.href = `delete_product.php?id=${productId}`;
            }
        }

        // عرض تفاصيل المنتج
        function showProductModal(productId) {
            // يمكن إضافة AJAX لجلب تفاصيل المنتج
            alert('عرض تفاصيل المنتج - يمكن تطويره باستخدام AJAX');
        }

        // إغلاق المودال عند النقر خارج المحتوى
        window.onclick = function(event) {
            const modal = document.getElementById('movementModal');
            if (event.target == modal) {
                closeMovementModal();
            }
        }

        // تأثيرات للجدول
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = (index * 0.1) + 's';
                row.classList.add('fade-in-up');
            });
        });

        // إضافة أنيميشن للصفوف
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in-up {
                animation: fadeInUp 0.5s ease-out;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>