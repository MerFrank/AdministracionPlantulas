<?php
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Obtener estadísticas de cuentas
$estadisticas = $con->query("
    SELECT 
        COUNT(*) as total_cuentas,
        SUM(saldo_actual) as saldo_total,
        (SELECT COUNT(*) FROM egresos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as movimientos_recientes
    FROM cuentas_bancarias 
    WHERE activo = 1
")->fetch();

// Obtener cuentas con saldos más altos
$cuentas_top = $con->query("
    SELECT nombre, banco, saldo_actual 
    FROM cuentas_bancarias 
    WHERE activo = 1 
    ORDER BY saldo_actual DESC 
    LIMIT 5
")->fetchAll();

// Obtener últimos movimientos
$ultimos_movimientos = $con->query("
    SELECT e.id_egreso, e.monto, e.fecha, e.concepto, c.nombre as cuenta_nombre
    FROM egresos e
    JOIN cuentas_bancarias c ON e.id_cuenta = c.id_cuenta
    WHERE c.activo = 1
    ORDER BY e.fecha DESC
    LIMIT 5
")->fetchAll();

// Configuración de la página
$titulo = 'Dashboard de Cuentas Bancarias';
$encabezado = 'Panel de Control - Cuentas Bancarias';
$active_page = 'cuentas';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?> - Plantulas</title>
    <!-- Favicon y estilos ya incluidos desde header.php -->
</head>
<body class="dashboard-body">
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-bank"></i> <?php echo htmlspecialchars($encabezado); ?></h1>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
                <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Sección de Estadísticas -->
        <div class="row mb-4 g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-primary h-100">
                    <div class="card-body">
                        <div class="center-text">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-wallet2 text-primary fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Total en Cuentas</h3>
                                <p class="fs-3 mb-0">$<?= number_format($estadisticas['saldo_total'] ?? 0, 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-success h-100">
                    <div class="card-body">
                        <div class="center-text">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-bank text-success fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Cuentas Activas</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['total_cuentas'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-info h-100">
                    <div class="card-body">
                        <div class="center-text">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-arrow-left-right text-info fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Movimientos (30 días)</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['movimientos_recientes'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Contenido Principal -->
        <div class="row g-4">
            <!-- Cuentas con mayor saldo -->
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="bi bi-trophy"></i> Cuentas con Mayor Saldo</h3>
                            <a href="lista_cuentas.php" class="btn btn-sm btn-light">Ver Todas</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cuentas_top)): ?>
                            <div class="alert alert-info mb-0">No hay cuentas registradas</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cuenta</th>
                                            <th>Banco</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cuentas_top as $cuenta): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cuenta['nombre']) ?></td>
                                            <td><?= htmlspecialchars($cuenta['banco']) ?></td>
                                            <td>$<?= number_format($cuenta['saldo_actual'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Últimos movimientos -->
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="bi bi-clock-history"></i> Últimos Movimientos</h3>
                            <a href="movimientos_cuenta.php" class="btn btn-sm btn-light">Ver Todos</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimos_movimientos)): ?>
                            <div class="alert alert-info mb-0">No hay movimientos recientes</div>
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
                                        <?php foreach ($ultimos_movimientos as $movimiento): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($movimiento['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($movimiento['concepto']) ?></td>
                                            <td><?= htmlspecialchars($movimiento['cuenta_nombre']) ?></td>
                                            <td class="text-danger">-$<?= number_format($movimiento['monto'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="row mt-4 g-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-plus-circle text-primary fs-1"></i>
                        </div>
                        <h3 class="h5">Nueva Cuenta</h3>
                        <p>Registrar una nueva cuenta bancaria</p>
                        <a href="registro_cuenta.php" class="btn btn-primary stretched-link">Crear</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-list-ul text-success fs-1"></i>
                        </div>
                        <h3 class="h5">Lista de Cuentas</h3>
                        <p>Ver y administrar todas las cuentas</p>
                        <a href="lista_cuentas.php" class="btn btn-success stretched-link">Ver Lista</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-graph-up text-info fs-1"></i>
                        </div>
                        <h3 class="h5">Reportes</h3>
                        <p>Generar reportes financieros</p>
                        <a href="reportes_cuentas.php" class="btn btn-info stretched-link">Generar</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>