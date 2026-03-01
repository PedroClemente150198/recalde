<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8') ?>/public/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>RECALDE</title>
</head>
<body>
    <?php
      $rolSesion = strtolower(trim((string) ($_SESSION['usuario']['nombre_rol'] ?? '')));
      $isDeveloperRole = in_array($rolSesion, ['desarrollador', 'developer'], true);
      $baseUrlEsc = htmlspecialchars((defined('BASE_URL') ? BASE_URL : ''), ENT_QUOTES, 'UTF-8');
    ?>
    <header>
        <div class="mobile-topbar">
          <button
            class="mobile-nav-toggle"
            id="mobile-nav-toggle"
            type="button"
            aria-label="Abrir navegación"
            aria-controls="dashboard-sidebar"
            aria-expanded="false"
          >
            <i class='bx bx-menu'></i>
            <span>Menú</span>
          </button>

          <div class="mobile-topbar-copy">
            <strong>RECALDE</strong>
            <small id="mobile-current-page">Sistema</small>
          </div>
        </div>

        <button
          class="sidebar-backdrop"
          id="sidebar-backdrop"
          type="button"
          aria-label="Cerrar navegación"
          hidden
        ></button>

        <!-- Sidebar for navigation -->
        <div class="sidebar" id="dashboard-sidebar">
          <div class="logo-details">
            <!-- Icon and logo name -->
            <!--<i class='bx bxl-c-plus-plus icon'></i>-->
            <div class="logo_name">RECALDE</div>
            <i class='bx bx-menu' id="btn"></i> <!-- Menu button to toggle sidebar -->
          </div>
          <ul class="nav-list">
            <!-- Search bar -->
            <li>
              <i class='bx bx-search'></i>
              <input type="text" placeholder="Search...">
              <span class="tooltip">Buscar por...</span>
            </li>
            <!-- List of navigation items -->
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=home" data-page="home">
                <i class='bx bx-grid-alt'></i>
                <span class="links_name">Sistema</span>
              </a>
              <span class="tooltip">Sistema</span>
            </li>
            <!-- Additional navigation items -->
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=perfil" data-page="perfil">
                <i class='bx bx-user'></i>
                <span class="links_name">Perfil</span>
              </a>
              <span class="tooltip">Perfil</span>
            </li>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=clientes" data-page="clientes">
                <i class='bx bx-user-plus'></i>
                <span class="links_name">Clientes</span>
              </a>
              <span class="tooltip">Clientes</span>
            </li>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=pedidos" data-page="pedidos">
                <i class='bx bx-chat'></i>
                <span class="links_name">N° Pedidos</span>
              </a>
              <span class="tooltip">N° Pedidos</span>
            </li>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=historial" data-page="historial">
                <i class='bx bx-pie-chart-alt-2'></i>
                <span class="links_name">Historial</span>
              </a>
              <span class="tooltip">Historial</span>
            </li>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=inventario" data-page="inventario">
                <i class='bx bx-folder'></i>
                <span class="links_name">Inventario</span>
              </a>
              <span class="tooltip">Inventario</span>
            </li>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=ventas" data-page="ventas">
                <i class='bx bx-cart-alt'></i>
                <span class="links_name">Ventas</span>
              </a>
              <span class="tooltip">Ventas</span>
            </li>
            <!--
            <li>
              <a href="#">
                <i class='bx bx-heart'></i>
                <span class="links_name">Saved</span>
              </a>
              <span class="tooltip">Saved</span>
            </li>
            -->
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=configuracion" data-page="configuracion">
                <i class='bx bx-cog'></i>
                <span class="links_name">Configuraciones</span>
              </a>
              <span class="tooltip">Configuraciones</span>
            </li>
            <?php if ($isDeveloperRole): ?>
            <li>
              <a href="<?= $baseUrlEsc ?>/?route=developer" data-page="developer">
                <i class='bx bx-code-alt'></i>
                <span class="links_name">Developer</span>
              </a>
              <span class="tooltip">Developer</span>
            </li>
            <?php endif; ?>
            <!-- Profile section -->
            <li class="profile">
              <div class="profile-details">
                <!--<img src="profile.jpg" alt="profileImg">-->
                <div class="name_job">
                  <div class="name"><?= htmlspecialchars($_SESSION['usuario']['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="job"><?= htmlspecialchars($_SESSION['usuario']['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>
              <!--Botón para cerrar Sesión -->
              <button
                id="log_out"
                class="sidebar-logout-btn"
                type="button"
                title="Cerrar sesión"
                aria-label="Cerrar sesión"
              >
                <i class='bx bx-log-out' aria-hidden="true"></i>
                <span>Cerrar sesión</span>
              </button>
            </li>
          </ul>
        </div>
        <!-- Main content area -->
      </header>
      <main>
        <section class="home-section">
          <div class="text">SISTEMA DE RECALDE</div>
          <div id="content"></div>  <!-- ← AQUI -->
        </section>
      </main>
      
