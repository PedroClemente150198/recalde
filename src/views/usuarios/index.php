<h1>Lista de Usuarios</h1>

<?php if (!empty($usuarios)): ?>
    <ul>
    <?php foreach ($usuarios as $u): ?>
        <li><?= htmlspecialchars($u['usuario']) ?> - <?= htmlspecialchars($u['correo']) ?></li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No se encontraron usuarios.</p>
<?php endif; 
?>
