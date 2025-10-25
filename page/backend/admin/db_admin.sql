-- /page/backend/admin/db_admin.sql
-- Run this once (or import via phpMyAdmin). Safe to re-run.

-- ========= MAIN APP DB =========
CREATE DATABASE IF NOT EXISTS shopdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopdb;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shops (create if not exists; adjust columns if your current table differs)
CREATE TABLE IF NOT EXISTS shops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  shop_name VARCHAR(255) NOT NULL,
  pickup_addr TEXT,
  province VARCHAR(100),
  email VARCHAR(255),
  phone VARCHAR(50),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  avatar_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL UNIQUE,
  seller_type ENUM('person','company') NOT NULL,
  citizen_name VARCHAR(255),
  citizen_id VARCHAR(32),
  dob DATE NULL,
  addr_line TEXT,
  subdistrict VARCHAR(100),
  district VARCHAR(100),
  province VARCHAR(100),
  postcode VARCHAR(10),
  company_name VARCHAR(255),
  tax_id VARCHAR(32),
  reg_doc VARCHAR(255),
  id_rep VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= EXCHANGE DB =========
CREATE DATABASE IF NOT EXISTS shopdb_ex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopdb_ex;

CREATE TABLE IF NOT EXISTS ex_user_kyc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  national_id_hash VARCHAR(255) NOT NULL,
  dob DATE,
  address TEXT,
  id_front_url VARCHAR(255),
  id_back_url VARCHAR(255),
  selfie_url VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ex_item_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  user_id INT NULL,
  reason TEXT NOT NULL,
  status ENUM('open','resolved') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
