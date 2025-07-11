<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$tipos = $con->query("SELECT * FROM tipos_egreso WHERE activo = 1 ORDER BY nombre")->fetchAll();

$titulo = 'Tipos de Egreso';
$encabezado = 'Listado de Tipos de Egreso';
require __DIR__ . '/../../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-tags"></i> Tipos de Egreso</h2>
                <a href="registro_tipo.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Nuevo Tipo
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
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tipos)): ?>
                            <tr>
                                <td colspan="3" class="text-center">No hay tipos registrados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tipos as $tipo): ?>
                            <tr>
                                <td><?= htmlspecialchars($tipo['nombre']) ?></td>
                                <td><?= htmlspecialchars($tipo['descripcion']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="editar_tipo.php?id=<?= $tipo['id_tipo'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_tipo.php?id=<?= $tipo['id_tipo'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar este tipo?')">
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

<?php require __DIR__ . '/../../../includes/footer.php'; ?>