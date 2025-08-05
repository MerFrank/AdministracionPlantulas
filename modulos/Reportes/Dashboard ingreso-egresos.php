<?php
// Variables para el encabezado
$titulo = "Panel Operaciones Financieras";
$encabezado = "Ingresos-Egresos";
$subtitulo = "Dashboard Principal";
$active_page = "dashboard";

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Obtener datos resumidos para el dashboard
try {
    $db = new Database();
    $con = $db->conectar();
    
    // Totales del mes actual
    $ingresosMes = $con->query("SELECT SUM(total) FROM NotasPedidos 
                               WHERE MONTH(fechaPedido) = MONTH(CURRENT_DATE()) 
                               AND YEAR(fechaPedido) = YEAR(CURRENT_DATE())")->fetchColumn();
    
    $egresosMes = $con->query("SELECT SUM(monto) FROM egresos 
                              WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) 
                              AND YEAR(fecha) = YEAR(CURRENT_DATE())")->fetchColumn();
    
    $balanceMes = $ingresosMes - $egresosMes;
    
    // Últimos 6 meses para gráfico
    $datosMensuales = $con->query("
        SELECT 
            DATE_FORMAT(fecha, '%Y-%m') as mes,
            SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
            SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
        FROM (
            SELECT fechaPedido as fecha, total as monto, 'ingreso' as tipo FROM NotasPedidos
            UNION ALL
            SELECT fecha, monto, 'egreso' as tipo FROM egresos
        ) as transacciones
        WHERE fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha, '%Y-%m')
        ORDER BY mes
    ")->fetchAll();
    
    // Preparar datos para gráfico
    $labels = [];
    $ingresosData = [];
    $egresosData = [];
    
    foreach($datosMensuales as $mes) {
        $labels[] = date('M Y', strtotime($mes['mes'] . '-01'));
        $ingresosData[] = $mes['ingresos'];
        $egresosData[] = $mes['egresos'];
    }
    
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}
?>

<main class="container-fluid py-4">
    <div class="row">
        <!-- Tarjetas resumen -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-success border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-success fw-bold text-uppercase mb-1">Ingresos (Mes)</div>
                            <div class="h5 mb-0 fw-bold">$<?= number_format($ingresosMes, 2) ?></div>
                        </div>
                        <i class="fas fa-arrow-up fa-2x text-success"></i>
                    </div>
                </div>
                <a href="Ingresos.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-danger border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-danger fw-bold text-uppercase mb-1">Egresos (Mes)</div>
                            <div class="h5 mb-0 fw-bold">$<?= number_format($egresosMes, 2) ?></div>
                        </div>
                        <i class="fas fa-arrow-down fa-2x text-danger"></i>
                    </div>
                </div>
                <a href="Egresos.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-info border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-info fw-bold text-uppercase mb-1">Balance (Mes)</div>
                            <div class="h5 mb-0 fw-bold <?= $balanceMes >= 0 ? 'text-info' : 'text-warning' ?>">
                                $<?= number_format(abs($balanceMes), 2) ?> <?= $balanceMes >= 0 ? '' : '(' . number_format(abs($balanceMes), 2) . ')' ?>
                            </div>
                        </div>
                        <i class="fas fa-balance-scale fa-2x text-info"></i>
                    </div>
                </div>
                <a href="analisis_general.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-primary border-5 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-primary fw-bold text-uppercase mb-1">Reportes</div>
                            <div class="h6 mb-0">Ver todos los reportes</div>
                        </div>
                        <i class="fas fa-file-alt fa-2x text-primary"></i>
                    </div>
                </div>
                <a href="Reporte_Ingresos_Egresos.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de tendencia -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tendencia últimos 6 meses</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="tendenciaChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Últimas transacciones -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Últimos ingresos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ultimosIngresos = $con->query("
                                    SELECT np.fechaPedido, c.nombre_Cliente, np.total 
                                    FROM NotasPedidos np
                                    JOIN Clientes c ON np.id_cliente = c.id_cliente
                                    ORDER BY np.fechaPedido DESC LIMIT 5
                                ")->fetchAll();
                                
                                foreach($ultimosIngresos as $ingreso):
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($ingreso['fechaPedido'])) ?></td>
                                    <td><?= htmlspecialchars($ingreso['nombre_Cliente']) ?></td>
                                    <td class="text-success fw-bold">$<?= number_format($ingreso['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Últimos egresos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ultimosEgresos = $con->query("
                                    SELECT e.fecha, e.concepto, e.monto 
                                    FROM egresos e
                                    ORDER BY e.fecha DESC LIMIT 5
                                ")->fetchAll();
                                
                                foreach($ultimosEgresos as $egreso):
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($egreso['concepto']) ?></td>
                                    <td class="text-danger fw-bold">$<?= number_format($egreso['monto'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de tendencia
    const ctx = document.getElementById('tendenciaChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Ingresos',
                    data: <?= json_encode($ingresosData) ?>,
                    borderColor: 'rgba(40, 167, 69, 1)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Egresos',
                    data: <?= json_encode($egresosData) ?>,
                    borderColor: 'rgba(220, 53, 69, 1)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.3,
                    fill: true
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
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>