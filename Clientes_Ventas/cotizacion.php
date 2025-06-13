<?php
include __DIR__ . '/../db/config.php';

// Obtener clientes para el select
$db = new Database();
$con = $db->conectar(); $sql = $con->prepare("SELECT id_clientes, nombre_Cliente
FROM Clientes"); $sql->execute(); $clientes = $sql->fetchAll(PDO::FETCH_ASSOC);
?>

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
            <h2>Cotización</h2>
            <p>Ofrece una propuesta a tus clientes</p>
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
          <form id="cotizacionForm" method="post" action="">
            <h2>Crear Cotización</h2>

            <div class="mb-3">
              <label for="id_clientes" class="form-label">Cliente</label>
              <select
                class="form-select"
                id="id_clientes"
                name="id_clientes"
                required
              >
                <option value="">Seleccione un Cliente</option>
                <?php foreach ($clientes as $row): ?>
                <option value="<?php echo $row['id_clientes'] ?>">
                  <?php echo $row['nombre_Cliente'] ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

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

        <script></script>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
