<?php
session_start();
require_once __DIR__ . '/config/db.php';

function applySchema(mysqli $conn): array {
    $schemaPath = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaPath)) {
        return [false, 'لم يتم العثور على ملف المخطط database/schema.sql'];
    }
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return [false, 'تعذر قراءة ملف المخطط'];
    }

    // Run multi-query
    if (!$conn->multi_query($sql)) {
        return [false, 'خطأ أثناء تطبيق المخطط: ' . $conn->error];
    }
    // Flush remaining results
    do { 
        if ($result = $conn->store_result()) { $result->free(); }
    } while ($conn->more_results() && $conn->next_result());

    // Seed admin user if not exists
    $adminUser = 'admin';
    $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $check->bind_param('s', $adminUser);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    if (!$exists) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, "admin")');
        $stmt->bind_param('ss', $adminUser, $hash);
        $stmt->execute();
    }

    // Seed sample category and supplier to make UI useful
    $conn->query("INSERT IGNORE INTO categories (id, name) VALUES (1, 'عام')");
    $conn->query("INSERT IGNORE INTO suppliers (id, name) VALUES (1, 'مزود افتراضي')");

    return [true, 'تم تطبيق المخطط وبذر المستخدم الافتراضي (admin / admin123) بنجاح'];
}

[$ok, $message] = applySchema($conn);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تثبيت النظام</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Cairo', sans-serif; background: #f8f9fa; margin:0; padding:0; }
    .container { max-width: 700px; margin: 60px auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
    h1 { margin: 0 0 10px; color: #2c3e50; }
    .msg { padding: 15px; border-radius: 10px; margin: 20px 0; font-weight: 600; }
    .ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .bad { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    a.btn { display:inline-flex; align-items:center; gap:10px; padding: 12px 18px; border-radius:10px; text-decoration:none; font-weight:700; }
    .btn-primary { background: #3498db; color: #fff; }
  </style>
</head>
<body>
  <div class="container">
    <h1><i class="fas fa-tools"></i> تثبيت النظام</h1>
    <p>سيتم إنشاء الجداول اللازمة وبذر مستخدم مسؤول افتراضي.</p>
    <div class="msg <?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo esc($message); ?></div>
    <?php if ($ok): ?>
      <p>تستطيع الآن تسجيل الدخول:</p>
      <ul>
        <li>اسم المستخدم: <strong>admin</strong></li>
        <li>كلمة المرور: <strong>admin123</strong></li>
      </ul>
      <p><a class="btn btn-primary" href="login.php"><i class="fas fa-sign-in-alt"></i> الذهاب لتسجيل الدخول</a></p>
    <?php else: ?>
      <p>يرجى التحقق من اتصال قاعدة البيانات وصلاحيات المستخدم.</p>
    <?php endif; ?>
  </div>
</body>
</html>
