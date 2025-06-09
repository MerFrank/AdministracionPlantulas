<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de Clientes</title>
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
            <h2>Registro de clientes</h2>
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
        <h2>Registrar Cliente (con Datos Fiscales y Contacto)</h2>
        <div class="container mt-5">
          <form id="clienteForm">
            <h5>Datos Generales</h5>
            <div class="mb-3">
              <label for="alias" class="form-label">Alias del cliente</label>
              <input
                type="text"
                class="form-control"
                id="alias"
                placeholder="Nombre corto o apodo"
              />
            </div>
            <div class="mb-3">
              <label for="nombre" class="form-label"
                >Nombre o Razón Social <span class="text-danger">*</span></label
              >
              <input type="text" class="form-control" id="nombre" required />
            </div>
            <div class="mb-3">
              <label for="tipo" class="form-label">Empresa</label>
              <input
                type="text"
                class="form-control"
                id="tipo"
                placeholder="Opcional"
              />
            </div>

            <hr />
            <h5>Datos de Contacto</h5>
            <div class="mb-3">
              <label for="nombreContacto" class="form-label"
                >Nombre del contacto</label
              >
              <input
                type="text"
                class="form-control"
                id="nombreContacto"
                placeholder="Ej. Juan Pérez"
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
                placeholder="Ej. 5512345678"
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
                placeholder="contacto@ejemplo.com"
              />
            </div>
            <hr />
            <div class="facturación">
              <label>¿Requiere facturación?</label>
              <div class="radio-group">
                <div class="radio-option">
                  <label for="opcion-si">Sí</label>
                  <input type="radio" id="opcion-si" name="opcion" value="si" />
                </div>
                <div class="radio-option">
                  <label for="opcion-no">No</label>
                  <input type="radio" id="opcion-no" name="opcion" value="no" />
                </div>
              </div>
              <div class="form-facturar">
                <h5>Datos Fiscales (para facturación)</h5>

                <div class="mb-3">
                  <label for="rfc" class="form-label">RFC </label>
                  <input
                    type="text"
                    class="form-control"
                    id="rfc"
                    maxlength="13"
                    placeholder="Ej. ABCD123456XYZ"
                    required
                  />
                </div>

                <div class="mb-3">
                  <label for="domicilioFiscal" class="form-label"
                    >Domicilio Fiscal
                  </label>
                  <textarea
                    class="form-control"
                    id="domicilioFiscal"
                    rows="2"
                    placeholder="Calle, número, colonia, ciudad, estado, CP"
                    required
                  ></textarea>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-success">
              Guardar Cliente
            </button>
          </form>
        </div>

        <script>
          window.addEventListener("DOMContentLoaded", () => {
            const radioSi = document.getElementById("opcion-si");
            const radioNo = document.getElementById("opcion-no");
            const formulario = document.querySelector(".form-facturar");

            const rfc = document.getElementById("rfc");
            const domicilioFiscal = document.getElementById("domicilioFiscal");

            radioSi.addEventListener("change", () => {
              if (radioSi.checked) {
                formulario.style.display = "block";
                rfc.required = true;
                domicilioFiscal.required = true;
              }
            });

            radioNo.addEventListener("change", () => {
              if (radioNo.checked) {
                formulario.style.display = "none";
                rfc.required = false;
                domicilioFiscal.required = false;
              }
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
