<?php
// /page/backend/categories.php
// ✅ หน้าที่ของไฟล์นี้: ใช้ดึง “รายชื่อหมวดหมู่สินค้า (categories)” จากฐานข้อมูล
// เพื่อนำไปแสดงในหน้าเว็บ เช่น หน้าค้นหา, หน้าอัปโหลดสินค้า, หรือหน้าเลือกหมวดหมู่
header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4","root","");
$rows = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
