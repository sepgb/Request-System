-- =========================================================
-- Library / Registrar Document Request System
-- Database Schema
-- =========================================================
CREATE DATABASE IF NOT EXISTS library_request_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE library_request_system;
-- ---------------------------------------------------------
-- Table: admin
-- Single registrar/admin account(s) that manage requests
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  -- stored with PHP password_hash()
  full_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- ---------------------------------------------------------
-- Table: requests
-- Every document request a student submits
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(20) NOT NULL UNIQUE,
  -- e.g. REQ-2026-000001
  student_number VARCHAR(20) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  course VARCHAR(150) NOT NULL,
  year_level VARCHAR(30) NOT NULL,
  semester VARCHAR(30) NULL,
  -- only required when document_type is Certificate of Enrollment / Certificate of Grades
  document_type VARCHAR(150) NOT NULL,
  copies INT NOT NULL DEFAULT 1,
  purpose TEXT NOT NULL,
  amount DECIMAL(8, 2) NOT NULL DEFAULT 20.00,
  -- flat GCash processing fee (prototype: not connected to a real payment gateway)
  claim_date DATE NULL,
  -- earliest date the student can claim the document at the registrar (see includes/functions.php)
  request_status ENUM(
    'Pending',
    'Ready for Pickup'
  ) NOT NULL DEFAULT 'Pending',
  admin_remarks TEXT NULL,
  date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student_number (student_number),
  INDEX idx_status (request_status)
) ENGINE = InnoDB;