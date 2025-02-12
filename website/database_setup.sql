CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    size VARCHAR(20) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    INDEX idx_email (email),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action)
);