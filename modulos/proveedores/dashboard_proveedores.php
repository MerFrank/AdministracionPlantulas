<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Configuración de encabezado
$titulo = "Gestión de Proveedores";
$encabezado = "Panel de Control de Proveedores";
$subtitulo = "Administra los proveedores de tu sistema";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "../../session/login.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
  <section class="dashboard-grid">

    <div class="card">
      <div class="card-icon"><i class="fas fa-user-plus"></i></div>
      <h2>🏢 Registrar Proveedores</h2>
      <p>Agrega nuevos proveedores al sistema.</p>
      <a href="registro_proveedor.php" class="btn">Acceder</a>
    </div>

    
    <div class="card">
      <div class="card-icon"><i class="fas fa-list-ul"></i></div>
      <h2>📋 Lista de Proveedores</h2>
      <p>Consulta todos los proveedores registrados.</p>
      <a href="lista_proveedores.php" class="btn">Ver lista</a>
    </div>

  </section>
</main>

<?php 
require __DIR__ . '/../../includes/footer.php'; 
?>