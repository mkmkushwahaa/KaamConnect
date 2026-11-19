<?php
/**
 * KaamConnect - Database Configuration & Connection Handler
 * Technology: PHP 8+ PDO with MySQL/MariaDB
 * Compatible with XAMPP defaults (localhost, user: root, password: '')
 */

declare(strict_types=1);

// Prevent direct script access if needed
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'kaamconnect_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');
/**
 * Returns a shared PDO instance with hardened connection attributes
 *
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database doesn't exist yet, attempt friendly message
            if ($e->getCode() === 1049) {
                die('<div style="font-family:Segoe UI,sans-serif;max-width:600px;margin:50px auto;padding:24px;border:1px solid #fed7aa;background:#fff7ed;border-radius:12px;color:#9a3412;">
                    <h2 style="margin-top:0;">Database Not Found</h2>
                    <p>The database <strong>kaamconnect_db</strong> does not exist yet.</p>
                    <p>Please import <code>database/kaamconnect.sql</code> via phpMyAdmin or run the database setup script.</p>
                </div>');
            }
            throw new PDOException("Database connection error: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    return $pdo;
}

/**
 * Helper: Execute a parameterized SELECT query and fetch all rows
 */
function dbFetchAll(string $sql, array $params = []): array {
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Helper: Execute a parameterized SELECT query and fetch a single row
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Helper: Execute an INSERT/UPDATE/DELETE query and return affected row count
 */
function dbExecute(string $sql, array $params = []): int {
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Helper: Execute an INSERT query and return the last inserted ID
 */
function dbInsert(string $sql, array $params = []): string {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}
