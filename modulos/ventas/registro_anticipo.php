<?php
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$conexion = $db->conectar();

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Ejecutar la consulta SQL
$sql = "SELECT
    np.id_notaPedido,
    np.id_cliente,
    c.nombre_Cliente AS nombre_cliente,
    np.fechaPedido,
    dnp.monto_total,
    COALESCE(SUM(sa.monto_pago), 0) AS total_abonado,
    (dnp.monto_total - COALESCE(SUM(sa.monto_pago), 0)) AS saldo_pendiente,
    MAX(sa.fecha_pago) AS ultima_fecha_pago,
    CASE 
        WHEN COALESCE(SUM(sa.monto_pago), 0) >= dnp.monto_total THEN 'Pagado completo'
        WHEN COALESCE(SUM(sa.monto_pago), 0) > 0 THEN 'Pago parcial'
        ELSE 'Pendiente de pago'
    END AS estado_pago
FROM notaspedidos np
JOIN detallesnotapedido dnp ON np.id_notaPedido = dnp.id_notaPedido
JOIN clientes c ON np.id_cliente = c.id_cliente
LEFT JOIN seguimientoanticipos sa ON np.id_notaPedido = sa.numero_venta
GROUP BY np.id_notaPedido, np.id_cliente, c.nombre_Cliente, np.fechaPedido, dnp.monto_total
ORDER BY np.id_notaPedido";

$stmt = $conexion->prepare($sql);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar resultados por nota de pedido para la vista principal
$notasAgrupadas = [];
foreach ($resultados as $row) {
    $id = $row['id_notaPedido'];
    if (!isset($notasAgrupadas[$id])) {
        $notasAgrupadas[$id] = $row;
    }
    // Actualizar con los últimos valores (ya que están ordenados por fecha)
    $notasAgrupadas[$id] = $row;
}


// Variables para el encabezado
$titulo = "Seguimiento de Créditos";
$encabezado = "Registro de Pagos";
$subtitulo = "Seguimiento de Pagos a Crédito";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "dashboard_clientesVentas.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>
<main>
  <div class="container mt-4">
    <div class="search-container mb-4">
      <div class="row">
        <div class="col-md-4">
          <div class="mb-3">
            <label for="searchClient" class="form-label"
              >Buscar por cliente:</label
            >
            <input
              type="text"
              class="form-control"
              id="searchClient"
              placeholder="Nombre del cliente"
            />
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="searchSale" class="form-label"
              >Buscar por número de venta:</label
            >
            <input
              type="text"
              class="form-control"
              id="searchSale"
              placeholder="N° de venta"
            />
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="filterStatus" class="form-label"
              >Filtrar por estado:</label
            >
            <select class="form-select" id="filterStatus">
              <option value="all">Todos</option>
              <option value="pending">Pendientes</option>
              <option value="completed">Completados</option>
            </select>
          </div>
        </div>
      </div>
      <button class="btn btn-primary" id="searchButton">
        <i class="bi bi-search"></i> Buscar
      </button>
    </div>

    <div class="card">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
          <i class="bi bi-credit-card"></i> Historial de Pagos a Crédito
        </h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover " id="creditosTable">
            <thead>
              <tr>
                <th>N° Venta</th>
                <th>Cliente</th>
                <th>Fecha Venta</th>
                <th>Total Venta</th>
                <th>Monto Abonado</th>
                <th>Saldo Pendiente</th>
                <th>Último Pago</th>
                <th>Método</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($notasAgrupadas)): ?>
              <tr>
                  <td colspan="8" class="text-center py-4">
                      <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                      <p class="mt-2">No se encontraron pagos <?= isset($_GET['busqueda']) && !empty($_GET['busqueda']) ? 'con el criterio de búsqueda' : '' ?></p>
                  </td> 
              </tr>
          <?php else: ?>
            <?php foreach ($notasAgrupadas as $nota): 
                $estadoClase = ($nota['saldo_pendiente'] <= 0) ? 'status-completed' : 'status-pending';
                $estadoTexto = ($nota['saldo_pendiente'] <= 0) ? 'Completado' : 'Pendiente';
                $metodoClase = ($nota['metodo_pago'] == 'transfer') ? 'payment-transfer' : 'payment-cash';
                $metodoTexto = ($nota['metodo_pago'] == 'transfer') ? 'Transferencia' : 'Efectivo'; ?>
              <tr data-id="<?= $nota['id_notaPedido'] ?>" data-idcliente="<?= $nota['id_cliente'] ?>">
                  <td><?= htmlspecialchars($nota['id_notaPedido']) ?></td>
                  <td><?= htmlspecialchars($nota['nombre_cliente']) ?></td>
                  <td><?= date('d/m/Y', strtotime($nota['fechaPedido'])) ?></td>
                  <td>$<?= number_format($nota['monto_total'], 2) ?></td>
                  <td>$<?= number_format($nota['total_abonado'], 2) ?></td>
                  <td>$<?= number_format($nota['saldo_pendiente'], 2) ?></td>
                  <td><?= date('d/m/Y', strtotime($nota['fecha_pago'])) ?></td>
                  <td>
                      <span class="payment-method <?= $metodoClase ?>">
                          <?= $metodoTexto ?>
                      </span>
                  </td>
                  <td>
                      <span class="status-badge <?= $estadoClase ?>">
                          <?= $estadoTexto ?>
                      </span>
                  </td>
                  <td>
                      <button class="btn btn-sm btn-primary view-details" 
                              data-bs-toggle="modal" 
                              data-bs-target="#paymentDetailsModal"
                              data-id="<?= $nota['id_notaPedido'] ?>">
                          <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-success new-payment"
                              data-bs-toggle="modal"
                              data-bs-target="#newPaymentModal"
                              data-id="<?= $nota['id_notaPedido'] ?>">
                          <i class="bi bi-cash-coin"></i>
                      </button>
                  </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<!-- Modal Detalles de Pagos -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="paymentDetailsModalLabel">Detalles de Venta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-4">
          <div class="col-md-6">
            <!-- Información del cliente se cargará aquí -->
          </div>
          <div class="col-md-6">
            <!-- Información de la venta se cargará aquí -->
          </div>
        </div>

        <h6 class="mb-3">Historial de Pagos</h6>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Método</th>
                <th>Recibido por</th>
                <th>Comentarios</th>
              </tr>
            </thead>
            <tbody>
              <!-- Historial de pagos se cargará aquí -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" 
                data-bs-target="#newPaymentModal" data-bs-dismiss="modal">
          Registrar Nuevo Pago
        </button>
      </div>
    </div>
  </div>
</div>

  <!-- Modal Registrar Nuevo Pago -->
  <div class="modal fade" id="newPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Registrar Nuevo Pago</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="paymentForm">
            <div class="mb-3">
              <label for="folioAnticipo" class="form-label">Folio Anticipo <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="folioAnticipo" required>
            </div>  
            <div class="mb-3">
            <label for="saleNumber" class="form-label">Número de Venta</label>
            <input type="text" class="form-control" id="saleNumber" readonly>
          </div>
          <div class="mb-3">
            <label for="clientName" class="form-label">Cliente</label>
            <input type="text" class="form-control" id="clientName" readonly>
            <input type="hidden" id="clienteId">
          </div>
            <div class="mb-3">
              <label for="paymentDate" class="form-label">Fecha de Pago <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="paymentDate" required>
            </div>
            <div class="mb-3">
              <label for="paymentAmount" class="form-label">Monto del Pago <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="paymentAmount" 
                      min="0.01" step="0.01" required>
              </div>
              <small class="text-muted"></small>
            </div>
            <div class="mb-3">
              <label for="paymentMethod" class="form-label">Método de Pago <span class="text-danger">*</span></label>
              <select class="form-select" id="paymentMethod" required>
                <option value="">Seleccionar...</option>
                <option value="cash">Efectivo</option>
                <option value="transfer">Transferencia Bancaria</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="paymentComments" class="form-label">Comentarios (Opcional)</label>
              <textarea class="form-control" id="paymentComments" rows="2"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-success" id="savePayment">
            <i class="bi bi-save"></i> Guardar Pago
          </button>
        </div>
      </div>
    </div>
  </div>

</main>
<?php require('../../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // 🔍 Buscar por cliente, venta y estado
  document.getElementById('searchButton').addEventListener('click', function () {
    const cliente = document.getElementById('searchClient').value.toLowerCase();
    const venta = document.getElementById('searchSale').value.toLowerCase();
    const estado = document.getElementById('filterStatus').value;

    document.querySelectorAll('#creditosTable tbody tr').forEach(row => {
      const textCliente = row.cells[1].textContent.toLowerCase();
      const textVenta = row.cells[0].textContent.toLowerCase();
      const textEstado = row.cells[8].textContent.toLowerCase();

      const matchCliente = textCliente.includes(cliente);
      const matchVenta = textVenta.includes(venta);
      const matchEstado = (estado === 'all') ||
        (estado === 'pending' && textEstado.includes('pendiente')) ||
        (estado === 'completed' && textEstado.includes('completado'));

      row.style.display = (matchCliente && matchVenta && matchEstado) ? '' : 'none';
    });
  });

  // 👁️ Cargar detalles en modal
  document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function () {
      const notaId = this.getAttribute('data-id');
      fetch(`obtener_detalles_nota.php?id=${notaId}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) throw new Error(data.error);

          // Título
          document.getElementById('paymentDetailsModalLabel').textContent =
            `Detalles de Venta #${data.nota.id_notaPedido}`;

          // Cliente
          document.querySelector('#paymentDetailsModal .col-md-6:first-child').innerHTML = `
            <h6>Información del Cliente</h6>
            <p><strong>Nombre:</strong> ${data.nota.nombre_Cliente}</p>
            <p><strong>Teléfono:</strong> ${data.cliente.telefono || 'No disponible'}</p>
            <p><strong>Dirección:</strong> ${data.cliente.domicilio_fiscal || 'No disponible'}</p>
          `;

          // Venta
          document.querySelector('#paymentDetailsModal .col-md-6:last-child').innerHTML = `
            <h6>Información de la Venta</h6>
            <p><strong>Fecha:</strong> ${new Date(data.nota.fechaPedido).toLocaleDateString()}</p>
            <p><strong>Total:</strong> $${parseFloat(data.nota.monto_total).toFixed(2)}</p>
            <p><strong>Abonado:</strong> $${parseFloat(data.nota.total_abonado).toFixed(2)}</p>
            <p><strong>Saldo:</strong> $${parseFloat(data.nota.saldo_pendiente).toFixed(2)}</p>
          `;

          // Historial
          const tbody = document.querySelector('#paymentDetailsModal tbody');
          tbody.innerHTML = '';

          if (data.pagos.length === 0) {
            tbody.innerHTML = `
              <tr><td colspan="5" class="text-center py-3 text-muted">No se han registrado pagos</td></tr>
            `;
          } else {
            data.pagos.forEach(pago => {
              tbody.innerHTML += `
                <tr>
                  <td>${new Date(pago.fecha_pago).toLocaleDateString()}</td>
                  <td>$${parseFloat(pago.monto_pago).toFixed(2)}</td>
                  <td>${pago.metodo_pago === 'transfer' ? 'Transferencia' : 'Efectivo'}</td>
                  <td>${pago.recibido_por || 'N/A'}</td>
                  <td>${pago.comentarios || ''}</td>
                </tr>
              `;
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error al cargar los detalles: ' + error.message);
        });
    });
  });

  // 💾 Guardar nuevo pago
  document.getElementById('savePayment').addEventListener('click', function () {
    const notaId = document.getElementById('saleNumber').value;
    const fechaPago = document.getElementById('paymentDate').value;
    const montoPago = parseFloat(document.getElementById('paymentAmount').value);
    const metodoPago = document.getElementById('paymentMethod').value;
    const comentarios = document.getElementById('paymentComments').value;
    const folioAnticipo = document.getElementById('folioAnticipo').value;
    const clienteId = document.getElementById('clienteId').value;


    if (!notaId || !fechaPago || !montoPago || !metodoPago) {
      alert('Por favor complete todos los campos obligatorios');
      return;
    }

    if (montoPago <= 0) {
      alert('El monto debe ser mayor a cero');
      return;
    }

    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
    this.disabled = true;

    fetch('registrar_pago.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        folio_anticipo: folioAnticipo,
        nota_id: notaId,
        fecha_pago: fechaPago,
        monto_pago: montoPago,
        metodo_pago: metodoPago,
        comentarios: comentarios,
        id_cliente: clienteId
      })
    })
      .then(response => response.json())
      .then(data => {
        if (data.error) throw new Error(data.error);

        bootstrap.Modal.getInstance(document.getElementById('newPaymentModal')).hide();
        alert('Pago registrado correctamente');
        location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al registrar el pago: ' + error.message);
      })
      .finally(() => {
        this.innerHTML = '<i class="bi bi-save"></i> Guardar Pago';
        this.disabled = false;
      });
  });

  // 🟢 Configurar datos al abrir el modal de nuevo pago
  document.getElementById('newPaymentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const notaId = button.getAttribute('data-id');

    const fila = document.querySelector(`tr[data-id="${notaId}"]`);
    if (!fila) return;

    const numeroVenta = fila.cells[0].textContent.trim();
    const nombreCliente = fila.cells[1].textContent.trim();
    const saldoText = fila.cells[5].textContent.replace('$', '').replace(',', '').trim();
    const saldoPendiente = parseFloat(saldoText) || 0;

    document.getElementById('saleNumber').value = numeroVenta;
    document.getElementById('clientName').value = nombreCliente;

    const small = document.querySelector('#newPaymentModal small.text-muted');
    if (small) small.textContent = `Saldo pendiente: $${saldoPendiente.toFixed(2)}`;

    const amountInput = document.getElementById('paymentAmount');
    if (amountInput) {
      amountInput.max = saldoPendiente;
      amountInput.setAttribute('max', saldoPendiente);
    }

    document.getElementById('paymentDate').valueAsDate = new Date();

    const idCliente = fila.getAttribute('data-idcliente');
    document.getElementById('clienteId').value = idCliente
  });

  // ✏️ Validación en tiempo real del monto
  document.getElementById('paymentAmount').addEventListener('input', function () {
    const max = parseFloat(this.max) || Infinity;
    const value = parseFloat(this.value) || 0;

    if (value > max) {
      this.setCustomValidity(`El monto no puede exceder $${max.toFixed(2)}`);
    } else {
      this.setCustomValidity('');
    }
  });

});
</script>
