<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Agregar Variedades</title>
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
            <h2>Agregar Variedades</h2>
            <p>Registra nuevas variedades para especies y colores</p>
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
          <form id="variedadForm">
            <h5>Datos de la Variedad</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="selectEspecieVariedad" class="form-label"
                  >Especie <span class="text-danger">*</span></label
                >
                <select class="form-select" id="selectEspecieVariedad" required>
                  <option value="">-- Seleccione una especie --</option>
                  <!-- Las especies se cargarán dinámicamente -->
                </select>
              </div>
              <div class="col-md-6">
                <label for="selectColorVariedad" class="form-label"
                  >Color <span class="text-danger">*</span></label
                >
                <select
                  class="form-select"
                  id="selectColorVariedad"
                  required
                  disabled
                >
                  <option value="">-- Seleccione un color --</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mt-2">
              <div class="col-md-6">
                <label for="nombreVariedad" class="form-label"
                  >Nombre de la Variedad
                  <span class="text-danger">*</span></label
                >
                <input
                  type="text"
                  class="form-control"
                  id="nombreVariedad"
                  required
                />
              </div>
              <div class="col-md-6">
                <label for="codigoVariedad" class="form-label"
                  >Código Único <span class="text-danger">*</span></label
                >
                <input
                  type="text"
                  class="form-control"
                  id="codigoVariedad"
                  required
                />
              </div>
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-success">
                Guardar Variedad
              </button>
            </div>
          </form>

          <div class="mt-5">
            <h5>Variedades Registradas</h5>
            <div class="table-responsive">
              <table
                class="table table-striped table-hover"
                id="tablaVariedades"
              >
                <thead>
                  <tr>
                    <th>Especie</th>
                    <th>Color</th>
                    <th>Variedad</th>
                    <th>Código</th>
                  </tr>
                </thead>
                <tbody id="listaVariedades">
                  <tr>
                    <td colspan="4" class="empty-message">
                      No hay variedades registradas
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <script>
          // Base de datos de ejemplo
          const especies = [
            {
              id: 1,
              nombre: "Rosa",
              colores: [
                { nombre: "Rojo", variedades: [] },
                {
                  nombre: "Blanco",
                  variedades: [
                    { nombre: "Clásica", codigo: "ROS-BLA-001" },
                    { nombre: "Premium", codigo: "ROS-BLA-002" },
                  ],
                },
              ],
            },
            {
              id: 2,
              nombre: "Tulipán",
              colores: [
                {
                  nombre: "Morado",
                  variedades: [{ nombre: "Estándar", codigo: "TUL-MOR-001" }],
                },
                { nombre: "Amarillo", variedades: [] },
              ],
            },
          ];

          document.addEventListener("DOMContentLoaded", function () {
            const selectEspecie = document.getElementById(
              "selectEspecieVariedad"
            );
            const selectColor = document.getElementById("selectColorVariedad");
            const listaVariedades = document.getElementById("listaVariedades");
            const form = document.getElementById("variedadForm");

            // Cargar especies en el select
            especies.forEach((especie) => {
              const option = document.createElement("option");
              option.value = especie.id;
              option.textContent = especie.nombre;
              selectEspecie.appendChild(option);
            });

            // Actualizar colores al seleccionar especie
            selectEspecie.addEventListener("change", function () {
              const especieId = parseInt(this.value);
              selectColor.innerHTML =
                '<option value="">-- Seleccione un color --</option>';
              selectColor.disabled = !especieId;

              if (especieId) {
                const especie = especies.find((e) => e.id === especieId);
                especie.colores.forEach((color) => {
                  const option = document.createElement("option");
                  option.value = color.nombre;
                  option.textContent = color.nombre;
                  selectColor.appendChild(option);
                });
              }

              actualizarListaVariedades();
            });

            // Actualizar al cambiar color
            selectColor.addEventListener("change", actualizarListaVariedades);

            // Guardar nueva variedad
            form.addEventListener("submit", function (e) {
              e.preventDefault();

              const especieId = parseInt(selectEspecie.value);
              const colorNombre = selectColor.value;
              const nombreVariedad = document
                .getElementById("nombreVariedad")
                .value.trim();
              const codigoVariedad = document
                .getElementById("codigoVariedad")
                .value.trim();

              // Validaciones
              if (
                !especieId ||
                !colorNombre ||
                !nombreVariedad ||
                !codigoVariedad
              ) {
                alert("Complete todos los campos obligatorios");
                return;
              }

              const especie = especies.find((e) => e.id === especieId);
              const color = especie.colores.find(
                (c) => c.nombre === colorNombre
              );

              // Validar código único
              const codigoExiste = especies.some((e) =>
                e.colores.some((c) =>
                  c.variedades.some((v) => v.codigo === codigoVariedad)
                )
              );

              if (codigoExiste) {
                alert("Este código ya está registrado para otra variedad");
                return;
              }

              // Agregar nueva variedad
              color.variedades.push({
                nombre: nombreVariedad,
                codigo: codigoVariedad,
              });

              // Actualizar lista y limpiar formulario
              actualizarListaVariedades();
              form.reset();
              selectColor.disabled = true;

              alert("Variedad registrada con éxito");
            });

            // Función para actualizar la lista de variedades
            function actualizarListaVariedades() {
              listaVariedades.innerHTML = "";
              const especieId = parseInt(selectEspecie.value);

              // Obtener todas las variedades de todas las especies
              let todasVariedades = [];

              especies.forEach((especie) => {
                especie.colores.forEach((color) => {
                  color.variedades.forEach((variedad) => {
                    todasVariedades.push({
                      especie: especie.nombre,
                      color: color.nombre,
                      nombre: variedad.nombre,
                      codigo: variedad.codigo,
                    });
                  });
                });
              });

              // Filtrar por especie seleccionada si hay una seleccionada
              if (especieId) {
                const especieSeleccionada = especies.find(
                  (e) => e.id === especieId
                );
                const colorNombre = selectColor.value;

                // Filtrar por color si también está seleccionado
                if (colorNombre) {
                  todasVariedades = todasVariedades.filter(
                    (v) =>
                      v.especie === especieSeleccionada.nombre &&
                      v.color === colorNombre
                  );
                } else {
                  todasVariedades = todasVariedades.filter(
                    (v) => v.especie === especieSeleccionada.nombre
                  );
                }
              }

              if (todasVariedades.length === 0) {
                listaVariedades.innerHTML = `
                <tr>
                  <td colspan="4" class="empty-message">No hay variedades registradas</td>
                </tr>
              `;
                return;
              }

              // Mostrar todas las variedades en la tabla
              todasVariedades.forEach((variedad) => {
                const row = document.createElement("tr");
                row.innerHTML = `
                <td>${variedad.especie}</td>
                <td>${variedad.color}</td>
                <td>${variedad.nombre}</td>
                <td>${variedad.codigo}</td>
              `;
                listaVariedades.appendChild(row);
              });
            }

            // Mostrar todas las variedades al cargar la página
            actualizarListaVariedades();
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
