<?php
require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Actividades Extra";
$encabezado = "Gestión de Actividades Extra";
$subtitulo = "Administre las actividades y pagos adicionales";
$active_page = "actividades";

$actividades = [];

try {
    $db = new Database();
    $con = $db->conectar();
    
    $sql = "SELECT * FROM actividades_extras ORDER BY nombre";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener actividades: " . $e->getMessage());
}


//Botón
$texto_boton = "";
$ruta = "dashboard_puestos.php";

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-star"></i> Actividades Extra</h2>
                <a href="registrar_actividad.php" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> Nueva Actividad
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover mt-3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Pago Extra</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($actividades)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No se encontraron actividades registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($actividades as $actividad): ?>
                                <tr>
                                    <td><?= htmlspecialchars($actividad['id_actividad']) ?></td>
                                    <td><?= htmlspecialchars($actividad['nombre']) ?></td>
                                    <td>$<?= number_format($actividad['pago_extra'], 2) ?></td>
                                    <td><?= ($actividad['activo'] == 1) ? 'Activa' : 'Inactiva' ?></td>
                                    <td>
                                        <a href="editar_actividad.php?id_actividad=<?= $actividad['id_actividad'] ?>" 

                                            class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_actividad.php?id_actividad=<?= $actividad['id_actividad'] ?>" 
                                            class="btn btn-sm btn-danger" title="Eliminar"
                                            onclick="return confirm('¿Está seguro de eliminar la actividad: <?= addslashes($actividad['nombre']) ?>?')">
                                                <i class="bi bi-trash"></i>

                                        </a>
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

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>