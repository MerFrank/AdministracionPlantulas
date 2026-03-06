<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$pdo = $db->conectar();

// Variables para el encabezado
$titulo = "Editar Asignación de Puesto";
$encabezado = "Editar Asignación de Puesto";
$subtitulo = "Modifica los datos de la asignación del empleado";
$active_page = "empleados";

//Botón
$texto_boton = "Regresar";
$ruta = "listado_empleados_puestos.php";

// Obtener el ID del empleado de la URL
$id_empleado = isset($_GET['id']) ? intval($_GET['id']) : 0;

// if ($id_empleado <= 0) {
//     header('Location: listado_empleados_puestos.php?error=ID no válido');
//     exit;
// }

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sueldo_diario = floatval($_POST['sueldo_diario']);
    $dias_laborales = $_POST['dias_laborales'];
    $hora_entrada = $_POST['hora_entrada'];
    $hora_salida = $_POST['hora_salida'];
    
    // Validar que los campos no estén vacíos
    if (!empty($sueldo_diario) && !empty($dias_laborales) && !empty($hora_entrada) && !empty($hora_salida)) {
        try {
            $sql = "UPDATE empleado_puesto SET 
                    sueldo_diario = :sueldo_diario,
                    dias_laborales = :dias_laborales,
                    hora_entrada = :hora_entrada,
                    hora_salida = :hora_salida
                    WHERE id_empleado = :id_empleado AND fecha_fin IS NULL";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sueldo_diario' => $sueldo_diario,
                ':dias_laborales' => $dias_laborales,
                ':hora_entrada' => $hora_entrada,
                ':hora_salida' => $hora_salida,
                ':id_empleado' => $id_empleado
            ]);
            
            header('Location: listado_empleados_puestos.php?success=Registro actualizado correctamente');
            exit;
            
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    } else {
        $error = "Todos los campos son obligatorios";
    }
}

// Obtener los datos actuales del empleado y su asignación
try {
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
                ep.hora_salida,
                ep.id_puesto
            FROM
                empleado_puesto AS ep
            LEFT JOIN empleados AS e
                ON ep.id_empleado = e.id_empleado
            LEFT JOIN puestos AS p
                ON ep.id_puesto = p.id_puesto
            WHERE
                ep.id_empleado = :id_empleado
                AND ep.fecha_fin IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_empleado' => $id_empleado]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        header('Location: listado_empleados_puestos.php?error=Empleado no encontrado o sin puesto activo');
        exit;
    }
    
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main>
    <div class="container-fluid px-4">
        <!-- Mostrar mensajes de error si existen -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-6 col-md-8 mx-auto">
                <div class="card mb-4 shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil-square me-2"></i>
                            Editar Asignación de Puesto
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Información del empleado (solo lectura) -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-person-badge me-1"></i> Información del Empleado
                                    </h6>
                                    <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($empleado['nombre_completo']); ?></p>
                                    <p class="mb-1"><strong>Puesto:</strong> <?php echo htmlspecialchars($empleado['nombre_puesto']); ?></p>
                                    <p class="mb-0"><strong>Nivel Jerárquico:</strong> <?php echo htmlspecialchars($empleado['nivel_jerarquico']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de edición -->
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <!-- Sueldo Diario -->
                                <div class="col-md-6">
                                    <label for="sueldo_diario" class="form-label">
                                        <i class="bi bi-cash-coin me-1"></i> Sueldo Diario
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="sueldo_diario" 
                                               name="sueldo_diario" 
                                               step="0.01" 
                                               min="0" 
                                               value="<?php echo htmlspecialchars($empleado['sueldo_diario']); ?>" 
                                               required>
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor ingresa el sueldo diario.
                                    </div>
                                </div>

                                <!-- Días Laborales -->
                                <div class="col-md-6">
                                    <label for="dias_laborales" class="form-label">
                                        <i class="bi bi-calendar-week me-1"></i> Días Laborales
                                    </label>
                                    <select class="form-select" id="dias_laborales" name="dias_laborales" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Lunes a Viernes" <?php echo ($empleado['dias_laborales'] == 'Lunes a Viernes') ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                        <option value="Lunes a Sábado" <?php echo ($empleado['dias_laborales'] == 'Lunes a Sábado') ? 'selected' : ''; ?>>Lunes a Sábado</option>
                                        <option value="Lunes a Domingo" <?php echo ($empleado['dias_laborales'] == 'Lunes a Domingo') ? 'selected' : ''; ?>>Lunes a Domingo</option>
                                        <option value="Sábado y Domingo" <?php echo ($empleado['dias_laborales'] == 'Sábado y Domingo') ? 'selected' : ''; ?>>Sábado y Domingo</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor selecciona los días laborales.
                                    </div>
                                </div>

                                <!-- Hora de Entrada -->
                                <div class="col-md-6">
                                    <label for="hora_entrada" class="form-label">
                                        <i class="bi bi-clock me-1"></i> Hora de Entrada
                                    </label>
                                    <input type="time" 
                                           class="form-control" 
                                           id="hora_entrada" 
                                           name="hora_entrada" 
                                           value="<?php echo htmlspecialchars($empleado['hora_entrada']); ?>" 
                                           required>
                                    <div class="invalid-feedback">
                                        Por favor ingresa la hora de entrada.
                                    </div>
                                </div>

                                <!-- Hora de Salida -->
                                <div class="col-md-6">
                                    <label for="hora_salida" class="form-label">
                                        <i class="bi bi-clock me-1"></i> Hora de Salida
                                    </label>
                                    <input type="time" 
                                           class="form-control" 
                                           id="hora_salida" 
                                           name="hora_salida" 
                                           value="<?php echo htmlspecialchars($empleado['hora_salida']); ?>" 
                                           required>
                                    <div class="invalid-feedback">
                                        Por favor ingresa la hora de salida.
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="listado_empleados_puestos.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Validación del formulario con Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>