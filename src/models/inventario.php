<?php

namespace Models;

use Database;
use PDO;
use PDOException;

class Inventario {
    private PDO $conn;
    private ?string $lastError = null;
    private bool $stockColumnsAvailable = false;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
        $this->stockColumnsAvailable = $this->detectStockColumns();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function hasRealStockColumns(): bool {
        return $this->stockColumnsAvailable;
    }

    /**
     * Obtiene el inventario con stock real si existen columnas físicas.
     * Si no existen, cae a una estimación basada en ventas históricas.
     */
    public function getInventario(): array {
        try {
            $stockExpression = $this->stockColumnsAvailable
                ? "p.stock_actual"
                : "GREATEST(100 - COALESCE(vt.unidades_vendidas, 0), 0)";
            $stockMinimoExpression = $this->stockColumnsAvailable
                ? "p.stock_minimo"
                : "20";

            $sql = "
                SELECT
                    p.id,
                    p.id_categoria,
                    c.tipo_categoria,
                    p.nombre_producto,
                    p.descripcion,
                    p.precio_base,
                    p.estado AS estado_producto,
                    p.fecha_actualizacion,
                    {$stockExpression} AS stock,
                    {$stockExpression} AS stock_actual,
                    {$stockMinimoExpression} AS stock_minimo,
                    GREATEST(({$stockExpression}) - COALESCE(cp.unidades_comprometidas, 0), 0) AS stock_disponible,
                    COALESCE(vt.unidades_vendidas, 0) AS unidades_vendidas,
                    COALESCE(cp.unidades_comprometidas, 0) AS unidades_comprometidas,
                    vt.ultima_venta,
                    CASE
                        WHEN ({$stockExpression}) <= 0 THEN 'agotado'
                        WHEN ({$stockExpression}) <= ({$stockMinimoExpression}) THEN 'critico'
                        WHEN GREATEST(({$stockExpression}) - COALESCE(cp.unidades_comprometidas, 0), 0) <= ({$stockMinimoExpression}) THEN 'ajustado'
                        ELSE 'estable'
                    END AS estado_stock
                FROM productos p
                LEFT JOIN categorias c ON p.id_categoria = c.id
                LEFT JOIN (
                    SELECT
                        dp.id_producto,
                        SUM(dp.cantidad) AS unidades_vendidas,
                        MAX(v.fecha_venta) AS ultima_venta
                    FROM detalle_pedidos dp
                    INNER JOIN ventas v ON v.id_pedido = dp.id_pedido
                    GROUP BY dp.id_producto
                ) vt ON vt.id_producto = p.id
                LEFT JOIN (
                    SELECT
                        dp.id_producto,
                        SUM(dp.cantidad) AS unidades_comprometidas
                    FROM detalle_pedidos dp
                    INNER JOIN pedidos pe ON pe.id = dp.id_pedido
                    LEFT JOIN ventas v ON v.id_pedido = pe.id
                    WHERE pe.estado IN ('pendiente', 'procesando', 'listo', 'entregado')
                      AND v.id IS NULL
                    GROUP BY dp.id_producto
                ) cp ON cp.id_producto = p.id
                ORDER BY
                    CASE
                        WHEN ({$stockExpression}) <= 0 THEN 0
                        WHEN ({$stockExpression}) <= ({$stockMinimoExpression}) THEN 1
                        WHEN GREATEST(({$stockExpression}) - COALESCE(cp.unidades_comprometidas, 0), 0) <= ({$stockMinimoExpression}) THEN 2
                        ELSE 3
                    END ASC,
                    p.fecha_actualizacion DESC,
                    p.nombre_producto ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Inventario::getInventario => " . $e->getMessage());
            return [];
        }
    }

    /**
     * Categorías activas para formulario de productos
     */
    public function getCategoriasActivas(): array {
        try {
            $sql = "SELECT id, tipo_categoria
                    FROM categorias
                    WHERE estado = 'activo'
                    ORDER BY tipo_categoria";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Inventario::getCategoriasActivas => " . $e->getMessage());
            return [];
        }
    }

    public function getCategorias(): array {
        try {
            $sql = "SELECT id, tipo_categoria, estado
                    FROM categorias
                    ORDER BY id DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error Inventario::getCategorias => " . $e->getMessage());
            return [];
        }
    }

    public function crearCategoria(string $tipoCategoria, string $estado = "activo"): ?int {
        $this->lastError = null;

        $tipoCategoria = trim($tipoCategoria);
        $estado = strtolower(trim($estado));

        if ($tipoCategoria === "") {
            $this->lastError = "El nombre de la categoría es obligatorio.";
            return null;
        }

        if (!in_array($estado, ["activo", "inactivo"], true)) {
            $this->lastError = "Estado de categoría inválido.";
            return null;
        }

        if ($this->categoriaNombreExiste($tipoCategoria)) {
            $this->lastError = "Ya existe una categoría con ese nombre.";
            return null;
        }

        try {
            $sql = "INSERT INTO categorias (tipo_categoria, estado)
                    VALUES (:tipo_categoria, :estado)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":tipo_categoria", $tipoCategoria, PDO::PARAM_STR);
            $stmt->bindParam(":estado", $estado, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error Inventario::crearCategoria => " . $e->getMessage());
            $this->lastError = "No se pudo crear la categoría.";
            return null;
        }
    }

    public function actualizarCategoria(int $idCategoria, string $tipoCategoria, string $estado): bool {
        $this->lastError = null;

        $tipoCategoria = trim($tipoCategoria);
        $estado = strtolower(trim($estado));

        if ($idCategoria <= 0) {
            $this->lastError = "ID de categoría inválido.";
            return false;
        }

        if ($tipoCategoria === "") {
            $this->lastError = "El nombre de la categoría es obligatorio.";
            return false;
        }

        if (!in_array($estado, ["activo", "inactivo"], true)) {
            $this->lastError = "Estado de categoría inválido.";
            return false;
        }

        if ($this->categoriaNombreExiste($tipoCategoria, $idCategoria)) {
            $this->lastError = "Ya existe otra categoría con ese nombre.";
            return false;
        }

        try {
            $sql = "UPDATE categorias
                    SET tipo_categoria = :tipo_categoria,
                        estado = :estado
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idCategoria, PDO::PARAM_INT);
            $stmt->bindParam(":tipo_categoria", $tipoCategoria, PDO::PARAM_STR);
            $stmt->bindParam(":estado", $estado, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() <= 0 && !$this->categoriaExiste($idCategoria)) {
                $this->lastError = "Categoría no encontrada.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Inventario::actualizarCategoria => " . $e->getMessage());
            $this->lastError = "No se pudo actualizar la categoría.";
            return false;
        }
    }

    public function eliminarCategoria(int $idCategoria): bool {
        $this->lastError = null;

        if ($idCategoria <= 0) {
            $this->lastError = "ID de categoría inválido.";
            return false;
        }

        if (!$this->categoriaExiste($idCategoria)) {
            $this->lastError = "Categoría no encontrada.";
            return false;
        }

        try {
            $sql = "DELETE FROM categorias WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idCategoria, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $this->lastError = "No se pudo eliminar la categoría.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Inventario::eliminarCategoria => " . $e->getMessage());
            $this->lastError = "No se pudo eliminar la categoría.";
            return false;
        }
    }

    public function crearProducto(
        ?int $idCategoria,
        string $nombre,
        ?string $descripcion,
        float $precioBase,
        string $estado,
        ?int $stockActual = null,
        ?int $stockMinimo = null
    ): ?int {
        $this->lastError = null;

        $nombre = trim($nombre);
        $descripcion = $this->normalizeNullable($descripcion);
        $estado = strtolower(trim($estado));
        $stockActual = $stockActual ?? 0;
        $stockMinimo = $stockMinimo ?? 5;

        if ($nombre === "") {
            $this->lastError = "El nombre del producto es obligatorio.";
            return null;
        }

        if ($precioBase <= 0) {
            $this->lastError = "El precio base debe ser mayor a 0.";
            return null;
        }

        if (!in_array($estado, ["activo", "inactivo"], true)) {
            $this->lastError = "Estado de producto inválido.";
            return null;
        }

        if ($stockActual < 0 || $stockMinimo < 0) {
            $this->lastError = "El stock y el stock mínimo deben ser valores válidos.";
            return null;
        }

        if ($idCategoria !== null && $idCategoria > 0 && !$this->categoriaExiste($idCategoria)) {
            $this->lastError = "La categoría seleccionada no existe.";
            return null;
        }

        try {
            $sql = "INSERT INTO productos
                        (
                            id_categoria,
                            nombre_producto,
                            descripcion,
                            precio_base,
                            " . ($this->stockColumnsAvailable ? "stock_actual, stock_minimo, " : "") . "
                            estado
                        )
                    VALUES
                        (
                            :id_categoria,
                            :nombre_producto,
                            :descripcion,
                            :precio_base,
                            " . ($this->stockColumnsAvailable ? ":stock_actual, :stock_minimo, " : "") . "
                            :estado
                        )";

            $stmt = $this->conn->prepare($sql);
            if ($idCategoria !== null && $idCategoria > 0) {
                $stmt->bindParam(":id_categoria", $idCategoria, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":id_categoria", null, PDO::PARAM_NULL);
            }
            $stmt->bindParam(":nombre_producto", $nombre, PDO::PARAM_STR);
            $stmt->bindValue(":descripcion", $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(":precio_base", $precioBase);
            if ($this->stockColumnsAvailable) {
                $stmt->bindParam(":stock_actual", $stockActual, PDO::PARAM_INT);
                $stmt->bindParam(":stock_minimo", $stockMinimo, PDO::PARAM_INT);
            }
            $stmt->bindParam(":estado", $estado, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error Inventario::crearProducto => " . $e->getMessage());
            $this->lastError = "No se pudo crear el producto.";
            return null;
        }
    }

    public function actualizarProducto(
        int $idProducto,
        ?int $idCategoria,
        string $nombre,
        ?string $descripcion,
        float $precioBase,
        string $estado,
        ?int $stockActual = null,
        ?int $stockMinimo = null
    ): bool {
        $this->lastError = null;

        $nombre = trim($nombre);
        $descripcion = $this->normalizeNullable($descripcion);
        $estado = strtolower(trim($estado));
        $stockActual = $stockActual ?? 0;
        $stockMinimo = $stockMinimo ?? 5;

        if ($idProducto <= 0) {
            $this->lastError = "ID de producto inválido.";
            return false;
        }

        if ($nombre === "") {
            $this->lastError = "El nombre del producto es obligatorio.";
            return false;
        }

        if ($precioBase <= 0) {
            $this->lastError = "El precio base debe ser mayor a 0.";
            return false;
        }

        if (!in_array($estado, ["activo", "inactivo"], true)) {
            $this->lastError = "Estado de producto inválido.";
            return false;
        }

        if ($stockActual < 0 || $stockMinimo < 0) {
            $this->lastError = "El stock y el stock mínimo deben ser valores válidos.";
            return false;
        }

        if ($idCategoria !== null && $idCategoria > 0 && !$this->categoriaExiste($idCategoria)) {
            $this->lastError = "La categoría seleccionada no existe.";
            return false;
        }

        try {
            $sql = "UPDATE productos SET
                        id_categoria = :id_categoria,
                        nombre_producto = :nombre_producto,
                        descripcion = :descripcion,
                        precio_base = :precio_base,
                        " . ($this->stockColumnsAvailable ? "stock_actual = :stock_actual, stock_minimo = :stock_minimo," : "") . "
                        estado = :estado
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idProducto, PDO::PARAM_INT);
            if ($idCategoria !== null && $idCategoria > 0) {
                $stmt->bindParam(":id_categoria", $idCategoria, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":id_categoria", null, PDO::PARAM_NULL);
            }
            $stmt->bindParam(":nombre_producto", $nombre, PDO::PARAM_STR);
            $stmt->bindValue(":descripcion", $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(":precio_base", $precioBase);
            if ($this->stockColumnsAvailable) {
                $stmt->bindParam(":stock_actual", $stockActual, PDO::PARAM_INT);
                $stmt->bindParam(":stock_minimo", $stockMinimo, PDO::PARAM_INT);
            }
            $stmt->bindParam(":estado", $estado, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() <= 0 && !$this->productoExiste($idProducto)) {
                $this->lastError = "Producto no encontrado.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Inventario::actualizarProducto => " . $e->getMessage());
            $this->lastError = "No se pudo actualizar el producto.";
            return false;
        }
    }

    public function eliminarProducto(int $idProducto): bool {
        $this->lastError = null;

        if ($idProducto <= 0) {
            $this->lastError = "ID de producto inválido.";
            return false;
        }

        if (!$this->productoExiste($idProducto)) {
            $this->lastError = "Producto no encontrado.";
            return false;
        }

        try {
            $sql = "DELETE FROM productos WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $idProducto, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() <= 0) {
                $this->lastError = "No se pudo eliminar el producto.";
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error Inventario::eliminarProducto => " . $e->getMessage());
            if ((string) $e->getCode() === "23000") {
                $this->lastError = "No se puede eliminar este producto porque tiene pedidos o ventas relacionadas.";
            } else {
                $this->lastError = "No se pudo eliminar el producto.";
            }
            return false;
        }
    }

    private function categoriaExiste(int $idCategoria): bool {
        $sql = "SELECT id FROM categorias WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $idCategoria, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function categoriaNombreExiste(string $tipoCategoria, ?int $excludeId = null): bool {
        if ($excludeId !== null) {
            $sql = "SELECT id
                    FROM categorias
                    WHERE LOWER(tipo_categoria) = LOWER(:tipo_categoria)
                      AND id <> :id
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":tipo_categoria", $tipoCategoria, PDO::PARAM_STR);
            $stmt->bindParam(":id", $excludeId, PDO::PARAM_INT);
            $stmt->execute();
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $sql = "SELECT id
                FROM categorias
                WHERE LOWER(tipo_categoria) = LOWER(:tipo_categoria)
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":tipo_categoria", $tipoCategoria, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function productoExiste(int $idProducto): bool {
        $sql = "SELECT id FROM productos WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $idProducto, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeNullable(?string $value): ?string {
        $value = trim((string) $value);
        return $value === "" ? null : $value;
    }

    private function detectStockColumns(): bool {
        try {
            return $this->columnExists("productos", "stock_actual")
                && $this->columnExists("productos", "stock_minimo");
        } catch (PDOException $e) {
            error_log("Error Inventario::detectStockColumns => " . $e->getMessage());
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        if (!preg_match('/^[a-z_]+$/i', $table)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":column", $column, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0) > 0;
    }
}
