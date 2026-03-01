<?php

namespace Models;

use Database;
use PDO;
use PDOException;
use models\usuarios as UsuariosModel;

class Dashboard {
    private PDO $conn;
    private const DEVELOPER_UI_PREFERENCES = [
        'ventas_show_actions_column' => 'ventasShowActionsColumn',
        'historial_show_actions_column' => 'historialShowActionsColumn',
    ];
    private string $table_ventas = 'ventas';
    private string $table_clientes = 'clientes';
    private ?bool $supportsForcePasswordColumn = null;
    private ?string $lastDeveloperError = null;
    private ?array $developerTablesCache = null;
    private ?string $databaseName = null;
    private ?bool $developerUiPreferencesTableReady = null;
    private ?array $sharedUiPreferencesCache = null;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getLastError(): ?string {
        return $this->lastDeveloperError;
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

    public function getDeveloperOverview(?string $selectedTable = null): array {
        $dbStatus = $this->isDatabaseAlive() ? "ok" : "error";
        $tables = $this->getDeveloperTableCatalog();

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
            ],
            "usuariosCredenciales" => $this->getDeveloperUsersCredentials(),
            "tables" => $tables,
            "tableManager" => $this->getDeveloperTableSnapshot($selectedTable, $tables)
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

    public function clearDeveloperTable(string $tableName): ?int {
        $this->lastDeveloperError = null;

        $tableName = $this->requireDeveloperTableName($tableName);
        if ($tableName === null) {
            return null;
        }

        if (in_array($tableName, ['usuarios', 'roles'], true)) {
            $this->lastDeveloperError = 'Por seguridad, no se permite vaciar esta tabla completa desde Developer.';
            return null;
        }

        try {
            $sql = "DELETE FROM " . $this->quoteIdentifier($tableName);
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $deletedRows = (int) $stmt->rowCount();

            $columns = $this->getDeveloperTableColumns($tableName);
            $hasAutoIncrement = false;
            foreach ($columns as $column) {
                if (!empty($column['is_auto_increment'])) {
                    $hasAutoIncrement = true;
                    break;
                }
            }

            if ($hasAutoIncrement) {
                $this->conn->exec("ALTER TABLE " . $this->quoteIdentifier($tableName) . " AUTO_INCREMENT = 1");
            }

            return $deletedRows;
        } catch (PDOException $e) {
            error_log("Error Dashboard::clearDeveloperTable => " . $e->getMessage());
            $this->lastDeveloperError = $this->normalizeDeveloperPdoError(
                $e,
                'No se pudo vaciar la tabla seleccionada.'
            );
            return null;
        }
    }

    public function deleteDeveloperTableRow(string $tableName, array $primaryValues): bool {
        $this->lastDeveloperError = null;

        $tableName = $this->requireDeveloperTableName($tableName);
        if ($tableName === null) {
            return false;
        }

        $columns = $this->getDeveloperTableColumns($tableName);
        $primaryKey = $this->getDeveloperPrimaryKeyColumns($columns);
        $normalizedPrimaryValues = $this->normalizeDeveloperPrimaryValues($primaryKey, $primaryValues);

        if ($normalizedPrimaryValues === null) {
            return false;
        }

        if ($tableName === 'usuarios') {
            $idUsuario = (int) ($normalizedPrimaryValues['id'] ?? 0);
            if (!$this->guardDeveloperUserMutation('delete', $idUsuario, [])) {
                return false;
            }
        }

        try {
            $where = $this->buildDeveloperPrimaryWhere($primaryKey, 'pk');
            $sql = "DELETE FROM " . $this->quoteIdentifier($tableName)
                . " WHERE {$where['sql']} LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            foreach ($normalizedPrimaryValues as $columnName => $value) {
                $stmt->bindValue(':pk_' . $columnName, $value);
            }
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $this->lastDeveloperError = 'No se encontro la fila a eliminar.';
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Dashboard::deleteDeveloperTableRow => " . $e->getMessage());
            $this->lastDeveloperError = $this->normalizeDeveloperPdoError(
                $e,
                'No se pudo eliminar la fila seleccionada.'
            );
            return false;
        }
    }

    public function updateDeveloperTableRow(string $tableName, array $primaryValues, array $values): bool {
        $this->lastDeveloperError = null;

        $tableName = $this->requireDeveloperTableName($tableName);
        if ($tableName === null) {
            return false;
        }

        $columns = $this->getDeveloperTableColumns($tableName);
        $primaryKey = $this->getDeveloperPrimaryKeyColumns($columns);
        $normalizedPrimaryValues = $this->normalizeDeveloperPrimaryValues($primaryKey, $primaryValues);

        if ($normalizedPrimaryValues === null) {
            return false;
        }

        $editableColumns = [];
        foreach ($columns as $column) {
            $columnName = (string) ($column['name'] ?? '');
            if ($columnName === '' || !$this->isDeveloperEditableColumn($tableName, $column)) {
                continue;
            }

            if (!array_key_exists($columnName, $values)) {
                continue;
            }

            $editableColumns[$columnName] = $this->normalizeDeveloperColumnValue($values[$columnName], $column);
        }

        if ($tableName === 'usuarios') {
            $idUsuario = (int) ($normalizedPrimaryValues['id'] ?? 0);
            if (!$this->guardDeveloperUserMutation('update', $idUsuario, $editableColumns)) {
                return false;
            }
        }

        if ($editableColumns === []) {
            $this->lastDeveloperError = 'No hay cambios validos para guardar.';
            return false;
        }

        try {
            $setParts = [];
            foreach (array_keys($editableColumns) as $columnName) {
                $setParts[] = $this->quoteIdentifier($columnName) . " = :set_" . $columnName;
            }

            $where = $this->buildDeveloperPrimaryWhere($primaryKey, 'pk');
            $sql = "UPDATE " . $this->quoteIdentifier($tableName)
                . " SET " . implode(', ', $setParts)
                . " WHERE {$where['sql']} LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            foreach ($editableColumns as $columnName => $value) {
                $stmt->bindValue(':set_' . $columnName, $value);
            }
            foreach ($normalizedPrimaryValues as $columnName => $value) {
                $stmt->bindValue(':pk_' . $columnName, $value);
            }

            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error Dashboard::updateDeveloperTableRow => " . $e->getMessage());
            $this->lastDeveloperError = $this->normalizeDeveloperPdoError(
                $e,
                'No se pudo actualizar la fila seleccionada.'
            );
            return false;
        }
    }

    private function getDeveloperTableSnapshot(?string $selectedTable = null, ?array $tables = null, int $limit = 25): array {
        $catalog = is_array($tables) ? $tables : $this->getDeveloperTableCatalog();
        $availableTableNames = array_map(
            static fn(array $item): string => (string) ($item['table'] ?? ''),
            $catalog
        );
        $availableTableNames = array_values(array_filter($availableTableNames, static fn(string $name): bool => $name !== ''));

        $resolvedTable = $this->resolveDeveloperSelectedTable($selectedTable, $availableTableNames);
        if ($resolvedTable === null) {
            return [
                'selectedTable' => '',
                'columns' => [],
                'rows' => [],
                'primaryKey' => [],
                'limit' => $limit
            ];
        }

        $columns = $this->getDeveloperTableColumns($resolvedTable);
        $primaryKey = $this->getDeveloperPrimaryKeyColumns($columns);

        return [
            'selectedTable' => $resolvedTable,
            'columns' => $columns,
            'rows' => $this->getDeveloperTableRows($resolvedTable, $columns, $primaryKey, $limit),
            'primaryKey' => $primaryKey,
            'limit' => $limit
        ];
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

    private function getDeveloperUsersCredentials(): array {
        try {
            $forceColumnExpr = $this->supportsForcePasswordColumn()
                ? "u.debe_cambiar_contrasena"
                : "0";
            $sql = "SELECT
                        u.id,
                        u.usuario,
                        u.correo,
                        u.contrasena,
                        u.estado,
                        {$forceColumnExpr} AS debe_cambiar_contrasena,
                        r.rol AS nombre_rol
                    FROM usuarios u
                    INNER JOIN roles r ON r.id = u.id_rol
                    ORDER BY u.id DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $storedPassword = (string) ($row['contrasena'] ?? '');
                $info = password_get_info($storedPassword);
                $row['password_is_hash'] = ((int) ($info['algo'] ?? 0)) !== 0;
            }
            unset($row);

            return $rows;
        } catch (PDOException $e) {
            error_log("Error Dashboard::getDeveloperUsersCredentials => " . $e->getMessage());
            return [];
        }
    }

    private function getDeveloperTableCatalog(): array {
        $tableNames = $this->getDeveloperAvailableTables();
        $catalog = [];

        foreach ($tableNames as $tableName) {
            $columns = $this->getDeveloperTableColumns($tableName);
            $primaryKey = $this->getDeveloperPrimaryKeyColumns($columns);

            $catalog[] = [
                'table' => $tableName,
                'rowCount' => $this->countRows($tableName),
                'columnCount' => count($columns),
                'primaryKey' => $primaryKey,
                'supportsBulkClear' => !in_array($tableName, ['usuarios', 'roles'], true)
            ];
        }

        return $catalog;
    }

    private function getDeveloperAvailableTables(): array {
        if ($this->developerTablesCache !== null) {
            return $this->developerTablesCache;
        }

        try {
            $sql = "SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = :schema
                      AND TABLE_TYPE = 'BASE TABLE'
                    ORDER BY TABLE_NAME ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':schema', $this->getCurrentDatabaseName(), PDO::PARAM_STR);
            $stmt->execute();

            $this->developerTablesCache = array_map(
                static fn(array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            error_log("Error Dashboard::getDeveloperAvailableTables => " . $e->getMessage());
            $this->developerTablesCache = [];
        }

        return $this->developerTablesCache;
    }

    private function getDeveloperTableColumns(string $tableName): array {
        if (!$this->isDeveloperTableAllowed($tableName)) {
            return [];
        }

        try {
            $sql = "SELECT
                        COLUMN_NAME,
                        COLUMN_TYPE,
                        DATA_TYPE,
                        IS_NULLABLE,
                        COLUMN_DEFAULT,
                        COLUMN_KEY,
                        EXTRA,
                        GENERATION_EXPRESSION
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = :schema
                      AND TABLE_NAME = :table
                    ORDER BY ORDINAL_POSITION ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':schema', $this->getCurrentDatabaseName(), PDO::PARAM_STR);
            $stmt->bindValue(':table', $tableName, PDO::PARAM_STR);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = [];

            foreach ($rows as $row) {
                $extra = strtolower((string) ($row['EXTRA'] ?? ''));
                $generationExpression = trim((string) ($row['GENERATION_EXPRESSION'] ?? ''));
                $columnName = (string) ($row['COLUMN_NAME'] ?? '');
                $isPrimary = strtoupper((string) ($row['COLUMN_KEY'] ?? '')) === 'PRI';
                $isGenerated = str_contains($extra, 'generated') || $generationExpression !== '';
                $isAutoIncrement = str_contains($extra, 'auto_increment');

                $columns[] = [
                    'name' => $columnName,
                    'column_type' => (string) ($row['COLUMN_TYPE'] ?? ''),
                    'data_type' => strtolower((string) ($row['DATA_TYPE'] ?? '')),
                    'is_nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'NO')) === 'YES',
                    'default' => $row['COLUMN_DEFAULT'] ?? null,
                    'column_key' => strtoupper((string) ($row['COLUMN_KEY'] ?? '')),
                    'extra' => (string) ($row['EXTRA'] ?? ''),
                    'is_primary' => $isPrimary,
                    'is_generated' => $isGenerated,
                    'is_auto_increment' => $isAutoIncrement,
                    'is_editable' => $this->isDeveloperEditableColumn($tableName, [
                        'name' => $columnName,
                        'column_key' => strtoupper((string) ($row['COLUMN_KEY'] ?? '')),
                        'extra' => (string) ($row['EXTRA'] ?? ''),
                        'is_primary' => $isPrimary,
                        'is_generated' => $isGenerated,
                        'is_auto_increment' => $isAutoIncrement
                    ])
                ];
            }

            return $columns;
        } catch (PDOException $e) {
            error_log("Error Dashboard::getDeveloperTableColumns => " . $e->getMessage());
            return [];
        }
    }

    private function getDeveloperTableRows(string $tableName, array $columns, array $primaryKey, int $limit): array {
        if ($columns === []) {
            return [];
        }

        $quotedColumns = [];
        foreach ($columns as $column) {
            $columnName = (string) ($column['name'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $quotedColumns[] = $this->quoteIdentifier($columnName);
        }

        if ($quotedColumns === []) {
            return [];
        }

        $orderColumns = $primaryKey;
        if ($orderColumns === []) {
            $firstColumn = (string) ($columns[0]['name'] ?? '');
            $orderColumns = $firstColumn !== '' ? [$firstColumn] : [];
        }

        $orderBy = '';
        if ($orderColumns !== []) {
            $parts = [];
            foreach ($orderColumns as $columnName) {
                $parts[] = $this->quoteIdentifier($columnName) . ' DESC';
            }
            $orderBy = ' ORDER BY ' . implode(', ', $parts);
        }

        try {
            $sql = "SELECT " . implode(', ', $quotedColumns)
                . " FROM " . $this->quoteIdentifier($tableName)
                . $orderBy
                . " LIMIT :limit";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Dashboard::getDeveloperTableRows => " . $e->getMessage());
            return [];
        }
    }

    private function getDeveloperPrimaryKeyColumns(array $columns): array {
        $primaryKey = [];

        foreach ($columns as $column) {
            if (!empty($column['is_primary']) && !empty($column['name'])) {
                $primaryKey[] = (string) $column['name'];
            }
        }

        return $primaryKey;
    }

    private function resolveDeveloperSelectedTable(?string $selectedTable, array $availableTableNames): ?string {
        $normalizedSelection = trim((string) ($selectedTable ?? ''));
        if ($normalizedSelection !== '' && in_array($normalizedSelection, $availableTableNames, true)) {
            return $normalizedSelection;
        }

        if (in_array('usuarios', $availableTableNames, true)) {
            return 'usuarios';
        }

        return $availableTableNames[0] ?? null;
    }

    private function isDeveloperEditableColumn(string $tableName, array $column): bool {
        $columnName = (string) ($column['name'] ?? '');
        if ($columnName === '') {
            return false;
        }

        if (!empty($column['is_primary'])) {
            return false;
        }

        if (!empty($column['is_auto_increment']) || !empty($column['is_generated'])) {
            return false;
        }

        if ($tableName === 'usuarios' && $columnName === 'contrasena') {
            return false;
        }

        return true;
    }

    private function normalizeDeveloperColumnValue(mixed $value, array $column): mixed {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = is_string($value)
            ? trim($value)
            : $value;

        if ($normalized === '' && !empty($column['is_nullable'])) {
            return null;
        }

        return $normalized;
    }

    private function normalizeDeveloperPrimaryValues(array $primaryKey, array $values): ?array {
        if ($primaryKey === []) {
            $this->lastDeveloperError = 'La tabla seleccionada no tiene clave primaria editable.';
            return null;
        }

        $normalized = [];
        foreach ($primaryKey as $columnName) {
            if (!array_key_exists($columnName, $values)) {
                $this->lastDeveloperError = 'No se recibio la clave primaria completa para la fila.';
                return null;
            }
            $normalized[$columnName] = $values[$columnName];
        }

        return $normalized;
    }

    private function buildDeveloperPrimaryWhere(array $primaryKey, string $prefix): array {
        $parts = [];

        foreach ($primaryKey as $columnName) {
            $parts[] = $this->quoteIdentifier($columnName) . ' = :' . $prefix . '_' . $columnName;
        }

        return [
            'sql' => implode(' AND ', $parts)
        ];
    }

    private function requireDeveloperTableName(string $tableName): ?string {
        $tableName = trim($tableName);
        if ($tableName === '') {
            $this->lastDeveloperError = 'Debes seleccionar una tabla valida.';
            return null;
        }

        if (!$this->isDeveloperTableAllowed($tableName)) {
            $this->lastDeveloperError = 'La tabla solicitada no esta disponible en este entorno.';
            return null;
        }

        return $tableName;
    }

    private function isDeveloperTableAllowed(string $tableName): bool {
        return in_array($tableName, $this->getDeveloperAvailableTables(), true);
    }

    private function getCurrentDatabaseName(): string {
        if ($this->databaseName !== null) {
            return $this->databaseName;
        }

        try {
            $stmt = $this->conn->query('SELECT DATABASE() AS db_name');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->databaseName = (string) ($row['db_name'] ?? '');
        } catch (PDOException $e) {
            error_log("Error Dashboard::getCurrentDatabaseName => " . $e->getMessage());
            $this->databaseName = '';
        }

        return $this->databaseName;
    }

    private function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function guardDeveloperUserMutation(string $action, int $idUsuario, array $changes): bool {
        if ($idUsuario <= 0) {
            $this->lastDeveloperError = 'Usuario invalido.';
            return false;
        }

        $usuariosModel = new UsuariosModel();
        $usuarioObjetivo = $usuariosModel->getUserById($idUsuario);
        if (!$usuarioObjetivo) {
            $this->lastDeveloperError = 'Usuario no encontrado.';
            return false;
        }

        if ($action === 'delete') {
            if ($usuariosModel->isLastActiveAdmin($idUsuario)) {
                $this->lastDeveloperError = 'No puedes eliminar el ultimo administrador activo.';
                return false;
            }

            return true;
        }

        $rolActual = strtolower(trim((string) ($usuarioObjetivo['nombre_rol'] ?? '')));
        $estadoActual = strtolower(trim((string) ($usuarioObjetivo['estado'] ?? '')));

        $rolObjetivo = $rolActual;
        if (array_key_exists('id_rol', $changes)) {
            $roleName = $usuariosModel->getRoleNameById((int) $changes['id_rol']);
            if ($roleName === null || $roleName === '') {
                $this->lastDeveloperError = 'Rol invalido para el usuario.';
                return false;
            }
            $rolObjetivo = $roleName;
        }

        $estadoObjetivo = array_key_exists('estado', $changes)
            ? strtolower(trim((string) $changes['estado']))
            : $estadoActual;

        $pierdeAdminActivo = $rolActual === 'admin'
            && $estadoActual === 'activo'
            && !($rolObjetivo === 'admin' && $estadoObjetivo === 'activo');

        if ($pierdeAdminActivo && $usuariosModel->isLastActiveAdmin($idUsuario)) {
            $this->lastDeveloperError = 'No puedes dejar al sistema sin administradores activos.';
            return false;
        }

        return true;
    }

    private function normalizeDeveloperPdoError(PDOException $e, string $fallbackMessage): string {
        if ((string) $e->getCode() === '23000') {
            return 'La accion viola relaciones o reglas de integridad de la base de datos.';
        }

        return $fallbackMessage;
    }

    private function supportsForcePasswordColumn(): bool {
        if ($this->supportsForcePasswordColumn !== null) {
            return $this->supportsForcePasswordColumn;
        }

        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM usuarios LIKE 'debe_cambiar_contrasena'");
            $this->supportsForcePasswordColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Dashboard::supportsForcePasswordColumn => " . $e->getMessage());
            $this->supportsForcePasswordColumn = false;
        }

        return $this->supportsForcePasswordColumn;
    }

    public function getSharedUiPreferences(): array {
        if ($this->sharedUiPreferencesCache !== null) {
            return $this->sharedUiPreferencesCache;
        }

        $preferences = [];
        foreach (self::DEVELOPER_UI_PREFERENCES as $storageKey => $viewKey) {
            $preferences[$viewKey] = true;
        }

        if (!$this->ensureDeveloperUiPreferencesTable()) {
            $this->sharedUiPreferencesCache = $preferences;
            return $preferences;
        }

        try {
            $stmt = $this->conn->query(
                'SELECT preference_key, preference_value FROM developer_ui_preferences'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $storageKey = strtolower(trim((string) ($row['preference_key'] ?? '')));
                if (!array_key_exists($storageKey, self::DEVELOPER_UI_PREFERENCES)) {
                    continue;
                }

                $viewKey = self::DEVELOPER_UI_PREFERENCES[$storageKey];
                $preferences[$viewKey] = $this->normalizeDeveloperUiPreferenceBoolean(
                    $row['preference_value'] ?? 1
                );
            }
        } catch (PDOException $e) {
            error_log('Error Dashboard::getSharedUiPreferences => ' . $e->getMessage());
        }

        $this->sharedUiPreferencesCache = $preferences;
        return $preferences;
    }

    public function saveSharedUiPreference(string $key, bool $value, ?int $updatedBy = null): bool {
        $key = strtolower(trim($key));
        if (!array_key_exists($key, self::DEVELOPER_UI_PREFERENCES)) {
            $this->lastDeveloperError = 'La preferencia UI solicitada no esta soportada.';
            return false;
        }

        if (!$this->ensureDeveloperUiPreferencesTable()) {
            return false;
        }

        try {
            $sql = "
                INSERT INTO developer_ui_preferences (
                    preference_key,
                    preference_value,
                    updated_by
                ) VALUES (
                    :preference_key,
                    :preference_value,
                    :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    preference_value = VALUES(preference_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':preference_key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':preference_value', $value ? 1 : 0, PDO::PARAM_INT);

            if ($updatedBy !== null && $updatedBy > 0) {
                $stmt->bindValue(':updated_by', $updatedBy, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
            }

            $stmt->execute();

            $preferences = $this->getSharedUiPreferences();
            $preferences[self::DEVELOPER_UI_PREFERENCES[$key]] = $value;
            $this->sharedUiPreferencesCache = $preferences;

            return true;
        } catch (PDOException $e) {
            error_log('Error Dashboard::saveSharedUiPreference => ' . $e->getMessage());
            $this->lastDeveloperError = 'No se pudo guardar la preferencia UI compartida.';
            return false;
        }
    }

    private function ensureDeveloperUiPreferencesTable(): bool {
        if ($this->developerUiPreferencesTableReady !== null) {
            return $this->developerUiPreferencesTableReady;
        }

        try {
            $this->conn->exec(
                "CREATE TABLE IF NOT EXISTS developer_ui_preferences (
                    preference_key VARCHAR(120) NOT NULL PRIMARY KEY,
                    preference_value TINYINT(1) NOT NULL DEFAULT 1,
                    updated_by INT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $this->developerUiPreferencesTableReady = true;
        } catch (PDOException $e) {
            error_log('Error Dashboard::ensureDeveloperUiPreferencesTable => ' . $e->getMessage());
            $this->lastDeveloperError = 'No se pudo preparar el almacenamiento global de preferencias UI.';
            $this->developerUiPreferencesTableReady = false;
        }

        return $this->developerUiPreferencesTableReady;
    }

    private function normalizeDeveloperUiPreferenceBoolean(mixed $value): bool {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $normalized === null ? (bool) $value : $normalized;
    }

    
}

?>
