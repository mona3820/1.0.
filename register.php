<?php
require_once __DIR__ . '/config/db.php';
redirectIfAuthenticated();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $msg = 'اسم المستخدم موجود مسبقاً';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, "user")');
            $ins->bind_param('ss', $username, $hash);
            if ($ins->execute()) { header('Location: login.php?success=تم إنشاء الحساب بنجاح'); exit; }
            else { $msg = 'حدث خطأ أثناء إنشاء الحساب'; }
        }
    } else {
        $msg = 'يرجى إدخال البيانات المطلوبة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>إنشاء حساب</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Cairo',sans-serif;direction:rtl;background:#f8f9fa;color:#34495e}.container{max-width:500px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:25px}.form-control{width:100%;padding:12px;border:2px solid #e9ecef;border-radius:8px;margin-bottom:12px}.btn{padding:12px 16px;border:none;border-radius:8px;background:#3498db;color:#fff;font-weight:600;cursor:pointer;width:100%}.alert{padding:10px;border-radius:8px;background:#f8d7da;color:#721c24;margin-bottom:10px}</style>
</head>
<body>
  <div class="container">
    <h2 style="margin-top:0"><i class="fas fa-user-plus"></i> إنشاء حساب جديد</h2>
    <?php if($msg){ ?><div class="alert"><?php echo esc($msg); ?></div><?php } ?>
    <form method="POST">
      <input class="form-control" name="username" placeholder="اسم المستخدم" required>
      <input class="form-control" name="password" type="password" placeholder="كلمة المرور" required>
      <button class="btn" type="submit">إنشاء</button>
    </form>
    <p style="margin-top:10px">لديك حساب؟ <a href="login.php">تسجيل الدخول</a></p>
  </div>
</body></html>
