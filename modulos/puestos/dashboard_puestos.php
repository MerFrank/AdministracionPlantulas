<?php
// Incluye el archivo de configuración para la conexión a la base de datos y otras constantes.
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Gestión de Puestos";
$subtitulo = "Panel de administración de puestos y asignaciones";
$active_page = "puestos";

// Obtener estadísticas de puestos
$total_puestos = 0;
$total_asignaciones = 0;
$empleados = [];

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Total de puestos activos
    $sql = "SELECT COUNT(*) as total FROM puestos WHERE activo = 1";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $total_puestos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de empleados con puesto asignado
    $sql = "SELECT COUNT(DISTINCT id_empleado) as total FROM empleado_puesto WHERE fecha_fin IS NULL";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $total_asignaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener listado de empleados asignados a puestos activos
    $sql_empleados = "SELECT e.nombre AS nombre_empleado, e.email, p.nombre AS nombre_puesto, p.nivel_jerarquico 
            FROM empleados AS e
            LEFT JOIN empleado_puesto AS ep ON e.id_empleado = ep.id_empleado
            LEFT JOIN puestos AS p ON ep.id_puesto = p.id_puesto
            WHERE p.activo = 1
            ORDER BY e.nombre";
    
    $stmt_empleados = $con->prepare($sql_empleados);
    $stmt_empleados->execute();
    $empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);
    
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
                <i class="bi bi-briefcase-fill me-1"></i>
                <?= htmlspecialchars($total_puestos ?? '0') ?> puestos
            </span>
            <span class="badge bg-info rounded-pill">
                <i class="bi bi-person-check me-1"></i>
                <?= htmlspecialchars($total_asignaciones ?? '0') ?> asignaciones
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
                                <i class="bi bi-briefcase text-primary fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Registrar Puesto</h2>
                        </div>
                        <p class="card-text">Registra nuevos puestos en la empresa.</p>
                        <a href="registro_puesto.php" class="btn btn-outline-primary stretched-link">
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
                                <i class="bi bi-list-ul text-success fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Listar Puestos</h2>
                        </div>
                        <p class="card-text">Consulte el listado completo de puestos registrados.</p>
                        <a href="lista_puestos.php" class="btn btn-outline-success stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-person-plus text-warning fs-2"></i>
                            </div>
                            <h2 class="h5 mb-0">Asignar Puestos</h2>
                        </div>
                        <p class="card-text">Asigna puestos a empleados con sueldo y horarios.</p>
                        <a href="asignar_puesto.php" class="btn btn-outline-warning stretched-link">
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
                <h2 class="h5 mb-0"><i class="bi bi-person-check-fill me-2"></i> Empleados Asignados a Puestos Activos</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Correo Electrónico</th>
                                <th>Puesto Asignado</th>
                                <th>Nivel Jerárquico</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empleados)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No se encontraron empleados asignados a puestos activos.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($empleados as $empleado): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($empleado['nombre_empleado']) ?></td>
                                        <td><?= htmlspecialchars($empleado['email']) ?></td>
                                        <td><?= htmlspecialchars($empleado['nombre_puesto'] ?? 'Sin puesto') ?></td>
                                        <td><?= htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>