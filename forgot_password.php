<?php
require_once __DIR__ . '/config/db.php';
redirectIfAuthenticated();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>نسيت كلمة المرور</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Cairo',sans-serif;direction:rtl;background:#f8f9fa;color:#34495e}.container{max-width:500px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:25px}.form-control{width:100%;padding:12px;border:2px solid #e9ecef;border-radius:8px;margin-bottom:12px}.btn{padding:12px 16px;border:none;border-radius:8px;background:#3498db;color:#fff;font-weight:600;cursor:pointer;width:100%}.note{padding:10px;border-radius:8px;background:#fff3cd;color:#856404;margin-bottom:10px}</style>
</head>
<body>
  <div class="container">
    <h2 style="margin-top:0"><i class="fas fa-key"></i> استعادة كلمة المرور</h2>
    <div class="note">هذه ميزة توضيحية. تواصل مع المسؤول لإعادة التعيين.</div>
    <form>
      <input class="form-control" name="username" placeholder="اسم المستخدم" required>
      <button class="btn" type="button" onclick="alert('يرجى التواصل مع المسؤول لإعادة تعيين كلمة المرور')">إرسال</button>
    </form>
    <p style="margin-top:10px"><a href="login.php">عودة لتسجيل الدخول</a></p>
  </div>
</body></html>
