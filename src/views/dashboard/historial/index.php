<?php
$historial = $historial ?? [];
?>
<link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/historial.css">

<div class="historial-container">
    <h1>Historial de Ventas</h1>
    <p class="historial-subtitle">
        Vista consolidada de ventas registradas con referencia de pedido, método de pago y responsable.
    </p>

    <div class="tabla-wrapper">
        <table class="tabla-historial">
            <thead>
                <tr>
                    <th># Historial</th>
                    <th>Venta / Pedido</th>
                    <th>Cliente</th>
                    <th>Resumen</th>
                    <th>Pago</th>
                    <th>Fechas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($historial)): ?>
                    <?php foreach ($historial as $item): ?>
                        <?php
                        $estadoHistorial = strtolower((string) ($item['estado'] ?? 'registrado'));
                        $estadoPedido = strtolower((string) ($item['estado_pedido'] ?? 'pendiente'));
                        $classEstado = match ($estadoHistorial) {
                            'anulado' => 'anulado',
                            'entregado' => 'entregado',
                            default => 'registrado',
                        };
                        $metodoPago = (string) ($item['metodo_pago'] ?? 'no definido');
                        $responsable = trim((string) ($item['usuario_responsable_nombre'] ?? ''));
                        ?>

                        <tr>
                            <td class="mono">#<?= (int) ($item['id'] ?? 0) ?></td>

                            <td>
                                <div class="historial-cell-title">Venta #<?= (int) ($item['id_venta'] ?? 0) ?></div>
                                <small class="historial-subdata">Pedido #<?= (int) ($item['id_pedido'] ?? 0) ?></small>
                            </td>

                            <td>
                                <div class="historial-cell-title">
                                    <?= htmlspecialchars(trim(((string) ($item['nombre'] ?? '')) . ' ' . ((string) ($item['apellido'] ?? '')))) ?>
                                </div>
                                <small class="historial-subdata">Cédula: <?= htmlspecialchars((string) ($item['cedula'] ?? '-')) ?></small>
                            </td>

                            <td>
                                <small class="historial-subdata">Items: <?= (int) ($item['total_items'] ?? 0) ?></small><br>
                                <small class="historial-subdata">Prendas: <?= (int) ($item['total_prendas'] ?? 0) ?></small><br>
                                <small class="historial-subdata">Pedido: <?= htmlspecialchars(ucfirst($estadoPedido)) ?></small>
                            </td>

                            <td>
                                <div class="historial-cell-title"><?= htmlspecialchars(ucfirst($metodoPago)) ?></div>
                                <small class="historial-subdata">
                                    Responsable:
                                    <?= htmlspecialchars($responsable !== '' ? $responsable : 'Sistema') ?>
                                </small>
                            </td>

                            <td>
                                <small class="historial-subdata">Registro: <?= htmlspecialchars((string) ($item['fecha'] ?? '-')) ?></small><br>
                                <small class="historial-subdata">Venta: <?= htmlspecialchars((string) ($item['fecha_venta'] ?? '-')) ?></small>
                            </td>

                            <td class="mono">$<?= number_format((float) ($item['total'] ?? 0), 2) ?></td>

                            <td>
                                <span class="badge <?= htmlspecialchars($classEstado, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(ucfirst($estadoHistorial)) ?>
                                </span>
                            </td>

                            <td>
                                <button class="btn ver" type="button" data-action="ver-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Ver</button>
                                <button class="btn imprimir" type="button" data-action="imprimir-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Imprimir</button>

                                <?php if ($estadoHistorial !== 'anulado'): ?>
                                    <button class="btn anular" type="button" data-action="anular-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Anular</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">
                            No hay registros en el historial.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="historial-modal" id="historial-modal" hidden>
    <div class="historial-modal-backdrop" data-close="historial-modal"></div>
    <div class="historial-modal-content" role="dialog" aria-modal="true" aria-labelledby="historial-modal-title">
        <div class="historial-modal-header">
            <h3 id="historial-modal-title">Detalle del Historial</h3>
            <button class="btn cerrar" type="button" data-close="historial-modal" aria-label="Cerrar detalle">x</button>
        </div>

        <div class="historial-modal-body">
            <div class="historial-detail-grid">
                <p><strong>ID Historial:</strong> <span id="historial-detalle-id">-</span></p>
                <p><strong>ID Venta:</strong> <span id="historial-detalle-venta">-</span></p>
                <p><strong>ID Pedido:</strong> <span id="historial-detalle-pedido">-</span></p>
                <p><strong>Cliente:</strong> <span id="historial-detalle-cliente">-</span></p>
                <p><strong>Cédula:</strong> <span id="historial-detalle-cedula">-</span></p>
                <p><strong>Teléfono:</strong> <span id="historial-detalle-telefono">-</span></p>
                <p><strong>Empresa:</strong> <span id="historial-detalle-empresa">-</span></p>
                <p><strong>Dirección:</strong> <span id="historial-detalle-direccion">-</span></p>
                <p><strong>Método de Pago:</strong> <span id="historial-detalle-metodo">-</span></p>
                <p><strong>Responsable:</strong> <span id="historial-detalle-responsable">-</span></p>
                <p><strong>Fecha Registro:</strong> <span id="historial-detalle-fecha">-</span></p>
                <p><strong>Fecha Venta:</strong> <span id="historial-detalle-fecha-venta">-</span></p>
                <p><strong>Total Historial:</strong> <span id="historial-detalle-total">-</span></p>
                <p><strong>Estado Historial:</strong> <span id="historial-detalle-estado">-</span></p>
                <p><strong>Estado Pedido:</strong> <span id="historial-detalle-estado-pedido">-</span></p>
            </div>

            <div class="historial-resumen-grid">
                <div class="resumen-card">
                    <small>Items</small>
                    <strong id="historial-detalle-total-items">0</strong>
                </div>
                <div class="resumen-card">
                    <small>Prendas</small>
                    <strong id="historial-detalle-total-prendas">0</strong>
                </div>
                <div class="resumen-card">
                    <small>Subtotal Productos</small>
                    <strong id="historial-detalle-subtotal">$0.00</strong>
                </div>
                <div class="resumen-card">
                    <small>Extras</small>
                    <strong id="historial-detalle-extras">$0.00</strong>
                </div>
                <div class="resumen-card">
                    <small>Total Calculado</small>
                    <strong id="historial-detalle-total-calculado">$0.00</strong>
                </div>
            </div>

            <div class="historial-detalle-items">
                <h4>Detalle de Productos</h4>
                <div class="tabla-wrapper">
                    <table class="tabla-detalle-historial">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>P. Unitario</th>
                                <th>Subtotal</th>
                                <th>Extras</th>
                                <th>Total Línea</th>
                                <th>Personalizaciones</th>
                            </tr>
                        </thead>
                        <tbody id="historial-detalle-items-body">
                            <tr>
                                <td colspan="7" style="text-align:center;">Sin detalle para mostrar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="historial-feedback" id="historial-feedback" hidden></p>
        </div>
    </div>
</div>
