<?php
$clientes = $clientes ?? [];

$totalClientes = count($clientes);
$clientesConCedula = 0;
$clientesConTelefono = 0;
$clientesConEmpresa = 0;
$clientesSinTelefono = 0;
$clientesPendientes = 0;
$clientesCompletos = 0;

foreach ($clientes as $cliente) {
    $cedula = trim((string) ($cliente['cedula'] ?? ''));
    $telefono = trim((string) ($cliente['telefono'] ?? ''));
    $empresa = trim((string) ($cliente['empresa'] ?? ''));

    if ($cedula !== '') {
        $clientesConCedula++;
    }

    if ($telefono !== '') {
        $clientesConTelefono++;
    } else {
        $clientesSinTelefono++;
    }

    if ($empresa !== '') {
        $clientesConEmpresa++;
    }

    if ($cedula === '' || $telefono === '') {
        $clientesPendientes++;
    }

    if ($cedula !== '' && $telefono !== '' && trim((string) ($cliente['direccion'] ?? '')) !== '') {
        $clientesCompletos++;
    }
}

$clientesSinCedula = max($totalClientes - $clientesConCedula, 0);
$clientesParticulares = max($totalClientes - $clientesConEmpresa, 0);
$coberturaCedula = $totalClientes > 0 ? (int) round(($clientesConCedula / $totalClientes) * 100) : 0;
$coberturaTelefono = $totalClientes > 0 ? (int) round(($clientesConTelefono / $totalClientes) * 100) : 0;
$coberturaEmpresa = $totalClientes > 0 ? (int) round(($clientesConEmpresa / $totalClientes) * 100) : 0;
$saludBase = $totalClientes > 0 ? (int) round((($clientesConCedula + $clientesConTelefono + $clientesCompletos) / ($totalClientes * 3)) * 100) : 0;
$ultimoCliente = $clientes[0] ?? null;
$ultimoClienteNombre = 'Sin registros';
$ultimoClienteDetalle = 'Aún no hay clientes cargados en el sistema.';

if ($ultimoCliente) {
    $ultimoClienteNombre = trim((string) (($ultimoCliente['nombre'] ?? '') . ' ' . ($ultimoCliente['apellido'] ?? '')));
    $ultimoClienteNombre = $ultimoClienteNombre !== '' ? $ultimoClienteNombre : 'Cliente sin nombre';

    $ultimoClienteEmpresa = trim((string) ($ultimoCliente['empresa'] ?? ''));
    $ultimoClienteUsuario = trim((string) ($ultimoCliente['usuario_registro'] ?? ''));

    if ($ultimoClienteEmpresa !== '') {
        $ultimoClienteDetalle = 'Asociado a ' . $ultimoClienteEmpresa;
    } elseif ($ultimoClienteUsuario !== '') {
        $ultimoClienteDetalle = 'Registrado por ' . $ultimoClienteUsuario;
    } else {
        $ultimoClienteDetalle = 'Registro sin información complementaria.';
    }
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/clientes.css">

<div class="clientes-container">
    <section class="clientes-hero clientes-panel">
        <div class="clientes-hero-copy">
            <span class="clientes-eyebrow">Relación Comercial</span>
            <h2>Gestión de Clientes</h2>
            <p>
                Organiza la base de clientes, identifica fichas incompletas y actúa rápido desde una vista más clara.
            </p>
        </div>

        <div class="clientes-hero-actions">
            <div class="clientes-hero-meta">
                <span>Último registro visible</span>
                <strong><?= htmlspecialchars($ultimoClienteNombre, ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($ultimoClienteDetalle, ENT_QUOTES, 'UTF-8') ?></small>
            </div>

            <button class="btn nuevo" type="button" data-action="nuevo-cliente">Nuevo Cliente</button>
        </div>
    </section>

    <section class="clientes-kpi-grid">
        <article class="clientes-kpi-card clientes-panel">
            <span>Clientes registrados</span>
            <strong><?= (int) $totalClientes ?></strong>
            <small>Base total disponible en este módulo.</small>
        </article>

        <article class="clientes-kpi-card clientes-panel">
            <span>Con teléfono</span>
            <strong><?= (int) $clientesConTelefono ?></strong>
            <small><?= (int) $clientesSinTelefono ?> sin número de contacto.</small>
        </article>

        <article class="clientes-kpi-card clientes-panel">
            <span>Con empresa</span>
            <strong><?= (int) $clientesConEmpresa ?></strong>
            <small><?= (int) max($totalClientes - $clientesConEmpresa, 0) ?> clientes particulares.</small>
        </article>

        <article class="clientes-kpi-card clientes-panel">
            <span>Cédula pendiente</span>
            <strong><?= (int) $clientesSinCedula ?></strong>
            <small><?= (int) $clientesConCedula ?> fichas ya documentadas.</small>
        </article>
    </section>

    <section class="clientes-insights-grid">
        <article class="clientes-insight-card clientes-panel clientes-insight-card-main">
            <div class="clientes-insight-head">
                <div>
                    <span>Salud de la base</span>
                    <strong><?= (int) $saludBase ?>%</strong>
                </div>
                <small><?= (int) $clientesCompletos ?> fichas completas de <?= (int) $totalClientes ?></small>
            </div>

            <div class="clientes-progress-list">
                <div class="clientes-progress-item">
                    <div class="clientes-progress-copy">
                        <strong>Documentación</strong>
                        <small><?= (int) $clientesConCedula ?> con cédula registrada</small>
                    </div>
                    <div class="clientes-progress-bar" aria-hidden="true">
                        <span style="width: <?= (int) $coberturaCedula ?>%;"></span>
                    </div>
                    <b><?= (int) $coberturaCedula ?>%</b>
                </div>

                <div class="clientes-progress-item">
                    <div class="clientes-progress-copy">
                        <strong>Contactabilidad</strong>
                        <small><?= (int) $clientesConTelefono ?> con teléfono disponible</small>
                    </div>
                    <div class="clientes-progress-bar" aria-hidden="true">
                        <span style="width: <?= (int) $coberturaTelefono ?>%;"></span>
                    </div>
                    <b><?= (int) $coberturaTelefono ?>%</b>
                </div>

                <div class="clientes-progress-item">
                    <div class="clientes-progress-copy">
                        <strong>Segmento empresa</strong>
                        <small><?= (int) $clientesConEmpresa ?> fichas empresariales</small>
                    </div>
                    <div class="clientes-progress-bar" aria-hidden="true">
                        <span style="width: <?= (int) $coberturaEmpresa ?>%;"></span>
                    </div>
                    <b><?= (int) $coberturaEmpresa ?>%</b>
                </div>
            </div>
        </article>

        <article class="clientes-insight-card clientes-panel">
            <span>Perfiles listos</span>
            <strong><?= (int) $clientesCompletos ?></strong>
            <small>Nombre, cédula, teléfono y dirección disponibles para una atención más ágil.</small>
        </article>

        <article class="clientes-insight-card clientes-panel">
            <span>Clientes particulares</span>
            <strong><?= (int) $clientesParticulares ?></strong>
            <small><?= (int) $clientesConEmpresa ?> pertenecen a empresa o institución.</small>
        </article>
    </section>

    <section class="clientes-panel clientes-list-panel">
        <div class="clientes-list-head">
            <div>
                <h3>Base de Clientes</h3>
                <p>Consulta la información principal, filtra por texto libre y edita el registro sin salir del módulo.</p>
            </div>

            <div class="clientes-toolbar">
                <label class="clientes-search" for="clientes-search">
                    <span>Buscar cliente</span>
                    <input
                        id="clientes-search"
                        type="search"
                        placeholder="Nombre, cédula, teléfono, empresa o dirección"
                        autocomplete="off"
                    >
                </label>
                <p class="clientes-filter-summary" id="clientes-filter-summary">
                    <?= $totalClientes > 0 ? 'Mostrando ' . (int) $totalClientes . ' clientes' : 'Sin clientes registrados' ?>
                </p>
            </div>
        </div>

        <div class="clientes-state-strip" aria-label="Cobertura de fichas">
            <span class="clientes-state-pill is-document">Con cédula <strong><?= (int) $clientesConCedula ?></strong></span>
            <span class="clientes-state-pill is-phone">Con teléfono <strong><?= (int) $clientesConTelefono ?></strong></span>
            <span class="clientes-state-pill is-company">Con empresa <strong><?= (int) $clientesConEmpresa ?></strong></span>
            <span class="clientes-state-pill is-pending">Pendientes de completar <strong><?= (int) $clientesPendientes ?></strong></span>
        </div>

        <div class="clientes-filter-empty" id="clientes-filter-empty" hidden>
            No se encontraron clientes con ese criterio de búsqueda.
        </div>

        <div class="clientes-table-shell">
            <table class="clientes-table" data-page-size="100000">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Cédula</th>
                        <th>Contacto</th>
                        <th>Empresa</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="clientes-table-body">
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <?php
                        $idCliente = (int) ($cliente['id'] ?? 0);
                        $nombreCompleto = trim((string) (($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')));
                        $nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : 'Cliente sin nombre';
                        $cedula = trim((string) ($cliente['cedula'] ?? ''));
                        $telefono = trim((string) ($cliente['telefono'] ?? ''));
                        $direccion = trim((string) ($cliente['direccion'] ?? ''));
                        $empresa = trim((string) ($cliente['empresa'] ?? ''));
                        $usuarioRegistro = trim((string) ($cliente['usuario_registro'] ?? ''));
                        $fichaCompleta = $cedula !== '' && $telefono !== '' && $direccion !== '';
                        $tipoRelacion = $empresa !== '' ? 'Empresarial' : 'Particular';
                        $searchText = strtolower($nombreCompleto . ' ' . $cedula . ' ' . $telefono . ' ' . $direccion . ' ' . $empresa . ' ' . $usuarioRegistro . ' ' . $idCliente);
                        ?>
                        <tr data-clientes-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="clientes-person-cell">
                                <strong><?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="clientes-person-tags">
                                    <span class="clientes-inline-badge<?= $fichaCompleta ? ' is-complete' : ' is-pending' ?>">
                                        <?= $fichaCompleta ? 'Ficha completa' : 'Por completar' ?>
                                    </span>
                                    <span class="clientes-inline-badge is-neutral">
                                        <?= htmlspecialchars($tipoRelacion, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <small>
                                    #<?= $idCliente ?>
                                    <?= $direccion !== '' ? ' · ' . htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8') : ' · Sin dirección registrada' ?>
                                </small>
                            </td>

                            <td>
                                <div class="clientes-data-stack">
                                    <strong><?= htmlspecialchars($cedula !== '' ? $cedula : 'Sin cédula', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= $cedula !== '' ? 'Documento registrado' : 'Pendiente de completar' ?></small>
                                </div>
                            </td>

                            <td>
                                <div class="clientes-data-stack">
                                    <strong><?= htmlspecialchars($telefono !== '' ? $telefono : 'Sin teléfono', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($direccion !== '' ? $direccion : 'Sin dirección registrada', ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <div class="clientes-contact-hints">
                                    <?php if ($telefono !== ''): ?>
                                        <span class="clientes-inline-badge is-contact">Contacto directo</span>
                                    <?php else: ?>
                                        <span class="clientes-inline-badge is-pending">Sin canal directo</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <div class="clientes-data-stack">
                                    <span class="clientes-company-badge<?= $empresa === '' ? ' is-empty' : '' ?>">
                                        <?= htmlspecialchars($empresa !== '' ? $empresa : 'Sin empresa', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <small><?= $empresa !== '' ? 'Cuenta corporativa o institucional' : 'Cliente individual' ?></small>
                                </div>
                            </td>

                            <td>
                                <div class="clientes-data-stack">
                                    <strong><?= htmlspecialchars($usuarioRegistro !== '' ? $usuarioRegistro : 'Sistema', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= $empresa !== '' ? 'Registro empresarial' : 'Registro particular' ?></small>
                                </div>
                            </td>

                            <td>
                                <div class="clientes-actions">
                                    <button
                                        class="btn editar"
                                        type="button"
                                        data-action="editar-cliente"
                                        data-id="<?= $idCliente ?>"
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
                                        data-id="<?= $idCliente ?>"
                                        data-nombre="<?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="clientes-empty">No hay clientes registrados. Crea el primero para comenzar.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="cliente-modal" id="cliente-edit-modal" hidden>
    <div class="cliente-modal-backdrop" data-close="cliente-edit-modal"></div>
    <div class="cliente-modal-content" role="dialog" aria-modal="true" aria-labelledby="cliente-edit-title">
        <div class="cliente-modal-header">
            <div>
                <h3 id="cliente-edit-title">Editar Cliente</h3>
                <p>Ajusta la ficha del cliente sin perder el contexto de la tabla.</p>
            </div>
            <button class="btn cerrar" type="button" data-close="cliente-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="cliente-edit-form" class="cliente-create-form">
            <input type="hidden" id="cliente-edit-id" name="id">

            <div class="cliente-form-note">
                Actualiza los datos clave para mantener una ficha útil para ventas, pedidos y seguimiento.
            </div>

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
            <div>
                <h3 id="cliente-create-title">Nuevo Cliente</h3>
                <p>Registra rápidamente una nueva ficha comercial.</p>
            </div>
            <button class="btn cerrar" type="button" data-close="cliente-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="cliente-create-form" class="cliente-create-form">
            <div class="cliente-form-note">
                Completa al menos nombre, apellido y un dato de contacto para que el cliente quede operativo desde el primer registro.
            </div>

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
