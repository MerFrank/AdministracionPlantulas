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

// Obtener cuentas con información de movimientos recientes
$cuentas = $con->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM egresos WHERE id_cuenta = c.id_cuenta) as num_movimientos,
           (SELECT MAX(fecha) FROM egresos WHERE id_cuenta = c.id_cuenta) as ultimo_movimiento
    FROM cuentas_bancarias c 
    WHERE c.activo = 1 
    ORDER BY c.nombre
")->fetchAll();

$titulo = 'Cuentas Bancarias';
$encabezado = 'Listado de Cuentas Bancarias';
$ruta = "dashboard_cuentas.php";
$texto_boton = "";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-bank"></i> Cuentas Bancarias</h2>
                <a href="registro_cuenta.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nueva Cuenta
                </a>
            </div>
        </div>
        
        <div class="card-body">
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
                            <th>Nombre</th>
                            <th>Banco</th>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Saldo</th>
                            <th>Movimientos</th>
                            <th>Último Movimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cuentas)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No hay cuentas registradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cuentas as $cuenta): ?>
                            <tr>
                                <td><?= htmlspecialchars($cuenta['nombre']) ?></td>
                                <td><?= htmlspecialchars($cuenta['banco']) ?></td>
                                <td><?= htmlspecialchars($cuenta['tipo_cuenta']) ?></td>
                                <td><?= htmlspecialchars($cuenta['numero']) ?></td>
                                <td>$<?= number_format($cuenta['saldo_actual'], 2) ?></td>
                                <td><?= $cuenta['num_movimientos'] ?></td>
                                <td><?= $cuenta['ultimo_movimiento'] ? date('d/m/Y', strtotime($cuenta['ultimo_movimiento'])) : 'N/A' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="editar_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar esta cuenta? Esta acción no se puede deshacer.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <a href="movimientos_cuenta.php?id=<?= $cuenta['id_cuenta'] ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-list-ul"></i> Movimientos
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
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
