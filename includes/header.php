<?php
define('BASE_URL', '/Administrativa');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $titulo ?? 'Panel Plantas Agrodex'; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/Administracion/assets/css/style.css">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand" href="/">
          <img src="/Administracion/assets/img/logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2><?php echo $encabezado ?? 'Panel de Control'; ?></h2>
          <p><?php echo $subtitulo ?? ''; ?></p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button class="save-button" onclick="window.location.href='<?php echo $ruta; ?>'">
                <i class="bi bi-arrow-left"></i>  <?php echo $texto_boton; ?>
              </button>
            </div>
          </div>
        </nav>
        
      </div>
    </header>
