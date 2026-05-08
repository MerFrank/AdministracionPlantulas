<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

$titulo = 'Reportes Financieros';
$encabezado = 'Listar Reportes';

$ruta = "dashboard_cuentas.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>
<main class="container py-4">
    <section class="dashboard-grid mb-5" style="display: flex; justify-content: center; gap: 1.25rem; flex-wrap: wrap;">
        
        <!-- Tarjeta 1 - Reportes Egresos -->
        <div style="flex: 0 1 25rem; min-width: 17.5rem;">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                            <i class="bi bi-file-earmark-text text-info fs-2"></i>
                        </div>
                        <h2 class="h5 mb-0">Reportes Egresos</h2>
                    </div>
                    <p class="card-text">Genere reportes detallados de egresos.</p>
                    <a href="reportes_egresos.php" class="btn btn-outline-info stretched-link">
                        Acceder <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2 - Reportes Ingresos -->
        <div style="flex: 0 1 25rem; min-width: 17.5rem;">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                            <i class="bi bi-file-earmark-text text-info fs-2"></i>
                        </div>
                        <h2 class="h5 mb-0">Reportes Ingresos</h2>
                    </div>
                    <p class="card-text">Genere reportes detallados de ingresos.</p>
                    <p class="card-text text-danger">Pendiente.</p>
                    <a href="reportes_ingresos.php" class="btn btn-outline-info stretched-link">
                        Acceder <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
    </section>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>