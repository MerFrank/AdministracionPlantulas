<?php
define('BASE_URL', '/AdministracionPlantulas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $titulo ?? 'Panel Plantas Agrodex'; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/AdministracionPlantulas/assets/css/style.css">
   <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand" href="/">
          <img src="/AdministracionPlantulas/assets/img/logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2><?php echo $encabezado ?? 'Panel de Control'; ?></h2>
          <p><?php echo $subtitulo ?? ''; ?></p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid d-flex justify-content-between">
 
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
            <?php else: ?>
              <div class="Opciones-barra">
                <button class="save-button" onclick="window.location.href='<?php echo $ruta; ?>'">
                  <i class="bi bi-arrow-left"></i> <?php echo $texto_boton; ?>
                </button>
              </div>
            <?php endif; ?>

          </div>
        </nav>
     </div>
  </header>
</body>
</html>

