<?php
$inventario = $inventario ?? [];
$categorias = $categorias ?? [];
$categoriasListado = $categoriasListado ?? [];
$stockColumnsEnabled = !empty($stockColumnsEnabled);

$totalProductos = count($inventario);
$totalCategorias = count($categoriasListado);
$productosActivos = 0;
$productosInactivos = 0;
$productosSinCategoria = 0;
$productosSinDescripcion = 0;
$productosConVenta = 0;
$productosComprometidos = 0;
$stockCriticoItems = [];
$productosAgotados = [];
$sumaPreciosBase = 0.0;
$stockActualTotal = 0;
$stockDisponibleTotal = 0;
$unidadesVendidasTotal = 0;
$unidadesComprometidasTotal = 0;
$ultimaActualizacion = null;

$categoriasResumen = [];
foreach ($categoriasListado as $categoria) {
    $categoriaId = (int) ($categoria['id'] ?? 0);
    $categoriasResumen[$categoriaId] = [
        'id' => $categoriaId,
        'nombre' => trim((string) ($categoria['tipo_categoria'] ?? 'Sin nombre')),
        'estado' => strtolower(trim((string) ($categoria['estado'] ?? 'activo'))),
        'productos' => 0,
        'activos' => 0,
        'criticos' => 0,
    ];
}

foreach ($inventario as $item) {
    $stock = max(0, (int) ($item['stock'] ?? 0));
    $stockActual = max(0, (int) ($item['stock_actual'] ?? $stock));
    $stockMinimo = max(0, (int) ($item['stock_minimo'] ?? 0));
    $stockDisponible = max(0, (int) ($item['stock_disponible'] ?? $stockActual));
    $unidadesVendidas = max(0, (int) ($item['unidades_vendidas'] ?? 0));
    $unidadesComprometidas = max(0, (int) ($item['unidades_comprometidas'] ?? 0));
    $estadoProducto = strtolower(trim((string) ($item['estado_producto'] ?? 'activo')));
    $descripcion = trim((string) ($item['descripcion'] ?? ''));
    $idCategoriaRaw = $item['id_categoria'] ?? null;
    $categoriaKey = ($idCategoriaRaw === null || $idCategoriaRaw === '') ? 0 : (int) $idCategoriaRaw;
    $fechaActualizacion = trim((string) ($item['fecha_actualizacion'] ?? ''));

    if ($estadoProducto === 'inactivo') {
        $productosInactivos++;
    } else {
        $productosActivos++;
    }

    if ($descripcion === '') {
        $productosSinDescripcion++;
    }

    if ($categoriaKey === 0) {
        $productosSinCategoria++;
    }

    if ($unidadesVendidas > 0) {
        $productosConVenta++;
    }

    if ($unidadesComprometidas > 0) {
        $productosComprometidos++;
    }

    $sumaPreciosBase += (float) ($item['precio_base'] ?? 0);
    $stockActualTotal += $stockActual;
    $stockDisponibleTotal += $stockDisponible;
    $unidadesVendidasTotal += $unidadesVendidas;
    $unidadesComprometidasTotal += $unidadesComprometidas;

    if ($stock <= $stockMinimo) {
        $stockCriticoItems[] = $item;
    }

    if ($stock <= 0) {
        $productosAgotados[] = $item;
    }

    if ($fechaActualizacion !== '') {
        $timestamp = strtotime($fechaActualizacion);
        if ($timestamp !== false && ($ultimaActualizacion === null || $timestamp > $ultimaActualizacion)) {
            $ultimaActualizacion = $timestamp;
        }
    }

    if (!isset($categoriasResumen[$categoriaKey])) {
        $categoriasResumen[$categoriaKey] = [
            'id' => $categoriaKey,
            'nombre' => $categoriaKey === 0 ? 'Sin categoria' : ('Categoria #' . $categoriaKey),
            'estado' => $categoriaKey === 0 ? 'pendiente' : 'activo',
            'productos' => 0,
            'activos' => 0,
            'criticos' => 0,
        ];
    }

    $categoriasResumen[$categoriaKey]['productos']++;
    if ($estadoProducto !== 'inactivo') {
        $categoriasResumen[$categoriaKey]['activos']++;
    }
    if ($stock <= $stockMinimo) {
        $categoriasResumen[$categoriaKey]['criticos']++;
    }
}

usort($stockCriticoItems, static function (array $left, array $right): int {
    $stockLeft = (int) ($left['stock'] ?? 0);
    $stockRight = (int) ($right['stock'] ?? 0);
    if ($stockLeft === $stockRight) {
        return strcmp((string) ($left['nombre_producto'] ?? ''), (string) ($right['nombre_producto'] ?? ''));
    }

    return $stockLeft <=> $stockRight;
});

$productosComprometidosListado = array_values(array_filter($inventario, static function (array $item): bool {
    return (int) ($item['unidades_comprometidas'] ?? 0) > 0;
}));

usort($productosComprometidosListado, static function (array $left, array $right): int {
    $compromiso = (int) ($right['unidades_comprometidas'] ?? 0) <=> (int) ($left['unidades_comprometidas'] ?? 0);
    if ($compromiso !== 0) {
        return $compromiso;
    }

    return strcmp((string) ($left['nombre_producto'] ?? ''), (string) ($right['nombre_producto'] ?? ''));
});

$categoriasDistribucion = array_values($categoriasResumen);
usort($categoriasDistribucion, static function (array $left, array $right): int {
    $productosDiff = (int) ($right['productos'] ?? 0) <=> (int) ($left['productos'] ?? 0);
    if ($productosDiff !== 0) {
        return $productosDiff;
    }

    return strcmp((string) ($left['nombre'] ?? ''), (string) ($right['nombre'] ?? ''));
});

$categoriasActivas = 0;
foreach ($categoriasListado as $categoria) {
    if (strtolower(trim((string) ($categoria['estado'] ?? 'activo'))) !== 'inactivo') {
        $categoriasActivas++;
    }
}

$stockCriticoCount = count($stockCriticoItems);
$productosAgotadosCount = count($productosAgotados);
$stockSaludableCount = max(0, $totalProductos - $stockCriticoCount);
$coberturaStock = $totalProductos > 0 ? (int) round(($stockSaludableCount / $totalProductos) * 100) : 100;
$precioPromedio = $totalProductos > 0 ? ($sumaPreciosBase / $totalProductos) : 0.0;
$stockCriticoPreview = array_slice($stockCriticoItems, 0, 5);
$productosComprometidosPreview = array_slice($productosComprometidosListado, 0, 5);
$productosRecientes = array_slice($inventario, 0, 5);
$stockModeTitle = $stockColumnsEnabled ? 'Stock real habilitado' : 'Stock estimado por historial';
$stockModeDescription = $stockColumnsEnabled
    ? 'Cada producto maneja stock actual y stock minimo desde su ficha.'
    : 'La vista sigue operativa, pero el stock mostrado usa una referencia calculada desde ventas.';

$formatearFecha = static function (?string $value): string {
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

$ultimaActualizacionLabel = $ultimaActualizacion !== null
    ? date('d/m/Y H:i', $ultimaActualizacion)
    : 'Sin movimientos recientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/inventario.css">
</head>
<body>

<div class="inventario-container">
    <section class="inventario-hero">
        <div class="inventario-hero-copy">
            <p class="inventario-eyebrow">Control operativo de inventario</p>
            <h1>Inventario profesional y sincronizado</h1>
            <p class="inventario-description">
                Controla el catalogo con stock real, productos comprometidos en pedidos y salida historica por ventas
                desde un solo modulo operativo.
            </p>

            <div class="inventario-hero-meta">
                <span><?= htmlspecialchars($stockModeTitle, ENT_QUOTES, 'UTF-8') ?></span>
                <span>Ultima actualizacion: <?= htmlspecialchars($ultimaActualizacionLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= $coberturaStock ?>% del catalogo por encima del minimo</span>
                <span><?= $productosSinDescripcion ?> fichas por completar</span>
            </div>
        </div>

        <div class="inventario-header-actions">
            <button class="btn categoria" type="button" data-action="nueva-categoria">Nueva Categoria</button>
            <button class="btn nuevo" type="button" data-action="nuevo-producto">Nuevo Producto</button>
        </div>
    </section>

    <section class="inventario-stats-grid" aria-label="Resumen de inventario">
        <article class="inventario-stat-card">
            <span class="inventario-stat-label">Productos catalogados</span>
            <strong><?= $totalProductos ?></strong>
            <p><?= $productosActivos ?> activos y <?= $productosInactivos ?> inactivos.</p>
        </article>

        <article class="inventario-stat-card">
            <span class="inventario-stat-label">Categorias en uso</span>
            <strong><?= $totalCategorias ?></strong>
            <p><?= $categoriasActivas ?> activas y <?= $productosSinCategoria ?> productos sin categoria.</p>
        </article>

        <article class="inventario-stat-card">
            <span class="inventario-stat-label">Stock disponible</span>
            <strong><?= $stockDisponibleTotal ?></strong>
            <p><?= $stockActualTotal ?> unidades registradas y <?= $unidadesComprometidasTotal ?> comprometidas.</p>
        </article>

        <article class="inventario-stat-card">
            <span class="inventario-stat-label">Demanda activa</span>
            <strong><?= $unidadesComprometidasTotal ?></strong>
            <p><?= $productosComprometidos ?> productos con pedidos pendientes de salida.</p>
        </article>

        <article class="inventario-stat-card inventario-stat-card-alert">
            <span class="inventario-stat-label">Alertas de stock</span>
            <strong><?= $stockCriticoCount ?></strong>
            <p><?= $productosAgotadosCount ?> agotados y <?= $stockSaludableCount ?> con cobertura estable.</p>
        </article>

        <article class="inventario-stat-card">
            <span class="inventario-stat-label">Salida historica</span>
            <strong><?= $unidadesVendidasTotal ?></strong>
            <p><?= $productosConVenta ?> productos ya tuvieron movimiento en ventas.</p>
        </article>
    </section>

    <section class="inventario-overview-grid">
        <article class="inventario-panel">
            <div class="inventario-panel-header">
                <div>
                    <p class="inventario-panel-kicker">Mapa del catalogo</p>
                    <h2 class="inventario-section-title">Categorias</h2>
                </div>
                <span class="inventario-panel-badge"><?= $categoriasActivas ?> activas</span>
            </div>

            <p class="inventario-panel-copy">
                Las categorias activas siguen alimentando el formulario de productos y ahora ayudan a leer mejor el
                volumen y el nivel de alerta del catalogo.
            </p>

            <div class="inventario-category-grid">
                <?php foreach (array_slice($categoriasDistribucion, 0, 6) as $categoriaResumen): ?>
                    <?php
                    $categoriaNombre = (string) ($categoriaResumen['nombre'] ?? 'Sin categoria');
                    $categoriaProductos = (int) ($categoriaResumen['productos'] ?? 0);
                    $categoriaCriticos = (int) ($categoriaResumen['criticos'] ?? 0);
                    $categoriaActivosResumen = (int) ($categoriaResumen['activos'] ?? 0);
                    ?>
                    <article class="inventario-category-card">
                        <strong><?= htmlspecialchars($categoriaNombre, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= $categoriaProductos ?> productos</span>
                        <span><?= $categoriaActivosResumen ?> activos</span>
                        <span><?= $categoriaCriticos > 0 ? ($categoriaCriticos . ' en alerta') : 'Sin alertas' ?></span>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="inventario-table-wrap">
                <table class="tabla-categorias">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoria</th>
                            <th>Productos</th>
                            <th>Salud</th>
                            <th>Estado</th>
                            <th class="acciones-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categoriasListado)): ?>
                            <?php foreach ($categoriasListado as $categoria): ?>
                                <?php
                                $categoriaId = (int) ($categoria['id'] ?? 0);
                                $categoriaStats = $categoriasResumen[$categoriaId] ?? [
                                    'productos' => 0,
                                    'criticos' => 0,
                                ];
                                $categoriaEstado = strtolower(trim((string) ($categoria['estado'] ?? 'activo')));
                                $categoriaCriticos = (int) ($categoriaStats['criticos'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= $categoriaId ?></td>
                                    <td class="left">
                                        <div class="inventario-table-title">
                                            <strong><?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span>Disponible para clasificar productos del catalogo.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inventario-count-pill"><?= (int) ($categoriaStats['productos'] ?? 0) ?> items</span>
                                    </td>
                                    <td>
                                        <?php if ($categoriaCriticos > 0): ?>
                                            <span class="inventario-health-pill danger"><?= $categoriaCriticos ?> en alerta</span>
                                        <?php else: ?>
                                            <span class="inventario-health-pill">Sin alertas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($categoriaEstado === 'inactivo'): ?>
                                            <span class="badge inactivo">Inactivo</span>
                                        <?php else: ?>
                                            <span class="badge activo">Activo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="acciones">
                                        <button
                                            class="btn editar"
                                            type="button"
                                            data-action="editar-categoria"
                                            data-id="<?= $categoriaId ?>"
                                            data-tipo="<?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-estado="<?= htmlspecialchars((string) ($categoria['estado'] ?? 'activo'), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            class="btn eliminar"
                                            type="button"
                                            data-action="eliminar-categoria"
                                            data-id="<?= $categoriaId ?>"
                                            data-tipo="<?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="inventario-empty-cell">No hay categorias registradas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="inventario-panel inventario-panel-alerts">
            <div class="inventario-panel-header">
                <div>
                    <p class="inventario-panel-kicker">Seguimiento operativo</p>
                    <h2 class="inventario-section-title">Alertas y sincronizacion</h2>
                </div>
                <span class="inventario-panel-badge"><?= $stockCriticoCount ?> pendientes</span>
            </div>

            <div class="inventario-alert-block inventario-alert-block-muted">
                <h3><?= htmlspecialchars($stockModeTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars($stockModeDescription, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="inventario-alert-block">
                <h3>Stock critico</h3>
                <?php if (!empty($stockCriticoPreview)): ?>
                    <ul class="inventario-alert-list">
                        <?php foreach ($stockCriticoPreview as $item): ?>
                            <?php
                            $stock = (int) ($item['stock'] ?? 0);
                            $categoria = trim((string) ($item['tipo_categoria'] ?? 'Sin categoria'));
                            ?>
                            <li>
                                <div>
                                    <strong><?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($categoria !== '' ? $categoria : 'Sin categoria', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="inventario-alert-value <?= $stock <= 0 ? 'danger' : '' ?>">
                                    <?= $stock <= 0 ? 'Agotado' : ('Stock ' . $stock) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="inventario-alert-empty">No hay productos por debajo del minimo configurado.</p>
                <?php endif; ?>
            </div>

            <div class="inventario-alert-block">
                <h3>Pedidos comprometidos</h3>
                <?php if (!empty($productosComprometidosPreview)): ?>
                    <ul class="inventario-alert-list">
                        <?php foreach ($productosComprometidosPreview as $item): ?>
                            <li>
                                <div>
                                    <strong><?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= (int) ($item['stock_disponible'] ?? 0) ?> disponibles tras reserva</span>
                                </div>
                                <span class="inventario-alert-value">
                                    <?= (int) ($item['unidades_comprometidas'] ?? 0) ?> comprometidas
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="inventario-alert-empty">No hay productos comprometidos en pedidos pendientes.</p>
                <?php endif; ?>
            </div>

            <div class="inventario-alert-block">
                <h3>Actualizados recientemente</h3>
                <?php if (!empty($productosRecientes)): ?>
                    <ul class="inventario-alert-list">
                        <?php foreach ($productosRecientes as $item): ?>
                            <li>
                                <div>
                                    <strong><?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($formatearFecha((string) ($item['fecha_actualizacion'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="inventario-alert-value">
                                    $<?= number_format((float) ($item['precio_base'] ?? 0), 2) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="inventario-alert-empty">Todavia no hay movimientos recientes en productos.</p>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="inventario-panel inventario-panel-products">
        <div class="inventario-panel-header">
            <div>
                <p class="inventario-panel-kicker">Vista detallada</p>
                <h2 class="inventario-section-title">Productos</h2>
            </div>
            <span class="inventario-panel-badge"><?= $totalProductos ?> registrados</span>
        </div>

        <div class="inventario-toolbar">
            <span class="inventario-toolbar-pill"><?= $stockDisponibleTotal ?> disponibles</span>
            <span class="inventario-toolbar-pill"><?= $unidadesComprometidasTotal ?> comprometidas</span>
            <span class="inventario-toolbar-pill"><?= $unidadesVendidasTotal ?> vendidas</span>
            <span class="inventario-toolbar-pill"><?= $productosSinDescripcion ?> sin descripcion</span>
        </div>

        <div class="inventario-table-wrap">
            <table class="tabla-inventario">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Categoria</th>
                        <th>Precio Base</th>
                        <th>Stock</th>
                        <th>Minimo</th>
                        <th>Estado</th>
                        <th>Actualizado</th>
                        <th class="acciones-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($inventario)): ?>
                        <?php foreach ($inventario as $item): ?>
                            <?php
                            $stock = max(0, (int) ($item['stock'] ?? 0));
                            $stockActual = max(0, (int) ($item['stock_actual'] ?? $stock));
                            $stockMinimo = max(0, (int) ($item['stock_minimo'] ?? 0));
                            $stockDisponible = max(0, (int) ($item['stock_disponible'] ?? $stockActual));
                            $unidadesVendidas = max(0, (int) ($item['unidades_vendidas'] ?? 0));
                            $unidadesComprometidas = max(0, (int) ($item['unidades_comprometidas'] ?? 0));
                            $lowStock = $stock <= $stockMinimo;
                            $stockTone = (string) ($item['estado_stock'] ?? 'estable');
                            $stockProgressBase = max($stockMinimo > 0 ? ($stockMinimo * 2) : 10, $stockActual > 0 ? $stockActual : 1);
                            $stockProgress = (int) max(0, min(100, round(($stockActual / $stockProgressBase) * 100)));
                            $categoriaNombre = trim((string) ($item['tipo_categoria'] ?? 'Sin categoria'));
                            $ultimaVenta = trim((string) ($item['ultima_venta'] ?? ''));
                            $descripcionProducto = trim((string) ($item['descripcion'] ?? ''));
                            ?>
                            <tr class="<?= $lowStock ? 'low-stock' : '' ?> <?= $stock <= 0 ? 'stock-zero' : '' ?>">
                                <td><?= (int) ($item['id'] ?? 0) ?></td>
                                <td class="left">
                                    <div class="inventario-table-title">
                                        <strong><?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span>
                                            <?= htmlspecialchars(
                                                $descripcionProducto !== '' ? $descripcionProducto : 'Sin descripcion operativa.',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                        <?php if ($ultimaVenta !== ''): ?>
                                            <small class="inventario-inline-meta">
                                                Ultima venta: <?= htmlspecialchars($formatearFecha($ultimaVenta), ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="inventario-category-pill">
                                        <?= htmlspecialchars($categoriaNombre !== '' ? $categoriaNombre : 'Sin categoria', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="inventario-price">$<?= number_format((float) ($item['precio_base'] ?? 0), 2) ?></span>
                                </td>
                                <td>
                                    <div class="inventario-stock-cell">
                                        <strong><?= $stockActual ?></strong>
                                        <div class="inventario-stock-bar">
                                            <span class="inventario-stock-fill <?= htmlspecialchars($stockTone, ENT_QUOTES, 'UTF-8') ?>" style="width: <?= $stockProgress ?>%;"></span>
                                        </div>
                                        <div class="inventario-stock-meta">
                                            <span>Disp. <?= $stockDisponible ?></span>
                                            <span>Comp. <?= $unidadesComprometidas ?></span>
                                            <span>Vend. <?= $unidadesVendidas ?></span>
                                        </div>
                                        <small>
                                            <?php if ($stock <= 0): ?>
                                                Agotado
                                            <?php elseif ($stockTone === 'critico'): ?>
                                                Bajo minimo
                                            <?php elseif ($stockTone === 'ajustado'): ?>
                                                Ajustado por pedidos comprometidos
                                            <?php else: ?>
                                                Cobertura estable
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td><?= $stockMinimo ?></td>
                                <td>
                                    <?php if (strtolower(trim((string) ($item['estado_producto'] ?? 'activo'))) === 'inactivo'): ?>
                                        <span class="badge inactivo">Inactivo</span>
                                    <?php else: ?>
                                        <span class="badge activo">Activo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($formatearFecha((string) ($item['fecha_actualizacion'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="acciones">
                                    <button
                                        class="btn editar"
                                        type="button"
                                        data-action="editar-producto"
                                        data-id="<?= (int) ($item['id'] ?? 0) ?>"
                                        data-id-categoria="<?= htmlspecialchars((string) ($item['id_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-nombre="<?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-descripcion="<?= htmlspecialchars((string) ($item['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-precio="<?= htmlspecialchars((string) ($item['precio_base'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                        data-stock-actual="<?= $stockActual ?>"
                                        data-stock-minimo="<?= $stockMinimo ?>"
                                        data-estado="<?= htmlspecialchars((string) ($item['estado_producto'] ?? 'activo'), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        class="btn eliminar"
                                        type="button"
                                        data-action="eliminar-producto"
                                        data-id="<?= (int) ($item['id'] ?? 0) ?>"
                                        data-nombre="<?= htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="inventario-empty-cell">No hay productos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="inventario-modal" id="categoria-create-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="categoria-create-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="categoria-create-title">
        <div class="inventario-modal-header">
            <div>
                <p class="inventario-modal-kicker">Catalogo</p>
                <h3 id="categoria-create-title">Nueva Categoria</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="categoria-create-modal" aria-label="Cerrar creacion">x</button>
        </div>

        <form id="categoria-create-form" class="producto-form">
            <p class="inventario-form-note">
                Las categorias activas quedan disponibles inmediatamente para clasificar productos nuevos.
            </p>

            <div class="inventario-form-grid inventario-form-grid-compact">
                <div class="inventario-field inventario-field-wide">
                    <label for="categoria-create-tipo">Nombre de la Categoria</label>
                    <input id="categoria-create-tipo" name="tipo_categoria" type="text" maxlength="100" required>
                </div>

                <div class="inventario-field">
                    <label for="categoria-create-estado">Estado</label>
                    <select id="categoria-create-estado" name="estado" required>
                        <option value="activo" selected>Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>

            <p class="producto-feedback" id="categoria-create-feedback" hidden></p>

            <div class="producto-actions">
                <button class="btn cancelar" type="button" data-close="categoria-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="categoria-create-submit">Guardar Categoria</button>
            </div>
        </form>
    </div>
</div>

<div class="inventario-modal" id="categoria-edit-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="categoria-edit-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="categoria-edit-title">
        <div class="inventario-modal-header">
            <div>
                <p class="inventario-modal-kicker">Catalogo</p>
                <h3 id="categoria-edit-title">Editar Categoria</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="categoria-edit-modal" aria-label="Cerrar edicion">x</button>
        </div>

        <form id="categoria-edit-form" class="producto-form">
            <input type="hidden" id="categoria-edit-id" name="id">

            <p class="inventario-form-note">
                Actualiza el nombre o desactiva la categoria cuando ya no deba aparecer en nuevos productos.
            </p>

            <div class="inventario-form-grid inventario-form-grid-compact">
                <div class="inventario-field inventario-field-wide">
                    <label for="categoria-edit-tipo">Nombre de la Categoria</label>
                    <input id="categoria-edit-tipo" name="tipo_categoria" type="text" maxlength="100" required>
                </div>

                <div class="inventario-field">
                    <label for="categoria-edit-estado">Estado</label>
                    <select id="categoria-edit-estado" name="estado" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>

            <p class="producto-feedback" id="categoria-edit-feedback" hidden></p>

            <div class="producto-actions">
                <button class="btn cancelar" type="button" data-close="categoria-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="categoria-edit-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="inventario-modal" id="producto-create-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="producto-create-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="producto-create-title">
        <div class="inventario-modal-header">
            <div>
                <p class="inventario-modal-kicker">Catalogo</p>
                <h3 id="producto-create-title">Nuevo Producto</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="producto-create-modal" aria-label="Cerrar creacion">x</button>
        </div>

        <form id="producto-create-form" class="producto-form">
            <p class="inventario-form-note">
                Mantener categoria, precio base, stock y descripcion al dia mejora la lectura del inventario y su
                sincronizacion con pedidos y ventas.
            </p>

            <?php if (!$stockColumnsEnabled): ?>
                <p class="inventario-form-note inventario-form-note-warning">
                    Este entorno aun esta mostrando stock estimado. Al habilitar columnas fisicas de stock, estos datos
                    quedaran administrables desde cada producto.
                </p>
            <?php endif; ?>

            <div class="inventario-form-grid">
                <div class="inventario-field">
                    <label for="producto-create-categoria">Categoria</label>
                    <select id="producto-create-categoria" name="id_categoria">
                        <option value="">Sin categoria</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= (int) ($categoria['id'] ?? 0) ?>">
                                <?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="inventario-field">
                    <label for="producto-create-estado">Estado</label>
                    <select id="producto-create-estado" name="estado" required>
                        <option value="activo" selected>Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="inventario-field inventario-field-wide">
                    <label for="producto-create-nombre">Nombre del Producto</label>
                    <input id="producto-create-nombre" name="nombre_producto" type="text" maxlength="100" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-create-precio">Precio Base</label>
                    <input id="producto-create-precio" name="precio_base" type="number" step="0.01" min="0.01" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-create-stock-actual">Stock Actual</label>
                    <input id="producto-create-stock-actual" name="stock_actual" type="number" min="0" step="1" value="0" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-create-stock-minimo">Stock Minimo</label>
                    <input id="producto-create-stock-minimo" name="stock_minimo" type="number" min="0" step="1" value="5" required>
                </div>

                <div class="inventario-field inventario-field-full">
                    <label for="producto-create-descripcion">Descripcion</label>
                    <textarea id="producto-create-descripcion" name="descripcion" rows="4"></textarea>
                </div>
            </div>

            <p class="producto-feedback" id="producto-create-feedback" hidden></p>

            <div class="producto-actions">
                <button class="btn cancelar" type="button" data-close="producto-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="producto-create-submit">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<div class="inventario-modal" id="producto-edit-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="producto-edit-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="producto-edit-title">
        <div class="inventario-modal-header">
            <div>
                <p class="inventario-modal-kicker">Catalogo</p>
                <h3 id="producto-edit-title">Editar Producto</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="producto-edit-modal" aria-label="Cerrar edicion">x</button>
        </div>

        <form id="producto-edit-form" class="producto-form">
            <input type="hidden" id="producto-edit-id" name="id">

            <p class="inventario-form-note">
                Los cambios se reflejan al recargar el modulo y mantienen el inventario alineado con pedidos, ventas y
                categorias activas.
            </p>

            <?php if (!$stockColumnsEnabled): ?>
                <p class="inventario-form-note inventario-form-note-warning">
                    Este entorno aun esta usando stock estimado. Los campos de stock se guardaran cuando la estructura
                    de la tabla de productos incluya columnas fisicas de stock.
                </p>
            <?php endif; ?>

            <div class="inventario-form-grid">
                <div class="inventario-field">
                    <label for="producto-edit-categoria">Categoria</label>
                    <select id="producto-edit-categoria" name="id_categoria">
                        <option value="">Sin categoria</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= (int) ($categoria['id'] ?? 0) ?>">
                                <?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="inventario-field">
                    <label for="producto-edit-estado">Estado</label>
                    <select id="producto-edit-estado" name="estado" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="inventario-field inventario-field-wide">
                    <label for="producto-edit-nombre">Nombre del Producto</label>
                    <input id="producto-edit-nombre" name="nombre_producto" type="text" maxlength="100" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-edit-precio">Precio Base</label>
                    <input id="producto-edit-precio" name="precio_base" type="number" step="0.01" min="0.01" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-edit-stock-actual">Stock Actual</label>
                    <input id="producto-edit-stock-actual" name="stock_actual" type="number" min="0" step="1" value="0" required>
                </div>

                <div class="inventario-field">
                    <label for="producto-edit-stock-minimo">Stock Minimo</label>
                    <input id="producto-edit-stock-minimo" name="stock_minimo" type="number" min="0" step="1" value="5" required>
                </div>

                <div class="inventario-field inventario-field-full">
                    <label for="producto-edit-descripcion">Descripcion</label>
                    <textarea id="producto-edit-descripcion" name="descripcion" rows="4"></textarea>
                </div>
            </div>

            <p class="producto-feedback" id="producto-edit-feedback" hidden></p>

            <div class="producto-actions">
                <button class="btn cancelar" type="button" data-close="producto-edit-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="producto-edit-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
