CREATE DATABASE personal_budget;
USE personal_budget;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('income','expense') NOT NULL
);

CREATE TABLE transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type ENUM('income','expense') NOT NULL,
  category_id INT,
  date DATE NOT NULL,
  description VARCHAR(255),

  CONSTRAINT fk_transaction_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,

  CONSTRAINT fk_transaction_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,

  INDEX idx_user_date (user_id, date),
  INDEX idx_category (category_id)
);

CREATE TABLE goals (
  goal_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  target_amount DECIMAL(10,2) NOT NULL,
  allocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(255),
  deadline DATE,
  status ENUM('active','realized') NOT NULL DEFAULT 'active',

  CONSTRAINT fk_goal_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE budgets (
  budget_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  monthly_limit DECIMAL(10,2) NOT NULL,

  CONSTRAINT fk_budget_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,

  CONSTRAINT fk_budget_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,

  UNIQUE KEY uk_user_category (user_id, category_id)
);

-- Add user_id to categories
ALTER TABLE categories ADD COLUMN user_id INT NOT NULL AFTER category_id;

-- FK to users (ON DELETE CASCADE = deleting a user deletes their categories)
ALTER TABLE categories
  ADD CONSTRAINT fk_category_user
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

-- Unique key per user (same name+type allowed for different users)
ALTER TABLE categories ADD UNIQUE KEY uk_user_name_type (user_id, name, type);

-- Insert users first (categories depend on user_id)
INSERT INTO users (name, email, password) VALUES
('Dana Levi', 'dana@example.com', '1234'),
('Omer Cohen', 'omer@example.com', '1234');

-- Insert categories with user_id (all assigned to Dana Levi = user_id 1)
INSERT INTO categories (user_id, name, type) VALUES
(1, 'Salary', 'income'),
(1, 'Freelance', 'income'),
(1, 'Food', 'expense'),
(1, 'Transport', 'expense'),
(1, 'Entertainment', 'expense');

INSERT INTO transactions (user_id, amount, type, category_id, date, description) VALUES
(1, 8000, 'income', 1, '2025-04-01', 'Salary'),
(1, 200, 'expense', 3, '2025-04-02', 'Groceries'),
(1, 50, 'expense', 4, '2025-04-03', 'Bus'),
(1, 300, 'expense', 5, '2025-04-05', 'Movies');

-- =====================================================================
-- MIGRATION: run this block on an existing database (skip for fresh install)
-- ALTER TABLE goals ADD COLUMN allocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
-- ALTER TABLE goals ADD COLUMN description VARCHAR(255);
-- ALTER TABLE goals ADD COLUMN status ENUM('active','realized') NOT NULL DEFAULT 'active';
-- =====================================================================

INSERT INTO goals (user_id, target_amount, allocated_amount, description, deadline, status) VALUES
(1, 10000, 0, 'חיסכון כללי', '2025-12-31', 'active');

INSERT INTO budgets (user_id, category_id, monthly_limit) VALUES
(1, 3, 1000), -- Food
(1, 4, 300),  -- Transport
(1, 5, 500);  -- Entertainment
