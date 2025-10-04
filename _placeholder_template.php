<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
$title = $title ?? 'صفحة';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($title); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Cairo',sans-serif;background:#f8f9fa;color:#34495e;direction:rtl}.container{max-width:1000px;margin:40px auto;padding:0 20px}.card{background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:25px}</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h2 style="margin-top:0;"><i class="fas fa-info-circle"></i> <?php echo esc($title); ?></h2>
    <p>هذه صفحة مؤقتة. سيتم استكمال الوظائف لاحقاً.</p>
  </div>
</div>
</body>
</html>
