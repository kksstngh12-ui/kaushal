-- SQL script to create wallet tables for branch admin

CREATE TABLE IF NOT EXISTS branch_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brid VARCHAR(50) NOT NULL UNIQUE,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each transaction (credit/debit) is recorded here for auditing
CREATE TABLE IF NOT EXISTS branch_wallet_transactions (
    trans_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    brid VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('CREDIT','DEBIT') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (brid),
    CONSTRAINT fk_wallet_branch FOREIGN KEY (brid) REFERENCES branch_wallet(brid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;