<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/AdministracionPlantulas');
}
// Detectar si es la página de login
$es_login = (basename($_SERVER['PHP_SELF']) == 'login.php'); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $titulo ?? 'Panel Plantas Agrodex'; ?></title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
  <div class="contenedor-pagina">
    
    <?php if (!$es_login): ?>
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand" href="/">
          <img src="<?php echo BASE_URL; ?>/assets/img/logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2><?php echo $encabezado ?? 'Panel de Control'; ?></h2>
          <p><?php echo $subtitulo ?? ''; ?></p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid d-flex justify-content-end">
            <div class="d-flex gap-2">
              <?php if (isset($opciones_menu)): ?>
                <div class="dropdown">
                  <button class="save-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Opciones
                  </button>
                  <ul class="dropdown-menu">
                    <?php foreach ($opciones_menu as $opcion): ?>
                      <li><a class="dropdown-item" href="<?php echo $opcion['ruta']; ?>"><?php echo $opcion['texto']; ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <?php if (isset($texto_boton)): ?>
                <div class="Opciones-barra">
                  <button class="save-button" onclick="window.location.href='<?php echo $ruta; ?>'">
                    <i class="bi bi-arrow-left"></i> <?php echo $texto_boton; ?>
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </nav>
      </div>
    </header>
    <?php endif; ?>