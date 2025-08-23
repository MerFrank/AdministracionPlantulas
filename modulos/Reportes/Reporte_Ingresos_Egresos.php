<?php
// Variables para el encabezado
$titulo = "Reporte General";
$encabezado = "Ingresos y Egresos";
$subtitulo = "Resumen consolidado";
$active_page = "reporte";
$texto_boton = "Reporte"; // Definir variable para el header

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Obtener parámetros de filtrado
$tipo_filtro = $_GET['tipo_filtro'] ?? 'rango';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$semana = $_GET['semana'] ?? date('Y-\WW');

// Procesar parámetros según el tipo de filtro
if ($tipo_filtro == 'semana') {
    if (preg_match('/^(\d{4})-W(\d{2})$/', $semana, $matches)) {
        $fecha_inicio = date('Y-m-d', strtotime($matches[1] . 'W' . $matches[2] . '1'));
        $fecha_fin = date('Y-m-d', strtotime($matches[1] . 'W' . $matches[2] . '7'));
    } else {
        $semana = date('Y-\WW');
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
    }
} else {
    if ($fecha_inicio > $fecha_fin) {
        $temp = $fecha_inicio;
        $fecha_inicio = $fecha_fin;
        $fecha_fin = $temp;
    }
}

// Obtener datos para el reporte
try {
    $db = new Database();
    $con = $db->conectar();
    
    // CONSULTA CORREGIDA PARA ONLY_FULL_GROUP_BY
    $sql = "SELECT 
                DATE(fecha) as fecha,
                ANY_VALUE(DATE_FORMAT(fecha, '%W')) as dia_semana,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) - 
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as balance
            FROM (
                SELECT fechaPedido as fecha, total as monto, 'ingreso' as tipo FROM NotasPedidos
                UNION ALL
                SELECT fecha, monto, 'egreso' as tipo FROM egresos
            ) as transacciones
            WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY DATE(fecha)
            ORDER BY fecha";
    
    $stmt = $con->prepare($sql);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $datosDiarios = $stmt->fetchAll();
    
    // Totales generales (usando consultas preparadas)
    $sqlIngresos = "SELECT IFNULL(SUM(total), 0) FROM NotasPedidos WHERE fechaPedido BETWEEN :fecha_inicio AND :fecha_fin";
    $stmtIngresos = $con->prepare($sqlIngresos);
    $stmtIngresos->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $totalIngresos = $stmtIngresos->fetchColumn();
    
    $sqlEgresos = "SELECT IFNULL(SUM(monto), 0) FROM egresos WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin";
    $stmtEgresos = $con->prepare($sqlEgresos);
    $stmtEgresos->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $totalEgresos = $stmtEgresos->fetchColumn();
    
    $balance = $totalIngresos - $totalEgresos;
    
    // Egresos por tipo (corregido para ONLY_FULL_GROUP_BY)
    $egresosPorTipo = $con->prepare("
        SELECT t.id_tipo, t.nombre as tipo, IFNULL(SUM(e.monto), 0) as total
        FROM egresos e
        JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
        WHERE e.fecha BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY t.id_tipo, t.nombre
        ORDER BY total DESC
    ");
    $egresosPorTipo->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $egresosPorTipo = $egresosPorTipo->fetchAll();
    
    // Ingresos por cliente (corregido para ONLY_FULL_GROUP_BY)
    $ingresosPorCliente = $con->prepare("
        SELECT c.id_cliente, c.nombre_Cliente as cliente, IFNULL(SUM(np.total), 0) as total
        FROM NotasPedidos np
        JOIN Clientes c ON np.id_cliente = c.id_cliente
        WHERE np.fechaPedido BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY c.id_cliente, c.nombre_Cliente
        ORDER BY total DESC
        LIMIT 10
    ");
    $ingresosPorCliente->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $ingresosPorCliente = $ingresosPorCliente->fetchAll();
    
    // Semanas disponibles
    $semanasDisponibles = $con->query("
        SELECT DISTINCT CONCAT(YEAR(fecha), '-W', LPAD(WEEK(fecha, 3), 2, '0')) as semana
        FROM (
            SELECT fechaPedido as fecha FROM NotasPedidos
            UNION
            SELECT fecha FROM egresos
        ) as fechas
        ORDER BY semana DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Error al generar reporte: " . $e->getMessage());
}
?>
<main class="container-fluid py-4">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-file-alt me-2"></i>Reporte Consolidado</h2>
                <div>
                    <button class="btn btn-light btn-sm" onclick="exportarPDF()">
                        <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                    </button>
                    <button class="btn btn-light btn-sm ms-2" onclick="exportarExcel()">
                        <i class="fas fa-file-excel me-1"></i>Exportar Excel
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filtros -->
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Filtro</label>
                    <select class="form-select" name="tipo_filtro" id="tipoFiltro">
                        <option value="rango" <?= $tipo_filtro == 'rango' ? 'selected' : '' ?>>Rango de Fechas</option>
                        <option value="semana" <?= $tipo_filtro == 'semana' ? 'selected' : '' ?>>Semana Específica</option>
                    </select>
                </div>
                
                <div class="col-md-3 filtro-rango" style="<?= $tipo_filtro != 'rango' ? 'display: none;' : '' ?>">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?= $tipo_filtro == 'rango' ? htmlspecialchars($fecha_inicio) : '' ?>">
                </div>
                <div class="col-md-3 filtro-rango" style="<?= $tipo_filtro != 'rango' ? 'display: none;' : '' ?>">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?= $tipo_filtro == 'rango' ? htmlspecialchars($fecha_fin) : '' ?>">
                </div>
                
                <div class="col-md-3 filtro-semana" style="<?= $tipo_filtro != 'semana' ? 'display: none;' : '' ?>">
                    <label class="form-label">Seleccionar Semana</label>
                    <select class="form-select" name="semana">
                        <?php foreach ($semanasDisponibles as $semanaOpt): ?>
                        <option value="<?= htmlspecialchars($semanaOpt['semana']) ?>" 
                            <?= ($tipo_filtro == 'semana' && $semanaOpt['semana'] == $semana) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($semanaOpt['semana']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filtrar
                    </button>
                    <a href="Reporte_Ingresos_Egresos.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </form>
            
            <!-- Período seleccionado -->
            <div class="alert alert-info mb-4">
                <strong>Período seleccionado:</strong> 
                <?php if ($tipo_filtro == 'semana'): ?>
                    Semana <?= htmlspecialchars($semana) ?> 
                    (<?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?>)
                <?php else: ?>
                    <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                <?php endif; ?>
            </div>
            
            <!-- Resumen general -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Ingresos Totales</h5>
                            <p class="display-6 text-success">$<?= number_format($totalIngresos, 2) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-danger h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Egresos Totales</h5>
                            <p class="display-6 text-danger">$<?= number_format($totalEgresos, 2) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 <?= $balance >= 0 ? 'border-info' : 'border-warning' ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title">Balance Neto</h5>
                            <p class="display-6 <?= $balance >= 0 ? 'text-info' : 'text-warning' ?>">
                                $<?= number_format(abs($balance), 2) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico comparativo -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Comparativo Ingresos vs Egresos</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="comparativoChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de datos por día -->
            <div class="card shadow mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Detalle por Día</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="tablaDias">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Día</th>
                                    <th>Ingresos</th>
                                    <th>Egresos</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datosDiarios as $dia): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($dia['fecha'])) ?></td>
                                    <td><?= $dia['dia_semana'] ?></td>
                                    <td class="text-success">$<?= number_format($dia['ingresos'], 2) ?></td>
                                    <td class="text-danger">$<?= number_format($dia['egresos'], 2) ?></td>
                                    <td class="<?= $dia['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        $<?= number_format(abs($dia['balance']), 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos de distribución -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Distribución de Ingresos por Cliente</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="ingresosClienteChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Distribución de Egresos por Tipo</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="egresosTipoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<script>
// Mostrar/ocultar filtros según tipo seleccionado
document.getElementById('tipoFiltro').addEventListener('change', function() {
    const tipo = this.value;
    document.querySelectorAll('.filtro-rango').forEach(el => {
        el.style.display = tipo === 'rango' ? 'block' : 'none';
    });
    document.querySelectorAll('.filtro-semana').forEach(el => {
        el.style.display = tipo === 'semana' ? 'block' : 'none';
    });
});

// Función para exportar a PDF
function exportarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    
    // Título del reporte
    doc.setFontSize(18);
    doc.text('Reporte Financiero', 140, 15, { align: 'center' });
    doc.setFontSize(12);
    doc.text(`Período: ${document.querySelector('input[name="fecha_inicio"]').value} al ${document.querySelector('input[name="fecha_fin"]').value}`, 140, 22, { align: 'center' });
    
    // Datos de resumen
    doc.setFontSize(14);
    doc.text('Resumen General', 20, 35);
    doc.setFontSize(12);
    
    doc.text('Ingresos Totales:', 20, 45);
    doc.text(`$${<?= $totalIngresos ?>}`, 60, 45);
    
    doc.text('Egresos Totales:', 20, 55);
    doc.text(`$${<?= $totalEgresos ?>}`, 60, 55);
    
    doc.text('Balance Neto:', 20, 65);
    doc.text(`$${<?= $balance ?>}`, 60, 65);
    
    // Tabla de datos
    doc.setFontSize(14);
    doc.text('Detalle por Día', 20, 80);
    
    // Crear tabla
    const headers = [['Fecha', 'Día', 'Ingresos', 'Egresos', 'Balance']];
    const data = [
        <?php foreach ($datosDiarios as $dia): ?>
        [
            '<?= date('d/m/Y', strtotime($dia['fecha'])) ?>',
            '<?= $dia['dia_semana'] ?>',
            `$${<?= $dia['ingresos'] ?>}`,
            `$${<?= $dia['egresos'] ?>}`,
            `$${<?= $dia['balance'] ?>}`
        ],
        <?php endforeach; ?>
    ];
    
    doc.autoTable({
        startY: 85,
        head: headers,
        body: data,
        margin: { left: 20 },
        styles: { fontSize: 10 },
        columnStyles: {
            0: { cellWidth: 'auto' },
            1: { cellWidth: 'auto' },
            2: { cellWidth: 'auto' },
            3: { cellWidth: 'auto' },
            4: { cellWidth: 'auto' }
        }
    });
    
    // Guardar PDF
    doc.save(`Reporte_Financiero_${new Date().toISOString().slice(0,10)}.pdf`);
}

// Función para exportar a Excel
function exportarExcel() {
    // Crear libro de trabajo
    const wb = XLSX.utils.book_new();
    
    // Hoja de resumen
    const resumenData = [
        ["Reporte Financiero", "", "", "", ""],
        ["Período:", document.querySelector('input[name="fecha_inicio"]').value + " al " + document.querySelector('input[name="fecha_fin"]').value, "", "", ""],
        ["Tipo de Filtro:", document.querySelector('select[name="tipo_filtro"]').value, "", "", ""],
        ["", "", "", "", ""],
        ["Ingresos Totales", <?= $totalIngresos ?>, "", "", ""],
        ["Egresos Totales", <?= $totalEgresos ?>, "", "", ""],
        ["Balance Neto", <?= $balance ?>, "", "", ""],
        ["", "", "", "", ""],
        ["Detalle por Día", "", "", "", ""],
        ["Fecha", "Día", "Ingresos", "Egresos", "Balance"]
    ];
    
    <?php foreach ($datosDiarios as $dia): ?>
    resumenData.push([
        '<?= date('d/m/Y', strtotime($dia['fecha'])) ?>',
        '<?= $dia['dia_semana'] ?>',
        <?= $dia['ingresos'] ?>,
        <?= $dia['egresos'] ?>,
        <?= $dia['balance'] ?>
    ]);
    <?php endforeach; ?>
    
    const wsResumen = XLSX.utils.aoa_to_sheet(resumenData);
    XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen");
    
    // Hoja de ingresos por cliente
    const ingresosClienteData = [
        ["Cliente", "Monto"]
    ];
    
    <?php foreach ($ingresosPorCliente as $cliente): ?>
    ingresosClienteData.push([
        '<?= $cliente['cliente'] ?>',
        <?= $cliente['total'] ?>
    ]);
    <?php endforeach; ?>
    
    const wsIngresosCliente = XLSX.utils.aoa_to_sheet(ingresosClienteData);
    XLSX.utils.book_append_sheet(wb, wsIngresosCliente, "Ingresos por Cliente");
    
    // Hoja de egresos por tipo
    const egresosTipoData = [
        ["Tipo de Egreso", "Monto"]
    ];
    
    <?php foreach ($egresosPorTipo as $tipo): ?>
    egresosTipoData.push([
        '<?= $tipo['tipo'] ?>',
        <?= $tipo['total'] ?>
    ]);
    <?php endforeach; ?>
    
    const wsEgresosTipo = XLSX.utils.aoa_to_sheet(egresosTipoData);
    XLSX.utils.book_append_sheet(wb, wsEgresosTipo, "Egresos por Tipo");
    
    // Guardar archivo
    XLSX.writeFile(wb, `Reporte_Financiero_${new Date().toISOString().slice(0,10)}.xlsx`);
}

// Gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico comparativo
    const ctxComp = document.getElementById('comparativoChart').getContext('2d');
    new Chart(ctxComp, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($datosDiarios as $dia): ?>
                '<?= date('d/m', strtotime($dia['fecha'])) ?>',
                <?php endforeach; ?>
            ],
            datasets: [
                {
                    label: 'Ingresos',
                    data: [<?php foreach ($datosDiarios as $dia) echo $dia['ingresos'] . ','; ?>],
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Egresos',
                    data: [<?php foreach ($datosDiarios as $dia) echo $dia['egresos'] . ','; ?>],
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.raw.toLocaleString(undefined, {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de ingresos por cliente
    const ctxIng = document.getElementById('ingresosClienteChart').getContext('2d');
    new Chart(ctxIng, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($ingresosPorCliente as $cliente) echo "'" . addslashes($cliente['cliente']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach ($ingresosPorCliente as $cliente) echo $cliente['total'] . ','; ?>],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(25, 135, 84, 0.7)',
                    'rgba(13, 110, 253, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(111, 66, 193, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${context.label}: $${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de egresos por tipo
    const ctxEgr = document.getElementById('egresosTipoChart').getContext('2d');
    new Chart(ctxEgr, {
        type: 'pie',
        data: {
            labels: [<?php foreach ($egresosPorTipo as $tipo) echo "'" . addslashes($tipo['tipo']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach ($egresosPorTipo as $tipo) echo $tipo['total'] . ','; ?>],
                backgroundColor: [
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(253, 13, 57, 0.7)',
                    'rgba(214, 51, 132, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(32, 201, 151, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${context.label}: $${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // DataTable para la tabla de días
    $('#tablaDias').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-MX.json'
        },
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        pageLength: 10,
        order: [[0, 'desc']]
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>