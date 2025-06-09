<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cotización</title>
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
            <h2>Contización</h2>
            <p>Ofrece una propues a tus clientes</p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_clientesVentas.php'"
                >
                  Regresar inicio
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
        <header></header>
        <div class="container">
          <form id="cotizacionForm">
            <h2>Crear Cotización</h2>
            <label for="cliente">Cliente</label>
            <input
              type="text"
              id="cliente"
              placeholder="Nombre del cliente"
              required
            />

            <label for="categoria">Especie </label>
            <select id="categoria" required>
              <option value="">-- Selecciona una Especie --</option>
            </select>

            <label for="producto">Variedad</label>
            <select id="producto" disabled required>
              <option value="">-- Selecciona variedad --</option>
            </select>

            <label for="cantidad">Cantidad</label>
            <input type="number" id="cantidad" placeholder="Cantidad" min="1" />

            <label for="precioUnitario">Precio Unitario (MXN)</label>
            <input
              type="number"
              id="precioUnitario"
              placeholder="Precio por unidad"
              step="0.01"
              min="0"
            />

            <button type="button" id="agregarProducto">Agregar producto</button>

            <table id="tablaProductos" style="display: none">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Precio Unitario</th>
                  <th>Subtotal</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <!-- Productos agregados -->
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="3">Total:</td>
                  <td id="totalGeneral">$0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>

            <label for="fechaValidez">Fecha de Validez</label>
            <input type="date" id="fechaValidez" required />

            <label for="notas">Notas adicionales</label>
            <textarea
              id="notas"
              rows="4"
              placeholder="Comentarios o condiciones especiales"
            ></textarea>

            <button type="submit">Guardar Cotización</button>
          </form>
        </div>

        <script>
          // Definimos las categorías y sus productos (subproductos)
          const datos = {
            Plantas: [
              "Plántula de tomate",
              "Plántula de chile",
              "Plántula de lechuga",
            ],
            Herramientas: ["Rastrillo", "Pala", "Tijeras de poda"],
            Fertilizantes: [
              "Abono orgánico",
              "Fertilizante NPK 10-10-10",
              "Compost",
            ],
          };

          const categoriaSelect = document.getElementById("categoria");
          const productoSelect = document.getElementById("producto");

          // Cargar categorías en el select
          function cargarCategorias() {
            for (const categoria in datos) {
              const option = document.createElement("option");
              option.value = categoria;
              option.textContent = categoria;
              categoriaSelect.appendChild(option);
            }
          }

          // Al cambiar la categoría, cargar los productos correspondientes
          categoriaSelect.addEventListener("change", () => {
            const seleccion = categoriaSelect.value;
            productoSelect.innerHTML =
              '<option value="">-- Selecciona producto --</option>';

            if (seleccion && datos[seleccion]) {
              datos[seleccion].forEach((prod) => {
                const option = document.createElement("option");
                option.value = prod;
                option.textContent = prod;
                productoSelect.appendChild(option);
              });
              productoSelect.disabled = false;
            } else {
              productoSelect.disabled = true;
            }
          });

          // Array para almacenar productos agregados a la cotización
          const productos = [];
          const tabla = document.getElementById("tablaProductos");
          const tbody = tabla.querySelector("tbody");
          const totalGeneralEl = document.getElementById("totalGeneral");
          const btnAgregar = document.getElementById("agregarProducto");

          btnAgregar.addEventListener("click", () => {
            const nombre = productoSelect.value;
            const cantidad = parseInt(
              document.getElementById("cantidad").value
            );
            const precio = parseFloat(
              document.getElementById("precioUnitario").value
            );

            if (!nombre) {
              alert("Por favor, selecciona un producto.");
              return;
            }
            if (!cantidad || cantidad <= 0) {
              alert("Por favor, ingresa una cantidad válida.");
              return;
            }
            if (isNaN(precio) || precio < 0) {
              alert("Por favor, ingresa un precio unitario válido.");
              return;
            }

            const subtotal = cantidad * precio;

            productos.push({ nombre, cantidad, precio, subtotal });
            actualizarTabla();

            // Limpiar inputs producto
            productoSelect.value = "";
            categoriaSelect.value = "";
            productoSelect.disabled = true;
            document.getElementById("cantidad").value = "";
            document.getElementById("precioUnitario").value = "";
            categoriaSelect.focus();
          });

          function actualizarTabla() {
            tbody.innerHTML = "";
            let total = 0;
            productos.forEach((prod, index) => {
              total += prod.subtotal;
              const tr = document.createElement("tr");
              tr.innerHTML = `
          <td>${prod.nombre}</td>
          <td><input class="cantidad-edit" type="number" min="1" value="${
            prod.cantidad
          }" onchange="editarCantidad(${index}, this.value)" /></td>
          <td><input class="precio-edit" type="number" min="0" step="0.01" value="${prod.precio.toFixed(
            2
          )}" onchange="editarPrecio(${index}, this.value)" /></td>
          <td>$${prod.subtotal.toFixed(2)}</td>
          <td><button class="btn-eliminar" onclick="eliminarProducto(${index})">Eliminar</button></td>
        `;
              tbody.appendChild(tr);
            });

            totalGeneralEl.textContent = `$${total.toFixed(2)}`;
            tabla.style.display = productos.length > 0 ? "table" : "none";
          }

          function eliminarProducto(index) {
            productos.splice(index, 1);
            actualizarTabla();
          }

          function editarCantidad(index, nuevoValor) {
            const cantidad = parseInt(nuevoValor);
            if (isNaN(cantidad) || cantidad <= 0) {
              alert("Cantidad inválida, debe ser mayor a 0.");
              actualizarTabla();
              return;
            }
            productos[index].cantidad = cantidad;
            productos[index].subtotal =
              productos[index].cantidad * productos[index].precio;
            actualizarTabla();
          }

          function editarPrecio(index, nuevoValor) {
            const precio = parseFloat(nuevoValor);
            if (isNaN(precio) || precio < 0) {
              alert("Precio inválido, debe ser mayor o igual a 0.");
              actualizarTabla();
              return;
            }
            productos[index].precio = precio;
            productos[index].subtotal =
              productos[index].cantidad * productos[index].precio;
            actualizarTabla();
          }

          // Hacer accesible las funciones para el onchange inline
          window.eliminarProducto = eliminarProducto;
          window.editarCantidad = editarCantidad;
          window.editarPrecio = editarPrecio;

          // Manejo del formulario completo
          document
            .getElementById("cotizacionForm")
            .addEventListener("submit", function (e) {
              e.preventDefault();

              if (productos.length === 0) {
                alert("Agrega al menos un producto a la cotización.");
                return;
              }

              const cotizacion = {
                cliente: document.getElementById("cliente").value,
                productos: productos,
                fechaValidez: document.getElementById("fechaValidez").value,
                notas: document.getElementById("notas").value,
              };

              let total = 0;
              cotizacion.productos.forEach((p) => (total += p.subtotal));
              cotizacion.total = total;

              console.log("Cotización guardada:", cotizacion);
              alert(
                "Cotización registrada exitosamente\nTotal: $" +
                  total.toFixed(2)
              );

              this.reset();
              productos.length = 0;
              actualizarTabla();

              // Deshabilitar select producto hasta que se elija categoría de nuevo
              productoSelect.disabled = true;
            });

          // Cargar las categorías al cargar la página
          cargarCategorias();
        </script>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
