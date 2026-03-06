<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
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
    
    $sql = "SELECT
        e.id_empleado,
        CONCAT(
            e.nombre,
            ' ',
            e.apellido_paterno,
            ' ',
            COALESCE(e.apellido_materno, '')
        ) AS nombre_completo,
        p.nombre AS nombre_puesto,
        p.nivel_jerarquico,
        ep.sueldo_diario,
        ep.dias_laborales,
        ep.hora_entrada,
        ep.hora_salida
    FROM
        empleado_puesto AS ep
    LEFT JOIN empleados AS e
    ON
        ep.id_empleado = e.id_empleado
    LEFT JOIN puestos AS p
    ON
        ep.id_puesto = p.id_puesto
    WHERE
            ep.fecha_fin IS NULL
    ORDER BY
        e.nombre,
        e.apellido_paterno";

    
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener el listado: " . $e->getMessage());
}

// Incluye el encabezado de la página.

//Botón
$texto_boton = "Regresar";
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
                            <th>Puesto Asignado</th>
                            <th>Nivel Jerárquico</th>
                            <th>Sueldo Diario</th>
                            <th>Dias Laborales</th>
                            <th>Hora entrada</th>
                            <th>Hora salida</th>

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

                                    <td><?= htmlspecialchars($empleado['nombre_completo'] . ' ' . $empleado['apellido_paterno']) ?></td>
                                    <td><?= htmlspecialchars($empleado['nombre_puesto'] ?? 'Sin puesto') ?></td>
                                    <td><?= htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($empleado['sueldo_diario']) ?></td>
                                    <td>
                                        <?php 
                                        $dias = $empleado['dias_laborales'];
                                        $abreviado = '';
                                        
                                        switch($dias) {
                                            case 'Lunes,Martes,Miércoles,Jueves,Viernes':
                                                $abreviado = 'Lun-Vie';
                                                break;
                                            case 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado':
                                                $abreviado = 'Lun-Sáb';
                                                break;
                                            case 'Lunes,Martes,Miércoles,Jueves,Viernes,Sábado, Domingo':
                                                $abreviado = 'Lun-Dom';
                                                break;
                                            case 'Sábado y Domingo':
                                                $abreviado = 'Sáb-Dom';
                                                break;
                                            case 'Martes,Miércoles,Jueves,Viernes':
                                                $abreviado = 'Mar-Vie';
                                                break;
                                            default:
                                                $abreviado = $dias;
                                        }
                                        echo htmlspecialchars($abreviado);
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($empleado['hora_entrada']) ?></td>
                                    <td><?= htmlspecialchars($empleado['hora_salida']) ?></td>    
                                    <td>
                                        <a href="/AdministracionPlantulas/modulos/puestos/historial_puestos.php?id=<?= $empleado['id_empleado'] ?>" 
                                        style="background-color: #14b3cfff; border-color: #f7dfef;"
                                        class="btn btn-sm btn-primary" title="Historial Puestos">
                                            <i class="bi bi-clock-history"></i> Historial
                                        </a>
                                        <a href="/AdministracionPlantulas/modulos/puestos/editar_empleado_puesto.php?id=<?= $empleado['id_empleado'] ?>" 
                                        style="background-color: rgb(20, 207, 73); border-color: #f7dfef;"
                                        class="btn btn-sm btn-primary" title="Editar">
                                            <i class="bi bi-clock-history"></i> Editar
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