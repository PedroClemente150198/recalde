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
        $sql = "SELECT 
                MONTH(fecha_venta) AS mes,
                SUM(total) AS total
            FROM ventas
            GROUP BY MONTH(fecha_venta)
            ORDER BY mes
        ";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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


    
}

?>
