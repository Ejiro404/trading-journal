<?php
declare(strict_types=1);

/*
 * Database configuration.
 *
 * Local XAMPP defaults are used only outside production.
 * In production, provide database credentials through environment variables.
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$appEnv = strtolower(getenv('APP_ENV') ?: 'development');
$isProduction = $appEnv === 'production';

$DB_HOST = getenv('DB_HOST') ?: ($isProduction ? '' : '127.0.0.1');
$DB_NAME = getenv('DB_NAME') ?: ($isProduction ? '' : 'trading_journal');
$DB_USER = getenv('DB_USER') ?: ($isProduction ? '' : 'root');

$DB_PASS = getenv('DB_PASS');
if ($DB_PASS === false) {
    $DB_PASS = '';
}

if (
    $DB_HOST === '' ||
    $DB_NAME === '' ||
    $DB_USER === '' ||
    ($isProduction && ($DB_PASS === '' || strtolower($DB_USER) === 'root'))
) {
    error_log('Database configuration is incomplete or unsafe for production.');
    http_response_code(500);
    exit('Application configuration error.');
}

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}
