<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Dashboard {
    private PDO $conn;
    private string $table_ventas = 'ventas';
    private string $table_clientes = 'clientes';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /**
     * Total de ventas (cantidad de registros en la tabla ventas)
     */
    public function getTotalVentas() {
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM ventas");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['total'] ?? 0;

        } catch (PDOException $e) {
            error_log("Error Dashboard::getTotalVentas => " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Total generado en ventas
     */
    public function getIngresosTotales() {
        try {
            $stmt = $this->conn->query("SELECT SUM(total) AS ingresos FROM ventas");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['ingresos'] ?? 0;

        } catch (PDOException $e) {
            error_log("Error Dashboard::getIngresosTotales => " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cantidad total de pedidos
     */
    public function getTotalPedidos() {
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) AS total FROM pedidos");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['total'] ?? 0;

        } catch (PDOException $e) {
            error_log("Error Dashboard::getTotalPedidos => " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Productos más vendidos (TOP 5)
     */
    public function getProductosMasVendidos() {
        try {
            $sql = "SELECT 
                p.nombre_producto,
                SUM(dp.cantidad) AS total_vendido
            FROM ventas v
            INNER JOIN pedidos pe ON v.id_pedido = pe.id
            INNER JOIN detalle_pedidos dp ON pe.id = dp.id_pedido
            INNER JOIN productos p ON dp.id_producto = p.id
            GROUP BY p.id, p.nombre_producto
            ORDER BY total_vendido DESC
            LIMIT 5
            ";

            $stmt = $this->conn->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Dashboard::getProductosMasVendidos => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ventas por día (últimos 7 días)
     */
    public function getVentasPorDia() {
        try {
            $sql = "SELECT 
                    DATE(fecha_venta) AS fecha,
                    SUM(total) AS total_dia
                FROM ventas
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha DESC
                LIMIT 7
            ";

            $stmt = $this->conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Asegurar formato correcto
            foreach ($rows as &$r) {
                $r['fecha'] = $r['fecha'] ?? '0000-00-00';
                $r['total_dia'] = $r['total_dia'] ?? 0;
            }

            return $rows;

        } catch (PDOException $e) {
            error_log("Error Dashboard::getVentasPorDia => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Últimos 5 clientes registrados
     */
    public function getUltimosClientes() {
        try {
            $sql = "SELECT 
                    id,
                    nombre,
                    apellido,
                    telefono
                FROM {$this->table_clientes} ORDER BY id DESC LIMIT 5
            ";

            $stmt = $this->conn->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error Dashboard::getUltimosClientes => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Últimos 5 pedidos realizados
     */
    public function getUltimosPedidos() {
        try {
            $sql = "
                SELECT 
                    p.id,
                    p.fecha_creacion AS fecha,
                    p.estado,
                    c.nombre,
                    c.apellido
                FROM pedidos p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                ORDER BY p.fecha_creacion DESC
                LIMIT 5
            ";

            $stmt = $this->conn->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Asegurar datos válidos
            foreach ($results as &$r) {
                $r['fecha'] = $r['fecha'] ?? 'Sin fecha';
                $r['nombre'] = $r['nombre'] ?? 'Cliente';
                $r['apellido'] = $r['apellido'] ?? 'Desconocido';
            }

            return $results;

        } catch (PDOException $e) {
            error_log("Error Dashboard::getUltimosPedidos => " . $e->getMessage());
            return [];
        }
    }

    public function getVentasPorMes() {
        return $this->getIngresosSerie('mes');
    }

    public function getIngresosSerie(string $periodo = 'mes'): array {
        $periodo = strtolower(trim($periodo));
        if (!in_array($periodo, ['dia', 'semana', 'mes'], true)) {
            $periodo = 'mes';
        }

        try {
            if ($periodo === 'dia') {
                $sql = "SELECT
                            DATE(fecha_venta) AS bucket,
                            SUM(total) AS total
                        FROM ventas
                        GROUP BY DATE(fecha_venta)
                        ORDER BY DATE(fecha_venta) ASC";
                $stmt = $this->conn->query($sql);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($periodo === 'semana') {
                $sql = "SELECT
                            YEAR(fecha_venta) AS anio,
                            WEEK(fecha_venta, 1) AS semana,
                            SUM(total) AS total
                        FROM ventas
                        GROUP BY YEAR(fecha_venta), WEEK(fecha_venta, 1)
                        ORDER BY YEAR(fecha_venta) ASC, WEEK(fecha_venta, 1) ASC";
                $stmt = $this->conn->query($sql);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $sql = "SELECT
                        YEAR(fecha_venta) AS anio,
                        MONTH(fecha_venta) AS mes,
                        SUM(total) AS total
                    FROM ventas
                    GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
                    ORDER BY YEAR(fecha_venta) ASC, MONTH(fecha_venta) ASC";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Dashboard::getIngresosSerie => " . $e->getMessage());
            return [];
        }
    }

    public function getPedidosPorEstado() {
        $sql = "SELECT 
                estado,
                COUNT(*) AS cantidad
            FROM pedidos
            GROUP BY estado
        ";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUltimasVentas() {
        $sql = "SELECT v.id, v.total, v.fecha_venta, c.nombre, c.apellido
            FROM ventas v
            JOIN clientes c ON v.id_cliente = c.id
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeveloperOverview(): array {
        $dbStatus = $this->isDatabaseAlive() ? "ok" : "error";

        return [
            "info" => [
                "appName" => defined("APP_NAME") ? APP_NAME : "Aplicación",
                "appVersion" => defined("APP_VERSION") ? APP_VERSION : "1.0.0",
                "phpVersion" => PHP_VERSION,
                "dbStatus" => $dbStatus,
                "generatedAt" => date("Y-m-d H:i:s")
            ],
            "resumen" => [
                "usuarios" => $this->countRows("usuarios"),
                "clientes" => $this->countRows("clientes"),
                "productos" => $this->countRows("productos"),
                "pedidos" => $this->countRows("pedidos"),
                "ventas" => $this->countRows("ventas"),
                "historial" => $this->countRows("historial_ventas")
            ],
            "integridad" => [
                "pedidosSinDetalle" => $this->countScalar("
                    SELECT COUNT(*) AS total
                    FROM pedidos p
                    LEFT JOIN detalle_pedidos d ON d.id_pedido = p.id
                    WHERE d.id IS NULL
                "),
                "pedidosTotalesDescuadrados" => $this->countScalar("
                    SELECT COUNT(*) AS total
                    FROM pedidos p
                    LEFT JOIN (
                        SELECT id_pedido, IFNULL(SUM(subtotal), 0) AS total_detalle
                        FROM detalle_pedidos
                        GROUP BY id_pedido
                    ) d ON d.id_pedido = p.id
                    WHERE ROUND(IFNULL(p.total, 0), 2) <> ROUND(IFNULL(d.total_detalle, 0), 2)
                "),
                "ventasSinHistorial" => $this->countScalar("
                    SELECT COUNT(*) AS total
                    FROM ventas v
                    LEFT JOIN historial_ventas h ON h.id_venta = v.id
                    WHERE h.id IS NULL
                ")
            ]
        ];
    }

    public function recalculatePedidosTotals(): ?int {
        try {
            $sql = "
                UPDATE pedidos p
                LEFT JOIN (
                    SELECT id_pedido, IFNULL(SUM(subtotal), 0) AS total_calculado
                    FROM detalle_pedidos
                    GROUP BY id_pedido
                ) d ON d.id_pedido = p.id
                SET p.total = IFNULL(d.total_calculado, 0)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error Dashboard::recalculatePedidosTotals => " . $e->getMessage());
            return null;
        }
    }

    private function isDatabaseAlive(): bool {
        try {
            $stmt = $this->conn->query("SELECT 1");
            return (bool) $stmt;
        } catch (PDOException $e) {
            error_log("Error Dashboard::isDatabaseAlive => " . $e->getMessage());
            return false;
        }
    }

    private function countRows(string $tableName): int {
        $tableName = trim($tableName);
        if ($tableName === "") {
            return 0;
        }

        return $this->countScalar("SELECT COUNT(*) AS total FROM {$tableName}");
    }

    private function countScalar(string $sql): int {
        try {
            $stmt = $this->conn->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($row["total"] ?? 0);
        } catch (PDOException $e) {
            error_log("Error Dashboard::countScalar => " . $e->getMessage());
            return 0;
        }
    }

    
}

?>
