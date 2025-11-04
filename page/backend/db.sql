-- ✅ สร้างฐานข้อมูลชื่อ shopdb ถ้ายังไม่มี
CREATE DATABASE IF NOT EXISTS shopdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ✅ ใช้งานฐานข้อมูลนี้
USE shopdb;
-- ✅ ตาราง users (เก็บข้อมูลผู้ใช้งาน)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,-- รหัสผู้ใช้ (Primary Key)
  name VARCHAR(150),                -- ชื่อผู้ใช้
  email VARCHAR(255) NOT NULL UNIQUE, -- อีเมล (ต้องไม่ซ้ำกัน)
  password_hash VARCHAR(255) NOT NULL,-- รหัสผ่าน (ถูกเข้ารหัสแล้ว)
  is_verified TINYINT(1) DEFAULT 0,  -- สถานะยืนยันอีเมล (0=ยัง, 1=ยืนยันแล้ว)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP-- วันที่สมัครสมาชิก
);
-- ✅ ตาราง email_verifications (เก็บรหัส OTP สำหรับยืนยันอีเมล)
CREATE TABLE IF NOT EXISTS email_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,-- รหัสการยืนยัน
  user_id INT NOT NULL,             -- เชื่อมกับผู้ใช้ในตาราง users
  otp_code VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
