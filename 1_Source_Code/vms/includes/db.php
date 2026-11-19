<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/db.php
 * Purpose : Database access layer (PDO + prepared statements).
 *           Every query in the project goes through these helpers, so
 *           user input is always bound as a parameter (no SQL injection).
 * ---------------------------------------------------------------------
 */

/**
 * Returns one shared PDO connection (created on first use).
 * If MySQL is not reachable, a friendly setup page is shown instead
 * of a raw PHP error.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
            ]);

            // Keep MySQL's NOW()/CURDATE() in the same timezone as PHP
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            db_setup_error($e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Stops the request with a readable "database not ready" page.
 */
function db_setup_error(string $detail): void
{
    http_response_code(500);
    $safeDetail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database not ready</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#1F2D29;line-height:1.6}';
    echo 'code{background:#EEF2EE;padding:2px 6px;border-radius:4px}h1{color:#16503F}</style></head><body>';
    echo '<h1>The database is not ready yet</h1>';
    echo '<p>' . APP_NAME . ' could not connect to MySQL. Please check these three things:</p><ol>';
    echo '<li>Apache and MySQL are running in the XAMPP control panel.</li>';
    echo '<li>The file <code>database/vaccination_db.sql</code> has been imported in phpMyAdmin.</li>';
    echo '<li>The username and password in <code>config/config.php</code> are correct.</li></ol>';
    if (APP_DEBUG) {
        echo '<p><strong>Technical detail:</strong> ' . $safeDetail . '</p>';
    }
    echo '</body></html>';
    exit;
}

/**
 * Runs a prepared statement and returns the PDOStatement.
 * Integer values are bound as integers (needed for LIMIT/OFFSET).
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        // Positional (?) placeholders are 1-based; named ones keep their name
        $param = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
        $type  = is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue($param, $value, $type);
    }

    $stmt->execute();
    return $stmt;
}

/** Returns all rows of a query. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** Returns the first row of a query, or null when nothing matched. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Returns the first column of the first row (handy for COUNT(*)). */
function db_value(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/** Runs INSERT/UPDATE/DELETE and returns the number of affected rows. */
function db_exec(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/** Runs an INSERT and returns the new auto-increment id. */
function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

/**
 * Builds "?, ?, ?" for an IN (...) clause with the given number of items.
 */
function db_placeholders(int $count): string
{
    return implode(', ', array_fill(0, max(1, $count), '?'));
}
