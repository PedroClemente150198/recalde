<?php

class Database {
    private string $host;
    private string $dbName;
    private string $userName;
    private string $password;
    private string $charset;

    private ?PDO $pdo = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->dbName = getenv('DB_NAME') ?: 'RECALDE';
        $this->userName = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '12345678';
        $this->charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    }

    public function connect(): PDO {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $this->pdo = new PDO($dsn, $this->userName, $this->password, $options);
            } catch (PDOException $e) {
                error_log('Error DB connection: ' . $e->getMessage());
                throw new PDOException('No se pudo establecer la conexión a la base de datos.', (int) $e->getCode());
            }
        }

        return $this->pdo;
    }

    public function disconnect(): void {
        $this->pdo = null;
    }
}
