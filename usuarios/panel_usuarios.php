<?php
// 0) Mostrar errores (solo en desarrollo)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

// 1) Sesión y rol
// require_once __DIR__.'/../session_manager.php';
// require_once __DIR__.'/../db.php';

// if (!isset($_SESSION['ID_Operador'])) {
//     header('Location: ../login.php?mensaje=Debe iniciar sesión'); exit;
// }
// if ((int)$_SESSION['Rol'] !== 1) {   // Rol 1 = Administrador
//     echo "<p class='error'>⚠️ Acceso denegado.</p>"; exit;
// }

// require_once __DIR__ . '/../../includes/config.php';

require_once(__DIR__ . '/../includes/config.php');
// Configuración de la página
$titulo = "Panel de Control Usuarios";
$encabezado = "Sistema de Gestión Ususarios";
$subtitulo = "Control y configuración de usuarios";
$active_page = "dashboard";
$ruta = "../modulos/dashboard_adminGeneral.php";
$texto_boton = "";
//Incluir el header
require_once(__DIR__ . '/../includes/header.php');

?>


<main>
  <div class="container-fluid py-3">

  
    <!-- Tarjetas -->
    <h5 class="mb-3">¡Hola, <?=htmlspecialchars($_SESSION['Nombre'])?>!</h5>
    <div class="row g-4">
  
      <!-- Alta operador -->
      <div class="col-12 col-sm-6 col-lg-4">
        <a href="registro_usuario.php" class="text-decoration-none">
          <div class="card shadow-sm card-admin h-100">
            <div class="card-body text-center">
              <h4 class="card-title">➕ Registrar operador</h4>
              <p class="card-text small text-muted">Crear nuevos usuarios</p>
            </div>
          </div>
        </a>
      </div>
  
      <!-- Gestionar operadores -->
      <div class="col-12 col-sm-6 col-lg-4">
        <a href="gestionar_operadores.php" class="text-decoration-none">
          <div class="card shadow-sm card-admin h-100">
            <div class="card-body text-center">
              <h4 class="card-title">👥 Gestionar operadores</h4>
              <p class="card-text small text-muted">Editar / desactivar usuarios</p>
            </div>
          </div>
        </a>
      </div>
  
      <!-- Reportes -->
      <!-- <div class="col-12 col-sm-6 col-lg-4">
        <a href="ver_reportes.php" class="text-decoration-none">
          <div class="card shadow-sm card-admin h-100">
            <div class="card-body text-center">
              <h4 class="card-title">📊 Reportes</h4>
              <p class="card-text small text-muted">Estadísticas generales</p>
            </div>
          </div>
        </a>
      </div> -->
  
      <!-- Llave maestra -->
      <div class="col-12 col-sm-6 col-lg-4">
        <a href="llave_maestra.php" class="text-decoration-none">
          <div class="card shadow-sm card-admin h-100 bg-warning-subtle">
            <div class="card-body text-center">
              <h4 class="card-title">🔑 Llave maestra</h4>
              <p class="card-text small text-muted">Impersonar otros roles</p>
            </div>
          </div>
        </a>
      </div>
  
    </div><!-- /row -->
  </div><!-- /container -->
</main>

    <!-- Footer incluido desde footer.php -->
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>