<?php
$appName = defined('APP_NAME') ? APP_NAME : 'RECALDE';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$baseUrl = (defined('BASE_URL') ? BASE_URL : '');
$appDebug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN) ? 'Activo' : 'Inactivo';
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'RECALDE';
$dbUser = getenv('DB_USER') ?: 'root';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/public/css/configuration.css">

<div class="configuration-page">
    <section class="configuration-hero">
        <h1>Configuración del Sistema</h1>
        <p>Panel de referencia para validar parámetros operativos y entorno de ejecución.</p>
    </section>

    <section class="configuration-grid">
        <article class="configuration-card">
            <h2>Aplicación</h2>
            <ul>
                <li><span>Nombre</span><strong><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>Versión</span><strong><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>Debug</span><strong><?= htmlspecialchars($appDebug, ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>Ruta base</span><strong><?= htmlspecialchars($baseUrl !== '' ? $baseUrl : '/', ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>PHP</span><strong><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></strong></li>
            </ul>
        </article>

        <article class="configuration-card">
            <h2>Base de Datos</h2>
            <ul>
                <li><span>Host</span><strong><?= htmlspecialchars($dbHost, ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>Nombre</span><strong><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li><span>Usuario</span><strong><?= htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') ?></strong></li>
            </ul>
            <p class="configuration-note">
                Estos datos se leen desde variables de entorno (`.env`).
            </p>
        </article>

        <article class="configuration-card">
            <h2>Checklist</h2>
            <ul>
                <li><span>Esquema SQL</span><strong>storage/schema.sql</strong></li>
                <li><span>Datos base</span><strong>storage/seed.sql</strong></li>
                <li><span>Variables</span><strong>.env</strong></li>
            </ul>
            <p class="configuration-note">
                Si alguna pantalla no carga, verifica primero conexión DB y sesión activa.
            </p>
        </article>
    </section>
</div>
