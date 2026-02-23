<?php
$clientes = $clientes ?? [];
$productos = $productos ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/pedidos.css">
</head>

<body>

<div class="pedidos-container">

    <div class="pedidos-header">
        <h2>Listado de Pedidos</h2>
        <button class="btn nuevo" type="button" data-action="nuevo-pedido">Nuevo Pedido</button>
    </div>

    <table class="pedidos-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cédula</th>
                <th>Total</th>
                <th>Fecha Pedido</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
        <?php if (!empty($pedidos)): ?>
            <?php foreach ($pedidos as $pedido): ?>

                <tr>
                    <td><?= htmlspecialchars($pedido['id']) ?></td>
                    <td><?= htmlspecialchars($pedido['nombre'] . " " . $pedido['apellido']) ?></td>
                    <td><?= htmlspecialchars($pedido['cedula']) ?></td>
                    <td>$<?= number_format($pedido['total'], 2) ?></td>
                    <td><?= htmlspecialchars($pedido['fecha_creacion']) ?></td>

                    <td>
                        <?php
                            $estado = strtolower((string) ($pedido['estado'] ?? 'desconocido'));
                            $estadoClass = preg_replace('/[^a-z0-9_-]/', '', $estado) ?: 'desconocido';
                        ?>
                        <span class="status-<?= htmlspecialchars($estadoClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($pedido['estado'] ?? 'Desconocido')) ?>
                        </span>
                    </td>

                    <td>
                        <button class="btn ver" data-action="ver-pedido" data-id="<?= (int) $pedido['id'] ?>">Ver</button>
                        <button class="btn editar" type="button" data-action="editar-pedido" data-id="<?= (int) $pedido['id'] ?>">Editar</button>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align:center;">No hay pedidos registrados</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>

<div class="pedido-modal" id="pedido-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-modal"></div>
    <div class="pedido-modal-content" role="dialog" aria-modal="true" aria-labelledby="pedido-modal-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-modal-title">Detalle del Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-modal" aria-label="Cerrar detalle">x</button>
        </div>
        <div class="pedido-modal-body">
            <p><strong>Cliente:</strong> <span id="pedido-detalle-cliente">-</span></p>
            <p><strong>Cedula:</strong> <span id="pedido-detalle-cedula">-</span></p>
            <p><strong>Estado:</strong> <span id="pedido-detalle-estado">-</span></p>
            <p><strong>Fecha:</strong> <span id="pedido-detalle-fecha">-</span></p>
            <p><strong>Total:</strong> <span id="pedido-detalle-total">-</span></p>

            <h4>Productos</h4>
            <table class="pedidos-table pedido-detalle-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="pedido-detalle-items">
                    <tr>
                        <td colspan="4" style="text-align:center;">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="pedido-modal pedido-edit-modal" id="pedido-edit-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-edit-modal"></div>
    <div class="pedido-modal-content" role="dialog" aria-modal="true" aria-labelledby="pedido-edit-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-edit-title">Editar Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="pedido-edit-form" class="pedido-edit-form">
            <input type="hidden" name="id" id="pedido-edit-id">

            <label for="pedido-edit-estado">Estado</label>
            <select name="estado" id="pedido-edit-estado" required>
                <option value="pendiente">Pendiente</option>
                <option value="procesando">Procesando</option>
                <option value="listo">Listo</option>
                <option value="entregado">Entregado</option>
                <option value="cancelado">Cancelado</option>
            </select>

            <p class="pedido-edit-feedback" id="pedido-edit-feedback" hidden></p>

            <div class="pedido-edit-actions">
                <button class="btn cancelar" type="button" data-close="pedido-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="pedido-edit-submit">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="pedido-modal pedido-create-modal" id="pedido-create-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-create-modal"></div>
    <div class="pedido-modal-content" role="dialog" aria-modal="true" aria-labelledby="pedido-create-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-create-title">Nuevo Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="pedido-create-form" class="pedido-create-form">
            <label for="pedido-create-cliente">Cliente</label>
            <select id="pedido-create-cliente" name="id_cliente" required>
                <option value="">Selecciona un cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= (int) $cliente['id'] ?>">
                        <?= htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))) ?>
                        <?= !empty($cliente['cedula']) ? ' - ' . htmlspecialchars($cliente['cedula']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="pedido-create-estado">Estado Inicial</label>
            <select id="pedido-create-estado" name="estado" required>
                <option value="pendiente" selected>Pendiente</option>
                <option value="procesando">Procesando</option>
                <option value="listo">Listo</option>
                <option value="entregado">Entregado</option>
                <option value="cancelado">Cancelado</option>
            </select>

            <h4>Productos del Pedido</h4>
            <div class="pedido-items" id="pedido-items">
                <div class="pedido-item-row">
                    <select class="pedido-item-producto" required>
                        <option value="">Selecciona producto</option>
                        <?php foreach ($productos as $producto): ?>
                            <option
                                value="<?= (int) $producto['id'] ?>"
                                data-precio="<?= htmlspecialchars((string) $producto['precio_base'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($producto['nombre_producto']) ?> - $<?= number_format((float) $producto['precio_base'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input class="pedido-item-cantidad" type="number" min="1" step="1" value="1" required>
                    <button class="btn quitar" type="button" data-action="quitar-item-pedido">Quitar</button>
                </div>
            </div>

            <button class="btn agregar" type="button" data-action="agregar-item-pedido">Agregar Producto</button>

            <p class="pedido-create-total">
                Total estimado: <strong id="pedido-create-total-valor">$0.00</strong>
            </p>

            <p class="pedido-create-feedback" id="pedido-create-feedback" hidden></p>

            <div class="pedido-edit-actions">
                <button class="btn cancelar" type="button" data-close="pedido-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="pedido-create-submit">Crear Pedido</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
