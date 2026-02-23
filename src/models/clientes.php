<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Clientes
{
    private PDO $conn;
    private ?string $lastError = null;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getClientes(): array
    {
        try {
            $sql = "SELECT
                        c.id,
                        c.nombre,
                        c.apellido,
                        c.cedula,
                        c.telefono,
                        c.direccion,
                        c.empresa,
                        c.id_usuario,
                        u.usuario AS usuario_registro
                    FROM clientes c
                    INNER JOIN usuarios u ON c.id_usuario = u.id
                    ORDER BY c.id DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Clientes::getClientes => " . $e->getMessage());
            return [];
        }
    }

    public function crearCliente(
        int $idUsuario,
        string $nombre,
        string $apellido,
        ?string $cedula,
        ?string $telefono,
        ?string $direccion,
        ?string $empresa
    ): ?int {
        $this->lastError = null;

        $nombre = trim($nombre);
        $apellido = trim($apellido);
        $cedula = $this->normalizeNullable($cedula);
        $telefono = $this->normalizeNullable($telefono);
        $direccion = $this->normalizeNullable($direccion);
        $empresa = $this->normalizeNullable($empresa);

        if ($idUsuario <= 0) {
            $this->lastError = "No se pudo identificar al usuario que registra el cliente.";
            return null;
        }

        if ($nombre === "" || $apellido === "") {
            $this->lastError = "Nombre y apellido son obligatorios.";
            return null;
        }

        if ($cedula !== null && $this->existeCedula($cedula)) {
            $this->lastError = "La cédula ya existe en otro cliente.";
            return null;
        }

        try {
            $sql = "INSERT INTO clientes
                        (id_usuario, nombre, apellido, cedula, telefono, direccion, empresa)
                    VALUES
                        (:id_usuario, :nombre, :apellido, :cedula, :telefono, :direccion, :empresa)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
            $stmt->bindParam(":apellido", $apellido, PDO::PARAM_STR);
            $stmt->bindValue(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":telefono", $telefono, $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":direccion", $direccion, $direccion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":empresa", $empresa, $empresa === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error Clientes::crearCliente => " . $e->getMessage());
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "La cédula ya existe en otro cliente.";
            } else {
                $this->lastError = "No se pudo crear el cliente.";
            }
            return null;
        }
    }

    public function actualizarCliente(
        int $idCliente,
        string $nombre,
        string $apellido,
        ?string $cedula,
        ?string $telefono,
        ?string $direccion,
        ?string $empresa
    ): bool {
        $this->lastError = null;

        $nombre = trim($nombre);
        $apellido = trim($apellido);
        $cedula = $this->normalizeNullable($cedula);
        $telefono = $this->normalizeNullable($telefono);
        $direccion = $this->normalizeNullable($direccion);
        $empresa = $this->normalizeNullable($empresa);

        if ($idCliente <= 0) {
            $this->lastError = "ID de cliente inválido.";
            return false;
        }

        if ($nombre === "" || $apellido === "") {
            $this->lastError = "Nombre y apellido son obligatorios.";
            return false;
        }

        if ($cedula !== null && $this->existeCedulaEnOtroCliente($idCliente, $cedula)) {
            $this->lastError = "La cédula ya existe en otro cliente.";
            return false;
        }

        try {
            $sql = "UPDATE clientes
                    SET
                        nombre = :nombre,
                        apellido = :apellido,
                        cedula = :cedula,
                        telefono = :telefono,
                        direccion = :direccion,
                        empresa = :empresa
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idCliente, PDO::PARAM_INT);
            $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
            $stmt->bindParam(":apellido", $apellido, PDO::PARAM_STR);
            $stmt->bindValue(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":telefono", $telefono, $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":direccion", $direccion, $direccion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(":empresa", $empresa, $empresa === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                // Puede ser que no hubo cambios, pero validamos que el cliente exista.
                $stmtExists = $this->conn->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
                $stmtExists->bindParam(":id", $idCliente, PDO::PARAM_INT);
                $stmtExists->execute();
                if (!$stmtExists->fetch(PDO::FETCH_ASSOC)) {
                    $this->lastError = "Cliente no encontrado.";
                    return false;
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Clientes::actualizarCliente => " . $e->getMessage());
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "La cédula ya existe en otro cliente.";
            } else {
                $this->lastError = "No se pudo actualizar el cliente.";
            }
            return false;
        }
    }

    public function eliminarCliente(int $idCliente): bool
    {
        $this->lastError = null;

        if ($idCliente <= 0) {
            $this->lastError = "ID de cliente inválido.";
            return false;
        }

        try {
            $stmtExists = $this->conn->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
            $stmtExists->bindParam(":id", $idCliente, PDO::PARAM_INT);
            $stmtExists->execute();

            if (!$stmtExists->fetch(PDO::FETCH_ASSOC)) {
                $this->lastError = "Cliente no encontrado.";
                return false;
            }

            $stmtDelete = $this->conn->prepare("DELETE FROM clientes WHERE id = :id");
            $stmtDelete->bindParam(":id", $idCliente, PDO::PARAM_INT);
            $stmtDelete->execute();

            if ($stmtDelete->rowCount() <= 0) {
                $this->lastError = "No se pudo eliminar el cliente.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Clientes::eliminarCliente => " . $e->getMessage());

            // Integridad referencial: por ejemplo, ventas asociadas al cliente.
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "No se puede eliminar este cliente porque tiene registros relacionados.";
            } else {
                $this->lastError = "No se pudo eliminar el cliente.";
            }
            return false;
        }
    }

    private function existeCedula(string $cedula): bool
    {
        $sql = "SELECT id FROM clientes WHERE cedula = :cedula LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":cedula", $cedula, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function existeCedulaEnOtroCliente(int $idCliente, string $cedula): bool
    {
        $sql = "SELECT id
                FROM clientes
                WHERE cedula = :cedula
                  AND id <> :id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":cedula", $cedula, PDO::PARAM_STR);
        $stmt->bindParam(":id", $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === "" ? null : $value;
    }
}
