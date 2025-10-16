<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Variables para el encabezado
$titulo = "Dashboard Analítico";
$encabezado = "Resumen Financiero";
$subtitulo = "Visualización de Ingresos y Egresos";
$active_page = "analisis";

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Procesar parámetros del filtro
$filtro = $_GET['filtro'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

// Ajustar fechas según el filtro seleccionado
switch($filtro) {
    case 'dia':
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d');
        break;
    case 'semana':
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
}

// Obtener datos para gráficos
try {
    $db = new Database();
    $con = $db->conectar();
    
    // Totales del período
    $totalIngresos = $con->query("SELECT SUM(total) FROM NotasPedidos 
                                 WHERE fechaPedido BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetchColumn();
    $totalEgresos = $con->query("SELECT SUM(monto) FROM egresos 
                                WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetchColumn();
    $balance = $totalIngresos - $totalEgresos;
    
    // Datos para gráfico comparativo
    $datosComparativo = [
        'labels' => [],
        'ingresos' => [],
        'egresos' => []
    ];
    
    // Consulta según el tipo de filtro
    if($filtro == 'dia') {
        // Datos por hora
        $query = "
            SELECT 
                HOUR(fecha) as hora,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
            FROM (
                SELECT fechaPedido as fecha, total as monto, 'ingreso' as tipo FROM NotasPedidos
                WHERE DATE(fechaPedido) = '$fecha_inicio'
                UNION ALL
                SELECT fecha, monto, 'egreso' as tipo FROM egresos
                WHERE DATE(fecha) = '$fecha_inicio'
            ) as transacciones
            GROUP BY HOUR(fecha)
            ORDER BY hora
        ";
        
        $result = $con->query($query)->fetchAll();
        
        for($h = 0; $h < 24; $h++) {
            $datosComparativo['labels'][] = "$h:00";
            $datosComparativo['ingresos'][] = 0;
            $datosComparativo['egresos'][] = 0;
        }
        
        foreach($result as $row) {
            $hora = $row['hora'];
            $datosComparativo['ingresos'][$hora] = $row['ingresos'];
            $datosComparativo['egresos'][$hora] = $row['egresos'];
        }
    } 
    elseif($filtro == 'semana') {
        // Datos por día de la semana
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        
        $query = "
            SELECT 
                DAYOFWEEK(fecha) as dia_semana,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
            FROM (
                SELECT fechaPedido as fecha, total as monto, 'ingreso' as tipo FROM NotasPedidos
                WHERE fechaPedido BETWEEN '$fecha_inicio' AND '$fecha_fin'
                UNION ALL
                SELECT fecha, monto, 'egreso' as tipo FROM egresos
                WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
            ) as transacciones
            GROUP BY DAYOFWEEK(fecha)
            ORDER BY dia_semana
        ";
        
        $result = $con->query($query)->fetchAll();
        
        // Inicializar todos los días con 0
        foreach($diasSemana as $index => $dia) {
            $datosComparativo['labels'][] = $dia;
            $datosComparativo['ingresos'][] = 0;
            $datosComparativo['egresos'][] = 0;
        }
        
        // Llenar con datos reales (MySQL devuelve DAYOFWEEK de 1=Dom a 7=Sab)
        foreach($result as $row) {
            $indice = $row['dia_semana'] - 1; // Ajustar para que Dom=0, Lun=1, etc.
            $datosComparativo['ingresos'][$indice] = $row['ingresos'];
            $datosComparativo['egresos'][$indice] = $row['egresos'];
        }
        
        // Reordenar para que la semana empiece en Lunes
        array_unshift($datosComparativo['labels'], array_pop($datosComparativo['labels']));
        array_unshift($datosComparativo['ingresos'], array_pop($datosComparativo['ingresos']));
        array_unshift($datosComparativo['egresos'], array_pop($datosComparativo['egresos']));
    }
    else {
        // Datos por día para mes o período personalizado
        $query = "
            SELECT 
                DATE(fecha) as fecha_dia,
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
            FROM (
                SELECT fechaPedido as fecha, total as monto, 'ingreso' as tipo FROM NotasPedidos
                WHERE fechaPedido BETWEEN '$fecha_inicio' AND '$fecha_fin'
                UNION ALL
                SELECT fecha, monto, 'egreso' as tipo FROM egresos
                WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
            ) as transacciones
            GROUP BY DATE(fecha)
            ORDER BY fecha_dia
        ";
        
        $result = $con->query($query)->fetchAll();
        
        foreach($result as $row) {
            $datosComparativo['labels'][] = date('d/m', strtotime($row['fecha_dia']));
            $datosComparativo['ingresos'][] = $row['ingresos'];
            $datosComparativo['egresos'][] = $row['egresos'];
        }
    }
    
    // Distribución de ingresos por cliente (top 5)
    $ingresosPorCliente = $con->query("
        SELECT c.nombre_Cliente as cliente, SUM(np.total) as total
        FROM NotasPedidos np
        JOIN Clientes c ON np.id_cliente = c.id_cliente
        WHERE np.fechaPedido BETWEEN '$fecha_inicio' AND '$fecha_fin'
        GROUP BY c.nombre_Cliente
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll();
    
    // Distribución de egresos por tipo
    $egresosPorTipo = $con->query("
        SELECT t.nombre as tipo, SUM(e.monto) as total
        FROM egresos e
        JOIN tipos_egreso t ON e.id_tipo_egreso = t.id_tipo
        WHERE e.fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
        GROUP BY t.nombre
        ORDER BY total DESC
    ")->fetchAll();
    
    // Distribución de egresos por proveedor (top 5)
    $egresosPorProveedor = $con->query("
        SELECT p.nombre_proveedor as proveedor, SUM(e.monto) as total
        FROM egresos e
        JOIN proveedores p ON e.id_proveedor = p.id_proveedor
        WHERE e.fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
        GROUP BY p.nombre_proveedor
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}
?>

<main class="container-fluid py-4">
    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Filtrar por:</label>
                    <select class="form-select" name="filtro" id="selectFiltro">
                        <option value="dia" <?= $filtro == 'dia' ? 'selected' : '' ?>>Hoy</option>
                        <option value="semana" <?= $filtro == 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="mes" <?= $filtro == 'mes' ? 'selected' : '' ?>>Este mes</option>
                        <option value="personalizado" <?= $filtro == 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                
                <div class="col-md-4" id="fechaInicioContainer" style="<?= $filtro != 'personalizado' ? 'display:none;' : '' ?>">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                </div>
                
                <div class="col-md-4" id="fechaFinContainer" style="<?= $filtro != 'personalizado' ? 'display:none;' : '' ?>">
                    <label class="form-label">Fecha fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Aplicar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjetas resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-success border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-success fw-bold text-uppercase mb-1">Ingresos</div>
                            <div class="h5 mb-0 fw-bold">$<?= number_format($totalIngresos, 2) ?></div>
                        </div>
                        <i class="fas fa-arrow-up fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-danger border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-danger fw-bold text-uppercase mb-1">Egresos</div>
                            <div class="h5 mb-0 fw-bold">$<?= number_format($totalEgresos, 2) ?></div>
                        </div>
                        <i class="fas fa-arrow-down fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-info border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-info fw-bold text-uppercase mb-1">Balance</div>
                            <div class="h5 mb-0 fw-bold <?= $balance >= 0 ? 'text-info' : 'text-warning' ?>">
                                $<?= number_format(abs($balance), 2) ?>
                            </div>
                        </div>
                        <i class="fas fa-balance-scale fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-primary border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-primary fw-bold text-uppercase mb-1">Período</div>
                            <div class="h6 mb-0">
                                <?= $filtro == 'dia' ? date('d/m/Y', strtotime($fecha_inicio)) : 
                                  ($filtro == 'semana' ? date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)) : 
                                  date('F Y', strtotime($fecha_inicio))) ?>
                            </div>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfico comparativo -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Comparativo Ingresos vs Egresos</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative; height: 400px;">
                <canvas id="graficoComparativo"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gráficos de distribución -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribución de Ingresos</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="graficoIngresos"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribución de Egresos</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container" style="position: relative; height: 250px;">
                                <canvas id="graficoEgresosTipo"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container" style="position: relative; height: 250px;">
                                <canvas id="graficoEgresosProveedor"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Mostrar/ocultar campos de fecha según filtro
document.getElementById('selectFiltro').addEventListener('change', function() {
    const esPersonalizado = this.value === 'personalizado';
    document.getElementById('fechaInicioContainer').style.display = esPersonalizado ? 'block' : 'none';
    document.getElementById('fechaFinContainer').style.display = esPersonalizado ? 'block' : 'none';
});

// Configuración común para gráficos
const chartOptions = {
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
};

// Gráfico comparativo
const ctxComp = document.getElementById('graficoComparativo').getContext('2d');
new Chart(ctxComp, {
    type: 'bar',
    data: {
        labels: <?= json_encode($datosComparativo['labels']) ?>,
        datasets: [
            {
                label: 'Ingresos',
                data: <?= json_encode($datosComparativo['ingresos']) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            },
            {
                label: 'Egresos',
                data: <?= json_encode($datosComparativo['egresos']) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
            }
        ]
    },
    options: chartOptions
});

// Gráfico de ingresos por cliente
const ctxIng = document.getElementById('graficoIngresos').getContext('2d');
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
const ctxEgrTipo = document.getElementById('graficoEgresosTipo').getContext('2d');
new Chart(ctxEgrTipo, {
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
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Por Tipo',
                font: {
                    size: 14
                }
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

// Gráfico de egresos por proveedor
const ctxEgrProv = document.getElementById('graficoEgresosProveedor').getContext('2d');
new Chart(ctxEgrProv, {
    type: 'pie',
    data: {
        labels: [<?php foreach ($egresosPorProveedor as $proveedor) echo "'" . addslashes($proveedor['proveedor']) . "',"; ?>],
        datasets: [{
            data: [<?php foreach ($egresosPorProveedor as $proveedor) echo $proveedor['total'] . ','; ?>],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Por Proveedor',
                font: {
                    size: 14
                }
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>