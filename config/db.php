<?php 

class Database {
    private $host = "localhost";
    private $dbName = "RECALDE";
    private $userName = "root";
    private $password = "12345678";
    private $charset = "utf8mb4";

    // almacenar la conexión
    private $pdo;

    public function connect() {
        //echo "<p>Intentando conectar a la base de datos...</p>";
        if ($this->pdo == null) {
            try{
                // DSN = cadena de conexión
                $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset={$this->charset}";
                // opciones de PDO (seguridad y rendimiento)
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                // crear una nueva instancia de PDO
                $this->pdo = new PDO($dsn, $this->userName, $this->password, $options);
                //echo "Conexion exitosa a la base de datos.";
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return $this->pdo;
        //$conn = new mysqli($this->host, $this->userName, $this->password, $this->db);
        //if ($conn->connect_error) {
        //    die("Connection failed: " . $conn->connect_error);
        //}
        /*$conn = new PDO("mysql:host={$this->host};dbname={$this->dbName}", $this->userName, $this->password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p>Conexion exitosa a la base de datos.</p>";
        $query = $conn->query("SELECT * FROM roles");
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        if (!$results) {
            echo "No se encontraron roles.";
            return;
        }
        foreach ($results as $row) {
            echo "<h1>Role ID: " . $row['id'] . " Rol: " . $row['rol'] . "<br></h1>";
        }
        */
    }
    public function disconnect() {
        $this->pdo = null;
    }
}
?>