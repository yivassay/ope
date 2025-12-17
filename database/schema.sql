-- Callcenter analytics (MySQL 8+ recommended)
-- Charset: utf8mb4

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','callcenter_manager') NOT NULL DEFAULT 'callcenter_manager',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(190) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operators (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  fixed_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  percent_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_operators_active (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operator_daily_sales (
  sale_date DATE NOT NULL,
  operator_id BIGINT UNSIGNED NOT NULL,
  sales_sum DECIMAL(14,2) NOT NULL DEFAULT 0,
  order_count INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  updated_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sale_date, operator_id),
  KEY idx_operator_daily_sales_date (sale_date),
  KEY idx_operator_daily_sales_operator (operator_id, sale_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS telegram_daily_stats (
  stat_date DATE NOT NULL,
  order_count INT UNSIGNED NOT NULL DEFAULT 0,
  sum_final DECIMAL(14,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  updated_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stat_date),
  KEY idx_telegram_stats_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily aggregates for Smartomato delivered orders (by Tashkent date)
CREATE TABLE IF NOT EXISTS smartomato_daily_stats (
  stat_date DATE NOT NULL,
  restaurant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  delivery_type ENUM('delivery','pickup') NOT NULL,
  channel ENUM('app','web','yandex','wolt','board','other') NOT NULL,
  payment_source VARCHAR(50) NOT NULL,
  order_count INT UNSIGNED NOT NULL DEFAULT 0,
  sum_final DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stat_date, restaurant_id, delivery_type, channel, payment_source),
  KEY idx_smartomato_stats_date (stat_date),
  KEY idx_smartomato_stats_restaurant (restaurant_id, stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smartomato_runs (
  run_date DATE NOT NULL,
  status ENUM('running','ok','error') NOT NULL,
  message VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

