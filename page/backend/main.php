<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?type=error&msg=' . rawurlencode('กรุณาเข้าสู่ระบบก่อน'));
    exit;
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>Main</title>
</head>

<body>
    <h1>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
    <p><a href="/page/backend/logout.php">ออกจากระบบ</a></p>
</body>

</html>