<?php
$clientes = $clientes ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/clientes.css">
</head>
<body>

<div class="clientes-container">
    <div class="clientes-header">
        <h2>Listado de Clientes</h2>
        <button class="btn nuevo" type="button" data-action="nuevo-cliente">Nuevo Cliente</button>
    </div>

    <table class="clientes-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre Completo</th>
                <th>Cédula</th>
                <th>Teléfono</th>
                <th>Empresa</th>
                <th>Usuario Registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($clientes)): ?>
            <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><?= (int) ($cliente['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars((string) ($cliente['cedula'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($cliente['telefono'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($cliente['empresa'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($cliente['usuario_registro'] ?? '-')) ?></td>
                    <td>
                        <button
                            class="btn editar"
                            type="button"
                            data-action="editar-cliente"
                            data-id="<?= (int) ($cliente['id'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars((string) ($cliente['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-apellido="<?= htmlspecialchars((string) ($cliente['apellido'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-cedula="<?= htmlspecialchars((string) ($cliente['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-telefono="<?= htmlspecialchars((string) ($cliente['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-direccion="<?= htmlspecialchars((string) ($cliente['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-empresa="<?= htmlspecialchars((string) ($cliente['empresa'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            Editar
                        </button>
                        <button
                            class="btn eliminar"
                            type="button"
                            data-action="eliminar-cliente"
                            data-id="<?= (int) ($cliente['id'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="clientes-empty">No hay clientes registrados.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="cliente-modal" id="cliente-edit-modal" hidden>
    <div class="cliente-modal-backdrop" data-close="cliente-edit-modal"></div>
    <div class="cliente-modal-content" role="dialog" aria-modal="true" aria-labelledby="cliente-edit-title">
        <div class="cliente-modal-header">
            <h3 id="cliente-edit-title">Editar Cliente</h3>
            <button class="btn cerrar" type="button" data-close="cliente-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="cliente-edit-form" class="cliente-create-form">
            <input type="hidden" id="cliente-edit-id" name="id">

            <div class="cliente-grid">
                <div class="cliente-field">
                    <label for="cliente-edit-nombre">Nombre</label>
                    <input id="cliente-edit-nombre" name="nombre" type="text" maxlength="100" required>
                </div>
                <div class="cliente-field">
                    <label for="cliente-edit-apellido">Apellido</label>
                    <input id="cliente-edit-apellido" name="apellido" type="text" maxlength="100" required>
                </div>
                <div class="cliente-field">
                    <label for="cliente-edit-cedula">Cédula</label>
                    <input id="cliente-edit-cedula" name="cedula" type="text" maxlength="15">
                </div>
                <div class="cliente-field">
                    <label for="cliente-edit-telefono">Teléfono</label>
                    <input id="cliente-edit-telefono" name="telefono" type="text" maxlength="30">
                </div>
                <div class="cliente-field">
                    <label for="cliente-edit-empresa">Empresa</label>
                    <input id="cliente-edit-empresa" name="empresa" type="text" maxlength="100">
                </div>
            </div>

            <div class="cliente-field">
                <label for="cliente-edit-direccion">Dirección</label>
                <textarea id="cliente-edit-direccion" name="direccion" rows="3"></textarea>
            </div>

            <p class="cliente-create-feedback" id="cliente-edit-feedback" hidden></p>

            <div class="cliente-actions">
                <button class="btn cancelar" type="button" data-close="cliente-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="cliente-edit-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="cliente-modal" id="cliente-create-modal" hidden>
    <div class="cliente-modal-backdrop" data-close="cliente-create-modal"></div>
    <div class="cliente-modal-content" role="dialog" aria-modal="true" aria-labelledby="cliente-create-title">
        <div class="cliente-modal-header">
            <h3 id="cliente-create-title">Nuevo Cliente</h3>
            <button class="btn cerrar" type="button" data-close="cliente-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="cliente-create-form" class="cliente-create-form">
            <div class="cliente-grid">
                <div class="cliente-field">
                    <label for="cliente-nombre">Nombre</label>
                    <input id="cliente-nombre" name="nombre" type="text" maxlength="100" required>
                </div>
                <div class="cliente-field">
                    <label for="cliente-apellido">Apellido</label>
                    <input id="cliente-apellido" name="apellido" type="text" maxlength="100" required>
                </div>
                <div class="cliente-field">
                    <label for="cliente-cedula">Cédula</label>
                    <input id="cliente-cedula" name="cedula" type="text" maxlength="15">
                </div>
                <div class="cliente-field">
                    <label for="cliente-telefono">Teléfono</label>
                    <input id="cliente-telefono" name="telefono" type="text" maxlength="30">
                </div>
                <div class="cliente-field">
                    <label for="cliente-empresa">Empresa</label>
                    <input id="cliente-empresa" name="empresa" type="text" maxlength="100">
                </div>
            </div>

            <div class="cliente-field">
                <label for="cliente-direccion">Dirección</label>
                <textarea id="cliente-direccion" name="direccion" rows="3"></textarea>
            </div>

            <p class="cliente-create-feedback" id="cliente-create-feedback" hidden></p>

            <div class="cliente-actions">
                <button class="btn cancelar" type="button" data-close="cliente-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="cliente-create-submit">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
