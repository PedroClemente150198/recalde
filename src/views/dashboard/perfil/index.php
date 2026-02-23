<?php $usuario = $usuario ?? []; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/perfil.css">
</head>
<body>
    
    <div class="perfil-container">

        <h1>Perfil de Usuario</h1>

        <p><strong>Nombre de usuario:</strong> 
            <?= htmlspecialchars((string)($usuario['usuario'] ?? '')) ?>
        </p>

        <p><strong>Contraseña:</strong>
            <?= htmlspecialchars((string)($usuario['contrasena'] ?? '')) ?>
        </p>

        <p><strong>Correo electrónico:</strong> 
            <?= htmlspecialchars((string)($usuario['correo'] ?? '')) ?>
        </p>

        <p><strong>Rol:</strong>
            <?= htmlspecialchars((string)($usuario['nombre_rol'] ?? '')) ?>
        </p>

        <p><strong>Estado:</strong>
            <?= (($usuario['estado'] ?? 0) == 1 ? "Activo" : "Inactivo") ?>
        </p>

        <p><strong>Fecha de registro:</strong> 
            <?= htmlspecialchars((string)($usuario['fecha_registro'] ?? '')) ?>
        </p>

    </div>

</body>
</html>
