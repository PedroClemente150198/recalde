<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Historial {

    private PDO $conn;
    private ?bool $hasAbonosTableCache = null;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /**
     * Obtener historial completo de ventas
     */
    public function getHistorial() {
        try {
            if ($this->hasAbonosTable()) {
                $sql = "
                    SELECT 
                        hv.id,
                        hv.id_venta,
                        hv.id_cliente,
                        hv.fecha,
                        hv.total,
                        hv.estado,
                        hv.usuario_responsable,
                        
                        c.nombre,
                        c.apellido,
                        c.cedula,

                        v.id_pedido,
                        v.metodo_pago,
                        v.fecha_venta,

                        p.estado AS estado_pedido,
                        u.usuario AS usuario_responsable_nombre,

                        COALESCE(rp.total_items, 0) AS total_items,
                        COALESCE(rp.total_prendas, 0) AS total_prendas,
                        COALESCE(ax.total_abonado, 0) AS total_abonado,
                        GREATEST(hv.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                        CASE
                            WHEN COALESCE(ax.total_abonado, 0) >= hv.total THEN 'pagado'
                            WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                            ELSE 'pendiente'
                        END AS estado_pago,
                        ax.ultima_fecha_abono

                    FROM historial_ventas hv
                    INNER JOIN clientes c ON hv.id_cliente = c.id
                    LEFT JOIN ventas v ON hv.id_venta = v.id
                    LEFT JOIN pedidos p ON v.id_pedido = p.id
                    LEFT JOIN usuarios u ON hv.usuario_responsable = u.id
                    LEFT JOIN (
                        SELECT
                            dp.id_pedido,
                            COUNT(*) AS total_items,
                            COALESCE(SUM(dp.cantidad), 0) AS total_prendas
                        FROM detalle_pedidos dp
                        GROUP BY dp.id_pedido
                    ) rp ON rp.id_pedido = v.id_pedido
                    LEFT JOIN (
                        SELECT
                            av.id_venta,
                            COALESCE(SUM(av.monto), 0) AS total_abonado,
                            MAX(av.fecha_abono) AS ultima_fecha_abono
                        FROM abonos_ventas av
                        GROUP BY av.id_venta
                    ) ax ON ax.id_venta = hv.id_venta
                    ORDER BY hv.fecha DESC
                ";
            } else {
                $sql = "
                    SELECT 
                        hv.id,
                        hv.id_venta,
                        hv.id_cliente,
                        hv.fecha,
                        hv.total,
                        hv.estado,
                        hv.usuario_responsable,
                        
                        c.nombre,
                        c.apellido,
                        c.cedula,

                        v.id_pedido,
                        v.metodo_pago,
                        v.fecha_venta,

                        p.estado AS estado_pedido,
                        u.usuario AS usuario_responsable_nombre,

                        COALESCE(rp.total_items, 0) AS total_items,
                        COALESCE(rp.total_prendas, 0) AS total_prendas,
                        hv.total AS total_abonado,
                        0.00 AS saldo_pendiente,
                        'pagado' AS estado_pago,
                        NULL AS ultima_fecha_abono

                    FROM historial_ventas hv
                    INNER JOIN clientes c ON hv.id_cliente = c.id
                    LEFT JOIN ventas v ON hv.id_venta = v.id
                    LEFT JOIN pedidos p ON v.id_pedido = p.id
                    LEFT JOIN usuarios u ON hv.usuario_responsable = u.id
                    LEFT JOIN (
                        SELECT
                            dp.id_pedido,
                            COUNT(*) AS total_items,
                            COALESCE(SUM(dp.cantidad), 0) AS total_prendas
                        FROM detalle_pedidos dp
                        GROUP BY dp.id_pedido
                    ) rp ON rp.id_pedido = v.id_pedido
                    ORDER BY hv.fecha DESC
                ";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('Error Historial::getHistorial => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un registro puntual del historial
     */
    public function getById($idHistorial) {
        try {
            if ($this->hasAbonosTable()) {
                $sql = "
                    SELECT 
                        hv.id,
                        hv.id_venta,
                        hv.id_cliente,
                        hv.fecha,
                        hv.total,
                        hv.estado,
                        hv.usuario_responsable,

                        c.nombre,
                        c.apellido,
                        c.cedula,
                        c.telefono,
                        c.direccion,
                        c.empresa,

                        v.id_pedido,
                        v.metodo_pago,
                        v.fecha_venta,

                        p.estado AS estado_pedido,
                        u.usuario AS usuario_responsable_nombre,

                        COALESCE(rp.total_items, 0) AS total_items,
                        COALESCE(rp.total_prendas, 0) AS total_prendas,
                        COALESCE(ax.total_abonado, 0) AS total_abonado,
                        GREATEST(hv.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                        CASE
                            WHEN COALESCE(ax.total_abonado, 0) >= hv.total THEN 'pagado'
                            WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                            ELSE 'pendiente'
                        END AS estado_pago,
                        ax.ultima_fecha_abono

                    FROM historial_ventas hv
                    INNER JOIN clientes c ON hv.id_cliente = c.id
                    LEFT JOIN ventas v ON hv.id_venta = v.id
                    LEFT JOIN pedidos p ON v.id_pedido = p.id
                    LEFT JOIN usuarios u ON hv.usuario_responsable = u.id
                    LEFT JOIN (
                        SELECT
                            dp.id_pedido,
                            COUNT(*) AS total_items,
                            COALESCE(SUM(dp.cantidad), 0) AS total_prendas
                        FROM detalle_pedidos dp
                        GROUP BY dp.id_pedido
                    ) rp ON rp.id_pedido = v.id_pedido
                    LEFT JOIN (
                        SELECT
                            av.id_venta,
                            COALESCE(SUM(av.monto), 0) AS total_abonado,
                            MAX(av.fecha_abono) AS ultima_fecha_abono
                        FROM abonos_ventas av
                        GROUP BY av.id_venta
                    ) ax ON ax.id_venta = hv.id_venta
                    WHERE hv.id = :id
                    LIMIT 1
                ";
            } else {
                $sql = "
                    SELECT 
                        hv.id,
                        hv.id_venta,
                        hv.id_cliente,
                        hv.fecha,
                        hv.total,
                        hv.estado,
                        hv.usuario_responsable,

                        c.nombre,
                        c.apellido,
                        c.cedula,
                        c.telefono,
                        c.direccion,
                        c.empresa,

                        v.id_pedido,
                        v.metodo_pago,
                        v.fecha_venta,

                        p.estado AS estado_pedido,
                        u.usuario AS usuario_responsable_nombre,

                        COALESCE(rp.total_items, 0) AS total_items,
                        COALESCE(rp.total_prendas, 0) AS total_prendas,
                        hv.total AS total_abonado,
                        0.00 AS saldo_pendiente,
                        'pagado' AS estado_pago,
                        NULL AS ultima_fecha_abono

                    FROM historial_ventas hv
                    INNER JOIN clientes c ON hv.id_cliente = c.id
                    LEFT JOIN ventas v ON hv.id_venta = v.id
                    LEFT JOIN pedidos p ON v.id_pedido = p.id
                    LEFT JOIN usuarios u ON hv.usuario_responsable = u.id
                    LEFT JOIN (
                        SELECT
                            dp.id_pedido,
                            COUNT(*) AS total_items,
                            COALESCE(SUM(dp.cantidad), 0) AS total_prendas
                        FROM detalle_pedidos dp
                        GROUP BY dp.id_pedido
                    ) rp ON rp.id_pedido = v.id_pedido
                    WHERE hv.id = :id
                    LIMIT 1
                ";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $idHistorial, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;

        } catch (PDOException $e) {
            error_log('Error Historial::getById => ' . $e->getMessage());
            return null;
        }
    }

    public function getResumenGeneral(): array {
        $defaults = [
            'total_registros' => 0,
            'total_vigentes' => 0,
            'total_anulados' => 0,
            'monto_vigente' => 0.0,
            'total_abonado' => 0.0,
            'total_pendiente' => 0.0,
        ];

        try {
            if ($this->hasAbonosTable()) {
                $sql = "SELECT
                            COUNT(*) AS total_registros,
                            SUM(CASE WHEN LOWER(hv.estado) = 'anulado' THEN 1 ELSE 0 END) AS total_anulados,
                            SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN 1 ELSE 0 END) AS total_vigentes,
                            COALESCE(SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN hv.total ELSE 0 END), 0) AS monto_vigente,
                            COALESCE(SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN COALESCE(ax.total_abonado, 0) ELSE 0 END), 0) AS total_abonado,
                            COALESCE(SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN GREATEST(hv.total - COALESCE(ax.total_abonado, 0), 0) ELSE 0 END), 0) AS total_pendiente
                        FROM historial_ventas hv
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = hv.id_venta";
            } else {
                $sql = "SELECT
                            COUNT(*) AS total_registros,
                            SUM(CASE WHEN LOWER(hv.estado) = 'anulado' THEN 1 ELSE 0 END) AS total_anulados,
                            SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN 1 ELSE 0 END) AS total_vigentes,
                            COALESCE(SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN hv.total ELSE 0 END), 0) AS monto_vigente,
                            COALESCE(SUM(CASE WHEN LOWER(hv.estado) <> 'anulado' THEN hv.total ELSE 0 END), 0) AS total_abonado,
                            0.00 AS total_pendiente
                        FROM historial_ventas hv";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $defaults;
            }

            return [
                'total_registros' => (int) ($row['total_registros'] ?? 0),
                'total_vigentes' => (int) ($row['total_vigentes'] ?? 0),
                'total_anulados' => (int) ($row['total_anulados'] ?? 0),
                'monto_vigente' => (float) ($row['monto_vigente'] ?? 0),
                'total_abonado' => (float) ($row['total_abonado'] ?? 0),
                'total_pendiente' => (float) ($row['total_pendiente'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('Error Historial::getResumenGeneral => ' . $e->getMessage());
            return $defaults;
        }
    }

    public function getUltimosRegistros(int $limit = 5): array {
        $limit = max(1, min($limit, 30));

        try {
            if ($this->hasAbonosTable()) {
                $sql = "SELECT 
                            hv.id,
                            hv.id_venta,
                            hv.fecha,
                            hv.total,
                            hv.estado,
                            c.nombre,
                            c.apellido,
                            COALESCE(ax.total_abonado, 0) AS total_abonado,
                            GREATEST(hv.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                            CASE
                                WHEN COALESCE(ax.total_abonado, 0) >= hv.total THEN 'pagado'
                                WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                                ELSE 'pendiente'
                            END AS estado_pago
                        FROM historial_ventas hv
                        INNER JOIN clientes c ON hv.id_cliente = c.id
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = hv.id_venta
                        ORDER BY hv.fecha DESC
                        LIMIT {$limit}";
            } else {
                $sql = "SELECT 
                            hv.id,
                            hv.id_venta,
                            hv.fecha,
                            hv.total,
                            hv.estado,
                            c.nombre,
                            c.apellido,
                            hv.total AS total_abonado,
                            0.00 AS saldo_pendiente,
                            'pagado' AS estado_pago
                        FROM historial_ventas hv
                        INNER JOIN clientes c ON hv.id_cliente = c.id
                        ORDER BY hv.fecha DESC
                        LIMIT {$limit}";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error Historial::getUltimosRegistros => ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Obtener detalle completo para modal/impresión
     */
    public function getDetalleCompleto(int $idHistorial): ?array {
        $registro = $this->getById($idHistorial);
        if (!$registro) {
            return null;
        }

        $idPedido = (int) ($registro['id_pedido'] ?? 0);
        $detalle = [];
        if ($idPedido > 0) {
            $detalle = $this->getDetallePorPedido($idPedido);
        }

        $resumen = [
            'total_items' => 0,
            'total_prendas' => 0,
            'subtotal_productos' => 0.0,
            'total_extras' => 0.0,
            'total_calculado' => 0.0,
            'total_historial' => (float) ($registro['total'] ?? 0)
        ];

        foreach ($detalle as $item) {
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $totalExtra = (float) ($item['total_extra'] ?? 0);

            $resumen['total_items']++;
            $resumen['total_prendas'] += $cantidad;
            $resumen['subtotal_productos'] += $subtotal;
            $resumen['total_extras'] += $totalExtra;
            $resumen['total_calculado'] += ($subtotal + $totalExtra);
        }

        if ($resumen['total_items'] === 0) {
            $resumen['total_items'] = (int) ($registro['total_items'] ?? 0);
            $resumen['total_prendas'] = (int) ($registro['total_prendas'] ?? 0);
        }

        return [
            'registro' => $registro,
            'detalle' => $detalle,
            'resumen' => $resumen
        ];
    }


    private function getDetallePorPedido(int $idPedido): array {
        try {
            $sql = "
                SELECT
                    dp.id,
                    dp.id_producto,
                    p.nombre_producto,
                    dp.cantidad,
                    dp.precio_unitario,
                    dp.subtotal,
                    COALESCE(px.total_extra, 0) AS total_extra,
                    COALESCE(px.personalizaciones, '') AS personalizaciones
                FROM detalle_pedidos dp
                INNER JOIN productos p ON dp.id_producto = p.id
                LEFT JOIN (
                    SELECT
                        dper.id_detalle_pedido,
                        SUM(dper.precio) AS total_extra,
                        GROUP_CONCAT(op.nombre ORDER BY op.nombre SEPARATOR ', ') AS personalizaciones
                    FROM detalle_personalizaciones dper
                    INNER JOIN opciones_personalizacion op ON op.id = dper.id_personalizacion
                    GROUP BY dper.id_detalle_pedido
                ) px ON px.id_detalle_pedido = dp.id
                WHERE dp.id_pedido = :id_pedido
                ORDER BY dp.id ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('Error Historial::getDetallePorPedido => ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Obtener historial filtrado por cliente
     */
    public function getByCliente($idCliente) {
        try {
            $sql = "
                SELECT 
                    hv.id,
                    hv.id_venta,
                    hv.id_cliente,
                    hv.fecha,
                    hv.total,
                    hv.estado,
                    
                    c.nombre,
                    c.apellido,
                    c.cedula

                FROM historial_ventas hv
                INNER JOIN clientes c ON hv.id_cliente = c.id
                WHERE hv.id_cliente = :idCliente
                ORDER BY hv.fecha DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':idCliente', $idCliente, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('Error Historial::getByCliente => ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Insertar registro en historial cuando se realice una venta
     */
    public function registrarHistorial($idVenta, $idCliente, $total, $estado = 'registrado') {
        try {
            $sql = "
                INSERT INTO historial_ventas (id_venta, id_cliente, total, estado)
                VALUES (:id_venta, :id_cliente, :total, :estado)
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':id_venta', $idVenta);
            $stmt->bindParam(':id_cliente', $idCliente);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':estado', $estado);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log('Error Historial::registrarHistorial => ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Cambiar estado de un registro (ej: anulado, entregado)
     */
    public function actualizarEstado($idHistorial, $estado) {
        try {
            $sql = "
                UPDATE historial_ventas 
                SET estado = :estado
                WHERE id = :id
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id', $idHistorial);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log('Error Historial::actualizarEstado => ' . $e->getMessage());
            return false;
        }
    }

    private function hasAbonosTable(): bool {
        if ($this->hasAbonosTableCache !== null) {
            return $this->hasAbonosTableCache;
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'abonos_ventas'");
            $this->hasAbonosTableCache = (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (PDOException $e) {
            $this->hasAbonosTableCache = false;
        }

        return $this->hasAbonosTableCache;
    }
}

?>
