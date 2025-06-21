<?php
include __DIR__ . '/../db/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y limpiar los datos de entrada
    $alias = htmlspecialchars(trim($_POST['alias']));
    $nombre_Cliente = htmlspecialchars(trim($_POST['nombre_Cliente']));
    $nombre_Empresa = htmlspecialchars(trim($_POST['nombre_Empresa']));
    $nombre_contacto = htmlspecialchars(trim($_POST['nombre_contacto']));
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

    $requiere_factura = isset($_POST['opcion']) && $_POST['opcion'] === 'si';
    $rfc = $requiere_factura ? htmlspecialchars(trim($_POST['rfc'])) : null;
    $domicilio_fiscal = $requiere_factura ? htmlspecialchars(trim($_POST['domicilio_fiscal'])) : null;

    // Validar campos requeridos
    if (empty($alias) || empty($nombre_Cliente) || empty($telefono)) {
        die("Error: Campos requeridos faltantes");
    }

    if (!$email) {
        die("Error: Email no válido");
    }

    if ($requiere_factura && (empty($rfc) || strlen($rfc) < 12)) {
        die("Error: RFC no válido o faltante.");
    }

    // Crear instancia de Database y obtener conexión PDO
    $db = new Database();
    $conexion = $db->conectar();

    try {
        // Consulta SQL 
        $sql = "INSERT INTO Clientes (alias, nombre_Cliente, nombre_Empresa, nombre_contacto, telefono, email, rfc, domicilio_fiscal)
                VALUES (:alias, :nombre_Cliente, :nombre_Empresa, :nombre_contacto, :telefono, :email, :rfc, :domicilio_fiscal)";
        
        $stmt = $conexion->prepare($sql);
        
        // Bind de parámetros con PDO especificando el tipo para NULL
        $stmt->bindParam(':alias', $alias);
        $stmt->bindParam(':nombre_Cliente', $nombre_Cliente);
        $stmt->bindParam(':nombre_Empresa', $nombre_Empresa);
        $stmt->bindParam(':nombre_contacto', $nombre_contacto);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        
        // Manejo especial para campos que pueden ser NULL
        $stmt->bindParam(':rfc', $rfc, $rfc === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':domicilio_fiscal', $domicilio_fiscal, $domicilio_fiscal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            echo "<script>alert('Datos ingresados correctamente'); window.location.href='registro_cliente.php';</script>";
        } else {
            $errorInfo = $stmt->errorInfo();
            echo "<script>alert('Error al ingresar los datos: " . addslashes($errorInfo[2]) . "'); window.location.href='registro_cliente.php';</script>";
        }
    } catch(PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='registro_cliente.php';</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de clientes</title>
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
            <h2>Registra un nuevo Cliente</h2>
            <p>Ingresa un nuevo cliente en el sistema</p>
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
        <div class="container mt-5">
          <h2>Registrar Cliente</h2>
          <form id="clienteForm" method="post" action="">
            <h5>Datos Generales</h5>
            <div class="mb-3">
              <label for="alias" class="form-label">Alias del cliente</label>
              <input type="text" class="form-control" id="alias" name="alias" />
            </div>
            <div class="mb-3">
              <label for="nombre_Cliente" class="form-label"
                >Nombre o Razón Social <span class="text-danger">*</span></label
              >
              <input
                type="text"
                class="form-control"
                id="nombre"
                name="nombre_Cliente"
                required
              />
            </div>
            <div class="mb-3">
              <label for="nombre_Empresa" class="form-label">Empresa</label>
              <input
                type="text"
                class="form-control"
                id="tipo"
                name="nombre_Empresa"
              />
            </div>

            <h5>Datos de Contacto</h5>
            <div class="mb-3">
              <label for="nombre_contacto" class="form-label"
                >Nombre del contacto</label
              >
              <input
                type="text"
                class="form-control"
                id="nombreContacto"
                name="nombre_contacto"
              />
            </div>
            <div class="mb-3">
              <label for="telefonoContacto" class="form-label"
                >Teléfono del contacto</label
              >
              <input
                type="tel"
                class="form-control"
                id="telefonoContacto"
                name="telefono"
                required
              />
            </div>
            <div class="mb-3">
              <label for="correoContacto" class="form-label"
                >Correo del contacto</label
              >
              <input
                type="email"
                class="form-control"
                id="correoContacto"
                name="email"
                required
              />
            </div>

            <hr />
            <label>¿Requiere facturación?</label>
            <div class="radio-group mb-3">
              <label for="opcion-si">Sí</label>
              <input type="radio" id="opcion-si" name="opcion" value="si" />
              <label for="opcion-no">No</label>
              <input
                type="radio"
                id="opcion-no"
                name="opcion"
                value="no"
                checked
              />
            </div>

            <div class="form-facturar" style="display: none">
              <h5>Datos Fiscales</h5>
              <div class="mb-3">
                <label for="rfc" class="form-label">RFC</label>
                <input
                  type="text"
                  class="form-control"
                  id="rfc"
                  maxlength="13"
                  name="rfc"
                />
              </div>
              <div class="mb-3">
                <label for="domicilioFiscal" class="form-label"
                  >Domicilio Fiscal</label
                >
                <textarea
                  class="form-control"
                  id="domicilioFiscal"
                  name="domicilio_fiscal"
                  rows="2"
                ></textarea>
              </div>
            </div>

            <button type="submit" class="btn btn-success">
              Guardar Cliente
            </button>
          </form>
        </div>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script>
      window.addEventListener("DOMContentLoaded", () => {
        const radioSi = document.getElementById("opcion-si");
        const radioNo = document.getElementById("opcion-no");
        const formFactura = document.querySelector(".form-facturar");

        const rfc = document.getElementById("rfc");
        const domicilioFiscal = document.getElementById("domicilioFiscal");

        function toggleFacturaFields() {
          if (radioSi.checked) {
            formFactura.style.display = "block";
            rfc.required = true;
            domicilioFiscal.required = true;
          } else {
            formFactura.style.display = "none";
            rfc.required = false;
            domicilioFiscal.required = false;
          }
        }

        radioSi.addEventListener("change", toggleFacturaFields);
        radioNo.addEventListener("change", toggleFacturaFields);
        toggleFacturaFields(); // Al cargar
      });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
