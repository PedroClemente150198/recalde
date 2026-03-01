<?php
$historial = $historial ?? [];
$showActionsColumn = isset($showActionsColumn) ? (bool) $showActionsColumn : true;
$totalRegistros = count($historial);
$totalVigentes = 0;
$totalAnulados = 0;
$montoVigente = 0.0;
$totalAbonado = 0.0;
$totalPendiente = 0.0;
$pagosPagados = 0;
$pagosParciales = 0;
$pagosPendientes = 0;
$responsables = [];

foreach ($historial as $item) {
    $estadoHistorial = strtolower(trim((string) ($item['estado'] ?? 'registrado')));
    $estadoPago = strtolower(trim((string) ($item['estado_pago'] ?? 'pendiente')));
    $monto = (float) ($item['total'] ?? 0);
    $responsable = trim((string) ($item['usuario_responsable_nombre'] ?? ''));

    if ($estadoHistorial === 'anulado') {
        $totalAnulados++;
    } else {
        $totalVigentes++;
        $montoVigente += $monto;
        $totalAbonado += (float) ($item['total_abonado'] ?? $monto);
        $totalPendiente += (float) ($item['saldo_pendiente'] ?? 0);
    }

    if ($estadoPago === 'pagado') {
        $pagosPagados++;
    } elseif ($estadoPago === 'parcial') {
        $pagosParciales++;
    } else {
        $pagosPendientes++;
    }

    $responsableKey = $responsable !== '' ? $responsable : 'Sistema';
    if (!isset($responsables[$responsableKey])) {
        $responsables[$responsableKey] = 0;
    }
    $responsables[$responsableKey]++;
}

arsort($responsables);
$responsablesTop = array_slice($responsables, 0, 4, true);
$historialPreview = array_slice($historial, 0, 4);
$porcentajeCobrado = $montoVigente > 0 ? (int) round(($totalAbonado / $montoVigente) * 100) : 100;

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Sin registro';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y H:i', $timestamp);
};

$formatMethod = static function (?string $value): string {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return 'Sin metodo';
    }

    return match ($value) {
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        default => ucfirst($value),
    };
};
?>
<link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/historial.css">

<div class="historial-page">
    <section class="historial-hero">
        <div class="historial-hero-copy">
            <p class="historial-eyebrow">Seguimiento documental</p>
            <h1>Historial de ventas con trazabilidad de cobro y operación</h1>
            <p class="historial-subtitle">
                Consulta el registro consolidado de ventas emitidas, estados operativos, responsables y detalle
                comercial desde una sola vista.
            </p>

            <div class="historial-hero-meta">
                <span><?= $porcentajeCobrado ?>% cobrado sobre historial vigente</span>
                <span><?= $totalVigentes ?> registros activos en seguimiento</span>
                <span><?= $pagosParciales ?> operaciones requieren control de cartera</span>
            </div>
        </div>

        <div class="historial-hero-summary">
            <article>
                <small>Total vigente</small>
                <strong>$<?= number_format($montoVigente, 2) ?></strong>
            </article>
            <article>
                <small>Total cobrado</small>
                <strong>$<?= number_format($totalAbonado, 2) ?></strong>
            </article>
            <article>
                <small>Saldo activo</small>
                <strong>$<?= number_format($totalPendiente, 2) ?></strong>
            </article>
        </div>
    </section>

    <section class="historial-kpi-grid" aria-label="Resumen de historial">
        <article class="historial-kpi-card">
            <small>Registros totales</small>
            <strong><?= $totalRegistros ?></strong>
            <p><?= $totalVigentes ?> vigentes y <?= $totalAnulados ?> anulados.</p>
        </article>

        <article class="historial-kpi-card">
            <small>Pagadas</small>
            <strong><?= $pagosPagados ?></strong>
            <p>Operaciones con pago cubierto por completo.</p>
        </article>

        <article class="historial-kpi-card">
            <small>Parciales</small>
            <strong><?= $pagosParciales ?></strong>
            <p>Ventas con abonos y saldo pendiente.</p>
        </article>

        <article class="historial-kpi-card danger">
            <small>Pendientes</small>
            <strong><?= $pagosPendientes ?></strong>
            <p>Registros que todavía no liquidan su pago.</p>
        </article>

        <article class="historial-kpi-card">
            <small>Total abonado</small>
            <strong>$<?= number_format($totalAbonado, 2) ?></strong>
            <p>Cobro efectivo registrado en historial.</p>
        </article>

        <article class="historial-kpi-card danger">
            <small>Saldo pendiente</small>
            <strong>$<?= number_format($totalPendiente, 2) ?></strong>
            <p>Cartera pendiente en operaciones vigentes.</p>
        </article>
    </section>

    <section class="historial-overview-grid">
        <article class="historial-panel historial-panel-collection">
            <div class="historial-panel-header">
                <div>
                    <p class="historial-panel-kicker">Cobranza</p>
                    <h2 class="historial-section-title">Estado financiero</h2>
                </div>
                <span class="historial-panel-badge"><?= $porcentajeCobrado ?>% cobrado</span>
            </div>

            <div class="historial-progress-shell">
                <div class="historial-progress-bar">
                    <span style="width: <?= max(0, min(100, $porcentajeCobrado)) ?>%;"></span>
                </div>
                <div class="historial-progress-legend">
                    <span>Cobrado: $<?= number_format($totalAbonado, 2) ?></span>
                    <span>Pendiente: $<?= number_format($totalPendiente, 2) ?></span>
                </div>
            </div>

            <div class="historial-status-grid">
                <article class="historial-status-card is-good">
                    <strong><?= $pagosPagados ?></strong>
                    <span>Pagadas</span>
                </article>
                <article class="historial-status-card is-warning">
                    <strong><?= $pagosParciales ?></strong>
                    <span>Parciales</span>
                </article>
                <article class="historial-status-card is-danger">
                    <strong><?= $pagosPendientes ?></strong>
                    <span>Pendientes</span>
                </article>
            </div>
        </article>

        <article class="historial-panel">
            <div class="historial-panel-header">
                <div>
                    <p class="historial-panel-kicker">Equipo</p>
                    <h2 class="historial-section-title">Responsables activos</h2>
                </div>
                <span class="historial-panel-badge"><?= count($responsablesTop) ?> visibles</span>
            </div>

            <?php if (!empty($responsablesTop)): ?>
                <ul class="historial-mini-list">
                    <?php foreach ($responsablesTop as $responsableNombre => $cantidad): ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars((string) $responsableNombre, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>Responsable operativo</span>
                            </div>
                            <span class="historial-mini-value"><?= (int) $cantidad ?> registros</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="historial-empty-copy">No hay responsables registrados todavía.</p>
            <?php endif; ?>
        </article>

        <article class="historial-panel">
            <div class="historial-panel-header">
                <div>
                    <p class="historial-panel-kicker">Actividad reciente</p>
                    <h2 class="historial-section-title">Últimos movimientos</h2>
                </div>
                <span class="historial-panel-badge"><?= count($historialPreview) ?> recientes</span>
            </div>

            <?php if (!empty($historialPreview)): ?>
                <ul class="historial-mini-list">
                    <?php foreach ($historialPreview as $item): ?>
                        <?php
                        $clienteNombre = trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')));
                        ?>
                        <li>
                            <div>
                                <strong>#<?= (int) ($item['id'] ?? 0) ?> · <?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars($formatDate((string) ($item['fecha_venta'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <span class="historial-mini-value">$<?= number_format((float) ($item['total'] ?? 0), 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="historial-empty-copy">No hay movimientos para mostrar.</p>
            <?php endif; ?>
        </article>
    </section>

    <section class="historial-panel historial-main-panel<?= $showActionsColumn ? '' : ' historial-actions-hidden' ?>" aria-label="Listado de historial" data-shared-ui-scope="historial">
        <div class="historial-panel-header">
            <div>
                <p class="historial-panel-kicker">Control documental</p>
                <h2 class="historial-section-title">Listado de historial</h2>
            </div>
            <span class="historial-panel-badge"><?= $totalRegistros ?> registros</span>
        </div>

        <div class="historial-toolbar">
            <span class="historial-toolbar-pill"><?= $pagosPagados ?> pagadas</span>
            <span class="historial-toolbar-pill"><?= $pagosParciales ?> parciales</span>
            <span class="historial-toolbar-pill"><?= $pagosPendientes ?> pendientes</span>
            <span class="historial-toolbar-pill"><?= $totalAnulados ?> anuladas</span>
        </div>

        <div class="tabla-wrapper tabla-wrapper-historial">
            <table class="historial-table" data-page-size="8">
                <colgroup>
                    <col class="historial-col-id">
                    <col class="historial-col-operacion">
                    <col class="historial-col-cliente">
                    <col class="historial-col-cobranza">
                    <col class="historial-col-fechas">
                    <col class="historial-col-total">
                    <col class="historial-col-estado">
                    <col class="historial-col-acciones">
                </colgroup>
                <thead>
                    <tr>
                        <th>Historial</th>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Cobranza</th>
                        <th>Fechas</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="historial-actions-col">Acciones</th>
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

                            $classPedido = match ($estadoPedido) {
                                'entregado', 'listo' => 'is-ok',
                                'procesando' => 'is-proceso',
                                'cancelado' => 'is-cancelado',
                                default => 'is-pendiente',
                            };

                            $metodoPago = (string) ($item['metodo_pago'] ?? 'no definido');
                            $responsable = trim((string) ($item['usuario_responsable_nombre'] ?? ''));
                            $estadoPago = strtolower((string) ($item['estado_pago'] ?? 'pagado'));
                            $estadoPagoClass = preg_replace('/[^a-z0-9_-]/', '', $estadoPago) ?: 'pagado';
                            $abonado = (float) ($item['total_abonado'] ?? 0);
                            $saldo = (float) ($item['saldo_pendiente'] ?? 0);
                            $clienteNombre = trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')));
                            ?>

                            <tr
                                class="historial-row-trigger"
                                data-historial-row="1"
                                data-historial-id="<?= (int) ($item['id'] ?? 0) ?>"
                                data-venta-id="<?= (int) ($item['id_venta'] ?? 0) ?>"
                                data-pedido-id="<?= (int) ($item['id_pedido'] ?? 0) ?>"
                                data-cliente="<?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?>"
                                data-total-label="<?= htmlspecialchars('$' . number_format((float) ($item['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?>"
                                data-estado-pago="<?= htmlspecialchars(ucfirst($estadoPago), ENT_QUOTES, 'UTF-8') ?>"
                                data-metodo="<?= htmlspecialchars($formatMethod($metodoPago), ENT_QUOTES, 'UTF-8') ?>"
                                data-fecha="<?= htmlspecialchars($formatDate((string) ($item['fecha_venta'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                data-estado-historial="<?= htmlspecialchars(ucfirst($estadoHistorial), ENT_QUOTES, 'UTF-8') ?>"
                                title="Haz clic para ver acciones de este registro"
                            >
                                <td class="historial-cell-id" data-label="Historial">
                                    <div class="historial-meta-stack">
                                        <strong class="mono">#<?= (int) ($item['id'] ?? 0) ?></strong>
                                    </div>
                                </td>

                                <td data-label="Operación">
                                    <div class="historial-cell-title">Venta #<?= (int) ($item['id_venta'] ?? 0) ?></div>
                                    <small class="historial-subdata">Pedido #<?= (int) ($item['id_pedido'] ?? 0) ?></small>
                                    <div class="historial-inline-pills">
                                        <span class="pedido-chip <?= htmlspecialchars($classPedido, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(ucfirst($estadoPedido), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="historial-inline-pill">
                                            <?= (int) ($item['total_items'] ?? 0) ?> items
                                        </span>
                                        <span class="historial-inline-pill">
                                            <?= (int) ($item['total_prendas'] ?? 0) ?> prendas
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Cliente">
                                    <div class="historial-cell-title">
                                        <?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <small class="historial-subdata">
                                        <?= !empty($item['cedula']) ? 'CI ' . htmlspecialchars((string) $item['cedula'], ENT_QUOTES, 'UTF-8') : 'Documento sin registrar' ?>
                                    </small>
                                    <small class="historial-subdata">
                                        <?= !empty($item['empresa']) ? htmlspecialchars((string) $item['empresa'], ENT_QUOTES, 'UTF-8') : 'Sin empresa asociada' ?>
                                    </small>
                                </td>

                                <td data-label="Cobranza">
                                    <div class="historial-meta-stack">
                                        <strong><?= htmlspecialchars($formatMethod($metodoPago), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="pago-chip pago-<?= htmlspecialchars($estadoPagoClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(ucfirst($estadoPago), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <small>Abonado: $<?= number_format($abonado, 2) ?></small>
                                        <small>Saldo: $<?= number_format($saldo, 2) ?></small>
                                        <small>Responsable: <?= htmlspecialchars($responsable !== '' ? $responsable : 'Sistema', ENT_QUOTES, 'UTF-8') ?></small>
                                    </div>
                                </td>

                                <td data-label="Fechas">
                                    <div class="historial-date-stack">
                                        <span class="historial-date-label">Registro</span>
                                        <strong class="mono"><?= htmlspecialchars($formatDate((string) ($item['fecha'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="historial-date-stack">
                                        <span class="historial-date-label">Venta</span>
                                        <strong class="mono"><?= htmlspecialchars($formatDate((string) ($item['fecha_venta'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                </td>

                                <td class="historial-total" data-label="Total">
                                    <div class="historial-meta-stack">
                                        <strong class="mono">$<?= number_format((float) ($item['total'] ?? 0), 2) ?></strong>
                                    </div>
                                </td>

                                <td data-label="Estado">
                                    <div class="historial-meta-stack">
                                        <span class="badge <?= htmlspecialchars($classEstado, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(ucfirst($estadoHistorial), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <small class="historial-state-note"><?= $estadoHistorial === 'anulado' ? 'Fuera de flujo' : 'En seguimiento' ?></small>
                                    </div>
                                </td>

                                <td class="historial-actions-cell" data-label="Acciones">
                                    <div class="historial-actions">
                                        <button class="btn ver" type="button" data-action="ver-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Ver</button>
                                        <button class="btn imprimir" type="button" data-action="imprimir-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Imprimir</button>
                                        <?php if ($estadoHistorial !== 'anulado'): ?>
                                            <button class="btn anular" type="button" data-action="anular-historial" data-id="<?= (int) ($item['id'] ?? 0) ?>">Anular</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="historial-empty">No hay registros en el historial.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="historial-modal" id="historial-actions-modal" hidden>
    <div class="historial-modal-backdrop" data-close="historial-actions-modal"></div>
    <div class="historial-modal-content historial-modal-content-compact" role="dialog" aria-modal="true" aria-labelledby="historial-actions-title">
        <div class="historial-modal-header">
            <div>
                <p class="historial-panel-kicker">Historial</p>
                <h3 id="historial-actions-title">Acciones rápidas</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="historial-actions-modal" aria-label="Cerrar acciones">x</button>
        </div>

        <div class="historial-actions-summary">
            <p><strong>Historial:</strong> <span id="historial-actions-id">-</span></p>
            <p><strong>Venta:</strong> <span id="historial-actions-venta">-</span></p>
            <p><strong>Pedido:</strong> <span id="historial-actions-pedido">-</span></p>
            <p><strong>Cliente:</strong> <span id="historial-actions-cliente">-</span></p>
            <p><strong>Total:</strong> <span id="historial-actions-total">$0.00</span></p>
            <p><strong>Pago:</strong> <span id="historial-actions-pago">-</span></p>
            <p><strong>Metodo:</strong> <span id="historial-actions-metodo">-</span></p>
            <p><strong>Fecha:</strong> <span id="historial-actions-fecha">-</span></p>
            <p><strong>Estado:</strong> <span id="historial-actions-estado">-</span></p>
        </div>

        <div class="historial-actions-grid">
            <button class="btn ver historial-actions-btn" type="button" id="historial-actions-ver" data-action="ver-historial">
                Ver detalle
            </button>
            <button class="btn imprimir historial-actions-btn" type="button" id="historial-actions-imprimir" data-action="imprimir-historial">
                Imprimir
            </button>
            <button class="btn anular historial-actions-btn" type="button" id="historial-actions-anular" data-action="anular-historial">
                Anular
            </button>
        </div>

        <p class="historial-actions-hint" id="historial-actions-hint">Selecciona una acción para continuar con este registro.</p>
    </div>
</div>

<div class="historial-modal" id="historial-modal" hidden>
    <div class="historial-modal-backdrop" data-close="historial-modal"></div>
    <div class="historial-modal-content" role="dialog" aria-modal="true" aria-labelledby="historial-modal-title">
        <div class="historial-modal-header">
            <div>
                <p class="historial-panel-kicker">Detalle documental</p>
                <h3 id="historial-modal-title">Detalle del historial</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="historial-modal" aria-label="Cerrar detalle">x</button>
        </div>

        <div class="historial-modal-body">
            <div class="historial-detail-layout">
                <section class="historial-detail-card">
                    <h4>Operación</h4>
                    <dl class="historial-detail-list">
                        <div><dt>ID Historial</dt><dd><span id="historial-detalle-id" class="mono">-</span></dd></div>
                        <div><dt>ID Venta</dt><dd><span id="historial-detalle-venta" class="mono">-</span></dd></div>
                        <div><dt>ID Pedido</dt><dd><span id="historial-detalle-pedido" class="mono">-</span></dd></div>
                        <div><dt>Método de Pago</dt><dd><span id="historial-detalle-metodo">-</span></dd></div>
                        <div><dt>Responsable</dt><dd><span id="historial-detalle-responsable">-</span></dd></div>
                        <div><dt>Fecha Registro</dt><dd><span id="historial-detalle-fecha" class="mono">-</span></dd></div>
                        <div><dt>Fecha Venta</dt><dd><span id="historial-detalle-fecha-venta" class="mono">-</span></dd></div>
                        <div><dt>Total Historial</dt><dd><span id="historial-detalle-total" class="mono">$0.00</span></dd></div>
                        <div><dt>Total Abonado</dt><dd><span id="historial-detalle-abonado" class="mono">$0.00</span></dd></div>
                        <div><dt>Saldo Pendiente</dt><dd><span id="historial-detalle-saldo" class="mono">$0.00</span></dd></div>
                        <div><dt>Estado de Pago</dt><dd><span id="historial-detalle-estado-pago" class="pago-chip pago-pendiente">-</span></dd></div>
                        <div><dt>Último Abono</dt><dd><span id="historial-detalle-ultimo-abono" class="mono">-</span></dd></div>
                        <div><dt>Estado Historial</dt><dd><span id="historial-detalle-estado" class="badge registrado">-</span></dd></div>
                        <div><dt>Estado Pedido</dt><dd><span id="historial-detalle-estado-pedido" class="pedido-chip is-pendiente">-</span></dd></div>
                    </dl>
                </section>

                <section class="historial-detail-card">
                    <h4>Cliente</h4>
                    <dl class="historial-detail-list">
                        <div><dt>Nombre</dt><dd><span id="historial-detalle-cliente">-</span></dd></div>
                        <div><dt>Cédula</dt><dd><span id="historial-detalle-cedula">-</span></dd></div>
                        <div><dt>Teléfono</dt><dd><span id="historial-detalle-telefono">-</span></dd></div>
                        <div><dt>Empresa</dt><dd><span id="historial-detalle-empresa">-</span></dd></div>
                        <div><dt>Dirección</dt><dd><span id="historial-detalle-direccion">-</span></dd></div>
                    </dl>
                </section>

                <section class="historial-detail-card">
                    <h4>Resumen de cálculo</h4>
                    <div class="historial-resumen-grid">
                        <article class="resumen-card">
                            <small>Items</small>
                            <strong id="historial-detalle-total-items">0</strong>
                        </article>
                        <article class="resumen-card">
                            <small>Prendas</small>
                            <strong id="historial-detalle-total-prendas">0</strong>
                        </article>
                        <article class="resumen-card">
                            <small>Subtotal Productos</small>
                            <strong id="historial-detalle-subtotal">$0.00</strong>
                        </article>
                        <article class="resumen-card">
                            <small>Extras</small>
                            <strong id="historial-detalle-extras">$0.00</strong>
                        </article>
                        <article class="resumen-card">
                            <small>Total Calculado</small>
                            <strong id="historial-detalle-total-calculado">$0.00</strong>
                        </article>
                    </div>
                </section>
            </div>

            <section class="historial-detalle-items">
                <h4>Detalle de productos</h4>
                <div class="tabla-wrapper">
                    <table class="tabla-detalle-historial" data-page-size="6">
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
                                <td colspan="7" class="historial-empty">Sin detalle para mostrar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="historial-feedback" id="historial-feedback" hidden></p>
        </div>
    </div>
</div>
