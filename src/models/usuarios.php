<?php 

namespace Models;

use Database;
use PDO;
use PDOException;
//require_once BASE_PATH.'/config/db.php';

class Usuarios {
    private PDO $conn;
    private string $table_name = "usuarios";
    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
        //echo "Conexion exitosa a la base de datos.";
    }

    public function getAllUsers(): array {
        try {
            $sql = "SELECT * FROM {$this->table_name}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getAllUsers: " . $e->getMessage());
            return [];
        }
    }

    public function getUserByUsername(string $usuario): ?array {
        try {
            $sql = "SELECT 
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro
                    FROM usuarios u
                    INNER JOIN roles r ON u.id_rol = r.id
                    WHERE u.usuario = :usuario
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;

        } catch (PDOException $e) {
            error_log("Error en getUserByUsername: " . $e->getMessage());
            return null;
        }
    }
}


?>