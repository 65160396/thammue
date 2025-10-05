<?php
// /page/backend/categories.php
header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4","root","");
$rows = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
