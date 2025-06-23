<?php
// Encabezado común para todas las páginas
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $titulo ?? 'Panel Plantas Agrodex'; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/Plantulas/assets/css/style.css">
</head>
<body>
  <div class="contenedor-pagina">
    <header>

      <div class="encabezado">
        <a class="navbar-brand" href="/">
          <img src="/Plantulas/assets/img/logoplantulas.png" alt="Logo" width="130" height="124">
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
                <button class="button:hover" onclick="window.history.back()">
                  <i class="bi bi-arrow-left"></i>
                  Regresar
                </button>
              </div>
            </div>
          </nav>
        </div>
</header>

