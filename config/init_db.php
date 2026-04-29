<?php
/**
 * init_db.php
 *
 * מתחבר ל-MySQL וודא שה-DB, הטבלאות והנתונים הבסיסיים קיימים.
 * נקרא אוטומטית מ-db.php — בטוח לריצה חוזרת (idempotent).
 */

function init_database(string $host, string $user, string $password, string $dbname): mysqli
{
    // ── 1. התחברות ללא בחירת DB ──────────────────────────────────────────────
    $conn = new mysqli($host, $user, $password);
    if ($conn->connect_error) {
        die(json_encode([
            "error"   => "DB connection failed",
            "details" => $conn->connect_error
        ]));
    }
    $conn->set_charset("utf8mb4");

    // ── 2. יצירת ה-DB אם לא קיים ─────────────────────────────────────────────
    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($conn->error) {
        die(json_encode(["error" => "Failed to create database", "details" => $conn->error]));
    }
    $conn->select_db($dbname);

    // ── 3. יצירת טבלאות (IF NOT EXISTS) ──────────────────────────────────────
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    $tables = [

        "users" => "
            CREATE TABLE IF NOT EXISTS `users` (
                `user_id`    INT AUTO_INCREMENT PRIMARY KEY,
                `name`       VARCHAR(100)  NOT NULL,
                `email`      VARCHAR(150)  NOT NULL UNIQUE,
                `password`   VARCHAR(255)  NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",

        "categories" => "
            CREATE TABLE IF NOT EXISTS `categories` (
                `category_id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`     INT          NOT NULL,
                `name`        VARCHAR(100) NOT NULL,
                `type`        ENUM('income','expense') NOT NULL,
                CONSTRAINT `fk_category_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
                UNIQUE KEY `uk_user_name_type` (`user_id`, `name`, `type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",

        "transactions" => "
            CREATE TABLE IF NOT EXISTS `transactions` (
                `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`        INT             NOT NULL,
                `amount`         DECIMAL(10,2)   NOT NULL,
                `type`           ENUM('income','expense') NOT NULL,
                `category_id`    INT,
                `date`           DATE            NOT NULL,
                `description`    VARCHAR(255),
                CONSTRAINT `fk_transaction_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
                CONSTRAINT `fk_transaction_category`
                    FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
                INDEX `idx_user_date` (`user_id`, `date`),
                INDEX `idx_category`  (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",

        "goals" => "
            CREATE TABLE IF NOT EXISTS `goals` (
                `goal_id`          INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`          INT           NOT NULL,
                `target_amount`    DECIMAL(10,2) NOT NULL,
                `allocated_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `description`      VARCHAR(255),
                `deadline`         DATE,
                `status`           ENUM('active','realized') NOT NULL DEFAULT 'active',
                CONSTRAINT `fk_goal_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",

        "budgets" => "
            CREATE TABLE IF NOT EXISTS `budgets` (
                `budget_id`     INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`       INT           NOT NULL,
                `category_id`   INT           NOT NULL,
                `monthly_limit` DECIMAL(10,2) NOT NULL,
                CONSTRAINT `fk_budget_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
                CONSTRAINT `fk_budget_category`
                    FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE CASCADE,
                UNIQUE KEY `uk_user_category` (`user_id`, `category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
    ];

    foreach ($tables as $table => $sql) {
        $conn->query($sql);
        if ($conn->error) {
            die(json_encode(["error" => "Failed to create table `$table`", "details" => $conn->error]));
        }
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // ── 4. מיגרציות: עמודות שנוספו אחרי יצירת הטבלאות ────────────────────────
    _add_column_if_missing($conn, $dbname, "goals", "allocated_amount",
        "ALTER TABLE `goals` ADD COLUMN `allocated_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `target_amount`");

    _add_column_if_missing($conn, $dbname, "goals", "description",
        "ALTER TABLE `goals` ADD COLUMN `description` VARCHAR(255) AFTER `allocated_amount`");

    _add_column_if_missing($conn, $dbname, "goals", "status",
        "ALTER TABLE `goals` ADD COLUMN `status` ENUM('active','realized') NOT NULL DEFAULT 'active' AFTER `deadline`");

    // ── 5. נתוני זרע — רק אם הטבלאות ריקות ──────────────────────────────────
    $userCount = $conn->query("SELECT COUNT(*) AS c FROM `users`")->fetch_assoc()['c'];

    if ($userCount == 0) {
        $seeds = [
            // users
            "INSERT INTO `users` (`name`, `email`, `password`) VALUES
                ('Dana Levi', 'dana@example.com', '1234'),
                ('Omer Cohen', 'omer@example.com', '1234')",

            // categories (user_id=1 = Dana)
            "INSERT INTO `categories` (`user_id`, `name`, `type`) VALUES
                (1, 'Salary',        'income'),
                (1, 'Freelance',     'income'),
                (1, 'Food',          'expense'),
                (1, 'Transport',     'expense'),
                (1, 'Entertainment', 'expense')",

            // transactions
            "INSERT INTO `transactions` (`user_id`, `amount`, `type`, `category_id`, `date`, `description`) VALUES
                (1, 8000, 'income',  1, '2025-04-01', 'Salary'),
                (1,  200, 'expense', 3, '2025-04-02', 'Groceries'),
                (1,   50, 'expense', 4, '2025-04-03', 'Bus'),
                (1,  300, 'expense', 5, '2025-04-05', 'Movies')",

            // goals
            "INSERT INTO `goals` (`user_id`, `target_amount`, `allocated_amount`, `description`, `deadline`, `status`) VALUES
                (1, 10000, 0, 'חיסכון כללי', '2025-12-31', 'active')",

            // budgets
            "INSERT INTO `budgets` (`user_id`, `category_id`, `monthly_limit`) VALUES
                (1, 3, 1000),
                (1, 4,  300),
                (1, 5,  500)",
        ];

        foreach ($seeds as $sql) {
            $conn->query($sql);
            if ($conn->error) {
                die(json_encode(["error" => "Failed to seed data", "details" => $conn->error, "query" => $sql]));
            }
        }
    }

    return $conn;
}

/**
 * מוסיף עמודה לטבלה רק אם היא עדיין לא קיימת.
 */
function _add_column_if_missing(mysqli $conn, string $db, string $table, string $column, string $alterSql): void
{
    $res = $conn->query(
        "SELECT COUNT(*) AS c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$db'
           AND TABLE_NAME   = '$table'
           AND COLUMN_NAME  = '$column'"
    );
    if ($res && $res->fetch_assoc()['c'] == 0) {
        $conn->query($alterSql);
    }
}
