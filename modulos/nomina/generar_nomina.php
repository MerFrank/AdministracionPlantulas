<?php
// Habilitar mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicio de sesión debe ir al principio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Crear instancia de Database y obtener conexión PDO
$database = new Database();
$pdo = $database->conectar();

// Verificar si hay conexión a la base de datos
try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
    // Test simple de conexión
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

$registrosEmpleados = [];

function obtenerInformacionEmpleado($id_checador, $pdo)
{
    try {

        $stmt = $pdo->prepare("
            SELECT 
                e.id_empleado,
                CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, '')) as nombre_completo,
                ep.sueldo_diario,
                ep.hora_entrada,
                ep.hora_salida,
                ep.dias_laborales,
                p.nombre as puesto,
                p.nivel_jerarquico,
                COALESCE(SUM(ea.pago_calculado), 0) as pago_actividades
            FROM empleados e
            LEFT JOIN empleado_puesto ep ON e.id_empleado = ep.id_empleado AND ep.fecha_fin IS NULL
            LEFT JOIN puestos p ON ep.id_puesto = p.id_puesto
            LEFT JOIN empleado_actividades ea ON ep.id_asignacion = ea.id_asignacion
            WHERE e.id_checador = ?
            GROUP BY e.id_empleado, ep.id_asignacion
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . implode(", ", $pdo->errorInfo()));
        }

        $stmt->execute([$id_checador]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: false;

    } catch (PDOException $e) {
        error_log("Error PDO en obtener Informacion Empleado: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error general en obtener Informacion Empleado: " . $e->getMessage());
        return false;
    }
}

function obtenerActividadesExtras($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                id_actividad,
                nombre,
                pago_extra
            FROM actividades_extras
            WHERE activo = 1
            ORDER BY nombre ASC
        ");
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . implode(", ", $pdo->errorInfo()));
        }

        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $resultados ?: [];

    } catch (PDOException $e) {
        error_log("Error al obtener actividades extras: " . $e->getMessage());
        return [];
    }
}

// Obtener cuentas bancarias activas
$cuentas_bancarias = [];
try {
    $stmt_cuentas = $pdo->prepare("
        SELECT id_cuenta, nombre, banco, numero 
        FROM cuentas_bancarias 
        WHERE activo = 1
        ORDER BY nombre
    ");
    $stmt_cuentas->execute();
    $cuentas_bancarias = $stmt_cuentas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener cuentas bancarias: " . $e->getMessage());
    $cuentas_bancarias = [];
}

function procesarArchivo()
{
    try {
        unset($_SESSION['registros_empleados']);
        global $pdo;
        global $registrosEmpleados;

        // Verificar que el archivo se subió correctamente
        if (!isset($_FILES['asistencia_file']) || $_FILES['asistencia_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo. Código de error: " . $_FILES['asistencia_file']['error']);
        }

        $fileTmpPath = $_FILES['asistencia_file']['tmp_name'];
        $fileName = $_FILES['asistencia_file']['name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!file_exists($fileTmpPath)) {
            throw new Exception("El archivo temporal no existe");
        }

        if ($fileType === 'xls' || $fileType === 'xlsx') {
            $spreadsheet = IOFactory::load($fileTmpPath);

            $sheetNames = $spreadsheet->getSheetNames();
            error_log("Hojas disponibles: " . implode(", ", $sheetNames));

            if (count($sheetNames) >= 3) {
                $sheet = $spreadsheet->getSheet(2);
                error_log("Usando tercera hoja: " . $sheetNames[2]);
            } else {
                $sheet = $spreadsheet->getSheet(0);
                error_log("Usando primera hoja (no hay tercera)");
            }

            $data = $sheet->toArray(null, true, true, true);

            $idsEncontrados = 0;
            $idsNoEnBD = 0;

            foreach ($data as $numeroFila => $row) {
                $columnaA = isset($row['A']) ? trim($row['A']) : '';
                $columnaC = isset($row['C']) ? trim($row['C']) : '';

                // Buscar "ID" incluso si tiene espacios, dos puntos, etc.
                if (stripos($columnaA, 'ID') !== false && !empty($columnaC)) {
                    $id_checador = $columnaC;
                    error_log("ENCONTRADO - Fila $numeroFila: A='$columnaA', ID='$id_checador'");

                    $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);

                    if ($infoEmpleado) {
                        $diasTrabajados = contarDiasTrabajados($data, $numeroFila, $id_checador);

                        $diasIncompletos = contarDiasIncompletos($data, $numeroFila);
                        $descuentoIncompletos = $diasIncompletos * 25; // $25 por día incompleto

                        
                        $infoEmpleado['id_checador'] = $id_checador;
                        $infoEmpleado['fila_excel'] = $numeroFila;
                        $infoEmpleado['dias_trabajados'] = $diasTrabajados;
                        $infoEmpleado['dias_original'] = $diasTrabajados;
                        
                        $infoEmpleado['dias_incompletos'] = $diasIncompletos;
                        $infoEmpleado['dias_incompletos_original'] = $diasIncompletos;
                        $infoEmpleado['descuento_incompletos'] = $descuentoIncompletos;
                        $infoEmpleado['descuento_incompletos_original'] = $descuentoIncompletos;
                        
                        $registrosEmpleados[] = $infoEmpleado;
                        $idsEncontrados++;
                        // error_log("✓ Empleado encontrado en BD: " . $infoEmpleado['nombre_completo']);
                    } else {
                        error_log("✗ ID $id_checador NO encontrado en BD");
                        $idsNoEnBD++;
                    }
                }
            }


            if ($idsEncontrados > 0) {
                $_SESSION['registros_empleados'] = $registrosEmpleados;
                $_SESSION['success_message'] = "Archivo procesado. $idsEncontrados empleados encontrados.";
                if ($idsNoEnBD > 0) {
                    $_SESSION['warning_message'] = "$idsNoEnBD IDs no se encontraron en la base de datos.";
                }
            } else {
                if (($idsEncontrados + $idsNoEnBD) > 0) {
                    $_SESSION['error_message'] = "Se encontraron " . ($idsEncontrados + $idsNoEnBD) . " IDs en el archivo, pero NINGUNO está en la base de datos.";
                } else {
                    $_SESSION['error_message'] = "No se encontraron IDs en el archivo. Verifica el formato.";
                }
            }

            return $registrosEmpleados;

        } else {
            throw new Exception("Formato de archivo no válido. Solo se permiten archivos XLS o XLSX");
        }

    } catch (ReaderException $e) {
        $error = "Error al leer el archivo de Excel: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log($error);
        header('Location: generar_nomina.php');
        exit();
    } catch (Exception $e) {
        $error = "Error general: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log($error);
        header('Location: generar_nomina.php');
        exit();
    }
}


function contarDiasTrabajados($data, $filaId, $idChecador) {
    $dias = 0;
    
    // La fila DESPUÉS del ID tiene los registros de la semana
    $filaRegistros = $filaId + 1;
    
    if (isset($data[$filaRegistros])) {
        $row = $data[$filaRegistros];
        
        // Contar celdas con datos en las columnas A-G
        // Cada celda con datos es 1 día trabajado
        for ($col = 'A'; $col <= 'G'; $col++) {
            if (isset($row[$col]) && !empty(trim($row[$col]))) {
                $valor = trim($row[$col]);
                // Si tiene cualquier texto (incluso si es "07:3011:4712:2916:31"), cuenta como día
                if (!empty($valor)) {
                    $dias++;
                }
            }
        }
    }
    
    return $dias;
}

function contarDiasIncompletos($data, $filaId) {
    $diasIncompletos = 0;
    $filaRegistros = $filaId + 1;
    
    if (isset($data[$filaRegistros])) {
        $row = $data[$filaRegistros];
        
        for ($col = 'A'; $col <= 'G'; $col++) {
            if (isset($row[$col]) && !empty(trim($row[$col]))) {
                $valor = trim($row[$col]);
                // Contar cuántos timestamps tiene (cada timestamp es HH:MM)
                $timestampCount = preg_match_all('/\d{1,2}:\d{2}/', $valor);
                if ($timestampCount > 0 && $timestampCount < 4) {
                    $diasIncompletos++;
                }
            }
        }
    }
    
    return $diasIncompletos;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar']) && isset($_FILES['asistencia_file'])) {
    procesarArchivo();
}


// Obtener actividades extras activas
$actividades_extras = obtenerActividadesExtras($pdo);

// Guardar cambios en días trabajados y actividades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_dias'])) {
    if (isset($_POST['dias_trabajados']) && is_array($_POST['dias_trabajados'])) {
        foreach ($_POST['dias_trabajados'] as $index => $dias) {
            if (isset($registrosEmpleados[$index])) {
                $dias = intval($dias);
                $original = $registrosEmpleados[$index]['dias_original'] ?? 0;
                if ($dias >= $original && $dias <= 7) {
                    $registrosEmpleados[$index]['dias_trabajados'] = $dias;
                }
            }
        }
    }
    
    // Guardar actividades seleccionadas
    if (isset($_POST['actividades']) && is_array($_POST['actividades'])) {
        foreach ($_POST['actividades'] as $index => $actividadesEmpleado) {
            if (isset($registrosEmpleados[$index])) {
                $registrosEmpleados[$index]['actividades_seleccionadas'] = $actividadesEmpleado;
            }
        }
    }

    if (isset($_POST['dias_incompletos']) && is_array($_POST['dias_incompletos'])) {
    foreach ($_POST['dias_incompletos'] as $index => $dias) {
        if (isset($registrosEmpleados[$index])) {
            $dias = intval($dias);
            $original = $registrosEmpleados[$index]['dias_incompletos_original'] ?? 0;
            // Solo se puede reducir, no aumentar
            if ($dias >= 0 && $dias <= $original) {
                $registrosEmpleados[$index]['dias_incompletos'] = $dias;
                $registrosEmpleados[$index]['descuento_incompletos'] = $dias * 25;
            }
        }
    }
}
    
    $_SESSION['update_message'] = "Cambios guardados correctamente.";
}



$titulo = "Generar Nómina";
$encabezado = "Generar Nómina";
$subtitulo = "Subir y analizar el archivo de asistencia";
$active_page = "nomina";
$ruta = "dashboard_nomina.php";
$texto_boton = "";
require_once __DIR__ . '/../../includes/header.php';
?>



<main>
    <div class="container-nomina-full">
        <!-- FORMULARIO PARA SUBIR ARCHIVO -->
        <div class="form-container-nomina">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group-nomina">
                    <label for="asistencia_file">Seleccionar archivo Excel (.xls o .xlsx):</label>
                    <input type="file" name="asistencia_file" id="asistencia_file" accept=".xls,.xlsx" required
                    class="form-control">
                </div>
                
                <button type="submit" name="procesar" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i> Procesar Archivo
                </button>

            </form>
        </div>
        <!-- Mostrar mensajes de error/success -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['warning_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['update_message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo $_SESSION['update_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['update_message']); ?>
        <?php endif; ?>
        
        <!-- TABLA PARA MOSTRAR RESULTADOS -->
        <?php if (!empty($registrosEmpleados)): ?>
            <div class="employee-detail-section mt-4">
                <h3 class="section-title-nomina">
                    Empleados Encontrados
                    <span class="badge bg-primary"><?php echo count($registrosEmpleados); ?></span>
                </h3>
            <form method="POST" id="formDiasTrabajados"  class="form-nomina">
                <div class="table-responsive-nomina nomina-fit">
                    <table class="table-clientes">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID Checador</th>
                                <th>Nombre Completo</th>
                                <th>Puesto</th>
                                <th>Nivel Jerárquico</th>
                                <th>Sueldo Diario</th>
                                <th>Días Trabajados</th>
                                <th>Actividades Extras</th>
                                <th>Descuentos</th>
                                <th>Sueldo Base</th>
                                <th>Total Descuento</th>
                                <th>Total a Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrosEmpleados as $index => $empleado):
                                 $diasTrabajados = $empleado['dias_trabajados'] ?? 0;
                                 $diasOriginal = $empleado['dias_original'] ?? $diasTrabajados;
                                 $actividadesSeleccionadas = $empleado['actividades_seleccionadas'] ?? []; 
                                ?>
                                
                                <tr>
                                    <td class="fw-bold"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge bg-dark">
                                            <?php echo htmlspecialchars($empleado['id_checador'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($empleado['nombre_completo'] ?? 'No encontrado'); ?></td>
                                    <td><?php echo htmlspecialchars($empleado['puesto'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        $<?php echo number_format($empleado['sueldo_diario'] ?? 0, 2); ?>
                                    </td>
                                    <td>
                                        <!-- Input de días trabajados -->
                                        <div class="input-group input-group-sm" style="width: 150px;">
                                            <input type="number" 
                                                name="dias_trabajados[<?php echo $index; ?>]" 
                                                value="<?php echo $diasTrabajados; ?>"
                                                min="<?php echo $diasOriginal; ?>" 
                                                max="7"
                                                class="form-control form-control-sm text-center dias-input"
                                                data-index="<?php echo $index; ?>"
                                                data-original="<?php echo $diasOriginal; ?>"
                                                data-sueldo-diario="<?php echo $empleado['sueldo_diario'] ?? 0; ?>"> 
                                                
                                            <button type="button" class="btn btn-outline-secondary btn-sm btn-restaurar" 
                                                    data-index="<?php echo $index; ?>"
                                                    title="Restaurar valor original">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Original: <?php echo $diasOriginal; ?> día<?php echo $diasOriginal != 1 ? 's' : ''; ?>
                                            <?php if ($diasTrabajados != $diasOriginal): ?>
                                                <span class="text-warning ms-2">
                                                    <i class="fas fa-pencil-alt"></i> Modificado
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    
                                    <!-- Actividades extras -->
                                    <td>
                                        <div class="actividades-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #dee2e6; 
                                        border-radius: 5px; padding: 10px; background: white;">
                                            <?php if (!empty($actividades_extras)): ?>
                                                <?php 
                                                $totalActividades = 0;
                                                foreach ($actividades_extras as $actividad): 
                                                    $checked = in_array($actividad['id_actividad'], $actividadesSeleccionadas) ? 'checked' : '';
                                                    if ($checked) {
                                                        $totalActividades += $actividad['pago_extra'];
                                                    }
                                                ?>
                                                    <div class="actividad-item" style="margin-bottom: 8px; padding: 5px; border-radius: 3px; transition: background 0.2s ease;">
                                                        <input type="checkbox" 
                                                            name="actividades[<?php echo $index; ?>][<?php echo $actividad['id_actividad']; ?>]"
                                                            value="<?php echo $actividad['id_actividad']; ?>"
                                                            id="act_<?php echo $index; ?>_<?php echo $actividad['id_actividad']; ?>"
                                                            data-valor="<?php echo $actividad['pago_extra']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($actividad['nombre']); ?>"
                                                            class="actividad-checkbox"
                                                            data-index="<?php echo $index; ?>"
                                                            <?php echo $checked; ?>>
                                                        <label for="act_<?php echo $index; ?>_<?php echo $actividad['id_actividad']; ?>" 
                                                            style="font-size: 12px; margin-bottom: 0; cursor: pointer;">
                                                            <?php echo htmlspecialchars($actividad['nombre']); ?> - 
                                                            $<?php echo number_format($actividad['pago_extra'], 2); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted mb-0" style="font-size: 12px;">No hay actividades extras disponibles</p>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Total Actividades -->
                                        <div class="mt-2 text-center">
                                            <small class="fw-bold text-success" id="total-actividades-<?php echo $index; ?>">
                                                Total: $<?php echo number_format($totalActividades, 2); ?>
                                            </small>
                                        </div>
                                    </td>
                                    
                                    <!-- Descuentos-->
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 150px;">
                                            <input type="number" 
                                                name="dias_incompletos[<?php echo $index; ?>]" 
                                                value="<?php echo $empleado['dias_incompletos'] ?? 0; ?>"
                                                min="0" 
                                                max="<?php echo $empleado['dias_incompletos_original'] ?? 0; ?>"
                                                class="form-control form-control-sm text-center dias-incompletos-input"
                                                data-index="<?php echo $index; ?>"
                                                data-original="<?php echo $empleado['dias_incompletos_original'] ?? 0; ?>"
                                                data-precio="25">
                                            <button type="button" class="btn btn-outline-secondary btn-sm btn-restaurar-incompletos" 
                                                    data-index="<?php echo $index; ?>"
                                                    title="Restaurar valor original">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </div>
                                        <!-- Total Descuentos -->
                                        <div class="mt-2 text-center">
                                            <small class="fw-bold text-danger" id="descuento-incompletos-<?php echo $index; ?>">
                                                Descuento: $<?php echo number_format($empleado['descuento_incompletos'] ?? 0, 2); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">Original: <?php echo $empleado['dias_incompletos_original'] ?? 0; ?> día(s)</small>
                                        </div>
                                    </td>
                                    
                                    <!-- Sueldo Base -->
                                    <td class="fw-bold text-primary" id="sueldo-base-<?php echo $index; ?>">
                                        $<?php 
                                            $sueldoBase = ($empleado['sueldo_diario'] ?? 0) * ($empleado['dias_trabajados'] ?? 0);
                                            echo number_format($sueldoBase, 2); 
                                        ?>
                                    </td>
                                    
                                    <!-- Total Descuento -->
                                    <td class="fw-bold text-danger" id="descuento-incompletos-<?php echo $index; ?>">
                                        $<?php echo number_format($empleado['descuento_incompletos'] ?? 0, 2); ?>
                                    </td>
                                    
                                    <!-- Total a Pagar -->
                                    <td class="fw-bold" style="background-color: #e8f5e8;" id="total-pagar-<?php echo $index; ?>">
                                        $<?php 
                                            $totalPagar = $sueldoBase + $totalActividades - ($empleado['descuento_incompletos'] ?? 0);
                                            echo number_format($totalPagar, 2); 
                                        ?>
                                    </td>
                                </tr>
                                
                            <?php endforeach; ?>
                            
                            <!-- FILA DE TOTALES -->
                            <tr class="total-row">
                                <td colspan="5" class="text-end fw-bold">TOTAL EMPLEADOS:</td>
                                <td class="fw-bold text-primary">
                                    <?php echo count($registrosEmpleados); ?> empleados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form> 
                <!-- RESUMEN -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Resumen</h5>
                            <p class="mb-1">• Empleados procesados: <?php echo count($registrosEmpleados); ?></p>
                            <p class="mb-1">• Pago total actividades: $<?php 
                                $totalPago = 0;
                                foreach ($registrosEmpleados as $emp) {
                                    $totalPago += $emp['pago_actividades'] ?? 0;
                                }
                                echo number_format($totalPago, 2); 
                            ?></p>
                            <p class="mb-0">• Archivo: <?php 
                                echo htmlspecialchars(
                                    isset($_FILES['asistencia_file']['name']) ? 
                                    $_FILES['asistencia_file']['name'] : 
                                    'No procesado'
                                ); 
                            ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Procesado exitosamente</h5>
                            <p class="mb-0">
                                <strong><?php echo count($registrosEmpleados); ?></strong> empleados listos para nómina.
                                <?php if (isset($_SESSION['warning_message'])): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php 
                                            echo $_SESSION['warning_message'];
                                            unset($_SESSION['warning_message']);
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (isset($_POST['procesar'])): ?>
            <div class="alert alert-warning mt-4">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Sin resultados</h5>
                <p class="mb-0">No se encontraron empleados en el archivo. Verifica que el formato sea correcto.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // Evitar reenvío del formulario al recargar
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Mostrar nombre del archivo seleccionado
    document.getElementById('asistencia_file').addEventListener('change', function (e) {
        const fileName = e.target.files[0]?.name || 'No seleccionado';
        const label = this.previousElementSibling;
        label.innerHTML = `Archivo seleccionado: <strong>${fileName}</strong>`;
    });

    // Función para calcular totales
    function calcularTotales(index) {
        const diasInput = document.querySelector(`.dias-input[data-index="${index}"]`);
        const incompletosInput = document.querySelector(`.dias-incompletos-input[data-index="${index}"]`);
        
        // Obtener valores
        const sueldoDiario = parseFloat(diasInput?.dataset.sueldoDiario) || 0;
        const diasTrabajados = parseInt(diasInput?.value) || 0;
        const diasIncompletos = parseInt(incompletosInput?.value) || 0;
        const precioIncompleto = parseInt(incompletosInput?.dataset.precio) || 25;
        
        // Calcular sueldo base
        const sueldoBase = sueldoDiario * diasTrabajados;
        
        // Calcular total actividades
        let totalActividades = 0;
        document.querySelectorAll(`.actividad-checkbox[data-index="${index}"]:checked`).forEach(checkbox => {
            totalActividades += parseFloat(checkbox.dataset.valor) || 0;
        });
        
        // Calcular descuento por incompletos
        const descuentoIncompletos = diasIncompletos * precioIncompleto;
        
        // Calcular total a pagar
        const totalPagar = sueldoBase + totalActividades - descuentoIncompletos;
        
        // Actualizar displays - Asegurarte de que todos los elementos existen
        const elements = {
            'sueldo-base': sueldoBase,
            'total-actividades': totalActividades,
            'descuento-incompletos': descuentoIncompletos,
            'total-pagar': totalPagar
        };
        
        for (const [id, value] of Object.entries(elements)) {
            const element = document.getElementById(`${id}-${index}`);
            if (element) {
                if (id === 'total-actividades') {
                    element.textContent = `Total: $${value.toFixed(2)}`;
                } else if (id === 'descuento-incompletos') {
                    // Puede haber dos elementos con este id, actualizar ambos
                    document.querySelectorAll(`[id^="descuento-incompletos-${index}"]`).forEach(el => {
                        if (el.classList.contains('fw-bold')) {
                            el.textContent = `$${value.toFixed(2)}`;
                        } else {
                            el.textContent = `Descuento: $${value.toFixed(2)}`;
                        }
                    });
                } else if (id === 'sueldo-base') {
                    element.textContent = `$${value.toFixed(2)}`;
                } else {
                    element.textContent = `$${value.toFixed(2)}`;
                }
            }
        }
    }

    // Actualizar estado "Modificado"
    function actualizarEstadoModificado(input) {
        const index = input.dataset.index;
        const original = parseInt(input.dataset.original);
        const actual = parseInt(input.value);

        const row = input.closest('tr');
        const estadoSpan = row.querySelector('.text-warning');

        if (actual !== original) {
            if (!estadoSpan) {
                const small = row.querySelector('small.text-muted');
                const span = document.createElement('span');
                span.className = 'text-warning ms-2';
                span.innerHTML = '<i class="fas fa-pencil-alt"></i> Modificado';
                small.appendChild(span);
            }
        } else {
            if (estadoSpan) {
                estadoSpan.remove();
            }
        }
    }

    // Mostrar mensaje temporal
    function showTempMessage(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-2`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'info' ? 'info-circle' : 'check'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        const container = document.querySelector('.employee-detail-section');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Evento para cambios en días trabajados
    document.querySelectorAll('.dias-input').forEach(input => {
        input.addEventListener('change', function () {
            actualizarEstadoModificado(this);
        });

        input.addEventListener('input', function () {
            const original = parseInt(this.dataset.original);
            const value = parseInt(this.value) || original;

            if (value < original) {
                this.value = original;
                showTempMessage('No se puede reducir los días del valor original', 'warning');
            }

            if (value > 7) {
                this.value = 7;
                showTempMessage('Máximo 7 días permitidos', 'warning');
            }
            
            // Calcular totales
            const index = this.dataset.index;
            calcularTotales(index);
        });
    });

    // Evento para cambios en actividades
    document.querySelectorAll('.actividad-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const index = this.dataset.index;
            calcularTotales(index);
        });
    });

    // Evento para cambios en días incompletos
    document.querySelectorAll('.dias-incompletos-input').forEach(input => {
        input.addEventListener('input', function() {
            const index = this.dataset.index;
            const dias = parseInt(this.value) || 0;
            const original = parseInt(this.dataset.original);
            
            if (dias > original) {
                this.value = original;
                showTempMessage('No puede exceder los días originales', 'warning');
            }
            
            calcularTotales(index);
        });
    });

    // Botón restaurar para días trabajados
    document.querySelectorAll('.btn-restaurar').forEach(button => {
        button.addEventListener('click', function () {
            const index = this.dataset.index;
            const input = document.querySelector(`.dias-input[data-index="${index}"]`);
            const original = input.dataset.original;

            if (input && original) {
                input.value = original;
                input.min = original;
                actualizarEstadoModificado(input);
                calcularTotales(index);
            }
        });
    });

    // Botón restaurar para incompletos
    document.querySelectorAll('.btn-restaurar-incompletos').forEach(button => {
        button.addEventListener('click', function() {
            const index = this.dataset.index;
            const input = document.querySelector(`.dias-incompletos-input[data-index="${index}"]`);
            const original = input.dataset.original;
            
            if (input && original) {
                input.value = original;
                calcularTotales(index);
            }
        }); 
    });

    // Validar formulario antes de enviar
    document.getElementById('formDiasTrabajados')?.addEventListener('submit', function (e) {
        let valid = true;

        document.querySelectorAll('.dias-input').forEach(input => {
            const value = parseInt(input.value);
            const original = parseInt(input.dataset.original);
            const min = parseInt(input.min);
            const max = parseInt(input.max);

            if (isNaN(value) || value < min || value > max) {
                input.classList.add('is-invalid');
                valid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();
            showTempMessage('Por favor, corrige los valores inválidos', 'danger');
        }
    });

    // Inicializar cálculos al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.dias-input').forEach(input => {
            const index = input.dataset.index;
            calcularTotales(index);
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>