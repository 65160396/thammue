<?php
// เปลี่ยน 'admin123' เป็นรหัสผ่านที่ต้องการ
$plain = 'admin123';

// ใช้ค่าเริ่มต้น (bcrypt) ของ PHP
$hash = password_hash($plain, PASSWORD_DEFAULT);

// แสดงผล
echo $hash;
