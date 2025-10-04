<?php
require_once __DIR__ . '/config/db.php';
redirectIfAuthenticated();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['role'] = $row['role'];
                $conn->query('UPDATE users SET last_login = NOW() WHERE id = ' . (int)$row['id']);
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

if (isset($_GET['success'])) {
    $success = '✅ ' . esc($_GET['success']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة العمال</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2c3e50; --secondary: #3498db; --accent: #e74c3c; --success: #27ae60;
            --warning: #f39c12; --light: #ecf0f1; --dark: #2c3e50; --white: #ffffff; --text: #34495e;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body { font-family: 'Cairo', sans-serif; background: var(--gradient); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; direction: rtl; }
        .login-container { display: flex; width: 100%; max-width: 1000px; background: var(--white); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; min-height: 600px; }
        .login-left { flex: 1; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: var(--white); padding: 40px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .login-left::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\" fill=\"rgba(255,255,255,0.1)\"><circle cx=\"50\" cy=\"50\" r=\"2\"/></svg>') repeat; animation: float 20s infinite linear; }
        @keyframes float { 0% { transform: translate(0, 0) rotate(0deg); } 100% { transform: translate(-50px, -50px) rotate(360deg); } }
        .login-content { position: relative; z-index: 2; }
        .welcome-title { font-size: 2.5rem; margin-bottom: 15px; font-weight: 700; }
        .welcome-subtitle { font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9; }
        .features-list { list-style: none; margin-top: 30px; }
        .features-list li { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; font-size: 1.1rem; }
        .features-list i { color: var(--warning); font-size: 1.2rem; }
        .login-right { flex: 1; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 40px; }
        .logo-icon { width: 80px; height: 80px; background: var(--gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem; color: var(--white); }
        .logo h1 { color: var(--primary); font-size: 2rem; font-weight: 700; }
        .logo p { color: var(--text); opacity: 0.7; }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-label { display: block; margin-bottom: 8px; color: var(--primary); font-weight: 600; font-size: 1rem; }
        .input-with-icon { position: relative; }
        .form-control { width: 100%; padding: 15px 50px 15px 15px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; transition: all 0.3s ease; background: var(--white); font-family: 'Cairo', sans-serif; }
        .form-control:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1); }
        .input-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 1.1rem; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Cairo', sans-serif; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary { background: linear-gradient(135deg, var(--secondary), var(--primary)); color: var(--white); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4); }
        .btn-outline { background: transparent; border: 2px solid var(--secondary); color: var(--secondary); }
        .btn-outline:hover { background: var(--secondary); color: var(--white); }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .login-links { text-align: center; margin-top: 30px; }
        .login-links a { color: var(--secondary); text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .login-links a:hover { color: var(--primary); text-decoration: underline; }
        .divider { text-align: center; margin: 25px 0; position: relative; color: #6c757d; }
        .divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 45%; height: 1px; background: #e9ecef; }
        .divider::before { right: 0; } .divider::after { left: 0; }
        @media (max-width: 768px) {
            .login-container { flex-direction: column; max-width: 100%; }
            .login-left { padding: 30px 20px; text-align: center; }
            .login-right { padding: 30px 20px; }
            .welcome-title { font-size: 2rem; }
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(30px);} to { opacity: 1; transform: translateY(0);} }
        .slide-in { animation: slideIn 0.6s ease-out; }
        .form-control:valid { border-color: var(--success); }
        .password-toggle { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; cursor: pointer; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="login-container slide-in">
        <div class="login-left">
            <div class="login-content">
                <h1 class="welcome-title">مرحباً بعودتك! 👋</h1>
                <p class="welcome-subtitle">سجل الدخول إلى نظام إدارة العمال المتكامل</p>
                <ul class="features-list">
                    <li><i class="fas fa-shield-alt"></i><span>نظام آمن وحماية متقدمة</span></li>
                    <li><i class="fas fa-users-cog"></i><span>إدارة متكاملة للعمال والرواتب</span></li>
                    <li><i class="fas fa-chart-line"></i><span>تقارير وتحليلات مفصلة</span></li>
                    <li><i class="fas fa-mobile-alt"></i><span>واجهة متجاوبة مع جميع الأجهزة</span></li>
                    <li><i class="fas fa-headset"></i><span>دعم فني متواصل 24/7</span></li>
                </ul>
            </div>
        </div>
        <div class="login-right">
            <div class="login-card">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-users-cog"></i></div>
                    <h1>نظام العمال</h1>
                    <p>إدارة متكاملة بحقوق متقدمة</p>
                </div>
                <?php if(!empty($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="username"><i class="fas fa-user"></i> اسم المستخدم</label>
                        <div class="input-with-icon">
                            <input type="text" id="username" name="username" class="form-control" placeholder="أدخل اسم المستخدم" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <div class="input-icon"><i class="fas fa-user-circle"></i></div>
                            <button type="button" class="password-toggle" style="visibility:hidden"></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
                        <div class="input-with-icon">
                            <input type="password" id="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required>
                            <div class="input-icon"><i class="fas fa-key"></i></div>
                            <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i>تسجيل الدخول</button>
                    </div>
                </form>
                <div class="divider">أو</div>
                <div class="login-links">
                    <p>ليس لديك حساب؟ <a href="register.php"><i class="fas fa-user-plus"></i> إنشاء حساب جديد</a></p>
                    <p><a href="forgot_password.php"><i class="fas fa-question-circle"></i> نسيت كلمة المرور؟</a></p>
                </div>
                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <p style="color: #6c757d; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> للدعم الفني: <a href="tel:+966500000000" style="color: var(--secondary);">+966 50 000 0000</a></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() { const passwordInput = document.getElementById('password'); const toggleIcon = document.querySelector('.password-toggle i'); if (passwordInput.type === 'password') { passwordInput.type = 'text'; if (toggleIcon) toggleIcon.className = 'fas fa-eye-slash'; } else { passwordInput.type = 'password'; if (toggleIcon) toggleIcon.className = 'fas fa-eye'; } }
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() { this.parentElement.style.transform = 'scale(1.02)'; });
                input.addEventListener('blur', function() { this.parentElement.style.transform = 'scale(1)'; });
            });
        });
    </script>
</body>
</html>
