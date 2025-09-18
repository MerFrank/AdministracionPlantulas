<?php
// Configuración de la página
$titulo = "Panel de Ventas";
$encabezado = "Gestión de Ventas";
$subtitulo = "Panel de administración de ventas";
$active_page = "ventas";
$ruta = "../../session/login.php";
$texto_boton = "Regresar";

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Conexión a la base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener estadísticas de ventas (modificado para usar pagosventas)
$estadisticas = $con->query("
    SELECT 
        COUNT(*) as total_ventas,
        SUM(total) as monto_total,
        (SELECT COUNT(*) FROM notaspedidos WHERE fechaPedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as ventas_recientes,
        (SELECT SUM(total) FROM notaspedidos WHERE estado = 'pendiente' OR estado = 'parcial') as pendientes,
        (SELECT SUM(monto) FROM pagosventas) as total_abonos,
        (SELECT SUM(saldo_pendiente) FROM notaspedidos WHERE tipo_pago = 'credito') as credito_pendiente
    FROM notaspedidos
")->fetch();

// Calcular saldos
$saldo_pendiente = ($estadisticas['pendientes'] ?? 0) - ($estadisticas['total_abonos'] ?? 0);
$saldo_credito = max($estadisticas['credito_pendiente'] ?? 0, 0);

// Obtener últimas ventas registradas
$ultimas_ventas = $con->query("
    SELECT np.id_notaPedido as id_venta, np.total, np.fechaPedido as fecha, 
           c.nombre_Cliente as cliente_nombre, np.estado, np.tipo_pago, np.saldo_pendiente
    FROM notaspedidos np
    LEFT JOIN clientes c ON np.id_cliente = c.id_cliente
    ORDER BY np.fechaPedido DESC
    LIMIT 5
")->fetchAll();
?>


    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="bi bi-cart-check"></i> <?php echo htmlspecialchars($encabezado); ?></h1>
                <p class="lead mb-0"><?php echo htmlspecialchars($subtitulo); ?></p>
            </div>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
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
                                <h3 class="h5 mb-0">Total Ventas</h3>
                                <p class="fs-3 mb-0">$<?= number_format($estadisticas['monto_total'] ?? 0, 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 2: Ventas Registradas -->
            <div class="col-md-3">
                <div class="card shadow-sm border-primary h-100">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-list-check text-primary fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Ventas Registradas</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['total_ventas'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 3: Últimos 30 días -->
            <div class="col-md-3">
                <div class="card shadow-sm border-info h-100">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-clock-history text-info fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Últimos 30 días</h3>
                                <p class="fs-3 mb-0"><?= $estadisticas['ventas_recientes'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 4: Saldo Pendiente (NUEVA) -->
            <div class="col-md-3">
                <div class="card shadow-sm border-danger h-100">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-cash-coin text-danger fs-2"></i>
                            </div>
                            <div>
                                <h3 class="h5 mb-0">Saldo Pendiente</h3>
                                <p class="fs-3 mb-0">$<?= number_format($saldo_credito, 2) ?></p>
                                <small class="text-muted">(Crédito - Abonos)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Contenido Principal -->
        <div class="row g-4">
            <!-- Últimas ventas -->
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="bi bi-clock-history"></i> Últimas Ventas</h3>
                            <a href="lista_ventas.php" class="btn btn-sm btn-light">Ver Todas</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimas_ventas)): ?>
                            <div class="alert alert-info mb-0">No hay ventas registradas</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_ventas as $venta): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($venta['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Sin cliente') ?></td>
                                            <td>$<?= number_format($venta['total'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $venta['estado'] == 'completado' ? 'success' : ($venta['estado'] == 'pendiente' ? 'secondary' : 'warning') ?>">
                                                    <?= ucfirst($venta['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($venta['tipo_pago'] == 'Crédito'): ?>
                                                    $<?= number_format($venta['saldo_pendiente'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
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
            <div class="col-lg-6">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-plus-circle text-primary fs-1"></i>
                                </div>
                                <h3 class="h5">Nueva Venta</h3>
                                <p class="text-muted">Registrar una nueva venta en el sistema</p>
                                <a href="registro_venta.php" class="btn btn-primary stretched-link">
                                    <i class="bi bi-plus-circle me-1"></i> Nueva Venta
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
                                <h3 class="h5">Lista de Ventas</h3>
                                <p class="text-muted">Ver todas las ventas registradas</p>
                                <a href="lista_ventas.php" class="btn btn-success stretched-link">
                                    <i class="bi bi-list-ul me-1"></i> Ver Lista
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-file-earmark-text text-info fs-1"></i>
                                </div>
                                <h3 class="h5">Desde Cotización</h3>
                                <p class="text-muted">Crear venta desde una cotización existente</p>
                                <a href="venta_desde_cotizacion.php" class="btn btn-info stretched-link">
                                    <i class="bi bi-file-earmark-text me-1"></i> Usar Cotización
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                    <i class="bi bi-receipt text-warning fs-1"></i>
                                </div>
                                <h3 class="h5">Registrar Abono</h3>
                                <p class="text-muted">Registrar pago a créditos pendientes</p>
                                <a href="registro_abono.php" class="btn btn-warning stretched-link">
                                    <i class="bi bi-cash-coin me-1"></i> Abonar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/../../includes/footer.php'; ?>