<?php 

namespace Models;

use Database;
//require_once BASE_PATH.'/config/db.php';

class Usuarios {
    private $conn;
    private $table_name = "usuarios";
    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
        //echo "Conexion exitosa a la base de datos.";
    }

    public function getAllUsers(){
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUserByUsername($usuario) {
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1";
        $query = $this->conn->prepare($sql);
        $query->bindParam(":usuario", $usuario);
        $query->execute();
        return $query->fetch();
    }
    

}


?>