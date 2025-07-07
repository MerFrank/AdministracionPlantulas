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
      <button
        class="btn btn-success ms-2"
        data-bs-toggle="modal"
        data-bs-target="#newPaymentModal"
      >
        <i class="bi bi-plus-circle"></i> Registrar Pago
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
              <tr data-id="<?= $nota['id_notaPedido'] ?>">
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
  <div
    class="modal fade"
    id="paymentDetailsModal"
    tabindex="-1"
    aria-labelledby="paymentDetailsModalLabel"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="paymentDetailsModalLabel">
          </h5>
          <button
            type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <div class="row mb-4">
            <div class="col-md-6">
              <h6>Información del Cliente</h6>
              <p><strong>Nombre:</strong></p>
              <p><strong>Teléfono:</strong></p>
              <p>
                <strong>Dirección:</strong></p>
            </div>
            <div class="col-md-6">
              <h6>Información de la Venta</h6>
              <p><strong>Fecha:</strong> </p>
              <p><strong>Total:</strong> </p>
              <p><strong>Abonado:</strong></p>
              <p><strong>Saldo:</strong></p>
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
                <?php foreach ($notasAgrupadas as $notas):
                 $estadoClase = ($nota['saldo_pendiente'] <= 0) ? 'status-completed' : 'status-pending';
                 $estadoTexto = ($nota['saldo_pendiente'] <= 0) ? 'Completado' : 'Pendiente';
                 $metodoClase = ($nota['metodo_pago'] == 'transfer') ? 'payment-transfer' : 'payment-cash';
                 $metodoTexto = ($nota['metodo_pago'] == 'transfer') ? 'Transferencia' : 'Efectivo';
                ?>
                <tr data-id="<?= $nota['id_notaPedido']?>">
                  <a href=""></a>
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
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal"
          >
            Cerrar
          </button>
          <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#newPaymentModal"
            data-bs-dismiss="modal"
          >
            Registrar Nuevo Pago
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Registrar Nuevo Pago -->
  <div
    class="modal fade"
    id="newPaymentModal"
    tabindex="-1"
    aria-labelledby="newPaymentModalLabel"
    aria-hidden="true"
  >
  <form action="" method="POST">
    <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="newPaymentModalLabel">
              Registrar Nuevo Pago
            </h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <form id="paymentForm">
              <div class="mb-3">
                <label for="saleNumber" class="form-label"
                  >Número de Venta</label
                >
                <input
                  type="text"
                  class="form-control"
                  id="saleNumber"
                  readonly
                />
              </div>
              <div class="mb-3">
                <label for="saleNumber" class="form-label"
                  >Folio de anticipo</label
                >
                <input
                  type="text"
                  class="form-control"
                  id="saleNumber"
                  readonly
                />
              </div>
              <div class="mb-3">
                <label for="clientName" class="form-label">Cliente</label>
                <input
                  type="text"
                  class="form-control"
                  id="clientName"
                  readonly
                />
              </div>
              <div class="mb-3">
                <label for="paymentDate" class="form-label"
                  >Fecha de Pago</label
                >
                <input
                  type="date"
                  class="form-control"
                  id="paymentDate"
                  required
                />
              </div>
              <div class="mb-3">
                <label for="paymentAmount" class="form-label"
                  >Monto del Pago</label
                >
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input
                    type="number"
                    class="form-control"
                    id="paymentAmount"
                    min="1"
                    step="0.01"
                    required
                  />
                </div>
                <small class="text-muted"></small>
              </div>
              <div class="mb-3">
                <label for="paymentMethod" class="form-label"
                  >Método de Pago</label
                >
                <select class="form-select" id="paymentMethod" required>
                  <option value="">Seleccionar...</option>
                  <option value="cash">Efectivo</option>
                  <option value="transfer">Transferencia Bancaria</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="paymentComments" class="form-label"
                  >Comentarios (Opcional)</label
                >
                <textarea
                  class="form-control"
                  id="paymentComments"
                  rows="2"
                ></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancelar
            </button>
            <button type="button" class="btn btn-success" id="savePayment">
              Guardar Pago
            </button>
          </div>
        </div>
      </div>
  
    </div>
  </form>  

</main>
<?php require('../../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar clic en botón de ver detalles
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const notaId = this.getAttribute('data-id');
            cargarDetallesNota(notaId);
        });
    });
    
    // Manejar clic en botón de nuevo pago
    document.querySelectorAll('.new-payment').forEach(button => {
        button.addEventListener('click', function() {
            const notaId = this.getAttribute('data-id');
            document.getElementById('saleNumber').value = notaId;
        });
    });
    
    // Función para cargar detalles de una nota específica
    function cargarDetallesNota(notaId) {
        fetch(`obtener_detalles_nota.php?id=${notaId}`)
            .then(response => response.json())
            .then(data => {
                // Actualizar modal de detalles
                document.getElementById('paymentDetailsModalLabel').textContent = `Detalles de Venta #${data.nota.id_notaPedido}`;
                
                // Información del cliente
                document.querySelector('#paymentDetailsModal .modal-body p:nth-child(1)').innerHTML = `<strong>Nombre:</strong> ${data.nota.nombre_cliente}`;
                // Agrega más campos según sea necesario
                
                // Información de la venta
                document.querySelector('#paymentDetailsModal .modal-body p:nth-child(4)').innerHTML = `<strong>Fecha:</strong> ${new Date(data.nota.fechaPedido).toLocaleDateString()}`;
                document.querySelector('#paymentDetailsModal .modal-body p:nth-child(5)').innerHTML = `<strong>Total:</strong> $${data.nota.monto_total.toFixed(2)}`;
                document.querySelector('#paymentDetailsModal .modal-body p:nth-child(6)').innerHTML = `<strong>Abonado:</strong> $${data.nota.total_abonado.toFixed(2)}`;
                document.querySelector('#paymentDetailsModal .modal-body p:nth-child(7)').innerHTML = `<strong>Saldo:</strong> $${data.nota.saldo_pendiente.toFixed(2)}`;
                
                // Historial de pagos
                const tbody = document.querySelector('#paymentDetailsModal tbody');
                tbody.innerHTML = '';
                data.pagos.forEach(pago => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${new Date(pago.fecha_pago).toLocaleDateString()}</td>
                        <td>$${pago.monto_pago.toFixed(2)}</td>
                        <td><span class="payment-method ${pago.metodo_pago === 'transfer' ? 'payment-transfer' : 'payment-cash'}">
                            ${pago.metodo_pago === 'transfer' ? 'Transferencia' : 'Efectivo'}
                        </span></td>
                        <td>${pago.recibido_por || 'N/A'}</td>
                        <td>${pago.comentarios || ''}</td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }
    
    // Manejar búsqueda
    document.getElementById('searchButton').addEventListener('click', function() {
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
});
</script>