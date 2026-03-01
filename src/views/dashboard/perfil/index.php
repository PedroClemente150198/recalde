<?php
$usuario = $usuario ?? [];
$isAdmin = (bool) ($isAdmin ?? false);
$canDeactivateSelf = (bool) ($canDeactivateSelf ?? true);
$usuarios = $usuarios ?? [];
$roles = $roles ?? [];

$idUsuarioActual = (int) ($usuario['user_id'] ?? $usuario['id'] ?? 0);
$estadoActual = strtolower((string) ($usuario['estado'] ?? 'activo'));
?>
<link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/perfil.css">

<div class="perfil-page">
    <section class="perfil-card">
        <div class="perfil-head">
            <h1>Mi Perfil</h1>
            <p>Administra tu cuenta y los datos de acceso del sistema.</p>
        </div>

        <div class="perfil-info-grid">
            <div>
                <span>Usuario</span>
                <strong><?= htmlspecialchars((string) ($usuario['usuario'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>Correo</span>
                <strong><?= htmlspecialchars((string) ($usuario['correo'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>Rol</span>
                <strong><?= htmlspecialchars((string) ($usuario['nombre_rol'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>Estado</span>
                <strong><?= htmlspecialchars(ucfirst($estadoActual)) ?></strong>
            </div>
            <div>
                <span>Fecha de registro</span>
                <strong><?= htmlspecialchars((string) ($usuario['fecha_registro'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>ID Usuario</span>
                <strong>#<?= (int) $idUsuarioActual ?></strong>
            </div>
        </div>
    </section>

    <section class="perfil-card">
        <h2>Actualizar Mi Perfil</h2>

        <form id="perfil-update-form" class="perfil-form">
            <input type="hidden" name="id" value="<?= (int) $idUsuarioActual ?>">

            <div class="perfil-form-grid">
                <div class="perfil-field">
                    <label for="perfil-update-usuario">Usuario</label>
                    <input
                        id="perfil-update-usuario"
                        name="usuario"
                        type="text"
                        maxlength="30"
                        value="<?= htmlspecialchars((string) ($usuario['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="perfil-field">
                    <label for="perfil-update-correo">Correo</label>
                    <input
                        id="perfil-update-correo"
                        name="correo"
                        type="email"
                        maxlength="100"
                        value="<?= htmlspecialchars((string) ($usuario['correo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>
            </div>

            <div class="perfil-field">
                <label for="perfil-update-contrasena">Nueva contraseña (opcional)</label>
                <input
                    id="perfil-update-contrasena"
                    name="contrasena"
                    type="text"
                    maxlength="255"
                    placeholder="Déjalo vacío para mantener la actual"
                >
            </div>

            <p class="perfil-feedback" id="perfil-update-feedback" hidden></p>

            <div class="perfil-actions">
                <button class="btn primary" type="submit" id="perfil-update-submit">Guardar Cambios</button>
            </div>
        </form>
    </section>

    <section class="perfil-card perfil-danger-zone">
        <h2>Zona de Riesgo</h2>
        <?php if ($canDeactivateSelf): ?>
            <p>Si desactivas tu perfil, se cerrará tu sesión y no podrás volver a entrar hasta que un admin lo reactive.</p>
        <?php else: ?>
            <p>No puedes desactivar tu perfil porque eres el último administrador activo del sistema.</p>
        <?php endif; ?>

        <div class="perfil-actions">
            <button
                class="btn danger"
                type="button"
                data-action="eliminar-mi-perfil"
                data-id="<?= (int) $idUsuarioActual ?>"
                <?= $canDeactivateSelf ? '' : 'disabled' ?>
            >
                Desactivar Mi Perfil
            </button>
        </div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="perfil-card">
            <div class="perfil-admin-head">
                <h2>Gestión de Usuarios</h2>
                <button class="btn primary" type="button" data-action="nuevo-usuario-perfil">Nuevo Usuario</button>
            </div>

            <div class="perfil-table-wrap">
                <table class="perfil-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $item): ?>
                                <?php
                                $userId = (int) ($item['user_id'] ?? $item['id'] ?? 0);
                                $isSelf = $userId === $idUsuarioActual;
                                $estado = strtolower((string) ($item['estado'] ?? 'activo'));
                                ?>
                                <tr>
                                    <td data-label="ID">#<?= $userId ?></td>
                                    <td data-label="Usuario">
                                        <?= htmlspecialchars((string) ($item['usuario'] ?? '-')) ?>
                                        <?php if ($isSelf): ?>
                                            <small class="perfil-tag">Tú</small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Correo"><?= htmlspecialchars((string) ($item['correo'] ?? '-')) ?></td>
                                    <td data-label="Rol"><?= htmlspecialchars((string) ($item['nombre_rol'] ?? '-')) ?></td>
                                    <td data-label="Estado">
                                        <span class="perfil-badge <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(ucfirst($estado)) ?>
                                        </span>
                                    </td>
                                    <td data-label="Registro"><?= htmlspecialchars((string) ($item['fecha_registro'] ?? '-')) ?></td>
                                    <td data-label="Acciones">
                                        <button
                                            class="btn ghost"
                                            type="button"
                                            data-action="editar-usuario-perfil"
                                            data-id="<?= $userId ?>"
                                            data-usuario="<?= htmlspecialchars((string) ($item['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-correo="<?= htmlspecialchars((string) ($item['correo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-id-rol="<?= (int) ($item['id_rol'] ?? 0) ?>"
                                            data-estado="<?= htmlspecialchars((string) $estado, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            Editar
                                        </button>

                                        <?php if (!$isSelf): ?>
                                            <button
                                                class="btn danger subtle"
                                                type="button"
                                                data-action="eliminar-usuario-perfil"
                                                data-id="<?= $userId ?>"
                                                data-usuario="<?= htmlspecialchars((string) ($item['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Desactivar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No hay usuarios registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
    <div class="perfil-modal" id="perfil-create-modal" hidden>
        <div class="perfil-modal-backdrop" data-close="perfil-create-modal"></div>
        <div class="perfil-modal-content" role="dialog" aria-modal="true" aria-labelledby="perfil-create-title">
            <div class="perfil-modal-header">
                <h3 id="perfil-create-title">Crear Nuevo Usuario</h3>
                <button class="btn close" type="button" data-close="perfil-create-modal" aria-label="Cerrar">x</button>
            </div>

            <form id="perfil-create-form" class="perfil-form">
                <div class="perfil-form-grid">
                    <div class="perfil-field">
                        <label for="perfil-create-usuario">Usuario</label>
                        <input id="perfil-create-usuario" name="usuario" type="text" maxlength="30" required>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-create-correo">Correo</label>
                        <input id="perfil-create-correo" name="correo" type="email" maxlength="100" required>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-create-contrasena">Contraseña</label>
                        <input id="perfil-create-contrasena" name="contrasena" type="text" maxlength="255" required>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-create-id-rol">Rol</label>
                        <select id="perfil-create-id-rol" name="id_rol" required>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) ($rol['id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($rol['rol'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-create-estado">Estado</label>
                        <select id="perfil-create-estado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>

                <p class="perfil-feedback" id="perfil-create-feedback" hidden></p>

                <div class="perfil-actions">
                    <button class="btn ghost" type="button" data-close="perfil-create-modal">Cancelar</button>
                    <button class="btn primary" type="submit" id="perfil-create-submit">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <div class="perfil-modal" id="perfil-edit-modal" hidden>
        <div class="perfil-modal-backdrop" data-close="perfil-edit-modal"></div>
        <div class="perfil-modal-content" role="dialog" aria-modal="true" aria-labelledby="perfil-edit-title">
            <div class="perfil-modal-header">
                <h3 id="perfil-edit-title">Editar Usuario</h3>
                <button class="btn close" type="button" data-close="perfil-edit-modal" aria-label="Cerrar">x</button>
            </div>

            <form id="perfil-edit-form" class="perfil-form">
                <input type="hidden" id="perfil-edit-id" name="id">

                <div class="perfil-form-grid">
                    <div class="perfil-field">
                        <label for="perfil-edit-usuario">Usuario</label>
                        <input id="perfil-edit-usuario" name="usuario" type="text" maxlength="30" required>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-edit-correo">Correo</label>
                        <input id="perfil-edit-correo" name="correo" type="email" maxlength="100" required>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-edit-contrasena">Nueva contraseña (opcional)</label>
                        <input id="perfil-edit-contrasena" name="contrasena" type="text" maxlength="255">
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-edit-id-rol">Rol</label>
                        <select id="perfil-edit-id-rol" name="id_rol" required>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) ($rol['id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($rol['rol'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="perfil-field">
                        <label for="perfil-edit-estado">Estado</label>
                        <select id="perfil-edit-estado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>

                <p class="perfil-feedback" id="perfil-edit-feedback" hidden></p>

                <div class="perfil-actions">
                    <button class="btn ghost" type="button" data-close="perfil-edit-modal">Cancelar</button>
                    <button class="btn primary" type="submit" id="perfil-edit-submit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
