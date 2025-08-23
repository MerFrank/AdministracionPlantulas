<?php
// Variables para el encabezado
$titulo = "Registro de Egresos";
$encabezado = "Egresos";
$subtitulo = "Gestión de salidas financieras";
$active_page = "egresos";

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Obtener parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_proveedor = $_GET['id_proveedor'] ?? null;
$id_tipo = $_GET['id_tipo'] ?? null;

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

// Obtener datos de egresos
try {
    $db = new Database();
    $con = $db->conectar();
    
    // Consulta base
    $sql = "SELECT e.id_egreso, e.fecha, e.monto, e.concepto, e.metodo_pago, e.comprobante,
                   p.nombre_proveedor as proveedor, t.nombre as tipo_egreso
            FROM egresos e
            LEFT JOIN proveedores p ON e.id_proveedor = p.id_proveedor
            LEFT JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
            WHERE e.fecha BETWEEN :fecha_inicio AND :fecha_fin";
    
    $params = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    
    if ($id_proveedor) {
        $sql .= " AND e.id_proveedor = :id_proveedor";
        $params[':id_proveedor'] = $id_proveedor;
    }
    
    if ($id_tipo) {
        $sql .= " AND e.id_tipo_egreso = :id_tipo";
        $params[':id_tipo'] = $id_tipo;
    }
    
    $sql .= " ORDER BY e.fecha DESC";
    
    $stmt = $con->prepare($sql);
    $stmt->execute($params);
    $egresos = $stmt->fetchAll();
    
    // Obtener proveedores y tipos para filtros
    $proveedores = $con->query("SELECT id_proveedor, nombre_proveedor FROM proveedores WHERE activo = 1 ORDER BY nombre_proveedor")->fetchAll();
    $tiposEgreso = $con->query("SELECT id_tipo, nombre FROM tipos_egreso WHERE activo = 1 ORDER BY nombre")->fetchAll();
    
} catch (PDOException $e) {
    die("Error al obtener egresos: " . $e->getMessage());
}
?>

<main class="container-fluid py-4">
    <div class="card shadow mb-4">
        <div class="card-header bg-danger text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Registro de Egresos</h2>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#nuevoEgresoModal">
                    <i class="fas fa-plus me-1"></i>Nuevo Egreso
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
                <div class="col-md-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select" name="id_proveedor">
                        <option value="">Todos los proveedores</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <option value="<?= $proveedor['id_proveedor'] ?>" <?= $id_proveedor == $proveedor['id_proveedor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proveedor['nombre_proveedor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Egreso</label>
                    <select class="form-select" name="id_tipo">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tiposEgreso as $tipo): ?>
                            <option value="<?= $tipo['id_tipo'] ?>" <?= $id_tipo == $tipo['id_tipo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filtrar
                    </button>
                    <a href="Egresos.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </form>
            
            <!-- Tabla de egresos -->
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaEgresos">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Método Pago</th>
                            <th>Monto</th>
                            <th>Comprobante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($egresos as $egreso): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></td>
                            <td><?= htmlspecialchars($egreso['concepto']) ?></td>
                            <td><?= htmlspecialchars($egreso['proveedor'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($egreso['tipo_egreso'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($egreso['metodo_pago']) ?></td>
                            <td class="text-danger fw-bold">$<?= number_format($egreso['monto'], 2) ?></td>
                            <td><?= htmlspecialchars($egreso['comprobante'] ?? 'N/A') ?></td>
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
    
    <!-- Modal para nuevo egreso -->
    <div class="modal fade" id="nuevoEgresoModal" tabindex="-1" aria-labelledby="nuevoEgresoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="nuevoEgresoModalLabel">Registrar Nuevo Egreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEgreso">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select">
                                    <option value="">Seleccionar proveedor...</option>
                                    <?php foreach ($proveedores as $proveedor): ?>
                                        <option value="<?= $proveedor['id_proveedor'] ?>"><?= htmlspecialchars($proveedor['nombre_proveedor']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Egreso</label>
                                <select class="form-select" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <?php foreach ($tiposEgreso as $tipo): ?>
                                        <option value="<?= $tipo['id_tipo'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Monto</label>
                                <input type="number" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Comprobante</label>
                                <input type="text" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Concepto</label>
                                <textarea class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger">Guardar Egreso</button>
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
    
    // DataTable para la tabla de egresos
    $('#tablaEgresos').DataTable({
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