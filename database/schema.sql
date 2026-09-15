CREATE DATABASE IF NOT EXISTS library_request_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE library_request_system;
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  -- stored with PHP password_hash()
  full_name VARCHAR(100) NOT NULL,
  photo VARCHAR(255) NULL,
  -- relative path from site root, e.g. assets/uploads/admin_photos/admin_3_....jpg
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(20) NOT NULL UNIQUE,
  -- e.g. REQ-2026-000001
  student_number VARCHAR(20) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  course VARCHAR(150) NOT NULL,
  year_level VARCHAR(30) NOT NULL,
  semester VARCHAR(30) NULL,
  document_type VARCHAR(150) NOT NULL,
  copies INT NOT NULL DEFAULT 1,
  purpose TEXT NOT NULL,
  amount DECIMAL(8, 2) NOT NULL DEFAULT 20.00,
  claim_date DATE NULL,
  request_status ENUM('Pending', 'Ready for Pickup') NOT NULL DEFAULT 'Pending',
  admin_remarks TEXT NULL,
  date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student_number (student_number),
  INDEX idx_status (request_status)
) ENGINE = InnoDB;