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

$entregas = $pdo->query("
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
    ORDER BY np.fechaPedido DESC
")->fetchAll();

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
</style>

<main class="container py-4">
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