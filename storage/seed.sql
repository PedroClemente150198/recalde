-- RECALDE seed data
-- Usuario inicial:
-- usuario: admin
-- contraseña: admin123

INSERT INTO roles (id, rol)
VALUES
    (1, 'admin'),
    (2, 'desarrollador'),
    (3, 'operador')
ON DUPLICATE KEY UPDATE rol = VALUES(rol);

INSERT INTO usuarios (id_rol, usuario, correo, contrasena, estado)
SELECT
    1,
    'admin',
    'admin@recalde.local',
    '$2y$10$3x3kiM3QF8FyHm9QXFUSWu/VZZun/Oba0SljAH1dCMjTHcPAmWOY2',
    'activo'
WHERE NOT EXISTS (
    SELECT 1
    FROM usuarios
    WHERE usuario = 'admin'
);

INSERT INTO categorias (tipo_categoria, estado)
SELECT 'General', 'activo'
WHERE NOT EXISTS (
    SELECT 1
    FROM categorias
    WHERE tipo_categoria = 'General'
);
