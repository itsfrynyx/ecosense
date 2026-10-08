-- Run ONCE against the database configured in config/config.php.
-- If rain_raw already exists, do not rerun this statement.
ALTER TABLE readings ADD COLUMN rain_raw SMALLINT UNSIGNED NULL AFTER humidity_pct;
