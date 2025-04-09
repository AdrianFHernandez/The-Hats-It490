CREATE TABLE Bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bundle_name VARCHAR(255) NOT NULL,
  version INT NOT NULL,
  bundle_type ENUM('WEB', 'DB', 'DMZ'),
  status ENUM('NEW', 'PASSED', 'FAILED') DEFAULT 'NEW',
  file_path VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(bundle_name, version)
);
