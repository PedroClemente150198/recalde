CREATE TABLE IF NOT EXISTS abonos_ventas (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
