<?php
// Variables para el encabezado
$titulo = "Registro de Ingresos";
$encabezado = "Ingresos";
$subtitulo = "Gestión de entradas financieras";
$active_page = "ingresos";

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Obtener parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_cliente = $_GET['id_cliente'] ?? null;

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

// Obtener datos de ingresos
try {
    $db = new Database();
    $con = $db->conectar();
    
    // Consulta base
    $sql = "SELECT np.id_notaPedido, np.folio, np.fechaPedido, c.nombre_Cliente as cliente, 
                   np.total, np.estado, np.metodo_Pago,
                   (SELECT SUM(monto) FROM PagosVentas WHERE id_notaPedido = np.id_notaPedido) as pagado
            FROM NotasPedidos np
            JOIN Clientes c ON np.id_cliente = c.id_cliente
            WHERE np.fechaPedido BETWEEN :fecha_inicio AND :fecha_fin";
    
    $params = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    
    if ($id_cliente) {
        $sql .= " AND np.id_cliente = :id_cliente";
        $params[':id_cliente'] = $id_cliente;
    }
    
    $sql .= " ORDER BY np.fechaPedido DESC";
    
    $stmt = $con->prepare($sql);
    $stmt->execute($params);
    $ingresos = $stmt->fetchAll();
    
    // Obtener clientes para filtro
    $clientes = $con->query("SELECT id_cliente, nombre_Cliente FROM Clientes ORDER BY nombre_Cliente")->fetchAll();
    
} catch (PDOException $e) {
    die("Error al obtener ingresos: " . $e->getMessage());
}
?>

<main class="container-fluid py-4">
    <div class="card shadow mb-4">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Registro de Ingresos</h2>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#nuevoIngresoModal">
                    <i class="fas fa-plus me-1"></i>Nuevo Ingreso
                </button>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filtros -->
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" name="id_cliente">
                        <option value="">Todos los clientes</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id_cliente'] ?>" <?= $id_cliente == $cliente['id_cliente'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filtrar
                    </button>
                    <a href="Ingresos.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </form>
            
            <!-- Tabla de ingresos -->
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaIngresos">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Método Pago</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ingresos as $ingreso): 
                            $pagado = $ingreso['pagado'] ?? 0;
                            $saldo = $ingreso['total'] - $pagado;
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($ingreso['fechaPedido'])) ?></td>
                            <td><?= htmlspecialchars($ingreso['folio']) ?></td>
                            <td><?= htmlspecialchars($ingreso['cliente']) ?></td>
                            <td><?= htmlspecialchars($ingreso['metodo_Pago']) ?></td>
                            <td class="fw-bold">$<?= number_format($ingreso['total'], 2) ?></td>
                            <td class="text-success">$<?= number_format($pagado, 2) ?></td>
                            <td class="<?= $saldo > 0 ? 'text-danger' : 'text-success' ?>">
                                $<?= number_format(abs($saldo), 2) ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $saldo <= 0 ? 'success' : ($ingreso['estado'] == 'pendiente' ? 'warning' : 'info') ?>">
                                    <?= $saldo <= 0 ? 'Pagado' : ucfirst($ingreso['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal para nuevo ingreso -->
    <div class="modal fade" id="nuevoIngresoModal" tabindex="-1" aria-labelledby="nuevoIngresoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="nuevoIngresoModalLabel">Registrar Nuevo Ingreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formIngreso">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cliente</label>
                                <select class="form-select" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id_cliente'] ?>"><?= htmlspecialchars($cliente['nombre_Cliente']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total</label>
                                <input type="number" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success">Guardar Ingreso</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // DataTable para la tabla de ingresos
    $('#tablaIngresos').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-MX.json'
        },
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        pageLength: 10
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>