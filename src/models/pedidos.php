<?php 

namespace Models;

use Database;
use PDO;
use PDOException;

class Pedidos 
{
    private PDO $conn;
    private ?string $lastError = null;
    private ?bool $hasAbonosTableCache = null;

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
                    p.id_cliente,
                    p.fecha_creacion,
                    p.estado,
                    p.total,
                    v.id AS id_venta,

                    c.nombre,
                    c.apellido,
                    c.cedula

                FROM pedidos p
                INNER JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN ventas v ON v.id_pedido = p.id
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
                    v.id AS id_venta,
                    c.nombre,
                    c.apellido,
                    c.cedula,
                    c.telefono,
                    c.direccion
                FROM pedidos p
                INNER JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN ventas v ON v.id_pedido = p.id
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

    public function getMedidasPorPedido(int $idPedido): array {
        try {
            $sql = "SELECT
                    m.id,
                    m.id_detalle_pedido,
                    m.nombre_persona,
                    m.referencia,
                    m.cantidad,
                    m.medidas
                FROM detalle_pedido_medidas m
                INNER JOIN detalle_pedidos dp ON dp.id = m.id_detalle_pedido
                WHERE dp.id_pedido = :id_pedido
                ORDER BY m.id_detalle_pedido ASC, m.id ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_pedido", $idPedido, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $agrupadas = [];
            foreach ($rows as $row) {
                $idDetalle = (int) ($row['id_detalle_pedido'] ?? 0);
                if ($idDetalle <= 0) {
                    continue;
                }

                if (!isset($agrupadas[$idDetalle])) {
                    $agrupadas[$idDetalle] = [];
                }

                $agrupadas[$idDetalle][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nombre_persona' => (string) ($row['nombre_persona'] ?? ''),
                    'referencia' => $row['referencia'] !== null ? (string) $row['referencia'] : null,
                    'cantidad' => (int) ($row['cantidad'] ?? 0),
                    'medidas' => $row['medidas'] !== null ? (string) $row['medidas'] : null,
                ];
            }

            return $agrupadas;
        } catch (PDOException $e) {
            error_log("Error Pedidos::getMedidasPorPedido => " . $e->getMessage());
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

    public function actualizarPedidoConDetalles(int $idPedido, int $idCliente, string $estado, array $items): bool {
        $this->lastError = null;

        if ($idPedido <= 0) {
            $this->lastError = "Pedido inválido para actualizar.";
            return false;
        }

        if ($idCliente <= 0) {
            $this->lastError = "Cliente inválido para actualizar el pedido.";
            return false;
        }

        if (empty($items)) {
            $this->lastError = "Debes agregar al menos un producto al pedido.";
            return false;
        }

        $itemsNormalizados = [];
        foreach ($items as $posicion => $item) {
            if (!is_array($item)) {
                $this->lastError = "Item inválido al actualizar pedido.";
                return false;
            }

            $idProducto = (int) ($item['id_producto'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $asignaciones = $this->normalizarAsignacionesItem(
                $item['asignaciones'] ?? [],
                (int) $posicion + 1
            );

            if ($asignaciones === null) {
                return false;
            }

            if ($idProducto <= 0 || $cantidad <= 0) {
                $this->lastError = "Item inválido al actualizar pedido.";
                return false;
            }

            $cantidadAsignada = 0;
            foreach ($asignaciones as $asignacion) {
                $cantidadAsignada += (int) ($asignacion['cantidad'] ?? 0);
            }

            if ($cantidadAsignada > $cantidad) {
                $this->lastError = "La cantidad asignada en medidas supera la cantidad solicitada para el producto #{$idProducto}.";
                return false;
            }

            $itemsNormalizados[] = [
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'asignaciones' => $asignaciones,
            ];
        }

        $idsProducto = array_values(array_unique(array_map(
            static fn ($item) => (int) ($item['id_producto'] ?? 0),
            $itemsNormalizados
        )));

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
            error_log("Error Pedidos::actualizarPedidoConDetalles (precios) => " . $e->getMessage());
            $this->lastError = "No se pudieron validar los productos del pedido.";
            return false;
        }

        $preciosPorProducto = [];
        foreach ($preciosRows as $row) {
            $preciosPorProducto[(int) $row['id']] = (float) $row['precio_base'];
        }

        foreach ($idsProducto as $idProducto) {
            if (!isset($preciosPorProducto[(int) $idProducto])) {
                $this->lastError = "Producto no válido o inactivo: {$idProducto}.";
                return false;
            }
        }

        $detalleRows = [];
        $totalPedido = 0.0;
        $tieneAsignaciones = false;
        foreach ($itemsNormalizados as $itemNormalizado) {
            $idProducto = (int) ($itemNormalizado['id_producto'] ?? 0);
            $cantidad = (int) ($itemNormalizado['cantidad'] ?? 0);
            $asignaciones = is_array($itemNormalizado['asignaciones'] ?? null)
                ? $itemNormalizado['asignaciones']
                : [];
            $precioUnitario = (float) ($preciosPorProducto[$idProducto] ?? 0);

            if ($idProducto <= 0 || $cantidad <= 0 || $precioUnitario <= 0) {
                $this->lastError = "Item inválido al preparar la actualización del pedido.";
                return false;
            }

            $detalleRows[] = [
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'asignaciones' => $asignaciones,
            ];

            if (!empty($asignaciones)) {
                $tieneAsignaciones = true;
            }

            $totalPedido += ($precioUnitario * $cantidad);
        }

        try {
            $this->conn->beginTransaction();

            $stmtPedido = $this->conn->prepare("
                SELECT id
                FROM pedidos
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtPedido->bindParam(':id', $idPedido, PDO::PARAM_INT);
            $stmtPedido->execute();
            if (!$stmtPedido->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                $this->lastError = "Pedido no encontrado.";
                return false;
            }

            $stmtVenta = $this->conn->prepare("
                SELECT id
                FROM ventas
                WHERE id_pedido = :id_pedido
                LIMIT 1
                FOR UPDATE
            ");
            $stmtVenta->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmtVenta->execute();
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC) ?: null;
            $idVenta = (int) ($venta['id'] ?? 0);

            if ($idVenta > 0 && $this->hasAbonosTable()) {
                $stmtAbonos = $this->conn->prepare("
                    SELECT COALESCE(SUM(monto), 0) AS total_abonado
                    FROM abonos_ventas
                    WHERE id_venta = :id_venta
                ");
                $stmtAbonos->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
                $stmtAbonos->execute();
                $totalAbonado = (float) ($stmtAbonos->fetch(PDO::FETCH_ASSOC)['total_abonado'] ?? 0);

                if (($totalAbonado - $totalPedido) > 0.00001) {
                    $this->conn->rollBack();
                    $this->lastError = "El total del pedido no puede ser menor al valor ya abonado en la venta asociada.";
                    return false;
                }
            }

            $stmtUpdatePedido = $this->conn->prepare("
                UPDATE pedidos
                SET id_cliente = :id_cliente,
                    estado = :estado,
                    total = :total
                WHERE id = :id
            ");
            $stmtUpdatePedido->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
            $stmtUpdatePedido->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmtUpdatePedido->bindValue(':total', $totalPedido);
            $stmtUpdatePedido->bindParam(':id', $idPedido, PDO::PARAM_INT);
            $stmtUpdatePedido->execute();

            $stmtDeleteDetalles = $this->conn->prepare("DELETE FROM detalle_pedidos WHERE id_pedido = :id_pedido");
            $stmtDeleteDetalles->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmtDeleteDetalles->execute();

            $stmtDetalle = $this->conn->prepare("
                INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario)
                VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)
            ");

            $stmtMedidas = null;
            if ($tieneAsignaciones) {
                $stmtMedidas = $this->conn->prepare("
                    INSERT INTO detalle_pedido_medidas (id_detalle_pedido, nombre_persona, referencia, cantidad, medidas)
                    VALUES (:id_detalle_pedido, :nombre_persona, :referencia, :cantidad, :medidas)
                ");
            }

            foreach ($detalleRows as $detalle) {
                $stmtDetalle->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmtDetalle->bindValue(':id_producto', (int) $detalle['id_producto'], PDO::PARAM_INT);
                $stmtDetalle->bindValue(':cantidad', (int) $detalle['cantidad'], PDO::PARAM_INT);
                $stmtDetalle->bindValue(':precio_unitario', (float) $detalle['precio_unitario']);
                $stmtDetalle->execute();

                $idDetalle = (int) $this->conn->lastInsertId();
                if ($idDetalle <= 0) {
                    throw new PDOException("No se pudo obtener el ID del detalle del pedido.");
                }

                if ($stmtMedidas === null) {
                    continue;
                }

                $asignaciones = is_array($detalle['asignaciones'] ?? null)
                    ? $detalle['asignaciones']
                    : [];

                foreach ($asignaciones as $asignacion) {
                    $stmtMedidas->bindValue(':id_detalle_pedido', $idDetalle, PDO::PARAM_INT);
                    $stmtMedidas->bindValue(':nombre_persona', (string) ($asignacion['nombre_persona'] ?? ''), PDO::PARAM_STR);
                    $stmtMedidas->bindValue(
                        ':referencia',
                        $asignacion['referencia'] ?? null,
                        ($asignacion['referencia'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                    );
                    $stmtMedidas->bindValue(':cantidad', (int) ($asignacion['cantidad'] ?? 1), PDO::PARAM_INT);
                    $stmtMedidas->bindValue(
                        ':medidas',
                        $asignacion['medidas'] ?? null,
                        ($asignacion['medidas'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                    );
                    $stmtMedidas->execute();
                }
            }

            if ($idVenta > 0) {
                $stmtVentaSync = $this->conn->prepare("
                    UPDATE ventas
                    SET id_cliente = :id_cliente,
                        total = :total
                    WHERE id = :id_venta
                ");
                $stmtVentaSync->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
                $stmtVentaSync->bindValue(':total', $totalPedido);
                $stmtVentaSync->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
                $stmtVentaSync->execute();

                $stmtHistorialSync = $this->conn->prepare("
                    UPDATE historial_ventas
                    SET id_cliente = :id_cliente,
                        total = :total
                    WHERE id_venta = :id_venta
                ");
                $stmtHistorialSync->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
                $stmtHistorialSync->bindValue(':total', $totalPedido);
                $stmtHistorialSync->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
                $stmtHistorialSync->execute();
            }

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $mensaje = strtolower($e->getMessage());
            $faltaTablaMedidas = str_contains($mensaje, "detalle_pedido_medidas")
                && (str_contains($mensaje, "doesn't exist") || str_contains($mensaje, "1146"));

            if ($faltaTablaMedidas) {
                $this->lastError = "Falta la tabla de medidas personalizadas. Actualiza tu base de datos con storage/schema.sql.";
            } else {
                $this->lastError = "No se pudo actualizar el pedido. " . $e->getMessage();
            }

            error_log("Error Pedidos::actualizarPedidoConDetalles => " . $e->getMessage());
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

        $itemsNormalizados = [];
        foreach ($items as $posicion => $item) {
            if (!is_array($item)) {
                $this->lastError = "Item inválido al crear pedido.";
                return null;
            }

            $idProducto = (int) ($item['id_producto'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $asignaciones = $this->normalizarAsignacionesItem(
                $item['asignaciones'] ?? [],
                (int) $posicion + 1
            );

            if ($asignaciones === null) {
                return null;
            }

            if ($idProducto <= 0 || $cantidad <= 0) {
                $this->lastError = "Item inválido al crear pedido.";
                return null;
            }

            $cantidadAsignada = 0;
            foreach ($asignaciones as $asignacion) {
                $cantidadAsignada += (int) ($asignacion['cantidad'] ?? 0);
            }

            if ($cantidadAsignada > $cantidad) {
                $this->lastError = "La cantidad asignada en medidas supera la cantidad solicitada para el producto #{$idProducto}.";
                return null;
            }

            $itemsNormalizados[] = [
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'asignaciones' => $asignaciones,
            ];
        }

        if (empty($itemsNormalizados)) {
            $this->lastError = "Debes agregar al menos un producto al pedido.";
            return null;
        }

        // Obtener precios fuera de la transacción para reducir tiempo de locks.
        $idsProducto = array_values(array_unique(array_map(
            static fn ($item) => (int) ($item['id_producto'] ?? 0),
            $itemsNormalizados
        )));
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
        $tieneAsignaciones = false;
        foreach ($itemsNormalizados as $itemNormalizado) {
            $idProducto = (int) ($itemNormalizado['id_producto'] ?? 0);
            $cantidad = (int) ($itemNormalizado['cantidad'] ?? 0);
            $asignaciones = is_array($itemNormalizado['asignaciones'] ?? null)
                ? $itemNormalizado['asignaciones']
                : [];
            $precioUnitario = (float) ($preciosPorProducto[$idProducto] ?? 0);

            if ($idProducto <= 0 || $cantidad <= 0 || $precioUnitario <= 0) {
                $this->lastError = "Item inválido al preparar el pedido.";
                return null;
            }

            $detalleRows[] = [
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'asignaciones' => $asignaciones,
            ];
            if (!empty($asignaciones)) {
                $tieneAsignaciones = true;
            }
            $totalPedido += ($precioUnitario * $cantidad);
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

                $stmtMedidas = null;
                if ($tieneAsignaciones) {
                    $stmtMedidas = $this->conn->prepare("
                        INSERT INTO detalle_pedido_medidas (id_detalle_pedido, nombre_persona, referencia, cantidad, medidas)
                        VALUES (:id_detalle_pedido, :nombre_persona, :referencia, :cantidad, :medidas)
                    ");
                }

                foreach ($detalleRows as $detalle) {
                    $stmtDetalle->bindValue(":id_pedido", $idPedido, PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":id_producto", (int) $detalle['id_producto'], PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":cantidad", (int) $detalle['cantidad'], PDO::PARAM_INT);
                    $stmtDetalle->bindValue(":precio_unitario", (float) $detalle['precio_unitario']);
                    $stmtDetalle->execute();

                    $idDetalle = (int) $this->conn->lastInsertId();
                    if ($idDetalle <= 0) {
                        throw new PDOException("No se pudo obtener el ID del detalle del pedido.");
                    }

                    if ($stmtMedidas === null) {
                        continue;
                    }

                    $asignaciones = is_array($detalle['asignaciones'] ?? null)
                        ? $detalle['asignaciones']
                        : [];

                    foreach ($asignaciones as $asignacion) {
                        $stmtMedidas->bindValue(":id_detalle_pedido", $idDetalle, PDO::PARAM_INT);
                        $stmtMedidas->bindValue(":nombre_persona", (string) ($asignacion['nombre_persona'] ?? ''), PDO::PARAM_STR);
                        $stmtMedidas->bindValue(
                            ":referencia",
                            $asignacion['referencia'] ?? null,
                            ($asignacion['referencia'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                        );
                        $stmtMedidas->bindValue(":cantidad", (int) ($asignacion['cantidad'] ?? 1), PDO::PARAM_INT);
                        $stmtMedidas->bindValue(
                            ":medidas",
                            $asignacion['medidas'] ?? null,
                            ($asignacion['medidas'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                        );
                        $stmtMedidas->execute();
                    }
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
                $faltaTablaMedidas = str_contains($mensaje, "detalle_pedido_medidas")
                    && (str_contains($mensaje, "doesn't exist") || str_contains($mensaje, "1146"));

                if ($esBloqueo && $intento < $maxIntentos) {
                    usleep(250000); // 250ms antes de reintentar si la BD está bloqueada temporalmente.
                    continue;
                }

                if ($faltaTablaMedidas) {
                    $this->lastError = "Falta la tabla de medidas personalizadas. Actualiza tu base de datos con storage/schema.sql.";
                } elseif ($esBloqueo) {
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

    private function normalizarAsignacionesItem($asignacionesRaw, int $posicionItem): ?array {
        if (!is_array($asignacionesRaw)) {
            $this->lastError = "Formato inválido de medidas para el producto #{$posicionItem}.";
            return null;
        }

        $asignacionesNormalizadas = [];
        foreach ($asignacionesRaw as $posicionAsignacion => $asignacionRaw) {
            if (!is_array($asignacionRaw)) {
                continue;
            }

            $nombrePersona = trim((string) ($asignacionRaw['nombre_persona'] ?? ''));
            $referencia = trim((string) ($asignacionRaw['referencia'] ?? ''));
            $medidas = trim((string) ($asignacionRaw['medidas'] ?? ''));
            $cantidad = (int) ($asignacionRaw['cantidad'] ?? 1);

            if ($nombrePersona === '' && $referencia === '' && $medidas === '') {
                continue;
            }

            if ($nombrePersona === '') {
                $numAsignacion = (int) $posicionAsignacion + 1;
                $this->lastError = "Debes indicar el nombre de la persona en la medida #{$numAsignacion} del producto #{$posicionItem}.";
                return null;
            }

            if ($cantidad <= 0) {
                $numAsignacion = (int) $posicionAsignacion + 1;
                $this->lastError = "La cantidad de la medida #{$numAsignacion} del producto #{$posicionItem} debe ser mayor a cero.";
                return null;
            }

            if (strlen($nombrePersona) > 120) {
                $this->lastError = "El nombre de la persona no puede exceder 120 caracteres.";
                return null;
            }

            if (strlen($referencia) > 120) {
                $this->lastError = "La referencia de la medida no puede exceder 120 caracteres.";
                return null;
            }

            if (strlen($medidas) > 4000) {
                $this->lastError = "El detalle de medidas no puede exceder 4000 caracteres.";
                return null;
            }

            $asignacionesNormalizadas[] = [
                'nombre_persona' => $nombrePersona,
                'referencia' => $referencia !== '' ? $referencia : null,
                'cantidad' => $cantidad,
                'medidas' => $medidas !== '' ? $medidas : null,
            ];
        }

        return $asignacionesNormalizadas;
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
        $this->lastError = null;

        $idPedido = (int) $id;
        if ($idPedido <= 0) {
            $this->lastError = "Pedido inválido para eliminar.";
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmtPedido = $this->conn->prepare("
                SELECT id
                FROM pedidos
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtPedido->bindParam(':id', $idPedido, PDO::PARAM_INT);
            $stmtPedido->execute();
            if (!$stmtPedido->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                $this->lastError = "Pedido no encontrado.";
                return false;
            }

            $stmtVenta = $this->conn->prepare("SELECT id FROM ventas WHERE id_pedido = :id_pedido LIMIT 1");
            $stmtVenta->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmtVenta->execute();
            if ($stmtVenta->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                $this->lastError = "No se puede eliminar un pedido que ya tiene una venta asociada.";
                return false;
            }

            $stmtDelete = $this->conn->prepare("DELETE FROM pedidos WHERE id = :id");
            $stmtDelete->bindParam(':id', $idPedido, PDO::PARAM_INT);
            $stmtDelete->execute();

            if ($stmtDelete->rowCount() <= 0) {
                $this->conn->rollBack();
                $this->lastError = "No se pudo eliminar el pedido.";
                return false;
            }

            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Pedidos::eliminarPedido => " . $e->getMessage());
            $this->lastError = "No se pudo eliminar el pedido.";
            return false;
        }
    }

    private function hasAbonosTable(): bool {
        if ($this->hasAbonosTableCache !== null) {
            return $this->hasAbonosTableCache;
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'abonos_ventas'");
            $this->hasAbonosTableCache = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Pedidos::hasAbonosTable => " . $e->getMessage());
            $this->hasAbonosTableCache = false;
        }

        return $this->hasAbonosTableCache;
    }
}


?>
