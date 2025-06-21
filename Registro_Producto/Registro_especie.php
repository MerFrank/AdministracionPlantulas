<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de Especies</title>
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
            <h2>Registro de especies</h2>
            <p>Ingresa una nueva especie en el sistema</p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_registroProducto.php'"
                >
                  Regresar inicio
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
        <h2>Registrar Nueva Especie</h2>
        <div class="container mt-5">
          <form id="especieForm">
            <h5>Datos de la Especie</h5>
            <div class="mb-3">
              <label for="nombreEspecie" class="form-label"
                >Nombre de la especie <span class="text-danger">*</span></label
              >
              <input
                type="text"
                class="form-control"
                id="nombreEspecie"
                required
                placeholder="Ej. Rosa, Tulipán, Orquídea"
              />
            </div>
            <div class="mb-3">
              <label for="descripcionEspecie" class="form-label"
                >Descripción</label
              >
              <textarea
                class="form-control"
                id="descripcionEspecie"
                rows="3"
                placeholder="Características principales de la especie"
              ></textarea>
            </div>

            <button type="submit" class="btn btn-success">
              Guardar Especie
            </button>
          </form>
        </div>

        <div class="container mt-5">
          <h5>Especies Registradas</h5>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Descripción</th>
                  <th>Fecha Registro</th>
                </tr>
              </thead>
              <tbody id="listaEspecies">
                <!-- Las especies se cargarán aquí dinámicamente -->
                <tr>
                  <td colspan="4" class="text-center">
                    No hay especies registradas
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <script>
          // Base de datos temporal
          let especies = [];

          document
            .getElementById("especieForm")
            .addEventListener("submit", function (event) {
              event.preventDefault();

              const nombre = document
                .getElementById("nombreEspecie")
                .value.trim();
              const descripcion = document
                .getElementById("descripcionEspecie")
                .value.trim();

              // Validar que el nombre no esté vacío
              if (!nombre) {
                alert("Por favor ingrese el nombre de la especie");
                return;
              }

              // Verificar si la especie ya existe
              const existe = especies.some(
                (esp) => esp.nombre.toLowerCase() === nombre.toLowerCase()
              );
              if (existe) {
                alert("Esta especie ya está registrada");
                return;
              }

              // Crear nuevo registro
              const nuevaEspecie = {
                id: Date.now(),
                nombre,
                descripcion,
                fecha: new Date().toLocaleDateString(),
              };

              // Agregar a la lista
              especies.push(nuevaEspecie);

              // Actualizar la tabla
              actualizarListaEspecies();

              // Limpiar formulario
              this.reset();

              // Mostrar mensaje de éxito
              alert("Especie registrada exitosamente");
            });

          function actualizarListaEspecies() {
            const tbody = document.getElementById("listaEspecies");

            if (especies.length === 0) {
              tbody.innerHTML = `
              <tr>
                <td colspan="4" class="text-center">No hay especies registradas</td>
              </tr>
            `;
              return;
            }

            tbody.innerHTML = especies
              .map(
                (especie) => `
            <tr>
              <td>${especie.id}</td>
              <td>${especie.nombre}</td>
              <td>${especie.descripcion || "-"}</td>
              <td>${especie.fecha}</td>
            </tr>
          `
              )
              .join("");
          }
        </script>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
