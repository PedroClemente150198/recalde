<?php 

namespace Models;

use Database;
use PDO;
use PDOException;

class Pedidos 
{
    private PDO $conn;
    private ?string $lastError = null;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }


    /* ======================================================
     *  LISTAR TODOS LOS PEDIDOS (JOIN CLIENTES)
     * ====================================================== */
    public function getPedidos() {
        try {
            $sql = "SELECT 
                    p.id,
                    p.fecha_creacion,
                    p.estado,
                    p.total,

                    c.nombre,
                    c.apellido,
                    c.cedula

                FROM pedidos p
                INNER JOIN clientes c ON p.id_cliente = c.id
                ORDER BY p.fecha_creacion DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getPedidos => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Listado de clientes para crear pedidos
     */
    public function getClientesParaPedido(): array {
        try {
            $sql = "SELECT 
                    c.id,
                    c.nombre,
                    c.apellido,
                    c.cedula
                FROM clientes c
                ORDER BY c.nombre, c.apellido
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getClientesParaPedido => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Listado de productos activos para crear pedidos
     */
    public function getProductosParaPedido(): array {
        try {
            $sql = "SELECT 
                    p.id,
                    p.nombre_producto,
                    p.precio_base
                FROM productos p
                WHERE p.estado = 'activo'
                ORDER BY p.nombre_producto
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getProductosParaPedido => " . $e->getMessage());
            return [];
        }
    }


    /* ======================================================
     *  OBTENER PEDIDO POR ID (BÁSICO)
     * ====================================================== */
    public function getPedidoById($id) {
        try {
            $sql = "SELECT 
                    p.*,
                    c.nombre,
                    c.apellido,
                    c.cedula,
                    c.telefono,
                    c.direccion
                FROM pedidos p
                INNER JOIN clientes c ON p.id_cliente = c.id
                WHERE p.id = :id
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getPedidoById => " . $e->getMessage());
            return null;
        }
    }


    /* ======================================================
     *  OBTENER DETALLE COMPLETO DE UN PEDIDO
     * ====================================================== */
    public function getDetallePedido($id_pedido) {
        try {
            $sql = "SELECT 
                    dp.id,
                    dp.id_producto,
                    p.nombre_producto,
                    dp.cantidad,
                    dp.precio_unitario,
                    dp.subtotal
                FROM detalle_pedidos dp
                INNER JOIN productos p ON dp.id_producto = p.id
                WHERE dp.id_pedido = :id_pedido
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getDetallePedido => " . $e->getMessage());
            return [];
        }
    }


    /* ======================================================
     *  OBTENER PERSONALIZACIONES DE UN DETALLE
     * ====================================================== */
    public function getPersonalizaciones($id_detalle) {
        try {
            $sql = "SELECT 
                    dp.id,
                    op.nombre,
                    op.tipo,
                    dp.precio
                FROM detalle_personalizaciones dp
                INNER JOIN opciones_personalizacion op 
                    ON dp.id_personalizacion = op.id
                WHERE dp.id_detalle_pedido = :id_detalle
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_detalle", $id_detalle);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Pedidos::getPersonalizaciones => " . $e->getMessage());
            return [];
        }
    }


    /* ======================================================
     *  CREAR UN NUEVO PEDIDO
     * ====================================================== */
    public function crearPedido($id_cliente) {
        try {
            $sql = "INSERT INTO pedidos (id_cliente)
                VALUES (:id_cliente)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_cliente", $id_cliente);
            $stmt->execute();

            return $this->conn->lastInsertId();

        } catch (PDOException $e) {
            error_log("Error Pedidos::crearPedido => " . $e->getMessage());
            return false;
        }
    }


    /* ======================================================
     *  AGREGAR DETALLE AL PEDIDO
     * ====================================================== */
    public function agregarDetalle($id_pedido, $id_producto, $cantidad, $precio_unitario) {
        try {
            $sql = "INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario)
                VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->bindParam(":id_producto", $id_producto);
            $stmt->bindParam(":cantidad", $cantidad);
            $stmt->bindParam(":precio_unitario", $precio_unitario);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error Pedidos::agregarDetalle => " . $e->getMessage());
            return false;
        }
    }


    /* ======================================================
     *  ACTUALIZAR ESTADO DEL PEDIDO
     * ====================================================== */
    public function actualizarEstado($id, $nuevo_estado) {
        try {
            $sql = "UPDATE pedidos
                SET estado = :estado
                WHERE id = :id
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":estado", $nuevo_estado);
            $stmt->bindParam(":id", $id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error Pedidos::actualizarEstado => " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear pedido y detalle dentro de una transacción
     */
    public function crearPedidoConDetalles(int $idCliente, string $estado, array $items): ?int {
        $this->lastError = null;

        if (empty($items)) {
            $this->lastError = "Debes agregar al menos un producto al pedido.";
            return null;
        }

        if ($idCliente <= 0) {
            $this->lastError = "Cliente inválido para crear el pedido.";
            return null;
        }

        // Normalizar y consolidar items para evitar duplicados del mismo producto.
        $itemsNormalizados = [];
        foreach ($items as $item) {
            $idProducto = (int) ($item['id_producto'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);

            if ($idProducto <= 0 || $cantidad <= 0) {
                $this->lastError = "Item inválido al crear pedido.";
                return null;
            }

            if (!isset($itemsNormalizados[$idProducto])) {
                $itemsNormalizados[$idProducto] = 0;
            }
            $itemsNormalizados[$idProducto] += $cantidad;
        }

        if (empty($itemsNormalizados)) {
            $this->lastError = "Debes agregar al menos un producto al pedido.";
            return null;
        }

        // Obtener precios fuera de la transacción para reducir tiempo de locks.
        $idsProducto = array_keys($itemsNormalizados);
        $placeholders = implode(",", array_fill(0, count($idsProducto), "?"));
        $sqlPrecios = "SELECT id, precio_base
                       FROM productos
                       WHERE estado = 'activo'
                         AND id IN ({$placeholders})";

        try {
            $stmtPrecios = $this->conn->prepare($sqlPrecios);
            foreach ($idsProducto as $index => $idProducto) {
                $stmtPrecios->bindValue($index + 1, (int) $idProducto, PDO::PARAM_INT);
            }
            $stmtPrecios->execute();
            $preciosRows = $stmtPrecios->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Pedidos::crearPedidoConDetalles (precios) => " . $e->getMessage());
            $this->lastError = "No se pudieron validar los productos del pedido.";
            return null;
        }

        $preciosPorProducto = [];
        foreach ($preciosRows as $row) {
            $preciosPorProducto[(int) $row['id']] = (float) $row['precio_base'];
        }

        foreach ($idsProducto as $idProducto) {
            if (!isset($preciosPorProducto[(int) $idProducto])) {
                $this->lastError = "Producto no válido o inactivo: {$idProducto}.";
                return null;
            }
        }

        $detalleRows = [];
        $totalPedido = 0.0;
        foreach ($itemsNormalizados as $idProducto => $cantidad) {
            $precioUnitario = (float) $preciosPorProducto[(int) $idProducto];
            $detalleRows[] = [
                'id_producto' => (int) $idProducto,
                'cantidad' => (int) $cantidad,
                'precio_unitario' => $precioUnitario
            ];
            $totalPedido += ($precioUnitario * (int) $cantidad);
        }

        $maxIntentos = 3;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $paso = "inicio";
            try {
                $paso = "beginTransaction";
                $this->conn->beginTransaction();

                $paso = "insert_pedido";
                $sqlPedido = "INSERT INTO pedidos (id_cliente, estado, total)
                    VALUES (:id_cliente, :estado, :total)
                ";
                $stmtPedido = $this->conn->prepare($sqlPedido);
                $stmtPedido->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
                $stmtPedido->bindParam(":estado", $estado, PDO::PARAM_STR);
                $stmtPedido->bindValue(":total", $totalPedido);
                $stmtPedido->execute();

                $idPedido = (int) $this->conn->lastInsertId();
                if ($idPedido <= 0) {
                    throw new PDOException("No se pudo obtener el ID del pedido creado.");
                }

                $paso = "insert_detalles";
                $stmtDetalle = $this->conn->prepare("
                    INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario)
                    VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)
                ");

                foreach ($detalleRows as $detalle) {
                    $stmtDetalle->bindValue(":id_pedido", $idPedido, PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":id_producto", (int) $detalle['id_producto'], PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":cantidad", (int) $detalle['cantidad'], PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":precio_unitario", (float) $detalle['precio_unitario']);
                    $stmtDetalle->execute();
                }

                $paso = "commit";
                $this->conn->commit();
                return $idPedido;
            } catch (\Throwable $e) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }

                $mensaje = strtolower($e->getMessage());
                $esLockTimeout = str_contains($mensaje, "lock wait timeout") || str_contains($mensaje, "1205");
                $esDeadlock = str_contains($mensaje, "deadlock");
                $esBloqueo = $esLockTimeout || $esDeadlock;

                if ($esBloqueo && $intento < $maxIntentos) {
                    usleep(250000); // 250ms antes de reintentar si la BD está bloqueada temporalmente.
                    continue;
                }

                if ($esBloqueo) {
                    $this->lastError = "La base de datos está ocupada por otra operación. Intenta nuevamente en unos segundos.";
                } else {
                    $this->lastError = "No se pudo crear el pedido. " . $e->getMessage();
                }

                error_log("Error Pedidos::crearPedidoConDetalles (paso {$paso}, intento {$intento}) => " . $e->getMessage());
                return null;
            }
        }

        $this->lastError = "No se pudo crear el pedido por bloqueo de base de datos.";
        return null;
    }


    /* ======================================================
     *  ACTUALIZAR TOTAL (SUMA DE SUBTOTALES)
     * ====================================================== */
    public function actualizarTotal($id_pedido) {
        try {
            $sql = "UPDATE pedidos
                SET total = (
                    SELECT IFNULL(SUM(subtotal), 0)
                    FROM detalle_pedidos
                    WHERE id_pedido = :id_pedido
                )
                WHERE id = :id_pedido
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_pedido", $id_pedido);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error Pedidos::actualizarTotal => " . $e->getMessage());
            return false;
        }
    }


    /* ======================================================
     *  ELIMINAR PEDIDO COMPLETO (DETALLES INCLUIDOS)
     * ====================================================== */
    public function eliminarPedido($id) {
        try {
            $sql = "DELETE FROM pedidos WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error Pedidos::eliminarPedido => " . $e->getMessage());
            return false;
        }
    }
}


?>
