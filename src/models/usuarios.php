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
    private ?bool $passwordResetTableReady = null;

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

    public function getUserByEmail(string $correo): ?array {
        $correo = trim($correo);
        if ($correo === '') {
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
                    WHERE u.correo = :correo
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":correo", $correo, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error Usuarios::getUserByEmail => " . $e->getMessage());
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

    public function createPasswordResetToken(int $idUsuario, int $ttlSeconds = 3600): ?array {
        $this->lastError = null;

        if ($idUsuario <= 0) {
            $this->lastError = "ID de usuario inválido.";
            return null;
        }

        if (!$this->ensurePasswordResetTable()) {
            $this->lastError = $this->lastError ?? "No se pudo preparar el sistema de recuperación.";
            return null;
        }

        $ttlSeconds = max(300, min($ttlSeconds, 86400));
        $token = $this->generateSecureToken(32);
        $tokenHash = hash('sha256', $token);
        $expiresAtUtc = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        try {
            $this->conn->beginTransaction();

            $sqlCleanup = "DELETE FROM password_resets
                           WHERE id_usuario = :id_usuario
                              OR expires_at <= UTC_TIMESTAMP()
                              OR used_at IS NOT NULL";
            $stmtCleanup = $this->conn->prepare($sqlCleanup);
            $stmtCleanup->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtCleanup->execute();

            $sqlInsert = "INSERT INTO password_resets
                            (id_usuario, token_hash, expires_at)
                          VALUES
                            (:id_usuario, :token_hash, :expires_at)";
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $stmtInsert->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtInsert->bindParam(':token_hash', $tokenHash, PDO::PARAM_STR);
            $stmtInsert->bindParam(':expires_at', $expiresAtUtc, PDO::PARAM_STR);
            $stmtInsert->execute();

            $this->conn->commit();

            return [
                'token' => $token,
                'expires_at' => $expiresAtUtc,
                'expires_in' => $ttlSeconds,
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Usuarios::createPasswordResetToken => " . $e->getMessage());
            $this->lastError = "No se pudo generar el enlace de recuperación.";
            return null;
        }
    }

    public function getUserByPasswordResetToken(string $token): ?array {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (!$this->ensurePasswordResetTable()) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        try {
            $sql = "SELECT
                        pr.id AS reset_id,
                        pr.id_usuario,
                        pr.expires_at,
                        u.id AS id,
                        u.id AS user_id,
                        u.usuario,
                        u.correo,
                        u.estado
                    FROM password_resets pr
                    INNER JOIN {$this->table_name} u ON u.id = pr.id_usuario
                    WHERE pr.token_hash = :token_hash
                      AND pr.used_at IS NULL
                      AND pr.expires_at > UTC_TIMESTAMP()
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':token_hash', $tokenHash, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("Error Usuarios::getUserByPasswordResetToken => " . $e->getMessage());
            return null;
        }
    }

    public function resetPasswordWithToken(string $token, string $newPassword): bool {
        $this->lastError = null;

        $token = trim($token);
        $newPassword = trim($newPassword);

        if ($token === '') {
            $this->lastError = "Token de recuperación inválido.";
            return false;
        }

        if (strlen($newPassword) < 8) {
            $this->lastError = "La nueva contraseña debe tener al menos 8 caracteres.";
            return false;
        }

        if (!$this->ensurePasswordResetTable()) {
            $this->lastError = $this->lastError ?? "No se pudo preparar el sistema de recuperación.";
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            $this->lastError = "No se pudo procesar la nueva contraseña.";
            return false;
        }

        $tokenHash = hash('sha256', $token);

        try {
            $this->conn->beginTransaction();

            $sqlToken = "SELECT
                            pr.id AS reset_id,
                            pr.id_usuario,
                            u.estado
                        FROM password_resets pr
                        INNER JOIN {$this->table_name} u ON u.id = pr.id_usuario
                        WHERE pr.token_hash = :token_hash
                          AND pr.used_at IS NULL
                          AND pr.expires_at > UTC_TIMESTAMP()
                        LIMIT 1
                        FOR UPDATE";
            $stmtToken = $this->conn->prepare($sqlToken);
            $stmtToken->bindParam(':token_hash', $tokenHash, PDO::PARAM_STR);
            $stmtToken->execute();
            $row = $stmtToken->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                $this->lastError = "El enlace de recuperación es inválido o expiró.";
                return false;
            }

            $estado = strtolower(trim((string) ($row['estado'] ?? '')));
            if ($estado !== 'activo') {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                $this->lastError = "Tu usuario está inactivo. Contacta al administrador.";
                return false;
            }

            $idUsuario = (int) ($row['id_usuario'] ?? 0);
            $idReset = (int) ($row['reset_id'] ?? 0);

            if ($idUsuario <= 0 || $idReset <= 0) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                $this->lastError = "Token de recuperación inválido.";
                return false;
            }

            $sqlUpdateUser = "UPDATE {$this->table_name}
                              SET contrasena = :contrasena";
            if ($this->supportsForcedPasswordChange()) {
                $sqlUpdateUser .= ", debe_cambiar_contrasena = 0";
            }
            $sqlUpdateUser .= " WHERE id = :id";

            $stmtUpdateUser = $this->conn->prepare($sqlUpdateUser);
            $stmtUpdateUser->bindParam(':contrasena', $hashedPassword, PDO::PARAM_STR);
            $stmtUpdateUser->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmtUpdateUser->execute();

            $sqlUseToken = "UPDATE password_resets
                            SET used_at = UTC_TIMESTAMP()
                            WHERE id = :id";
            $stmtUseToken = $this->conn->prepare($sqlUseToken);
            $stmtUseToken->bindParam(':id', $idReset, PDO::PARAM_INT);
            $stmtUseToken->execute();

            $sqlCleanup = "DELETE FROM password_resets
                           WHERE id_usuario = :id_usuario
                             AND id <> :id";
            $stmtCleanup = $this->conn->prepare($sqlCleanup);
            $stmtCleanup->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtCleanup->bindParam(':id', $idReset, PDO::PARAM_INT);
            $stmtCleanup->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Usuarios::resetPasswordWithToken => " . $e->getMessage());
            $this->lastError = "No se pudo actualizar la contraseña.";
            return false;
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

    private function ensurePasswordResetTable(): bool {
        if ($this->passwordResetTableReady === true) {
            return true;
        }

        $idColumnType = $this->getUserIdColumnType();
        $idColumnType = $this->sanitizeSqlTypeDefinition($idColumnType);
        if ($idColumnType === '') {
            $idColumnType = 'INT UNSIGNED';
        }

        $sqlWithForeignKey = "CREATE TABLE IF NOT EXISTS password_resets (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    id_usuario {$idColumnType} NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_resets_usuario (id_usuario),
                    INDEX idx_password_resets_expires (expires_at),
                    CONSTRAINT fk_password_resets_usuario
                        FOREIGN KEY (id_usuario) REFERENCES {$this->table_name} (id)
                        ON UPDATE CASCADE
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $sqlWithoutForeignKey = "CREATE TABLE IF NOT EXISTS password_resets (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    id_usuario {$idColumnType} NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_resets_usuario (id_usuario),
                    INDEX idx_password_resets_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->conn->exec($sqlWithForeignKey);
        } catch (PDOException $e) {
            error_log("Error Usuarios::ensurePasswordResetTable FK => " . $e->getMessage());
            try {
                $this->conn->exec($sqlWithoutForeignKey);
            } catch (PDOException $fallbackException) {
                error_log("Error Usuarios::ensurePasswordResetTable => " . $fallbackException->getMessage());
                $this->passwordResetTableReady = false;
                $this->lastError = "No se pudo preparar la tabla de recuperación de contraseñas.";
                return false;
            }
        }

        $this->passwordResetTableReady = true;
        return true;
    }

    private function getUserIdColumnType(): string {
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM {$this->table_name} LIKE 'id'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return trim((string) ($row['Type'] ?? ''));
        } catch (PDOException $e) {
            error_log("Error Usuarios::getUserIdColumnType => " . $e->getMessage());
            return '';
        }
    }

    private function sanitizeSqlTypeDefinition(string $definition): string {
        $definition = strtolower(trim($definition));
        $definition = preg_replace('/[^a-z0-9\(\)\s,_]/', '', $definition);
        $definition = preg_replace('/\s+/', ' ', (string) $definition);
        return trim((string) $definition);
    }

    private function forcePasswordSelect(string $tableAlias = 'u'): string {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) ?: 'u';
        return $this->supportsForcedPasswordChange() ? "{$alias}.debe_cambiar_contrasena" : "0";
    }

    private function generateSecureToken(int $bytes = 32): string {
        $bytes = max(16, min($bytes, 64));
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('token', true) . microtime(true));
        }
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
