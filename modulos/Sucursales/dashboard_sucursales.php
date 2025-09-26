
<?php
// Configuración de la página
$titulo = "Panel de Sucursales";
$encabezado = "Gestión de Sucursales";
$subtitulo = "Administra las sucursales de tu sistema";
$active_page = "sucursales";

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';
$ruta = "../../session/login.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';

// Conexión a la base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener estadísticas de sucursales
$estadisticas = $con->query("
    SELECT 
        COUNT(*) as total_sucursales,
        (SELECT COUNT(*) FROM sucursales WHERE activo = 1) as sucursales_activas
    FROM sucursales
")->fetch();

// Obtener sucursales recientes (últimas 5 activas)
$sucursales_recientes = $con->query("
    SELECT nombre, direccion, telefono, responsable, fecha_creacion 
    FROM sucursales 
    WHERE activo = 1 
    ORDER BY fecha_creacion DESC 
    LIMIT 5
")->fetchAll();

?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="bi bi-shop"></i> <?php echo htmlspecialchars($encabezado); ?></h1>
                <p class="lead mb-0"><?php echo htmlspecialchars($subtitulo); ?></p>
            </div>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['Nombre'] ?? 'Usuario'); ?></span>
                <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <!-- Sección de Estadísticas -->
        <div class="row mb-4 g-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-primary h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-shop text-primary fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Total Sucursales</h3>
                                <p class="fs-3 mb-0"><?= number_format($estadisticas['total_sucursales'] ?? 0) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-success h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-check-circle text-success fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Sucursales Activas</h3>
                                <p class="fs-3 mb-0"><?= number_format($estadisticas['sucursales_activas'] ?? 0) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Contenido Principal -->
        <div class="row g-4">
            <!-- Sucursales Recientes -->
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h3 class="h5 mb-0"><i class="bi bi-clock-history me-2"></i>Sucursales Recientes</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sucursales_recientes)): ?>
                            <div class="alert alert-info mb-0">No hay sucursales recientes</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Dirección</th>
                                            <th>Teléfono</th>
                                            <th>Responsable</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sucursales_recientes as $sucursal): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sucursal['nombre']) ?></td>
                                            <td><?= htmlspecialchars($sucursal['direccion']) ?></td>
                                            <td><?= htmlspecialchars($sucursal['telefono'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($sucursal['responsable'] ?? 'N/A') ?></td>
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
            <div class="col-lg-4">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-plus-circle text-primary fs-1"></i>
                                </div>
                                <h3 class="h5">Registrar Sucursal</h3>
                                <p class="text-muted">Agregar una nueva sucursal al sistema</p>
                                <a href="registro_sucursal.php" class="btn btn-primary stretched-link">
                                    <i class="bi bi-plus-circle me-1"></i> Nueva Sucursal
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-list-ul text-success fs-1"></i>
                                </div>
                                <h3 class="h5">Lista de Sucursales</h3>
                                <p class="text-muted">Ver todas las sucursales registradas</p>
                                <a href="lista_sucursales.php" class="btn btn-success stretched-link">
                                    <i class="bi bi-list-ul me-1"></i> Ver Lista
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

