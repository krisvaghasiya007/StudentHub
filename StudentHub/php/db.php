<?php
/* =====================================================
   MySQL connection (MySQLi) + prepared-statement helpers
   ===================================================== */
require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('StudentHub DB connection failed: ' . $e->getMessage());
        throw new RuntimeException('Could not connect to the database. Please try again later.');
    }
    return $conn;
}

/** Prepare + bind + execute. Returns the executed statement. */
function db_run(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** SELECT many rows -> array of assoc arrays */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** SELECT one row -> assoc array or null */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

/** SELECT single value */
function db_value(string $sql, string $types = '', array $params = [])
{
    $row = db_one($sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

/** INSERT / UPDATE / DELETE -> affected rows */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_run($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_insert_id(): int
{
    return (int) db()->insert_id;
}
