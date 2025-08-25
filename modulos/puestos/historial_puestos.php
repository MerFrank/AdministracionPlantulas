<?php
// Definir la variable $ruta para el botón de volver
// En este ejemplo, el botón regresará a la página del dashboard de puestos.
$ruta = BASE_URL . '/vistas/puestos/dashboard_puestos.php';

// El texto del botón es opcional, ya que el header tiene un valor por defecto.
$texto_boton = "Volver a Puestos"; 
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Historial de Puestos";
$active_page = "puestos";

// Obtener ID del empleado
$id_empleado = isset($_GET['id_empleado']) ? (int)$_GET['id_empleado'] : 0;

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener información del empleado
    $sql_empleado = "SELECT nombre, apellido_paterno, apellido_materno 
                     FROM empleados WHERE id_empleado = ?";
    $stmt_empleado = $con->prepare($sql_empleado);
    $stmt_empleado->execute([$id_empleado]);
    $empleado = $stmt_empleado->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        throw new Exception("Empleado no encontrado");
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
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-clock-history"></i> Historial de Puestos</h2>
        </div>
        
        <div class="card-body">
            <h3 class="mb-4"><?= htmlspecialchars($empleado['apellido_paterno'] . ' ' . ($empleado['apellido_materno'] ?? '') . ', ' . $empleado['nombre']) ?></h3>
            
            <div class="d-flex justify-content-between mb-4">
                <a href="asignar_puesto.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Asignar Nuevo Puesto
                </a>
                <a href="empleados/lista_empleados.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Empleados
                </a>
            </div>
            
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
                                <td><?= htmlspecialchars($registro['fecha_inicio']) ?></td>
                                <td><?= htmlspecialchars($registro['fecha_fin'] ?? 'Actual') ?></td>
                                <td><?= str_replace(',', ', ', htmlspecialchars($registro['dias_laborales'])) ?></td>
                                <td><?= htmlspecialchars($registro['hora_entrada']) ?> a <?= htmlspecialchars($registro['hora_salida']) ?></td>
                                <td>
                                    <a href="detalle_puesto.php?id=<?= $registro['id_asignacion'] ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>