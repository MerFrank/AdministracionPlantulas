<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Registrar productos</title>
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
            <h2>🎨 Registro Colores</h2>
            <p>
              Administra los colores disponibles para cada especie o variedad.
            </p>
            <a href="Registro_colores.html">Ver detalles</a>
          </div>

          <div class="card">
            <h2>🌿 Registro Especie</h2>
            <p>Agrega o edita especies de plantas disponibles en el sistema.</p>
            <a href="Registro_especie.html">Ver detalles</a>
          </div>

          <div class="card">
            <h2>🧾 Registro Variedades</h2>
            <p>
              Gestiona las diferentes variedades para cada especie registrada.
            </p>
            <a href="Registro_variedades.html">Ver detalles</a>
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
