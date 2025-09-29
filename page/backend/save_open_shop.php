session_start();
// สมมติรับ POST อื่น ๆ มาแล้ว
$userEmail = $_SESSION['user_email'] ?? null;
// ใช้ $userEmail เป็นตัวจริงเวลาเขียนฐานข้อมูล