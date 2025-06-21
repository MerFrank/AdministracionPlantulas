<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Asignar Colores a Especies</title>
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
            <h2>Asignar Colores a Especies</h2>
            <p>Gestiona los colores disponibles para cada especie</p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_registroProducto.html'"
                >
                  Regresar inicio
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
        <div class="container mt-5">
          <form id="colorForm">
            <h5>Asignar Nuevo Color</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="selectEspecie" class="form-label"
                  >Seleccionar Especie <span class="text-danger">*</span></label
                >
                <select class="form-select" id="selectEspecie" required>
                  <option value="">-- Seleccione una especie --</option>
                  <!-- Las especies se cargarán dinámicamente -->
                </select>
              </div>
              <div class="col-md-6">
                <label for="nuevoColor" class="form-label"
                  >Nombre del Color <span class="text-danger">*</span></label
                >
                <div class="input-group">
                  <input
                    type="text"
                    class="form-control"
                    id="nuevoColor"
                    required
                  />
                  <button
                    type="button"
                    class="btn btn-primary"
                    id="btnAgregarColor"
                  >
                    Agregar
                  </button>
                </div>
              </div>
            </div>
          </form>

          <div class="mt-4">
            <h5>Colores Asignados</h5>
            <div class="color-container" id="coloresAsignados">
              <div class="empty-message">
                Seleccione una especie para ver sus colores
              </div>
            </div>
          </div>
        </div>

        <script>
          // Base de datos de ejemplo
          const especies = [
            { id: 1, nombre: "Rosa", colores: ["Rojo", "Blanco", "Amarillo"] },
            { id: 2, nombre: "Tulipán", colores: ["Morado", "Blanco"] },
            { id: 3, nombre: "Orquídea", colores: ["Blanco", "Rosa"] },
          ];

          document.addEventListener("DOMContentLoaded", function () {
            const selectEspecie = document.getElementById("selectEspecie");
            const coloresAsignados =
              document.getElementById("coloresAsignados");
            const btnAgregarColor = document.getElementById("btnAgregarColor");
            const inputNuevoColor = document.getElementById("nuevoColor");

            // Cargar especies en el select
            especies.forEach((especie) => {
              const option = document.createElement("option");
              option.value = especie.id;
              option.textContent = especie.nombre;
              selectEspecie.appendChild(option);
            });

            // Mostrar colores al seleccionar especie
            selectEspecie.addEventListener("change", function () {
              const especieId = parseInt(this.value);
              const especie = especies.find((e) => e.id === especieId);

              coloresAsignados.innerHTML = "";

              if (!especie) {
                coloresAsignados.innerHTML =
                  '<div class="empty-message">Seleccione una especie para ver sus colores</div>';
                return;
              }

              if (especie.colores.length === 0) {
                coloresAsignados.innerHTML =
                  '<div class="empty-message">Esta especie no tiene colores asignados</div>';
                return;
              }

              especie.colores.forEach((color) => {
                const badge = document.createElement("span");
                badge.className = "color-badge";
                badge.textContent = color;
                coloresAsignados.appendChild(badge);
              });
            });

            // Agregar nuevo color
            btnAgregarColor.addEventListener("click", function () {
              const especieId = parseInt(selectEspecie.value);
              const nuevoColor = inputNuevoColor.value.trim();

              if (!especieId) {
                alert("Por favor seleccione una especie");
                return;
              }

              if (!nuevoColor) {
                alert("Ingrese el nombre del color");
                return;
              }

              const especie = especies.find((e) => e.id === especieId);

              // Validar que el color no exista
              if (
                especie.colores.some(
                  (c) => c.toLowerCase() === nuevoColor.toLowerCase()
                )
              ) {
                alert("Este color ya está asignado a la especie seleccionada");
                return;
              }

              // Agregar el color
              especie.colores.push(nuevoColor);

              // Actualizar la visualización
              const badge = document.createElement("span");
              badge.className = "color-badge";
              badge.textContent = nuevoColor;
              coloresAsignados.appendChild(badge);

              // Limpiar el input
              inputNuevoColor.value = "";

              alert(
                `Color "${nuevoColor}" agregado correctamente a "${especie.nombre}"`
              );
            });
          });
        </script>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
