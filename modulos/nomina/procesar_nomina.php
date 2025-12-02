<?php
/**
 * Procesamiento principal de la nómina
 */

// Crear instancia de Database y obtener conexión PDO
try {
    $database = new Database();
    $pdo = $database->conectar();
    
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
    
    // Test simple de conexión
    $pdo->query("SELECT 1");
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error de conexión a la base de datos: " . $e->getMessage();
    header('Location: generar_nomina.php');
    exit;
}

// Variables para el procesamiento de datos
$horasPorEmpleado = [];
$empleados = [];
$clasificacionEmpleados = [];
$detalleDias = [];
$nominaCompleta = [];
$actividadesExtras = [];
$totalesGenerales = [];

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Procesar actividades seleccionadas si se enviaron
        if (isset($_POST['actividades_empleado'])) {
            $actividadesSeleccionadasGlobal = $_POST['actividades_empleado'];
            
            foreach ($actividadesSeleccionadasGlobal as $id_checador => $actividadesEmpleado) {
                // Validar que el ID sea numérico
                if (!is_numeric($id_checador)) {
                    continue;
                }
                
                $totalPagoActividadesGlobal = 0;
                $actividadesIds = [];
                
                foreach ($actividadesEmpleado as $idActividad => $valor) {
                    // Validar que el ID de actividad sea numérico
                    if (!is_numeric($idActividad)) {
                        continue;
                    }
                    
                    // Buscar información de la actividad
                    $actividadesExtras = obtenerActividadesExtras($pdo);
                    foreach ($actividadesExtras as $actividad) {
                        if ($actividad['id_actividad'] == $idActividad) {
                            $totalPagoActividadesGlobal += floatval($actividad['pago_extra']);
                            $actividadesIds[] = $idActividad;
                            break;
                        }
                    }
                }
                
                // Guardar en sesión
                $_SESSION['actividades_empleado'][$id_checador] = [
                    'total' => $totalPagoActividadesGlobal,
                    'actividades_ids' => $actividadesIds
                ];
            }
        }

        // Validar archivo subido
        if (!isset($_FILES['asistencia_file']) || $_FILES['asistencia_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo. Código de error: " . $_FILES['asistencia_file']['error']);
        }

        $fileTmpPath = $_FILES['asistencia_file']['tmp_name'];
        $fileName = $_FILES['asistencia_file']['name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileType === 'xls' || $fileType === 'xlsx') {
            // Procesar archivo Excel
            $resultados = procesarArchivoExcel($fileTmpPath, $pdo, $empleadosExcluir);
            
            $horasPorEmpleado = $resultados['horasPorEmpleado'];
            $empleados = $resultados['empleados'];
            $clasificacionEmpleados = $resultados['clasificacionEmpleados'];
            $detalleDias = $resultados['detalleDias'];
            
            // Calcular nómina completa
            $resultadoNomina = calcularNominaCompleta(
                $horasPorEmpleado, 
                $clasificacionEmpleados, 
                $empleados, 
                $pdo, 
                $descuentoRegistrosIncompletos
            );
            
            $nominaCompleta = $resultadoNomina['nominaCompleta'];
            $totalesGenerales = $resultadoNomina['totalesGenerales'];
            $actividadesExtras = $resultadoNomina['actividadesExtras'];
            
        } elseif ($fileType === 'csv') {
            $_SESSION['error_message'] = "Procesamiento de CSV temporalmente no disponible";
            header('Location: generar_nomina.php');
            exit;
        } else {
            throw new Exception("Error: Solo se permiten archivos de tipo CSV, XLS o XLSX.");
        }

    } catch (Exception $e) {
        $error = "Error en el procesamiento: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log($error);
        header('Location: generar_nomina.php');
        exit;
    }
}
?>