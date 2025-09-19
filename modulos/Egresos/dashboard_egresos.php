<?php
// Configuración de la página
$titulo = "Panel de Egresos";
$encabezado = "Gestión de Egresos";
$subtitulo = "Panel de administración de egresos";
$active_page = "egresos";

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';
$ruta = "../../session/login.php";
$texto_boton = "";
require_once __DIR__ . '/../../includes/header.php';

// Conexión a la base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener estadísticas de egresos
$estadisticas = $con->query("
    SELECT 
        COUNT(*) as total_egresos,
        SUM(monto) as monto_total,
        (SELECT COUNT(*) FROM egresos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as egresos_recientes
    FROM egresos
")->fetch();

// Obtener últimos egresos registrados
$ultimos_egresos = $con->query("
    SELECT e.id_egreso, e.monto, e.fecha, e.concepto, c.nombre as cuenta_nombre
    FROM egresos e
    JOIN cuentas_bancarias c ON e.id_cuenta = c.id_cuenta
    ORDER BY e.fecha DESC
    LIMIT 5
")->fetchAll();
?>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="bi bi-cash-coin"></i> <?php echo htmlspecialchars($encabezado); ?></h1>
                <p class="lead mb-0"><?php echo htmlspecialchars($subtitulo); ?></p>
            </div>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['Nombre'] ?? 'Usuario'); ?></span>
                <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <!-- Sección de Estadísticas -->
        <div class="row mb-4 g-4">
            <?php if ($_SESSION['Rol'] == 1 ): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm border-danger h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                    <i class="bi bi-cash-stack text-danger fs-2"></i>
                                </div>
                                <div>
                                    <h3 class="h5 mb-0">Total Egresos</h3>
                                    <p class="fs-3 mb-0">$<?= number_format($estadisticas['monto_total'] ?? 0, 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-md-4">
                <div class="card shadow-sm border-warning h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-list-check text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Egresos Registrados</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['total_egresos'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-info h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-clock-history text-info fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Últimos 30 días</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['egresos_recientes'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Contenido Principal -->
        <div class="row g-4">
            <!-- Últimos egresos -->
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="bi bi-clock-history"></i> Últimos Egresos</h3>
                            <a href="lista_egresos.php" class="btn btn-sm btn-light">Ver Todos</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimos_egresos)): ?>
                            <div class="alert alert-info mb-0">No hay egresos registrados</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Concepto</th>
                                            <th>Cuenta</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimos_egresos as $egreso): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($egreso['concepto']) ?></td>
                                            <td><?= htmlspecialchars($egreso['cuenta_nombre']) ?></td>
                                            <td class="text-danger">-$<?= number_format($egreso['monto'], 2) ?></td>
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
            <div class="col-lg-6">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-plus-circle text-primary fs-1"></i>
                                </div>
                                <h3 class="h5">Registrar Egreso</h3>
                                <p class="text-muted">Registrar un nuevo egreso en el sistema</p>
                                <a href="Registro_egreso.php" class="btn btn-primary stretched-link">
                                    <i class="bi bi-plus-circle me-1"></i> Nuevo Egreso
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-plus-circle text-primary fs-1"></i>
                                </div>
                                <h3 class="h5">Registrar Tipos de Egreso</h3>
                                <p class="text-muted">Registrar un nuevo tipo de egreso</p>
                                <a href="./tipos_egreso/registro_tipo.php" class="btn btn-primary stretched-link">
                                    <i class="bi bi-plus-circle me-1"></i> Nuevo Egreso
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
                                <h3 class="h5">Lista de Egresos</h3>
                                <p class="text-muted">Ver todos los egresos registrados</p>
                                <a href="lista_egresos.php" class="btn btn-success stretched-link">
                                    <i class="bi bi-list-ul me-1"></i> Ver Lista
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
                                <h3 class="h5">Lista Tipos de Egresos</h3>
                                <p class="text-muted">Ver todos los tipos de egresos</p>
                                <a href="./tipos_egreso/lista_tipos.php" class="btn btn-success stretched-link">
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
