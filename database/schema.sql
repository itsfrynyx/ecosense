-- ============================================================================
-- EcoSense — schema + demo data
-- Import once in phpMyAdmin (or: mysql -u root < database/schema.sql).
-- Readings are generated relative to NOW(), so re-importing refreshes the
-- 48-hour window that the dashboard charts expect.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `ecosense`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ecosense`;

SET NAMES utf8mb4;
-- Keep this in sync with APP_TIMEZONE in config/config.php so PHP and MySQL
-- agree on the clock that stamps every reading.
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `alert_rules`;
DROP TABLE IF EXISTS `readings`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `schema_migrations`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'viewer',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fleet
-- ---------------------------------------------------------------------------
CREATE TABLE `devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_uid` VARCHAR(40) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `location` VARCHAR(160) DEFAULT NULL,
  `api_key` CHAR(64) NOT NULL,
  `firmware` VARCHAR(40) NOT NULL DEFAULT 'unknown',
  `status` VARCHAR(20) NOT NULL DEFAULT 'offline',
  `last_seen_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_devices_uid` (`device_uid`),
  UNIQUE KEY `uq_devices_key` (`api_key`),
  KEY `idx_devices_status` (`status`),
  KEY `idx_devices_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Telemetry
-- ---------------------------------------------------------------------------
CREATE TABLE `readings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` INT UNSIGNED NOT NULL,
  `recorded_at` DATETIME NOT NULL,
  `temperature_c` DECIMAL(5,2) DEFAULT NULL,
  `humidity_pct` DECIMAL(5,2) DEFAULT NULL,
  `rain_raw` SMALLINT UNSIGNED DEFAULT NULL,
  `rain_detected` TINYINT UNSIGNED DEFAULT NULL COMMENT '1 = wet, 0 = dry, NULL = no digital reading',
  `pressure_hpa` DECIMAL(7,2) DEFAULT NULL,
  `heat_index_c` DECIMAL(5,2) DEFAULT NULL,
  `lux` DECIMAL(8,2) DEFAULT NULL,
  `battery_pct` TINYINT UNSIGNED DEFAULT NULL,
  `signal_dbm` SMALLINT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_readings_device_time` (`device_id`, `recorded_at`),
  KEY `idx_readings_time` (`recorded_at`),
  CONSTRAINT `fk_readings_device` FOREIGN KEY (`device_id`)
    REFERENCES `devices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Alerting
-- ---------------------------------------------------------------------------
CREATE TABLE `alert_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = applies to every device',
  `metric` VARCHAR(32) NOT NULL COMMENT 'temperature_c | humidity_pct | pressure_hpa | lux | battery_pct',
  `operator` VARCHAR(4) NOT NULL DEFAULT '>' COMMENT '> | < | >= | <=',
  `threshold` DECIMAL(9,2) NOT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'warning' COMMENT 'warning | critical',
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rules_device` (`device_id`),
  KEY `idx_rules_metric` (`metric`),
  CONSTRAINT `fk_rules_device` FOREIGN KEY (`device_id`)
    REFERENCES `devices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `alerts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` INT UNSIGNED DEFAULT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `metric` VARCHAR(32) NOT NULL,
  `value` DECIMAL(9,2) DEFAULT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'warning',
  `message` VARCHAR(255) NOT NULL,
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_time` (`triggered_at`),
  KEY `idx_alerts_ack` (`acknowledged_at`),
  KEY `idx_alerts_device` (`device_id`),
  CONSTRAINT `fk_alerts_rule` FOREIGN KEY (`rule_id`)
    REFERENCES `alert_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_alerts_device` FOREIGN KEY (`device_id`)
    REFERENCES `devices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Global configuration (key/value)
-- ---------------------------------------------------------------------------
CREATE TABLE `settings` (
  `key` VARCHAR(64) NOT NULL,
  `value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schema_migrations` (
  `version` VARCHAR(24) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- Seed data
-- ===========================================================================

-- demo@ecosense.local / Demo@12345
-- operator@ecosense.local / Oper@12345
-- viewer@ecosense.local / View@12345
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `created_at`) VALUES
  ('Demo Administrator', 'demo@ecosense.local',   '$2y$10$1dT53dEzj3xLeAZiTTbp3.PmS9dM683cee.NawF63k2sd81EPEqvW', 'admin',    DATE_SUB(NOW(), INTERVAL 52 DAY)),
  ('Field Operator',     'operator@ecosense.local','$2y$10$pyXSzdFY3IkQfFhiGlEO3.n59ztLMBjJKyPmDdP28kwEdFlPXeLqC', 'operator', DATE_SUB(NOW(), INTERVAL 31 DAY)),
  ('Read-only Viewer',   'viewer@ecosense.local',  '$2y$10$maP/Lw36d23FejVUJNGfwemcVT6YC6WGQ2xI3/gwh0JQiEG.eAHPK', 'viewer',   DATE_SUB(NOW(), INTERVAL 12 DAY));

INSERT INTO `devices` (`device_uid`, `name`, `location`, `api_key`, `firmware`, `status`, `last_seen_at`, `created_at`) VALUES
  ('ECO-TH-001', 'Lab Node 01',       'Engineering Lab — East Window',  'a7f3c19e5b2d4086ac1f7e93b04d62158ea3c7f9051b2d8e46a0c1f7b93d25e1a', 'ecosense-node/1.0.2', 'online',       DATE_SUB(NOW(), INTERVAL 2 SECOND), DATE_SUB(NOW(), INTERVAL 50 DAY)),
  ('ECO-TH-002', 'Lab Node 02',       'Engineering Lab — Doorway',     '3b90d5ea71c64f28b5a0d9e47c182f6b3d40e97a5c2618fbd74e0a95c3d17b62', 'ecosense-node/1.0.2', 'online',       DATE_SUB(NOW(), INTERVAL 4 SECOND), DATE_SUB(NOW(), INTERVAL 47 DAY)),
  ('ECO-TH-003', 'Rooftop Node 01',   'Main Building Rooftop',          'c41a8e07b93d25f16a8c40be7351d92f60a37e4b9d1582cf60e3a74b95d082f3', 'ecosense-node/1.1.0', 'online',       DATE_SUB(NOW(), INTERVAL 2 SECOND), DATE_SUB(NOW(), INTERVAL 38 DAY)),
  ('ECO-TH-004', 'Greenhouse Node 01','Campus Greenhouse — Bay A',      'd72b6f14e08a39c5f1b47d0a9268e3c5b041f7a6c8e20d95b37a41f68e05c2d9', 'ecosense-node/1.0.2', 'maintenance', DATE_SUB(NOW(), INTERVAL 3 HOUR),  DATE_SUB(NOW(), INTERVAL 21 DAY));

INSERT INTO `alert_rules` (`device_id`, `metric`, `operator`, `threshold`, `severity`, `enabled`, `created_at`) VALUES
  (NULL, 'temperature_c', '>',  32.00, 'warning',  1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
  (NULL, 'temperature_c', '>',  38.00, 'critical', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
  (3,    'humidity_pct',  '>',  82.00, 'warning',  1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (NULL, 'pressure_hpa',  '<', 990.00, 'critical', 1, DATE_SUB(NOW(), INTERVAL 30 DAY));

INSERT INTO `settings` (`key`, `value`) VALUES
  ('unit', 'c'),
  ('site_name', 'Main Campus'),
  ('retention_days', '30'),
  ('telemetry_interval_s', '2'),
  ('dashboard_poll_ms', '3000'),
  ('device_online_window_s', '60'),
  ('temp_warn_high', '32.00'),
  ('temp_crit_high', '38.00'),
  ('temp_warn_low', '12.00'),
  ('humidity_warn_high', '80.00'),
  ('humidity_warn_low', '20.00'),
  ('pressure_warn_low', '990.00'),
  ('battery_warn_pct', '25'),
  ('ingest_rate_limit', '60'),
  ('maintenance_mode', '0');

INSERT INTO `schema_migrations` (`version`) VALUES ('1.0.0');

-- ---------------------------------------------------------------------------
-- 48 h backfill: 480 samples per device at 6-minute spacing, with a daily
-- sine curve, per-node offsets and deterministic noise. Temporary digit
-- tables keep this portable (no recursive CTE, no stored procedure).
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `tmp_digit`;
CREATE TEMPORARY TABLE `tmp_digit` (`d` TINYINT UNSIGNED NOT NULL PRIMARY KEY);
INSERT INTO `tmp_digit` (`d`) VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

DROP TEMPORARY TABLE IF EXISTS `tmp_seq`;
CREATE TEMPORARY TABLE `tmp_seq` (`n` INT UNSIGNED NOT NULL PRIMARY KEY);
INSERT INTO `tmp_seq` (`n`)
SELECT a.d + b.d * 10 + c.d * 100
FROM `tmp_digit` a
CROSS JOIN `tmp_digit` b
CROSS JOIN `tmp_digit` c
WHERE (a.d + b.d * 10 + c.d * 100) < 480;

INSERT INTO `readings`
  (`device_id`, `recorded_at`, `temperature_c`, `humidity_pct`, `pressure_hpa`,
   `heat_index_c`, `lux`, `battery_pct`, `signal_dbm`)
SELECT
  m.device_id,
  m.ts,
  m.temp_c,
  m.rh,
  m.pres,
  -- simplified Steadman heat index, close enough for demo data
  ROUND(m.temp_c + 0.055 * GREATEST(0, m.rh - 45) * (m.temp_c - 21) / 5, 2),
  m.lux,
  m.bat,
  m.rssi
FROM (
  SELECT
    a.device_id,
    a.ts,
    ROUND(
      27.20
      + a.device_id * 0.35
      + (a.device_id = 4) * 1.70
      + 3.40 * SIN(2 * PI() * (((HOUR(a.ts) * 3600 + MINUTE(a.ts) * 60 + SECOND(a.ts)) / 86400) - 0.333))
      + 0.45 * SIN(a.n * 1.73 + a.device_id * 2.10)
      + 0.22 * SIN(a.n * 0.41),
      2
    ) AS temp_c,
    LEAST(99.5, GREATEST(8.0, ROUND(
      66.00
      - 11.00 * SIN(2 * PI() * (((HOUR(a.ts) * 3600 + MINUTE(a.ts) * 60 + SECOND(a.ts)) / 86400) - 0.333))
      + 2.40 * SIN(a.n * 1.31 + a.device_id)
      + 1.10 * SIN(a.n * 0.27)
      - (a.device_id = 1) * 8.0
      - (a.device_id = 2) * 4.0
      + (a.device_id = 3) * 6.0
      + (a.device_id = 4) * 14.0,
      2
    ))) AS rh,
    ROUND(
      1008.50
      + 3.20 * SIN(a.n * 0.06 + a.device_id * 0.40)
      + 0.60 * SIN(a.n * 0.90),
      2
    ) AS pres,
    ROUND(
      GREATEST(0, 950 * SIN(PI() * (((HOUR(a.ts) * 3600 + MINUTE(a.ts) * 60 + SECOND(a.ts)) / 86400) - 0.25))
      * (0.25 + 0.75 * (a.device_id = 3) + 0.15 * (a.device_id = 4))) + 6,
      2
    ) AS lux,
    GREATEST(14, 100 - ROUND(26 * (480 - a.n) / 479) - (a.device_id = 4) * 4) AS bat,
    CAST(-48 - CAST(a.device_id AS SIGNED) * 5 - ROUND(6 * SIN(a.n * 0.35 + a.device_id)) AS SIGNED) AS rssi
  FROM (
    SELECT
      d.id AS device_id,
      s.n AS n,
      DATE_SUB(NOW(), INTERVAL (480 - s.n) * 6 MINUTE) AS ts
    FROM `tmp_seq` s
    CROSS JOIN `devices` d
  ) a
) m;

-- Three sub-cadence samples so the newest reading is only seconds old and the
-- fleet reads as online the moment the schema is imported.
INSERT INTO `readings`
  (`device_id`, `recorded_at`, `temperature_c`, `humidity_pct`, `pressure_hpa`,
   `heat_index_c`, `lux`, `battery_pct`, `signal_dbm`)
SELECT
  f.device_id,
  f.ts,
  f.temp_c,
  f.rh,
  f.pres,
  ROUND(f.temp_c + 0.055 * GREATEST(0, f.rh - 45) * (f.temp_c - 21) / 5, 2),
  f.lux,
  f.bat,
  f.rssi
FROM (
  SELECT
    d.id AS device_id,
    f.n AS n,
    DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND) AS ts,
    ROUND(27.20 + d.id * 0.35 + (d.id = 4) * 1.70
      + 3.40 * SIN(2 * PI() * (((HOUR(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 3600
          + MINUTE(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 60
          + SECOND(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND))) / 86400) - 0.333))
      + 0.30 * SIN(480 + f.n * 1.73 + d.id * 2.10), 2) AS temp_c,
    LEAST(99.5, GREATEST(8.0, ROUND(66.00
      - 11.00 * SIN(2 * PI() * (((HOUR(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 3600
          + MINUTE(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 60
          + SECOND(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND))) / 86400) - 0.333))
      + 1.40 * SIN(480 + f.n * 1.31 + d.id)
      - (d.id = 1) * 8.0 - (d.id = 2) * 4.0 + (d.id = 3) * 6.0 + (d.id = 4) * 14.0, 2))) AS rh,
    ROUND(1008.50 + 0.40 * SIN(480 * 0.06 + d.id * 0.40), 2) AS pres,
    ROUND(GREATEST(0, 950 * SIN(PI() * (((HOUR(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 3600
        + MINUTE(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND)) * 60
        + SECOND(DATE_SUB(NOW(), INTERVAL (3 - f.n) * 2 SECOND))) / 86400) - 0.25))
        * (0.25 + 0.75 * (d.id = 3) + 0.15 * (d.id = 4))) + 6, 2) AS lux,
    GREATEST(14, 100 - ROUND(26 / 479) - (d.id = 4) * 4) AS bat,
    CAST(-48 - CAST(d.id AS SIGNED) * 5 - ROUND(6 * SIN(480 * 0.35 + d.id)) AS SIGNED) AS rssi
  FROM `devices` d
  CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2) f
) f;

DROP TEMPORARY TABLE IF EXISTS `tmp_seq`;
DROP TEMPORARY TABLE IF EXISTS `tmp_digit`;

-- ---------------------------------------------------------------------------
-- Recent alert log so the alerts page is not empty on a fresh import
-- ---------------------------------------------------------------------------
INSERT INTO `alerts` (`rule_id`, `device_id`, `metric`, `value`, `severity`, `message`, `triggered_at`, `acknowledged_at`) VALUES
  (1, 3, 'temperature_c', 33.42, 'warning',  'Rooftop Node 01 temperature 33.42 °C above 32.00 °C',        DATE_SUB(NOW(), INTERVAL 19 HOUR), DATE_SUB(NOW(), INTERVAL 18 HOUR)),
  (1, 1, 'temperature_c', 32.86, 'warning',  'Lab Node 01 temperature 32.86 °C above 32.00 °C',            DATE_SUB(NOW(), INTERVAL 11 HOUR), DATE_SUB(NOW(), INTERVAL 10 HOUR)),
  (3, 3, 'humidity_pct',  84.10, 'warning',  'Rooftop Node 01 humidity 84.10 % above 82.00 %',              DATE_SUB(NOW(), INTERVAL 7 HOUR),  NULL),
  (1, 3, 'temperature_c', 34.08, 'warning',  'Rooftop Node 01 temperature 34.08 °C above 32.00 °C',        DATE_SUB(NOW(), INTERVAL 5 HOUR),  NULL),
  (2, 4, 'temperature_c', 38.64, 'critical', 'Greenhouse Node 01 temperature 38.64 °C above 38.00 °C',      DATE_SUB(NOW(), INTERVAL 3 HOUR),  NULL),
  (4, 2, 'pressure_hpa',  988.40,'critical', 'Lab Node 02 pressure 988.40 hPa below 990.00 hPa',          DATE_SUB(NOW(), INTERVAL 52 MINUTE),NULL);

-- Retention housekeeping, if you schedule it (Windows Task Scheduler / cron):
--   DELETE FROM readings WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
