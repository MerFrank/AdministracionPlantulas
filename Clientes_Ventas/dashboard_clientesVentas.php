<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Operaciones Financiazas</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
  </head>
  <body>
    <div class="contenedor-pagina">
      <header>
        <div class="encabezado">
          <a class="navbar-brand" href="#">
            <img
              src="/css/logoplantulas.png"
              alt="Logo"
              width="130"
              height="124"
              class="d-inline-block align-text-center"
            />
          </a>
          <div>
            <h2>Clientes y Ventas</h2>
            <p></p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button onclick="window.location.href=''">
                  Cerrar Sesión (aún no funciona)
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
        <section class="dashboard-grid">
          <div class="card">
            <h2>📝 Registrar Clientes</h2>
            <p>Revisa a los nuevo clientes para la empresa.</p>
            <a href="registro_cliente.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>📊 Seguimiento Ventas</h2>
            <p>Realiza un seguimiento de tus ventas.</p>
            <a href="reporte_ventas.php">Trabajo en Disección</a>
          </div>
          <div class="card">
            <h2>📝 Cotización</h2>
            <p>Muestra diferentes precio u opciones a los clientes.</p>
            <a href="cotizacion.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>🧾 Nota de venta</h2>
            <p>Levanta una nota de venta.</p>
            <a href="notas_pedido.php">Ver detalles</a>
          </div>
          <div class="card">
            <h2>📊 Registro de anticipos</h2>
            <p>Manten un seguimiento de los pagos hechos.</p>
            <a href="registro_anticipo.php">Ver detalles</a>
          </div>
        </section>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
