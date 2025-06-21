<?php
include __DIR__ . '/../db/config.php';

$db = new Database();
$con = $db->conectar();

// Obtener clientes
$sql_clientes = $con->prepare("SELECT id_cliente, nombre_Cliente FROM Clientes");
$sql_clientes->execute();
$clientes = $sql_clientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener especies
$sql_especies = $con->prepare("SELECT id_especie, nombre FROM Especies");
$sql_especies->execute();
$especies = $sql_especies->fetchAll(PDO::FETCH_ASSOC);

// Manejar la solicitud AJAX para obtener datos del cliente
if (isset($_GET['ajax_request']) && isset($_GET['id_cliente'])) {
    header('Content-Type: application/json');
    
    try {
        $cliente_id = (int)$_GET['id_cliente'];
        $sql = $con->prepare("SELECT nombre_Cliente, rfc, telefono, domicilio_fiscal FROM Clientes WHERE id_cliente = ?");
        $sql->execute([$cliente_id]);
        $cliente = $sql->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente) {
            echo json_encode($cliente, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Cliente no encontrado']);
        }
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener lista de clientes para el select
$clientes = [];
try {
    $sql = $con->query("SELECT id_cliente, nombre_Cliente FROM Clientes ORDER BY nombre_Cliente");
    $clientes = $sql->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_clientes = "Error al cargar la lista de clientes: " . $e->getMessage();
}


// Solicitud AJAX para obtener Colores
if (isset($_GET['especie_id'])) { 
    $especie_id = $_GET['especie_id'];
    
    $sql_colores = $con->prepare("SELECT 
    id_color,
    nombre_color
    FROM Colores
    WHERE id_especie = :especie_id
    ORDER BY nombre_color");

    $sql_colores->execute([':especie_id' => $especie_id]);
    $colores = $sql_colores->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($colores);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  // Validar y limpiar datos
  $num_remision = htmlspecialchars(trim($_POST['num_remision']));
  $fechaPedido = htmlspecialchars(trim($_POST['fechaPedido']));
  $id_cliente = htmlspecialchars(trim($_POST['id_cliente']));
  $tipo_pago = htmlspecialchars(trim($_POST['tipo_pago']));
  $metodo_Pago = htmlspecialchars(trim($_POST['metodo_Pago']));
  $importe_letra = htmlspecialchars(trim($_POST['importe_letra']));
  $observaciones = htmlspecialchars(trim($_POST['observaciones']));
  $num_pagare = htmlspecialchars(trim($_POST['num_pagare']));
  $fecha_validez = htmlspecialchars(trim($_POST['fecha_validez']));
  $fecha_entrega = htmlspecialchars(trim($_POST['fecha_entrega']));
  $lugar_pago = htmlspecialchars(trim($_POST['lugar_pago']));

  $db = new Database();
  $conexion = $db->conectar();

  try {
    $sql = "INSERT INTO NotasPedidos (num_remision, fechaPedido, id_cliente, tipo_pago, metodo_Pago, importe_letra, observaciones, num_pagare, fecha_validez, fecha_entrega, lugar_pago)
            VALUES (:num_remision, :fechaPedido, :id_cliente, :tipo_pago, :metodo_Pago, :importe_letra, :observaciones, :num_pagare, :fecha_validez, :fecha_entrega, :lugar_pago)";

    $stmt = $conexion->prepare($sql);

    $stmt->bindParam(':num_remision', $num_remision);
    $stmt->bindParam(':fechaPedido', $fechaPedido);
    $stmt->bindParam(':id_cliente', $id_cliente);
    $stmt->bindParam(':tipo_pago', $tipo_pago);
    $stmt->bindParam(':metodo_Pago', $metodo_Pago);
    $stmt->bindParam(':importe_letra', $importe_letra);
    $stmt->bindParam(':observaciones', $observaciones);
    $stmt->bindParam(':num_pagare', $num_pagare);
    $stmt->bindParam(':fecha_validez', $fecha_validez);
    $stmt->bindParam(':fecha_entrega', $fecha_entrega);
    $stmt->bindParam(':lugar_pago', $lugar_pago);

    if ($stmt->execute()) {
      echo "<script>alert('Datos ingresados correctamente'); window.location.href='notas_pedido.php';</script>";
    } else {
      $errorInfo = $stmt->errorInfo();
      echo "<script>alert('Error al ingresar los datos: " . addslashes($errorInfo[2]) . "'); window.location.href='notas_pedido.php';</script>";
    }

  } catch(PDOException $e) {
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='notas_pedido.php';</script>";
  }
}
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                      name="numeroRemision"
                      value=""
                      required
                    />
                  </div>
                  <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input
                      type="date"
                      class="form-control"
                      id="fecha"
                      name="fecha"
                      required
                    />
                  </div>
                  <div class="col-md-6">
                    <label for="cotizacion" class="form-label"
                      >Cotización Relacionada</label
                    >
                    <select class="form-select" id="cotizacion">
                      <option value="">-- Seleccione una cotización --</option>
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
                    <label for="id_clientes" class="form-label">Cliente</label>
                    <select
                      class="form-select"
                      id="id_cliente"
                      name="id_cliente"
                      required
                    >
                      <option value="">Seleccione un Cliente</option>
                      <?php foreach ($clientes as $row): ?>
                      <option value="<?php echo $row['id_cliente'] ?>">
                        <?php echo $row['nombre_Cliente'] ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="rfcCliente" class="form-label">RFC</label>
                    <input
                      type="text"
                      class="form-control"
                      id="rfc"
                      name="rfc"
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
                      id="nombre_Cliente"
                      name="nombre_Cliente"
                      required
                    />
                  </div>
                  <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input
                      type="tel"
                      class="form-control"
                      id="telefono"
                      name="telefono"
                    />
                  </div>
                  <div class="col-md-12">
                    <label for="direccion" class="form-label">Dirección</label>
                    <textarea
                      class="form-control"
                      id="domicilio_fiscal"
                      name="domicilio_fiscal"
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
                    <select
                      name="especie"
                      id="especie"
                      class="form-select"
                      required
                    >
                      <option value="">Seleccione una Especie</option>
                      <?php foreach ($especies as $row): ?>
                      <option value="<?php echo $row['id_especie'] ?>">
                        <?php echo $row['nombre'] ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="Color" class="form-label">Color</label>
                    <select
                      class="form-select"
                      id="color"
                      name="color"
                      required
                    >
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
                        <th>Color/Especie</th>
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
                    <input type="text" class="form-control" id="numeroPagare" />
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
                      value=""
                      placeholder="Tenancingo, México"
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
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      $(document).ready(function () {
        //Completar datos del cliente seleccionado
        $("#id_cliente").change(function () {
          var clienteId = $(this).val();

          if (clienteId) {
            $.ajax({
              url: window.location.pathname + "?ajax_request=1",
              type: "GET",
              data: { id_cliente: clienteId },
              dataType: "json",
              success: function (data) {
                console.log("Respuesta recibida:", data);

                if (data && !data.error) {
                  $("#nombre_Cliente").val(data.nombre_Cliente || "");
                  $("#rfc").val(data.rfc || "");
                  $("#telefono").val(data.telefono || "");
                  $("#domicilio_fiscal").val(data.domicilio_fiscal || "");
                } else {
                  console.error("Error del servidor:", data.error);
                  alert("Error: " + (data.error || "Datos no válidos"));
                }
              },
              error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error, xhr.responseText);
                alert("Error en la comunicación con el servidor");
              },
            });
          } else {
            // Limpiar campos si no hay cliente seleccionado
            $("#nombre_Cliente, #rfc, #telefono, #domicilio_fiscal").val("");
          }
        });
        // Habilitar colores cuando se selecciona una especie
        $("#especie").change(function () {
          var especieId = $(this).val();
          var $colorSelect = $("#color"); // Cambiado a #Color para coincidir con tu HTML

          if (especieId) {
            // Habilitar el select de color
            $colorSelect.prop("disabled", false);

            // Limpiar opciones anteriores
            $colorSelect
              .empty()
              .append('<option value="">Cargando colores...</option>');

            // Hacer petición AJAX para obtener colores
            $.ajax({
              url: window.location.href,
              data: { especie_id: especieId },
              dataType: "json",
              success: function (data) {
                $colorSelect.empty();
                if (data.length > 0) {
                  $colorSelect.append(
                    '<option value="">Seleccione un color</option>'
                  );
                  $.each(data, function (key, color) {
                    $colorSelect.append(
                      $("<option></option>")
                        .attr("value", color.id_color)
                        .text(color.nombre_color)
                    );
                  });
                } else {
                  $colorSelect.append(
                    '<option value="">No hay colores para esta especie</option>'
                  );
                }
              },
              error: function () {
                $colorSelect
                  .empty()
                  .append('<option value="">Error al cargar colores</option>');
              },
            });
          } else {
            // Deshabilitar si no hay especie seleccionada
            $colorSelect
              .prop("disabled", true)
              .empty()
              .append(
                '<option value="">-- Primero seleccione una especie --</option>'
              );
          }
        });

        function eliminarProducto(index) {
          productos.splice(index, 1);
          actualizarTablaProductos();
        }

        // Limpiar formulario
        $("#limpiarForm").on("click", function () {
          if (!confirm("¿Está seguro de que desea limpiar todo el formulario?")) return;

          $("#numeroRemision").val("");
          $("#fecha").val("");
          $("#cotizacion").val("");
          $("#id_cliente").val("");
          $("#nombre_Cliente, #telefono, #rfc, #domicilio_fiscal").val("");
          $("#especie, #color").val("");
          $("#cantidad").val("1");
          $("#costo").val("");
          $("#tipoPago").val("contado");
          $("#metodoPago").val("efectivo");
          $("#anticipo").val("0");
          $("#importeLetra").val("");
          $("#observaciones").val("");
          $("#numeroPagare").val("");
          $("#fechaVencimiento").val("");
          $("#lugarPago").val("Tenancingo, México");
          productos = [];
          actualizarTablaProductos();
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
          doc.text(
            "CALLE 16 DE SEPTIEMBRE S/N. COL. EMILIANO ZAPATA",
            105,
            27,
            {
              align: "center",
            }
          );
          doc.text("TENANCINGO, EDO. DE MEXICA 52433", 105, 32, {
            align: "center",
          });
          doc.text(
            "CELS.: 7222041444  E-mail: plantasdoc@hotmail.com",
            105,
            37,
            {
              align: "center",
            }
          );

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
          doc.text(
            `RFC: ${document.getElementById("rfcCliente").value}`,
            20,
            90
          );

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
              ["COLOR Y/O ESPECIE", "CANTIDAD", "COSTO POR PLANTA", "SUBTOTAL"],
            ],
            body: plantasData,
            margin: { left: 10 },
            styles: { fontSize: 8 },
            headStyles: { fillColor: [40, 167, 69] },
          });

          // Total
          const finalY = doc.lastAutoTable.finalY + 10;
          doc.text(
            `IMPORTE CON LETRA: ${
              document.getElementById("importeLetra").value
            }`,
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
              `Observaciones: ${
                document.getElementById("observaciones").value
              }`,
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
            doc.text(
              "Acepto de Conformidad: _________________________",
              20,
              120
            );
          }

          // Guardar PDF
          doc.save(
            `Nota_Remision_${
              document.getElementById("numeroRemision").value
            }.pdf`
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
        $("#generarPDF").on("click", function () {
          if (typeof generarPDF === "function") {
            generarPDF();
          } else {
            alert(
              "La función generarPDF no está definida o no ha sido cargada correctamente."
            );
          }
        });
        
        let productos = [];

        function eliminarProducto(index) {
          productos.splice(index, 1);
          actualizarTablaProductos();
        }

        // Agregar planta a la tabla
        $("#agregarProducto").on("click", function () {
          const especieText = $("#especie option:selected").text();
          const colorText = $("#color option:selected").text();
          const especie = $("#especie").val();
          const color = $("#color").val();
          const cantidad = parseInt($("#cantidad").val());
          const costo = parseFloat($("#costo").val());

          if (!especie || !color || isNaN(cantidad) || isNaN(costo)) {
            alert("Por favor complete todos los campos de planta correctamente.");
            return;
          }

          const descripcion = `${colorText} / ${especieText}`;
          const subtotal = cantidad * costo;

          productos.push({ descripcion, cantidad, costo, subtotal });
          actualizarTablaProductos();
        });

        function actualizarTablaProductos() {
          const $tabla = $("#tablaProductos");
          const $tbody = $tabla.find("tbody");
          $tbody.empty();

          let total = 0;
          productos.forEach((prod, index) => {
            total += prod.subtotal;
            $tbody.append(`
              <tr>
                <td>${prod.descripcion}</td>
                <td>${prod.cantidad}</td>
                <td>$${prod.costo.toFixed(2)}</td>
                <td>$${prod.subtotal.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})"><i class="bi bi-trash"></i></button></td>
              </tr>
            `);
          });

          $("#totalGeneral").text(`$${total.toFixed(2)}`);
          $tabla.toggle(productos.length > 0);
        }

        document.getElementById("generarPDF").addEventListener("click", async () => {
          const { jsPDF } = window.jspdf;
          const doc = new jsPDF();
          doc.text("NOTA DE REMISIÓN", 10, 10);
          doc.autoTable({
            head: [["Color/Especie", "Cantidad", "Precio Unitario", "Subtotal"]],
            body: productos.map(p => [p.descripcion, p.cantidad, `$${p.costo.toFixed(2)}`, `$${p.subtotal.toFixed(2)}`])
          });
          doc.save("nota_remision.pdf");
        });
      });
    </script>
  </body>
</html>
