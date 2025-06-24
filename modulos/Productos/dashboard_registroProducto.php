<?php

// Configuración de encabezado
$titulo = "Gestión de Productos";
$encabezado = "Panel de Control de Productos";
$subtitulo = "Administra inventario, colores, especies y variedades";

// Incluir la cabecera (ruta relativa al archivo actual)
require('../../includes/header.php');
?>


<main class="container mt-4">
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

<?php require('../../includes/footer.php'); ?>