<?php
// THAMMUE/backend/config.php
return [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'shopdb',
        'user' => 'root',   // XAMPP เริ่มต้น
        'pass' => '',       // XAMPP เริ่มต้น
        'charset' => 'utf8mb4'
    ],
    'smtp' => [
        // แนะนำเริ่มด้วย Mailtrap หรือใช้ Gmail (ต้องมี App Password)
        'host' => 'smtp.gmail.com',   // หรือ smtp.mailtrap.io
        'username' => 'your@gmail.com',
        'password' => 'your-app-password',
        'port' => 587,
        'secure' => 'tls',
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => 'Your Shop'
    ],
    'otp' => [
        'length' => 6,
        'expire_minutes' => 10
    ]
];
