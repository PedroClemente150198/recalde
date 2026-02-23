<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Ventas {
    private PDO $conn;
    private string $tableName = "ventas";
    private ?string $lastError = null;
    private array $metodosPermitidos = ['efectivo', 'transferencia', 'tarjeta', 'otro'];

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Obtener todas las ventas con datos del cliente y pedido.
     */
    public function getAllVentas(): array {
        try {
            $sql = "SELECT
                        v.id,
                        v.id_pedido,
                        v.id_cliente,
                        v.total,
                        v.metodo_pago,
                        v.fecha_venta,
                        c.nombre,
                        c.apellido,
                        c.cedula,
                        p.estado
                    FROM {$this->tableName} v
                    INNER JOIN clientes c ON v.id_cliente = c.id
                    INNER JOIN pedidos p ON v.id_pedido = p.id
                    ORDER BY v.fecha_venta DESC";

            $query = $this->conn->prepare($sql);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getAllVentas => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener venta por ID.
     */
    public function getVentaById(int $id): ?array {
        try {
            $sql = "SELECT
                        v.*,
                        c.nombre,
                        c.apellido
                    FROM ventas v
                    INNER JOIN clientes c ON c.id = v.id_cliente
                    WHERE v.id = :id
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error Ventas::getVentaById => " . $e->getMessage());
            return null;
        }
    }

    /**
     * Pedidos disponibles para registrar una venta (sin venta previa).
     */
    public function getPedidosDisponiblesParaVenta(): array {
        try {
            $sql = "SELECT
                        p.id,
                        p.id_cliente,
                        p.total,
                        p.estado,
                        c.nombre,
                        c.apellido,
                        c.cedula
                    FROM pedidos p
                    INNER JOIN clientes c ON c.id = p.id_cliente
                    LEFT JOIN ventas v ON v.id_pedido = p.id
                    WHERE v.id IS NULL
                      AND p.estado <> 'cancelado'
                    ORDER BY p.fecha_creacion DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getPedidosDisponiblesParaVenta => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compatibilidad con firma anterior.
     */
    public function createVenta(
        int $id_pedido,
        int $id_cliente,
        float $total,
        string $metodo_pago,
        int $usuario_registro = null
    ): bool {
        $idVenta = $this->crearVentaDesdePedido($id_pedido, $total, $metodo_pago, $usuario_registro);
        return $idVenta !== null;
    }

    /**
     * Crear venta desde pedido y registrar historial.
     */
    public function crearVentaDesdePedido(
        int $idPedido,
        float $total,
        string $metodoPago,
        ?int $usuarioRegistro = null
    ): ?int {
        $this->lastError = null;
        $metodoPago = strtolower(trim($metodoPago));

        if ($idPedido <= 0) {
            $this->lastError = "Pedido inválido para registrar la venta.";
            return null;
        }

        if ($total <= 0) {
            $this->lastError = "El total de la venta debe ser mayor a 0.";
            return null;
        }

        if (!in_array($metodoPago, $this->metodosPermitidos, true)) {
            $this->lastError = "Método de pago inválido.";
            return null;
        }

        try {
            $this->conn->beginTransaction();

            $sqlPedido = "SELECT id, id_cliente, total, estado
                          FROM pedidos
                          WHERE id = :id
                          LIMIT 1";
            $stmtPedido = $this->conn->prepare($sqlPedido);
            $stmtPedido->bindParam(":id", $idPedido, PDO::PARAM_INT);
            $stmtPedido->execute();
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $this->conn->rollBack();
                $this->lastError = "Pedido no encontrado.";
                return null;
            }

            if (($pedido['estado'] ?? '') === 'cancelado') {
                $this->conn->rollBack();
                $this->lastError = "No se puede registrar una venta de un pedido cancelado.";
                return null;
            }

            $stmtVentaExiste = $this->conn->prepare("SELECT id FROM ventas WHERE id_pedido = :id_pedido LIMIT 1");
            $stmtVentaExiste->bindParam(":id_pedido", $idPedido, PDO::PARAM_INT);
            $stmtVentaExiste->execute();

            if ($stmtVentaExiste->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                $this->lastError = "Este pedido ya tiene una venta registrada.";
                return null;
            }

            $idCliente = (int) ($pedido['id_cliente'] ?? 0);
            if ($idCliente <= 0) {
                $this->conn->rollBack();
                $this->lastError = "El pedido no tiene un cliente válido.";
                return null;
            }

            $sqlVenta = "INSERT INTO ventas
                            (id_pedido, id_cliente, total, metodo_pago, usuario_registro)
                         VALUES
                            (:id_pedido, :id_cliente, :total, :metodo_pago, :usuario_registro)";
            $stmtVenta = $this->conn->prepare($sqlVenta);
            $stmtVenta->bindParam(":id_pedido", $idPedido, PDO::PARAM_INT);
            $stmtVenta->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
            $stmtVenta->bindParam(":total", $total);
            $stmtVenta->bindParam(":metodo_pago", $metodoPago, PDO::PARAM_STR);
            if ($usuarioRegistro !== null && $usuarioRegistro > 0) {
                $stmtVenta->bindParam(":usuario_registro", $usuarioRegistro, PDO::PARAM_INT);
            } else {
                $stmtVenta->bindValue(":usuario_registro", null, PDO::PARAM_NULL);
            }
            $stmtVenta->execute();

            $idVenta = (int) $this->conn->lastInsertId();
            if ($idVenta <= 0) {
                throw new PDOException("No se pudo obtener el ID de la venta creada.");
            }

            $sqlHistorial = "INSERT INTO historial_ventas
                                (id_venta, id_cliente, total, estado, usuario_responsable)
                             VALUES
                                (:id_venta, :id_cliente, :total, 'registrado', :usuario_responsable)";
            $stmtHistorial = $this->conn->prepare($sqlHistorial);
            $stmtHistorial->bindParam(":id_venta", $idVenta, PDO::PARAM_INT);
            $stmtHistorial->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
            $stmtHistorial->bindParam(":total", $total);
            if ($usuarioRegistro !== null && $usuarioRegistro > 0) {
                $stmtHistorial->bindParam(":usuario_responsable", $usuarioRegistro, PDO::PARAM_INT);
            } else {
                $stmtHistorial->bindValue(":usuario_responsable", null, PDO::PARAM_NULL);
            }
            $stmtHistorial->execute();

            $this->conn->commit();
            return $idVenta;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Ventas::crearVentaDesdePedido => " . $e->getMessage());
            $this->lastError = "No se pudo registrar la venta.";
            return null;
        }
    }

    /**
     * Actualizar total y método de pago de una venta.
     */
    public function updateVenta(int $id, float $total, string $metodoPago): bool {
        $this->lastError = null;
        $metodoPago = strtolower(trim($metodoPago));

        if ($id <= 0) {
            $this->lastError = "ID de venta inválido.";
            return false;
        }

        if ($total <= 0) {
            $this->lastError = "El total de la venta debe ser mayor a 0.";
            return false;
        }

        if (!in_array($metodoPago, $this->metodosPermitidos, true)) {
            $this->lastError = "Método de pago inválido.";
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE ventas
                    SET total = :total,
                        metodo_pago = :metodo_pago
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":total", $total);
            $stmt->bindParam(":metodo_pago", $metodoPago);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $stmtExists = $this->conn->prepare("SELECT id FROM ventas WHERE id = :id LIMIT 1");
                $stmtExists->bindParam(":id", $id, PDO::PARAM_INT);
                $stmtExists->execute();
                if (!$stmtExists->fetch(PDO::FETCH_ASSOC)) {
                    $this->conn->rollBack();
                    $this->lastError = "Venta no encontrada.";
                    return false;
                }
            }

            $stmtHistorial = $this->conn->prepare("UPDATE historial_ventas
                                                   SET total = :total
                                                   WHERE id_venta = :id_venta");
            $stmtHistorial->bindParam(":total", $total);
            $stmtHistorial->bindParam(":id_venta", $id, PDO::PARAM_INT);
            $stmtHistorial->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Ventas::updateVenta => " . $e->getMessage());
            $this->lastError = "No se pudo actualizar la venta.";
            return false;
        }
    }

    /**
     * Eliminar una venta.
     */
    public function deleteVenta(int $id): bool {
        $this->lastError = null;

        if ($id <= 0) {
            $this->lastError = "ID de venta inválido.";
            return false;
        }

        try {
            $stmtExists = $this->conn->prepare("SELECT id FROM ventas WHERE id = :id LIMIT 1");
            $stmtExists->bindParam(":id", $id, PDO::PARAM_INT);
            $stmtExists->execute();
            if (!$stmtExists->fetch(PDO::FETCH_ASSOC)) {
                $this->lastError = "Venta no encontrada.";
                return false;
            }

            $sql = "DELETE FROM ventas WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $this->lastError = "No se pudo eliminar la venta.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Ventas::deleteVenta => " . $e->getMessage());
            $this->lastError = "No se pudo eliminar la venta.";
            return false;
        }
    }
}

?>
