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

$sucursales = $con->query("SELECT * FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();

$titulo = 'Sucursales';
$encabezado = 'Listado de Sucursales';
require('../../includes/header.php');
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-shop"></i> Sucursales</h2>
                <a href="registro_sucursal.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nueva Sucursal
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
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th>Responsable</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sucursales)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay sucursales registradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sucursales as $sucursal): ?>
                            <tr>
                                <td><?= htmlspecialchars($sucursal['nombre']) ?></td>
                                <td><?= htmlspecialchars($sucursal['direccion']) ?></td>
                                <td><?= htmlspecialchars($sucursal['telefono']) ?></td>
                                <td><?= htmlspecialchars($sucursal['responsable']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="editar_sucursal.php?id=<?= $sucursal['id_sucursal'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_sucursal.php?id=<?= $sucursal['id_sucursal'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar esta sucursal?')">
                                            <i class="bi bi-trash"></i>
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

<?php require('../../includes/footer.php'); ?>