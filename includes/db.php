<?php
/**
 * Lazy PDO singleton. Every entry point returns null/false instead of
 * throwing, so a stopped MySQL service can never produce a fatal error.
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/config.php';
}

function db()
{
    static $pdo   = null;
    static $tried = false;

    if ($pdo !== null) {
        return $pdo;
    }
    if ($tried || (defined('OFFLINE_MODE') && OFFLINE_MODE)) {
        return null;
    }
    $tried = true;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // Store DATETIME values in the same local timezone used by PHP.
        $offset = (new DateTimeImmutable())->format('P');
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
    } catch (PDOException $e) {
        $pdo = null;
        db_set_error($e->getMessage());
    }

    return $pdo;
}

function db_set_error($message)
{
    $GLOBALS['ecosense_db_error'] = (string) $message;
}

function db_error()
{
    return isset($GLOBALS['ecosense_db_error']) ? (string) $GLOBALS['ecosense_db_error'] : '';
}

function db_available()
{
    return db() instanceof PDO;
}

/** Prepared-statement helper: returns the statement, or false on failure. */
function db_query($sql, array $params = [])
{
    $pdo = db();
    if ($pdo === null) {
        return false;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        db_set_error($e->getMessage());
        return false;
    }
}

function db_all($sql, array $params = [])
{
    $stmt = db_query($sql, $params);
    return $stmt === false ? [] : $stmt->fetchAll();
}

function db_one($sql, array $params = [])
{
    $stmt = db_query($sql, $params);
    if ($stmt === false) {
        return null;
    }
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_val($sql, array $params = [], $default = null)
{
    $row = db_one($sql, $params);
    if ($row === null) {
        return $default;
    }
    $first = reset($row);
    return $first === false || $first === null ? $default : $first;
}

function db_exec($sql, array $params = [])
{
    $stmt = db_query($sql, $params);
    if ($stmt === false) {
        return false;
    }
    return $stmt->rowCount();
}

/** Single scalar from a key/value table, e.g. setting('unit', 'c'). */
function setting($key, $default = null)
{
    if (!db_available()) {
        return $default;
    }
    $value = db_val('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1', [$key]);
    return $value === null ? $default : $value;
}
