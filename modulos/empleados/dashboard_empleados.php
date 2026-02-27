<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Variables para el encabezado
$titulo = "Empleados";
$encabezado = "Gestión de Empleados";
$subtitulo = "Panel de administración de empleados";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "../../session/login.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
  <section class="dashboard-grid">
    <div class="card">
      <h2>📋 Registrar Empleado</h2>
      <p>Registra nuevos empleados para la empresa.</p>
      <a href="registro_empleado.php">Ver detalles</a>
    </div>

    <div class="card">
      <h2>👥 Listar Empleados</h2>
      <p>Consulte el listado completo de empleados registrados en el sistema.</p>
      <a href="lista_empleados.php">Ver detalles</a>
    </div>

    <div class="card">
      <h2>💰 Prestamos Empleados</h2>
      <p>Consulte el listado  de empleados con prestamos en el sistema.</p>
      <a href="lista_empleadosDeuda.php">Ver detalles</a>
    </div>
    
  </section>
</main>

<?php require('../../includes/footer.php'); ?>