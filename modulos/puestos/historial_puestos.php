<?php

require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos
// if (function_exists('verificarRol')) {
//     verificarRol('admin');
// }

// Obtener ID del empleado
$id_empleado = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug: verificar que el ID está llegando
if ($id_empleado <= 0) {
    die("Error: No se recibió un ID de empleado válido. ID recibido: " . ($_GET['id'] ?? 'Ninguno'));
}


try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener información del empleado

    $sql_empleado = "SELECT id_empleado, nombre, apellido_paterno, apellido_materno 
 
                     FROM empleados WHERE id_empleado = ?";
    $stmt_empleado = $con->prepare($sql_empleado);
    $stmt_empleado->execute([$id_empleado]);
    $empleado = $stmt_empleado->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {

        // Debug adicional para ver qué ID se está buscando
        die("Empleado no encontrado. ID buscado: " . $id_empleado . 
            ". ¿Existe el empleado con este ID en la base de datos?");

    }
    
    // Obtener historial de puestos
    $sql_historial = "SELECT ep.*, p.nombre as puesto_nombre 
                      FROM empleado_puesto ep
                      JOIN puestos p ON ep.id_puesto = p.id_puesto
                      WHERE ep.id_empleado = ?
                      ORDER BY ep.fecha_inicio DESC";
    $stmt_historial = $con->prepare($sql_historial);
    $stmt_historial->execute([$id_empleado]);
    $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}


// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Historial de Puestos";
$subtitulo = "Historial de puestos para: " . htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']);
$active_page = "puestos";
$texto_boton = "";
$ruta = "dashboard_puestos.php";
require_once __DIR__ . '/../../includes/header.php';

?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-clock-history"></i> Historial de Puestos</h2>

            <h3 class="h5 mb-0"><?= htmlspecialchars($empleado['apellido_paterno'] . ' ' . ($empleado['apellido_materno'] ?? '') . ', ' . $empleado['nombre']) ?></h3>
        </div>
        
        <div class="card-body">
            <div class="d-flex justify-content-between mb-4">
                <a href="asignar_puesto.php?id_empleado=<?= $id_empleado ?>" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Asignar Nuevo Puesto
                </a>
                <a href="../empleados/lista_empleados.php" class="btn btn-secondary">

                    <i class="bi bi-arrow-left"></i> Volver a Empleados
                </a>
            </div>
            

            <?php if (empty($historial)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Este empleado no tiene historial de puestos registrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Puesto</th>
                                <th>Sueldo Diario</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Días Laborales</th>
                                <th>Horario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $registro): ?>
                                <tr>
                                    <td><?= htmlspecialchars($registro['puesto_nombre']) ?></td>
                                    <td>$<?= number_format($registro['sueldo_diario'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($registro['fecha_inicio'])) ?></td>
                                    <td>
                                        <?php if (!empty($registro['fecha_fin'])): ?>
                                            <?= date('d/m/Y', strtotime($registro['fecha_fin'])) ?>
                                        <?php else: ?>
                                            <span class="badge bg-success">Actual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= str_replace(',', ', ', htmlspecialchars($registro['dias_laborales'])) ?></td>
                                    <td><?= htmlspecialchars($registro['hora_entrada']) ?> a <?= htmlspecialchars($registro['hora_salida']) ?></td>
                                    <td>
                                        <a href="detalle_puesto.php?id=<?= $registro['id_asignacion'] ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar_puesto.php?id=<?= $registro['id_asignacion'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>