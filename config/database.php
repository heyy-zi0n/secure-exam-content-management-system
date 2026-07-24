<?php
/**
 * Database Connection Manager (PDO)
 */

require_once __DIR__ . '/constants.php';

class Database {
    private static ?PDO $instance = null;

    private string $host = '127.0.0.1';
    private string $db   = 'lasu_exam_enterprise_db';
    private string $user = 'root';
    private string $pass = '';
    private string $port = '3306';
    private string $charset = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dbInstance = new self();
            self::$instance = $dbInstance->connect();
        }
        return self::$instance;
    }

    private function connect(): PDO {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci"
        ];

        try {
            return new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // Log connection error locally
            $logFile = LOG_PATH . '/db_errors.log';
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] Connection Failed: " . $e->getMessage() . PHP_EOL;
            @file_put_contents($logFile, $errorMessage, FILE_APPEND);

            throw new PDOException("Database Connection Error. Please verify database server settings.", (int)$e->getCode());
        }
    }
}