<?php
// ==============================================
// SECCIÓN PHP - CONFIGURACIÓN Y LÓGICA PRINCIPAL
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';

// Conexión a la base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener estadísticas de cotizaciones (ahora con "completadas" en lugar de "aprobadas")
$estadisticas = $con->query("
SELECT 
COUNT(*) as total_cotizaciones,
SUM(total) as monto_total,
(SELECT COUNT(*) FROM cotizaciones WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as cotizaciones_recientes,
(SELECT COUNT(*) FROM cotizaciones WHERE estado = 'completado') as completadas
FROM cotizaciones
")->fetch();

// Obtener últimas cotizaciones registradas
$ultimas_cotizaciones = $con->query("
SELECT c.id_cotizacion, c.folio, c.fecha, c.total, cl.nombre_Cliente as cliente_nombre, c.estado
FROM cotizaciones c
JOIN clientes cl ON c.id_cliente = cl.id_cliente
ORDER BY c.fecha DESC
LIMIT 5
")->fetchAll();


// Configuración de la página
$titulo = "Panel de Cotizaciones";
$encabezado = "Panel de adCotizaciones";
$subtitulo = "Crea y gestiona cotizaciones.";

$active_page = "cotizaciones";
// boton
$ruta = "../../session/login.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

?>

<!-- ============================================== -->
<!-- SECCIÓN HTML - CONTENIDO PRINCIPAL -->
<!-- ============================================== -->

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-file-text"></i> Gestión de Cotizaciones</h1>
            <p class="lead mb-0">Panel de administración de cotizaciones</p>
        </div>
        <div class="user-info">
            <span class="me-2"><?php echo htmlspecialchars($_SESSION['Nombre'] ?? 'Usuario'); ?></span>
            <i class="bi bi-person-circle"></i>
        </div>
    </div>

    <!-- Sección de Estadísticas -->
     <div class="row mb-4 g-4">
            <!-- Tarjeta 1: Total Ventas -->
            <div class="col-md-3">
                <div class="card shadow-sm border-success h-100">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-currency-dollar text-success fs-2"></i>
                            </div>
                            <div>
                            <h3 class="h5 mb-0">Total Cotizaciones</h3>
                            <p class="fs-3 mb-0"><?= $estadisticas['total_cotizaciones'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-success h-100 ">
                <div class="card-body">

                    <div class="text-center">
                        <div class="bg-success bg-opacity-10 p-3  rounded me-3">
                            <i class="bi bi-check-circle text-success fs-2"></i>
                        </div>
                        <div>
                            <h3 class="h5 mb-0">Completadas</h3>
                            <p class="fs-3 mb-0"><?= $estadisticas['completadas'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-info h-100 ">
                <div class="card-body">

                    <div class="text-center">
                        <div class="bg-info bg-opacity-10 p-3  rounded me-3">
                            <i class="bi bi-clock-history text-info fs-2"></i>
                        </div>
                        <div>
                            <h3 class="h5 mb-0">Últimos 30 días</h3>
                            <p class="fs-3 mb-0"><?= $estadisticas['cotizaciones_recientes'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-warning h-100 ">
                <div class="card-body">

                    <div class="text-center">
                        <div class="bg-warning bg-opacity-10 p-3 py-1 rounded me-3">
                            <i class="bi bi-cash-stack text-warning fs-2"></i>
                        </div>
                        <div>
                            <h3 class="h5 mb-0">Monto Total</h3>
                            <p class="fs-3 mb-0">$<?= number_format($estadisticas['monto_total'] ?? 0, 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Contenido Principal -->
    <div class="row g-4">
        <!-- Últimas cotizaciones -->
        <div class="col-lg-7">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0"><i class="bi bi-clock-history"></i> Últimas Cotizaciones</h3>
                        <a href="lista_cotizaciones.php" class="btn btn-sm btn-light">Ver Todas</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimas_cotizaciones)): ?>
                        <div class="alert alert-info mb-0">No hay cotizaciones registradas</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimas_cotizaciones as $cotizacion): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cotizacion['folio']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></td>
                                        <td><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></td>
                                        <td>$<?= number_format($cotizacion['total'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $cotizacion['estado'] == 'completado' ? 'success' : 
                                                ($cotizacion['estado'] == 'pendiente' ? 'warning' : 'secondary') 
                                            ?>">
                                                <?= ucfirst($cotizacion['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="col-lg-5">
            <div class="row g-4">
                <div class="col-md-6">  
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-plus-circle text-primary fs-1"></i>
                            </div>
                            <h3 class="h5">Nueva Cotización</h3>
                            <p class="text-muted">Crear una nueva cotización</p>
                            <a href="registro_cotizacion.php" class="btn btn-primary stretched-link">
                                <i class="bi bi-plus-circle me-1"></i> Crear
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-list-ul text-success fs-1"></i>
                            </div>
                            <h3 class="h5">Lista de Cotizaciones</h3>
                            <p class="text-muted">Ver todas las cotizaciones</p>
                            <a href="lista_cotizaciones.php" class="btn btn-success stretched-link">
                                <i class="bi bi-list-ul me-1"></i> Ver Lista
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-people text-warning fs-1"></i>
                            </div>
                            <h3 class="h5">Clientes</h3>
                            <p class="text-muted">Administrar clientes</p>
                            <a href="../clientes/lista_clientes.php" class="btn btn-warning stretched-link">
                                <i class="bi bi-people me-1"></i> Clientes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// ==============================================
// SECCIÓN JAVASCRIPT
// ==============================================
require_once __DIR__ . '/../../includes/footer.php';
?>