SET @schema_name = DATABASE();

SET @stock_actual_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'productos'
      AND COLUMN_NAME = 'stock_actual'
);

SET @sql = IF(
    @stock_actual_exists = 0,
    'ALTER TABLE productos ADD COLUMN stock_actual INT UNSIGNED NOT NULL DEFAULT 0 AFTER precio_base',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @stock_actual_exists = 0,
    'UPDATE productos p
     LEFT JOIN (
         SELECT dp.id_producto, SUM(dp.cantidad) AS unidades_vendidas
         FROM detalle_pedidos dp
         INNER JOIN ventas v ON v.id_pedido = dp.id_pedido
         GROUP BY dp.id_producto
     ) vt ON vt.id_producto = p.id
     SET p.stock_actual = GREATEST(100 - COALESCE(vt.unidades_vendidas, 0), 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stock_minimo_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'productos'
      AND COLUMN_NAME = 'stock_minimo'
);

SET @sql = IF(
    @stock_minimo_exists = 0,
    'ALTER TABLE productos ADD COLUMN stock_minimo INT UNSIGNED NOT NULL DEFAULT 5 AFTER stock_actual',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @stock_minimo_exists = 0,
    'UPDATE productos SET stock_minimo = 20',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
