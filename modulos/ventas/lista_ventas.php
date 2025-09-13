<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Consulta para obtener las ventas con información de cliente (modificada para usar pagosventas)
$ventas = $con->query("
    SELECT np.*, c.nombre_Cliente as cliente_nombre, o.Nombre as vendedor,
           (SELECT SUM(monto) FROM pagosventas WHERE id_notaPedido = np.id_notaPedido) as pagado
    FROM notaspedidos np
    LEFT JOIN clientes c ON np.id_cliente = c.id_cliente
    LEFT JOIN operadores o ON np.ID_Operador = o.ID_Operador
    ORDER BY np.fechaPedido DESC
")->fetchAll();

$titulo = 'Listado de Ventas';
$ruta = "dashboard_ventas.php";
$texto_boton = "";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-cart-check"></i> Listado de Ventas</h2>
                <div>
                    <a href="registro_venta.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Nueva Venta
                    </a>
                    <a href="venta_desde_cotizacion.php" class="btn btn-info">
                        <i class="bi bi-file-earmark-text"></i> Desde Cotización
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Pagado</th>
                        <th>Estado</th>
                        <th>Vendedor</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay ventas registradas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?= htmlspecialchars($venta['num_remision']) ?></td>
                            <td><?= date('d/m/Y', strtotime($venta['fechaPedido'])) ?></td>
                            <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Sin cliente') ?></td>
                            <td>$<?= number_format($venta['total'], 2) ?></td>
                            <td>$<?= number_format($venta['pagado'] ?? 0, 2) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $venta['estado'] == 'completado' ? 'success' : 
                                    ($venta['estado'] == 'parcial' ? 'warning' : 
                                    ($venta['estado'] == 'cancelado' ? 'danger' : 'secondary')) 
                                ?>">
                                    <?= ucfirst($venta['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($venta['vendedor'] ?? 'Sin informacion') ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="detalle_venta.php?id=<?= $venta['id_notaPedido'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="editar_venta.php?id=<?= $venta['id_notaPedido'] ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="generar_nota.php?id=<?= $venta['id_notaPedido'] ?>" class="btn btn-sm btn-info" target="_blank">
                                        <i class="bi bi-receipt"></i> Nota
                                    </a>
                                    <a href="listar_pagosventa.php?id=<?= $venta['id_notaPedido'] ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-receipt"></i>Pagos
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>