<?php
include __DIR__ . '/db/config.php';

?>

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sistema de notas de remision</title>
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
            <h2>Nota de remision</h2>
            <p>Sistema de notas para plantas</p>
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
      // Limpiar formulario
      document
        .getElementById("limpiarForm")
        .addEventListener("click", function () {
          if (
            confirm("¿Está seguro de que desea limpiar todo el formulario?")
          ) {
            document.getElementById("numeroRemision").value = "3320";
            document.getElementById("fecha").value = today;
            document.getElementById("cotizacion").value = "";
            document.getElementById("buscarCliente").value = "";
            document.getElementById("nombreCliente").value = "";
            document.getElementById("telefono").value = "";
            document.getElementById("direccion").value = "";
            document.getElementById("rfcCliente").value = "";
            document.getElementById("especie").value = "";
            document.getElementById("variedad").innerHTML =
              '<option value="">-- Seleccione primero una especie --</option>';
            document.getElementById("cantidad").value = "1";
            document.getElementById("costo").value = "";
            document.getElementById("tipoPago").value = "contado";
            document.getElementById("metodoPago").value = "efectivo";
            document.getElementById("anticipo").value = "0";
            document.getElementById("importeLetra").value = "";
            document.getElementById("observaciones").value = "";
            document.getElementById("numeroPagare").value = "";
            document.getElementById("fechaVencimiento").value = "";
            productos = [];
            actualizarTablaProductos();
          }
        });

      // Función para generar el PDF
      function generarPDF() {
        if (!validarFormulario()) return;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Encabezado
        doc.setFontSize(16);
        doc.setTextColor(40, 167, 69);
        doc.text("Venta de Plantas In vitro", 105, 15, { align: "center" });

        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.text("ING. SILVESTRE PEREZ PEREZ", 105, 22, { align: "center" });
        doc.text("CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA", 105, 27, {
          align: "center",
        });
        doc.text("TENANCINGO, EDO. DE MEXICA 52433", 105, 32, {
          align: "center",
        });
        doc.text("CELS.: 7222041444  E-mail: plantasdoc@hotmail.com", 105, 37, {
          align: "center",
        });

        // Nota de Remisión
        doc.setFontSize(12);
        doc.text("NOTA DE REMISIÓN", 105, 45, { align: "center" });
        doc.text(
          `No. ${document.getElementById("numeroRemision").value}`,
          105,
          50,
          { align: "center" }
        );

        // Línea divisoria
        doc.line(10, 55, 200, 55);

        // Datos del cliente
        doc.setFontSize(10);
        const fecha = new Date(
          document.getElementById("fecha").value
        ).toLocaleDateString("es-MX");
        doc.text(`TENANCINGO, MEX. A ${fecha}`, 20, 65);
        doc.text(
          `Nombre: ${document.getElementById("nombreCliente").value}`,
          20,
          75
        );
        doc.text(
          `Dirección: ${document.getElementById("direccion").value}`,
          20,
          80
        );
        doc.text(`Tel: ${document.getElementById("telefono").value}`, 20, 85);
        doc.text(`RFC: ${document.getElementById("rfcCliente").value}`, 20, 90);

        // Tabla de plantas
        const plantasData = [];
        productos.forEach((prod) => {
          plantasData.push([
            prod.descripcion,
            prod.cantidad,
            `$${prod.costo.toFixed(2)}`,
            `$${prod.subtotal.toFixed(2)}`,
          ]);
        });

        doc.autoTable({
          startY: 95,
          head: [
            [
              "VARIEDAD Y/O ESPECIE",
              "CANTIDAD",
              "COSTO POR PLANTA",
              "SUBTOTAL",
            ],
          ],
          body: plantasData,
          margin: { left: 10 },
          styles: { fontSize: 8 },
          headStyles: { fillColor: [40, 167, 69] },
        });

        // Total
        const finalY = doc.lastAutoTable.finalY + 10;
        doc.text(
          `IMPORTE CON LETRA: ${document.getElementById("importeLetra").value}`,
          20,
          finalY
        );
        doc.text(
          `TOTAL $${document
            .getElementById("totalGeneral")
            .textContent.substring(1)}`,
          20,
          finalY + 5
        );

        // Información de pago
        doc.text(
          `Tipo de pago: ${document
            .getElementById("tipoPago")
            .value.toUpperCase()}`,
          20,
          finalY + 15
        );
        doc.text(
          `Método de pago: ${document
            .getElementById("metodoPago")
            .value.toUpperCase()}`,
          20,
          finalY + 20
        );
        if (document.getElementById("anticipo").value > 0) {
          doc.text(
            `Anticipo: $${parseFloat(
              document.getElementById("anticipo").value
            ).toFixed(2)}`,
            20,
            finalY + 25
          );
        }

        // Observaciones
        if (document.getElementById("observaciones").value) {
          doc.text(
            `Observaciones: ${document.getElementById("observaciones").value}`,
            20,
            finalY + 35,
            { maxWidth: 170 }
          );
        }

        // Pagaré (segunda página)
        if (document.getElementById("tipoPago").value === "credito") {
          doc.addPage();
          doc.setFontSize(12);
          doc.text("PAGARÉ", 105, 20, { align: "center" });
          doc.text(
            `No. ${document.getElementById("numeroPagare").value}`,
            105,
            25,
            { align: "center" }
          );

          doc.setFontSize(10);
          doc.text(
            `Bueno por: $${document
              .getElementById("totalGeneral")
              .textContent.substring(1)}`,
            20,
            35
          );

          const fechaVencimiento = new Date(
            document.getElementById("fechaVencimiento").value
          ).toLocaleDateString("es-MX");
          const lugarPago = document.getElementById("lugarPago").value;
          const importeLetra = document.getElementById("importeLetra").value;

          const pagareText1 = `Por este PAGARÉ me obligo incondicionalmente a pagar a la orden de ING. SILVESTRE PEREZ PEREZ en ${lugarPago} el día ${fechaVencimiento}, la cantidad de ${importeLetra}.`;
          const pagareText2 = `El pago deberá efectuarse en el domicilio del beneficiario ubicado en CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA, TENANCINGO, EDO. DE MEXICA.`;
          const pagareText3 = `Este documento ampara la venta de plantas in vitro que he recibido a mi entera satisfacción. En caso de mora, me obligo a pagar intereses moratorios del 15% anual.`;

          doc.text(pagareText1, 20, 45, { maxWidth: 170 });
          doc.text(pagareText2, 20, 60, { maxWidth: 170 });
          doc.text(pagareText3, 20, 75, { maxWidth: 170 });

          // Firma
          doc.text(
            `Nombre: ${document.getElementById("nombreCliente").value}`,
            20,
            100
          );
          doc.text(
            `Dirección: ${document.getElementById("direccion").value}`,
            20,
            105
          );
          doc.text(
            `RFC: ${document.getElementById("rfcCliente").value}`,
            20,
            110
          );
          doc.text("Acepto de Conformidad: _________________________", 20, 120);
        }

        // Guardar PDF
        doc.save(
          `Nota_Remision_${document.getElementById("numeroRemision").value}.pdf`
        );
      }

      // Función para convertir números a letras (versión mejorada)
      function numeroALetras(numero) {
        const unidades = [
          "",
          "UN",
          "DOS",
          "TRES",
          "CUATRO",
          "CINCO",
          "SEIS",
          "SIETE",
          "OCHO",
          "NUEVE",
        ];
        const decenas = [
          "",
          "DIEZ",
          "VEINTE",
          "TREINTA",
          "CUARENTA",
          "CINCUENTA",
          "SESENTA",
          "SETENTA",
          "OCHENTA",
          "NOVENTA",
        ];
        const especiales = [
          "DIEZ",
          "ONCE",
          "DOCE",
          "TRECE",
          "CATORCE",
          "QUINCE",
          "DIECISEIS",
          "DIECISIETE",
          "DIECIOCHO",
          "DIECINUEVE",
        ];
        const centenas = [
          "",
          "CIENTO",
          "DOSCIENTOS",
          "TRESCIENTOS",
          "CUATROCIENTOS",
          "QUINIENTOS",
          "SEISCIENTOS",
          "SETECIENTOS",
          "OCHOCIENTOS",
          "NOVECIENTOS",
        ];
      }
    </script>
  </body>
</html>
