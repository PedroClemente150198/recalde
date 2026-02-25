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
