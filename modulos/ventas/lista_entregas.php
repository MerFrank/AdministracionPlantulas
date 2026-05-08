<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once (__DIR__ . '/../../includes/config.php');
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
$clientes = $pdo->query("SELECT DISTINCT c.id_cliente, c.nombre_Cliente FROM clientes c ORDER BY c.nombre_Cliente")->fetchAll();
$estados = $pdo->query("SELECT DISTINCT estado FROM notaspedidos WHERE estado IS NOT NULL ORDER BY estado")->fetchAll();
$tipoPagos = $pdo->query("SELECT DISTINCT tipo_pago FROM notaspedidos WHERE tipo_pago IS NOT NULL ORDER BY tipo_pago")->fetchAll();

// Construir la consulta con filtros
$where = [];
$params = [];

// Filtro por Cliente
if (!empty($_GET['cliente'])) {
    $where[] = "np.id_cliente = :cliente";
    $params[':cliente'] = $_GET['cliente'];
}

// Filtro por Estado
if (!empty($_GET['estado'])) {
    $where[] = "np.estado = :estado";
    $params[':estado'] = $_GET['estado'];
}

// Filtro por Tipo de Pago
if (!empty($_GET['tipo_pago'])) {
    $where[] = "np.tipo_pago = :tipo_pago";
    $params[':tipo_pago'] = $_GET['tipo_pago'];
}

// Filtro por Folio
if (!empty($_GET['folio'])) {
    $where[] = "np.folio LIKE :folio";
    $params[':folio'] = '%' . $_GET['folio'] . '%';
}

// Filtro por Saldo Pendiente (mayor que)
if (!empty($_GET['saldo_min'])) {
    $where[] = "np.saldo_pendiente >= :saldo_min";
    $params[':saldo_min'] = floatval($_GET['saldo_min']);
}

// Filtro por Saldo Pendiente (menor que)
if (!empty($_GET['saldo_max'])) {
    $where[] = "np.saldo_pendiente <= :saldo_max";
    $params[':saldo_max'] = floatval($_GET['saldo_max']);
}

// Filtro por Fecha Pedido (desde)
if (!empty($_GET['fecha_pedido_desde'])) {
    $where[] = "np.fechaPedido >= :fecha_pedido_desde";
    $params[':fecha_pedido_desde'] = $_GET['fecha_pedido_desde'];
}

// Filtro por Fecha Pedido (hasta)
if (!empty($_GET['fecha_pedido_hasta'])) {
    $where[] = "np.fechaPedido <= :fecha_pedido_hasta";
    $params[':fecha_pedido_hasta'] = $_GET['fecha_pedido_hasta'];
}

// Filtro por Fecha Entrega (desde)
if (!empty($_GET['fecha_entrega_desde'])) {
    $where[] = "np.fecha_entrega >= :fecha_entrega_desde";
    $params[':fecha_entrega_desde'] = $_GET['fecha_entrega_desde'];
}

// Filtro por Fecha Entrega (hasta)
if (!empty($_GET['fecha_entrega_hasta'])) {
    $where[] = "np.fecha_entrega <= :fecha_entrega_hasta";
    $params[':fecha_entrega_hasta'] = $_GET['fecha_entrega_hasta'];
}

// Filtro por Atrasos
if (isset($_GET['atrasos']) && $_GET['atrasos'] === '1') {
    $where[] = "(np.fecha_entrega_Real IS NOT NULL AND np.fecha_entrega_Real > np.fecha_entrega) OR (np.fecha_entrega_Real IS NULL AND np.fecha_entrega < CURDATE())";
}

// Construir query SQL
$sql = "
    SELECT
        np.folio,
        c.nombre_Cliente,
        v.nombre_variedad,
        dnp.color,
        dnp.cantidad,
        np.tipo_pago,
        np.total,
        np.saldo_pendiente,
        np.estado,
        np.fechaPedido,
        np.fecha_entrega,
        np.fecha_entrega_Real,
        np.fecha_validez
    FROM
        notaspedidos np
    LEFT JOIN detallesnotapedido dnp ON np.id_notaPedido = dnp.id_notaPedido
    LEFT JOIN clientes c ON np.id_cliente = c.id_cliente
    LEFT JOIN variedades v ON dnp.id_variedad = v.id_variedad
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY np.fechaPedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entregas = $stmt->fetchAll();

$titulo = 'Lista de Pedidos';
$encabezado = 'Reportes entregas de pedidos';
$ruta = "vista_pedidos.php";
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
    
    .table thead th {
        background-color: #45814d;
        position: sticky;
        top: 0;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        white-space: nowrap;
    }
    
    /* Badges de estado */
    .badge-estado {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }
    
    .estado-pagado { background-color: #d4edda; color: #155724; }
    .estado-pendiente { background-color: #fff3cd; color: #856404; }
    .estado-parcial { background-color: #fff3cd; color: #856404; }
    .estado-cancelado { background-color: #f8d7da; color: #721c24; }
    
    .fecha-atrasada { color: #dc3545; font-weight: 500; }
    .fecha-normal { color: #28a745; }
    .texto-atraso { font-size: 0.7rem; color: #dc3545; margin-left: 5px; font-weight: 500; }
    
    .fecha-atrasada, .texto-atraso {
        color: #dc3545 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .fecha-normal {
        color: #28a745 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .registros-info {
        padding: 10px 15px;
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.85rem;
        color: #6c757d;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .tooltip-fecha {
        cursor: help;
        border-bottom: 1px dashed #ccc;
    }
    
    /* Estilos para exportación */
    .btn-excel {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        margin-bottom: 20px;
        transition: background-color 0.3s;
    }
    
    .btn-excel:hover {
        background-color: #1e7e34;
    }
    
    .btn-excel i {
        margin-right: 5px;
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
    
    /* Estilos para los filtros */
    .filtros-container {
        background-color: #f8f9fa;
        border: 2px solid #45814d;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .filtros-titulo {
        color: #45814d;
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filtro-grupo {
        margin-bottom: 15px;
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
        background-color: #45814d;
        color: white;
        border: none;
    }
    
    .btn-aplicar:hover {
        background-color: #366b3d;
    }
    
    .btn-limpiar {
        background-color: #6c757d;
        color: white;
        border: none;
    }
    
    .btn-limpiar:hover {
        background-color: #5a6268;
    }
    
    .checkbox-filtro {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-filtro input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .checkbox-filtro label {
        margin: 0;
        cursor: pointer;
        font-weight: normal;
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
        color: #45814d;
    }
</style>

<main class="container py-4">
    <!-- Filtros de búsqueda -->
    <div class="filtros-container">
        <div class="filtros-titulo">
            <i class="fas fa-filter"></i> Filtros de Búsqueda
        </div>
        
        <form method="GET" action="" id="filtroForm">
            <!-- Fila 1: Cliente, Estado, Tipo de Pago -->
            <div class="filtro-row">
                <div class="filtro-col">
                    <div class="filtro-label">Cliente</div>
                    <select name="cliente" class="form-select">
                        <option value="">Todos los clientes</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id_cliente'] ?>" <?= (isset($_GET['cliente']) && $_GET['cliente'] == $cliente['id_cliente']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-col">
                    <div class="filtro-label">Estado</div>
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado['estado']) ?>" <?= (isset($_GET['estado']) && $_GET['estado'] == $estado['estado']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-col">
                    <div class="filtro-label">Tipo de Pago</div>
                    <select name="tipo_pago" class="form-select">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tipoPagos as $pago): ?>
                            <option value="<?= htmlspecialchars($pago['tipo_pago']) ?>" <?= (isset($_GET['tipo_pago']) && $_GET['tipo_pago'] == $pago['tipo_pago']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pago['tipo_pago']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Fila 2: Folio, Saldo Mínimo, Saldo Máximo -->
            <div class="filtro-row" style="margin-top: 15px;">
                <div class="filtro-col">
                    <div class="filtro-label">Folio</div>
                    <input type="text" name="folio" class="form-control" placeholder="Buscar por folio..." value="<?= htmlspecialchars($_GET['folio'] ?? '') ?>">
                </div>
                
                <div class="filtro-col">
                    <div class="filtro-label">Saldo Pendiente (mínimo)</div>
                    <input type="number" name="saldo_min" class="form-control" placeholder="$0.00" step="0.01" min="0" value="<?= htmlspecialchars($_GET['saldo_min'] ?? '') ?>">
                </div>
                
                <div class="filtro-col">
                    <div class="filtro-label">Saldo Pendiente (máximo)</div>
                    <input type="number" name="saldo_max" class="form-control" placeholder="$0.00" step="0.01" min="0" value="<?= htmlspecialchars($_GET['saldo_max'] ?? '') ?>">
                </div>
            </div>
            
            <!-- Fila 3: Fecha Pedido -->
            <div class="filtro-row" style="margin-top: 15px;">
                <div class="filtro-col">
                    <div class="filtro-label">Fecha Pedido (desde - hasta)</div>
                    <div class="fecha-rango">
                        <input type="date" name="fecha_pedido_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_pedido_desde'] ?? '') ?>">
                        <small>a</small>
                        <input type="date" name="fecha_pedido_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_pedido_hasta'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Fila 4: Fecha Entrega -->
            <div class="filtro-row" style="margin-top: 15px;">
                <div class="filtro-col">
                    <div class="filtro-label">Fecha Entrega (desde - hasta)</div>
                    <div class="fecha-rango">
                        <input type="date" name="fecha_entrega_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_entrega_desde'] ?? '') ?>">
                        <small>a</small>
                        <input type="date" name="fecha_entrega_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_entrega_hasta'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Fila 5: Checkbox Atrasos -->
            <div class="filtro-row" style="margin-top: 15px;">
                <div class="filtro-col">
                    <div class="filtro-label">Estado de Entrega</div>
                    <div class="checkbox-filtro">
                        <input type="checkbox" name="atrasos" id="atrasos" value="1" <?= (isset($_GET['atrasos']) && $_GET['atrasos'] == '1') ? 'checked' : '' ?>>
                        <label for="atrasos">Solo entregas atrasadas</label>
                    </div>
                </div>
            </div>
            
            <!-- Fila 6: Botones de acción -->
            <div class="filtro-row" style="margin-top: 20px; justify-content: space-between; align-items: center;">
                <div>
                    <span class="contador-registros">
                        <i class="fas fa-list"></i> Registros encontrados: <?= count($entregas) ?>
                    </span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-filtro btn-aplicar">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                    <a href="?todo=1" class="btn-filtro btn-limpiar">
                        <i class="fas fa-eraser"></i> Limpiar Filtros
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Botones de acción -->
    <div class="action-buttons">
        <button class="btn-excel" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
    </div>
    
    <!-- Contenedor con scroll -->
    <div class="table-scroll-container">
        <table class="table table-striped table-hover" id="tablaPedidos">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Variedad</th>
                    <th>Color</th>
                    <th>Cantidad</th>
                    <th>Tipo de pago</th>
                    <th>Total</th>
                    <th>Saldo Pendiente</th>
                    <th>Estado</th>
                    <th>Fecha Pedido</th>
                    <th>Fecha Entrega</th>
                    <th>Fecha Entrega Real</th>
                    <th>Días de Atraso</th>
                    <th>Fecha Validez</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($entregas) > 0): ?>
                    <?php foreach ($entregas as $entrega): ?>
                        <tr>
                            <td><?= htmlspecialchars($entrega['folio']) ?></td>
                            <td><?= htmlspecialchars($entrega['nombre_Cliente']) ?></td>
                            <td><?= htmlspecialchars($entrega['nombre_variedad']) ?></td>
                            <td><?= htmlspecialchars($entrega['color']) ?></td>
                            <td><?= htmlspecialchars($entrega['cantidad']) ?></td>
                            <td><?= htmlspecialchars($entrega['tipo_pago']) ?></td>
                            <td>$<?= number_format($entrega['total'], 2) ?></td>
                            <td>$<?= number_format($entrega['saldo_pendiente'], 2) ?></td>
                            <td>
                                <?php 
                                $estado = $entrega['estado'];
                                $badge_class = '';
                                if($estado == 'Pagado') $badge_class = 'estado-pagado';
                                if($estado == 'Pendiente') $badge_class = 'estado-pendiente';
                                if($estado == 'parcial') $badge_class = 'estado-parcial';
                                if($estado == 'Cancelado') $badge_class = 'estado-cancelado';
                                ?>
                                <span class="badge-estado <?= $badge_class ?>">
                                    <?= htmlspecialchars($estado) ?>
                                </span>
                            </td>
                            <td class="tooltip-fecha" title="Fecha de creación del pedido">
                                <?= date('d/m/Y', strtotime($entrega['fechaPedido'])) ?>
                            </td>
                            <td class="tooltip-fecha" title="Fecha de entrega programada">
                                <?= date('d/m/Y', strtotime($entrega['fecha_entrega'])) ?>
                            </td>
                            <td>
                                <?php 
                                $entrega_real = $entrega['fecha_entrega_Real'];
                                if($entrega_real && strtotime($entrega_real) > strtotime($entrega['fecha_entrega'])):
                                ?>
                                    <span class="fecha-atrasada"><?= date('d/m/Y', strtotime($entrega_real)) ?></span>
                                <?php elseif($entrega_real): ?>
                                    <span class="fecha-normal">
                                        <i class="fas fa-check-circle"></i> 
                                        <?= date('d/m/Y', strtotime($entrega_real)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-clock"></i> Pendiente
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                // Calcular días de atraso como número entero
                                if($entrega_real && strtotime($entrega_real) > strtotime($entrega['fecha_entrega'])):
                                    $dias_atraso = floor((strtotime($entrega_real) - strtotime($entrega['fecha_entrega'])) / (60 * 60 * 24));
                                ?>
                                    <span class="texto-atraso">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <?= $dias_atraso ?> días
                                    </span>
                                <?php elseif($entrega_real): ?>
                                    <span class="text-muted">0 días</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $validez = $entrega['fecha_validez'];
                                if($validez && strtotime($validez) < strtotime(date('Y-m-d'))):
                                ?>
                                    <span class="fecha-atrasada tooltip-fecha" title="Fecha de validez expirada">
                                        <i class="fas fa-hourglass-end"></i> 
                                        <?= date('d/m/Y', strtotime($validez)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="tooltip-fecha" title="Fecha de validez vigente">
                                        <i class="fas fa-calendar-check"></i> 
                                        <?= date('d/m/Y', strtotime($validez)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14" class="text-center py-4">
                            <i class="fas fa-search" style="font-size: 2rem; color: #ccc; display: block; margin-bottom: 10px;"></i>
                            No se encontraron registros con los filtros seleccionados
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
function exportToExcel() {
    const table = document.getElementById('tablaPedidos');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Pedidos"});
    XLSX.writeFile(wb, `Reporte_Pedidos_${new Date().toISOString().slice(0,10)}.xlsx`);
}
</script>

<!-- Incluir SheetJS para exportación a Excel -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>