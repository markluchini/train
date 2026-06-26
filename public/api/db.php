<?php
// Database connection helper using PDO

$config_file = '/var/www/.train.drluchini.com.php';
if (file_exists($config_file)) {
    require_once $config_file;
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'training');
if (!defined('DB_USER')) define('DB_USER', 'training');
if (!defined('DB_PASS')) define('DB_PASS', '');

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // Return clean error response if DB connection fails
            header("Content-Type: application/json; charset=UTF-8");
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "data" => [
                    "message" => "Database connection failure: " . $e->getMessage()
                ]
            ]);
            exit;
        }
    }
    return $pdo;
}
