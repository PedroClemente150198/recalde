<?php
$ventas = $ventas ?? [];
$pedidosDisponibles = $pedidosDisponibles ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/ventas.css">
</head>
<body>
<div class="ventas-container">
    <div class="ventas-header">
        <h2>Listado de Ventas</h2>
        <button class="btn nuevo" type="button" data-action="nueva-venta">Nueva Venta</button>
    </div>

    <table class="ventas-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Cédula</th>
                <th>Total</th>
                <th>Método</th>
                <th>Fecha</th>
                <th>Estado Pedido</th>
                <th class="acciones-col">Acciones</th>
            </tr>
        </thead>

        <tbody>
        <?php if (!empty($ventas)): ?>
            <?php foreach ($ventas as $venta): ?>
                <?php
                    $estado = strtolower((string) ($venta['estado'] ?? 'desconocido'));
                    $estadoClass = preg_replace('/[^a-z0-9_-]/', '', $estado) ?: 'desconocido';
                ?>
                <tr>
                    <td><?= (int) ($venta['id'] ?? 0) ?></td>
                    <td>#<?= (int) ($venta['id_pedido'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(trim(($venta['nombre'] ?? '') . ' ' . ($venta['apellido'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars((string) ($venta['cedula'] ?? '-')) ?></td>
                    <td>$<?= number_format((float) ($venta['total'] ?? 0), 2) ?></td>
                    <td><?= htmlspecialchars((string) ($venta['metodo_pago'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($venta['fecha_venta'] ?? '-')) ?></td>
                    <td>
                        <span class="status-<?= htmlspecialchars($estadoClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($venta['estado'] ?? 'Desconocido')) ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <button
                            class="btn editar"
                            type="button"
                            data-action="editar-venta"
                            data-id="<?= (int) ($venta['id'] ?? 0) ?>"
                            data-total="<?= htmlspecialchars((string) ($venta['total'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                            data-metodo="<?= htmlspecialchars((string) ($venta['metodo_pago'] ?? 'efectivo'), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            Editar
                        </button>
                        <button
                            class="btn eliminar"
                            type="button"
                            data-action="eliminar-venta"
                            data-id="<?= (int) ($venta['id'] ?? 0) ?>"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" style="text-align:center;">No hay ventas registradas</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="ventas-modal" id="venta-create-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-create-modal"></div>
    <div class="ventas-modal-content" role="dialog" aria-modal="true" aria-labelledby="venta-create-title">
        <div class="ventas-modal-header">
            <h3 id="venta-create-title">Nueva Venta</h3>
            <button class="btn cerrar" type="button" data-close="venta-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="venta-create-form" class="venta-form">
            <label for="venta-create-pedido">Pedido</label>
            <select id="venta-create-pedido" name="id_pedido" required>
                <option value="">Selecciona un pedido</option>
                <?php foreach ($pedidosDisponibles as $pedido): ?>
                    <option
                        value="<?= (int) ($pedido['id'] ?? 0) ?>"
                        data-total="<?= htmlspecialchars((string) ($pedido['total'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <?= '#'.(int) ($pedido['id'] ?? 0) ?>
                        <?= ' - '.htmlspecialchars(trim(($pedido['nombre'] ?? '') . ' ' . ($pedido['apellido'] ?? ''))) ?>
                        <?= !empty($pedido['cedula']) ? ' - ' . htmlspecialchars((string) $pedido['cedula']) : '' ?>
                        <?= ' - $' . number_format((float) ($pedido['total'] ?? 0), 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="venta-create-total">Total</label>
            <input id="venta-create-total" name="total" type="number" step="0.01" min="0.01" required>

            <label for="venta-create-metodo">Método de Pago</label>
            <select id="venta-create-metodo" name="metodo_pago" required>
                <option value="efectivo" selected>Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="otro">Otro</option>
            </select>

            <p class="venta-feedback" id="venta-create-feedback" hidden></p>

            <div class="venta-actions">
                <button class="btn cancelar" type="button" data-close="venta-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="venta-create-submit">Guardar Venta</button>
            </div>
        </form>
    </div>
</div>

<div class="ventas-modal" id="venta-edit-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-edit-modal"></div>
    <div class="ventas-modal-content" role="dialog" aria-modal="true" aria-labelledby="venta-edit-title">
        <div class="ventas-modal-header">
            <h3 id="venta-edit-title">Editar Venta</h3>
            <button class="btn cerrar" type="button" data-close="venta-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="venta-edit-form" class="venta-form">
            <input type="hidden" id="venta-edit-id" name="id">

            <label for="venta-edit-total">Total</label>
            <input id="venta-edit-total" name="total" type="number" step="0.01" min="0.01" required>

            <label for="venta-edit-metodo">Método de Pago</label>
            <select id="venta-edit-metodo" name="metodo_pago" required>
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="otro">Otro</option>
            </select>

            <p class="venta-feedback" id="venta-edit-feedback" hidden></p>

            <div class="venta-actions">
                <button class="btn cancelar" type="button" data-close="venta-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="venta-edit-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
