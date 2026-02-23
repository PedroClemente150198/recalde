<?php
$inventario = $inventario ?? [];
$categorias = $categorias ?? [];
$categoriasListado = $categoriasListado ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/inventario.css">
</head>

<body>

<div class="inventario-container">

    <div class="inventario-header">
        <h1>Inventario de Productos</h1>
        <div class="inventario-header-actions">
            <button class="btn categoria" type="button" data-action="nueva-categoria">Nueva Categoría</button>
            <button class="btn nuevo" type="button" data-action="nuevo-producto">Nuevo Producto</button>
        </div>
    </div>

    <section class="categorias-section">
        <h2 class="inventario-subtitle">Categorías</h2>

        <table class="tabla-categorias">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th class="acciones-col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categoriasListado)): ?>
                    <?php foreach ($categoriasListado as $categoria): ?>
                        <tr>
                            <td><?= (int) ($categoria['id'] ?? 0) ?></td>
                            <td class="left"><?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? '')) ?></td>
                            <td>
                                <?php if (($categoria['estado'] ?? '') === "inactivo"): ?>
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
                                    data-id="<?= (int) ($categoria['id'] ?? 0) ?>"
                                    data-tipo="<?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-estado="<?= htmlspecialchars((string) ($categoria['estado'] ?? 'activo'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Editar
                                </button>
                                <button
                                    class="btn eliminar"
                                    type="button"
                                    data-action="eliminar-categoria"
                                    data-id="<?= (int) ($categoria['id'] ?? 0) ?>"
                                    data-tipo="<?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">No hay categorías registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <h2 class="inventario-subtitle">Productos</h2>
    <table class="tabla-inventario">
        <thead>
            <tr>
                <th>ID</th>
                <th>Categoría</th>
                <th>Producto</th>
                <th>Precio Base</th>
                <th>Stock</th>
                <th>Mínimo</th>
                <th>Estado</th>
                <th>Actualizado</th>
                <th class="acciones-col">Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($inventario)): ?>
                <?php foreach ($inventario as $item): ?>
                    <?php $lowStock = ((int) ($item['stock'] ?? 0) <= (int) ($item['stock_minimo'] ?? 0)); ?>

                    <tr class="<?= $lowStock ? 'low-stock' : '' ?>">
                        <td><?= (int) ($item['id'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string) ($item['tipo_categoria'] ?? 'Sin categoría')) ?></td>
                        <td class="left"><?= htmlspecialchars((string) ($item['nombre_producto'] ?? '')) ?></td>
                        <td>$<?= number_format((float) ($item['precio_base'] ?? 0), 2) ?></td>
                        <td><?= (int) ($item['stock'] ?? 0) ?></td>
                        <td><?= (int) ($item['stock_minimo'] ?? 0) ?></td>

                        <td>
                            <?php if (($item['estado_producto'] ?? '') === "inactivo"): ?>
                                <span class="badge inactivo">Inactivo</span>
                            <?php else: ?>
                                <span class="badge activo">Activo</span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars((string) ($item['fecha_actualizacion'] ?? '-')) ?></td>

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
                    <td colspan="9" style="text-align:center;">No hay productos registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<div class="inventario-modal" id="categoria-create-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="categoria-create-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="categoria-create-title">
        <div class="inventario-modal-header">
            <h3 id="categoria-create-title">Nueva Categoría</h3>
            <button class="btn cerrar" type="button" data-close="categoria-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="categoria-create-form" class="producto-form">
            <label for="categoria-create-tipo">Nombre de la Categoría</label>
            <input id="categoria-create-tipo" name="tipo_categoria" type="text" maxlength="100" required>

            <label for="categoria-create-estado">Estado</label>
            <select id="categoria-create-estado" name="estado" required>
                <option value="activo" selected>Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

            <p class="producto-feedback" id="categoria-create-feedback" hidden></p>

            <div class="producto-actions">
                <button class="btn cancelar" type="button" data-close="categoria-create-modal">Cancelar</button>
                <button class="btn guardar" type="submit" id="categoria-create-submit">Guardar Categoría</button>
            </div>
        </form>
    </div>
</div>

<div class="inventario-modal" id="categoria-edit-modal" hidden>
    <div class="inventario-modal-backdrop" data-close="categoria-edit-modal"></div>
    <div class="inventario-modal-content" role="dialog" aria-modal="true" aria-labelledby="categoria-edit-title">
        <div class="inventario-modal-header">
            <h3 id="categoria-edit-title">Editar Categoría</h3>
            <button class="btn cerrar" type="button" data-close="categoria-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="categoria-edit-form" class="producto-form">
            <input type="hidden" id="categoria-edit-id" name="id">

            <label for="categoria-edit-tipo">Nombre de la Categoría</label>
            <input id="categoria-edit-tipo" name="tipo_categoria" type="text" maxlength="100" required>

            <label for="categoria-edit-estado">Estado</label>
            <select id="categoria-edit-estado" name="estado" required>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

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
            <h3 id="producto-create-title">Nuevo Producto</h3>
            <button class="btn cerrar" type="button" data-close="producto-create-modal" aria-label="Cerrar creación">x</button>
        </div>

        <form id="producto-create-form" class="producto-form">
            <label for="producto-create-categoria">Categoría</label>
            <select id="producto-create-categoria" name="id_categoria">
                <option value="">Sin categoría</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) ($categoria['id'] ?? 0) ?>">
                        <?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="producto-create-nombre">Nombre del Producto</label>
            <input id="producto-create-nombre" name="nombre_producto" type="text" maxlength="100" required>

            <label for="producto-create-descripcion">Descripción</label>
            <textarea id="producto-create-descripcion" name="descripcion" rows="3"></textarea>

            <label for="producto-create-precio">Precio Base</label>
            <input id="producto-create-precio" name="precio_base" type="number" step="0.01" min="0.01" required>

            <label for="producto-create-estado">Estado</label>
            <select id="producto-create-estado" name="estado" required>
                <option value="activo" selected>Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

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
            <h3 id="producto-edit-title">Editar Producto</h3>
            <button class="btn cerrar" type="button" data-close="producto-edit-modal" aria-label="Cerrar edición">x</button>
        </div>

        <form id="producto-edit-form" class="producto-form">
            <input type="hidden" id="producto-edit-id" name="id">

            <label for="producto-edit-categoria">Categoría</label>
            <select id="producto-edit-categoria" name="id_categoria">
                <option value="">Sin categoría</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) ($categoria['id'] ?? 0) ?>">
                        <?= htmlspecialchars((string) ($categoria['tipo_categoria'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="producto-edit-nombre">Nombre del Producto</label>
            <input id="producto-edit-nombre" name="nombre_producto" type="text" maxlength="100" required>

            <label for="producto-edit-descripcion">Descripción</label>
            <textarea id="producto-edit-descripcion" name="descripcion" rows="3"></textarea>

            <label for="producto-edit-precio">Precio Base</label>
            <input id="producto-edit-precio" name="precio_base" type="number" step="0.01" min="0.01" required>

            <label for="producto-edit-estado">Estado</label>
            <select id="producto-edit-estado" name="estado" required>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

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
