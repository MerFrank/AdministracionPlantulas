<?php 
// Incluir archivo de configuración de base de datos
require_once 'C:/xampp/htdocs/Plantulas/includes/config.php';
?>

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Registrar productos</title>
    <link rel="stylesheet" href="/Plantulas/assets/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
  </head>
  <body>
    <?php 
    // Incluir header
    include 'C:/xampp/htdocs/Plantulas/includes/header.php';
    ?>

    <main>
      <section class="dashboard-grid">

      <div class="card">
          <h2>🌿 Registro Especie</h2>
          <p>Agrega o edita especies de plantas disponibles en el sistema.</p>
          <a href="Registro_especie.php">Ver detalles</a>
        </div>

        
        <div class="card">
          <h2>🎨 Registro Colores</h2>
          <p>
            Administra los colores disponibles para cada especie o variedad.
          </p>
          <a href="Registro_colores.php">Ver detalles</a>
        </div>

        
        <div class="card">
          <h2>🧾 Registro Variedades</h2>
          <p>
            Gestiona las diferentes variedades para cada especie registrada.
          </p>
          <a href="Registro_variedades.php">Ver detalles</a>
        </div>
      </section>
    </main>

    <?php 
    // Incluir footer
    include 'C:/xampp/htdocs/Plantulas/includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>