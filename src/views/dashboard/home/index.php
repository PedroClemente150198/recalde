<?php
$topProductos = $topProductos ?? [];
$ultimasVentas = $ultimasVentas ?? [];

$homeData = [
    'totalVentas' => (int) ($totalVentas ?? 0),
    'totalPedidos' => (int) ($totalPedidos ?? 0),
    'ingresosTotales' => (float) ($ingresosTotales ?? 0),
    'topProductos' => $topProductos,
    'ultimasVentas' => $ultimasVentas,
    'periodoIngresos' => $periodoIngresos ?? 'mes',
    'labelsIngresos' => $labelsIngresos ?? ($labelsMes ?? []),
    'datosIngresos' => $datosIngresos ?? ($datosVentasMes ?? []),
    // Compatibilidad
    'labelsMes' => $labelsMes ?? ($labelsIngresos ?? []),
    'datosVentasMes' => $datosVentasMes ?? ($datosIngresos ?? []),
    'pedidosEstados' => $pedidosEstados ?? [],
    'ultimaActualizacion' => $ultimaActualizacion ?? date('Y-m-d H:i:s')
];

$periodoIngresosActual = strtolower((string) ($homeData['periodoIngresos'] ?? 'mes'));
if (!in_array($periodoIngresosActual, ['dia', 'semana', 'mes'], true)) {
    $periodoIngresosActual = 'mes';
}

$ingresosTitulos = [
    'dia' => 'Ingresos por Día',
    'semana' => 'Ingresos por Semana',
    'mes' => 'Ingresos por Mes',
];

$ingresosDescripciones = [
    'dia' => 'Detalle diario de ingresos registrados.',
    'semana' => 'Comparativa semanal de ingresos.',
    'mes' => 'Tendencia mensual del rendimiento comercial acumulado.',
];

$ingresosTitulo = $ingresosTitulos[$periodoIngresosActual];
$ingresosDescripcion = $ingresosDescripciones[$periodoIngresosActual];

$topProductoPrincipal = !empty($topProductos[0]['nombre_producto'])
    ? (string) $topProductos[0]['nombre_producto']
    : 'Sin datos';

$ultimaVenta = $ultimasVentas[0] ?? null;
$ultimaVentaCliente = trim((string) (($ultimaVenta['nombre'] ?? '') . ' ' . ($ultimaVenta['apellido'] ?? '')));
$ultimaVentaTotal = (float) ($ultimaVenta['total'] ?? 0);
$ultimaVentaFecha = (string) ($ultimaVenta['fecha_venta'] ?? '-');

$estadoPedidos = $homeData['pedidosEstados'] ?? [];
$pendiente = (int) ($estadoPedidos['pendiente'] ?? 0);
$procesando = (int) ($estadoPedidos['procesando'] ?? 0);
$listo = (int) ($estadoPedidos['listo'] ?? 0);
$entregado = (int) ($estadoPedidos['entregado'] ?? 0);
$cancelado = (int) ($estadoPedidos['cancelado'] ?? 0);
$activos = $pendiente + $procesando + $listo;
?>
<link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/home.css">

<div class="home-dashboard" data-home-dashboard="1">
    <section class="home-hero">
        <div class="hero-main">
            <p class="hero-tag">Panel Ejecutivo</p>
            <h1 class="hero-title">Control Comercial en Tiempo Real</h1>
            <p class="hero-subtitle">
                Vista central de ventas y pedidos actualizados automáticos.
            </p>
        </div>

        <div class="hero-meta">
            <article class="hero-chip">
                <span>Frecuencia</span>
                <strong>10 segundos</strong>
                <small>Actualización automática</small>
            </article>

            <article class="hero-chip">
                <span>Última actualización</span>
                <strong id="home-updated-at"><?= htmlspecialchars((string) ($homeData['ultimaActualizacion'] ?? '-')) ?></strong>
                <small>Fuente: módulo Home</small>
            </article>

            <article class="hero-chip">
                <span>Última venta</span>
                <strong id="home-ultima-venta-total">$<?= number_format($ultimaVentaTotal, 2) ?></strong>
                <small id="home-ultima-venta-cliente"><?= htmlspecialchars($ultimaVentaCliente !== '' ? $ultimaVentaCliente : 'Sin ventas registradas') ?></small>
                <small id="home-ultima-venta-fecha"><?= htmlspecialchars($ultimaVentaFecha) ?></small>
            </article>
        </div>
    </section>

    <section class="kpi-grid">
        <article class="kpi-card">
            <p class="kpi-label">Ventas Registradas</p>
            <p class="kpi-value" id="home-total-ventas"><?= (int) ($homeData['totalVentas'] ?? 0) ?></p>
            <span class="kpi-note">Operaciones cerradas</span>
        </article>

        <article class="kpi-card">
            <p class="kpi-label">Pedidos Totales</p>
            <p class="kpi-value" id="home-total-pedidos"><?= (int) ($homeData['totalPedidos'] ?? 0) ?></p>
            <span class="kpi-note">Pedidos históricos</span>
        </article>

        <article class="kpi-card">
            <p class="kpi-label">Ingresos Acumulados</p>
            <p class="kpi-value" id="home-ingresos-totales">$<?= number_format((float) ($homeData['ingresosTotales'] ?? 0), 2) ?></p>
            <span class="kpi-note">Monto total vendido</span>
        </article>

        <article class="kpi-card">
            <p class="kpi-label">Producto Líder</p>
            <p class="kpi-value kpi-text" id="home-producto-lider"><?= htmlspecialchars($topProductoPrincipal) ?></p>
            <span class="kpi-note">Mayor rotación</span>
        </article>
    </section>

    <section class="analytics-grid">
        <article class="home-panel panel-wide">
            <div class="panel-head panel-head-inline">
                <div>
                    <h3 id="home-ingresos-title"><?= htmlspecialchars($ingresosTitulo) ?></h3>
                    <p id="home-ingresos-subtitle"><?= htmlspecialchars($ingresosDescripcion) ?></p>
                </div>

                <label class="home-period-filter" for="home-ingresos-periodo">
                    <span>Periodo</span>
                    <select id="home-ingresos-periodo" name="periodo_ingresos">
                        <option value="dia" <?= $periodoIngresosActual === 'dia' ? 'selected' : '' ?>>Día</option>
                        <option value="semana" <?= $periodoIngresosActual === 'semana' ? 'selected' : '' ?>>Semana</option>
                        <option value="mes" <?= $periodoIngresosActual === 'mes' ? 'selected' : '' ?>>Mes</option>
                    </select>
                </label>
            </div>
            <div class="chart-wrap">
                <canvas id="chartVentasMes"></canvas>
            </div>
        </article>

        <article class="home-panel panel-state">
            <div class="panel-head">
                <h3>Estado Operativo de Pedidos</h3>
                <p>Seguimiento de cada fase del proceso.</p>
            </div>

            <div class="state-list">
                <div class="state-row">
                    <span>Pendiente</span>
                    <strong id="home-estado-pendiente"><?= $pendiente ?></strong>
                </div>
                <div class="state-row">
                    <span>Procesando</span>
                    <strong id="home-estado-procesando"><?= $procesando ?></strong>
                </div>
                <div class="state-row">
                    <span>Listo</span>
                    <strong id="home-estado-listo"><?= $listo ?></strong>
                </div>
                <div class="state-row">
                    <span>Entregado</span>
                    <strong id="home-estado-entregado"><?= $entregado ?></strong>
                </div>
                <div class="state-row">
                    <span>Cancelado</span>
                    <strong id="home-estado-cancelado"><?= $cancelado ?></strong>
                </div>
            </div>

            <div class="state-highlight">
                <span>Pedidos Activos</span>
                <strong id="home-estado-activos"><?= $activos ?></strong>
            </div>
        </article>

        <article class="home-panel">
            <div class="panel-head">
                <h3>Pedidos por Estado</h3>
                <p>Distribución porcentual actual.</p>
            </div>
            <div class="chart-wrap chart-wrap-small">
                <canvas id="chartPedidos"></canvas>
            </div>
        </article>

        <article class="home-panel">
            <div class="panel-head">
                <h3>Top 5 Productos Más Vendidos</h3>
                <p>Comparativa rápida de las prendas más demandadas.</p>
            </div>
            <div class="table-wrap">
                <table class="home-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody id="home-top-productos-body">
                        <?php if (!empty($topProductos)): ?>
                            <?php foreach ($topProductos as $producto): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($producto['nombre_producto'] ?? '-')) ?></td>
                                    <td><?= (int) ($producto['total_vendido'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align:center;">Sin datos disponibles.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="home-panel panel-sales">
        <div class="panel-head">
            <h3>Últimas Ventas Registradas</h3>
            <p>Últimos movimientos para control de caja y atención al cliente.</p>
        </div>

        <div class="table-wrap">
            <table class="home-table">
                <thead>
                    <tr>
                        <th>ID Venta</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody id="home-ultimas-ventas-body">
                    <?php if (!empty($ultimasVentas)): ?>
                        <?php foreach ($ultimasVentas as $venta): ?>
                            <tr>
                                <td><?= (int) ($venta['id'] ?? 0) ?></td>
                                <td><?= htmlspecialchars(trim((string) (($venta['nombre'] ?? '') . ' ' . ($venta['apellido'] ?? '')))) ?></td>
                                <td>$<?= number_format((float) ($venta['total'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars((string) ($venta['fecha_venta'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">Sin ventas registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script id="home-dashboard-data" type="application/json"><?= json_encode($homeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
