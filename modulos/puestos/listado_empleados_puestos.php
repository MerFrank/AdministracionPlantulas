<?php
// Incluye el archivo de configuración y la clase de base de datos.
require_once __DIR__ . '/../../includes/config.php';

// Variables para el encabezado
$titulo = "Empleados";
$encabezado = "Empleados y Puestos Asignados";
$subtitulo = "Lista de todos los empleados y sus puestos actuales.";
$active_page = "empleados";

$empleados = [];

try {
    $db = new Database();
    $con = $db->conectar();
    

    
    $sql = "SELECT e.id_empleado, e.nombre AS nombre_empleado, e.apellido_paterno, e.email, 
                   p.nombre AS nombre_puesto, p.nivel_jerarquico 
            FROM empleados AS e
            LEFT JOIN empleado_puesto AS ep ON e.id_empleado = ep.id_empleado
            LEFT JOIN puestos AS p ON ep.id_puesto = p.id_puesto
            ORDER BY e.nombre, e.apellido_paterno";

    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener el listado: " . $e->getMessage());
}

// Incluye el encabezado de la página.

//Botón
$texto_boton = "";
$ruta = "dashboard_puestos.php";

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0"><i class="bi bi-people-fill"></i> Listado de Empleados y Puestos</h2>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover mt-3">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Correo Electrónico</th>
                            <th>Puesto Asignado</th>
                            <th>Nivel Jerárquico</th>

                            <th>Acciones</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empleados)): ?>
                            <tr>

                                <td colspan="5" class="text-center">No se encontraron empleados o asignaciones de puestos.</td>

                            </tr>
                        <?php else: ?>
                            <?php foreach ($empleados as $empleado): ?>
                                <tr>

                                    <td><?= htmlspecialchars($empleado['nombre_empleado'] . ' ' . $empleado['apellido_paterno']) ?></td>
                                    <td><?= htmlspecialchars($empleado['email']) ?></td>
                                    <td><?= htmlspecialchars($empleado['nombre_puesto'] ?? 'Sin puesto') ?></td>
                                    <td><?= htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A') ?></td>
                                    <td>
                                        <a href="/AdministracionPlantulas/modulos/puestos/historial_puestos.php?id=<?= $empleado['id_empleado'] ?>" 
                                        class="btn btn-sm btn-primary" title="Historial Puestos">
                                            <i class="bi bi-clock-history"></i> Historial
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