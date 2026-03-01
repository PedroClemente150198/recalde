<?php
$ventas = $ventas ?? [];
$pedidosDisponibles = $pedidosDisponibles ?? [];
$resumenCartera = $resumenCartera ?? [];
$clientesConDeuda = $clientesConDeuda ?? [];
$showActionsColumn = isset($showActionsColumn) ? (bool) $showActionsColumn : true;

$totalVentas = (int) ($resumenCartera['total_ventas'] ?? 0);
$totalFacturado = (float) ($resumenCartera['total_facturado'] ?? 0);
$totalAbonado = (float) ($resumenCartera['total_abonado'] ?? 0);
$totalPendiente = (float) ($resumenCartera['total_pendiente'] ?? 0);
$ventasConSaldo = (int) ($resumenCartera['ventas_con_saldo'] ?? 0);
$ventasSinAbono = (int) ($resumenCartera['ventas_sin_abono'] ?? 0);

$ventasPagadas = 0;
$ventasParciales = 0;
$ventasPendientes = 0;
$mayorSaldo = 0.0;

foreach ($ventas as $venta) {
    $estadoPago = strtolower(trim((string) ($venta['estado_pago'] ?? 'pendiente')));
    $saldo = (float) ($venta['saldo_pendiente'] ?? 0);

    if ($estadoPago === 'pagado') {
        $ventasPagadas++;
    } elseif ($estadoPago === 'parcial') {
        $ventasParciales++;
    } else {
        $ventasPendientes++;
    }

    if ($saldo > $mayorSaldo) {
        $mayorSaldo = $saldo;
    }
}

$ticketPromedio = $totalVentas > 0 ? ($totalFacturado / $totalVentas) : 0.0;
$porcentajeCobrado = $totalFacturado > 0 ? (int) round(($totalAbonado / $totalFacturado) * 100) : 100;
$pedidosListosParaFacturar = count($pedidosDisponibles);
$clientesConDeudaPreview = array_slice($clientesConDeuda, 0, 5);
$pedidosDisponiblesPreview = array_slice($pedidosDisponibles, 0, 5);
$ventasRecientesPreview = array_slice($ventas, 0, 4);

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

$formatDateParts = static function (?string $value): array {
    $value = trim((string) $value);
    if ($value === '') {
        return ['Sin registro', ''];
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return [$value, ''];
    }

    return [date('d/m/Y', $timestamp), date('H:i', $timestamp)];
};

$formatPaymentMethod = static function (?string $value): string {
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/ventas.css">
</head>
<body>
<div class="ventas-container">
    <section class="ventas-hero">
        <div class="ventas-hero-copy">
            <p class="ventas-eyebrow">Gestion comercial y cartera</p>
            <h1>Ventas con foco en cobro, liquidez y seguimiento</h1>
            <p class="ventas-subtitle">
                Supervisa ventas, pagos parciales, pedidos listos para facturar y clientes con deuda desde una misma
                vista operativa.
            </p>

            <div class="ventas-hero-meta">
                <span><?= $porcentajeCobrado ?>% cobrado sobre lo facturado</span>
                <span><?= $pedidosListosParaFacturar ?> pedidos disponibles para venta</span>
                <span><?= $ventasConSaldo ?> ventas aun requieren seguimiento</span>
            </div>
        </div>

        <div class="ventas-header-actions">
            <button class="btn nuevo" type="button" data-action="nueva-venta">Nueva Venta</button>
        </div>
    </section>

    <section class="ventas-kpis" aria-label="Resumen de cartera">
        <article class="ventas-kpi-card">
            <small>Ventas registradas</small>
            <strong><?= $totalVentas ?></strong>
            <p><?= $ventasPagadas ?> liquidadas y <?= $ventasConSaldo ?> por cobrar.</p>
        </article>

        <article class="ventas-kpi-card">
            <small>Total facturado</small>
            <strong>$<?= number_format($totalFacturado, 2) ?></strong>
            <p>Ticket promedio de $<?= number_format($ticketPromedio, 2) ?>.</p>
        </article>

        <article class="ventas-kpi-card">
            <small>Total abonado</small>
            <strong>$<?= number_format($totalAbonado, 2) ?></strong>
            <p>Ingreso efectivamente cobrado hasta ahora.</p>
        </article>

        <article class="ventas-kpi-card debt">
            <small>Saldo pendiente</small>
            <strong>$<?= number_format($totalPendiente, 2) ?></strong>
            <p>Mayor saldo individual de $<?= number_format($mayorSaldo, 2) ?>.</p>
        </article>

        <article class="ventas-kpi-card">
            <small>Pagos parciales</small>
            <strong><?= $ventasParciales ?></strong>
            <p><?= $ventasSinAbono ?> ventas aun no registran abonos.</p>
        </article>

        <article class="ventas-kpi-card">
            <small>Pipeline por facturar</small>
            <strong><?= $pedidosListosParaFacturar ?></strong>
            <p>Pedidos listos para convertirse en venta.</p>
        </article>
    </section>

    <section class="ventas-overview-grid">
        <article class="ventas-panel ventas-panel-collection">
            <div class="ventas-panel-header">
                <div>
                    <p class="ventas-panel-kicker">Cobranza</p>
                    <h2 class="ventas-section-title">Estado de cartera</h2>
                </div>
                <span class="ventas-panel-badge"><?= $porcentajeCobrado ?>% cobrado</span>
            </div>

            <div class="ventas-collection-progress">
                <div class="ventas-collection-bar">
                    <span style="width: <?= max(0, min(100, $porcentajeCobrado)) ?>%;"></span>
                </div>
                <div class="ventas-collection-legend">
                    <span>Cobrado: $<?= number_format($totalAbonado, 2) ?></span>
                    <span>Pendiente: $<?= number_format($totalPendiente, 2) ?></span>
                </div>
            </div>

            <div class="ventas-status-grid">
                <article class="ventas-status-card paid">
                    <strong><?= $ventasPagadas ?></strong>
                    <span>Pagadas</span>
                </article>
                <article class="ventas-status-card partial">
                    <strong><?= $ventasParciales ?></strong>
                    <span>Parciales</span>
                </article>
                <article class="ventas-status-card pending">
                    <strong><?= $ventasPendientes ?></strong>
                    <span>Pendientes</span>
                </article>
            </div>

            <div class="ventas-mini-list">
                <h3>Ultimas ventas registradas</h3>
                <?php if (!empty($ventasRecientesPreview)): ?>
                    <ul>
                        <?php foreach ($ventasRecientesPreview as $venta): ?>
                            <?php
                            $clienteNombre = trim((string) (($venta['nombre'] ?? '') . ' ' . ($venta['apellido'] ?? '')));
                            ?>
                            <li>
                                <div>
                                    <strong>#<?= (int) ($venta['id'] ?? 0) ?> · <?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($formatDate((string) ($venta['fecha_venta'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span>$<?= number_format((float) ($venta['total'] ?? 0), 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ventas-empty-copy">Todavia no hay ventas registradas.</p>
                <?php endif; ?>
            </div>
        </article>

        <article class="ventas-panel">
            <div class="ventas-panel-header">
                <div>
                    <p class="ventas-panel-kicker">Prioridad de cobro</p>
                    <h2 class="ventas-section-title">Clientes con deuda</h2>
                </div>
                <span class="ventas-panel-badge"><?= count($clientesConDeuda) ?> clientes</span>
            </div>

            <?php if (!empty($clientesConDeudaPreview)): ?>
                <ul class="ventas-priority-list">
                    <?php foreach ($clientesConDeudaPreview as $deudor): ?>
                        <?php $cliente = trim((string) (($deudor['nombre'] ?? '') . ' ' . ($deudor['apellido'] ?? ''))); ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars($cliente !== '' ? $cliente : '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>
                                    <?= (int) ($deudor['total_ventas_con_deuda'] ?? 0) ?> ventas con deuda
                                    <?php if (!empty($deudor['ultima_venta'])): ?>
                                        · Ultima: <?= htmlspecialchars($formatDate((string) ($deudor['ultima_venta'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="ventas-priority-value">$<?= number_format((float) ($deudor['deuda_total'] ?? 0), 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ventas-empty-copy">No hay clientes con deuda pendiente.</p>
            <?php endif; ?>
        </article>

        <article class="ventas-panel">
            <div class="ventas-panel-header">
                <div>
                    <p class="ventas-panel-kicker">Pipeline comercial</p>
                    <h2 class="ventas-section-title">Pedidos por facturar</h2>
                </div>
                <span class="ventas-panel-badge"><?= $pedidosListosParaFacturar ?> disponibles</span>
            </div>

            <?php if (!empty($pedidosDisponiblesPreview)): ?>
                <ul class="ventas-priority-list">
                    <?php foreach ($pedidosDisponiblesPreview as $pedido): ?>
                        <?php $cliente = trim((string) (($pedido['nombre'] ?? '') . ' ' . ($pedido['apellido'] ?? ''))); ?>
                        <li>
                            <div>
                                <strong>#<?= (int) ($pedido['id'] ?? 0) ?> · <?= htmlspecialchars($cliente !== '' ? $cliente : '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= !empty($pedido['cedula']) ? htmlspecialchars((string) $pedido['cedula'], ENT_QUOTES, 'UTF-8') : 'Sin cedula' ?></span>
                            </div>
                            <span class="ventas-priority-value">$<?= number_format((float) ($pedido['total'] ?? 0), 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ventas-empty-copy">No hay pedidos pendientes por convertir en venta.</p>
            <?php endif; ?>
        </article>
    </section>

    <section class="ventas-panel ventas-main-panel<?= $showActionsColumn ? '' : ' ventas-actions-hidden' ?>" data-shared-ui-scope="ventas">
        <div class="ventas-panel-header">
            <div>
                <p class="ventas-panel-kicker">Operacion diaria</p>
                <h2 class="ventas-section-title">Listado de ventas</h2>
            </div>
            <span class="ventas-panel-badge"><?= $totalVentas ?> registros</span>
        </div>

        <div class="ventas-toolbar">
            <span class="ventas-toolbar-pill"><?= $ventasPagadas ?> pagadas</span>
            <span class="ventas-toolbar-pill"><?= $ventasParciales ?> parciales</span>
            <span class="ventas-toolbar-pill"><?= $ventasPendientes ?> pendientes</span>
            <span class="ventas-toolbar-pill"><?= $pedidosListosParaFacturar ?> por facturar</span>
        </div>

        <div class="tabla-wrapper tabla-wrapper-ventas">
            <table class="ventas-table" data-page-size="8">
                <colgroup>
                    <col class="ventas-col-id">
                    <col class="ventas-col-pedido">
                    <col class="ventas-col-cliente">
                    <col class="ventas-col-total">
                    <col class="ventas-col-abonado">
                    <col class="ventas-col-saldo">
                    <col class="ventas-col-pago">
                    <col class="ventas-col-metodo">
                    <col class="ventas-col-fecha">
                    <col class="ventas-col-estado">
                    <col class="ventas-col-acciones">
                </colgroup>
                <thead>
                    <tr>
                        <th>
                            <span class="ventas-th-title">Venta</span>
                            <small class="ventas-th-subtitle">ID</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Pedido</span>
                            <small class="ventas-th-subtitle">Referencia</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Cliente</span>
                            <small class="ventas-th-subtitle">Nombre y documento</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Total</span>
                            <small class="ventas-th-subtitle">Facturado</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Abonado</span>
                            <small class="ventas-th-subtitle">Cobro actual</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Saldo</span>
                            <small class="ventas-th-subtitle">Pendiente</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Pago</span>
                            <small class="ventas-th-subtitle">Estado</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Metodo</span>
                            <small class="ventas-th-subtitle">Cobranza</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Fecha</span>
                            <small class="ventas-th-subtitle">Registro</small>
                        </th>
                        <th>
                            <span class="ventas-th-title">Estado</span>
                            <small class="ventas-th-subtitle">Pedido</small>
                        </th>
                        <th class="acciones-col">
                            <span class="ventas-th-title">Acciones</span>
                            <small class="ventas-th-subtitle">Gestion</small>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($ventas)): ?>
                    <?php foreach ($ventas as $venta): ?>
                        <?php
                        $estadoPedido = strtolower((string) ($venta['estado'] ?? 'desconocido'));
                        $estadoPedidoClass = preg_replace('/[^a-z0-9_-]/', '', $estadoPedido) ?: 'desconocido';
                        $estadoPedidoLabel = ucfirst(trim((string) ($venta['estado'] ?? 'Desconocido')));
                        $estadoPago = strtolower((string) ($venta['estado_pago'] ?? 'pendiente'));
                        $estadoPagoClass = preg_replace('/[^a-z0-9_-]/', '', $estadoPago) ?: 'pendiente';
                        $total = (float) ($venta['total'] ?? 0);
                        $abonado = (float) ($venta['total_abonado'] ?? 0);
                        $saldo = (float) ($venta['saldo_pendiente'] ?? max($total - $abonado, 0));
                        $clienteNombre = trim((string) (($venta['nombre'] ?? '') . ' ' . ($venta['apellido'] ?? '')));
                        $progresoCobro = $total > 0 ? (int) max(0, min(100, round(($abonado / $total) * 100))) : 0;
                        [$fechaVenta, $horaVenta] = $formatDateParts((string) ($venta['fecha_venta'] ?? ''));
                        ?>
                        <tr
                            class="ventas-row-trigger"
                            data-venta-row="1"
                            data-venta-id="<?= (int) ($venta['id'] ?? 0) ?>"
                            data-pedido-id="<?= (int) ($venta['id_pedido'] ?? 0) ?>"
                            data-cliente="<?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?>"
                            data-total-label="<?= htmlspecialchars('$' . number_format($total, 2), ENT_QUOTES, 'UTF-8') ?>"
                            data-saldo-label="<?= htmlspecialchars('$' . number_format($saldo, 2), ENT_QUOTES, 'UTF-8') ?>"
                            data-estado-pago="<?= htmlspecialchars(ucfirst($estadoPago), ENT_QUOTES, 'UTF-8') ?>"
                            data-metodo="<?= htmlspecialchars($formatPaymentMethod((string) ($venta['metodo_pago'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>"
                            data-fecha="<?= htmlspecialchars($fechaVenta . ($horaVenta !== '' ? ' · ' . $horaVenta . ' h' : ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-estado-pedido="<?= htmlspecialchars($estadoPedidoLabel, ENT_QUOTES, 'UTF-8') ?>"
                            title="Haz clic para ver acciones de esta venta"
                        >
                            <td class="ventas-cell-id" data-label="Venta">
                                <div class="ventas-meta-stack">
                                    <strong>#<?= (int) ($venta['id'] ?? 0) ?></strong>
                                    <small>Venta registrada</small>
                                </div>
                            </td>
                            <td class="ventas-cell-pedido" data-label="Pedido">
                                <span class="ventas-pill ventas-pill-reference">Pedido #<?= (int) ($venta['id_pedido'] ?? 0) ?></span>
                            </td>
                            <td class="ventas-cell-cliente" data-label="Cliente">
                                <div class="ventas-cell-title"><?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="ventas-subdata">
                                    <?= !empty($venta['cedula']) ? 'CI ' . htmlspecialchars((string) $venta['cedula'], ENT_QUOTES, 'UTF-8') : 'Documento sin registrar' ?>
                                </small>
                            </td>
                            <td class="ventas-money-cell" data-label="Total">
                                <div class="ventas-meta-stack">
                                    <strong>$<?= number_format($total, 2) ?></strong>
                                    <small>Total facturado</small>
                                </div>
                            </td>
                            <td class="ventas-cell-abonado" data-label="Abonado">
                                <div class="ventas-progress-cell">
                                    <strong>$<?= number_format($abonado, 2) ?></strong>
                                    <div class="ventas-progress-bar">
                                        <span class="ventas-progress-fill <?= htmlspecialchars($estadoPagoClass, ENT_QUOTES, 'UTF-8') ?>" style="width: <?= $progresoCobro ?>%;"></span>
                                    </div>
                                    <small><?= $progresoCobro ?>% cubierto</small>
                                </div>
                            </td>
                            <td class="monto-deuda ventas-money-cell" data-label="Saldo">
                                <div class="ventas-meta-stack">
                                    <strong><?= '$' . number_format($saldo, 2) ?></strong>
                                    <small>Por cobrar</small>
                                </div>
                            </td>
                            <td class="ventas-cell-status" data-label="Pago">
                                <span class="payment-status payment-<?= htmlspecialchars($estadoPagoClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(ucfirst($estadoPago), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="ventas-cell-method" data-label="Metodo">
                                <span class="ventas-pill ventas-pill-method"><?= htmlspecialchars($formatPaymentMethod((string) ($venta['metodo_pago'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="ventas-cell-date" data-label="Fecha">
                                <span class="ventas-date-badge"><?= htmlspecialchars($fechaVenta, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($horaVenta !== ''): ?>
                                    <small class="ventas-date-time"><?= htmlspecialchars($horaVenta . ' h', ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="ventas-cell-status" data-label="Estado">
                                <span class="status-<?= htmlspecialchars($estadoPedidoClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($estadoPedidoLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="ventas-cell-actions acciones" data-label="Acciones">
                                <button
                                    class="btn detalle"
                                    type="button"
                                    data-action="ver-venta-detalle"
                                    data-id="<?= (int) ($venta['id'] ?? 0) ?>"
                                >
                                    Detalle
                                </button>

                                <?php if ($saldo > 0.0001): ?>
                                    <button
                                        class="btn abonar"
                                        type="button"
                                        data-action="registrar-abono-venta"
                                        data-id="<?= (int) ($venta['id'] ?? 0) ?>"
                                        data-pedido="<?= (int) ($venta['id_pedido'] ?? 0) ?>"
                                        data-cliente="<?= htmlspecialchars($clienteNombre, ENT_QUOTES, 'UTF-8') ?>"
                                        data-total="<?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?>"
                                        data-abonado="<?= htmlspecialchars((string) $abonado, ENT_QUOTES, 'UTF-8') ?>"
                                        data-saldo="<?= htmlspecialchars((string) $saldo, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Abonar
                                    </button>
                                <?php endif; ?>

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
                        <td colspan="11" class="ventas-empty-cell">No hay ventas registradas.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="ventas-modal" id="venta-actions-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-actions-modal"></div>
    <div class="ventas-modal-content ventas-modal-content-compact" role="dialog" aria-modal="true" aria-labelledby="venta-actions-title">
        <div class="ventas-modal-header">
            <div>
                <p class="ventas-modal-kicker">Venta</p>
                <h3 id="venta-actions-title">Acciones rápidas</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="venta-actions-modal" aria-label="Cerrar acciones">x</button>
        </div>

        <div class="venta-actions-summary">
            <p><strong>Venta:</strong> <span id="venta-actions-id">-</span></p>
            <p><strong>Pedido:</strong> <span id="venta-actions-pedido">-</span></p>
            <p><strong>Cliente:</strong> <span id="venta-actions-cliente">-</span></p>
            <p><strong>Total:</strong> <span id="venta-actions-total">$0.00</span></p>
            <p><strong>Saldo:</strong> <span id="venta-actions-saldo">$0.00</span></p>
            <p><strong>Pago:</strong> <span id="venta-actions-pago">-</span></p>
            <p><strong>Metodo:</strong> <span id="venta-actions-metodo">-</span></p>
            <p><strong>Fecha:</strong> <span id="venta-actions-fecha">-</span></p>
            <p><strong>Estado:</strong> <span id="venta-actions-estado-pedido">-</span></p>
        </div>

        <div class="venta-actions-grid">
            <button class="btn detalle venta-actions-btn" type="button" id="venta-actions-detalle" data-action="ver-venta-detalle">
                Ver detalle
            </button>
            <button class="btn editar venta-actions-btn" type="button" id="venta-actions-editar" data-action="editar-venta">
                Editar venta
            </button>
            <button class="btn abonar venta-actions-btn" type="button" id="venta-actions-abono" data-action="registrar-abono-venta">
                Registrar abono
            </button>
            <button class="btn eliminar venta-actions-btn" type="button" id="venta-actions-eliminar" data-action="eliminar-venta">
                Eliminar venta
            </button>
        </div>

        <p class="venta-actions-hint" id="venta-actions-hint">Selecciona una acción para continuar con esta venta.</p>
    </div>
</div>

<div class="ventas-modal" id="venta-create-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-create-modal"></div>
    <div class="ventas-modal-content" role="dialog" aria-modal="true" aria-labelledby="venta-create-title">
        <div class="ventas-modal-header">
            <div>
                <p class="ventas-modal-kicker">Venta</p>
                <h3 id="venta-create-title">Nueva Venta</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="venta-create-modal" aria-label="Cerrar creacion">x</button>
        </div>

        <form id="venta-create-form" class="venta-form">
            <p class="venta-form-note">
                Selecciona un pedido pendiente de facturacion y define si entra pagado completo o con abono inicial.
            </p>

            <div class="venta-form-grid">
                <div class="venta-field venta-field-wide">
                    <label for="venta-create-pedido">Pedido</label>
                    <select id="venta-create-pedido" name="id_pedido" required>
                        <option value="">Selecciona un pedido</option>
                        <?php foreach ($pedidosDisponibles as $pedido): ?>
                            <option
                                value="<?= (int) ($pedido['id'] ?? 0) ?>"
                                data-total="<?= htmlspecialchars((string) ($pedido['total'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= '#'.(int) ($pedido['id'] ?? 0) ?>
                                <?= ' - '.htmlspecialchars(trim(($pedido['nombre'] ?? '') . ' ' . ($pedido['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                <?= !empty($pedido['cedula']) ? ' - ' . htmlspecialchars((string) $pedido['cedula'], ENT_QUOTES, 'UTF-8') : '' ?>
                                <?= ' - $' . number_format((float) ($pedido['total'] ?? 0), 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="venta-field">
                    <label for="venta-create-total">Total de Venta</label>
                    <input id="venta-create-total" name="total" type="number" step="0.01" min="0.01" required>
                </div>

                <div class="venta-field">
                    <label for="venta-create-abono-inicial">Abono Inicial</label>
                    <input id="venta-create-abono-inicial" name="abono_inicial" type="number" step="0.01" min="0" placeholder="Si lo dejas vacio, toma pago completo.">
                </div>

                <div class="venta-field">
                    <label for="venta-create-metodo">Metodo de Pago</label>
                    <select id="venta-create-metodo" name="metodo_pago" required>
                        <option value="efectivo" selected>Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <small class="venta-hint">Puedes registrar pagos parciales desde el inicio. El saldo se calcula automaticamente.</small>

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
            <div>
                <p class="ventas-modal-kicker">Venta</p>
                <h3 id="venta-edit-title">Editar Venta</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="venta-edit-modal" aria-label="Cerrar edicion">x</button>
        </div>

        <form id="venta-edit-form" class="venta-form">
            <input type="hidden" id="venta-edit-id" name="id">

            <p class="venta-form-note">
                Ajusta el total o el metodo base sin dejar la venta por debajo de lo ya abonado.
            </p>

            <div class="venta-form-grid venta-form-grid-compact">
                <div class="venta-field">
                    <label for="venta-edit-total">Total</label>
                    <input id="venta-edit-total" name="total" type="number" step="0.01" min="0.01" required>
                </div>

                <div class="venta-field">
                    <label for="venta-edit-metodo">Metodo de Pago</label>
                    <select id="venta-edit-metodo" name="metodo_pago" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <small class="venta-hint">No puedes poner un total menor al valor ya abonado.</small>

            <p class="venta-feedback" id="venta-edit-feedback" hidden></p>

            <div class="venta-actions">
                <button class="btn cancelar" type="button" data-close="venta-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="venta-edit-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="ventas-modal" id="venta-abono-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-abono-modal"></div>
    <div class="ventas-modal-content" role="dialog" aria-modal="true" aria-labelledby="venta-abono-title">
        <div class="ventas-modal-header">
            <div>
                <p class="ventas-modal-kicker">Cobranza</p>
                <h3 id="venta-abono-title">Registrar Abono</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="venta-abono-modal" aria-label="Cerrar abono">x</button>
        </div>

        <form id="venta-abono-form" class="venta-form">
            <input type="hidden" id="venta-abono-id-venta" name="id_venta">

            <div class="venta-context-grid">
                <p><strong>Venta:</strong> <span id="venta-abono-id-label">-</span></p>
                <p><strong>Pedido:</strong> <span id="venta-abono-pedido-label">-</span></p>
                <p><strong>Cliente:</strong> <span id="venta-abono-cliente-label">-</span></p>
                <p><strong>Total:</strong> <span id="venta-abono-total-label">$0.00</span></p>
                <p><strong>Abonado:</strong> <span id="venta-abono-abonado-label">$0.00</span></p>
                <p><strong>Saldo:</strong> <span id="venta-abono-saldo-label">$0.00</span></p>
            </div>

            <div class="venta-form-grid venta-form-grid-compact">
                <div class="venta-field">
                    <label for="venta-abono-monto">Monto del Abono</label>
                    <input id="venta-abono-monto" name="monto" type="number" step="0.01" min="0.01" required>
                </div>

                <div class="venta-field">
                    <label for="venta-abono-metodo">Metodo de Pago</label>
                    <select id="venta-abono-metodo" name="metodo_pago" required>
                        <option value="efectivo" selected>Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <div class="venta-field">
                <label for="venta-abono-observacion">Observacion (opcional)</label>
                <textarea id="venta-abono-observacion" name="observacion" rows="3" maxlength="255" placeholder="Ej: Abono cuota #2"></textarea>
            </div>

            <p class="venta-feedback" id="venta-abono-feedback" hidden></p>

            <div class="venta-actions">
                <button class="btn cancelar" type="button" data-close="venta-abono-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="venta-abono-submit">Guardar Abono</button>
            </div>
        </form>
    </div>
</div>

<div class="ventas-modal" id="venta-detalle-modal" hidden>
    <div class="ventas-modal-backdrop" data-close="venta-detalle-modal"></div>
    <div class="ventas-modal-content" role="dialog" aria-modal="true" aria-labelledby="venta-detalle-title">
        <div class="ventas-modal-header">
            <div>
                <p class="ventas-modal-kicker">Detalle</p>
                <h3 id="venta-detalle-title">Detalle de Venta y Abonos</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="venta-detalle-modal" aria-label="Cerrar detalle">x</button>
        </div>

        <div class="venta-detalle-grid">
            <p><strong>Venta:</strong> <span id="venta-detalle-id">-</span></p>
            <p><strong>Pedido:</strong> <span id="venta-detalle-pedido">-</span></p>
            <p><strong>Cliente:</strong> <span id="venta-detalle-cliente">-</span></p>
            <p><strong>Cedula:</strong> <span id="venta-detalle-cedula">-</span></p>
            <p><strong>Telefono:</strong> <span id="venta-detalle-telefono">-</span></p>
            <p><strong>Empresa:</strong> <span id="venta-detalle-empresa">-</span></p>
            <p><strong>Fecha Venta:</strong> <span id="venta-detalle-fecha">-</span></p>
            <p><strong>Metodo Base:</strong> <span id="venta-detalle-metodo">-</span></p>
            <p><strong>Estado Pedido:</strong> <span id="venta-detalle-estado-pedido">-</span></p>
            <p><strong>Total:</strong> <span id="venta-detalle-total">$0.00</span></p>
            <p><strong>Total Abonado:</strong> <span id="venta-detalle-abonado">$0.00</span></p>
            <p><strong>Saldo Pendiente:</strong> <span id="venta-detalle-saldo">$0.00</span></p>
            <p><strong>Estado de Pago:</strong> <span id="venta-detalle-estado-pago">-</span></p>
            <p><strong>Ultimo Abono:</strong> <span id="venta-detalle-ultimo-abono">-</span></p>
        </div>

        <div class="ventas-panel-shell">
            <h4>Historial de Abonos</h4>
            <div class="tabla-wrapper">
                <table class="ventas-table venta-abonos-table" data-page-size="6">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Metodo</th>
                            <th>Observacion</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="venta-detalle-abonos-body">
                        <tr>
                            <td colspan="6" class="ventas-empty-cell">Sin abonos registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="venta-feedback" id="venta-detalle-feedback" hidden></p>
    </div>
</div>

</body>
</html>
