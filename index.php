<?php
// Variables para el encabezado
$titulo = "Página principal";
$encabezado = "Este es el index";
$subtitulo = "En esta página puedes ver el contenido del index";

// Incluir la cabecera
require('./includes/header.php');
?>


<main class="container mt-4">
  <section class="dashboard-grid">
        <div class="card">
          <h2>👤 Clientes</h2>
          <p>Agrega o edita clientes disponibles en el sistema.</p>
          <a href="./modulos/clientes/dashboard_clientes.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>🌿 Registro Productos</h2>
          <p>Agrega o edita productos  en el sistema.</p>
          <a href="./modulos/Productos/dashboard_registroProducto.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>📊 Ventas</h2>
          <p>Manten y registra ventas en el sistema.</p>
          <a href="./modulos/ventas/dashboard_clientesVentas.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>📦 Provedores</h2>
          <p>Ten un segimiento de los proveedores disponibles en el sistema.</p>
          <a href="./modulos/proveedores/dashboard_proveedores.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>📦 Base de datos 📦 </h2>
          <p>Provar la concexión a la base de datos.</p>
          <a href="./includes/config.php">Ver detalles</a>
        </div>
  </section>
</main>

<?php require('./includes/footer.php'); ?>

