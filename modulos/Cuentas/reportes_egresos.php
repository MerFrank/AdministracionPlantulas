<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener valores únicos para los filtros
$tiposEgreso = $pdo->query("SELECT DISTINCT te.id_tipo, te.nombre FROM tipos_egreso te ORDER BY te.nombre")->fetchAll();
$proveedores = $pdo->query("SELECT DISTINCT p.id_proveedor, p.nombre_proveedor FROM proveedores p WHERE p.nombre_proveedor IS NOT NULL ORDER BY p.nombre_proveedor")->fetchAll();
$sucursales = $pdo->query("SELECT DISTINCT s.id_sucursal, s.nombre FROM sucursales s WHERE s.nombre IS NOT NULL ORDER BY s.nombre")->fetchAll();
$cuentas = $pdo->query("SELECT DISTINCT cb.id_cuenta, cb.nombre FROM cuentas_bancarias cb WHERE cb.nombre IS NOT NULL ORDER BY cb.nombre")->fetchAll();
$metodosPago = $pdo->query("SELECT DISTINCT metodo_pago FROM egresos WHERE metodo_pago IS NOT NULL ORDER BY metodo_pago")->fetchAll();

// Construir la consulta con filtros
$where = [];
$params = [];

// Filtro por Fecha (desde)
if (!empty($_GET['fecha_desde'])) {
    $where[] = "e.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $_GET['fecha_desde'];
}

// Filtro por Fecha (hasta)
if (!empty($_GET['fecha_hasta'])) {
    $where[] = "e.fecha <= :fecha_hasta";
    $params[':fecha_hasta'] = $_GET['fecha_hasta'];
}

// Filtro por Tipo de Egreso
if (!empty($_GET['tipo_egreso'])) {
    $where[] = "e.id_tipo_egreso = :tipo_egreso";
    $params[':tipo_egreso'] = $_GET['tipo_egreso'];
}

// Filtro por Proveedor
if (!empty($_GET['proveedor'])) {
    $where[] = "e.id_proveedor = :proveedor";
    $params[':proveedor'] = $_GET['proveedor'];
}

// Filtro por Sucursal
if (!empty($_GET['sucursal'])) {
    $where[] = "e.id_sucursal = :sucursal";
    $params[':sucursal'] = $_GET['sucursal'];
}

// Filtro por Cuenta Bancaria
if (!empty($_GET['cuenta'])) {
    $where[] = "e.id_cuenta = :cuenta";
    $params[':cuenta'] = $_GET['cuenta'];
}

// Filtro por Método de Pago
if (!empty($_GET['metodo_pago'])) {
    $where[] = "e.metodo_pago = :metodo_pago";
    $params[':metodo_pago'] = $_GET['metodo_pago'];
}

// Filtro por Concepto (búsqueda por texto)
if (!empty($_GET['concepto'])) {
    $where[] = "e.concepto LIKE :concepto";
    $params[':concepto'] = '%' . $_GET['concepto'] . '%';
}

// Filtro por Monto (mínimo)
if (!empty($_GET['monto_min'])) {
    $where[] = "e.monto >= :monto_min";
    $params[':monto_min'] = floatval($_GET['monto_min']);
}

// Filtro por Monto (máximo)
if (!empty($_GET['monto_max'])) {
    $where[] = "e.monto <= :monto_max";
    $params[':monto_max'] = floatval($_GET['monto_max']);
}

// Construir query SQL
$sql = 'SELECT 
        e.fecha,
        te.nombre as tipo_egreso,
        p.nombre_proveedor as nombre_proveedor,
        s.nombre as nombre_sucursal,
        cb.nombre as nombre_cuenta,
        e.monto,
        e.concepto,
        e.metodo_pago,
        e.comprobante,
        e.observaciones
     FROM egresos e
     LEFT JOIN tipos_egreso te ON e.id_tipo_egreso = te.id_tipo
     LEFT JOIN proveedores p ON e.id_proveedor = p.id_proveedor
     LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
     LEFT JOIN cuentas_bancarias cb ON e.id_cuenta = cb.id_cuenta';

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY e.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$egresos = $stmt->fetchAll();

// Calcular total de montos filtrados
$totalMonto = array_sum(array_column($egresos, 'monto'));

$titulo = 'Reportes Egresos';
$encabezado = 'Reportes Egresos';

$ruta = "lista_reportes.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>

<style>
    /* Contenedor con scroll */
    .table-scroll-container {
        max-height: 600px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        margin-top: 20px;
        position: relative;
    }
    
    /* Cabecera fija */
    .table thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    

    
    /* Estilos para los filtros */
    .filtros-container {
        background-color: #f8f9fa;
        border: 2px solid #0d6efd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .filtros-titulo {
        color: #0d6efd;
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filtro-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 5px;
    }
    
    .filtro-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: end;
    }
    
    .filtro-col {
        flex: 1;
        min-width: 200px;
    }
    
    .btn-filtro {
        padding: 8px 20px;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-aplicar {
        background-color: #0d6efd;
        color: white;
        border: none;
    }
    
    .btn-aplicar:hover {
        background-color: #0b5ed7;
    }
    
    .btn-limpiar {
        background-color: #6c757d;
        color: white;
        border: none;
    }
    
    .btn-limpiar:hover {
        background-color: #5a6268;
    }
    
    .btn-excel {
        background-color: #28a745;
        color: white;
        border: none;
    }
    
    .btn-excel:hover {
        background-color: #1e7e34;
    }
    
    .fecha-rango {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .fecha-rango small {
        font-weight: normal;
        color: #6c757d;
    }
    
    .contador-registros {
        font-weight: 600;
        color: #0d6efd;
    }
    
    .total-monto {
        background-color: #e7f1ff;
        padding: 10px 15px;
        border-radius: 5px;
        margin-top: 15px;
        font-weight: 600;
        color: #0d6efd;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .total-monto i {
        font-size: 1.2rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    /* Scrollbar personalizado */
    .table-scroll-container::-webkit-scrollbar {
        width: 10px;
    }
    
    .table-scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 5px;
    }
    
    .table-scroll-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 5px;
    }
    
    .table-scroll-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    .monto-col {
        text-align: right;
        font-weight: 500;
    }
</style>

<main class="container mt-4">
    <div class="card card-lista">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>Egresos</h2>
                <div>
                    <a href="../Egresos/Registro_egreso.php" class="btn btn-success btn-sm ms-2">
                        <i class="bi bi-plus-circle"></i> Nuevo
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filtros de búsqueda -->
            <div class="filtros-container">
                <div class="filtros-titulo">
                    <i class="bi bi-funnel"></i> Filtros de Búsqueda
                </div>
                
                <form method="GET" action="" id="filtroForm">
                    <!-- Fila 1: Fecha desde, Fecha hasta, Tipo de Egreso -->
                    <div class="filtro-row">
                        <div class="filtro-col">
                            <div class="filtro-label">Fecha (desde)</div>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>">
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Fecha (hasta)</div>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>">
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Tipo de Egreso</div>
                            <select name="tipo_egreso" class="form-select">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($tiposEgreso as $tipo): ?>
                                    <option value="<?= $tipo['id_tipo'] ?>" <?= (isset($_GET['tipo_egreso']) && $_GET['tipo_egreso'] == $tipo['id_tipo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Fila 2: Proveedor, Sucursal, Cuenta Bancaria -->
                    <div class="filtro-row" style="margin-top: 15px;">
                        <div class="filtro-col">
                            <div class="filtro-label">Proveedor</div>
                            <select name="proveedor" class="form-select">
                                <option value="">Todos los proveedores</option>
                                <?php foreach ($proveedores as $proveedor): ?>
                                    <option value="<?= $proveedor['id_proveedor'] ?>" <?= (isset($_GET['proveedor']) && $_GET['proveedor'] == $proveedor['id_proveedor']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($proveedor['nombre_proveedor']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Sucursal</div>
                            <select name="sucursal" class="form-select">
                                <option value="">Todas las sucursales</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['id_sucursal'] ?>" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == $sucursal['id_sucursal']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Cuenta Bancaria</div>
                            <select name="cuenta" class="form-select">
                                <option value="">Todas las cuentas</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>" <?= (isset($_GET['cuenta']) && $_GET['cuenta'] == $cuenta['id_cuenta']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cuenta['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Fila 3: Método de Pago, Concepto, Monto -->
                    <div class="filtro-row" style="margin-top: 15px;">
                        <div class="filtro-col">
                            <div class="filtro-label">Método de Pago</div>
                            <select name="metodo_pago" class="form-select">
                                <option value="">Todos los métodos</option>
                                <?php foreach ($metodosPago as $metodo): ?>
                                    <option value="<?= htmlspecialchars($metodo['metodo_pago']) ?>" <?= (isset($_GET['metodo_pago']) && $_GET['metodo_pago'] == $metodo['metodo_pago']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($metodo['metodo_pago']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Concepto</div>
                            <input type="text" name="concepto" class="form-control" placeholder="Buscar por concepto..." value="<?= htmlspecialchars($_GET['concepto'] ?? '') ?>">
                        </div>
                        
                        <div class="filtro-col">
                            <div class="filtro-label">Monto</div>
                            <div class="fecha-rango">
                                <input type="number" name="monto_min" class="form-control" placeholder="Mínimo" step="0.01" min="0" value="<?= htmlspecialchars($_GET['monto_min'] ?? '') ?>">
                                <small>a</small>
                                <input type="number" name="monto_max" class="form-control" placeholder="Máximo" step="0.01" min="0" value="<?= htmlspecialchars($_GET['monto_max'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fila 4: Botones de acción y contador -->
                    <div class="filtro-row" style="margin-top: 20px; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="contador-registros">
                                <i class="bi bi-list-ul"></i> Registros encontrados: <?= count($egresos) ?>
                            </span>
                            <span class="total-monto">
                                <i class="bi bi-cash-stack"></i> Total: $<?= number_format($totalMonto, 2) ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn-filtro btn-aplicar">
                                <i class="bi bi-search"></i> Aplicar Filtros
                            </button>
                            <a href="?todo=1" class="btn-filtro btn-limpiar">
                                <i class="bi bi-eraser"></i> Limpiar Filtros
                            </a>
                            <button type="button" class="btn-filtro btn-excel" onclick="exportToExcel()">
                                <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tabla de egresos -->
            <div class="table-scroll-container">
                <table class="table table-striped table-hover" id="tablaEgresos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Proveedor</th>
                            <th>Sucursal</th>
                            <th>Cuenta</th>
                            <th class="monto-col">Monto</th>
                            <th>Concepto</th>
                            <th>Método de pago</th>
                            <th>Comprobante</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($egresos)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                    No se encontraron egresos con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($egresos as $egreso): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($egreso['tipo_egreso'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['nombre_proveedor'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['nombre_sucursal'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['nombre_cuenta'] ?? 'N/A') ?></td>
                                    <td class="monto-col">$<?= number_format($egreso['monto'], 2) ?></td>
                                    <td><?= htmlspecialchars($egreso['concepto'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['metodo_pago'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['comprobante'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($egreso['observaciones'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script>
function exportToExcel() {
    const table = document.getElementById('tablaEgresos');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Egresos"});
    
    // Obtener fecha actual para el nombre del archivo
    const fecha = new Date().toISOString().slice(0,10);
    
    // Agregar información de filtros al archivo
    const filtros = [];
    <?php if (!empty($_GET['fecha_desde']) || !empty($_GET['fecha_hasta'])): ?>
    filtros.push('Período: <?= htmlspecialchars($_GET['fecha_desde'] ?? 'Inicio') ?> - <?= htmlspecialchars($_GET['fecha_hasta'] ?? 'Fin') ?>');
    <?php endif; ?>
    
    if (filtros.length > 0) {
        const ws = wb.Sheets["Egresos"];
        XLSX.utils.sheet_add_aoa(ws, [['']], {origin: -1});
        XLSX.utils.sheet_add_aoa(ws, [filtros], {origin: -1});
        XLSX.utils.sheet_add_aoa(ws, [['Total General: $<?= number_format($totalMonto, 2) ?>']], {origin: -1});
    }
    
    XLSX.writeFile(wb, `Reporte_Egresos_${fecha}.xlsx`);
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>