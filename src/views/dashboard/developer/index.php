<?php
$info = $info ?? [];
$resumen = $resumen ?? [];
$integridad = $integridad ?? [];
$usuariosCredenciales = $usuariosCredenciales ?? [];
$tables = $tables ?? [];
$tableManager = $tableManager ?? [];
$preferences = $preferences ?? [];
$rolActual = $rolActual ?? ($info['rolActual'] ?? '-');
$ventasShowActionsColumn = !array_key_exists('ventasShowActionsColumn', $preferences)
    ? true
    : (bool) $preferences['ventasShowActionsColumn'];
$historialShowActionsColumn = !array_key_exists('historialShowActionsColumn', $preferences)
    ? true
    : (bool) $preferences['historialShowActionsColumn'];

$dbStatus = strtolower((string) ($info['dbStatus'] ?? 'error'));
$dbStatusText = $dbStatus === 'ok' ? 'Conectada' : 'Error';
?>
<link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/developer.css">

<div class="developer-page" data-developer-panel="1">
    <section class="developer-hero">
        <div>
            <h1>Centro de Control del Desarrollador</h1>
            <p>
                Módulo técnico para diagnóstico y mantenimiento operativo.
                Acceso restringido exclusivamente al rol desarrollador.
            </p>
        </div>

        <div class="developer-meta">
            <div>
                <span>Aplicación</span>
                <strong id="developer-info-app"><?= htmlspecialchars((string) ($info['appName'] ?? 'RECALDE')) ?></strong>
            </div>
            <div>
                <span>Versión</span>
                <strong id="developer-info-version"><?= htmlspecialchars((string) ($info['appVersion'] ?? '1.0.0')) ?></strong>
            </div>
            <div>
                <span>PHP</span>
                <strong id="developer-info-php"><?= htmlspecialchars((string) ($info['phpVersion'] ?? PHP_VERSION)) ?></strong>
            </div>
            <div>
                <span>Rol</span>
                <strong id="developer-info-role"><?= htmlspecialchars((string) $rolActual) ?></strong>
            </div>
            <div>
                <span>DB</span>
                <strong id="developer-db-status" class="<?= $dbStatus === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($dbStatusText) ?></strong>
            </div>
            <div>
                <span>Actualizado</span>
                <strong id="developer-info-generated"><?= htmlspecialchars((string) ($info['generatedAt'] ?? date('Y-m-d H:i:s'))) ?></strong>
            </div>
        </div>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Resumen Global</h2>
            <button class="btn dev-btn secondary" type="button" data-action="developer-refresh">Actualizar diagnóstico</button>
        </div>

        <div class="developer-kpi-grid">
            <article class="developer-kpi">
                <small>Usuarios</small>
                <strong id="developer-count-usuarios"><?= (int) ($resumen['usuarios'] ?? 0) ?></strong>
            </article>
            <article class="developer-kpi">
                <small>Clientes</small>
                <strong id="developer-count-clientes"><?= (int) ($resumen['clientes'] ?? 0) ?></strong>
            </article>
            <article class="developer-kpi">
                <small>Productos</small>
                <strong id="developer-count-productos"><?= (int) ($resumen['productos'] ?? 0) ?></strong>
            </article>
            <article class="developer-kpi">
                <small>Pedidos</small>
                <strong id="developer-count-pedidos"><?= (int) ($resumen['pedidos'] ?? 0) ?></strong>
            </article>
            <article class="developer-kpi">
                <small>Ventas</small>
                <strong id="developer-count-ventas"><?= (int) ($resumen['ventas'] ?? 0) ?></strong>
            </article>
            <article class="developer-kpi">
                <small>Historial Ventas</small>
                <strong id="developer-count-historial"><?= (int) ($resumen['historial'] ?? 0) ?></strong>
            </article>
        </div>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Integridad de Datos</h2>
        </div>

        <div class="developer-check-grid">
            <article class="developer-check">
                <small>Pedidos sin detalle</small>
                <strong id="developer-check-pedidos-sin-detalle"><?= (int) ($integridad['pedidosSinDetalle'] ?? 0) ?></strong>
            </article>
            <article class="developer-check">
                <small>Pedidos con total descuadrado</small>
                <strong id="developer-check-pedidos-descuadrados"><?= (int) ($integridad['pedidosTotalesDescuadrados'] ?? 0) ?></strong>
            </article>
            <article class="developer-check">
                <small>Ventas sin historial</small>
                <strong id="developer-check-ventas-sin-historial"><?= (int) ($integridad['ventasSinHistorial'] ?? 0) ?></strong>
            </article>
        </div>

        <div class="developer-actions">
            <button class="btn dev-btn primary" type="button" data-action="developer-recalcular-pedidos">
                Recalcular Totales de Pedidos
            </button>
        </div>

        <p class="developer-feedback" id="developer-feedback" hidden></p>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Acceso Rápido</h2>
            <p>Control centralizado para navegar a los módulos más críticos.</p>
        </div>

        <div class="developer-quick-grid">
            <button class="btn dev-btn ghost" type="button" data-nav-page="home">Home</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="perfil">Perfil</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="clientes">Clientes</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="inventario">Inventario</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="pedidos">Pedidos</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="ventas">Ventas</button>
            <button class="btn dev-btn ghost" type="button" data-nav-page="historial">Historial</button>
        </div>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Preferencias de UI</h2>
            <p>Ajustes globales: lo que cambies aquí se aplica a todos los roles del dashboard.</p>
        </div>

        <div class="developer-check-grid">
            <article class="developer-check developer-ui-card">
                <small>Ventas · Lista de gestión</small>
                <strong id="developer-ui-ventas-actions-state"><?= $ventasShowActionsColumn ? 'Visible' : 'Oculta' ?></strong>
                <p class="developer-ui-copy" id="developer-ui-ventas-actions-copy">
                    <?= $ventasShowActionsColumn
                        ? 'La columna de acciones se muestra en el listado principal de ventas para todos los roles.'
                        : 'La columna de acciones está oculta globalmente; la gestión sigue disponible al hacer clic en la fila.' ?>
                </p>
                <div class="developer-actions">
                    <button
                        class="btn dev-btn <?= $ventasShowActionsColumn ? 'danger subtle' : 'primary' ?>"
                        type="button"
                        data-action="developer-toggle-ventas-actions-column"
                        data-enabled="<?= $ventasShowActionsColumn ? '1' : '0' ?>"
                    >
                        <?= $ventasShowActionsColumn ? 'Ocultar columna' : 'Mostrar columna' ?>
                    </button>
                </div>
            </article>

            <article class="developer-check developer-ui-card">
                <small>Historial · Lista documental</small>
                <strong id="developer-ui-historial-actions-state"><?= $historialShowActionsColumn ? 'Visible' : 'Oculta' ?></strong>
                <p class="developer-ui-copy" id="developer-ui-historial-actions-copy">
                    <?= $historialShowActionsColumn
                        ? 'La columna de acciones se muestra en el listado principal del historial para todos los roles.'
                        : 'La columna de acciones está oculta globalmente; la gestión sigue disponible al hacer clic en la fila.' ?>
                </p>
                <div class="developer-actions">
                    <button
                        class="btn dev-btn <?= $historialShowActionsColumn ? 'danger subtle' : 'primary' ?>"
                        type="button"
                        data-action="developer-toggle-historial-actions-column"
                        data-enabled="<?= $historialShowActionsColumn ? '1' : '0' ?>"
                    >
                        <?= $historialShowActionsColumn ? 'Ocultar columna' : 'Mostrar columna' ?>
                    </button>
                </div>
            </article>
        </div>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Gestor de Tablas</h2>
            <p>Consulta, edita, elimina filas y vacía tablas operativas desde el rol desarrollador.</p>
        </div>

        <div class="developer-table-controls">
            <label class="developer-field-inline" for="developer-table-select">
                <span>Tabla activa</span>
                <select id="developer-table-select">
                    <?php foreach ($tables as $item): ?>
                        <?php $tableName = (string) ($item['table'] ?? ''); ?>
                        <option
                            value="<?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $tableName === (string) ($tableManager['selectedTable'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="developer-actions developer-actions-inline">
                <button class="btn dev-btn secondary" type="button" data-action="developer-refresh-table">
                    Actualizar tabla
                </button>
                <button class="btn dev-btn danger" type="button" data-action="developer-clear-table">
                    Vaciar tabla
                </button>
            </div>
        </div>

        <div class="developer-table-catalog" id="developer-table-catalog"></div>

        <div class="developer-table-meta">
            <article>
                <small>Registros</small>
                <strong id="developer-table-row-count">0</strong>
            </article>
            <article>
                <small>Columnas</small>
                <strong id="developer-table-column-count">0</strong>
            </article>
            <article>
                <small>Clave primaria</small>
                <strong id="developer-table-primary-key">-</strong>
            </article>
            <article>
                <small>Límite de vista</small>
                <strong id="developer-table-limit">25</strong>
            </article>
        </div>

        <p class="developer-table-note">
            El vaciado masivo se bloquea en tablas críticas del sistema. Los borrados respetan claves foráneas activas.
        </p>

        <div class="developer-table-wrap">
            <table class="developer-records-table" data-page-size="10">
                <thead id="developer-records-head"></thead>
                <tbody id="developer-records-body">
                    <tr>
                        <td colspan="2" style="text-align:center;">Cargando tabla seleccionada...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="developer-section">
        <div class="developer-section-head">
            <h2>Usuarios y Credenciales</h2>
            <p>Vista técnica del valor almacenado en `usuarios.contrasena`.</p>
        </div>

        <p class="developer-credentials-note">
            Si el valor es hash, no puede convertirse a texto plano.
        </p>

        <div class="developer-table-wrap">
            <table class="developer-credentials-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Cambio forzado</th>
                        <th>Contraseña almacenada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="developer-users-body">
                    <?php if (!empty($usuariosCredenciales)): ?>
                        <?php foreach ($usuariosCredenciales as $item): ?>
                            <tr>
                                <td>#<?= (int) ($item['id'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($item['usuario'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($item['correo'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($item['nombre_rol'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($item['estado'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= !empty($item['debe_cambiar_contrasena']) ? 'Sí' : 'No' ?></td>
                                <td class="mono">
                                    <?= htmlspecialchars((string) ($item['contrasena'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($item['password_is_hash'])): ?>
                                        <small class="developer-hash-label">hash</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button
                                        class="btn dev-btn secondary developer-reset-btn"
                                        type="button"
                                        data-action="developer-reset-password-user"
                                        data-user-id="<?= (int) ($item['id'] ?? 0) ?>"
                                        data-username="<?= htmlspecialchars((string) ($item['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Resetear contraseña
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No hay usuarios para mostrar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="developer-modal" id="developer-row-edit-modal" hidden>
    <div class="developer-modal-backdrop" data-close="developer-row-edit-modal"></div>
    <div class="developer-modal-content" role="dialog" aria-modal="true" aria-labelledby="developer-row-edit-title">
        <div class="developer-modal-header">
            <h3 id="developer-row-edit-title">Editar Registro</h3>
            <button class="btn dev-btn close" type="button" data-close="developer-row-edit-modal" aria-label="Cerrar">x</button>
        </div>

        <form id="developer-row-edit-form" class="developer-form">
            <div class="developer-form-grid" id="developer-row-edit-fields"></div>

            <div class="developer-primary-summary">
                <span>Clave primaria</span>
                <div id="developer-row-edit-primary"></div>
            </div>

            <p class="developer-modal-feedback" id="developer-row-edit-feedback" hidden></p>

            <div class="developer-actions developer-actions-inline">
                <button class="btn dev-btn ghost" type="button" data-close="developer-row-edit-modal">Cancelar</button>
                <button class="btn dev-btn primary" type="submit" id="developer-row-edit-submit">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script id="developer-panel-data" type="application/json"><?= json_encode([
    'info' => $info,
    'resumen' => $resumen,
    'integridad' => $integridad,
    'usuariosCredenciales' => $usuariosCredenciales,
    'tables' => $tables,
    'tableManager' => $tableManager,
    'preferences' => $preferences,
    'rolActual' => $rolActual
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
