<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href=" <?php BASE_PATH; ?>/public/css/dashboard.css">
    <title>RECALDE</title>
</head>
<body>
    <header>

        <!-- Sidebar for navigation -->
        <div class="sidebar">
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
              <a href="/home" data-page="home">
                <i class='bx bx-grid-alt'></i>
                <span class="links_name">Sistema</span>
              </a>
              <span class="tooltip">Sistema</span>
            </li>
            <!-- Additional navigation items -->
            <li>
              <a href="/perfil" data-page="perfil">
                <i class='bx bx-user'></i>
                <span class="links_name">Perfil</span>
              </a>
              <span class="tooltip">Perfil</span>
            </li>
            <li>
              <a href="/pedidos" data-page="pedidos">
                <i class='bx bx-chat'></i>
                <span class="links_name">N° Pedidos</span>
              </a>
              <span class="tooltip">N° Pedidos</span>
            </li>
            <li>
              <a href="/historial" data-page="historial">
                <i class='bx bx-pie-chart-alt-2'></i>
                <span class="links_name">Historial</span>
              </a>
              <span class="tooltip">Historial</span>
            </li>
            <li>
              <a href="/inventario" data-page="inventario">
                <i class='bx bx-folder'></i>
                <span class="links_name">Inventario</span>
              </a>
              <span class="tooltip">Inventario</span>
            </li>
            <li>
              <a href="/ventas" data-page="ventas">
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
              <a href="/configuracion" data-page="configuracion">
                <i class='bx bx-cog'></i>
                <span class="links_name">Configuraciones</span>
              </a>
              <span class="tooltip">Configuraciones</span>
            </li>
            <!-- Profile section -->
            <li class="profile">
              <div class="profile-details">
                <!--<img src="profile.jpg" alt="profileImg">-->
                <div class="name_job">
                  <div class="name"> <?php echo $_SESSION['usuario']['usuario']; ?> </div>
                  <div class="job"> <?php echo $_SESSION['usuario']['correo']; ?> </div>
                </div>
              </div>
              <i class='bx bx-log-out' id="log_out"></i> <!-- Logout icon -->
            </li>
          </ul>
        </div>
        <!-- Main content area -->
      </header>
      <main>
        <section class="home-section">
          <div class="text">Sistema</div>
          <div id="content"></div>  <!-- ← AQUI -->
        </section>
      </main>
      