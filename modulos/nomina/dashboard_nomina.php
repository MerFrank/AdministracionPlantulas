<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Nómina";
$encabezado = "Dashboard de Nómina";
$subtitulo = "Panel de administración de nómina";
$active_page = "nomina";

// Obtener estadísticas de nómina
try {
    $db = new Database();
    $con = $db->conectar();
    
    // Total de nóminas generadas (períodos distintos)
    $sql = "SELECT COUNT(DISTINCT id_periodo) as total FROM nominas";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $total_nominas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nómina del mes actual
    $mes_actual = date('Y-m');
    $sql = "SELECT COUNT(*) as total, COALESCE(SUM(sueldo_neto), 0) as monto 
            FROM nominas WHERE id_periodo = ? AND estatus = 'pagada'";
    $stmt = $con->prepare($sql);
    $stmt->execute([$mes_actual]);
    $nomina_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Próxima nómina a generar
    $sql = "SELECT MAX(id_periodo) as ultimo_periodo FROM nominas";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $ultimo_periodo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo_periodo'];
    
} catch (PDOException $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1"><?= htmlspecialchars($encabezado) ?></h1>
            <p class="lead text-muted mb-0"><?= htmlspecialchars($subtitulo) ?></p>
        </div>
        <div class="stats-badge">
            <span class="badge bg-primary rounded-pill me-2">
                <i class="bi bi-cash-coin me-1"></i>
                <?= htmlspecialchars($total_nominas ?? '0') ?> nóminas
            </span>
            <span class="badge bg-success rounded-pill">
                <i class="bi bi-currency-dollar me-1"></i>
                $<?= number_format($nomina_mes['monto'] ?? 0, 2) ?>
            </span>
        </div>
    </div>

    <section class="dashboard-grid mb-5">
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-plus-circle text-primary fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Generar Nómina</h2>
                        </div>
                        <p class="card-text">Genere una nueva nómina para el período actual.</p>
                        <a href="generar_nomina.php" class="btn btn-outline-primary stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-list-check text-success fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Historial de Nóminas</h2>
                        </div>
                        <p class="card-text">Consulte el historial completo de nóminas generadas.</p>
                        <a href="historial_nominas.php" class="btn btn-outline-success stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-file-earmark-text text-info fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Reportes de Nómina</h2>
                        </div>
                        <p class="card-text">Genere reportes detallados de nómina.</p>
                        <a href="reporte_nomina.php" class="btn btn-outline-info stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="card shadow">
            <div class="card-header bg-secondary text-white">
                <h2 class="h5 mb-0"><i class="bi bi-calendar-check me-2"></i> Próxima Nómina</h2>
            </div>
            <div class="card-body">
                <?php if ($ultimo_periodo): ?>
                    <?php
                    $ultimo = new DateTime($ultimo_periodo . '-01');
                    $proximo = clone $ultimo;
                    $proximo->modify('+1 month');
                    ?>
                    <p class="mb-1">Último período procesado: <strong><?= $ultimo->format('F Y') ?></strong></p>
                    <p class="mb-0">Próximo período a procesar: <strong><?= $proximo->format('F Y') ?></strong></p>
                <?php else: ?>
                    <p class="mb-0">No hay nóminas generadas aún. Puede generar la primera nómina.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>

<style>
.dashboard-grid .card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    border-radius: 10px;
    overflow: hidden;
}

.dashboard-grid .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-badge .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.8rem;
}

.card-body {
    padding: 1.5rem;
}

.card-text {
    color: #6c757d;
    margin-bottom: 1.5rem;
}
</style>