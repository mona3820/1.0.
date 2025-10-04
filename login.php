<?php
session_start();
require_once __DIR__ . '/config/db.php';

// إذا كان المستخدم مسجل دخول بالفعل، توجيه للوحة التحكم
redirectIfAuthenticated();

$error = '';
$success = isset($_GET['success']) ? esc($_GET['success']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = (string)$user['username'];
                $_SESSION['role'] = (string)$user['role'];

                $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $upd->bind_param('i', $_SESSION['user_id']);
                $upd->execute();

                header('Location: dashboard.php');
                exit;
            } else {
                $error = '❌ كلمة المرور غير صحيحة';
            }
        } else {
            $error = '❌ اسم المستخدم غير موجود';
        }
    } else {
        $error = '⚠️ يرجى ملء جميع الحقول';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تسجيل الدخول - نظام إدارة المخازن</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary:#2c3e50; --secondary:#3498db; --gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%); --text:#34495e; --white:#fff; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Cairo', sans-serif; background: var(--gradient); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { width: 100%; max-width: 960px; display: grid; grid-template-columns: 1fr 1fr; background: var(--white); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,.12); }
    .left { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: var(--white); padding: 40px; position: relative; }
    .right { padding: 50px; display:flex; align-items:center; justify-content:center; }
    .logo { text-align:center; margin-bottom: 30px; }
    .logo .icon { width: 80px; height: 80px; border-radius:50%; background: linear-gradient(135deg, #667eea, #764ba2); color:#fff; display:flex; align-items:center; justify-content:center; margin: 0 auto 12px; font-size: 2rem; }
    h1 { color:#2c3e50; margin-bottom: 6px; }
    p.muted { color:#6c757d; }
    .form-group { margin-bottom: 18px; }
    label { display:block; font-weight: 700; color:#2c3e50; margin-bottom:8px; }
    input { width:100%; border:2px solid #e9ecef; border-radius:12px; padding: 14px 16px; font-family:'Cairo', sans-serif; font-size: 1rem; }
    input:focus { outline:none; border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }
    .btn { width:100%; border:none; border-radius:12px; padding:14px 16px; background: linear-gradient(135deg, #3498db, #2c3e50); color:#fff; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; }
    .alert { padding: 14px; border-radius: 10px; text-align:center; font-weight:700; margin-bottom: 16px; }
    .alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    @media (max-width: 840px) { .card { grid-template-columns: 1fr; } .left { min-height: 220px; } }
  </style>
</head>
<body>
  <div class="card">
    <div class="left">
      <h2 style="margin-bottom:10px;">مرحباً بعودتك 👋</h2>
      <p>سجل الدخول لإدارة المخزون والعمال بسهولة.</p>
      <ul style="margin-top:20px; padding-right:18px; line-height:1.9;">
        <li>حركات مخزون دقيقة</li>
        <li>تقارير إيرادات ومصروفات</li>
        <li>إدارة عمال وحضور</li>
      </ul>
    </div>
    <div class="right">
      <div style="width:100%; max-width:380px;">
        <div class="logo">
          <div class="icon"><i class="fas fa-warehouse"></i></div>
          <h1>نظام المخازن</h1>
          <p class="muted">تسجيل الدخول</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-error"><?php echo esc($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-group">
            <label for="username"><i class="fas fa-user"></i> اسم المستخدم</label>
            <input type="text" id="username" name="username" required value="<?php echo esc($_POST['username'] ?? ''); ?>" />
          </div>
          <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
            <input type="password" id="password" name="password" required />
          </div>
          <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> دخول</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
