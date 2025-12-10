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

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// ============================================
// CONFIGURACIÓN Y CONSTANTES
// ============================================
$empleadosExcluir = ['16'];
$jornadaCompletaHoras = 8;
$descuentoRegistrosIncompletos = 25; // $25 por día sin 4 registros

// ============================================
// FUNCIONES AUXILIARES
// ============================================

/**
 * Calcular horas trabajadas a partir de una cadena de tiempos
 */
function calcularHorasTrabajadas($cadenaHoras) {
    // Validar que no sea null o vacío
    if (empty($cadenaHoras) || $cadenaHoras === null || trim($cadenaHoras) === '') {
        return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'sin_registros'];
    }

    try {
        // Limpiar y formatear la cadena
        $cadenaHoras = preg_replace('/[^0-9:]/', '', trim($cadenaHoras ?? ''));
        
        if (strlen($cadenaHoras) <= 5) {
            return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'registro_incompleto'];
        }

        $horas = [];
        $i = 0;
        while ($i < strlen($cadenaHoras)) {
            if (preg_match('/(\d{1,2}:\d{2})/', $cadenaHoras, $match, 0, $i)) {
                $horaStr = $match[1];
                // Normalizar formato (7:26 → 07:26)
                if (strlen($horaStr) == 4) {
                    $horaStr = '0' . $horaStr;
                }
                $horas[] = $horaStr;
                $i += strlen($match[1]);
            } else {
                $i++;
            }
        }

        if (count($horas) < 2) {
            return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'registro_incompleto'];
        }

        $entrada = new DateTime($horas[0]);
        $salida = new DateTime(end($horas));
        
        if ($salida < $entrada) {
            $salida->modify('+1 day');
        }
        
        $total = $entrada->diff($salida);
        $horasTrabajadas = $total->h + ($total->i / 60);

        // Descontar receso solo si hay 4 registros exactos
        if (count($horas) == 4) {
            $salidaReceso = new DateTime($horas[1]);
            $entradaReceso = new DateTime($horas[2]);
            
            if ($entradaReceso < $salidaReceso) {
                $entradaReceso->modify('+1 day');
            }
            
            $receso = $salidaReceso->diff($entradaReceso);
            $horasReceso = $receso->h + ($receso->i / 60);
            $horasTrabajadas -= $horasReceso;
        }

        // Determinar tipo de registro
        $tipo = 'otros';
        if (count($horas) == 2) {
            $tipo = 'turno_simple';
        } elseif (count($horas) == 4) {
            $tipo = 'turno_completo';
        } elseif (count($horas) == 1) {
            $tipo = 'registro_incompleto';
        } else {
            $tipo = 'registros_extra';
        }

        return [
            'horas' => max($horasTrabajadas, 0), 
            'entrada' => $horas[0], 
            'salida' => end($horas),
            'tipo' => $tipo
        ];
        
    } catch (Exception $e) {
        error_log("Error en calcularHorasTrabajadas: " . $e->getMessage());
        return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'error_calculo'];
    }
}

/**
 * Obtener información del empleado desde la base de datos
 */
function obtenerInformacionEmpleado($id_checador, $pdo) {
    try {
        if (!$pdo) {
            throw new Exception("Conexión a BD no disponible");
        }
        
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
        error_log("Error PDO en obtenerInformacionEmpleado: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error general en obtenerInformacionEmpleado: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener actividades extras disponibles
 */
function obtenerActividadesExtras($pdo) {
    try {
        if (!$pdo) {
            throw new Exception("Conexión a BD no disponible");
        }
        
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

/**
 * Obtener actividades extras para gerentes generales
 */
function obtenerActividadesExtrasGerente($pdo, $id_empleado) {
    try {
        if (!$pdo) {
            throw new Exception("Conexión a BD no disponible");
        }
        
        // Validar que el ID sea numérico
        if (!is_numeric($id_empleado)) {
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ae.id_actividad,
                ae.nombre,
                ae.pago_extra,
                eag.cantidad,
                (ae.pago_extra * eag.cantidad) as total_pago
            FROM empleado_actividades_gerente eag
            INNER JOIN actividades_extras ae ON eag.id_actividad = ae.id_actividad
            WHERE eag.id_empleado = ? AND eag.activo = 1
        ");
        
        $stmt->execute([$id_empleado]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultados ?: [];
    } catch (PDOException $e) {
        error_log("Error al obtener actividades extras de gerente: " . $e->getMessage());
        return [];
    }
}

// ============================================
// PROCESAMIENTO PRINCIPAL
// ============================================

// Variables para el procesamiento de datos
$horasPorEmpleado = [];
$empleados = [];
$clasificacionEmpleados = [];
$detalleDias = [];
$nominaCompleta = [];
$actividadesExtras = [];
$totalesGenerales = [
    'sueldoBase' => 0,
    'actividadesBD' => 0,
    'actividadesSeleccionadas' => 0,
    'descuentos' => 0,
    'totalPagar' => 0
];

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
                    $actividadesExtrasTemp = obtenerActividadesExtras($pdo);
                    foreach ($actividadesExtrasTemp as $actividad) {
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
            try {
                if (!file_exists($fileTmpPath)) {
                    throw new Exception("El archivo temporal no existe");
                }
                
                $spreadsheet = IOFactory::load($fileTmpPath);
                $sheetNames = $spreadsheet->getSheetNames();
                
                // Usar la tercera hoja si existe, sino la primera
                if (count($sheetNames) >= 3) {
                    $sheet = $spreadsheet->getSheet(2);
                } else {
                    $sheet = $spreadsheet->getSheet(0);
                }
                
                $data = $sheet->toArray(null, true, true, true);
                
                foreach ($data as $numeroFila => $row) {
                    $id_checador = null;
                    $nombre = null;
                    
                    // Buscar ID numérico en las columnas
                    foreach ($row as $col => $value) {
                        $value = $value !== null ? trim($value) : '';
                        
                        // Buscar ID numérico
                        if (is_numeric($value) && $value > 0 && $value < 10000) {
                            $id_checador = $value;
                            // Buscar nombre en columnas adyacentes
                            $nombre = '';
                            for ($i = 1; $i <= 3; $i++) {
                                $nextCol = chr(ord($col) + $i);
                                $nextValue = $row[$nextCol] ?? null;
                                if ($nextValue !== null && !empty(trim($nextValue)) && !is_numeric(trim($nextValue))) {
                                    $nombre = trim($nextValue);
                                    break;
                                }
                            }
                            break;
                        }
                        
                        // Buscar patrones como "ID: 123"
                        if (preg_match('/ID:\s*(\d+)/i', $value, $matches)) {
                            $id_checador = $matches[1];
                            foreach ($row as $col2 => $value2) {
                                $value2 = $value2 !== null ? trim($value2) : '';
                                if (!empty($value2) && !is_numeric($value2) && $value2 !== $value) {
                                    $nombre = $value2;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                    
                    if (!$id_checador || !is_numeric($id_checador)) {
                        continue;
                    }
                    
                    // Verificar si el empleado debe ser excluido
                    if (in_array($id_checador, $empleadosExcluir)) {
                        continue;
                    }
                    
                    $filaHoras = $numeroFila + 1;
                    if (isset($data[$filaHoras])) {
                        $horasFila = $data[$filaHoras];
                        
                        if (!isset($horasPorEmpleado[$id_checador])) {
                            $horasPorEmpleado[$id_checador] = 0;
                            $empleados[$id_checador] = $nombre ?: "Empleado $id_checador";
                            $clasificacionEmpleados[$id_checador] = [
                                'turnos_completos' => 0,
                                'turnos_simples' => 0,
                                'registros_incompletos' => 0,
                                'otros_registros' => 0,
                                'dias_sin_registro' => 0
                            ];
                            $detalleDias[$id_checador] = [];
                        }
                        
                        // Procesar cada día (Lunes a Viernes)
                        $diasProcesados = 0;
                        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                        
                        foreach ($horasFila as $col => $valorCelda) {
                            if ($diasProcesados >= 5) break;
                            
                            $valorCelda = $valorCelda !== null ? trim($valorCelda) : '';
                            if (!empty($valorCelda) && preg_match('/\d{1,2}:\d{2}/', $valorCelda)) {
                                $resultado = calcularHorasTrabajadas($valorCelda);
                                $horasPorEmpleado[$id_checador] += $resultado['horas'];
                                
                                // Clasificar el tipo de registro
                                switch ($resultado['tipo']) {
                                    case 'turno_completo':
                                        $clasificacionEmpleados[$id_checador]['turnos_completos']++;
                                        break;
                                    case 'turno_simple':
                                        $clasificacionEmpleados[$id_checador]['turnos_simples']++;
                                        break;
                                    case 'registro_incompleto':
                                        $clasificacionEmpleados[$id_checador]['registros_incompletos']++;
                                        break;
                                    default:
                                        $clasificacionEmpleados[$id_checador]['otros_registros']++;
                                }
                                
                                // Guardar detalle por día
                                $nombreDia = $dias[$diasProcesados];
                                $detalleDias[$id_checador][$nombreDia] = [
                                    'horas' => $resultado['horas'],
                                    'tipo' => $resultado['tipo'],
                                    'registros' => $valorCelda,
                                    'entrada' => $resultado['entrada'],
                                    'salida' => $resultado['salida']
                                ];
                                
                                $diasProcesados++;
                            }
                        }
                        
                        // Marcar días restantes como sin registro
                        for ($i = $diasProcesados; $i < 5; $i++) {
                            $nombreDia = $dias[$i];
                            $clasificacionEmpleados[$id_checador]['dias_sin_registro']++;
                            $detalleDias[$id_checador][$nombreDia] = [
                                'horas' => 0,
                                'tipo' => 'sin_registro',
                                'registros' => '',
                                'entrada' => null,
                                'salida' => null
                            ];
                        }
                    }
                }
                
            } catch (ReaderException $e) {
                throw new Exception("Error al leer el archivo de Excel: " . $e->getMessage());
            } catch (Exception $e) {
                throw new Exception("Error procesando archivo Excel: " . $e->getMessage());
            }
            
            // Calcular nómina completa
            $actividadesExtras = obtenerActividadesExtras($pdo);
            
            foreach ($horasPorEmpleado as $id_checador => $totalHoras) {
                $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);
                
                if (!$infoEmpleado) {
                    $nominaCompleta[$id_checador] = [
                        'error' => 'Empleado no encontrado en BD',
                        'nombre' => $empleados[$id_checador] ?? 'Desconocido'
                    ];
                    continue;
                }
                
                // Verificar si es gerente general
                $esGerenteGeneral = ($infoEmpleado['nivel_jerarquico'] === 'gerente_general');
                
                // Cálculos de nómina
                $sueldoDiario = floatval($infoEmpleado['sueldo_diario'] ?? 0);
                
                if ($esGerenteGeneral) {
                    // Para gerente general: usar días laborales asignados
                    $diasLaboralesStr = $infoEmpleado['dias_laborales'] ?? '';
                    $diasArray = explode(',', $diasLaboralesStr);
                    $diasArray = array_filter(array_map('trim', $diasArray));
                    $cantidadDiasLaborales = count($diasArray);
                    
                    // Valor por defecto si no hay días definidos
                    if ($cantidadDiasLaborales === 0) {
                        $cantidadDiasLaborales = 5;
                    }
                    
                    $diasAsignados = $cantidadDiasLaborales;
                    $sueldoBase = $sueldoDiario * $diasAsignados;
                    $descuentoRegistros = 0; // Sin descuentos por registros
                    $diasSin4Registros = 0; // No aplica para gerente general
                    
                    // Obtener actividades extras específicas para gerente general
                    $actividadesGerente = obtenerActividadesExtrasGerente($pdo, $infoEmpleado['id_empleado']);
                    $pagoActividadesGerente = 0;
                    foreach ($actividadesGerente as $actividad) {
                        $pagoActividadesGerente += floatval($actividad['total_pago']);
                    }
                    
                } else {
                    // Para empleados normales: cálculo basado en asistencia
                    $diasAsignados = 5; // Semana laboral estándar
                    $diasSinRegistro = $clasificacionEmpleados[$id_checador]['dias_sin_registro'] ?? 0;
                    $diasTrabajados = 5 - $diasSinRegistro;
                    $sueldoBase = $sueldoDiario * $diasTrabajados;
                    
                    // Calcular descuento por registros incompletos
                    $diasSin4Registros = $clasificacionEmpleados[$id_checador]['turnos_simples'] + 
                                        $clasificacionEmpleados[$id_checador]['registros_incompletos'] + 
                                        $clasificacionEmpleados[$id_checador]['otros_registros'];
                    $descuentoRegistros = $diasSin4Registros * $descuentoRegistrosIncompletos;
                    $pagoActividadesGerente = 0; // No aplica para empleados normales
                }
                
                // Pago por actividades (de BD) - para empleados normales
                $pagoActividades = floatval($infoEmpleado['pago_actividades'] ?? 0);
                
                // Pago por actividades seleccionadas manualmente (de sesión)
                $pagoActividadesSeleccionadas = $_SESSION['actividades_empleado'][$id_checador]['total'] ?? 0;
                
                // Calcular total a pagar según tipo de empleado
                if ($esGerenteGeneral) {
                    $totalPagar = $sueldoBase + $pagoActividadesGerente + $pagoActividadesSeleccionadas;
                } else {
                    $totalPagar = $sueldoBase + $pagoActividades + $pagoActividadesSeleccionadas - $descuentoRegistros;
                }
                
                $nominaCompleta[$id_checador] = [
                    'id_checador' => $id_checador,
                    'nombre_completo' => $infoEmpleado['nombre_completo'],
                    'puesto' => $infoEmpleado['puesto'] ?? 'No asignado',
                    'nivel_jerarquico' => $infoEmpleado['nivel_jerarquico'] ?? 'normal',
                    'es_gerente_general' => $esGerenteGeneral,
                    'sueldo_diario' => $sueldoDiario,
                    'dias_asignados' => $diasAsignados,
                    'dias_trabajados' => $esGerenteGeneral ? $diasAsignados : $diasTrabajados,
                    'dias_laborales' => $infoEmpleado['dias_laborales'] ?? '',
                    'sueldo_base' => $sueldoBase,
                    'pago_actividades_bd' => $pagoActividades,
                    'pago_actividades_gerente' => $pagoActividadesGerente,
                    'pago_actividades_seleccionadas' => $pagoActividadesSeleccionadas,
                    'descuento_registros' => $descuentoRegistros,
                    'total_pagar' => $totalPagar,
                    'horario_esperado' => ($infoEmpleado['hora_entrada'] ?? '--:--') . ' - ' . ($infoEmpleado['hora_salida'] ?? '--:--'),
                    'dias_sin_4_registros' => $diasSin4Registros ?? 0,
                    'actividades_gerente' => $actividadesGerente ?? []
                ];
                
                // Acumular totales generales
                $totalesGenerales['sueldoBase'] += $sueldoBase;
                $totalesGenerales['actividadesBD'] += $pagoActividades + $pagoActividadesGerente;
                $totalesGenerales['actividadesSeleccionadas'] += $pagoActividadesSeleccionadas;
                $totalesGenerales['descuentos'] += $descuentoRegistros;
                $totalesGenerales['totalPagar'] += $totalPagar;
            }
            
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

// ============================================
// HTML Y VISTA
// ============================================

$titulo = "Generar Nómina";
$encabezado = "Generar Nómina";
$subtitulo = "Subir y analizar el archivo de asistencia";
$active_page = "nomina";
$ruta = "dashboard_nomina.php";
$texto_boton = "";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ESTILOS ESPECÍFICOS PARA NÓMINA - SOBRESCRIBIENDO REGLAS EXISTENTES */
    .form-container-nomina {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #dee2e6;
        margin-bottom: 25px;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
    }

    /* FORZAR ANCHO COMPLETO PARA TODOS LOS ELEMENTOS DE NÓMINA */
    .container-nomina-full {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 15px;
        margin: 0 auto;
    }

    .table-responsive-nomina {
        overflow-x: auto;
        width: 100% !important;
        margin-top: 20px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .table-nomina {
        width: 100% !important;
        min-width: 1400px !important;
        border-collapse: collapse;
        background-color: white;
        margin-bottom: 0;
    }

    .table-nomina th,
    .table-nomina td {
        border: 1px solid #dee2e6;
        padding: 12px;
        text-align: center;
        vertical-align: middle;
    }

    .table-nomina thead {
        background-color: #45814d !important;
        color: white;
    }

    .table-nomina thead th {
        background-color: #45814d !important;
        color: white !important;
        text-transform: uppercase;
        font-weight: 500;
        padding: 1rem;
        border: none;
    }

    .form-group-nomina {
        margin-bottom: 20px;
        width: 100% !important;
    }

    .form-group-nomina label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
        width: 100% !important;
    }

    .form-group-nomina input[type="file"] {
        width: 100% !important;
        padding: 10px;
        border: 2px dashed #ced4da;
        border-radius: 5px;
        background: white;
        transition: all 0.3s ease;
    }

    .btn-submit-nomina {
        background: #007bff;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s ease;
        width: auto !important;
        display: inline-block !important;
    }

    .btn-submit-nomina:hover {
        background: #0056b3;
    }

    .total-row {
        background: #e3f2fd !important;
        font-weight: bold;
        font-size: 1.1em;
    }

    .total-row td {
        padding: 15px 12px;
        border-top: 2px solid #007bff;
    }

    .actividades-container {
        max-height: 150px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 10px;
        background: white;
        width: 100% !important;
    }

    .actividades-item {
        margin-bottom: 8px;
        padding: 5px;
        border-radius: 3px;
        transition: background 0.2s ease;
        width: 100% !important;
    }

    .actividades-item:hover {
        background: #f8f9fa;
    }

    .actividades-item label {
        font-weight: normal;
        margin-bottom: 0;
        cursor: pointer;
        width: 100% !important;
    }

    .positive-amount {
        color: #28a745;
        font-weight: 600;
    }

    .negative-amount {
        color: #dc3545;
        font-weight: 600;
    }

    .section-title-nomina {
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
        margin-bottom: 20px;
        width: 100% !important;
    }

    .employee-detail-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100% !important;
    }

    /* Estilos para condonación */
    .condonar-checkbox {
        transform: scale(1.2);
        margin: 0 8px;
    }

    .condonar-label {
        font-weight: normal;
        cursor: pointer;
        font-size: 12px;
    }

    .descuento-condonado {
        text-decoration: line-through;
        color: #6c757d !important;
    }

    .sin-descuento {
        color: #28a745 !important;
        font-weight: bold;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .container-nomina-full {
            padding: 0 10px;
        }
        
        .form-container-nomina {
            padding: 15px;
        }
        
        .table-nomina {
            min-width: 1200px !important;
        }
    }

    @media (max-width: 576px) {
        .container-nomina-full {
            padding: 0 5px;
        }
        
        .form-container-nomina {
            padding: 10px;
        }
        
        .btn-submit-nomina {
            width: 100% !important;
            padding: 15px;
        }
    }
</style>

<main>
    <div class="container-nomina-full">
        <h1 class="section-title-nomina">Generar Nómina</h1>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="width: 100% !important;">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="form-container-nomina">
            <form class="form-nomina" action="generar_nomina.php" method="post" enctype="multipart/form-data">
                <div class="form-group-nomina">
                    <label for="asistencia_file">Selecciona el archivo de asistencia (XLS o XLSX):</label>
                    <input type="file" name="asistencia_file" id="asistencia_file" accept=".xls,.xlsx" required>
                    <small class="form-text text-muted">Asegúrate de que el archivo tenga los datos en la tercera hoja.</small>
                </div>
                <button type="submit" class="btn-submit-nomina">Analizar y Generar Nómina</button>
            </form>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado) && !empty($horasPorEmpleado)): ?>
        
        <!-- ============================================
            VISTA DE NÓMINA COMPLETA
        ============================================ -->
        <div class="form-container-nomina">
            <h2 class="section-title-nomina">Nómina Completa</h2>
            
            <div class="table-responsive-nomina">
                <table class="table-nomina">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>Sueldo Diario</th>
                            <th>Días Trab.</th>
                            <th>Sueldo Base</th>
                            <th style="min-width: 250px;">Actividades Extras</th> 
                            <th>Días sin 4 reg.</th>
                            <th>Días a Condonar</th>
                            <th>Descuento Aplicado</th>
                            <th>Total a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Inicializar totales para esta vista
                        $vistaTotalSueldoBase = 0;
                        $vistaTotalActividadesBD = 0;
                        $vistaTotalActividadesSeleccionadas = 0;
                        $vistaTotalDescuentos = 0;
                        $vistaTotalPagar = 0;
                        
                        foreach ($nominaCompleta as $id_checador => $nomina): 
                            if (isset($nomina['error'])): 
                        ?>
                            <tr style="background-color: #ffe6e6;">
                                <td><?= htmlspecialchars($id_checador) ?></td>
                                <td colspan="10" style="color: red;">
                                    <?= htmlspecialchars($nomina['nombre']) ?> - <?= htmlspecialchars($nomina['error']) ?>
                                </td>
                            </tr>
                        <?php else: 
                            // Obtener IDs de actividades seleccionadas de la sesión
                            $actividadesSeleccionadasIds = $_SESSION['actividades_empleado'][$id_checador]['actividades_ids'] ?? [];
                            
                            // Obtener valores para este empleado
                            $sueldoBase = $nomina['sueldo_base'];
                            $descuentoRegistros = $nomina['descuento_registros'];
                            $pagoActividadesBD = $nomina['pago_actividades_bd'];
                            $pagoActividadesGerente = $nomina['pago_actividades_gerente'];
                            $pagoActividadesSeleccionadas = $nomina['pago_actividades_seleccionadas'];
                            $totalPagar = $nomina['total_pagar'];
                            $esGerenteGeneral = $nomina['es_gerente_general'];

                            // Acumular totales para la vista
                            $vistaTotalSueldoBase += $sueldoBase;
                            $vistaTotalActividadesBD += $pagoActividadesBD + $pagoActividadesGerente;
                            $vistaTotalActividadesSeleccionadas += $pagoActividadesSeleccionadas;
                            $vistaTotalDescuentos += $descuentoRegistros;
                            $vistaTotalPagar += $totalPagar;
                        ?>
                            <tr id="fila-<?= htmlspecialchars($id_checador) ?>" 
                                data-sueldo-base="<?= $sueldoBase ?>" 
                                data-descuento-registros="<?= $descuentoRegistros ?>"
                                data-pago-actividades-bd="<?= $pagoActividadesBD ?>"
                                data-pago-actividades-gerente="<?= $pagoActividadesGerente ?>"
                                data-es-gerente="<?= $esGerenteGeneral ? 'true' : 'false' ?>">
                                <td><?= htmlspecialchars($id_checador) ?></td>
                                <td><?= htmlspecialchars($nomina['nombre_completo']) ?></td>
                                <td>
                                    <?= htmlspecialchars($nomina['puesto']) ?>
                                    <?php if ($esGerenteGeneral): ?>
                                        <br><small style="color: green;">(Gerente General)</small>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($nomina['sueldo_diario'], 2) ?></td>
                                <td>
                                    <?= $nomina['dias_trabajados'] ?>
                                    <?php if ($esGerenteGeneral): ?>
                                        <br><small>de <?= $nomina['dias_asignados'] ?> asignados</small>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($sueldoBase, 2) ?></td>

                                <!-- Actividades Extras Seleccionadas -->
                                <td>
                                    <div class="actividades-container">
                                        <?php foreach ($actividadesExtras as $actividad): 
                                            $checked = in_array($actividad['id_actividad'], $actividadesSeleccionadasIds) ? 'checked' : '';
                                        ?>
                                            <div class="actividades-item">
                                                <input type="checkbox" 
                                                    class="actividad-checkbox"
                                                    name="actividades_empleado[<?= htmlspecialchars($id_checador) ?>][<?= htmlspecialchars($actividad['id_actividad']) ?>]"
                                                    data-empleado="<?= htmlspecialchars($id_checador) ?>"
                                                    data-valor="<?= htmlspecialchars($actividad['pago_extra']) ?>"
                                                    id="act_<?= htmlspecialchars($id_checador) ?>_<?= htmlspecialchars($actividad['id_actividad']) ?>"
                                                    value="1"
                                                    <?= $checked ?>>
                                                <label for="act_<?= htmlspecialchars($id_checador) ?>_<?= htmlspecialchars($actividad['id_actividad']) ?>" style="font-size: 12px;">
                                                    <?= htmlspecialchars($actividad['nombre']) ?> - $<?= number_format($actividad['pago_extra'], 2) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; font-weight: bold; text-align: center;">
                                        Total: $<span id="total-actividades-<?= htmlspecialchars($id_checador) ?>"><?= number_format($pagoActividadesSeleccionadas, 2) ?></span>
                                    </div>
                                </td>

                                <!-- Días sin 4 reg. -->
                                <td style="color: orange; font-weight: bold;">
                                    <?= $nomina['dias_sin_4_registros'] ?> días
                                </td>

                                <!-- Días a condonar -->
                                <td>
                                    <?php if (!$esGerenteGeneral): ?>
                                        <select class="dias-condonar" 
                                                id="condonar-<?= htmlspecialchars($id_checador) ?>" 
                                                data-empleado="<?= htmlspecialchars($id_checador) ?>"
                                                data-descuento-por-dia="<?= htmlspecialchars($descuentoRegistrosIncompletos ?? 0) ?>"
                                                data-dias-sin-registros="<?= htmlspecialchars($nomina['dias_sin_4_registros']) ?>">
                                            <?php for ($i = 0; $i <= $nomina['dias_sin_4_registros']; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?> días</option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php else: ?>
                                        <span style="color: green;">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Descuento aplicado -->
                                <td class="negative-amount" id="descuento-<?= htmlspecialchars($id_checador) ?>">
                                    <?php if ($esGerenteGeneral): ?>
                                        <span style="color: green;">SIN DESCUENTO</span>
                                    <?php else: ?>
                                        -$<span id="monto-descuento-<?= htmlspecialchars($id_checador) ?>"><?= number_format($descuentoRegistros, 2) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total a pagar -->
                                <td style="background-color: #e6ffe6; font-weight: bold;" class="positive-amount">
                                    $<span id="total-pagar-<?= htmlspecialchars($id_checador) ?>"><?= number_format($totalPagar, 2) ?></span>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                        
                        <!-- Totales generales -->
                        <tr class="total-row">
                            <td colspan="5" style="text-align: right; font-weight: bold;">TOTALES GENERALES:</td>
                            <td style="font-weight: bold;">$<span id="total-sueldo-base"><?= number_format($vistaTotalSueldoBase, 2) ?></span></td>
                            <td style="font-weight: bold;">$<span id="total-actividades"><?= number_format($vistaTotalActividadesSeleccionadas + $vistaTotalActividadesBD, 2) ?></span></td>
                            <td></td>
                            <td></td>
                            <td style="font-weight: bold;">-$<span id="total-descuentos"><?= number_format($vistaTotalDescuentos, 2) ?></span></td>
                            <td style="font-weight: bold; background-color: #d4edda;">$<span id="total-general"><?= number_format($vistaTotalPagar, 2) ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p><small>* Descuento de $25 por cada día sin 4 registros completos</small></p>
        </div>

        <!-- ============================================
        FORMULARIO PARA GUARDAR NÓMINA EN BD
        ============================================ -->
        <div class="form-container-nomina" style="margin-top: 40px; border: 2px solid #007bff;">
            <h2 class="section-title-nomina" style="color: #007bff;">Guardar Nómina en Base de Datos</h2>

            <form id="form-guardar-nomina" action="guardar_nomina.php" method="post">
                <!-- Campos ocultos para pasar los datos de la nómina -->
                <input type="hidden" name="nomina_data_json" id="nomina-data-json">
                <input type="hidden" name="totales_json" id="totales-json">
                
                <!-- Fechas del período -->
                <div class="form-group-nomina" style="display: flex; gap: 20px; margin-bottom: 30px;">
                    <div style="flex: 1;">
                        <label for="fecha_inicio" style="font-weight: bold;">Fecha Inicio del Período *</label>
                        <input type="text" 
                            name="fecha_inicio" 
                            id="fecha_inicio" 
                            class="form-control-nomina"
                            placeholder="DD/MM/AAAA"
                            required
                            pattern="\d{2}/\d{2}/\d{4}"
                            title="Formato: DD/MM/AAAA">
                        <small class="form-text text-muted">Ejemplo: 15/01/2024</small>
                    </div>
                    
                    <div style="flex: 1;">
                        <label for="fecha_fin" style="font-weight: bold;">Fecha Fin del Período *</label>
                        <input type="text" 
                            name="fecha_fin" 
                            id="fecha_fin" 
                            class="form-control-nomina"
                            placeholder="DD/MM/AAAA"
                            required
                            pattern="\d{2}/\d{2}/\d{4}"
                            title="Formato: DD/MM/AAAA">
                        <small class="form-text text-muted">Ejemplo: 31/01/2024</small>
                    </div>
                </div>
                
                <!-- Resumen de Totales (se actualizan automáticamente) -->
                <div class="resumen-totales" style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 25px;">
                    <h3 style="color: #28a745; margin-top: 0;">Resumen de Totales</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="total-item">
                            <span class="total-label">Total Sueldo Base:</span>
                            <span class="total-value" id="resumen-total-sueldo">$0.00</span>
                        </div>
                        
                        <div class="total-item">
                            <span class="total-label">Total Actividades Extras:</span>
                            <span class="total-value" id="resumen-total-actividades">$0.00</span>
                        </div>
                        
                        <div class="total-item">
                            <span class="total-label">Total Deducciones:</span>
                            <span class="total-value" id="resumen-total-deducciones">$0.00</span>
                        </div>
                        
                        <div class="total-item">
                            <span class="total-label">Total a Pagar:</span>
                            <span class="total-value" style="font-weight: bold; color: #28a745;" id="resumen-total-pagar">$0.00</span>
                        </div>
                    </div>
                    
                    <div class="total-item" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <span class="total-label">Empleados a Pagar:</span>
                        <span class="total-value" id="resumen-empleados">0</span>
                    </div>
                </div>
                
                <!-- Información de la cuenta  -->
                <div class="form-group-nomina">
                    <label for="id_cuenta">Cuenta de Pago </label>
                    <select name="id_cuenta" id="id_cuenta" class="form-control-nomina">
                        <option value="">-- Seleccionar Cuenta --</option>
                            <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>">
                                        <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                                    </option>
                            <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Botones de acción -->
                <div class="form-group-nomina" style="display: flex; gap: 15px; margin-top: 30px;">
                    
                    <button type="submit" id="btn-guardar-nomina" class="btn-submit-nomina" style="background-color: #28a745;">
                        <i class="fas fa-save"></i> Guardar Nómina en Base de Datos
                    </button>
                </div>
                
                <div id="mensaje-validacion" style="margin-top: 15px; display: none;"></div>
            </form>
        </div>  

        <!-- JavaScript para calcular totales en tiempo real -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar totales desde PHP
            window.totalesGenerales = {
                sueldoBase: <?= $vistaTotalSueldoBase ?? 0 ?>,
                actividades: <?= $vistaTotalActividadesSeleccionadas ?? 0 ?>,
                actividadesBD: <?= $vistaTotalActividadesBD ?? 0 ?>,
                descuentos: <?= $vistaTotalDescuentos ?? 0 ?>,
                general: <?= $vistaTotalPagar ?? 0 ?>
            };
            
            // Agregar event listeners a todos los checkboxes de actividades
            document.querySelectorAll('.actividad-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    calcularTotal(this);
                });
            });
            
            // Agregar event listeners a todos los selects de días a condonar
            document.querySelectorAll('.dias-condonar').forEach(select => {
                select.addEventListener('change', function() {
                    calcularDescuento(this);
                });
            });
        });

        function calcularTotal(checkbox) {
            const empleadoId = checkbox.dataset.empleado;
            const valorActividad = parseFloat(checkbox.dataset.valor);
            const estaMarcado = checkbox.checked;
            
            // Validar datos
            if (!empleadoId || isNaN(valorActividad)) {
                console.error('Datos inválidos en checkbox:', checkbox);
                return;
            }
            
            // Obtener los elementos relevantes
            const totalActividadesElement = document.getElementById('total-actividades-' + empleadoId);
            const totalPagarElement = document.getElementById('total-pagar-' + empleadoId);
            const fila = document.getElementById('fila-' + empleadoId);
            
            if (!totalActividadesElement || !totalPagarElement || !fila) {
                console.error('Elementos no encontrados para empleado:', empleadoId);
                return;
            }
            
            // Obtener valores base
            let totalActividadesActual = parseFloat(totalActividadesElement.textContent) || 0;
            const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
            const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
            const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
            const esGerente = fila.dataset.esGerente === 'true';
            
            // Obtener descuento actual
            const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
            const descuentoActual = parseFloat(montoDescuentoElement ? montoDescuentoElement.textContent : 0) || 0;
            
            // Actualizar total de actividades
            if (estaMarcado) {
                totalActividadesActual += valorActividad;
            } else {
                totalActividadesActual -= valorActividad;
            }
            
            // Asegurarse de que no sea negativo
            if (totalActividadesActual < 0) {
                totalActividadesActual = 0;
            }
            
            // Calcular nuevo total a pagar
            let nuevoTotalPagar;
            if (esGerente) {
                nuevoTotalPagar = sueldoBase + pagoActividadesGerente + totalActividadesActual - descuentoActual;
            } else {
                nuevoTotalPagar = sueldoBase + pagoActividadesBD + totalActividadesActual - descuentoActual;
            }
            
            // Actualizar displays
            totalActividadesElement.textContent = totalActividadesActual.toFixed(2);
            totalPagarElement.textContent = nuevoTotalPagar.toFixed(2);
            
            // Actualizar totales generales
            actualizarTotalesGenerales();
        }

        function calcularDescuento(select) {
            const empleadoId = select.dataset.empleado;
            const diasACondonar = parseInt(select.value);
            const descuentoPorDia = parseFloat(select.dataset.descuentoPorDia);
            const diasSinRegistros = parseInt(select.dataset.diasSinRegistros);
            
            // Validar datos
            if (!empleadoId || isNaN(diasACondonar) || isNaN(descuentoPorDia) || isNaN(diasSinRegistros)) {
                console.error('Datos inválidos en select:', select);
                return;
            }
            
            // Calcular nuevo descuento
            const diasConDescuento = diasSinRegistros - diasACondonar;
            const nuevoDescuento = diasConDescuento * descuentoPorDia;
            
            // Obtener elementos relevantes
            const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
            const totalPagarElement = document.getElementById('total-pagar-' + empleadoId);
            const fila = document.getElementById('fila-' + empleadoId);
            
            if (!montoDescuentoElement || !totalPagarElement || !fila) {
                console.error('Elementos no encontrados para empleado:', empleadoId);
                return;
            }
            
            // Obtener valores base
            const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
            const totalActividades = parseFloat(document.getElementById('total-actividades-' + empleadoId).textContent) || 0;
            const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
            const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
            const esGerente = fila.dataset.esGerente === 'true';
            
            // Actualizar monto de descuento
            montoDescuentoElement.textContent = nuevoDescuento.toFixed(2);
            
            // Calcular nuevo total a pagar
            let nuevoTotalPagar;
            if (esGerente) {
                nuevoTotalPagar = sueldoBase + pagoActividadesGerente + totalActividades - nuevoDescuento;
            } else {
                nuevoTotalPagar = sueldoBase + pagoActividadesBD + totalActividades - nuevoDescuento;
            }
            
            totalPagarElement.textContent = nuevoTotalPagar.toFixed(2);
            
            // Cambiar estilo visual si no hay descuento
            const descuentoElement = document.getElementById('descuento-' + empleadoId);
            if (nuevoDescuento === 0) {
                descuentoElement.classList.add('descuento-condonado');
            } else {
                descuentoElement.classList.remove('descuento-condonado');
            }
            
            // Actualizar totales generales
            actualizarTotalesGenerales();
        }

        function actualizarTotalesGenerales() {
            let totalSueldoBase = 0;
            let totalActividades = 0;
            let totalActividadesBD = 0;
            let totalDescuentos = 0;
            let totalGeneral = 0;
            
            // Recalcular todos los totales desde cero
            document.querySelectorAll('[id^="fila-"]').forEach(fila => {
                const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
                const empleadoId = fila.id.replace('fila-', '');
                const totalActividadesElement = document.getElementById('total-actividades-' + empleadoId);
                const totalActividadesEmpleado = parseFloat(totalActividadesElement ? totalActividadesElement.textContent : 0) || 0;
                const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
                const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
                const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
                const descuentoEmpleado = parseFloat(montoDescuentoElement ? montoDescuentoElement.textContent : 0) || 0;
                const esGerente = fila.dataset.esGerente === 'true';
                
                totalSueldoBase += sueldoBase;
                totalActividades += totalActividadesEmpleado;
                totalActividadesBD += pagoActividadesBD + pagoActividadesGerente;
                totalDescuentos += descuentoEmpleado;
                
                // Calcular total según tipo de empleado
                if (esGerente) {
                    totalGeneral += (sueldoBase + pagoActividadesGerente + totalActividadesEmpleado - descuentoEmpleado);
                } else {
                    totalGeneral += (sueldoBase + pagoActividadesBD + totalActividadesEmpleado - descuentoEmpleado);
                }
            });
            
            // Actualizar displays de totales generales
            const totalSueldoBaseElement = document.getElementById('total-sueldo-base');
            const totalActividadesElement = document.getElementById('total-actividades');
            const totalDescuentosElement = document.getElementById('total-descuentos');
            const totalGeneralElement = document.getElementById('total-general');
            
            if (totalSueldoBaseElement) totalSueldoBaseElement.textContent = totalSueldoBase.toFixed(2);
            if (totalActividadesElement) totalActividadesElement.textContent = (totalActividades + totalActividadesBD).toFixed(2);
            if (totalDescuentosElement) totalDescuentosElement.textContent = totalDescuentos.toFixed(2);
            if (totalGeneralElement) totalGeneralElement.textContent = totalGeneral.toFixed(2);
            
            // Actualizar el objeto global
            window.totalesGenerales = {
                sueldoBase: totalSueldoBase,
                actividades: totalActividades,
                actividadesBD: totalActividadesBD,
                descuentos: totalDescuentos,
                general: totalGeneral
            };
        }
        </script>

        <!-- ============================================
            VISTA DE ASISTENCIA
        ============================================ -->
        <!-- Tabla de Asistencia Resumen -->
        <div class="form-container-nomina">
            <h2 class="section-title-nomina">Resumen de Asistencia</h2>
            <div class="table-responsive-nomina">
                <table class="table-nomina">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Total Horas</th>
                            <th>Turnos Completos</th>
                            <th>Turnos Simples</th>
                            <th>Registros Incompletos</th>
                            <th>Días Sin Registro</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horasPorEmpleado as $id => $horas): 
                            if (in_array($id, $empleadosExcluir)) continue;
                            
                            $clasif = $clasificacionEmpleados[$id] ?? [];
                            $totalDias = 5;
                            $diasTrabajados = $totalDias - ($clasif['dias_sin_registro'] ?? 0);
                            
                            // Determinar estado
                            if ($diasTrabajados == 0) {
                                $estado = "❌ Sin registros";
                                $color = "red";
                            } elseif (($clasif['registros_incompletos'] ?? 0) > 2) {
                                $estado = "⚠️ Registros incompletos";
                                $color = "orange";
                            } elseif (($clasif['turnos_completos'] ?? 0) >= 3) {
                                $estado = "✅ Turnos completos";
                                $color = "green";
                            } else {
                                $estado = "ℹ️ Patrón mixto";
                                $color = "blue";
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($id) ?></td>
                            <td><?= htmlspecialchars($empleados[$id] ?? 'Desconocido') ?></td>
                            <td><strong><?= number_format($horas, 2) ?></strong></td>
                            <td style="color: green;"><?= $clasif['turnos_completos'] ?? 0 ?></td>
                            <td style="color: blue;"><?= $clasif['turnos_simples'] ?? 0 ?></td>
                            <td style="color: orange;"><?= $clasif['registros_incompletos'] ?? 0 ?></td>
                            <td style="color: red;"><?= $clasif['dias_sin_registro'] ?? 0 ?></td>
                            <td style="color: <?= $color ?>;"><strong><?= $estado ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reporte Detallado de Asistencia por Persona -->
        <div class="form-container-nomina">
            <h2 class="section-title-nomina">Reporte Detallado de Asistencia por Empleado</h2>
            <?php foreach ($detalleDias as $id => $dias): 
                if (in_array($id, $empleadosExcluir)) continue;
                
                $infoEmpleado = $nominaCompleta[$id] ?? null;
            ?>
            <div class="employee-detail-section">
                <h3 style="margin-top: 0; color: #333;">
                    <?= htmlspecialchars($empleados[$id] ?? "Empleado $id") ?> (ID: <?= htmlspecialchars($id) ?>)
                    <?php if ($infoEmpleado && !isset($infoEmpleado['error'])): ?>
                    - <?= htmlspecialchars($infoEmpleado['puesto'] ?? 'Sin puesto') ?>
                    <?php endif; ?>
                </h3>
                <div class="table-responsive-nomina">
                    <table class="table-nomina">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th>Registros</th>
                                <th>Entrada Real</th>
                                <th>Salida Real</th>
                                <th>Horas Trabajadas</th>
                                <th>Tipo de Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalHorasEmpleado = 0;
                            foreach ($dias as $dia => $info): 
                                $totalHorasEmpleado += $info['horas'];
                                $colorTipo = match($info['tipo']) {
                                    'turno_completo' => 'green',
                                    'turno_simple' => 'blue', 
                                    'registro_incompleto' => 'orange',
                                    'sin_registro' => 'red',
                                    default => 'black'
                                };
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($dia) ?></strong></td>
                                <td><?= htmlspecialchars($info['registros']) ?></td>
                                <td><?= htmlspecialchars($info['entrada'] ?? '--:--') ?></td>
                                <td><?= htmlspecialchars($info['salida'] ?? '--:--') ?></td>
                                <td><?= number_format($info['horas'], 2) ?> h</td>
                                <td style="color: <?= $colorTipo ?>;">
                                    <strong>
                                        <?= match($info['tipo']) {
                                            'turno_completo' => '✅ Completo (4 registros)',
                                            'turno_simple' => '🔵 Simple (2 registros)',
                                            'registro_incompleto' => '⚠️ Incompleto',
                                            'sin_registro' => '❌ Sin registro',
                                            default => $info['tipo']
                                        } ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td colspan="4" style="text-align: right;">Total Semanal:</td>
                                <td><?= number_format($totalHorasEmpleado, 2) ?> h</td>
                                <td>
                                    <?php 
                                    $clasif = $clasificacionEmpleados[$id] ?? [];
                                    echo "Completos: " . ($clasif['turnos_completos'] ?? 0) . " | ";
                                    echo "Simples: " . ($clasif['turnos_simples'] ?? 0) . " | ";
                                    echo "Incompletos: " . ($clasif['registros_incompletos'] ?? 0);
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>