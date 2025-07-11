<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

function safe_html($value) {
    return $value !== null ? htmlspecialchars($value) : '';
}

function format_currency($value) {
    return '$' . number_format($value, 2);
}

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$id_cuenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_cuenta <= 0) {
    $_SESSION['error_message'] = 'ID de cuenta no válido';
    header('Location: lista_cuentas.php');
    exit;
}

$stmt = $con->prepare("SELECT * FROM cuentas_bancarias WHERE id_cuenta = ?");
$stmt->execute([$id_cuenta]);
$cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cuenta) {
    $_SESSION['error_message'] = 'Cuenta no encontrada';
    header('Location: lista_cuentas.php');
    exit;
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
}

// Calcular saldo inicial
$sql_saldo_inicial = "SELECT COALESCE(SUM(CASE WHEN tipo_operacion = 'ingreso' THEN monto ELSE -monto END), 0) as saldo_anterior 
                      FROM egresos 
                      WHERE id_cuenta = :id_cuenta AND fecha < :fecha_inicio";

$stmt = $con->prepare($sql_saldo_inicial);
$stmt->execute([':id_cuenta' => $id_cuenta, ':fecha_inicio' => $fecha_inicio]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$saldo_inicial_periodo = $cuenta['saldo_inicial'] + $resultado['saldo_anterior'];

// Consulta de movimientos
$sql_movimientos = "
    SELECT 
        e.id_egreso,
        e.fecha,
        e.concepto,
        e.monto,
        e.metodo_pago,
        e.tipo_operacion,
        CASE 
            WHEN e.tipo_operacion = 'ingreso' THEN 'Ingreso'
            ELSE 'Egreso'
        END as tipo_movimiento
    FROM 
        egresos e
    WHERE 
        e.id_cuenta = :id_cuenta
        AND e.fecha BETWEEN :fecha_inicio AND :fecha_fin
    ORDER BY 
        e.fecha ASC, e.id_egreso ASC
";

$stmt = $con->prepare($sql_movimientos);
$stmt->execute([
    ':id_cuenta' => $id_cuenta,
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_ingresos = 0;
$total_egresos = 0;
$saldo_actual = $saldo_inicial_periodo;

foreach ($movimientos as &$mov) {
    if ($mov['tipo_operacion'] === 'ingreso') {
        $total_ingresos += $mov['monto'];
        $mov['ingreso'] = $mov['monto'];
        $mov['egreso'] = 0;
        $saldo_actual += $mov['monto'];
    } else {
        $total_egresos += $mov['monto'];
        $mov['ingreso'] = 0;
        $mov['egreso'] = $mov['monto'];
        $saldo_actual -= $mov['monto'];
    }
    $mov['saldo_acumulado'] = $saldo_actual;
}

$saldo_final = $saldo_actual;

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=movimientos_' . safe_html($cuenta['nombre']) . '.xls');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'td { padding: 2px; border: 1px solid #ddd; font-size: 11px; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; text-align: center; padding: 3px; border: 1px solid #ddd; font-size: 11px; }';
    echo '.empresa { font-size: 14px; font-weight: bold; text-align: center; }';
    echo '.titulo { font-size: 12px; text-align: center; margin-bottom: 10px; }';
    echo '.label { font-weight: bold; }';
    echo '.total { font-weight: bold; background-color: #f2f2f2; }';
    echo '.ingreso { color: green; }';
    echo '.egreso { color: red; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<table>';
    echo '<tr><td colspan="6" class="empresa">Plantulas Agrodex S.C. de P de R.L. de C.V.</td></tr>';
    echo '<tr><td colspan="6" class="titulo">REPORTE DE MOVIMIENTOS BANCARIOS</td></tr>';
    echo '<tr><td colspan="6">&nbsp;</td></tr>';
    
    // Información de la cuenta
    echo '<tr>';
    echo '<td colspan="2" class="label">Estado de Cuenta:</td>';
    echo '<td colspan="4">' . safe_html($cuenta['nombre']) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" class="label">Período:</td>';
    echo '<td colspan="4">Del ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" class="label">Banco:</td>';
    echo '<td colspan="4">' . safe_html($cuenta['banco']) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" class="label">Tipo de Cuenta:</td>';
    echo '<td colspan="4">' . safe_html($cuenta['tipo_cuenta']) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" class="label">Número de Cuenta:</td>';
    echo '<td colspan="4">' . safe_html($cuenta['numero']) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" class="label">Saldo Inicial:</td>';
    echo '<td colspan="4">' . format_currency($saldo_inicial_periodo) . '</td>';
    echo '</tr>';
    
    echo '<tr><td colspan="6">&nbsp;</td></tr>';
    
    // Encabezados de la tabla
    echo '<tr>';
    echo '<th>Fecha</th>';
    echo '<th>Concepto</th>';
    echo '<th>Ingreso</th>';
    echo '<th>Egreso</th>';
    echo '<th>Saldo</th>';
    echo '<th>Método Pago</th>';
    echo '</tr>';
    
    // Datos de movimientos
    if (empty($movimientos)) {
        echo '<tr><td colspan="6" style="text-align: center;">No hay movimientos en este período</td></tr>';
    } else {
        foreach ($movimientos as $mov) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y', strtotime($mov['fecha'])) . '</td>';
            echo '<td>' . safe_html($mov['concepto']) . '</td>';
            echo '<td class="ingreso">' . ($mov['ingreso'] > 0 ? format_currency($mov['ingreso']) : '') . '</td>';
            echo '<td class="egreso">' . ($mov['egreso'] > 0 ? format_currency($mov['egreso']) : '') . '</td>';
            echo '<td>' . format_currency($mov['saldo_acumulado']) . '</td>';
            echo '<td>' . safe_html($mov['metodo_pago']) . '</td>';
            echo '</tr>';
        }
    }
    
    echo '<tr><td colspan="6">&nbsp;</td></tr>';
    
    // Totales
    echo '<tr class="total">';
    echo '<td colspan="2">Total Ingresos:</td>';
    echo '<td class="ingreso">' . format_currency($total_ingresos) . '</td>';
    echo '<td></td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
    
    echo '<tr class="total">';
    echo '<td colspan="2">Total Egresos:</td>';
    echo '<td></td>';
    echo '<td class="egreso">' . format_currency($total_egresos) . '</td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
    
    echo '<tr class="total">';
    echo '<td colspan="2">Saldo Final:</td>';
    echo '<td colspan="2"></td>';
    echo '<td>' . format_currency($saldo_final) . '</td>';
    echo '<td></td>';
    echo '</tr>';
    
    echo '<tr><td colspan="6">&nbsp;</td></tr>';
    echo '<tr><td colspan="6" style="text-align: right; font-size: 10px;">Generado el ' . date('d/m/Y H:i:s') . '</td></tr>';
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-list-ul"></i> Movimientos de Cuenta: <?= safe_html($cuenta['nombre']) ?></h2>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= safe_html($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <form method="get" class="mb-4">
                <input type="hidden" name="id" value="<?= $id_cuenta ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= safe_html($fecha_inicio) ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                               value="<?= safe_html($fecha_fin) ?>" required>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        
                        <div class="btn-group">
                            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Exportar
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="generatePDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</a></li>
                                <li><a class="dropdown-item" href="?id=<?= $id_cuenta ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&export=excel"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-header">Saldo Inicial</div>
                        <div class="card-body">
                            <h5 class="card-title"><?= format_currency($saldo_inicial_periodo) ?></h5>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-header">Total Ingresos</div>
                        <div class="card-body">
                            <h5 class="card-title"><?= format_currency($total_ingresos) ?></h5>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-danger mb-3">
                        <div class="card-header">Total Egresos</div>
                        <div class="card-body">
                            <h5 class="card-title"><?= format_currency($total_egresos) ?></h5>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-header">Saldo Final</div>
                        <div class="card-body">
                            <h4 class="card-title"><?= format_currency($saldo_final) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Ingreso</th>
                            <th>Egreso</th>
                            <th>Saldo</th>
                            <th>Método Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay movimientos en este período</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                                <td><?= safe_html($mov['concepto']) ?></td>
                                <td class="text-success"><?= ($mov['ingreso'] > 0 ? format_currency($mov['ingreso']) : '') ?></td>
                                <td class="text-danger"><?= ($mov['egreso'] > 0 ? format_currency($mov['egreso']) : '') ?></td>
                                <td><?= format_currency($mov['saldo_acumulado']) ?></td>
                                <td><?= safe_html($mov['metodo_pago']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer">
            <a href="lista_cuentas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Cuentas
            </a>
        </div>
    </div>
</main>

<!-- Contenedor para el PDF (oculto pero accesible) -->
<div id="pdf-content" style="position: absolute; left: -9999px; width: 180mm; padding-right: 10mm; visibility: hidden;">
    <div style="text-align: center; margin-bottom: 5px; margin-right: 10mm;">
        <h1 style="font-size: 12px; font-weight: bold; margin: 0;">Plantulas Agrodex S.C. de P de R.L. de C.V.</h1>
        <h2 style="font-size: 11px; margin: 3px 0 0 0;">REPORTE DE MOVIMIENTOS BANCARIOS</h2>
    </div>
    
    <table style="width: 100%; margin-bottom: 5px; font-size: 9px; margin-right: 10mm;">
        <tr>
            <td style="width: 20%; font-weight: bold;">Estado de Cuenta:</td>
            <td style="width: 30%;"><?= safe_html($cuenta['nombre']) ?></td>
            <td style="width: 15%; font-weight: bold;">Período:</td>
            <td style="width: 35%;">Del <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Banco:</td>
            <td><?= safe_html($cuenta['banco']) ?></td>
            <td style="font-weight: bold;">Tipo de Cuenta:</td>
            <td><?= safe_html($cuenta['tipo_cuenta']) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Número de Cuenta:</td>
            <td><?= safe_html($cuenta['numero']) ?></td>
            <td style="font-weight: bold;">Saldo Inicial:</td>
            <td><?= format_currency($saldo_inicial_periodo) ?></td>
        </tr>
    </table>
    
    <table style="width: 95%; border-collapse: collapse; margin-top: 5px; font-size: 8px; margin-right: 10mm;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 12%;">Fecha</th>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 28%;">Concepto</th>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 12%;">Ingreso</th>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 12%;">Egreso</th>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 12%;">Saldo</th>
                <th style="border: 1px solid #000; padding: 2px; text-align: left; background-color: #f2f2f2; width: 24%;">Método Pago</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($movimientos)): ?>
                <?php foreach ($movimientos as $mov): ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 2px;"><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                    <td style="border: 1px solid #000; padding: 2px;"><?= safe_html($mov['concepto']) ?></td>
                    <td style="border: 1px solid #000; padding: 2px; color: green;"><?= ($mov['ingreso'] > 0 ? format_currency($mov['ingreso']) : '') ?></td>
                    <td style="border: 1px solid #000; padding: 2px; color: red;"><?= ($mov['egreso'] > 0 ? format_currency($mov['egreso']) : '') ?></td>
                    <td style="border: 1px solid #000; padding: 2px;"><?= format_currency($mov['saldo_acumulado']) ?></td>
                    <td style="border: 1px solid #000; padding: 2px;"><?= safe_html($mov['metodo_pago']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="border: 1px solid #000; padding: 2px; text-align: center;">No hay movimientos en este período</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 5px; border-top: 1px solid #000; padding-top: 3px; font-size: 9px; margin-right: 10mm;">
        <table style="width: 95%;">
            <tr>
                <td style="width: 40%; font-weight: bold;">Total Ingresos:</td>
                <td style="color: green;"><?= format_currency($total_ingresos) ?></td>
                <td style="width: 40%; font-weight: bold;">Total Egresos:</td>
                <td style="color: red;"><?= format_currency($total_egresos) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Saldo Final:</td>
                <td colspan="3"><strong><?= format_currency($saldo_final) ?></strong></td>
            </tr>
        </table>
    </div>
    
    <div style="text-align: right; margin-top: 5px; font-size: 8px; margin-right: 10mm;">
        Generado el <?= date('d/m/Y H:i:s') ?>
    </div>
</div>

<!-- Incluir la librería html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function generatePDF() {
    // Crear un nuevo div para el PDF
    const pdfContainer = document.createElement('div');
    pdfContainer.style.width = '180mm';
    pdfContainer.style.padding = '5mm 10mm 5mm 5mm'; // [top, right, bottom, left]
    
    // Clonar el contenido del pdf-content
    const content = document.getElementById('pdf-content').cloneNode(true);
    content.style.position = 'relative';
    content.style.left = '0';
    content.style.visibility = 'visible';
    
    // Agregar el contenido clonado al nuevo contenedor
    pdfContainer.appendChild(content);
    
    // Agregar temporalmente al cuerpo del documento
    document.body.appendChild(pdfContainer);
    
    const opt = {
        margin: [5, 10, 5, 5], // [top, right, bottom, left] - Margen derecho de 10mm
        filename: 'movimientos_<?= safe_html($cuenta["nombre"]) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            logging: true,
            useCORS: true,
            scrollX: 0,
            scrollY: 0,
            width: 180 * 3.78 // Convertir mm a px (1mm ≈ 3.78px)
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait',
            compress: true
        }
    };

    // Generar el PDF
    html2pdf().set(opt).from(pdfContainer).save().then(() => {
        // Eliminar el contenedor temporal después de generar el PDF
        document.body.removeChild(pdfContainer);
    });
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>