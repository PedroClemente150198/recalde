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
    private ?bool $supportsForcedPasswordChangeColumn = null;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getAllUsers(): array {
        try {
            $forcePasswordSelect = $this->forcePasswordSelect('u');
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro,
                        {$forcePasswordSelect} AS debe_cambiar_contrasena
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
            $forcePasswordSelect = $this->forcePasswordSelect('u');
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro,
                        {$forcePasswordSelect} AS debe_cambiar_contrasena
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
            $forcePasswordSelect = $this->forcePasswordSelect('u');
            $sql = "SELECT
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.id_rol,
                        r.rol AS nombre_rol,
                        u.estado,
                        u.fecha_registro,
                        {$forcePasswordSelect} AS debe_cambiar_contrasena
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

        $hashedPassword = password_hash($contrasena, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            $this->lastError = "No se pudo procesar la contraseña.";
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
            if ($this->supportsForcedPasswordChange()) {
                $sql = "INSERT INTO {$this->table_name}
                            (id_rol, usuario, correo, contrasena, estado, debe_cambiar_contrasena)
                        VALUES
                            (:id_rol, :usuario, :correo, :contrasena, :estado, 0)";
            } else {
                $sql = "INSERT INTO {$this->table_name}
                            (id_rol, usuario, correo, contrasena, estado)
                        VALUES
                            (:id_rol, :usuario, :correo, :contrasena, :estado)";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_rol", $idRol, PDO::PARAM_INT);
            $stmt->bindParam(":usuario", $usuario, PDO::PARAM_STR);
            $stmt->bindParam(":correo", $correo, PDO::PARAM_STR);
            $stmt->bindParam(":contrasena", $hashedPassword, PDO::PARAM_STR);
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
        $hashedPassword = null;

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

        if ($contrasena !== null && $contrasena !== "") {
            $hashedPassword = password_hash($contrasena, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                $this->lastError = "No se pudo procesar la contraseña.";
                return false;
            }
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

            if ($hashedPassword !== null) {
                $fields[] = "contrasena = :contrasena";
                if ($this->supportsForcedPasswordChange()) {
                    $fields[] = "debe_cambiar_contrasena = 0";
                }
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

            if ($hashedPassword !== null) {
                $stmt->bindParam(":contrasena", $hashedPassword, PDO::PARAM_STR);
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

    public function verifyPassword(string $plainPassword, string $storedPassword): bool {
        if ($plainPassword === '' || $storedPassword === '') {
            return false;
        }

        if ($this->isPasswordHash($storedPassword)) {
            return password_verify($plainPassword, $storedPassword);
        }

        return hash_equals($storedPassword, $plainPassword);
    }

    public function needsPasswordMigration(string $storedPassword): bool {
        if ($storedPassword === '') {
            return false;
        }

        return !$this->isPasswordHash($storedPassword);
    }

    public function passwordNeedsRehash(string $storedPassword): bool {
        if (!$this->isPasswordHash($storedPassword)) {
            return false;
        }

        return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
    }

    public function upgradePasswordHash(int $idUsuario, string $plainPassword): bool {
        if ($idUsuario <= 0 || $plainPassword === '') {
            return false;
        }

        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            return false;
        }

        try {
            $sql = "UPDATE {$this->table_name}
                    SET contrasena = :contrasena
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':contrasena', $newHash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error Usuarios::upgradePasswordHash => " . $e->getMessage());
            return false;
        }
    }

    public function forceResetPassword(int $idUsuario, ?string $temporaryPassword = null): ?array {
        $this->lastError = null;

        if ($idUsuario <= 0) {
            $this->lastError = "ID de usuario inválido.";
            return null;
        }

        $user = $this->getUserById($idUsuario);
        if (!$user) {
            $this->lastError = "Usuario no encontrado.";
            return null;
        }

        $plainPassword = trim((string) ($temporaryPassword ?? ''));
        if ($plainPassword === '') {
            $plainPassword = $this->generateTemporaryPassword();
        }

        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            $this->lastError = "No se pudo generar la contraseña temporal.";
            return null;
        }

        try {
            $sql = "UPDATE {$this->table_name}
                    SET contrasena = :contrasena";

            if ($this->supportsForcedPasswordChange()) {
                $sql .= ", debe_cambiar_contrasena = 1";
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':contrasena', $newHash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'id' => (int) ($user['id'] ?? $idUsuario),
                'usuario' => (string) ($user['usuario'] ?? ''),
                'temporaryPassword' => $plainPassword,
                'forceChangeEnabled' => $this->supportsForcedPasswordChange()
            ];
        } catch (PDOException $e) {
            error_log("Error Usuarios::forceResetPassword => " . $e->getMessage());
            $this->lastError = "No se pudo resetear la contraseña del usuario.";
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

    private function isPasswordHash(string $passwordValue): bool {
        $info = password_get_info($passwordValue);
        return (int) ($info['algo'] ?? 0) !== 0;
    }

    private function supportsForcedPasswordChange(): bool {
        if ($this->supportsForcedPasswordChangeColumn !== null) {
            return $this->supportsForcedPasswordChangeColumn;
        }

        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM {$this->table_name} LIKE 'debe_cambiar_contrasena'");
            $this->supportsForcedPasswordChangeColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Usuarios::supportsForcedPasswordChange => " . $e->getMessage());
            $this->supportsForcedPasswordChangeColumn = false;
        }

        return $this->supportsForcedPasswordChangeColumn;
    }

    private function forcePasswordSelect(string $tableAlias = 'u'): string {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) ?: 'u';
        return $this->supportsForcedPasswordChange() ? "{$alias}.debe_cambiar_contrasena" : "0";
    }

    private function generateTemporaryPassword(int $length = 12): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$_-';
        $maxIndex = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $maxIndex)];
        }

        return $result;
    }
}

?>
