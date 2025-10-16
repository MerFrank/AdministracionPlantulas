<?php
require_once(__DIR__ . '/../../includes/validacion_session.php'); 

// Variables para el encabezado
$titulo = "Clientes";
$encabezado = "Gestión de Clientes";
$subtitulo = "Panel de administración de clientes";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "../../session/login.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>


<main class="container mt-4">
  <section class="dashboard-grid">

   <div class="card">
          <h2>📋 Registrar Clientes</h2>
          <p>Registra clientes nuevos para la empresa.</p>
          <a href="registro_cliente.php">Ver detalles</a>
        </div>

    <div class="card">
          <h2>👤 Listar Clientes</h2>
          <p>Consulte el listado completo de clientes registrados en el sistema.</p>
          <a href="lista_clientes.php">Ver detalles</a>
        </div>

  </section>
</main>

<?php require('../../includes/footer.php'); ?>