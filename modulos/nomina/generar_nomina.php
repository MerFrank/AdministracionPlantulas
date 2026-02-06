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
            $empleadosSinSueldo = 0;

            foreach ($data as $numeroFila => $row) {
                $columnaA = isset($row['A']) ? trim($row['A']) : '';
                $columnaC = isset($row['C']) ? trim($row['C']) : '';

                // Buscar "ID" incluso si tiene espacios, dos puntos, etc.
                if (stripos($columnaA, 'ID') !== false && !empty($columnaC)) {
                    $id_checador = $columnaC;
                    error_log("ENCONTRADO - Fila $numeroFila: A='$columnaA', ID='$id_checador'");

                    $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);

                    if ($infoEmpleado) {
                        $sueldoDiario = $infoEmpleado['sueldo_diario'] ?? 0;
                        
                        // Verificar si el empleado tiene sueldo diario
                        if (empty($sueldoDiario) || $sueldoDiario <= 0) {
                            $empleadosSinSueldo++;
                            error_log("⚠ Empleado {$infoEmpleado['nombre_completo']} no tiene sueldo diario asignado");
                            continue; // Saltar este empleado
                        }
                        
                        $diasTrabajados = contarDiasTrabajados($data, $numeroFila, $id_checador);
                        $diasIncompletos = contarDiasIncompletos($data, $numeroFila);
                        $descuentoIncompletos = $diasIncompletos * 25; // $25 por día incompleto
                        
                        // Calcular valores directamente
                        $sueldoBase = $sueldoDiario * $diasTrabajados;
                        $totalActividades = $infoEmpleado['pago_actividades'] ?? 0;
                        $totalDescuentos = $descuentoIncompletos;
                        $totalPagar = $sueldoBase + $totalActividades - $totalDescuentos;
                        
                        // Almacenar toda la información requerida
                        $infoEmpleado['id_checador'] = $id_checador;
                        $infoEmpleado['fila_excel'] = $numeroFila;
                        
                        // DÍAS TRABAJADOS
                        $infoEmpleado['dias_trabajados'] = $diasTrabajados;
                        $infoEmpleado['dias_original'] = $diasTrabajados;
                        
                        // ACTIVIDADES EXTRAS
                        $infoEmpleado['total_actividades_extras'] = $totalActividades;
                        $infoEmpleado['total_actividades_original'] = $totalActividades;
                        
                        // DESCUENTOS
                        $infoEmpleado['dias_incompletos'] = $diasIncompletos;
                        $infoEmpleado['dias_incompletos_original'] = $diasIncompletos;
                        $infoEmpleado['descuento_incompletos'] = $descuentoIncompletos;
                        $infoEmpleado['descuento_incompletos_original'] = $descuentoIncompletos;
                        $infoEmpleado['total_descuentos'] = $totalDescuentos;
                        $infoEmpleado['total_descuentos_original'] = $totalDescuentos;
                        
                        // SUELDO BASE
                        $infoEmpleado['sueldo_base'] = $sueldoBase;
                        $infoEmpleado['sueldo_base_original'] = $sueldoBase;
                        
                        // TOTAL A PAGAR
                        $infoEmpleado['total_pagar'] = $totalPagar;
                        $infoEmpleado['total_pagar_original'] = $totalPagar;
                        
                        // Información adicional para cálculos
                        $infoEmpleado['actividades_seleccionadas'] = [];
                        $infoEmpleado['tiene_sueldo'] = true;
                        
                        $registrosEmpleados[] = $infoEmpleado;
                        $idsEncontrados++;
                    } else {
                        error_log("✗ ID $id_checador NO encontrado en BD");
                        $idsNoEnBD++;
                    }
                }
            }

            if ($idsEncontrados > 0) {
                $_SESSION['registros_empleados'] = $registrosEmpleados;
                $mensaje = "Archivo procesado. $idsEncontrados empleados encontrados.";
                
                if ($empleadosSinSueldo > 0) {
                    $mensaje .= " ($empleadosSinSueldo empleados sin sueldo diario fueron omitidos)";
                }
                if ($idsNoEnBD > 0) {
                    $_SESSION['warning_message'] = "$idsNoEnBD IDs no se encontraron en la base de datos.";
                }
                
                $_SESSION['success_message'] = $mensaje;
            } else {
                if (($idsEncontrados + $idsNoEnBD + $empleadosSinSueldo) > 0) {
                    $mensaje = "Se encontraron " . ($idsEncontrados + $idsNoEnBD + $empleadosSinSueldo) . " IDs en el archivo, ";
                    $mensaje .= "pero NINGUNO está en la base de datos o tiene sueldo diario asignado.";
                    $_SESSION['error_message'] = $mensaje;
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
                    $sueldoDiario = $registrosEmpleados[$index]['sueldo_diario'] ?? 0;
                    $registrosEmpleados[$index]['sueldo_base'] = $sueldoDiario * $dias;
                    $totalActividades = $registrosEmpleados[$index]['total_actividades_extras'] ?? 0;
                    $totalDescuentos = $registrosEmpleados[$index]['total_descuentos'] ?? 0;
                    $registrosEmpleados[$index]['total_pagar'] = 
                        ($sueldoDiario * $dias) + $totalActividades - $totalDescuentos;
                }
            }
        }
    }
    
    // Guardar actividades seleccionadas
    if (isset($_POST['actividades']) && is_array($_POST['actividades'])) {
        foreach ($_POST['actividades'] as $index => $actividadesEmpleado) {
            if (isset($registrosEmpleados[$index])) {
                $registrosEmpleados[$index]['actividades_seleccionadas'] = $actividadesEmpleado;
                $totalActividades = 0;
                if (!empty($actividades_extras)) {
                    foreach ($actividades_extras as $actividad) {
                        if (in_array($actividad['id_actividad'], $actividadesEmpleado)) {
                            $totalActividades += $actividad['pago_extra'];
                        }
                    }
                }
                
                $registrosEmpleados[$index]['total_actividades_extras'] = $totalActividades;

                 // Recalcular total a pagar
                $sueldoBase = $registrosEmpleados[$index]['sueldo_base'] ?? 0;
                $totalDescuentos = $registrosEmpleados[$index]['total_descuentos'] ?? 0;
                $registrosEmpleados[$index]['total_pagar'] = 
                    $sueldoBase + $totalActividades - $totalDescuentos;

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
                $registrosEmpleados[$index]['total_descuentos'] = $dias * 25;

                // Recalcular total a pagar
                $sueldoBase = $registrosEmpleados[$index]['sueldo_base'] ?? 0;
                $totalActividades = $registrosEmpleados[$index]['total_actividades_extras'] ?? 0;

                $registrosEmpleados[$index]['total_pagar'] = 
                        $sueldoBase + $totalActividades - ($dias * 25);
            }
        }
    }
}

// Sincronizar totales reales calculados en JS
if (isset($_POST['hidden_sueldo_base'])) {
    foreach ($_POST['hidden_sueldo_base'] as $index => $valor) {
        if (isset($registrosEmpleados[$index])) {
            $registrosEmpleados[$index]['sueldo_base'] = floatval($valor);
        }
    }
}

if (isset($_POST['hidden_total_actividades'])) {
    foreach ($_POST['hidden_total_actividades'] as $index => $valor) {
        if (isset($registrosEmpleados[$index])) {
            $registrosEmpleados[$index]['total_actividades_extras'] = floatval($valor);
        }
    }
}

if (isset($_POST['hidden_total_descuentos'])) {
    foreach ($_POST['hidden_total_descuentos'] as $index => $valor) {
        if (isset($registrosEmpleados[$index])) {
            $registrosEmpleados[$index]['total_descuentos'] = floatval($valor);
        }
    }
}

if (isset($_POST['hidden_total_pagar'])) {
    foreach ($_POST['hidden_total_pagar'] as $index => $valor) {
        if (isset($registrosEmpleados[$index])) {
            $registrosEmpleados[$index]['total_pagar'] = floatval($valor);
        }
    }
}

    
    $_SESSION['registros_empleados'] = $registrosEmpleados; 
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
            <form method="POST" action="guardar_nomina.php" id="formGuardarNomina" class="form-nomina">
                <div class="table-responsive-nomina nomina-fit">
                    <table class="table-clientes">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID Checador</th>
                                <th>Nombre Completo</th>
                                <th>Puesto</th>
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
                                    <td>
                                        <?php echo htmlspecialchars($empleado['nombre_completo'] ?? 'No encontrado'); ?>
                                        <div class="mt-2 text-center">
                                            <small class="badge bg-info">
                                                <?php echo htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($empleado['puesto'] ?? 'N/A'); ?></td>
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
                                            <small class="fw-bold text-success"
                                                id="total-actividades-<?php echo $index; ?>"
                                                data-value="<?php echo $empleado['total_actividades_extras'] ?? 0; ?>">
                                                Total: $<?php echo number_format($empleado['total_actividades_extras'] ?? 0, 2); ?>
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
                                            <small class="fw-bold text-danger" id="descuento-incompletos-small-<?php echo $index; ?>">
                                                Descuento: $<?php echo number_format($empleado['descuento_incompletos'] ?? 0, 2); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">Original: <?php echo $empleado['dias_incompletos_original'] ?? 0; ?> día(s)</small>
                                        </div>
                                    </td>
                                    
                                    <!-- Sueldo Base -->
                                    <td class="fw-bold text-primary"
                                        id="sueldo-base-<?php echo $index; ?>"
                                        data-value="<?php echo $empleado['sueldo_base'] ?? 0; ?>">
                                        $<?php echo number_format($empleado['sueldo_base'] ?? 0, 2); ?>
                                    </td>   
                                    
                                    <!-- Total Descuento -->
                                    <td class="fw-bold text-danger"
                                        id="total-descuentos-<?php echo $index; ?>"
                                        data-value="<?php echo $empleado['total_descuentos'] ?? 0; ?>">
                                        $<?php echo number_format($empleado['total_descuentos'] ?? 0, 2); ?>
                                    </td>
                                    
                                    <!-- Total a Pagar -->
                                    <td class="fw-bold"
                                        style="background-color: #e8f5e8;"
                                        id="total-pagar-<?php echo $index; ?>"
                                        data-value="<?php echo $empleado['total_pagar'] ?? 0; ?>">
                                        $<?php echo number_format($empleado['total_pagar'] ?? 0, 2); ?>
                                    </td>

                                    <input type="hidden" name="hidden_sueldo_base[<?php echo $index; ?>]" id="hidden-sueldo-base-<?php echo $index; ?>" value="<?php echo $empleado['sueldo_base']; ?>">
                                    <input type="hidden" name="hidden_total_actividades[<?php echo $index; ?>]" id="hidden-total-actividades-<?php echo $index; ?>" value="<?php echo $empleado['total_actividades_extras']; ?>">
                                    <input type="hidden" name="hidden_total_descuentos[<?php echo $index; ?>]" id="hidden-total-descuentos-<?php echo $index; ?>" value="<?php echo $empleado['total_descuentos']; ?>">
                                    <input type="hidden" name="hidden_total_pagar[<?php echo $index; ?>]" id="hidden-total-pagar-<?php echo $index; ?>" value="<?php echo $empleado['total_pagar']; ?>">
                                </tr>
                                
                            <?php endforeach; ?>

                            <?php
                            // Calcular totales generales
                            $totalGeneralDiasTrabajados = 0;
                            $totalGeneralActividades = 0;
                            $totalGeneralDescuentos = 0;
                            $totalGeneralSueldoBase = 0;
                            $totalGeneralTotalPagar = 0;

                            foreach ($registrosEmpleados as $empleado) {
                                $totalGeneralDiasTrabajados += $empleado['dias_trabajados'] ?? 0;
                                $totalGeneralActividades += $empleado['total_actividades_extras'] ?? 0;
                                $totalGeneralDescuentos += $empleado['total_descuentos'] ?? 0;
                                $totalGeneralSueldoBase += $empleado['sueldo_base'] ?? 0;
                                $totalGeneralTotalPagar += $empleado['total_pagar'] ?? 0;
                            }
                            ?>
                            <tr class="table-secondary fw-bold" style="background-color: #f8f9fa !important;">
                                <td colspan="5" class="text-end">TOTALES GENERALES:</td>
                                <td class="text-primary" id="total-general-dias"><?php echo $totalGeneralDiasTrabajados; ?> días</td>
                                <td class="text-success" id="total-general-actividades">$<?php echo number_format($totalGeneralActividades, 2); ?></td>
                                <td class="text-danger" id="total-general-descuentos">$<?php echo number_format($totalGeneralDescuentos, 2); ?></td>
                                <td class="text-primary" id="total-general-sueldo-base">$<?php echo number_format($totalGeneralSueldoBase, 2); ?></td>
                                <td class="text-danger" id="total-general-descuentos-2">$<?php echo number_format($totalGeneralDescuentos, 2); ?></td>
                                <td class="text-success" style="background-color: #d4edda !important;" id="total-general-total-pagar">
                                    $<?php echo number_format($totalGeneralTotalPagar, 2); ?>
                                </td>
                            </tr>
                            
                        </tbody>
                    </table>
                </div>

                <!-- === Cuenta Bancaria === -->
                <div class="form-group-nomina" style="background-color: #fff; padding:15px; border:1px solid #ddd; border-radius:5px; margin-top:20px;">
                    <label style="font-weight:bold;">Cuenta bancaria desde donde se pagará la nómina:</label>

                    <select name="id_cuenta" class="form-control" required style="margin-top:10px;">
                        <option value="">-- Selecciona una cuenta --</option>
                        <?php foreach ($cuentas_bancarias as $cuenta): ?>
                            <option value="<?= $cuenta['id_cuenta'] ?>">
                                <?= $cuenta['banco'] ?> - <?= $cuenta['numero'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- === Guardar Nomina === -->

                <div class="form-group-nomina" style="background-color: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; margin-top:20px;">
                    <h4 style="margin-top: 0; color: #333;">Resumen de Nómina a Guardar:</h4>

                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 15px;">
                        <div style="text-align: center; padding: 15px; background: #e8f5e9; border-radius: 5px; border: 2px solid #2e7d32;">
                            <div style="font-size: 24px; font-weight: bold; color: #2e7d32;">
                                <?php echo count($registrosEmpleados); ?>
                            </div>
                            <div style="font-size: 14px; color: #555;">Empleados</div>
                        </div>

                        <div style="text-align: center; padding: 15px; background: #e3f2fd; border-radius: 5px; border: 2px solid #1565c0;">
                            <div style="font-size: 24px; font-weight: bold; color: #1565c0;">
                                $<span id="resumen-sueldos"><?php echo number_format($totalGeneralSueldoBase, 2); ?></span>
                            </div>
                            <div style="font-size: 14px; color: #555;">Sueldos Base</div>
                        </div>

                        <div style="text-align: center; padding: 15px; background: #f3e5f5; border-radius: 5px; border: 2px solid #7b1fa2;">
                            <div style="font-size: 24px; font-weight: bold; color: #7b1fa2;">
                                $<span id="resumen-actividades"><?php echo number_format($totalGeneralActividades, 2); ?></span>
                            </div>
                            <div style="font-size: 14px; color: #555;">Actividades</div>
                        </div>

                        <div style="text-align: center; padding: 15px; background: #ffebee; border-radius: 5px; border: 2px solid #c62828;">
                            <div style="font-size: 24px; font-weight: bold; color: #c62828;">
                                $<span id="resumen-deducciones"><?php echo number_format($totalGeneralDescuentos, 2); ?></span>
                            </div>
                            <div style="font-size: 14px; color: #555;">Deducciones</div>
                        </div>

                        <div style="text-align: center; padding: 20px; background: #e8f5e9; border-radius: 5px; border: 3px solid #1b5e20; grid-column: span 4;">
                            <div style="font-size: 32px; font-weight: bold; color: #1b5e20;">
                                $<span id="resumen-total"><?php echo number_format($totalGeneralTotalPagar, 2); ?></span>
                            </div>
                            <div style="font-size: 18px; color: #555; margin-top: 5px;">TOTAL A PAGAR</div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; font-size: 12px; color: #666;">
                        <i class="fas fa-calendar"></i> 
                        Período: <?= date('d/m/Y', strtotime('monday this week')) ?> al <?= date('d/m/Y', strtotime('friday this week')) ?>
                    </div>
                </div>

                <!-- Contenedor para campos ocultos -->
                <div id="campos-ocultos-container"></div>

                <!-- Botón guardar -->
                <div class="form-group-nomina" style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn-submit-nomina" style="background-color: #28a745;">
                        <i class="fas fa-save"></i> Guardar Nómina en Base de Datos
                    </button>
                </div>

            </form> 
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
    document.getElementById('asistencia_file')?.addEventListener('change', function (e) {
        const fileName = e.target.files[0]?.name || 'No seleccionado';
        const label = this.previousElementSibling;
        if (label) {
            label.innerHTML = `Archivo seleccionado: <strong>${fileName}</strong>`;
        }
    });

    // Variable para prevenir recursión
    let isCalculating = false;

    // Función para calcular totales INDIVIDUALES de cada empleado
    function calcularTotales(index, skipGeneralUpdate = false) {
        if (isCalculating) return null;

        isCalculating = true;

        try {
            const diasInput = document.querySelector(`.dias-input[data-index="${index}"]`);
            const incompletosInput = document.querySelector(`.dias-incompletos-input[data-index="${index}"]`);

            const sueldoDiario = parseFloat(diasInput?.dataset.sueldoDiario) || 0;
            const diasTrabajados = parseInt(diasInput?.value) || 0;
            const diasIncompletos = parseInt(incompletosInput?.value) || 0;
            const precioIncompleto = parseFloat(incompletosInput?.dataset.precio) || 25;

            const sueldoBase = sueldoDiario * diasTrabajados;

            let totalActividades = 0;
            document.querySelectorAll(`.actividad-checkbox[data-index="${index}"]:checked`).forEach(checkbox => {
                totalActividades += parseFloat(checkbox.dataset.valor) || 0;
            });

            const totalDescuentos = diasIncompletos * precioIncompleto;
            const totalPagar = sueldoBase + totalActividades - totalDescuentos;

            const sueldoBaseElement = document.getElementById(`sueldo-base-${index}`);
            if (sueldoBaseElement) {
                sueldoBaseElement.textContent = `$${sueldoBase.toFixed(2)}`;
                sueldoBaseElement.dataset.value = sueldoBase;
                document.getElementById(`hidden-sueldo-base-${index}`).value = sueldoBase;
            }

            const totalActividadesElement = document.getElementById(`total-actividades-${index}`);
            if (totalActividadesElement) {
                totalActividadesElement.textContent = `Total: $${totalActividades.toFixed(2)}`;
                totalActividadesElement.dataset.value = totalActividades;
                document.getElementById(`hidden-total-actividades-${index}`).value = totalActividades;
            }

            const totalDescuentosElement = document.getElementById(`total-descuentos-${index}`);
            if (totalDescuentosElement) {
                totalDescuentosElement.textContent = `$${totalDescuentos.toFixed(2)}`;
                totalDescuentosElement.dataset.value = totalDescuentos;
                document.getElementById(`hidden-total-descuentos-${index}`).value = totalDescuentos;
            }

            const totalPagarElement = document.getElementById(`total-pagar-${index}`);
            if (totalPagarElement) {
                totalPagarElement.textContent = `$${totalPagar.toFixed(2)}`;
                totalPagarElement.dataset.value = totalPagar;
                document.getElementById(`hidden-total-pagar-${index}`).value = totalPagar;
            }

            if (!skipGeneralUpdate) {
                actualizarTotalesGenerales();
            }

            return { diasTrabajados, sueldoBase, totalActividades, totalDescuentos, totalPagar };

        } catch (e) {
            console.error(e);
            return null;
        } finally {
            isCalculating = false;
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
                if (small) {
                    const span = document.createElement('span');
                    span.className = 'text-warning ms-2';
                    span.innerHTML = '<i class="fas fa-pencil-alt"></i> Modificado';
                    small.appendChild(span);
                }
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
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);

            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    }

    // Función para calcular valor de un elemento de texto con formato de dinero
    function obtenerValorMoneda(elemento) {
        if (!elemento) return 0;
        
        // Extraer número del texto (ej: "Total: $123.45" o "$123.45")
        const texto = elemento.textContent || '';
        const match = texto.match(/(\d[\d,.]*\.?\d*)/);
        
        if (match && match[1]) {
            // Remover comas y convertir a número
            return parseFloat(match[1].replace(/,/g, '')) || 0;
        }
        
        return 0;
    }

    // Función para actualizar TOTALES GENERALES
    function actualizarTotalesGenerales() {

        const diasEl = document.getElementById('total-general-dias');
        const actEl = document.getElementById('total-general-actividades');
        const descEl = document.getElementById('total-general-descuentos');
        const sueldoEl = document.getElementById('total-general-sueldo-base');
        const desc2El = document.getElementById('total-general-descuentos-2');
        const pagarEl = document.getElementById('total-general-total-pagar');

        // 🚨 Si la fila no existe, salir sin romper nada
        if (!diasEl || !actEl || !descEl || !sueldoEl || !desc2El || !pagarEl) {
            return;
        }

        let totalDias = 0;
        let totalActividades = 0;
        let totalDescuentos = 0;
        let totalSueldoBase = 0;
        let totalPagar = 0;

        document.querySelectorAll('.dias-input').forEach(input => {
            const index = input.dataset.index;

            totalDias += parseInt(input.value) || 0;

            totalSueldoBase += parseFloat(document.getElementById(`sueldo-base-${index}`)?.dataset.value || 0);
            totalActividades += parseFloat(document.getElementById(`total-actividades-${index}`)?.dataset.value || 0);
            totalDescuentos += parseFloat(document.getElementById(`total-descuentos-${index}`)?.dataset.value || 0);
            totalPagar += parseFloat(document.getElementById(`total-pagar-${index}`)?.dataset.value || 0);
        });

        diasEl.textContent = `${totalDias} días`;
        actEl.textContent = `$${totalActividades.toFixed(2)}`;
        descEl.textContent = `$${totalDescuentos.toFixed(2)}`;
        sueldoEl.textContent = `$${totalSueldoBase.toFixed(2)}`;
        desc2El.textContent = `$${totalDescuentos.toFixed(2)}`;
        pagarEl.textContent = `$${totalPagar.toFixed(2)}`;
        actualizarResumenGuardar();
    }


    // Evento para cambios en días trabajados
    document.querySelectorAll('.dias-input').forEach(input => {
        input.addEventListener('change', function () {
            actualizarEstadoModificado(this);
            calcularTotales(this.dataset.index);
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
            calcularTotales(this.dataset.index);
        });
    });

    // Evento para cambios en actividades
    document.querySelectorAll('.actividad-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            calcularTotales(this.dataset.index);
        });
    });

    // Evento para cambios en días incompletos (DESCUENTOS)
    document.querySelectorAll('.dias-incompletos-input').forEach(input => {
        input.addEventListener('input', function() {
            const dias = parseInt(this.value) || 0;
            const original = parseInt(this.dataset.original);
            
            if (dias > original) {
                this.value = original;
                showTempMessage('No puede exceder los días originales', 'warning');
            }
            
            calcularTotales(this.dataset.index);
        });
        
        // Asegurarnos de que también se captura el evento change
        input.addEventListener('change', function() {
            calcularTotales(this.dataset.index);
        });
    });

    // Botón restaurar para días trabajados
    document.querySelectorAll('.btn-restaurar').forEach(button => {
        button.addEventListener('click', function () {
            const index = this.dataset.index;
            const input = document.querySelector(`.dias-input[data-index="${index}"]`);
            
            if (input) {
                const original = input.dataset.original;
                input.value = original;
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
            
            if (input) {
                const original = input.dataset.original;
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
        // Primero calcular todos los totales individuales
        document.querySelectorAll('.dias-input').forEach(input => {
            const index = input.dataset.index;
            calcularTotales(index, true); // true para saltar actualización general
        });
        
        // Luego calcular totales generales
        actualizarTotalesGenerales();
    });

    document.getElementById('formGuardarNomina').addEventListener('submit', function (e) {

        const container = document.getElementById('campos-ocultos-container');
        container.innerHTML = '';

        let totalSueldos = 0;
        let totalActividades = 0;
        let totalDeducciones = 0;
        let totalPagar = 0;
        let empleadosPagados = 0;

        document.querySelectorAll('.dias-input').forEach(input => {
            const index = input.dataset.index;

            const idEmpleado = document.getElementById(`id-empleado-${index}`).value;
            const dias = input.value;

            const sueldoBase = parseFloat(document.getElementById(`sueldo-base-${index}`).dataset.value || 0);
            const actividades = parseFloat(document.getElementById(`total-actividades-${index}`).dataset.value || 0);
            const descuentos = parseFloat(document.getElementById(`total-descuentos-${index}`).dataset.value || 0);
            const pagar = parseFloat(document.getElementById(`total-pagar-${index}`).dataset.value || 0);

            totalSueldos += sueldoBase;
            totalActividades += actividades;
            totalDeducciones += descuentos;
            totalPagar += pagar;
            empleadosPagados++;

            container.innerHTML += `
                <input type="hidden" name="empleados[${index}][id_empleado]" value="${idEmpleado}">
                <input type="hidden" name="empleados[${index}][dias]" value="${dias}">
                <input type="hidden" name="empleados[${index}][sueldo_base]" value="${sueldoBase}">
                <input type="hidden" name="empleados[${index}][actividades]" value="${actividades}">
                <input type="hidden" name="empleados[${index}][descuentos]" value="${descuentos}">
                <input type="hidden" name="empleados[${index}][total_pagar]" value="${pagar}">
            `;
        });

        // Totales generales
        container.innerHTML += `
            <input type="hidden" name="total_sueldos" value="${totalSueldos}">
            <input type="hidden" name="total_actividades" value="${totalActividades}">
            <input type="hidden" name="total_deducciones" value="${totalDeducciones}">
            <input type="hidden" name="total_pagar" value="${totalPagar}">
            <input type="hidden" name="empleados_pagados" value="${empleadosPagados}">
            <input type="hidden" name="fecha_inicio" value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
            <input type="hidden" name="fecha_fin" value="<?= date('Y-m-d', strtotime('friday this week')) ?>">
        `;
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>