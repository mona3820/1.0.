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

// Reuse the existing Arabic login UI from the previous file
include __DIR__ . '/تسجيل دخول.php';
