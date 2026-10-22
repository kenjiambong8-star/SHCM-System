-- SHCM System schema
-- Run this in MySQL Workbench while connected as root (Local instance 3306).
-- Select the shcm_system schema first, then execute.

USE shcm_system;

CREATE TABLE IF NOT EXISTS requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  course VARCHAR(50) NOT NULL,
  year_level TINYINT NOT NULL,
  sex ENUM('Male','Female') NULL,
  birthdate DATE NULL,
  contact VARCHAR(30) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS health_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  requirement_id INT NOT NULL,
  status ENUM('Compliant','Pending','Non-compliant') NOT NULL DEFAULT 'Pending',
  checkup_date DATE NULL,
  valid_until DATE NULL,
  ref_no VARCHAR(50) NULL,
  notes VARCHAR(255) NULL,
  recorded_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_requirement FOREIGN KEY (requirement_id) REFERENCES requirements(id),
  CONSTRAINT fk_hr_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_hr_student (student_id),
  INDEX idx_hr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO requirements (id, name, description) VALUES
  (1, 'Medical certificate', 'Valid school medical certificate'),
  (2, 'Chest X-ray', 'Chest X-ray clearance'),
  (3, 'Dental clearance', 'Dental examination clearance'),
  (4, 'Vaccination record', 'Updated immunization record'),
  (5, 'Physical exam', 'Annual physical examination');

-- Optional: let the app user create/alter tables later if needed
GRANT CREATE, ALTER, INDEX, DROP, REFERENCES ON shcm_system.* TO 'shcm_user'@'localhost';
FLUSH PRIVILEGES;
