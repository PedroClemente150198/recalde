<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Usuarios {
    private PDO $conn;
    private string $table_name = "usuarios";
    private ?string $lastError = null;
    private array $estadosPermitidos = ["activo", "inactivo"];

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getAllUsers(): array {
        try {
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro
                    FROM {$this->table_name} u
                    INNER JOIN roles r ON u.id_rol = r.id
                    ORDER BY u.id DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Usuarios::getAllUsers => " . $e->getMessage());
            return [];
        }
    }

    public function getRoles(): array {
        try {
            $sql = "SELECT id, rol FROM roles ORDER BY id ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Usuarios::getRoles => " . $e->getMessage());
            return [];
        }
    }

    public function getUserById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        try {
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro
                    FROM {$this->table_name} u
                    INNER JOIN roles r ON u.id_rol = r.id
                    WHERE u.id = :id
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error Usuarios::getUserById => " . $e->getMessage());
            return null;
        }
    }

    public function getUserByUsername(string $usuario): ?array {
        try {
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro
                    FROM {$this->table_name} u
                    INNER JOIN roles r ON u.id_rol = r.id
                    WHERE u.usuario = :usuario
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error Usuarios::getUserByUsername => " . $e->getMessage());
            return null;
        }
    }

    public function createUser(
        int $idRol,
        string $usuario,
        string $correo,
        string $contrasena,
        string $estado = "activo"
    ): ?int {
        $this->lastError = null;

        $usuario = trim($usuario);
        $correo = trim($correo);
        $contrasena = trim($contrasena);
        $estado = $this->normalizeEstado($estado);

        if ($idRol <= 0 || !$this->rolExists($idRol)) {
            $this->lastError = "Rol inválido para crear el usuario.";
            return null;
        }

        if ($usuario === "") {
            $this->lastError = "El nombre de usuario es obligatorio.";
            return null;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Correo electrónico inválido.";
            return null;
        }

        if ($contrasena === "") {
            $this->lastError = "La contraseña es obligatoria.";
            return null;
        }

        if ($this->usuarioExists($usuario)) {
            $this->lastError = "El nombre de usuario ya está en uso.";
            return null;
        }

        if ($this->correoExists($correo)) {
            $this->lastError = "El correo electrónico ya está en uso.";
            return null;
        }

        try {
            $sql = "INSERT INTO {$this->table_name}
                        (id_rol, usuario, correo, contrasena, estado)
                    VALUES
                        (:id_rol, :usuario, :correo, :contrasena, :estado)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_rol", $idRol, PDO::PARAM_INT);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
            $stmt->bindParam(":correo", $correo, PDO::PARAM_STR);
            $stmt->bindParam(":contrasena", $contrasena, PDO::PARAM_STR);
            $stmt->bindParam(":estado", $estado, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error Usuarios::createUser => " . $e->getMessage());
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "Usuario o correo ya registrado.";
            } else {
                $this->lastError = "No se pudo crear el usuario.";
            }
            return null;
        }
    }

    public function updateUser(
        int $idUsuario,
        string $usuario,
        string $correo,
        ?string $contrasena = null,
        ?int $idRol = null,
        ?string $estado = null
    ): bool {
        $this->lastError = null;

        $usuario = trim($usuario);
        $correo = trim($correo);
        $contrasena = $contrasena !== null ? trim($contrasena) : null;
        $estadoNormalizado = $estado !== null ? $this->normalizeEstado($estado) : null;

        if ($idUsuario <= 0) {
            $this->lastError = "ID de usuario inválido.";
            return false;
        }

        if ($usuario === "") {
            $this->lastError = "El nombre de usuario es obligatorio.";
            return false;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Correo electrónico inválido.";
            return false;
        }

        if ($idRol !== null && ($idRol <= 0 || !$this->rolExists($idRol))) {
            $this->lastError = "Rol inválido.";
            return false;
        }

        if ($this->usuarioExists($usuario, $idUsuario)) {
            $this->lastError = "El nombre de usuario ya está en uso.";
            return false;
        }

        if ($this->correoExists($correo, $idUsuario)) {
            $this->lastError = "El correo electrónico ya está en uso.";
            return false;
        }

        $user = $this->getUserById($idUsuario);
        if (!$user) {
            $this->lastError = "Usuario no encontrado.";
            return false;
        }

        try {
            $fields = [
                "usuario = :usuario",
                "correo = :correo"
            ];

            if ($contrasena !== null && $contrasena !== "") {
                $fields[] = "contrasena = :contrasena";
            }

            if ($idRol !== null) {
                $fields[] = "id_rol = :id_rol";
            }

            if ($estadoNormalizado !== null) {
                $fields[] = "estado = :estado";
            }

            $sql = "UPDATE {$this->table_name}
                    SET " . implode(", ", $fields) . "
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
            $stmt->bindParam(":correo", $correo, PDO::PARAM_STR);

            if ($contrasena !== null && $contrasena !== "") {
                $stmt->bindParam(":contrasena", $contrasena, PDO::PARAM_STR);
            }

            if ($idRol !== null) {
                $stmt->bindParam(":id_rol", $idRol, PDO::PARAM_INT);
            }

            if ($estadoNormalizado !== null) {
                $stmt->bindParam(":estado", $estadoNormalizado, PDO::PARAM_STR);
            }

            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error Usuarios::updateUser => " . $e->getMessage());
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "Usuario o correo ya registrado.";
            } else {
                $this->lastError = "No se pudo actualizar el usuario.";
            }
            return false;
        }
    }

    public function deleteUser(int $idUsuario): bool {
        $this->lastError = null;

        if ($idUsuario <= 0) {
            $this->lastError = "ID de usuario inválido.";
            return false;
        }

        try {
            $sql = "UPDATE {$this->table_name}
                    SET estado = 'inactivo'
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return true;
            }

            $user = $this->getUserById($idUsuario);
            if (!$user) {
                $this->lastError = "Usuario no encontrado.";
                return false;
            }

            return strtolower((string) ($user["estado"] ?? "")) === "inactivo";
        } catch (PDOException $e) {
            error_log("Error Usuarios::deleteUser => " . $e->getMessage());
            $this->lastError = "No se pudo desactivar el usuario.";
            return false;
        }
    }

    public function isLastActiveAdmin(int $idUsuario): bool {
        if ($idUsuario <= 0) {
            return false;
        }

        try {
            $sqlTarget = "SELECT
                            u.estado,
                            r.rol
                          FROM {$this->table_name} u
                          INNER JOIN roles r ON u.id_rol = r.id
                          WHERE u.id = :id
                          LIMIT 1";

            $stmtTarget = $this->conn->prepare($sqlTarget);
            $stmtTarget->bindParam(":id", $idUsuario, PDO::PARAM_INT);
            $stmtTarget->execute();
            $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                return false;
            }

            $rol = strtolower(trim((string) ($target["rol"] ?? "")));
            $estado = strtolower(trim((string) ($target["estado"] ?? "")));

            if ($rol !== "admin" || $estado !== "activo") {
                return false;
            }

            $sqlCount = "SELECT COUNT(*) AS total
                         FROM {$this->table_name} u
                         INNER JOIN roles r ON u.id_rol = r.id
                         WHERE LOWER(r.rol) = 'admin'
                           AND u.estado = 'activo'";

            $stmtCount = $this->conn->prepare($sqlCount);
            $stmtCount->execute();
            $row = $stmtCount->fetch(PDO::FETCH_ASSOC);
            $activos = (int) ($row["total"] ?? 0);

            return $activos <= 1;
        } catch (PDOException $e) {
            error_log("Error Usuarios::isLastActiveAdmin => " . $e->getMessage());
            return false;
        }
    }

    public function getRoleNameById(int $idRol): ?string {
        if ($idRol <= 0) {
            return null;
        }

        try {
            $sql = "SELECT rol FROM roles WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idRol, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return strtolower(trim((string) ($row["rol"] ?? "")));
        } catch (PDOException $e) {
            error_log("Error Usuarios::getRoleNameById => " . $e->getMessage());
            return null;
        }
    }

    private function rolExists(int $idRol): bool {
        $sql = "SELECT id FROM roles WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function usuarioExists(string $usuario, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM {$this->table_name} WHERE usuario = :usuario";
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindParam(":exclude_id", $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function correoExists(string $correo, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM {$this->table_name} WHERE correo = :correo";
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":correo", $correo, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindParam(":exclude_id", $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeEstado(?string $estado): string {
        $estado = strtolower(trim((string) $estado));
        if (!in_array($estado, $this->estadosPermitidos, true)) {
            return "activo";
        }
        return $estado;
    }
}

?>
