<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Seguimiento de Créditos</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    />
    <style></style>
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
            <h2>Registro de Pagos</h2>
            <p>Seguimiento de Pagos a Crédito</p>
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
                <table class="table table-striped table-hover">
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
                    <tr>
                      <td>V-00125</td>
                      <td>Juan Pérez</td>
                      <td>15/05/2025</td>
                      <td>$1,250.00</td>
                      <td>$750.00</td>
                      <td>$500.00</td>
                      <td>25/05/2025</td>
                      <td>
                        <span class="payment-method payment-cash"
                          >Efectivo</span
                        >
                      </td>
                      <td>
                        <span class="status-badge status-pending"
                          >Pendiente</span
                        >
                      </td>
                      <td>
                        <button
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#paymentDetailsModal"
                        >
                          <i class="bi bi-eye"></i>
                        </button>
                        <button
                          class="btn btn-sm btn-success"
                          data-bs-toggle="modal"
                          data-bs-target="#newPaymentModal"
                        >
                          <i class="bi bi-cash-coin"></i>
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>V-00118</td>
                      <td>María González</td>
                      <td>10/05/2025</td>
                      <td>$890.00</td>
                      <td>$890.00</td>
                      <td>$0.00</td>
                      <td>20/05/2025</td>
                      <td>
                        <span class="payment-method payment-transfer"
                          >Transferencia</span
                        >
                      </td>
                      <td>
                        <span class="status-badge status-completed"
                          >Completado</span
                        >
                      </td>
                      <td>
                        <button
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#paymentDetailsModal"
                        >
                          <i class="bi bi-eye"></i>
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>V-00132</td>
                      <td>Carlos Rodríguez</td>
                      <td>20/05/2025</td>
                      <td>$1,750.00</td>
                      <td>$500.00</td>
                      <td>$1,250.00</td>
                      <td>28/05/2025</td>
                      <td>
                        <span class="payment-method payment-transfer"
                          >Transferencia</span
                        >
                      </td>
                      <td>
                        <span class="status-badge status-pending"
                          >Pendiente</span
                        >
                      </td>
                      <td>
                        <button
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#paymentDetailsModal"
                        >
                          <i class="bi bi-eye"></i>
                        </button>
                        <button
                          class="btn btn-sm btn-success"
                          data-bs-toggle="modal"
                          data-bs-target="#newPaymentModal"
                        >
                          <i class="bi bi-cash-coin"></i>
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>V-00105</td>
                      <td>Ana Martínez</td>
                      <td>05/05/2025</td>
                      <td>$2,300.00</td>
                      <td>$1,800.00</td>
                      <td>$500.00</td>
                      <td>15/05/2025</td>
                      <td>
                        <span class="payment-method payment-cash"
                          >Efectivo</span
                        >
                      </td>
                      <td>
                        <span class="status-badge status-pending"
                          >Pendiente</span
                        >
                      </td>
                      <td>
                        <button
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#paymentDetailsModal"
                        >
                          <i class="bi bi-eye"></i>
                        </button>
                        <button
                          class="btn btn-sm btn-success"
                          data-bs-toggle="modal"
                          data-bs-target="#newPaymentModal"
                        >
                          <i class="bi bi-cash-coin"></i>
                        </button>
                      </td>
                    </tr>
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
                  Detalles de Pagos - Venta V-00125
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
                    <p><strong>Nombre:</strong> Juan Pérez</p>
                    <p><strong>Teléfono:</strong> 555-123-4567</p>
                    <p>
                      <strong>Dirección:</strong> Calle Flores #123, Col. Centro
                    </p>
                  </div>
                  <div class="col-md-6">
                    <h6>Información de la Venta</h6>
                    <p><strong>Fecha:</strong> 15/05/2025</p>
                    <p><strong>Total:</strong> $1,250.00</p>
                    <p><strong>Abonado:</strong> $750.00</p>
                    <p><strong>Saldo:</strong> $500.00</p>
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
                      <tr>
                        <td>15/05/2025</td>
                        <td>$500.00</td>
                        <td>
                          <span class="payment-method payment-cash"
                            >Efectivo</span
                          >
                        </td>
                        <td>María López</td>
                        <td>Pago inicial</td>
                      </tr>
                      <tr>
                        <td>25/05/2025</td>
                        <td>$250.00</td>
                        <td>
                          <span class="payment-method payment-cash"
                            >Efectivo</span
                          >
                        </td>
                        <td>Carlos Méndez</td>
                        <td>Segundo abono</td>
                      </tr>
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
                      value="V-00125"
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
                      value="FXG-0123"
                      readonly
                    />
                  </div>
                  <div class="mb-3">
                    <label for="clientName" class="form-label">Cliente</label>
                    <input
                      type="text"
                      class="form-control"
                      id="clientName"
                      value="Juan Pérez"
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
                    <small class="text-muted">Saldo pendiente: $500.00</small>
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
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script></script>
  </body>
</html>
