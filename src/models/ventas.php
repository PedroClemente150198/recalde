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
    private float $epsilon = 0.00001;
    private ?bool $hasAbonosTableCache = null;
    private ?string $abonosTableInitError = null;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getAllVentas(): array {
        try {
            if ($this->hasAbonosTable()) {
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
                            p.estado,
                            COALESCE(ax.total_abonado, 0) AS total_abonado,
                            GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                            CASE
                                WHEN COALESCE(ax.total_abonado, 0) >= v.total THEN 'pagado'
                                WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                                ELSE 'pendiente'
                            END AS estado_pago,
                            ax.ultima_fecha_abono
                        FROM {$this->tableName} v
                        INNER JOIN clientes c ON v.id_cliente = c.id
                        INNER JOIN pedidos p ON v.id_pedido = p.id
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado,
                                MAX(av.fecha_abono) AS ultima_fecha_abono
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = v.id
                        ORDER BY v.fecha_venta DESC";
            } else {
                // Compatibilidad con esquemas legacy sin módulo de abonos.
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
                            p.estado,
                            v.total AS total_abonado,
                            0.00 AS saldo_pendiente,
                            'pagado' AS estado_pago,
                            NULL AS ultima_fecha_abono
                        FROM {$this->tableName} v
                        INNER JOIN clientes c ON v.id_cliente = c.id
                        INNER JOIN pedidos p ON v.id_pedido = p.id
                        ORDER BY v.fecha_venta DESC";
            }

            $query = $this->conn->prepare($sql);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getAllVentas => " . $e->getMessage());
            return [];
        }
    }

    public function getUltimasVentas(int $limit = 5): array {
        $limit = max(1, min($limit, 50));

        try {
            if ($this->hasAbonosTable()) {
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
                            p.estado,
                            COALESCE(ax.total_abonado, 0) AS total_abonado,
                            GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                            CASE
                                WHEN COALESCE(ax.total_abonado, 0) >= v.total THEN 'pagado'
                                WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                                ELSE 'pendiente'
                            END AS estado_pago,
                            ax.ultima_fecha_abono
                        FROM {$this->tableName} v
                        INNER JOIN clientes c ON v.id_cliente = c.id
                        INNER JOIN pedidos p ON v.id_pedido = p.id
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado,
                                MAX(av.fecha_abono) AS ultima_fecha_abono
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = v.id
                        ORDER BY v.fecha_venta DESC
                        LIMIT {$limit}";
            } else {
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
                            p.estado,
                            v.total AS total_abonado,
                            0.00 AS saldo_pendiente,
                            'pagado' AS estado_pago,
                            NULL AS ultima_fecha_abono
                        FROM {$this->tableName} v
                        INNER JOIN clientes c ON v.id_cliente = c.id
                        INNER JOIN pedidos p ON v.id_pedido = p.id
                        ORDER BY v.fecha_venta DESC
                        LIMIT {$limit}";
            }

            $query = $this->conn->prepare($sql);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getUltimasVentas => " . $e->getMessage());
            return [];
        }
    }

    public function getVentaById(int $id): ?array {
        try {
            if ($this->hasAbonosTable()) {
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
                            c.telefono,
                            c.empresa,
                            p.estado,
                            COALESCE(ax.total_abonado, 0) AS total_abonado,
                            GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0) AS saldo_pendiente,
                            CASE
                                WHEN COALESCE(ax.total_abonado, 0) >= v.total THEN 'pagado'
                                WHEN COALESCE(ax.total_abonado, 0) > 0 THEN 'parcial'
                                ELSE 'pendiente'
                            END AS estado_pago,
                            ax.ultima_fecha_abono
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.id_cliente
                        INNER JOIN pedidos p ON p.id = v.id_pedido
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado,
                                MAX(av.fecha_abono) AS ultima_fecha_abono
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = v.id
                        WHERE v.id = :id
                        LIMIT 1";
            } else {
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
                            c.telefono,
                            c.empresa,
                            p.estado,
                            v.total AS total_abonado,
                            0.00 AS saldo_pendiente,
                            'pagado' AS estado_pago,
                            NULL AS ultima_fecha_abono
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.id_cliente
                        INNER JOIN pedidos p ON p.id = v.id_pedido
                        WHERE v.id = :id
                        LIMIT 1";
            }

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

    public function getAbonosByVenta(int $idVenta): array {
        if (!$this->hasAbonosTable()) {
            return [];
        }

        try {
            $sql = "SELECT
                        av.id,
                        av.id_venta,
                        av.monto,
                        av.metodo_pago,
                        av.observacion,
                        av.fecha_abono,
                        av.usuario_registro,
                        u.usuario AS usuario_registro_nombre
                    FROM abonos_ventas av
                    LEFT JOIN usuarios u ON u.id = av.usuario_registro
                    WHERE av.id_venta = :id_venta
                    ORDER BY av.fecha_abono ASC, av.id ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_venta", $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getAbonosByVenta => " . $e->getMessage());
            return [];
        }
    }

    public function getResumenCartera(): array {
        $defaults = [
            'total_ventas' => 0,
            'total_facturado' => 0.0,
            'total_abonado' => 0.0,
            'total_pendiente' => 0.0,
            'ventas_con_saldo' => 0,
            'ventas_sin_abono' => 0,
        ];

        try {
            if ($this->hasAbonosTable()) {
                $sql = "SELECT
                            COUNT(*) AS total_ventas,
                            COALESCE(SUM(v.total), 0) AS total_facturado,
                            COALESCE(SUM(COALESCE(ax.total_abonado, 0)), 0) AS total_abonado,
                            COALESCE(SUM(GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0)), 0) AS total_pendiente,
                            SUM(CASE WHEN GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0) > 0 THEN 1 ELSE 0 END) AS ventas_con_saldo,
                            SUM(CASE WHEN COALESCE(ax.total_abonado, 0) <= 0 THEN 1 ELSE 0 END) AS ventas_sin_abono
                        FROM ventas v
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = v.id";
            } else {
                $sql = "SELECT
                            COUNT(*) AS total_ventas,
                            COALESCE(SUM(v.total), 0) AS total_facturado,
                            COALESCE(SUM(v.total), 0) AS total_abonado,
                            0.00 AS total_pendiente,
                            0 AS ventas_con_saldo,
                            0 AS ventas_sin_abono
                        FROM ventas v";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $defaults;
            }

            return [
                'total_ventas' => (int) ($row['total_ventas'] ?? 0),
                'total_facturado' => (float) ($row['total_facturado'] ?? 0),
                'total_abonado' => (float) ($row['total_abonado'] ?? 0),
                'total_pendiente' => (float) ($row['total_pendiente'] ?? 0),
                'ventas_con_saldo' => (int) ($row['ventas_con_saldo'] ?? 0),
                'ventas_sin_abono' => (int) ($row['ventas_sin_abono'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log("Error Ventas::getResumenCartera => " . $e->getMessage());
            return $defaults;
        }
    }

    public function getClientesConDeuda(int $limit = 10): array {
        $limit = max(1, min($limit, 100));

        try {
            if ($this->hasAbonosTable()) {
                $sql = "SELECT
                            c.id,
                            c.nombre,
                            c.apellido,
                            c.cedula,
                            COUNT(v.id) AS total_ventas_con_deuda,
                            COALESCE(SUM(GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0)), 0) AS deuda_total,
                            MAX(v.fecha_venta) AS ultima_venta
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.id_cliente
                        LEFT JOIN (
                            SELECT
                                av.id_venta,
                                COALESCE(SUM(av.monto), 0) AS total_abonado
                            FROM abonos_ventas av
                            GROUP BY av.id_venta
                        ) ax ON ax.id_venta = v.id
                        WHERE GREATEST(v.total - COALESCE(ax.total_abonado, 0), 0) > 0
                        GROUP BY c.id, c.nombre, c.apellido, c.cedula
                        ORDER BY deuda_total DESC, total_ventas_con_deuda DESC
                        LIMIT {$limit}";
            } else {
                $sql = "SELECT
                            c.id,
                            c.nombre,
                            c.apellido,
                            c.cedula,
                            0 AS total_ventas_con_deuda,
                            0.00 AS deuda_total,
                            NULL AS ultima_venta
                        FROM clientes c
                        WHERE 1 = 0";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Ventas::getClientesConDeuda => " . $e->getMessage());
            return [];
        }
    }

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

    public function syncPedidosEstadoPorCartera(?int $idPedido = null): bool {
        $this->lastError = null;

        $filter = ($idPedido !== null && $idPedido > 0)
            ? " AND p.id = :id_pedido"
            : "";

        try {
            if ($this->hasAbonosTable()) {
                $sqlPaid = "UPDATE pedidos p
                            INNER JOIN ventas v ON v.id_pedido = p.id
                            LEFT JOIN (
                                SELECT
                                    av.id_venta,
                                    COALESCE(SUM(av.monto), 0) AS total_abonado
                                FROM abonos_ventas av
                                GROUP BY av.id_venta
                            ) ax ON ax.id_venta = v.id
                            SET p.estado = 'cancelado'
                            WHERE ROUND(COALESCE(ax.total_abonado, 0), 2) >= ROUND(v.total, 2)" . $filter;

                $stmtPaid = $this->conn->prepare($sqlPaid);
                if ($idPedido !== null && $idPedido > 0) {
                    $stmtPaid->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                }
                $stmtPaid->execute();

                $sqlRestore = "UPDATE pedidos p
                               LEFT JOIN ventas v ON v.id_pedido = p.id
                               LEFT JOIN (
                                   SELECT
                                       av.id_venta,
                                       COALESCE(SUM(av.monto), 0) AS total_abonado
                                   FROM abonos_ventas av
                                   GROUP BY av.id_venta
                               ) ax ON ax.id_venta = v.id
                               SET p.estado = 'entregado'
                               WHERE p.estado = 'cancelado'
                                 AND (v.id IS NULL OR ROUND(COALESCE(ax.total_abonado, 0), 2) < ROUND(v.total, 2))" . $filter;

                $stmtRestore = $this->conn->prepare($sqlRestore);
                if ($idPedido !== null && $idPedido > 0) {
                    $stmtRestore->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                }
                $stmtRestore->execute();
            } else {
                $sqlPaid = "UPDATE pedidos p
                            INNER JOIN ventas v ON v.id_pedido = p.id
                            SET p.estado = 'cancelado'
                            WHERE 1 = 1" . $filter;

                $stmtPaid = $this->conn->prepare($sqlPaid);
                if ($idPedido !== null && $idPedido > 0) {
                    $stmtPaid->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                }
                $stmtPaid->execute();

                $sqlRestore = "UPDATE pedidos p
                               LEFT JOIN ventas v ON v.id_pedido = p.id
                               SET p.estado = 'entregado'
                               WHERE p.estado = 'cancelado'
                                 AND v.id IS NULL" . $filter;

                $stmtRestore = $this->conn->prepare($sqlRestore);
                if ($idPedido !== null && $idPedido > 0) {
                    $stmtRestore->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                }
                $stmtRestore->execute();
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Ventas::syncPedidosEstadoPorCartera => " . $e->getMessage());
            $this->lastError = "No se pudo sincronizar el estado de los pedidos con la cartera.";
            return false;
        }
    }

    public function createVenta(
        int $id_pedido,
        int $id_cliente,
        float $total,
        string $metodo_pago,
        int $usuario_registro = null
    ): bool {
        $idVenta = $this->crearVentaDesdePedido($id_pedido, $total, $metodo_pago, $usuario_registro, $total);
        return $idVenta !== null;
    }

    public function crearVentaDesdePedido(
        int $idPedido,
        float $total,
        string $metodoPago,
        ?int $usuarioRegistro = null,
        ?float $abonoInicial = null
    ): ?int {
        $this->lastError = null;
        $hasAbonosTable = $this->hasAbonosTable();

        $metodoPagoNormalizado = $this->normalizeMetodoPago($metodoPago);
        if ($metodoPagoNormalizado === null) {
            $this->lastError = "Método de pago inválido.";
            return null;
        }

        if ($idPedido <= 0) {
            $this->lastError = "Pedido inválido para registrar la venta.";
            return null;
        }

        if ($total <= 0) {
            $this->lastError = "El total de la venta debe ser mayor a 0.";
            return null;
        }

        if ($abonoInicial === null) {
            $abonoInicial = $total;
        }

        if ($abonoInicial < 0) {
            $this->lastError = "El abono inicial no puede ser negativo.";
            return null;
        }

        if (($abonoInicial - $total) > $this->epsilon) {
            $this->lastError = "El abono inicial no puede superar el total de la venta.";
            return null;
        }

        if (!$hasAbonosTable && abs($abonoInicial - $total) > $this->epsilon) {
            $this->lastError = $this->buildAbonosTableErrorMessage();
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
            $stmtVenta->bindParam(":metodo_pago", $metodoPagoNormalizado, PDO::PARAM_STR);
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

            if ($hasAbonosTable && $abonoInicial > $this->epsilon) {
                $insertado = $this->insertarAbono(
                    $idVenta,
                    $abonoInicial,
                    $metodoPagoNormalizado,
                    'Abono inicial al registrar la venta.',
                    $usuarioRegistro > 0 ? $usuarioRegistro : null
                );

                if (!$insertado) {
                    throw new PDOException("No se pudo registrar el abono inicial.");
                }
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

            if (!$this->syncPedidosEstadoPorCartera($idPedido)) {
                throw new PDOException("No se pudo sincronizar el estado del pedido con la cartera.");
            }

            $this->conn->commit();
            return $idVenta;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Ventas::crearVentaDesdePedido => " . $e->getMessage());

            if ($this->isMissingAbonosTableMessage($e->getMessage())) {
                $this->lastError = $this->buildAbonosTableErrorMessage();
            } else {
                $this->lastError = "No se pudo registrar la venta.";
            }

            return null;
        }
    }

    public function registrarAbono(
        int $idVenta,
        float $monto,
        string $metodoPago,
        ?string $observacion = null,
        ?int $usuarioRegistro = null
    ): ?int {
        $this->lastError = null;

        if (!$this->hasAbonosTable()) {
            $this->lastError = $this->buildAbonosTableErrorMessage();
            return null;
        }

        $metodoPagoNormalizado = $this->normalizeMetodoPago($metodoPago);
        if ($metodoPagoNormalizado === null) {
            $this->lastError = 'Método de pago inválido para el abono.';
            return null;
        }

        if ($idVenta <= 0) {
            $this->lastError = 'Venta inválida para registrar abono.';
            return null;
        }

        if ($monto <= 0) {
            $this->lastError = 'El monto del abono debe ser mayor a 0.';
            return null;
        }

        $observacion = trim((string) $observacion);
        if (strlen($observacion) > 255) {
            $this->lastError = 'La observación no puede superar 255 caracteres.';
            return null;
        }

        try {
            $this->conn->beginTransaction();

            $sqlVenta = "SELECT
                            v.id,
                            v.total,
                            COALESCE((
                                SELECT SUM(av.monto)
                                FROM abonos_ventas av
                                WHERE av.id_venta = v.id
                            ), 0) AS total_abonado
                        FROM ventas v
                        WHERE v.id = :id
                        FOR UPDATE";

            $stmtVenta = $this->conn->prepare($sqlVenta);
            $stmtVenta->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmtVenta->execute();
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

            if (!$venta) {
                $this->conn->rollBack();
                $this->lastError = 'Venta no encontrada.';
                return null;
            }

            $total = (float) ($venta['total'] ?? 0);
            $abonado = (float) ($venta['total_abonado'] ?? 0);
            $saldo = max($total - $abonado, 0.0);

            if ($saldo <= $this->epsilon) {
                $this->conn->rollBack();
                $this->lastError = 'Esta venta ya está pagada en su totalidad.';
                return null;
            }

            if (($monto - $saldo) > $this->epsilon) {
                $this->conn->rollBack();
                $this->lastError = 'El abono supera el saldo pendiente de la venta.';
                return null;
            }

            $insertado = $this->insertarAbono(
                $idVenta,
                $monto,
                $metodoPagoNormalizado,
                $observacion !== '' ? $observacion : null,
                $usuarioRegistro
            );

            if (!$insertado) {
                throw new PDOException('No se pudo guardar el abono.');
            }

            $idAbono = (int) $this->conn->lastInsertId();
            if ($idAbono <= 0) {
                throw new PDOException('No se pudo obtener el ID del abono.');
            }

            $stmtPedido = $this->conn->prepare("SELECT id_pedido FROM ventas WHERE id = :id LIMIT 1");
            $stmtPedido->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmtPedido->execute();
            $idPedido = (int) ($stmtPedido->fetch(PDO::FETCH_ASSOC)['id_pedido'] ?? 0);

            if ($idPedido > 0 && !$this->syncPedidosEstadoPorCartera($idPedido)) {
                throw new PDOException('No se pudo sincronizar el estado del pedido con la cartera.');
            }

            $this->conn->commit();
            return $idAbono;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Error Ventas::registrarAbono => " . $e->getMessage());

            if ($this->isMissingAbonosTableMessage($e->getMessage())) {
                $this->lastError = $this->buildAbonosTableErrorMessage();
            } else {
                $this->lastError = 'No se pudo registrar el abono.';
            }

            return null;
        }
    }

    public function updateVenta(int $id, float $total, string $metodoPago): bool {
        $this->lastError = null;
        $hasAbonosTable = $this->hasAbonosTable();

        $metodoPagoNormalizado = $this->normalizeMetodoPago($metodoPago);
        if ($metodoPagoNormalizado === null) {
            $this->lastError = "Método de pago inválido.";
            return false;
        }

        if ($id <= 0) {
            $this->lastError = "ID de venta inválido.";
            return false;
        }

        if ($total <= 0) {
            $this->lastError = "El total de la venta debe ser mayor a 0.";
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmtVenta = $this->conn->prepare("SELECT id FROM ventas WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmtVenta->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVenta->execute();
            if (!$stmtVenta->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                $this->lastError = 'Venta no encontrada.';
                return false;
            }

            if ($hasAbonosTable) {
                $stmtAbonos = $this->conn->prepare("SELECT COALESCE(SUM(monto), 0) AS total_abonado FROM abonos_ventas WHERE id_venta = :id_venta");
                $stmtAbonos->bindParam(':id_venta', $id, PDO::PARAM_INT);
                $stmtAbonos->execute();
                $abonado = (float) ($stmtAbonos->fetch(PDO::FETCH_ASSOC)['total_abonado'] ?? 0);

                if (($abonado - $total) > $this->epsilon) {
                    $this->conn->rollBack();
                    $this->lastError = 'El total no puede ser menor al valor ya abonado.';
                    return false;
                }
            }

            $sql = "UPDATE ventas
                    SET total = :total,
                        metodo_pago = :metodo_pago
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":total", $total);
            $stmt->bindParam(":metodo_pago", $metodoPagoNormalizado);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmtHistorial = $this->conn->prepare("UPDATE historial_ventas
                                                   SET total = :total
                                                   WHERE id_venta = :id_venta");
            $stmtHistorial->bindParam(":total", $total);
            $stmtHistorial->bindParam(":id_venta", $id, PDO::PARAM_INT);
            $stmtHistorial->execute();

            $stmtPedido = $this->conn->prepare("SELECT id_pedido FROM ventas WHERE id = :id LIMIT 1");
            $stmtPedido->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtPedido->execute();
            $idPedido = (int) ($stmtPedido->fetch(PDO::FETCH_ASSOC)['id_pedido'] ?? 0);

            if ($idPedido > 0 && !$this->syncPedidosEstadoPorCartera($idPedido)) {
                throw new PDOException('No se pudo sincronizar el estado del pedido con la cartera.');
            }

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

    public function deleteVenta(int $id): bool {
        $this->lastError = null;
        $hasAbonosTable = $this->hasAbonosTable();

        if ($id <= 0) {
            $this->lastError = "ID de venta inválido.";
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmtExists = $this->conn->prepare("SELECT id, id_pedido FROM ventas WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmtExists->bindParam(":id", $id, PDO::PARAM_INT);
            $stmtExists->execute();
            $venta = $stmtExists->fetch(PDO::FETCH_ASSOC);
            if (!$venta) {
                $this->conn->rollBack();
                $this->lastError = "Venta no encontrada.";
                return false;
            }
            $idPedido = (int) ($venta['id_pedido'] ?? 0);

            if ($hasAbonosTable) {
                $stmtDeleteAbonos = $this->conn->prepare("DELETE FROM abonos_ventas WHERE id_venta = :id_venta");
                $stmtDeleteAbonos->bindParam(':id_venta', $id, PDO::PARAM_INT);
                $stmtDeleteAbonos->execute();
            }

            $sql = "DELETE FROM ventas WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $this->conn->rollBack();
                $this->lastError = "No se pudo eliminar la venta.";
                return false;
            }

            if ($idPedido > 0 && !$this->syncPedidosEstadoPorCartera($idPedido)) {
                throw new PDOException("No se pudo sincronizar el estado del pedido con la cartera.");
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error Ventas::deleteVenta => " . $e->getMessage());
            $this->lastError = "No se pudo eliminar la venta.";
            return false;
        }
    }

    private function insertarAbono(
        int $idVenta,
        float $monto,
        string $metodoPago,
        ?string $observacion,
        ?int $usuarioRegistro
    ): bool {
        $sqlAbono = "INSERT INTO abonos_ventas
                        (id_venta, monto, metodo_pago, observacion, usuario_registro)
                     VALUES
                        (:id_venta, :monto, :metodo_pago, :observacion, :usuario_registro)";

        $stmtAbono = $this->conn->prepare($sqlAbono);
        $stmtAbono->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
        $stmtAbono->bindParam(':monto', $monto);
        $stmtAbono->bindParam(':metodo_pago', $metodoPago, PDO::PARAM_STR);
        $stmtAbono->bindValue(':observacion', $observacion !== null && $observacion !== '' ? $observacion : null, ($observacion !== null && $observacion !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);

        if ($usuarioRegistro !== null && $usuarioRegistro > 0) {
            $stmtAbono->bindParam(':usuario_registro', $usuarioRegistro, PDO::PARAM_INT);
        } else {
            $stmtAbono->bindValue(':usuario_registro', null, PDO::PARAM_NULL);
        }

        return $stmtAbono->execute();
    }

    private function hasAbonosTable(): bool {
        if ($this->hasAbonosTableCache !== null) {
            return $this->hasAbonosTableCache;
        }

        try {
            $exists = $this->abonosTableExists();

            if (!$exists) {
                // Auto-recuperación para ambientes donde faltó correr migraciones.
                $this->createAbonosTableIfMissing();
                $exists = $this->abonosTableExists();
            }

            $this->hasAbonosTableCache = $exists;
        } catch (PDOException $e) {
            $this->abonosTableInitError = $e->getMessage();
            $this->hasAbonosTableCache = false;
        }

        return $this->hasAbonosTableCache;
    }

    private function abonosTableExists(): bool {
        try {
            // Si la tabla existe, este SELECT simple debe funcionar incluso sin filas.
            $this->conn->query("SELECT 1 FROM abonos_ventas LIMIT 1");
            return true;
        } catch (PDOException $e) {
            if ($this->isMissingAbonosTableMessage($e->getMessage())) {
                return false;
            }

            $this->abonosTableInitError = $e->getMessage();
            return false;
        }
    }

    private function createAbonosTableIfMissing(): void {
        $this->abonosTableInitError = null;

        try {
            $sqlWithForeignKeys = "CREATE TABLE IF NOT EXISTS abonos_ventas (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        id_venta INT UNSIGNED NOT NULL,
                        monto DECIMAL(12,2) NOT NULL,
                        metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
                        observacion VARCHAR(255) NULL,
                        fecha_abono TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        usuario_registro INT UNSIGNED NULL,
                        INDEX idx_abonos_venta (id_venta),
                        INDEX idx_abonos_fecha (fecha_abono),
                        INDEX idx_abonos_usuario (usuario_registro),
                        CONSTRAINT fk_abonos_ventas_venta
                            FOREIGN KEY (id_venta) REFERENCES ventas (id)
                            ON UPDATE CASCADE
                            ON DELETE CASCADE,
                        CONSTRAINT fk_abonos_ventas_usuario
                            FOREIGN KEY (usuario_registro) REFERENCES usuarios (id)
                            ON UPDATE CASCADE
                            ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->conn->exec($sqlWithForeignKeys);
            return;
        } catch (PDOException $e) {
            $this->abonosTableInitError = $e->getMessage();
            error_log("Error Ventas::createAbonosTableIfMissing (fk) => " . $e->getMessage());
        }

        try {
            // Fallback sin llaves foráneas para servidores con permisos limitados.
            $sqlFallback = "CREATE TABLE IF NOT EXISTS abonos_ventas (
                                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                id_venta INT UNSIGNED NOT NULL,
                                monto DECIMAL(12,2) NOT NULL,
                                metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
                                observacion VARCHAR(255) NULL,
                                fecha_abono TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                usuario_registro INT UNSIGNED NULL,
                                INDEX idx_abonos_venta (id_venta),
                                INDEX idx_abonos_fecha (fecha_abono),
                                INDEX idx_abonos_usuario (usuario_registro)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->conn->exec($sqlFallback);
        } catch (PDOException $e) {
            $prev = $this->abonosTableInitError ? $this->abonosTableInitError . ' | ' : '';
            $this->abonosTableInitError = $prev . $e->getMessage();
            error_log("Error Ventas::createAbonosTableIfMissing (fallback) => " . $e->getMessage());
        }
    }

    private function isMissingAbonosTableMessage(string $message): bool {
        $message = strtolower($message);
        return str_contains($message, 'abonos_ventas')
            && (str_contains($message, "doesn't exist")
                || str_contains($message, '1146')
                || str_contains($message, 'unknown table')
                || str_contains($message, 'base table or view not found'));
    }

    private function buildAbonosTableErrorMessage(): string {
        $base = 'No fue posible habilitar pagos parciales porque falta la tabla abonos_ventas.';
        $hint = ' Ejecuta storage/migrate_abonos_ventas.sql con un usuario administrador de MySQL.';
        $detail = strtolower(trim((string) $this->abonosTableInitError));

        if ($detail === '') {
            return $base . $hint;
        }

        if (str_contains($detail, 'access denied') || str_contains($detail, 'command denied')) {
            return $base . ' El usuario actual no tiene permisos CREATE/REFERENCES.' . $hint;
        }

        if (str_contains($detail, 'foreign key') || str_contains($detail, 'errno: 150')) {
            return $base . ' Falló la creación con llaves foráneas.' . $hint;
        }

        return $base . $hint;
    }

    private function normalizeMetodoPago(string $metodoPago): ?string {
        $metodoPago = strtolower(trim($metodoPago));
        return in_array($metodoPago, $this->metodosPermitidos, true)
            ? $metodoPago
            : null;
    }
}

?>
