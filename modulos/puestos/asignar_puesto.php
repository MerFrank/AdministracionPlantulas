<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Mueve esta línea al inicio del archivo para que BASE_URL esté definida.
require_once __DIR__ . '/../../includes/config.php';

$texto_boton = "Regresar";
$ruta = "dashboard_puestos.php";

require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Asignar Puesto a Empleado";
$subtitulo = "Complete el formulario para asignar un puesto con sueldo diario y actividades extras";
$active_page = "puestos";

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener lista de empleados activos
    $sql_empleados = "SELECT id_empleado, nombre, apellido_paterno, apellido_materno 
                      FROM empleados WHERE activo = 1 ORDER BY apellido_paterno, nombre";
    $stmt_empleados = $con->prepare($sql_empleados);
    $stmt_empleados->execute();
    $empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de puestos activos
    $sql_puestos = "SELECT id_puesto, nombre FROM puestos WHERE activo = 1 ORDER BY nombre";
    $stmt_puestos = $con->prepare($sql_puestos);
    $stmt_puestos->execute();
    $puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de actividades extra activas
    $sql_actividades = "SELECT id_actividad, nombre, pago_extra FROM actividades_extras WHERE activo = 1 ORDER BY nombre";
    $stmt_actividades = $con->prepare($sql_actividades);
    $stmt_actividades->execute();
    $actividades = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Proceso del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $db = new Database();
        $con = $db->conectar();

        // Validar y sanitizar datos
        $id_empleado = filter_var($_POST['id_empleado'], FILTER_SANITIZE_NUMBER_INT);
        $id_puesto = filter_var($_POST['id_puesto'], FILTER_SANITIZE_NUMBER_INT);
        $sueldo_diario = filter_var($_POST['sueldo_diario'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $fecha_inicio = filter_var($_POST['fecha_inicio'], FILTER_SANITIZE_STRING);
        $fecha_fin = $_POST['fecha_fin'] ? filter_var($_POST['fecha_fin'], FILTER_SANITIZE_STRING) : NULL;
        $dias_laborales = isset($_POST['dias_laborales']) ? implode(',', $_POST['dias_laborales']) : '';
        $hora_entrada = filter_var($_POST['hora_entrada'], FILTER_SANITIZE_STRING);
        $hora_salida = filter_var($_POST['hora_salida'], FILTER_SANITIZE_STRING);

        // Iniciar transacción
        $con->beginTransaction();

        // 1. Desactivar el puesto actual del empleado si existe
        $sql_desactivar = "UPDATE empleado_puesto SET fecha_fin = CURRENT_DATE() WHERE id_empleado = ? AND fecha_fin IS NULL";
        $stmt_desactivar = $con->prepare($sql_desactivar);
        $stmt_desactivar->execute([$id_empleado]);

        // 2. Insertar la nueva asignación
        $sql_insertar = "INSERT INTO empleado_puesto (id_empleado, id_puesto, sueldo_diario, fecha_inicio, fecha_fin, dias_laborales, hora_entrada, hora_salida) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insertar = $con->prepare($sql_insertar);
        $stmt_insertar->execute([$id_empleado, $id_puesto, $sueldo_diario, $fecha_inicio, $fecha_fin, $dias_laborales, $hora_entrada, $hora_salida]);
        $id_asignacion = $con->lastInsertId();


        // 3. Insertar las actividades extras si existen - CAMBIO AQUÍ
        if (isset($_POST['actividades']) && !empty($_POST['actividades'])) {
            $sql_actividades = "INSERT INTO empleado_actividades (id_asignacion, id_actividad, fecha, horas_trabajadas, pago_calculado, observaciones) 
                                VALUES (?, ?, CURDATE(), ?, ?, ?)";
            $stmt_actividades = $con->prepare($sql_actividades);
            
            foreach ($_POST['actividades'] as $id_actividad => $actividad_data) {
                if (isset($actividad_data['dias']) && !empty($actividad_data['dias'])) {
                    // Obtener información de la actividad para calcular el pago
                    $stmt_info_actividad = $con->prepare("SELECT pago_extra FROM actividades_extras WHERE id_actividad = ?");
                    $stmt_info_actividad->execute([$id_actividad]);
                    $info_actividad = $stmt_info_actividad->fetch(PDO::FETCH_ASSOC);
                    
                    if ($info_actividad) {
                        $pago_extra = $info_actividad['pago_extra'];
                        $dias_seleccionados = $actividad_data['dias'];
                        $horas_trabajadas = count($dias_seleccionados) * 8; // Suponiendo 8 horas por día
                        $pago_calculado = $pago_extra * $horas_trabajadas;
                        $observaciones = "Asignación automática. Días: " . implode(', ', $dias_seleccionados);
                        
                        $stmt_actividades->execute([
                            $id_asignacion, 
                            $id_actividad, 
                            $horas_trabajadas, 
                            $pago_calculado, 
                            $observaciones
                        ]);
                    }

                }
            }
        }

        // Commit la transacción
        $con->commit();

        $_SESSION['success'] = "Puesto asignado correctamente al empleado.";
        header("Location: " . BASE_URL . '/modulos/puestos/dashboard_puestos.php');
        exit();

    } catch (PDOException $e) {
        $con->rollBack();
        $_SESSION['error'] = "Error al asignar el puesto: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<main class="container py-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0"><?= htmlspecialchars($encabezado) ?></h2>
        </div>
        <div class="card-body">
            <p class="card-text"><?= htmlspecialchars($subtitulo) ?></p>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="h6 mb-3 text-primary"><i class="bi bi-person-fill me-2"></i>Datos del Empleado y Puesto</h3>
                        <div class="mb-3">
                            <label for="id_empleado" class="form-label">Empleado:</label>
                            <select class="form-select" id="id_empleado" name="id_empleado" required>
                                <option value="">Seleccione un empleado...</option>
                                <?php foreach ($empleados as $empleado): ?>
                                    <option value="<?= htmlspecialchars($empleado['id_empleado']) ?>">
                                        <?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un empleado.</div>
                        </div>

                        <div class="mb-3">
                            <label for="id_puesto" class="form-label">Puesto:</label>
                            <select class="form-select" id="id_puesto" name="id_puesto" required>
                                <option value="">Seleccione un puesto...</option>
                                <?php foreach ($puestos as $puesto): ?>
                                    <option value="<?= htmlspecialchars($puesto['id_puesto']) ?>">
                                        <?= htmlspecialchars($puesto['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un puesto.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sueldo_diario" class="form-label">Sueldo Diario:</label>
                            <input type="number" class="form-control" id="sueldo_diario" name="sueldo_diario" step="0.01" min="0" required>
                            <div class="invalid-feedback">Ingrese un sueldo diario válido.</div>
                        </div>

                        <div class="mb-3">
                            <label for="fecha_inicio" class="form-label">Fecha de Inicio:</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Ingrese una fecha de inicio.</div>
                        </div>

                        <div class="mb-3">
                            <label for="fecha_fin" class="form-label">Fecha de Fin (opcional):</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 mb-3 text-primary"><i class="bi bi-clock-fill me-2"></i>Horario de Trabajo</h3>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="hora_entrada" class="form-label">Hora de Entrada:</label>
                                <input type="time" class="form-control" id="hora_entrada" name="hora_entrada" required>
                                <div class="invalid-feedback">Ingrese una hora de entrada válida.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="hora_salida" class="form-label">Hora de Salida:</label>
                                <input type="time" class="form-control" id="hora_salida" name="hora_salida" required>
                                <div class="invalid-feedback">La hora de salida debe ser posterior a la de entrada.</div>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Días Laborales:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="lunes" name="dias_laborales[]" value="Lunes" checked>
                                    <label class="form-check-label" for="lunes">Lunes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="martes" name="dias_laborales[]" value="Martes" checked>
                                    <label class="form-check-label" for="martes">Martes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="miercoles" name="dias_laborales[]" value="Miércoles" checked>
                                    <label class="form-check-label" for="miercoles">Miércoles</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="jueves" name="dias_laborales[]" value="Jueves" checked>
                                    <label class="form-check-label" for="jueves">Jueves</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="viernes" name="dias_laborales[]" value="Viernes" checked>
                                    <label class="form-check-label" for="viernes">Viernes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="sabado" name="dias_laborales[]" value="Sábado">
                                    <label class="form-check-label" for="sabado">Sábado</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="domingo" name="dias_laborales[]" value="Domingo">
                                    <label class="form-check-label" for="domingo">Domingo</label>
                                </div>
                            </div>
                        </div>

                        <h3 class="h6 mb-3 mt-4 text-primary"><i class="bi bi-star-fill me-2"></i>Actividades Extras</h3>
                        <div class="row g-2">
                            <?php if (!empty($actividades)): ?>
                                <?php foreach ($actividades as $actividad): ?>
                                    <div class="col-12">
                                        <div class="card card-body p-3 actividad-card">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="form-check">
                                                    <input class="form-check-input actividad-check" type="checkbox" id="actividad_<?= htmlspecialchars($actividad['id_actividad']) ?>" 
                                                           name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][id]" value="<?= htmlspecialchars($actividad['id_actividad']) ?>"
                                                           data-actividad-id="<?= htmlspecialchars($actividad['id_actividad']) ?>">
                                                    <label class="form-check-label" for="actividad_<?= htmlspecialchars($actividad['id_actividad']) ?>">
                                                        <?= htmlspecialchars($actividad['nombre']) ?>
                                                    </label>
                                                </div>
                                                <span class="text-success fw-bold">$<?= number_format($actividad['pago_extra'], 2) ?></span>
                                            </div>
                                            <div id="dias-act-<?= htmlspecialchars($actividad['id_actividad']) ?>" style="display: none;" class="mt-2 ps-4">
                                                <small class="text-muted d-block mb-1">Días de la actividad:</small>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_lun" value="Lunes">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_lun">L</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_mar" value="Martes">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_mar">M</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_mie" value="Miércoles">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_mie">Mi</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_jue" value="Jueves">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_jue">J</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_vie" value="Viernes">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_vie">V</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_sab" value="Sábado">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_sab">S</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="actividades[<?= htmlspecialchars($actividad['id_actividad']) ?>][dias][]" id="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_dom" value="Domingo">
                                                    <label class="form-check-label" for="act_<?= htmlspecialchars($actividad['id_actividad']) ?>_dom">D</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No hay actividades extra activas para asignar.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Guardar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const horaEntrada = document.getElementById('hora_entrada');
    const horaSalida = document.getElementById('hora_salida');

    // Validación del formulario
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);

    // Validación de horarios
    function validarHorarios() {
        if (horaEntrada.value && horaSalida.value && horaEntrada.value >= horaSalida.value) {
            horaSalida.setCustomValidity('La hora de salida debe ser posterior a la hora de entrada');
        } else {
            horaSalida.setCustomValidity('');
        }
    }
    
    horaEntrada.addEventListener('change', validarHorarios);
    horaSalida.addEventListener('change', validarHorarios);
    
    // Mostrar/ocultar días para actividades seleccionadas
    document.querySelectorAll('.actividad-check').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const actividadId = this.dataset.actividadId;
            const diasContainer = document.getElementById(`dias-act-${actividadId}`);
            
            if (this.checked) {
                diasContainer.style.display = 'block';
                // Agregar clase para resaltar
                this.closest('.actividad-card').classList.add('border-primary');
            } else {
                diasContainer.style.display = 'none';
                // Quitar clase de resaltado
                this.closest('.actividad-card').classList.remove('border-primary');
                // Desmarcar todos los días de esta actividad
                document.querySelectorAll(`input[name="actividades[${actividadId}][dias][]"]`).forEach(dia => {
                    dia.checked = false;
                });
            }
        });
    });
});

</script>   

