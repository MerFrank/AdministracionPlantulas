<?php
include __DIR__ . '/db/config.php';

?>

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sistema de Notas de Remisión</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
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
            <h2>Nota de Remisión</h2>
            <p>Sistema de ventas</p>
          </div>
        </div>

        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_clientesVentas.php'"
                >
                  <i class="bi bi-arrow-left"></i> Regresar inicio
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <main>
        <div class="container">
          <div class="row">
            <div class="col-lg-12">
              <div class="card">
                <div class="card-header">
                  <i class="bi bi-file-earmark-text"></i> Información General
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label for="numeroRemision" class="form-label"
                        >Número de Remisión</label
                      >
                      <input
                        type="text"
                        class="form-control"
                        id="numeroRemision"
                        value="3320"
                        required
                      />
                    </div>
                    <div class="col-md-3">
                      <label for="fecha" class="form-label">Fecha</label>
                      <input
                        type="date"
                        class="form-control"
                        id="fecha"
                        required
                      />
                    </div>
                    <div class="col-md-6">
                      <label for="cotizacion" class="form-label"
                        >Cotización Relacionada</label
                      >
                      <select class="form-select" id="cotizacion">
                        <option value="">
                          -- Seleccione una cotización --
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <i class="bi bi-person"></i> Datos del Cliente
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="buscarCliente" class="form-label"
                        >Buscar Cliente</label
                      >
                      <div class="autocomplete-input">
                        <input
                          type="text"
                          class="form-control"
                          id="buscarCliente"
                          placeholder="Nombre, teléfono o RFC"
                        />
                        <div
                          class="autocomplete-list"
                          id="resultadosClientes"
                        ></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label for="rfcCliente" class="form-label">RFC</label>
                      <input
                        type="text"
                        class="form-control"
                        id="rfcCliente"
                        readonly
                      />
                    </div>
                    <div class="col-md-6">
                      <label for="nombreCliente" class="form-label"
                        >Nombre Completo</label
                      >
                      <input
                        type="text"
                        class="form-control"
                        id="nombreCliente"
                        required
                      />
                    </div>
                    <div class="col-md-6">
                      <label for="telefono" class="form-label">Teléfono</label>
                      <input type="tel" class="form-control" id="telefono" />
                    </div>
                    <div class="col-md-12">
                      <label for="direccion" class="form-label"
                        >Dirección</label
                      >
                      <textarea
                        class="form-control"
                        id="direccion"
                        rows="2"
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <i class="bi bi-basket"></i> Detalle de Plantas
                </div>
                <div class="card-body">
                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <label for="especie" class="form-label">Especie</label>
                      <select class="form-select" id="especie" required>
                        <option value="">-- Seleccione una especie --</option>
                        <option value="Tomate">Tomate</option>
                        <option value="Chile">Chile</option>
                        <option value="Lechuga">Lechuga</option>
                        <option value="Fresa">Fresa</option>
                        <option value="Orquídea">Orquídea</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label for="variedad" class="form-label">Variedad</label>
                      <select class="form-select" id="variedad" required>
                        <option value="">
                          -- Seleccione primero una especie --
                        </option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label for="cantidad" class="form-label">Cantidad</label>
                      <input
                        type="number"
                        class="form-control"
                        id="cantidad"
                        min="1"
                        value="1"
                        required
                      />
                    </div>
                    <div class="col-md-2">
                      <label for="costo" class="form-label"
                        >Precio Unitario</label
                      >
                      <input
                        type="number"
                        class="form-control"
                        id="costo"
                        min="0"
                        step="0.01"
                        required
                      />
                    </div>
                  </div>
                  <button
                    type="button"
                    class="btn btn-primary"
                    id="agregarProducto"
                  >
                    <i class="bi bi-plus-circle"></i> Agregar Planta
                  </button>

                  <div class="table-responsive mt-3">
                    <table
                      class="table table-bordered table-hover"
                      id="tablaProductos"
                      style="display: none"
                    >
                      <thead class="table-light">
                        <tr>
                          <th>Variedad/Especie</th>
                          <th>Cantidad</th>
                          <th>Precio Unitario</th>
                          <th>Subtotal</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr>
                          <td colspan="3" class="text-end">
                            <strong>Total:</strong>
                          </td>
                          <td class="total-display" id="totalGeneral">$0.00</td>
                          <td></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <i class="bi bi-cash-coin"></i> Información de Pago
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label for="tipoPago" class="form-label"
                        >Tipo de Pago</label
                      >
                      <select class="form-select" id="tipoPago">
                        <option value="contado">Contado</option>
                        <option value="credito">Crédito</option>
                        <option value="anticipo">Anticipo</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label for="metodoPago" class="form-label"
                        >Método de Pago</label
                      >
                      <select class="form-select" id="metodoPago">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="tarjeta">Tarjeta</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label for="anticipo" class="form-label"
                        >Anticipo (MXN)</label
                      >
                      <input
                        type="number"
                        class="form-control"
                        id="anticipo"
                        min="0"
                        step="0.01"
                        value="0"
                      />
                    </div>
                    <div class="col-md-12">
                      <label for="importeLetra" class="form-label"
                        >Importe con Letra</label
                      >
                      <input
                        type="text"
                        class="form-control"
                        id="importeLetra"
                        readonly
                      />
                    </div>
                    <div class="col-md-12">
                      <label for="observaciones" class="form-label"
                        >Observaciones</label
                      >
                      <textarea
                        class="form-control"
                        id="observaciones"
                        rows="2"
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <i class="bi bi-file-earmark-text"></i> Pagaré
                </div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label for="numeroPagare" class="form-label"
                        >Número de Pagaré</label
                      >
                      <input
                        type="text"
                        class="form-control"
                        id="numeroPagare"
                      />
                    </div>
                    <div class="col-md-4">
                      <label for="fechaVencimiento" class="form-label"
                        >Fecha de Vencimiento</label
                      >
                      <input
                        type="date"
                        class="form-control"
                        id="fechaVencimiento"
                      />
                    </div>
                    <div class="col-md-4">
                      <label for="lugarPago" class="form-label"
                        >Lugar de Pago</label
                      >
                      <input
                        type="text"
                        class="form-control"
                        id="lugarPago"
                        value="Tenancingo, México"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button
                  type="button"
                  class="btn btn-outline-secondary me-md-2"
                  id="limpiarForm"
                >
                  <i class="bi bi-x-circle"></i> Limpiar
                </button>
                <button
                  type="button"
                  class="btn btn-primary me-md-2"
                  id="guardarRemision"
                >
                  <i class="bi bi-save"></i> Guardar
                </button>
                <button type="button" class="btn btn-success" id="generarPDF">
                  <i class="bi bi-file-earmark-pdf"></i> Generar PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Ejemplo de funcionalidad JavaScript
      document.addEventListener("DOMContentLoaded", function () {
        // Configurar fecha actual en el formulario de pago
        const today = new Date().toISOString().split("T")[0];
        document.getElementById("paymentDate").value = today;

        // Ejemplo de guardar pago
        document
          .getElementById("savePayment")
          .addEventListener("click", function () {
            const form = document.getElementById("paymentForm");
            if (form.checkValidity()) {
              alert("Pago registrado correctamente");
              // Aquí iría el código para enviar los datos al servidor
              // ...
              // Cerrar el modal después de guardar
              const modal = bootstrap.Modal.getInstance(
                document.getElementById("newPaymentModal")
              );
              modal.hide();
            } else {
              form.reportValidity();
            }
          });

        // Ejemplo de búsqueda
        document
          .getElementById("searchButton")
          .addEventListener("click", function () {
            alert("Búsqueda realizada (simulación)");
            // Aquí iría el código para filtrar la tabla según los criterios de búsqueda
          });
      });
    </script>
  </body>
</html>
