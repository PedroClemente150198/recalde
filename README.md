# RECALDE

Aplicación MVC en PHP para gestión de clientes, pedidos, ventas, inventario, perfil e historial.

## Requisitos

- PHP 8.1+ con extensiones `pdo` y `pdo_mysql`
- MySQL 8+ (o MariaDB compatible)
- Apache con `mod_rewrite` habilitado (recomendado)

## Configuración rápida

1. Crea la base de datos:

```sql
CREATE DATABASE RECALDE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa esquema y seed:

```bash
mysql -u root -p RECALDE < storage/schema.sql
mysql -u root -p RECALDE < storage/seed.sql
```

Si tu base de datos ya existía desde una versión anterior, ejecuta también:

```sql
ALTER TABLE usuarios
ADD COLUMN debe_cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS detalle_pedido_medidas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_detalle_pedido INT UNSIGNED NOT NULL,
    nombre_persona VARCHAR(120) NOT NULL,
    referencia VARCHAR(120) NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    medidas TEXT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_detalle_pedido_medidas_detalle (id_detalle_pedido),
    CONSTRAINT fk_detalle_pedido_medidas_detalle
        FOREIGN KEY (id_detalle_pedido) REFERENCES detalle_pedidos (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

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
);
```

3. Crea tu archivo de entorno:

```bash
cp .env.example .env
```

4. Ajusta credenciales en `.env`:

```env
DB_HOST=localhost
DB_NAME=RECALDE
DB_USER=root
DB_PASS=12345678
APP_DEBUG=1
```

## Acceso inicial

- Usuario: `admin`
- Contraseña: `admin123`

## Ejecución

### Apache (recomendado)

- Puedes apuntar el DocumentRoot a:
  - `/var/www/html/recalde` (usa `index.php` en raíz), o
  - `/var/www/html/recalde/public` (usa `public/index.php`).

### Servidor embebido de PHP (desarrollo)

```bash
php -S 127.0.0.1:8000
```

Luego abre:

- `http://127.0.0.1:8000/?route=login`

## Notas técnicas

- El sistema usa `password_hash/password_verify`.
- Si existían usuarios legacy con contraseña en texto plano, se migran automáticamente a hash en el próximo login exitoso.
- `APP_DEBUG=1` habilita errores en pantalla; en producción usa `APP_DEBUG=0`.
- El rol `desarrollador` puede resetear contraseñas desde el módulo Developer: se genera una contraseña temporal y se marca cambio obligatorio al próximo login (si la columna `debe_cambiar_contrasena` existe).

## Medidas Personalizadas Por Persona

El módulo de pedidos ahora permite capturar medidas por persona dentro de cada producto del pedido.

Casos cubiertos:

- Cliente individual: puedes registrar una persona con sus medidas para cada prenda.
- Institución o empresa: puedes crear un solo pedido y agregar varias personas (alumnos, reclutas, colaboradores), cada una con su referencia y medidas.

Flujo recomendado:

1. Crea/selecciona el cliente (si es institucional, usa el campo `empresa` para identificar la entidad).
2. Crea el pedido y agrega los productos (camisa, pantalón, buso, etc.).
3. En cada producto, usa `Medidas por persona (opcional)` para agregar:
   - nombre de la persona,
   - referencia (curso/área/cargo),
   - cantidad asignada,
   - detalle de medidas.
4. El sistema valida que la suma de cantidades por medidas no supere la cantidad del producto.

## Cartera y Abonos

El módulo `Ventas` incluye control de pagos parciales:

- Registrar venta con pago completo o con `abono inicial`.
- Registrar abonos posteriores por cada venta.
- Calcular automáticamente:
  - total abonado,
  - saldo pendiente,
  - estado de pago (`pagado`, `parcial`, `pendiente`).
- Visualizar resumen de cartera:
  - total facturado,
  - total abonado,
  - deuda pendiente,
  - clientes con deuda.
