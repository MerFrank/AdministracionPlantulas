<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos para filtros
$sucursales = $con->query("SELECT id_sucursal, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$tiposEgreso = $con->query("SELECT id_tipo, nombre FROM tipos_egreso WHERE activo = 1 ORDER BY nombre")->fetchAll();
$especies = $con->query("SELECT id_especie, nombre FROM Especies ORDER BY nombre")->fetchAll();

// Procesar parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_sucursal = $_GET['id_sucursal'] ?? null;
$id_tipo_egreso = $_GET['id_tipo_egreso'] ?? null;
$id_especie = $_GET['id_especie'] ?? null;
$tipo_reporte = $_GET['tipo_reporte'] ?? 'semanal';
$tipo_visualizacion = $_GET['tipo_visualizacion'] ?? 'financiero'; // financiero, contable, administrativo

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

// Obtener datos para reportes
$datosReporte = [];
$balanceTotal = ['ingresos' => 0, 'egresos' => 0, 'balance' => 0];
$datosGraficas = [];

try {
    // Consulta para ingresos (ventas)
    $sqlIngresos = "SELECT 
        DATE(fechaPedido) as fecha,
        total as monto,
        'Venta de plantas' as descripcion,
        'Ventas' as categoria,
        'N/A' as sucursal
    FROM NotasPedidos 
    WHERE fechaPedido BETWEEN :fecha_inicio AND :fecha_fin
    AND estado != 'cancelado'";
    
    $paramsIngresos = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    
    if ($id_sucursal) {
        $sqlIngresos .= " AND id_sucursal = :id_sucursal";
        $paramsIngresos[':id_sucursal'] = $id_sucursal;
    }
    
    $stmtIngresos = $con->prepare($sqlIngresos);
    $stmtIngresos->execute($paramsIngresos);
    $ingresos = $stmtIngresos->fetchAll();

    // Consulta para egresos
    $sqlEgresos = "SELECT 
        e.fecha,
        e.monto,
        e.concepto as descripcion,
        t.nombre as tipo_egreso,
        s.nombre as sucursal
    FROM egresos e
    LEFT JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
    LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
    WHERE e.fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND e.estado = 'activo'";
    
    $paramsEgresos = [':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin];
    
    if ($id_sucursal) {
        $sqlEgresos .= " AND e.id_sucursal = :id_sucursal";
        $paramsEgresos[':id_sucursal'] = $id_sucursal;
    }
    
    if ($id_tipo_egreso) {
        $sqlEgresos .= " AND e.id_tipo_egreso = :id_tipo_egreso";
        $paramsEgresos[':id_tipo_egreso'] = $id_tipo_egreso;
    }
    
    $stmtEgresos = $con->prepare($sqlEgresos);
    $stmtEgresos->execute($paramsEgresos);
    $egresos = $stmtEgresos->fetchAll();

    // Procesar datos según tipo de reporte
    foreach ($ingresos as $ingreso) {
        $fecha = $ingreso['fecha'];
        $periodo = '';
        
        switch ($tipo_reporte) {
            case 'diario':
                $periodo = $fecha;
                break;
            case 'semanal':
                $periodo = date('Y-\WW', strtotime($fecha));
                break;
            case 'mensual':
                $periodo = date('Y-m', strtotime($fecha));
                break;
        }
        
        if (!isset($datosReporte[$periodo])) {
            $datosReporte[$periodo] = [
                'ingresos' => 0,
                'egresos' => 0,
                'detalle_ingresos' => [],
                'detalle_egresos' => [],
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha
            ];
        }
        
        $datosReporte[$periodo]['ingresos'] += $ingreso['monto'];
        $datosReporte[$periodo]['detalle_ingresos'][] = $ingreso;
        $balanceTotal['ingresos'] += $ingreso['monto'];
        
        // Actualizar fechas de inicio/fin
        if ($fecha < $datosReporte[$periodo]['fecha_inicio']) {
            $datosReporte[$periodo]['fecha_inicio'] = $fecha;
        }
        if ($fecha > $datosReporte[$periodo]['fecha_fin']) {
            $datosReporte[$periodo]['fecha_fin'] = $fecha;
        }
    }

    foreach ($egresos as $egreso) {
        $fecha = $egreso['fecha'];
        $periodo = '';
        
        switch ($tipo_reporte) {
            case 'diario':
                $periodo = $fecha;
                break;
            case 'semanal':
                $periodo = date('Y-\WW', strtotime($fecha));
                break;
            case 'mensual':
                $periodo = date('Y-m', strtotime($fecha));
                break;
        }
        
        if (!isset($datosReporte[$periodo])) {
            $datosReporte[$periodo] = [
                'ingresos' => 0,
                'egresos' => 0,
                'detalle_ingresos' => [],
                'detalle_egresos' => [],
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha
            ];
        }
        
        $datosReporte[$periodo]['egresos'] += $egreso['monto'];
        $datosReporte[$periodo]['detalle_egresos'][] = $egreso;
        $balanceTotal['egresos'] += $egreso['monto'];
        
        // Actualizar fechas de inicio/fin
        if ($fecha < $datosReporte[$periodo]['fecha_inicio']) {
            $datosReporte[$periodo]['fecha_inicio'] = $fecha;
        }
        if ($fecha > $datosReporte[$periodo]['fecha_fin']) {
            $datosReporte[$periodo]['fecha_fin'] = $fecha;
        }
    }

    // Calcular balance total
    $balanceTotal['balance'] = $balanceTotal['ingresos'] - $balanceTotal['egresos'];

    // Consulta para egresos por tipo
    $sqlEgresosTipo = "SELECT 
        COALESCE(t.nombre, 'Sin Tipo') as tipo_egreso,
        SUM(e.monto) as total
    FROM egresos e
    LEFT JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
    WHERE e.fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND e.estado = 'activo'
    GROUP BY t.nombre";
    
    $stmtEgresosTipo = $con->prepare($sqlEgresosTipo);
    $stmtEgresosTipo->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $datosGraficas['egresos_por_tipo'] = $stmtEgresosTipo->fetchAll();

    // Consulta para egresos por sucursal
    $sqlEgresosSucursal = "SELECT 
        COALESCE(s.nombre, 'Sin Sucursal') as sucursal,
        SUM(e.monto) as total
    FROM egresos e
    LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
    WHERE e.fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND e.estado = 'activo'
    GROUP BY s.nombre";
    
    $stmtEgresosSucursal = $con->prepare($sqlEgresosSucursal);
    $stmtEgresosSucursal->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $datosGraficas['egresos_por_sucursal'] = $stmtEgresosSucursal->fetchAll();

    // Consulta para ingresos por sucursal (si existe la columna id_sucursal en NotasPedidos)
    $sqlIngresosSucursal = "SELECT 
        COALESCE(s.nombre, 'Sin Sucursal') as sucursal,
        SUM(np.total) as total
    FROM NotasPedidos np
    LEFT JOIN sucursales s ON np.id_sucursal = s.id_sucursal
    WHERE np.fechaPedido BETWEEN :fecha_inicio AND :fecha_fin
    AND np.estado != 'cancelado'
    GROUP BY s.nombre";
    
    $stmtIngresosSucursal = $con->prepare($sqlIngresosSucursal);
    $stmtIngresosSucursal->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $datosGraficas['ingresos_por_sucursal'] = $stmtIngresosSucursal->fetchAll();

    // Consulta para egresos por variedad (si se seleccionó una especie)
    if ($id_especie) {
        $sqlEgresosVariedad = "SELECT 
            v.nombre_variedad,
            SUM(dnp.cantidad * dnp.precio_unitario) as total
        FROM DetallesNotaPedido dnp
        JOIN Variedades v ON dnp.id_variedad = v.id_variedad
        WHERE v.id_especie = :id_especie
        AND DATE(dnp.fecha_creacion) BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY v.nombre_variedad";
        
        $stmtEgresosVariedad = $con->prepare($sqlEgresosVariedad);
        $stmtEgresosVariedad->execute([
            ':id_especie' => $id_especie,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $datosGraficas['egresos_por_variedad'] = $stmtEgresosVariedad->fetchAll();
    }

} catch (PDOException $e) {
    die("Error al generar reportes: " . $e->getMessage());
}

$titulo = 'Reportes Financieros';
require __DIR__ . '/../../includes/header.php';
?>

<main class="container-fluid mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-graph-up"></i> Reportes <?= ucfirst($tipo_visualizacion) ?></h2>
                <div>
                    <button class="btn btn-success" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </button>
                    <button class="btn btn-danger" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <button class="btn btn-info" onclick="exportarPDF()">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar a PDF
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filtros -->
            <form method="get" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Tipo de Reporte</label>
                        <select class="form-select" name="tipo_visualizacion">
                            <option value="financiero" <?= $tipo_visualizacion === 'financiero' ? 'selected' : '' ?>>Financiero</option>
                            <option value="contable" <?= $tipo_visualizacion === 'contable' ? 'selected' : '' ?>>Contable</option>
                            <option value="administrativo" <?= $tipo_visualizacion === 'administrativo' ? 'selected' : '' ?>>Administrativo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Periodicidad</label>
                        <select class="form-select" name="tipo_reporte">
                            <option value="diario" <?= $tipo_reporte === 'diario' ? 'selected' : '' ?>>Diario</option>
                            <option value="semanal" <?= $tipo_reporte === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                            <option value="mensual" <?= $tipo_reporte === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sucursal</label>
                        <select class="form-select" name="id_sucursal">
                            <option value="">Todas</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?= $sucursal['id_sucursal'] ?>" <?= $id_sucursal == $sucursal['id_sucursal'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo de Egreso</label>
                        <select class="form-select" name="id_tipo_egreso">
                            <option value="">Todos</option>
                            <?php foreach ($tiposEgreso as $tipo): ?>
                                <option value="<?= $tipo['id_tipo'] ?>" <?= $id_tipo_egreso == $tipo['id_tipo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Especie (para egresos)</label>
                        <select class="form-select" name="id_especie">
                            <option value="">Todas</option>
                            <?php foreach ($especies as $especie): ?>
                                <option value="<?= $especie['id_especie'] ?>" <?= $id_especie == $especie['id_especie'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($especie['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="reportes_financieros.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Resumen de Balance -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Ingresos Totales</h5>
                            <p class="card-text h3">$<?= number_format($balanceTotal['ingresos'], 2) ?></p>
                            <small class="text-white-50"><?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">Egresos Totales</h5>
                            <p class="card-text h3">$<?= number_format($balanceTotal['egresos'], 2) ?></p>
                            <small class="text-white-50"><?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white <?= $balanceTotal['balance'] >= 0 ? 'bg-info' : 'bg-warning' ?>">
                        <div class="card-body">
                            <h5 class="card-title">Balance Neto</h5>
                            <p class="card-text h3">$<?= number_format($balanceTotal['balance'], 2) ?></p>
                            <small class="text-white-50"><?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficas según tipo de visualización -->
            <div class="row mb-4">
                <?php if ($tipo_visualizacion === 'financiero'): ?>
                    <!-- Visualización Financiera -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Ingresos vs Egresos</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaBalance" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Flujo de Caja</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaFlujoCaja" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($tipo_visualizacion === 'contable'): ?>
                    <!-- Visualización Contable -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Egresos por Tipo</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaEgresosTipo" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Ventas por Especie</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaVentasEspecie" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Visualización Administrativa -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Ventas por Sucursal</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaVentasSucursal" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Egresos por Sucursal</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="graficaEgresosSucursal" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($id_especie): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Egresos por Variedad (<?= htmlspecialchars($especies[array_search($id_especie, array_column($especies, 'id_especie'))]['nombre']) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="graficaEgresosVariedad" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tabla de resultados -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Detalle por <?= ucfirst($tipo_reporte) ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?= ucfirst($tipo_reporte) ?></th>
                                    <th>Ingresos</th>
                                    <th>Egresos</th>
                                    <th>Balance</th>
                                    <?php if ($tipo_reporte === 'semanal'): ?>
                                        <th>Rango de Fechas</th>
                                    <?php endif; ?>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($datosReporte)): ?>
                                    <tr>
                                        <td colspan="<?= $tipo_reporte === 'semanal' ? 6 : 5 ?>" class="text-center">No hay datos para mostrar</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($datosReporte as $periodo => $datos): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($periodo) ?></td>
                                            <td class="text-success">$<?= number_format($datos['ingresos'], 2) ?></td>
                                            <td class="text-danger">$<?= number_format($datos['egresos'], 2) ?></td>
                                            <td class="<?= ($datos['ingresos'] - $datos['egresos']) >= 0 ? 'text-success' : 'text-danger' ?>">
                                                $<?= number_format($datos['ingresos'] - $datos['egresos'], 2) ?>
                                            </td>
                                            <?php if ($tipo_reporte === 'semanal'): ?>
                                                <td><?= date('d/m/Y', strtotime($datos['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($datos['fecha_fin'])) ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalle('<?= $periodo ?>')">
                                                    <i class="bi bi-eye"></i> Detalle
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
            
            <!-- Detalle de ingresos/egresos por periodo (modal) -->
            <div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detalle de <span id="periodoModal"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs" id="detalleTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="ingresos-tab" data-bs-toggle="tab" data-bs-target="#ingresos" type="button" role="tab">Ingresos</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="egresos-tab" data-bs-toggle="tab" data-bs-target="#egresos" type="button" role="tab">Egresos</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="detalleTabsContent">
                                <div class="tab-pane fade show active" id="ingresos" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Descripción</th>
                                                    <th>Categoría</th>
                                                    <th>Sucursal</th>
                                                    <th>Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detalleIngresos">
                                                <!-- Datos se cargan con JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="egresos" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Descripción</th>
                                                    <th>Tipo</th>
                                                    <th>Sucursal</th>
                                                    <th>Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detalleEgresos">
                                                <!-- Datos se cargan con JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Incluir Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// Datos para JavaScript
const datosReporteJS = <?= json_encode($datosReporte) ?>;
const datosGraficasJS = <?= json_encode($datosGraficas) ?>;
const balanceTotalJS = <?= json_encode($balanceTotal) ?>;
const tipoReporte = '<?= $tipo_reporte ?>';
const tipoVisualizacion = '<?= $tipo_visualizacion ?>';

// Función para ver detalle de un periodo
function verDetalle(periodo) {
    const datos = datosReporteJS[periodo];
    document.getElementById('periodoModal').textContent = periodo;
    
    // Limpiar tablas
    document.getElementById('detalleIngresos').innerHTML = '';
    document.getElementById('detalleEgresos').innerHTML = '';
    
    // Llenar ingresos
    if (datos.detalle_ingresos && datos.detalle_ingresos.length > 0) {
        datos.detalle_ingresos.forEach(ingreso => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${ingreso.fecha}</td>
                <td>${ingreso.descripcion || 'Sin descripción'}</td>
                <td>${ingreso.categoria || 'Sin categoría'}</td>
                <td>${ingreso.sucursal || 'Sin sucursal'}</td>
                <td class="text-success">$${ingreso.monto.toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
            `;
            document.getElementById('detalleIngresos').appendChild(row);
        });
    } else {
        document.getElementById('detalleIngresos').innerHTML = '<tr><td colspan="5" class="text-center">No hay ingresos registrados</td></tr>';
    }
    
    // Llenar egresos
    if (datos.detalle_egresos && datos.detalle_egresos.length > 0) {
        datos.detalle_egresos.forEach(egreso => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${egreso.fecha}</td>
                <td>${egreso.descripcion || 'Sin descripción'}</td>
                <td>${egreso.tipo_egreso || 'Sin tipo'}</td>
                <td>${egreso.sucursal || 'Sin sucursal'}</td>
                <td class="text-danger">$${egreso.monto.toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
            `;
            document.getElementById('detalleEgresos').appendChild(row);
        });
    } else {
        document.getElementById('detalleEgresos').innerHTML = '<tr><td colspan="5" class="text-center">No hay egresos registrados</td></tr>';
    }
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('detalleModal'));
    modal.show();
}

// Función para exportar a Excel
function exportarExcel() {
    // Crear un libro de trabajo
    const wb = XLSX.utils.book_new();
    
    // Hoja de resumen
    const resumenData = [
        ["Reporte " + tipoVisualizacion.charAt(0).toUpperCase() + tipoVisualizacion.slice(1), "", "", ""],
        ["Período:", "<?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>", "", ""],
        ["Tipo de Reporte:", "<?= ucfirst($tipo_reporte) ?>", "", ""],
        ["", "", "", ""],
        ["Ingresos Totales", balanceTotalJS.ingresos, "", ""],
        ["Egresos Totales", balanceTotalJS.egresos, "", ""],
        ["Balance Neto", balanceTotalJS.balance, "", ""],
        ["", "", "", ""],
        ["Detalle por <?= ucfirst($tipo_reporte) ?>", "", "", ""],
        ["<?= ucfirst($tipo_reporte) ?>", "Ingresos", "Egresos", "Balance"]
    ];
    
    // Agregar datos del reporte
    Object.entries(datosReporteJS).forEach(([periodo, datos]) => {
        resumenData.push([
            periodo,
            datos.ingresos,
            datos.egresos,
            datos.ingresos - datos.egresos
        ]);
    });
    
    const wsResumen = XLSX.utils.aoa_to_sheet(resumenData);
    XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen");
    
    // Hoja de detalle de ingresos
    const ingresosData = [["Fecha", "Descripción", "Categoría", "Sucursal", "Monto"]];
    Object.values(datosReporteJS).forEach(datos => {
        if (datos.detalle_ingresos) {
            datos.detalle_ingresos.forEach(ingreso => {
                ingresosData.push([
                    ingreso.fecha,
                    ingreso.descripcion || 'Sin descripción',
                    ingreso.categoria || 'Sin categoría',
                    ingreso.sucursal || 'Sin sucursal',
                    ingreso.monto
                ]);
            });
        }
    });
    
    const wsIngresos = XLSX.utils.aoa_to_sheet(ingresosData);
    XLSX.utils.book_append_sheet(wb, wsIngresos, "Detalle Ingresos");
    
    // Hoja de detalle de egresos
    const egresosData = [["Fecha", "Descripción", "Tipo Egreso", "Sucursal", "Monto"]];
    Object.values(datosReporteJS).forEach(datos => {
        if (datos.detalle_egresos) {
            datos.detalle_egresos.forEach(egreso => {
                egresosData.push([
                    egreso.fecha,
                    egreso.descripcion || 'Sin descripción',
                    egreso.tipo_egreso || 'Sin tipo',
                    egreso.sucursal || 'Sin sucursal',
                    egreso.monto
                ]);
            });
        }
    });
    
    const wsEgresos = XLSX.utils.aoa_to_sheet(egresosData);
    XLSX.utils.book_append_sheet(wb, wsEgresos, "Detalle Egresos");
    
    // Hojas adicionales según tipo de visualización
    if (tipoVisualizacion === 'financiero') {
        // Hoja de flujo de caja
        const flujoData = [["Periodo", "Ingresos", "Egresos", "Flujo Neto"]];
        Object.entries(datosReporteJS).forEach(([periodo, datos]) => {
            flujoData.push([
                periodo,
                datos.ingresos,
                datos.egresos,
                datos.ingresos - datos.egresos
            ]);
        });
        
        const wsFlujo = XLSX.utils.aoa_to_sheet(flujoData);
        XLSX.utils.book_append_sheet(wb, wsFlujo, "Flujo de Caja");
    }
    
    if (tipoVisualizacion === 'contable') {
        // Hoja de egresos por tipo
        const egresosTipoData = [["Tipo de Egreso", "Monto"]];
        datosGraficasJS.egresos_por_tipo.forEach(item => {
            egresosTipoData.push([item.tipo_egreso, item.total]);
        });
        
        const wsEgresosTipo = XLSX.utils.aoa_to_sheet(egresosTipoData);
        XLSX.utils.book_append_sheet(wb, wsEgresosTipo, "Egresos por Tipo");
    }
    
    if (tipoVisualizacion === 'administrativo') {
        // Hoja de egresos por sucursal
        const egresosSucursalData = [["Sucursal", "Monto"]];
        datosGraficasJS.egresos_por_sucursal.forEach(item => {
            egresosSucursalData.push([item.sucursal, item.total]);
        });
        
        const wsEgresosSucursal = XLSX.utils.aoa_to_sheet(egresosSucursalData);
        XLSX.utils.book_append_sheet(wb, wsEgresosSucursal, "Egresos por Sucursal");
        
        // Hoja de ventas por sucursal
        const ventasSucursalData = [["Sucursal", "Monto"]];
        datosGraficasJS.ingresos_por_sucursal.forEach(item => {
            ventasSucursalData.push([item.sucursal, item.total]);
        });
        
        const wsVentasSucursal = XLSX.utils.aoa_to_sheet(ventasSucursalData);
        XLSX.utils.book_append_sheet(wb, wsVentasSucursal, "Ventas por Sucursal");
    }
    
    <?php if ($id_especie): ?>
    // Hoja de egresos por variedad
    const egresosVariedadData = [["Variedad", "Monto"]];
    datosGraficasJS.egresos_por_variedad.forEach(item => {
        egresosVariedadData.push([item.nombre_variedad, item.total]);
    });
    
    const wsEgresosVariedad = XLSX.utils.aoa_to_sheet(egresosVariedadData);
    XLSX.utils.book_append_sheet(wb, wsEgresosVariedad, "Egresos por Variedad");
    <?php endif; ?>
    
    // Generar y descargar el archivo
    XLSX.writeFile(wb, `Reporte_${tipoVisualizacion}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

// Función para exportar a PDF
function exportarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Título
    doc.setFontSize(18);
    doc.text(`Reporte ${tipoVisualizacion.charAt(0).toUpperCase() + tipoVisualizacion.slice(1)}`, 105, 15, { align: 'center' });
    
    // Información del reporte
    doc.setFontSize(12);
    doc.text(`Período: ${new Date('<?= $fecha_inicio ?>').toLocaleDateString()} - ${new Date('<?= $fecha_fin ?>').toLocaleDateString()}`, 14, 25);
    doc.text(`Tipo de reporte: ${tipoReporte.charAt(0).toUpperCase() + tipoReporte.slice(1)}`, 14, 32);
    
    // Resumen
    doc.setFontSize(14);
    doc.text("Resumen Financiero", 14, 45);
    doc.setFontSize(12);
    
    // Tabla de resumen
    doc.autoTable({
        startY: 50,
        head: [['Concepto', 'Monto']],
        body: [
            ['Ingresos Totales', '$' + balanceTotalJS.ingresos.toLocaleString('es-MX', {minimumFractionDigits: 2})],
            ['Egresos Totales', '$' + balanceTotalJS.egresos.toLocaleString('es-MX', {minimumFractionDigits: 2})],
            ['Balance Neto', '$' + balanceTotalJS.balance.toLocaleString('es-MX', {minimumFractionDigits: 2})]
        ],
        styles: {
            halign: 'right',
            cellPadding: 3
        },
        columnStyles: {
            0: { halign: 'left', fontStyle: 'bold' }
        }
    });
    
    // Detalle por periodo
    doc.setFontSize(14);
    doc.text(`Detalle por ${tipoReporte.charAt(0).toUpperCase() + tipoReporte.slice(1)}`, 14, doc.autoTable.previous.finalY + 15);
    doc.setFontSize(12);
    
    const detalleData = [];
    Object.entries(datosReporteJS).forEach(([periodo, datos]) => {
        detalleData.push([
            periodo,
            '$' + datos.ingresos.toLocaleString('es-MX', {minimumFractionDigits: 2}),
            '$' + datos.egresos.toLocaleString('es-MX', {minimumFractionDigits: 2}),
            '$' + (datos.ingresos - datos.egresos).toLocaleString('es-MX', {minimumFractionDigits: 2})
        ]);
    });
    
    doc.autoTable({
        startY: doc.autoTable.previous.finalY + 20,
        head: [[
            tipoReporte.charAt(0).toUpperCase() + tipoReporte.slice(1),
            'Ingresos',
            'Egresos',
            'Balance'
        ]],
        body: detalleData,
        styles: {
            halign: 'right',
            cellPadding: 3
        },
        columnStyles: {
            0: { halign: 'left' }
        }
    });
    
    // Guardar PDF
    doc.save(`Reporte_${tipoVisualizacion}_${new Date().toISOString().slice(0,10)}.pdf`);
}

// Gráficas
document.addEventListener('DOMContentLoaded', function() {
    // Configuración común para gráficas
    const commonOptions = {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        label += '$' + context.raw.toLocaleString('es-MX', {minimumFractionDigits: 2});
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString('es-MX');
                    }
                }
            }
        }
    };
    
    // Gráfica de Balance (Ingresos vs Egresos)
    if (document.getElementById('graficaBalance')) {
        const ctxBalance = document.getElementById('graficaBalance').getContext('2d');
        const labels = Object.keys(datosReporteJS);
        const ingresosData = Object.values(datosReporteJS).map(d => d.ingresos);
        const egresosData = Object.values(datosReporteJS).map(d => d.egresos);
        
        new Chart(ctxBalance, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: ingresosData,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Egresos',
                        data: egresosData,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: commonOptions
        });
    }
    
    // Gráfica de Flujo de Caja
    if (document.getElementById('graficaFlujoCaja')) {
        const ctxFlujo = document.getElementById('graficaFlujoCaja').getContext('2d');
        const labels = Object.keys(datosReporteJS);
        const flujoData = Object.values(datosReporteJS).map(d => d.ingresos - d.egresos);
        
        new Chart(ctxFlujo, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Flujo Neto',
                    data: flujoData,
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: commonOptions
        });
    }
    
    // Gráfica de Egresos por Tipo
    if (document.getElementById('graficaEgresosTipo')) {
        const ctxEgrTipo = document.getElementById('graficaEgresosTipo').getContext('2d');
        const data = datosGraficasJS.egresos_por_tipo;
        
        new Chart(ctxEgrTipo, {
            type: 'pie',
            data: {
                labels: data.map(item => item.tipo_egreso),
                datasets: [{
                    data: data.map(item => item.total),
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(253, 126, 20, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(32, 201, 151, 0.7)',
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(111, 66, 193, 0.7)'
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        'rgba(253, 126, 20, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(32, 201, 151, 1)',
                        'rgba(13, 110, 253, 1)',
                        'rgba(111, 66, 193, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: $${value.toLocaleString('es-MX')} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Gráfica de Ventas por Sucursal
    if (document.getElementById('graficaVentasSucursal')) {
        const ctxIngSuc = document.getElementById('graficaVentasSucursal').getContext('2d');
        const data = datosGraficasJS.ingresos_por_sucursal;
        
        new Chart(ctxIngSuc, {
            type: 'bar',
            data: {
                labels: data.map(item => item.sucursal),
                datasets: [{
                    label: 'Ventas',
                    data: data.map(item => item.total),
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: commonOptions
        });
    }
    
    // Gráfica de Egresos por Sucursal
    if (document.getElementById('graficaEgresosSucursal')) {
        const ctxEgrSuc = document.getElementById('graficaEgresosSucursal').getContext('2d');
        const data = datosGraficasJS.egresos_por_sucursal;
        
        new Chart(ctxEgrSuc, {
            type: 'bar',
            data: {
                labels: data.map(item => item.sucursal),
                datasets: [{
                    label: 'Egresos',
                    data: data.map(item => item.total),
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: commonOptions
        });
    }
    
    // Gráfica de Egresos por Variedad
    if (document.getElementById('graficaEgresosVariedad')) {
        const ctxEgrVar = document.getElementById('graficaEgresosVariedad').getContext('2d');
        const data = datosGraficasJS.egresos_por_variedad;
        
        new Chart(ctxEgrVar, {
            type: 'doughnut',
            data: {
                labels: data.map(item => item.nombre_variedad),
                datasets: [{
                    data: data.map(item => item.total),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: $${value.toLocaleString('es-MX')} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>