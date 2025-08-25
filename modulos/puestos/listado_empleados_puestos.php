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
    
    // Consulta SQL para obtener empleados y sus puestos asignados.
    // Se utiliza un JOIN para combinar las tablas 'empleados', 'empleado_puesto' y 'puestos'.
    $sql = "SELECT e.nombre AS nombre_empleado, e.apellido, e.email, p.nombre AS nombre_puesto, p.nivel_jerarquico 
            FROM empleados AS e
            LEFT JOIN empleado_puesto AS ep ON e.id_empleado = ep.id_empleado
            LEFT JOIN puestos AS p ON ep.id_puesto = p.id_puesto
            ORDER BY e.nombre, e.apellido";
    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener el listado: " . $e->getMessage());
}

// Incluye el encabezado de la página.
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empleados)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No se encontraron empleados o asignaciones de puestos.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empleados as $empleado): ?>
                                <tr>
                                    <td><?= htmlspecialchars($empleado['nombre_empleado'] . ' ' . $empleado['apellido']) ?></td>
                                    <td><?= htmlspecialchars($empleado['email']) ?></td>
                                    <td><?= htmlspecialchars($empleado['nombre_puesto'] ?? 'Sin puesto') ?></td>
                                    <td><?= htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A') ?></td>
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