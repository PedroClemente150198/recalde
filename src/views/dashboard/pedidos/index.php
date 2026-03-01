<?php
$pedidos = $pedidos ?? [];
$clientes = $clientes ?? [];
$productos = $productos ?? [];

$totalPedidos = count($pedidos);
$totalImporte = 0.0;
$pedidosPendientes = 0;
$pedidosProcesando = 0;
$pedidosListos = 0;
$pedidosEnSeguimiento = 0;
$pedidosCerrados = 0;
$pedidosCancelados = 0;
$clientesAtendidos = [];
$ultimaActividadTs = null;

foreach ($pedidos as $pedido) {
    $totalImporte += (float) ($pedido['total'] ?? 0);

    $estadoPedido = strtolower(trim((string) ($pedido['estado'] ?? 'desconocido')));
    if ($estadoPedido === 'pendiente') {
        $pedidosPendientes++;
    }
    if ($estadoPedido === 'procesando') {
        $pedidosProcesando++;
    }
    if ($estadoPedido === 'listo') {
        $pedidosListos++;
    }
    if (in_array($estadoPedido, ['pendiente', 'procesando', 'listo'], true)) {
        $pedidosEnSeguimiento++;
    }
    if ($estadoPedido === 'cancelado') {
        $pedidosCancelados++;
    }
    if (in_array($estadoPedido, ['entregado', 'finalizado'], true)) {
        $pedidosCerrados++;
    }

    $clienteKey = trim((string) ($pedido['cedula'] ?? ''));
    if ($clienteKey === '') {
        $clienteKey = trim((string) (($pedido['nombre'] ?? '') . '|' . ($pedido['apellido'] ?? '')));
    }
    if ($clienteKey !== '') {
        $clientesAtendidos[$clienteKey] = true;
    }

    $fechaPedidoTs = strtotime((string) ($pedido['fecha_creacion'] ?? ''));
    if ($fechaPedidoTs !== false && ($ultimaActividadTs === null || $fechaPedidoTs > $ultimaActividadTs)) {
        $ultimaActividadTs = $fechaPedidoTs;
    }
}

$ticketPromedio = $totalPedidos > 0 ? $totalImporte / $totalPedidos : 0.0;
$ultimaActividad = $ultimaActividadTs !== null ? date('d/m/Y H:i', $ultimaActividadTs) : 'Sin registros';
$clientesAtendidosTotal = count($clientesAtendidos);
?>

<link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/pedidos.css">

<div class="pedidos-container">
    <section class="pedidos-hero pedidos-panel">
        <div class="pedidos-hero-copy">
            <span class="pedidos-eyebrow">Operacion diaria</span>
            <h2>Gestión de Pedidos</h2>
            <p>
                Supervisa los pedidos activos, revisa el detalle de prendas y actualiza estados desde una sola vista operativa.
            </p>
        </div>

        <div class="pedidos-hero-actions">
            <div class="pedidos-hero-meta">
                <span>Última actividad</span>
                <strong><?= htmlspecialchars($ultimaActividad, ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= $totalPedidos > 0 ? htmlspecialchars((string) $totalPedidos, ENT_QUOTES, 'UTF-8') . ' pedidos en historial visible' : 'Aún no hay pedidos cargados' ?></small>
            </div>

            <button class="btn nuevo" type="button" data-action="nuevo-pedido">Nuevo Pedido</button>
        </div>
    </section>

    <section class="pedidos-kpi-grid">
        <article class="pedidos-kpi-card pedidos-panel">
            <span>Pedidos registrados</span>
            <strong><?= (int) $totalPedidos ?></strong>
            <small><?= (int) $clientesAtendidosTotal ?> clientes con actividad</small>
        </article>

        <article class="pedidos-kpi-card pedidos-panel">
            <span>En seguimiento</span>
            <strong><?= (int) $pedidosEnSeguimiento ?></strong>
            <small><?= (int) $pedidosPendientes ?> pendientes de iniciar</small>
        </article>

        <article class="pedidos-kpi-card pedidos-panel">
            <span>Cerrados</span>
            <strong><?= (int) $pedidosCerrados ?></strong>
            <small><?= (int) $pedidosCancelados ?> cancelados en el periodo visible</small>
        </article>

        <article class="pedidos-kpi-card pedidos-panel">
            <span>Ticket promedio</span>
            <strong>$<?= number_format($ticketPromedio, 2) ?></strong>
            <small>Total visible: $<?= number_format($totalImporte, 2) ?></small>
        </article>
    </section>

    <section class="pedidos-panel pedidos-list-panel">
        <div class="pedidos-list-head">
            <div>
                <h3>Listado Operativo</h3>
                <p>Consulta cada pedido, revisa su detalle y cambia el estado sin salir del módulo.</p>
            </div>

            <div class="pedidos-state-strip" aria-label="Resumen de estados">
                <span class="pedidos-state-pill status-pendiente">Pendientes <strong><?= (int) $pedidosPendientes ?></strong></span>
                <span class="pedidos-state-pill status-procesando">Procesando <strong><?= (int) $pedidosProcesando ?></strong></span>
                <span class="pedidos-state-pill status-listo">Listos <strong><?= (int) $pedidosListos ?></strong></span>
                <span class="pedidos-state-pill status-entregado">Entregados <strong><?= (int) $pedidosCerrados ?></strong></span>
                <span class="pedidos-state-pill status-cancelado">Cancelados <strong><?= (int) $pedidosCancelados ?></strong></span>
            </div>
        </div>

        <div class="pedidos-table-shell">
            <table class="pedidos-table" data-page-size="8">
                <thead>
                    <tr>
                        <th>Pedido</th>
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
                            <?php
                            $estado = strtolower((string) ($pedido['estado'] ?? 'desconocido'));
                            $estadoClass = preg_replace('/[^a-z0-9_-]/', '', $estado) ?: 'desconocido';
                            $fechaPedidoRaw = (string) ($pedido['fecha_creacion'] ?? '');
                            $fechaPedidoTs = strtotime($fechaPedidoRaw);
                            $fechaPedido = $fechaPedidoTs !== false ? date('d/m/Y H:i', $fechaPedidoTs) : ($fechaPedidoRaw !== '' ? $fechaPedidoRaw : '-');
                            $clienteNombre = trim((string) (($pedido['nombre'] ?? '') . ' ' . ($pedido['apellido'] ?? '')));
                            ?>
                            <?php $idVenta = (int) ($pedido['id_venta'] ?? 0); ?>
                            <tr
                                class="pedido-row-trigger"
                                data-pedido-row="1"
                                data-id="<?= (int) ($pedido['id'] ?? 0) ?>"
                                data-cliente="<?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : 'Cliente sin nombre', ENT_QUOTES, 'UTF-8') ?>"
                                data-cedula="<?= htmlspecialchars((string) ($pedido['cedula'] ?? 'Sin cédula'), ENT_QUOTES, 'UTF-8') ?>"
                                data-total-label="<?= htmlspecialchars('$' . number_format((float) ($pedido['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?>"
                                data-fecha="<?= htmlspecialchars($fechaPedido, ENT_QUOTES, 'UTF-8') ?>"
                                data-estado="<?= htmlspecialchars((string) ($pedido['estado'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8') ?>"
                                data-venta-id="<?= $idVenta ?>"
                                title="Haz clic para ver acciones del pedido"
                            >
                                <td class="pedidos-id-cell" data-label="Pedido">
                                    <strong>#<?= (int) ($pedido['id'] ?? 0) ?></strong>
                                    <small>Pedido</small>
                                </td>

                                <td class="pedidos-client-cell" data-label="Cliente">
                                    <strong><?= htmlspecialchars($clienteNombre !== '' ? $clienteNombre : 'Cliente sin nombre', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small>Registro asociado al cliente</small>
                                </td>

                                <td data-label="Cédula">
                                    <?= htmlspecialchars((string) ($pedido['cedula'] ?? 'Sin cédula'), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="pedidos-total-cell" data-label="Total">
                                    $<?= number_format((float) ($pedido['total'] ?? 0), 2) ?>
                                </td>

                                <td class="pedidos-date-cell" data-label="Fecha Pedido">
                                    <?= htmlspecialchars($fechaPedido, ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td data-label="Estado">
                                    <span class="status-<?= htmlspecialchars($estadoClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($pedido['estado'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td class="pedidos-actions-cell" data-label="Acciones">
                                    <div class="pedidos-actions">
                                        <button class="btn ver" type="button" data-action="ver-pedido" data-id="<?= (int) ($pedido['id'] ?? 0) ?>">
                                            Ver
                                        </button>
                                        <button class="btn editar" type="button" data-action="editar-pedido" data-id="<?= (int) ($pedido['id'] ?? 0) ?>">
                                            Editar
                                        </button>
                                        <button
                                            class="btn eliminar"
                                            type="button"
                                            data-action="eliminar-pedido"
                                            data-id="<?= (int) ($pedido['id'] ?? 0) ?>"
                                            <?= $idVenta > 0 ? 'disabled title="No puedes eliminar un pedido que ya tiene una venta asociada."' : '' ?>
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="pedidos-empty-state">No hay pedidos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="pedido-modal" id="pedido-actions-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-actions-modal"></div>
    <div class="pedido-modal-content pedido-modal-content-compact" role="dialog" aria-modal="true" aria-labelledby="pedido-actions-title">
        <div class="pedido-modal-header">
            <div class="pedido-actions-header-copy">
                <span class="pedido-actions-kicker">Gestion inmediata</span>
                <h3 id="pedido-actions-title">Acciones del Pedido</h3>
            </div>
            <button class="btn cerrar" type="button" data-close="pedido-actions-modal" aria-label="Cerrar acciones">x</button>
        </div>

        <section class="pedido-actions-hero">
            <div class="pedido-actions-hero-main">
                <span class="pedido-actions-hero-label">Pedido activo</span>
                <strong class="pedido-actions-hero-id" id="pedido-actions-id">-</strong>
                <p class="pedido-actions-hero-client" id="pedido-actions-cliente">-</p>
            </div>

            <div class="pedido-actions-hero-side">
                <div class="pedido-actions-chip-card">
                    <span>Estado</span>
                    <strong id="pedido-actions-estado">-</strong>
                </div>
                <div class="pedido-actions-chip-card">
                    <span>Venta asociada</span>
                    <strong id="pedido-actions-venta">Sin venta</strong>
                </div>
            </div>
        </section>

        <div class="pedido-actions-summary">
            <p><strong>Cédula</strong><span id="pedido-actions-cedula">-</span></p>
            <p><strong>Total</strong><span id="pedido-actions-total">$0.00</span></p>
            <p><strong>Fecha</strong><span id="pedido-actions-fecha">-</span></p>
        </div>

        <div class="pedido-actions-grid">
            <button class="btn ver pedido-actions-btn" type="button" id="pedido-actions-ver" data-action="ver-pedido">
                Ver detalle
            </button>
            <button class="btn editar pedido-actions-btn" type="button" id="pedido-actions-editar" data-action="editar-pedido">
                Editar pedido
            </button>
            <button class="btn eliminar pedido-actions-btn" type="button" id="pedido-actions-eliminar" data-action="eliminar-pedido">
                Eliminar pedido
            </button>
        </div>

        <p class="pedido-actions-hint" id="pedido-actions-hint">Gestiona este pedido desde una sola acción rápida.</p>
    </div>
</div>

<div class="pedido-modal" id="pedido-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-modal"></div>
    <div class="pedido-modal-content" role="dialog" aria-modal="true" aria-labelledby="pedido-modal-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-modal-title">Detalle del Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-modal" aria-label="Cerrar detalle">x</button>
        </div>

        <div class="pedido-modal-body">
            <div class="pedido-detail-grid">
                <article class="pedido-detail-card">
                    <span>Cliente</span>
                    <strong id="pedido-detalle-cliente">-</strong>
                </article>
                <article class="pedido-detail-card">
                    <span>Cédula</span>
                    <strong id="pedido-detalle-cedula">-</strong>
                </article>
                <article class="pedido-detail-card">
                    <span>Estado</span>
                    <strong id="pedido-detalle-estado">-</strong>
                </article>
                <article class="pedido-detail-card">
                    <span>Fecha</span>
                    <strong id="pedido-detalle-fecha">-</strong>
                </article>
                <article class="pedido-detail-card pedido-detail-card-total">
                    <span>Total</span>
                    <strong id="pedido-detalle-total">-</strong>
                </article>
            </div>

            <div class="pedido-detail-section">
                <div class="pedido-section-head">
                    <h4>Productos</h4>
                    <p>Detalle completo de prendas, cantidades y medidas registradas.</p>
                </div>

                <table class="pedidos-table pedido-detalle-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Subtotal</th>
                            <th>Medidas</th>
                        </tr>
                    </thead>
                    <tbody id="pedido-detalle-items">
                        <tr>
                            <td colspan="5" class="pedidos-empty-state">Cargando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="pedido-modal pedido-edit-modal" id="pedido-edit-modal" hidden>
    <div class="pedido-modal-backdrop" data-close="pedido-edit-modal"></div>
    <div class="pedido-modal-content pedido-modal-content-wide" role="dialog" aria-modal="true" aria-labelledby="pedido-edit-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-edit-title">Editar Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="pedido-edit-form" class="pedido-edit-form">
            <input type="hidden" name="id" id="pedido-edit-id">

            <div class="pedido-form-grid">
                <div class="pedido-field">
                    <label for="pedido-edit-cliente">Cliente</label>
                    <select id="pedido-edit-cliente" name="id_cliente" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>">
                                <?= htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                <?= !empty($cliente['cedula']) ? ' - ' . htmlspecialchars((string) $cliente['cedula'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pedido-field">
                    <label for="pedido-edit-estado">Estado</label>
                    <select name="estado" id="pedido-edit-estado" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="procesando">Procesando</option>
                        <option value="listo">Listo</option>
                        <option value="entregado">Entregado</option>
                    </select>
                    <small class="pedido-field-help">Si el pedido ya fue liquidado, al guardar volverá a mostrarse como cancelado automáticamente.</small>
                </div>
            </div>

            <section class="pedido-create-section">
                <div class="pedido-section-head">
                    <h4>Productos del Pedido</h4>
                    <p>Edita cantidades, productos y medidas registradas en el pedido completo.</p>
                </div>

                <div class="pedido-items" id="pedido-edit-items">
                    <div class="pedido-item-row">
                        <div class="pedido-item-main">
                            <select class="pedido-item-producto" required>
                                <option value="">Selecciona producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option
                                        value="<?= (int) $producto['id'] ?>"
                                        data-precio="<?= htmlspecialchars((string) $producto['precio_base'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars((string) $producto['nombre_producto'], ENT_QUOTES, 'UTF-8') ?> - $<?= number_format((float) $producto['precio_base'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input class="pedido-item-cantidad" type="number" min="1" step="1" value="1" required>
                            <button class="btn quitar" type="button" data-action="quitar-item-pedido">Quitar</button>
                        </div>

                        <div class="pedido-item-medidas">
                            <div class="pedido-item-medidas-header">
                                <span>Medidas por persona (opcional)</span>
                                <button class="btn agregar-medida" type="button" data-action="agregar-medida-item">Agregar Persona</button>
                            </div>

                            <div class="pedido-medidas-list">
                                <div class="pedido-medida-row">
                                    <input class="pedido-medida-nombre" type="text" maxlength="120" placeholder="Nombre persona">
                                    <input class="pedido-medida-referencia" type="text" maxlength="120" placeholder="Curso / Área / Cargo">
                                    <input class="pedido-medida-cantidad" type="number" min="1" step="1" value="1">
                                    <textarea class="pedido-medida-texto" rows="2" maxlength="4000" placeholder="Ej: Pantalón: Cintura 76, Largo 98. Camisa: Cuello 38, Manga 60."></textarea>
                                    <button class="btn quitar-medida" type="button" data-action="quitar-medida-item">Quitar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn agregar" type="button" data-action="agregar-item-pedido">Agregar Producto</button>
            </section>

            <p class="pedido-create-total">
                Total actualizado: <strong id="pedido-edit-total-valor">$0.00</strong>
            </p>

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
    <div class="pedido-modal-content pedido-modal-content-wide" role="dialog" aria-modal="true" aria-labelledby="pedido-create-title">
        <div class="pedido-modal-header">
            <h3 id="pedido-create-title">Nuevo Pedido</h3>
            <button class="btn cerrar" type="button" data-close="pedido-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="pedido-create-form" class="pedido-create-form">
            <div class="pedido-form-grid">
                <div class="pedido-field">
                    <label for="pedido-create-cliente">Cliente</label>
                    <select id="pedido-create-cliente" name="id_cliente" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>">
                                <?= htmlspecialchars(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                <?= !empty($cliente['cedula']) ? ' - ' . htmlspecialchars((string) $cliente['cedula'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pedido-field">
                    <label for="pedido-create-estado">Estado Inicial</label>
                    <select id="pedido-create-estado" name="estado" required>
                        <option value="pendiente" selected>Pendiente</option>
                        <option value="procesando">Procesando</option>
                        <option value="listo">Listo</option>
                        <option value="entregado">Entregado</option>
                    </select>
                </div>
            </div>

            <section class="pedido-create-section">
                <div class="pedido-section-head">
                    <h4>Productos del Pedido</h4>
                    <p>Agrega uno o varios productos y registra medidas por persona cuando aplique.</p>
                </div>

                <div class="pedido-items" id="pedido-items">
                    <div class="pedido-item-row">
                        <div class="pedido-item-main">
                            <select class="pedido-item-producto" required>
                                <option value="">Selecciona producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option
                                        value="<?= (int) $producto['id'] ?>"
                                        data-precio="<?= htmlspecialchars((string) $producto['precio_base'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars((string) $producto['nombre_producto'], ENT_QUOTES, 'UTF-8') ?> - $<?= number_format((float) $producto['precio_base'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input class="pedido-item-cantidad" type="number" min="1" step="1" value="1" required>
                            <button class="btn quitar" type="button" data-action="quitar-item-pedido">Quitar</button>
                        </div>

                        <div class="pedido-item-medidas">
                            <div class="pedido-item-medidas-header">
                                <span>Medidas por persona (opcional)</span>
                                <button class="btn agregar-medida" type="button" data-action="agregar-medida-item">Agregar Persona</button>
                            </div>

                            <div class="pedido-medidas-list">
                                <div class="pedido-medida-row">
                                    <input class="pedido-medida-nombre" type="text" maxlength="120" placeholder="Nombre persona">
                                    <input class="pedido-medida-referencia" type="text" maxlength="120" placeholder="Curso / Área / Cargo">
                                    <input class="pedido-medida-cantidad" type="number" min="1" step="1" value="1">
                                    <textarea class="pedido-medida-texto" rows="2" maxlength="4000" placeholder="Ej: Pantalón: Cintura 76, Largo 98. Camisa: Cuello 38, Manga 60."></textarea>
                                    <button class="btn quitar-medida" type="button" data-action="quitar-medida-item">Quitar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn agregar" type="button" data-action="agregar-item-pedido">Agregar Producto</button>
            </section>

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
