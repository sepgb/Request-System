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
  request_status ENUM(
    'Pending',
    'Processing',
    'Ready for Pickup',
    'Rejected'
  ) NOT NULL DEFAULT 'Pending',
  admin_remarks TEXT NULL,
  date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student_number (student_number),
  INDEX idx_status (request_status)
) ENGINE = InnoDB;
-- ---------------------------------------------------------
-- Table: audit_log
-- Permanent record of every status change and claim/delete,
-- written BEFORE the mutating action — nothing is lost even
-- though `requests` rows get deleted once claimed.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  reference_no VARCHAR(20) NOT NULL,
  student_number VARCHAR(20) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  document_type VARCHAR(150) NOT NULL,
  action VARCHAR(30) NOT NULL,
  -- 'status_change' or 'claimed'
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NULL,
  performed_by_admin_id INT NULL,
  performed_by_username VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reference_no (reference_no),
  INDEX idx_request_id (request_id)
) ENGINE = InnoDB;
-- ---------------------------------------------------------
-- Table: lookup_attempts
-- Tracks track.php / receipt.php lookup attempts per IP so a
-- script can't brute-force reference numbers against guessed
-- student numbers. See includes/functions.php: checkRateLimit()
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS lookup_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_address, created_at)
) ENGINE = InnoDB;