<?php

// Configuración de encabezado
$titulo = "Gestión de Ventas";
$encabezado = "Panel de Ventas y Seguimiento de Pagos";
$subtitulo = "Administra cotizaciones, ventas, seguimiento de pagos";

// Incluir la cabecera (ruta relativa al archivo actual)
require('../../includes/header.php');
?>


<main class="container mt-4">
  <section class="dashboard-grid">
          <div class="card">
            <h2>👤📝 Registrar Clientes</h2>
            <p>Revisa a los nuevo clientes para la empresa.</p>
            <a href="registro_cliente.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>📊 Seguimiento Ventas</h2>
            <p>Realiza un seguimiento de tus ventas.</p>
            <a href="reporte_ventas.php">Trabajo en Disección</a>
          </div>
          <div class="card">
            <h2>💰 Cotización</h2>
            <p>Muestra diferentes precio u opciones a los clientes.</p>
            <a href="cotizacion.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>🧾 Nota de venta</h2>
            <p>Levanta una nota de venta.</p>
            <a href="notas_pedido.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>💳  Registro de anticipos</h2>
            <p>Manten un seguimiento de los pagos hechos.</p>
            <a href="registro_anticipo.php">Ver detalles</a>
          </div>
        </section>
</main>

<?php require('../../includes/footer.php'); ?>