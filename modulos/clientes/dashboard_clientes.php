<?php

// Variables para el encabezado
$titulo = "Clientes";
$encabezado = "Gestión de Clientes";
$subtitulo = "Panel de administración de clientes";

// Incluir la cabecera (ruta relativa al archivo actual)
require('../../includes/header.php');
?>


<main class="container mt-4">
  <section class="dashboard-grid">
    <div class="card">
          <h2>👤 Listar Clientes</h2>
          <p>Visualisa todos los clientes disponibles en el sistema.</p>
          <a href="lista_clientes.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>📋 Registrar Clientes</h2>
          <p>Registra a los nuevo clientes para la empresa.</p>
          <a href="registro_cliente.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>📝 Edita Clientes</h2>
          <p>Edita los datos de los clientes en el sistema.</p>
          <a href="editar_cliente.php">Ver detalles</a>
        </div>

        <div class="card">
          <h2>🗑️ Elimina Clientes</h2>
          <p>Elimina los clintes que ya no estaran en el sistema.</p>
          <a href="eliminar_cliente.php">Ver detalles</a>
        </div>
  </section>
</main>

<?php require('../../includes/footer.php'); ?>