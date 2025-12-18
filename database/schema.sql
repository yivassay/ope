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

CREATE TABLE IF NOT EXISTS uzum_daily_stats (
  stat_date DATE NOT NULL,
  order_count INT UNSIGNED NOT NULL DEFAULT 0,
  sum_final DECIMAL(14,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  updated_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stat_date),
  KEY idx_uzum_stats_date (stat_date)
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

-- Taxi (Yandex) daily stats and import details
CREATE TABLE IF NOT EXISTS taxi_imports (
  import_date DATE NOT NULL,
  original_filename VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('ok','error') NOT NULL DEFAULT 'ok',
  message VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (import_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxi_trips (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_date DATE NOT NULL,
  trip_time DATETIME NULL,
  application_id VARCHAR(64) NOT NULL DEFAULT '', -- ID заявки
  tariff VARCHAR(100) NOT NULL DEFAULT '',
  delivery_variant VARCHAR(100) NOT NULL DEFAULT '',
  status VARCHAR(100) NOT NULL DEFAULT '',
  order_source VARCHAR(100) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  sender_address VARCHAR(500) NOT NULL DEFAULT '',
  receiver_address VARCHAR(500) NOT NULL DEFAULT '',
  restaurant_name VARCHAR(190) NOT NULL DEFAULT 'Unknown',
  sum_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  sum_waiting DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_cancel_sum DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_paid_cancel TINYINT(1) NOT NULL DEFAULT 0,
  returned_sum DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_returned TINYINT(1) NOT NULL DEFAULT 0,
  is_roundtrip TINYINT(1) NOT NULL DEFAULT 0,
  is_duplicate_3h TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_taxi_trips_date (trip_date),
  KEY idx_taxi_trips_restaurant (restaurant_name, trip_date),
  KEY idx_taxi_trips_receiver (trip_date, receiver_address(150))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxi_daily_stats (
  stat_date DATE NOT NULL,
  restaurant_name VARCHAR(190) NOT NULL,
  trips_count INT UNSIGNED NOT NULL DEFAULT 0,
  sum_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  sum_waiting DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_cancel_count INT UNSIGNED NOT NULL DEFAULT 0,
  paid_cancel_sum DECIMAL(14,2) NOT NULL DEFAULT 0,
  returned_count INT UNSIGNED NOT NULL DEFAULT 0,
  returned_sum DECIMAL(14,2) NOT NULL DEFAULT 0,
  roundtrip_count INT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_3h_count INT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_3h_sum_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stat_date, restaurant_name),
  KEY idx_taxi_stats_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Millennium taxi (manual input)
CREATE TABLE IF NOT EXISTS millennium_taxi_daily_stats (
  stat_date DATE NOT NULL,
  restaurant_name VARCHAR(190) NOT NULL,
  trips_count INT UNSIGNED NOT NULL DEFAULT 0,
  sum_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  updated_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stat_date, restaurant_name),
  KEY idx_millennium_taxi_date (stat_date),
  KEY idx_millennium_taxi_restaurant (restaurant_name, stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery errors (manual)
CREATE TABLE IF NOT EXISTS delivery_errors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  error_date DATE NOT NULL,
  target_type ENUM('operator','restaurant') NOT NULL,
  operator_id BIGINT UNSIGNED NULL,
  restaurant_id BIGINT UNSIGNED NULL,
  restaurant_name VARCHAR(190) NOT NULL DEFAULT '',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  comment TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_delivery_errors_date (error_date),
  KEY idx_delivery_errors_operator (operator_id, error_date),
  KEY idx_delivery_errors_restaurant (restaurant_id, error_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

