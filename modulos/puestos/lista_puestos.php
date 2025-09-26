<?php
// Incluye el archivo de configuración para la conexión a la base de datos y otras constantes.
require_once __DIR__ . '/../../includes/config.php';

// Verificación de permisos: solo los administradores pueden ver esta página.
if (function_exists('verificarRol')) {
    verificarRol('admin');
}


$puestos = [];

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Consulta SQL para seleccionar todos los puestos ordenados por nombre.
    $sql = "SELECT * FROM puestos ORDER BY nombre";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error al obtener los puestos: " . $e->getMessage());
}

// Incluye el encabezado de la página.

// Variables para el encabezado y la página activa.
$titulo = "Puestos";
$encabezado = "Puestos Registrados";
$subtitulo = "Lista de todos los puestos de la organización.";
$active_page = "puestos";

//Botón
$texto_boton = "Regresar";
$ruta = "dashboard_puestos.php";
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-briefcase"></i> Lista de Puestos</h2>
                <a href="registro_puesto.php" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> Nuevo Puesto
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
                            <th>Descripción</th>
                            <th>Nivel Jerárquico</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($puestos)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No se encontraron puestos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($puestos as $puesto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($puesto['id_puesto']) ?></td>
                                    <td><?= htmlspecialchars($puesto['nombre']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($puesto['descripcion'], 0, 50)) . '...' ?></td>
                                    <td><?= htmlspecialchars($puesto['nivel_jerarquico']) ?></td>
                                    <td>
                                        <?php if ($puesto['activo'] == 1): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="editar_puesto.php?id_puesto=<?= $puesto['id_puesto'] ?>" 
                                           class="btn btn-sm btn-primary" 
                                           style="background-color: var(--color-accent); border-color: var(--color-accent);"
                                           title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="eliminar_puesto.php?id_puesto=<?= $puesto['id_puesto'] ?>" 
                                           class="btn btn-sm btn-primary" 
                                           style="background-color: var(--color-danger); border-color: var(--color-danger);" 
                                           title="Eliminar"
                                           onclick="return confirm('¿Estás seguro de eliminar este puesto?')">
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
// Incluye el pie de página.
require_once __DIR__ . '/../../includes/footer.php';
?>