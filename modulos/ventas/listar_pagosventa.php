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

// Obtener ID de la venta a mostrar
$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_venta <= 0) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener información de la venta
$sql_venta = $con->prepare("SELECT np.id_notaPedido, np.fechaPedido, c.nombre_Cliente as cliente 
                           FROM notaspedidos np 
                           INNER JOIN clientes c ON np.id_cliente = c.id_cliente 
                           WHERE np.id_notaPedido = ?");
$sql_venta->execute([$id_venta]);
$venta = $sql_venta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener pagos de la venta
$sql_pagos = $con->prepare("SELECT p.*, o.nombre as empleado 
                           FROM pagosventas p 
                           LEFT JOIN operadores o ON p.ID_Operador = o.ID_Operador 
                           WHERE p.id_notaPedido = ? 
                           ORDER BY p.fecha DESC");
$sql_pagos->execute([$id_venta]);
$pagos = $sql_pagos->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Pagos de la Venta #' . $venta['id_notaPedido'];
$encabezado = "Gestión de Pagos";

$ruta = "lista_ventas.php";
$texto_boton = "Volver a Ventas";

$opciones_menu = [
    'opcion1' => ['ruta' => 'dashboard_ventas.php', 'texto' => 'Panel ventas'],
    'opcion2' => ['ruta' => 'registro_abono.php?id_venta_auto=' . $id_venta, 'texto' => 'abono'],     
];
require __DIR__ .( '/../../includes/header.php');
?>

<main class="container mt-4">
        <!-- Mostrar mensajes de éxito/error -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-cash-coin"></i> Pagos de la Venta #<?= $venta['id_notaPedido'] ?></h2>
                
            </div>
            <div class="mt-2">
                <p class="mb-0">Fecha de venta: <?= $venta['fechaPedido'] ?></p>
                <p class="mb-0">Cliente: <?= htmlspecialchars($venta['cliente']) ?></p>
            </div>
        </div>

        <div class="card-body">
            <?php if (count($pagos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Pago</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Método de pago</th>
                                <th>Referencia</th>
                                <th>Observaciones</th>
                                <th>Empleado</th>
                                <?php if ($_SESSION['Rol'] == 1 ): ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $pago): ?>
                                <tr>
                                    <td><?= $pago['id_pago'] ?></td>
                                    <td><?= $pago['fecha'] ?></td>
                                    <td class="fw-bold">$<?= number_format($pago['monto'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($pago['metodo_pago']) ?></span>
                                    </td>
                                    <td><?= !empty($pago['referencia']) ? htmlspecialchars($pago['referencia']) : '<span class="text-muted">N/A</span>' ?></td>
                                    <td><?= !empty($pago['observaciones']) ? htmlspecialchars($pago['observaciones']) : '<span class="text-muted">Ninguna</span>' ?></td>
                                    <td><?= !empty($pago['empleado']) ? htmlspecialchars($pago['empleado']) : '<span class="text-muted">No especificado</span>' ?></td>
                                    <td>
                                        <?php if ($_SESSION['Rol'] == 1 ): ?>
                                            <div class="btn-group">
                                                <a href="editar_pago.php?id=<?= $pago['id_pago'] ?>" 
                                                 style="background-color: var(--color-accent); border-color: var(--color-accent);"
                                                class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> 
                                                </a>
                                                <a href="eliminar_pago.php?id=<?= $pago['id_pago'] ?>"
                                                 style="background-color: var(--color-danger); border-color: var(--color-danger);"
                                                 class="btn btn-sm btn-primary">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="2" class="text-end fw-bold">Total pagado:</td>
                                <td class="fw-bold">$<?= number_format(array_sum(array_column($pagos, 'monto')), 2) ?></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No se han registrado pagos para esta venta.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('a[href*="eliminar_pago"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres eliminar este pago?')) {
                e.preventDefault();
            }
        });
    });
});
</script>