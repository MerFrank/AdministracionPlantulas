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

$titulo = 'Entregas de Pedidos';
$encabezado = 'Reportes entregas';
$ruta = "dashboard_ventas.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>
<main class="container py-4">

    <section class="dashboard-grid mb-5">

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-clipboard2-plus text-success fs-1"></i>
                        </div>
                        <h3 class="h5">Registrar entrega de pedidos</h3>
                        <p class="text-muted">Manten el registro de las fechas de registros de los pedidos</p>
                        <a href="registro_entregas.php" class="btn btn-info stretched-link">
                            <i class="bi me-1"></i> Acceder
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-truck text-info fs-1"></i>
                        </div>
                        <h3 class="h5">Reporte Entregas</h3>
                        <p class="text-muted">Generar reportes detallados de entregas</p>
                        <a href="lista_entregas.php" class="btn btn-info stretched-link">
                            <i class="bi me-1"></i> Acceder
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </section>

</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>