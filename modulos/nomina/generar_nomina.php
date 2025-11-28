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

// Variables para el procesamiento de datos
$asistenciaData = [];
$horasPorEmpleado = [];
$empleados = [];
$clasificacionEmpleados = [];
$detalleDias = [];
$nominaCompleta = [];

// Lista de empleados a excluir del reporte
$empleadosExcluir = ['16'];

// Configuración
$jornadaCompletaHoras = 8;
$descuentoRegistrosIncompletos = 25; // $25 por día sin 4 registros

// FUNCIÓN PARA PROCESAR GUARDADO DE NÓMINA - NUEVA SECCIÓN
function procesarGuardadoNomina($pdo, &$nominaCompleta) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_nomina'])) {
        try {
            $fechaInicio = $_POST['fecha_inicio'] ?? '';
            $fechaFin = $_POST['fecha_fin'] ?? '';
            $idCuenta = $_POST['id_cuenta'] ?? '';
            $idOperador = $_POST['id_operador'] ?? '';
            
            // DECODIFICAR LOS DATOS DE NÓMINA CON VALIDACIÓN
            $nominaDataJson = $_POST['nomina_data'] ?? '{}';
            $nominaData = json_decode($nominaDataJson, true);
            
            $actividadesDataJson = $_POST['actividades_data'] ?? '{}';
            $actividadesData = json_decode($actividadesDataJson, true);
            
            // Validaciones básicas
            if (empty($fechaInicio) || empty($fechaFin) || empty($idCuenta)) {
                throw new Exception("Faltan datos requeridos para guardar la nómina");
            }

            // CORRECCIÓN PRINCIPAL: Usar los datos de la sesión y validar
            if (isset($_SESSION['nomina_completa']) && !empty($_SESSION['nomina_completa'])) {
                $nominaCompleta = $_SESSION['nomina_completa'];
                
                // DEBUG DETALLADO: Mostrar datos antes de guardar
                error_log("=== DATOS DE NÓMINA ANTES DE GUARDAR ===");
                $totalCalculado = 0;
                $empleadosValidos = 0;
                
                foreach ($nominaCompleta as $id => $nomina) {
                    if (!isset($nomina['error'])) {
                        $empleadosValidos++;
                        $totalCalculado += $nomina['total_pagar'];
                        error_log("EMPLEADO {$id}:");
                        error_log("  - Sueldo Base: {$nomina['sueldo_base']}");
                        error_log("  - Actividades BD: {$nomina['pago_actividades_bd']}");
                        error_log("  - Actividades Gerente: {$nomina['pago_actividades_gerente']}");
                        error_log("  - Actividades Seleccionadas: {$nomina['pago_actividades_seleccionadas']}");
                        error_log("  - Descuentos: {$nomina['descuento_registros']}");
                        error_log("  - Total a Pagar: {$nomina['total_pagar']}");
                    }
                }
                
                error_log("TOTAL CALCULADO DESDE SESIÓN: {$totalCalculado}");
                error_log("EMPLEADOS VÁLIDOS: {$empleadosValidos}");
                
                // DEBUG: Mostrar datos del formulario JSON
                error_log("=== DATOS DEL FORMULARIO JSON ===");
                if (!empty($nominaData)) {
                    $totalJSON = 0;
                    foreach ($nominaData as $idChecador => $datosEmpleado) {
                        $totalJSON += $datosEmpleado['total_pagar'];
                        error_log("EMPLEADO {$idChecador} desde JSON:");
                        error_log("  - Sueldo Base: {$datosEmpleado['sueldo_base']}");
                        error_log("  - Actividades Extras: {$datosEmpleado['actividades_extras']}");
                        error_log("  - Deducciones: {$datosEmpleado['deducciones']}");
                        error_log("  - Total a Pagar: {$datosEmpleado['total_pagar']}");
                    }
                    error_log("TOTAL DESDE JSON: {$totalJSON}");
                } else {
                    error_log("NO HAY DATOS EN nominaData JSON");
                }

                // ACTUALIZACIÓN CRÍTICA: Usar los datos de la sesión, NO del formulario
                // Solo actualizar campos que pueden haber cambiado dinámicamente
                if (!empty($nominaData)) {
                    foreach ($nominaData as $idChecador => $datosEmpleado) {
                        if (isset($nominaCompleta[$idChecador]) && !isset($nominaCompleta[$idChecador]['error'])) {
                            // CORRECCIÓN: Usar los valores actualizados del formulario
                            $nominaCompleta[$idChecador]['pago_actividades_seleccionadas'] = floatval(str_replace(',', '', $datosEmpleado['actividades_extras']));
                            $nominaCompleta[$idChecador]['descuento_registros'] = floatval(str_replace(',', '', $datosEmpleado['deducciones']));
                            $nominaCompleta[$idChecador]['total_pagar'] = floatval(str_replace(',', '', $datosEmpleado['total_pagar']));

                            
                            error_log("ACTUALIZADO EMPLEADO {$idChecador}:");
                            error_log("  - Nuevo Total: {$nominaCompleta[$idChecador]['total_pagar']}");
                        }
                    }
                }
                
                // Guardar en la base de datos
                $resultado = guardarNominaEnBD($pdo, $nominaCompleta, $fechaInicio, $fechaFin, $idCuenta, $idOperador);
                
                if ($resultado['success']) {
                    $_SESSION['success_message'] = "✅ Nómina guardada exitosamente. ID: " . $resultado['id_nomina_general'] . 
                                                 ". Empleados procesados: " . $resultado['empleados_insertados'] .
                                                 ". Total: $" . number_format($resultado['totales']['total_pagar'], 2);
                    
                    // DEBUG: Mostrar lo que se guardó
                    error_log("=== RESULTADO DEL GUARDADO ===");
                    error_log("Total guardado en BD: {$resultado['totales']['total_pagar']}");
                    
                    // Limpiar datos de sesión después de guardar exitosamente
                    unset($_SESSION['actividades_empleado']);
                    unset($_SESSION['nomina_completa']);
                    unset($_SESSION['horas_por_empleado']);
                    unset($_SESSION['empleados']);
                    unset($_SESSION['clasificacion_empleados']);
                    unset($_SESSION['detalle_dias']);
                    
                    // Redirigir para evitar reenvío del formulario
                    header('Location: generar_nomina.php');
                    exit;
                } else {
                    throw new Exception("Error al guardar nómina: " . $resultado['error']);
                }
                
            } else {
                throw new Exception("No hay datos de nómina disponibles. Por favor, sube el archivo de asistencia primero.");
            }
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            error_log("Error en procesarGuardadoNomina: " . $e->getMessage());
        }
    }
}

function calcularHorasTrabajadas($cadenaHoras) {
    // CORREGIDO: Validar que no sea null antes de trim()
    if (empty($cadenaHoras) || $cadenaHoras === null || trim($cadenaHoras) === '') {
        return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'sin_registros'];
    }

    try {
        // CORREGIDO: Asegurar que no sea null
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

function obtenerInformacionEmpleado($id_checador, $pdo) {
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

function obtenerActividadesExtras($pdo){
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

// NUEVA FUNCIÓN: Obtener actividades extras para gerentes generales
function obtenerActividadesExtrasGerente($pdo, $id_empleado) {
    try {
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

function guardarNominaEnBD($pdo, $nominaCompleta, $fechaInicio, $fechaFin, $idCuenta, $idOperador) {
    try {
        // Iniciar transacción para asegurar la integridad de los datos
        $pdo->beginTransaction();

        // 1. Insertar en nomina_general (encabezado)
        $totalEmpleados = 0;
        $totalSueldos = 0;
        $totalActividadesExtras = 0;
        $totalDeducciones = 0;
        $totalAPagar = 0;

        // Calcular totales generales - SOLO empleados sin error
        foreach ($nominaCompleta as $id_checador => $nomina) {
            if (!isset($nomina['error'])) {
                $totalEmpleados++;
                $totalSueldos += $nomina['sueldo_base'];
                
                // CORRECCIÓN: Calcular actividades extras correctamente
                $actividadesExtrasEmpleado = $nomina['pago_actividades_seleccionadas'] + 
                                           $nomina['pago_actividades_bd'] + 
                                           $nomina['pago_actividades_gerente'];
                
                $totalActividadesExtras += $actividadesExtrasEmpleado;
                $totalDeducciones += $nomina['descuento_registros'];
                
                // CORRECCIÓN: Usar el total_pagar ya calculado en lugar de recalcular
                $totalAPagar += $nomina['total_pagar'];
                
                error_log("Empleado {$id_checador}: Sueldo={$nomina['sueldo_base']}, Actividades={$actividadesExtrasEmpleado}, Descuentos={$nomina['descuento_registros']}, Total={$nomina['total_pagar']}");
            }
        }

        // DEBUG: Mostrar totales calculados
        error_log("TOTALES CALCULADOS: Empleados={$totalEmpleados}, Sueldos={$totalSueldos}, Actividades={$totalActividadesExtras}, Deducciones={$totalDeducciones}, Total={$totalAPagar}");

        // Insertar en nomina_general
        $stmtGeneral = $pdo->prepare("
            INSERT INTO nomina_general (
                fecha_inicio, fecha_fin, empleados_pagados, 
                total_sueldos, total_actividades_extras, total_deducciones, 
                total_a_pagar, id_cuenta, id_operador, fecha_registro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmtGeneral->execute([
            $fechaInicio,
            $fechaFin,
            $totalEmpleados,
            $totalSueldos,
            $totalActividadesExtras,
            $totalDeducciones,
            $totalAPagar, // CORREGIDO: Usar el total calculado correctamente
            $idCuenta,
            $idOperador
        ]);

        // Obtener el ID de la nómina general recién insertada
        $idNominaGeneral = $pdo->lastInsertId();

        // 2. Insertar en nomina_detalle (detalle por empleado)
        $stmtDetalle = $pdo->prepare("
            INSERT INTO nomina_detalle (
                id_nomina_general, id_empleado, dias_laborados, 
                sueldo_base, actividades_extras, deducciones, 
                total_pagar, id_operador, fecha_registro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $empleadosInsertados = 0;

        foreach ($nominaCompleta as $id_checador => $nomina) {
            if (isset($nomina['error'])) {
                continue; // Saltar empleados con error
            }

            // Obtener información completa del empleado para obtener id_empleado
            $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);
            if (!$infoEmpleado || !isset($infoEmpleado['id_empleado'])) {
                error_log("No se pudo obtener ID de empleado para ID checador: $id_checador");
                continue;
            }

            $idEmpleado = $infoEmpleado['id_empleado'];

            // CORRECCIÓN: Calcular actividades extras totales para este empleado (igual que arriba)
            $actividadesExtrasEmpleado = $nomina['pago_actividades_seleccionadas'] + 
                                       $nomina['pago_actividades_bd'] + 
                                       $nomina['pago_actividades_gerente'];

            // Insertar detalle - CORREGIDO: Usar los valores ya calculados
            $stmtDetalle->execute([
                $idNominaGeneral,
                $idEmpleado,
                $nomina['dias_trabajados'],
                $nomina['sueldo_base'],
                $actividadesExtrasEmpleado,
                $nomina['descuento_registros'],
                $nomina['total_pagar'], // CORREGIDO: Usar el total ya calculado
                $idOperador
            ]);

            $empleadosInsertados++;
        }

        // Confirmar transacción
        $pdo->commit();

        return [
            'success' => true,
            'id_nomina_general' => $idNominaGeneral,
            'empleados_insertados' => $empleadosInsertados,
            'totales' => [
                'sueldos' => $totalSueldos,
                'actividades_extras' => $totalActividadesExtras,
                'deducciones' => $totalDeducciones,
                'total_pagar' => $totalAPagar
            ]
        ];

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        
        error_log("Error al guardar nómina: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Función para obtener las cuentas bancarias disponibles
function obtenerCuentasBancarias($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_cuenta, nombre, banco, numero 
            FROM cuentas_bancarias 
            WHERE activo = 1 
            ORDER BY nombre 
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener cuentas bancarias: " . $e->getMessage());
        return [];
    }
}


function validarTotalesNomina($nominaCompleta) {
    $totalSueldoBase = 0;
    $totalActividadesBD = 0;
    $totalActividadesSeleccionadas = 0;
    $totalDescuentos = 0;
    $totalPagar = 0;
    
    foreach ($nominaCompleta as $id_checador => $nomina) {
        if (!isset($nomina['error'])) {
            $totalSueldoBase += $nomina['sueldo_base'];
            $totalActividadesBD += $nomina['pago_actividades_bd'] + $nomina['pago_actividades_gerente'];
            $totalActividadesSeleccionadas += $nomina['pago_actividades_seleccionadas'];
            $totalDescuentos += $nomina['descuento_registros'];
            $totalPagar += $nomina['total_pagar'];
        }
    }
    
    // Verificar que la suma sea consistente
    $totalCalculado = $totalSueldoBase + $totalActividadesBD + $totalActividadesSeleccionadas - $totalDescuentos;
    
    error_log("VALIDACIÓN DE TOTALES:");
    error_log("Sueldo Base: $totalSueldoBase");
    error_log("Actividades BD: $totalActividadesBD");
    error_log("Actividades Seleccionadas: $totalActividadesSeleccionadas");
    error_log("Descuentos: $totalDescuentos");
    error_log("Total Pagar (calculado): $totalCalculado");
    error_log("Total Pagar (real): $totalPagar");
    error_log("Diferencia: " . ($totalPagar - $totalCalculado));
    
    return [
        'sueldo_base' => $totalSueldoBase,
        'actividades_bd' => $totalActividadesBD,
        'actividades_seleccionadas' => $totalActividadesSeleccionadas,
        'descuentos' => $totalDescuentos,
        'total_pagar' => $totalPagar,
        'total_calculado' => $totalCalculado,
        'diferencia' => $totalPagar - $totalCalculado
    ];
}

// Llama a esta función después de calcular la nómina completa
if (!empty($nominaCompleta)) {
    // Validar totales
    $validacion = validarTotalesNomina($nominaCompleta);
    
    // Si hay discrepancia, usar los totales calculados consistentemente
    if (abs($validacion['diferencia']) > 0.01) {
        error_log("ADVERTENCIA: Discrepancia en totales. Usando totales calculados.");
        $totalGeneralSueldoBase = $validacion['sueldo_base'];
        $totalGeneralActividadesBD = $validacion['actividades_bd'];
        $totalGeneralActividadesSeleccionadas = $validacion['actividades_seleccionadas'];
        $totalGeneralDescuentos = $validacion['descuentos'];
        $totalGeneralPagar = $validacion['total_calculado']; // Usar el calculado para consistencia
    }
    
    // Guardar datos en sesión
    $_SESSION['nomina_completa'] = $nominaCompleta;
    $_SESSION['horas_por_empleado'] = $horasPorEmpleado;
    $_SESSION['empleados'] = $empleados;
    $_SESSION['clasificacion_empleados'] = $clasificacionEmpleados;
    $_SESSION['detalle_dias'] = $detalleDias;
}

// CORRECCIÓN: Cargar datos desde sesión si existen
if (isset($_SESSION['nomina_completa']) && !empty($_SESSION['nomina_completa'])) {
    $nominaCompleta = $_SESSION['nomina_completa'];
    $horasPorEmpleado = $_SESSION['horas_por_empleado'] ?? [];
    $empleados = $_SESSION['empleados'] ?? [];
    $clasificacionEmpleados = $_SESSION['clasificacion_empleados'] ?? [];
    $detalleDias = $_SESSION['detalle_dias'] ?? [];
}

$actividadesExtras = obtenerActividadesExtras($pdo);

// Inicializar variables para actividades
$actividadesSeleccionadasGlobal = [];
$totalPagoActividadesGlobal = 0;

// Variables para totales generales
$totalGeneralSueldoBase = 0;
$totalGeneralActividadesBD = 0;
$totalGeneralActividadesSeleccionadas = 0;
$totalGeneralDescuentos = 0;
$totalGeneralPagar = 0;

// CORRECCIÓN IMPORTANTE: Reordenar el flujo
// 1. Primero procesar el guardado de nómina
procesarGuardadoNomina($pdo, $nominaCompleta);

// 2. Luego procesar el archivo de asistencia (si se envió)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['asistencia_file'])) {
    try {
        // Procesar actividades seleccionadas si se enviaron
        if (isset($_POST['actividades_empleado'])) {
            $actividadesSeleccionadasGlobal = $_POST['actividades_empleado'];
            
            foreach ($actividadesSeleccionadasGlobal as $id_checador => $actividadesEmpleado) {
                $totalPagoActividadesGlobal = 0;
                $actividadesIds = []; // Array para guardar los IDs de actividades seleccionadas
                
                foreach ($actividadesEmpleado as $idActividad => $valor) {
                    foreach ($actividadesExtras as $actividad) {
                        if ($actividad['id_actividad'] == $idActividad) {
                            $totalPagoActividadesGlobal += floatval($actividad['pago_extra']);
                            $actividadesIds[] = $idActividad; // Guardar el ID de la actividad
                            break;
                        }
                    }
                }
                
                // Guardar tanto el total como los IDs de actividades seleccionadas
                $_SESSION['actividades_empleado'][$id_checador] = [
                    'total' => $totalPagoActividadesGlobal,
                    'actividades_ids' => $actividadesIds
                ];
            }
        }

        if (!isset($_FILES['asistencia_file']) || $_FILES['asistencia_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo. Código de error: " . $_FILES['asistencia_file']['error']);
        }

        $fileTmpPath = $_FILES['asistencia_file']['tmp_name'];
        $fileName = $_FILES['asistencia_file']['name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Verificar que el archivo existe
        if (!file_exists($fileTmpPath)) {
            throw new Exception("El archivo temporal no existe");
        }

        if ($fileType === 'xls' || $fileType === 'xlsx') {
            $spreadsheet = IOFactory::load($fileTmpPath);
            
            // VERIFICAR Y MOSTRAR INFORMACIÓN DE LAS HOJAS
            $sheetNames = $spreadsheet->getSheetNames();
            error_log("Hojas disponibles en el archivo: " . implode(", ", $sheetNames));
            
            // Obtener la tercera hoja (índice 2)
            if (count($sheetNames) >= 3) {
                $sheet = $spreadsheet->getSheet(2); // Tercera hoja (índice 2)
                error_log("Leyendo la tercera hoja: " . $sheetNames[2]);
            } else {
                // Si no hay tercera hoja, usar la primera
                $sheet = $spreadsheet->getSheet(0);
                error_log("No hay tercera hoja, usando la primera: " . $sheetNames[0]);
            }
            
            $data = $sheet->toArray(null, true, true, true);
            
            // DEBUG: Mostrar primeras filas para diagnóstico
            error_log("Primeras 5 filas de datos:");
            $counter = 0;
            foreach ($data as $numeroFila => $row) {
                if ($counter >= 5) break;
                error_log("Fila $numeroFila: " . json_encode($row));
                $counter++;
            }

            foreach ($data as $numeroFila => $row) {
                // BUSCAR PATRÓN MÁS FLEXIBLE PARA IDENTIFICAR EMPLEADOS
                $id_checador = null;
                $nombre = null;
                
                // Buscar en todas las columnas posibles
                foreach ($row as $col => $value) {
                    // CORREGIDO: Validar que no sea null antes de trim()
                    $value = $value !== null ? trim($value) : '';
                    
                    // Buscar ID numérico
                    if (is_numeric($value) && $value > 0 && $value < 10000) {
                        $id_checador = $value;
                        // Intentar obtener nombre de columnas adyacentes
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
                        // Buscar nombre en la misma fila
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

                    // Procesar cada día (Lunes a Viernes) - buscar en columnas que contengan horas
                    $diasProcesados = 0;
                    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                    
                    foreach ($horasFila as $col => $valorCelda) {
                        if ($diasProcesados >= 5) break;
                        
                        // CORREGIDO: Validar que no sea null antes de trim()
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
                    
                    // Si no encontramos suficientes días con datos, marcar los restantes como sin registro
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
                    
                    error_log("Procesado empleado ID: $id_checador, Nombre: " . $empleados[$id_checador] . ", Días procesados: $diasProcesados");
                }
            }

            // DEBUG: Mostrar resultados del procesamiento
            error_log("Total empleados procesados: " . count($horasPorEmpleado));
            error_log("IDs procesados: " . implode(", ", array_keys($horasPorEmpleado)));

            // CALCULAR NÓMINA COMPLETA - CÓDIGO MEJORADO
            if (!empty($horasPorEmpleado)) {
                foreach ($horasPorEmpleado as $id_checador => $totalHoras) {
                    if (in_array($id_checador, $empleadosExcluir)) {
                        continue;
                    }

                    $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);
                    
                    if (!$infoEmpleado) {
                        $nominaCompleta[$id_checador] = [
                            'error' => 'Empleado no encontrado en BD',
                            'nombre' => $empleados[$id_checador] ?? 'Desconocido'
                        ];
                        continue;
                    }

                    // NUEVA LÓGICA: Verificar si es gerente general
                    $esGerenteGeneral = ($infoEmpleado['nivel_jerarquico'] === 'gerente_general');
                    
                    // Cálculos de nómina
                    $sueldoDiario = floatval($infoEmpleado['sueldo_diario'] ?? 0);
                    
                    // LÓGICA CORREGIDA: Diferente cálculo según puesto
                    if ($esGerenteGeneral) {
                        // Para gerente general: contar los días en dias_laborales
                        $diasLaboralesStr = $infoEmpleado['dias_laborales'] ?? '';
                        $diasArray = explode(',', $diasLaboralesStr);
                        $diasArray = array_filter(array_map('trim', $diasArray)); // Limpiar y quitar vacíos
                        $cantidadDiasLaborales = count($diasArray);
                        // Asegurar que siempre tenga un valor
                        if ($cantidadDiasLaborales === 0) {
                            $cantidadDiasLaborales = 5; // Valor por defecto
                        }
                        
                        $diasTrabajados = $cantidadDiasLaborales; // Usamos la cantidad de días laborales
                        $sueldoBase = $sueldoDiario * $cantidadDiasLaborales;
                        $descuentoRegistros = 0; // Sin descuentos por registros
                        $diasSin4Registros = 0; // No aplica para gerente general
                        
                        // NUEVO: Obtener actividades extras específicas para gerente general
                        $actividadesGerente = obtenerActividadesExtrasGerente($pdo, $infoEmpleado['id_empleado']);
                        $pagoActividadesGerente = 0;
                        foreach ($actividadesGerente as $actividad) {
                            $pagoActividadesGerente += floatval($actividad['total_pago']);
                        }
                        
                    } else {
                        // Para empleados normales: cálculo actual basado en asistencia
                        $diasTrabajados = 5 - ($clasificacionEmpleados[$id_checador]['dias_sin_registro'] ?? 5);
                        $diasTrabajados = max($diasTrabajados, 0); // mínimo permitido = 0
                        $diasTrabajados = abs($diasTrabajados); // elimina negativo si aparece
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
                    
                    // Pago por actividades seleccionadas manualmente
                    $pagoActividadesSeleccionadas = $_SESSION['actividades_empleado'][$id_checador]['total'] ?? 0;
                    
                    // Total CORREGIDO - incluir pago de actividades según el tipo de empleado
                    if ($esGerenteGeneral) {
                        // Para gerente: sueldo base + actividades de gerente + actividades seleccionadas
                        $totalPagar = $sueldoBase + $pagoActividadesGerente + $pagoActividadesSeleccionadas;
                    } else {
                        // Para empleados normales: sueldo base + actividades BD + actividades seleccionadas - descuentos
                        $totalPagar = $sueldoBase + $pagoActividades + $pagoActividadesSeleccionadas - $descuentoRegistros;
                    }

                    $nominaCompleta[$id_checador] = [
                        'id_empleado' => $infoEmpleado['id_empleado'], // AÑADIDO: Incluir ID del empleado
                        'nombre_completo' => $infoEmpleado['nombre_completo'],
                        'puesto' => $infoEmpleado['puesto'] ?? 'No asignado',
                        'nivel_jerarquico' => $infoEmpleado['nivel_jerarquico'] ?? 'normal',
                        'sueldo_diario' => $sueldoDiario,
                        'dias_trabajados' => $diasTrabajados,
                        'dias_laborales' => $infoEmpleado['dias_laborales'] ?? '',
                        'cantidad_dias_laborales' => $cantidadDiasLaborales ?? $diasTrabajados,
                        'sueldo_base' => $sueldoBase,
                        'pago_actividades_bd' => $pagoActividades,
                        'pago_actividades_gerente' => $pagoActividadesGerente,
                        'pago_actividades_seleccionadas' => $pagoActividadesSeleccionadas,
                        'descuentos_horarios' => 0,
                        'descuento_registros' => $descuentoRegistros,
                        'total_pagar' => $totalPagar,
                        'horario_esperado' => ($infoEmpleado['hora_entrada'] ?? '--:--') . ' - ' . ($infoEmpleado['hora_salida'] ?? '--:--'),
                        'dias_sin_4_registros' => $diasSin4Registros ?? 0,
                        'es_nivel_jerarquico' => $esGerenteGeneral,
                        'actividades_gerente' => $actividadesGerente ?? []
                    ];

                    // Acumular totales generales (actualizado para incluir actividades de gerente)
                    $totalGeneralSueldoBase += $sueldoBase;
                    $totalGeneralActividadesBD += $pagoActividades + $pagoActividadesGerente; // Incluir ambas
                    $totalGeneralActividadesSeleccionadas += $pagoActividadesSeleccionadas;
                    $totalGeneralDescuentos += $descuentoRegistros;
                    $totalGeneralPagar += $totalPagar;
                }
            }

            // CORRECCIÓN: Guardar datos en sesión después de procesar el archivo
            if (!empty($nominaCompleta)) {
                $_SESSION['nomina_completa'] = $nominaCompleta;
                $_SESSION['horas_por_empleado'] = $horasPorEmpleado;
                $_SESSION['empleados'] = $empleados;
                $_SESSION['clasificacion_empleados'] = $clasificacionEmpleados;
                $_SESSION['detalle_dias'] = $detalleDias;
            }

        } elseif ($fileType === 'csv') {
            $_SESSION['error_message'] = "Procesamiento de CSV temporalmente no disponible";
            header('Location: generar_nomina.php');
            exit;
        } else {
            throw new Exception("Error: Solo se permiten archivos de tipo CSV, XLS o XLSX.");
        }

    } catch (ReaderException $e) {
        $error = "Error al leer el archivo de Excel: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log($error);
        header('Location: generar_nomina.php');
        exit;
    } catch (Exception $e) {
        $error = "Error general: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log($error);
        header('Location: generar_nomina.php');
        exit;
    }
}

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

    /* Agregar en la sección de estilos */
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

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" style="width: 100% !important;">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success" style="width: 100% !important;">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        
        // Mostrar información de depuración si no hay datos
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($horasPorEmpleado)) {
            echo '<div class="alert alert-warning" style="width: 100% !important;">';
            echo '<h4>Información de Depuración:</h4>';
            echo '<p>No se encontraron datos de empleados en el archivo.</p>';
            echo '</div>';
        }
        ?>

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
        
        <!-- Formulario para seleccionar actividades por empleado -->
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
                        // Reinicializar totales
                        $totalGeneralSueldoBase = 0;
                        $totalGeneralActividadesBD = 0;
                        $totalGeneralActividadesSeleccionadas = 0;
                        $totalGeneralDescuentos = 0;
                        $totalGeneralPagar = 0;
                        
                        foreach ($nominaCompleta as $id_checador => $nomina): 
                            if (isset($nomina['error'])): 
                        ?>
                            <tr style="background-color: #ffe6e6;">
                                <td><?= $id_checador ?></td>
                                <td colspan="10" style="color: red;">
                                    <?= $nomina['nombre'] ?> - <?= $nomina['error'] ?>
                                </td>
                            </tr>
                        <?php else: 
                            // Obtener IDs de actividades seleccionadas
                            $actividadesSeleccionadasIds = $_SESSION['actividades_empleado'][$id_checador]['actividades_ids'] ?? [];
                            
                            // Calcular totales para este empleado
                            $sueldoBase = $nomina['sueldo_base'];
                            $descuentoRegistros = $nomina['descuento_registros'];
                            $pagoActividadesBD = $nomina['pago_actividades_bd'];
                            $pagoActividadesGerente = $nomina['pago_actividades_gerente'];
                            $pagoActividadesSeleccionadas = $nomina['pago_actividades_seleccionadas'];
                            $totalPagar = $nomina['total_pagar'];

                            // Acumular totales generales
                            $totalGeneralSueldoBase += $sueldoBase;
                            $totalGeneralActividadesBD += $pagoActividadesBD + $pagoActividadesGerente;
                            $totalGeneralActividadesSeleccionadas += $pagoActividadesSeleccionadas;
                            $totalGeneralDescuentos += $descuentoRegistros;
                            $totalGeneralPagar += $totalPagar;
                        ?>
                            <tr id="fila-<?= $id_checador ?>" 
                                data-sueldo-base="<?= $sueldoBase ?>" 
                                data-descuento-registros="<?= $descuentoRegistros ?>"
                                data-pago-actividades-bd="<?= $pagoActividadesBD ?>"
                                data-pago-actividades-gerente="<?= $pagoActividadesGerente ?>"
                                data-es-gerente="<?= $nomina['es_nivel_jerarquico'] ? 'true' : 'false' ?>">
                                <td><?= $id_checador ?></td>
                                <td><?= htmlspecialchars($nomina['nombre_completo']) ?></td>
                                <td>
                                    <?= htmlspecialchars($nomina['puesto']) ?>
                                    <?php if (isset($nomina['es_nivel_jerarquico']) && $nomina['es_nivel_jerarquico']): ?>
                                        <br><small style="color: green;">(Gerente General)</small>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($nomina['sueldo_diario'], 2) ?></td>
                                <td>
                                    <?= $nomina['dias_trabajados'] ?>
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
                                                    name="actividades_empleado[<?= $id_checador ?>][<?= $actividad['id_actividad'] ?>]"
                                                    data-empleado="<?= $id_checador ?>"
                                                    data-valor="<?= $actividad['pago_extra'] ?>"
                                                    id="act_<?= $id_checador ?>_<?= $actividad['id_actividad'] ?>"
                                                    value="1"
                                                    <?= $checked ?>>
                                                <label for="act_<?= $id_checador ?>_<?= $actividad['id_actividad'] ?>" style="font-size: 12px;">
                                                    <?= htmlspecialchars($actividad['nombre']) ?> - $<?= number_format($actividad['pago_extra'], 2) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; font-weight: bold; text-align: center;">
                                        Total: $<span id="total-actividades-<?= $id_checador ?>"><?= number_format($pagoActividadesSeleccionadas, 2) ?></span>
                                    </div>
                                </td>

                                <!-- Días sin 4 reg. -->
                                <td style="color: orange; font-weight: bold;">
                                    <?= $nomina['dias_sin_4_registros'] ?> días
                                </td>

                                <!-- Días a condonar -->
                                <td>
                                    <?php if (!(isset($nomina['es_nivel_jerarquico']) && $nomina['es_nivel_jerarquico'])): ?>
                                        <select class="dias-condonar" 
                                                id="condonar-<?= $id_checador ?>" 
                                                data-empleado="<?= $id_checador ?>"
                                                data-descuento-por-dia="<?= $descuentoRegistrosIncompletos ?? 0 ?>"
                                                data-dias-sin-registros="<?= $nomina['dias_sin_4_registros'] ?>">
                                            <?php for ($i = 0; $i <= $nomina['dias_sin_4_registros']; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?> días</option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php else: ?>
                                        <span style="color: green;">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Descuento aplicado -->
                                <td class="negative-amount" id="descuento-<?= $id_checador ?>">
                                    <?php if (isset($nomina['es_nivel_jerarquico']) && $nomina['es_nivel_jerarquico']): ?>
                                        <span style="color: green;">SIN DESCUENTO</span>
                                    <?php else: ?>
                                        -$<span id="monto-descuento-<?= $id_checador ?>"><?= number_format($descuentoRegistros, 2) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total a pagar -->
                                <td style="background-color: #e6ffe6; font-weight: bold;" class="positive-amount">
                                    $<span id="total-pagar-<?= $id_checador ?>"><?= number_format($totalPagar, 2) ?></span>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                        
                        <!-- Totales generales -->
                        <tr class="total-row">
                            <td colspan="5" style="text-align: right; font-weight: bold;">TOTALES GENERALES:</td>
                            <td style="font-weight: bold;">$<span id="total-sueldo-base"><?= number_format($totalGeneralSueldoBase, 2) ?></span></td>
                            <td style="font-weight: bold;">$<span id="total-actividades"><?= number_format($totalGeneralActividadesSeleccionadas + $totalGeneralActividadesBD, 2) ?></span></td>
                            <td></td>
                            <td></td>
                            <td style="font-weight: bold;">-$<span id="total-descuentos"><?= number_format($totalGeneralDescuentos, 2) ?></span></td>
                            <td style="font-weight: bold; background-color: #d4edda;">$<span id="total-general"><?= number_format($totalGeneralPagar, 2) ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p><small>* Descuento de $25 por cada día sin 4 registros completos</small></p>
        </div>

        <!-- FORMULARIO PARA GUARDAR NÓMINA - AQUÍ ESTÁ EL BOTÓN DE GUARDAR -->
        <div class="form-container-nomina">
            <h2 class="section-title-nomina">Guardar Nómina en Base de Datos</h2>
            
            <?php
            // Obtener cuentas bancarias
            $cuentasBancarias = obtenerCuentasBancarias($pdo);
            $idOperador = $_SESSION['user_id'] ?? 1; // ID del usuario logueado
            ?>

            <!-- FORMULARIO DE GUARDADO -->
            <form id="form-guardar-nomina" action="generar_nomina.php" method="post">
                <!-- Campos ocultos con los datos de la nómina -->
                <input type="hidden" name="guardar_nomina" value="1">
                <input type="hidden" name="nomina_data" id="nomina_data" value="">
                <input type="hidden" name="actividades_data" id="actividades_data" value="">
                <input type="hidden" name="id_operador" value="<?= $idOperador ?>">
                
                <div class="form-group-nomina">
                    <label for="fecha_inicio">Fecha de Inicio del Período:</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" required 
                           value="<?= date('Y-m-d', strtotime('last monday')) ?>">
                </div>
                
                <div class="form-group-nomina">
                    <label for="fecha_fin">Fecha de Fin del Período:</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" required
                           value="<?= date('Y-m-d', strtotime('last friday')) ?>">
                </div>
                
                <div class="form-group-nomina">
                    <label for="id_cuenta">Cuenta Bancaria para Pago:</label>
                    <select name="id_cuenta" id="id_cuenta" required>
                        <option value="">Seleccione una cuenta</option>
                        <?php foreach ($cuentasBancarias as $cuenta): ?>
                            <option value="<?= $cuenta['id_cuenta'] ?>">
                                <?= htmlspecialchars($cuenta['nombre']) ?> - 
                                <?= htmlspecialchars($cuenta['banco']) ?> 
                                (****<?= substr($cuenta['numero'], -4) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="resumen-totales" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>Resumen de la Nómina a Guardar:</h4>
                    <div class="totales-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <div><strong>Total Empleados:</strong> <span id="resumen-empleados"><?= count(array_filter($nominaCompleta, function($nomina) { return !isset($nomina['error']); })) ?></span></div>
                        <div><strong>Total Sueldos:</strong> $<span id="resumen-sueldos"><?= number_format($totalGeneralSueldoBase, 2) ?></span></div>
                        <div><strong>Total Actividades Extras:</strong> $<span id="resumen-actividades"><?= number_format($totalGeneralActividadesSeleccionadas + $totalGeneralActividadesBD, 2) ?></span></div>
                        <div><strong>Total Deducciones:</strong> $<span id="resumen-deducciones"><?= number_format($totalGeneralDescuentos, 2) ?></span></div>
                        <div><strong>Total a Pagar:</strong> $<span id="resumen-total"><?= number_format($totalGeneralPagar, 2) ?></span></div>
                    </div>
                    <!-- AÑADIR ESTA LÍNEA PARA DEBUG -->
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        <strong>DEBUG:</strong> 
                        Sueldos: $<?= number_format($totalGeneralSueldoBase, 2) ?> | 
                        Actividades: $<?= number_format($totalGeneralActividadesSeleccionadas + $totalGeneralActividadesBD, 2) ?> | 
                        Deducciones: $<?= number_format($totalGeneralDescuentos, 2) ?> | 
                        Total: $<?= number_format($totalGeneralPagar, 2) ?>
                    </div>
                </div>
                
                <!-- BOTÓN DE GUARDAR NÓMINA -->
                <button type="submit" class="btn-submit-nomina" style="background: #28a745;">
                    💾 Guardar Nómina en Base de Datos
                </button>
            </form>
        </div>

        <!-- JavaScript para calcular totales en tiempo real -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar totales desde PHP
            window.totalesGenerales = {
                sueldoBase: <?= $totalGeneralSueldoBase ?? 0 ?>,
                actividadesBD: <?= $totalGeneralActividadesBD ?? 0 ?>,
                actividadesSeleccionadas: <?= $totalGeneralActividadesSeleccionadas ?? 0 ?>,
                descuentos: <?= $totalGeneralDescuentos ?? 0 ?>,
                general: <?= $totalGeneralPagar ?? 0 ?>
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

            // Función para preparar los datos antes de enviar el formulario
            document.getElementById('form-guardar-nomina').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Preparar datos de la nómina
                const nominaData = {};
                const actividadesData = {};
                
                // Recopilar datos de cada empleado
                document.querySelectorAll('[id^="fila-"]').forEach(fila => {
                    const empleadoId = fila.id.replace('fila-', '');
                    
                    // Solo procesar empleados sin error
                    if (!fila.querySelector('.alert-danger')) {
                        const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
                        const descuentoRegistros = parseFloat(document.getElementById('monto-descuento-' + empleadoId)?.textContent) || 0;
                        const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
                        const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
                        const totalActividadesSeleccionadas = parseFloat(document.getElementById('total-actividades-' + empleadoId)?.textContent) || 0;
                        const totalPagar = parseFloat(document.getElementById('total-pagar-' + empleadoId)?.textContent) || 0;
                        const esGerente = fila.dataset.esGerente === 'true';
                        
                        // Calcular actividades extras totales
                        const actividadesExtrasTotales = totalActividadesSeleccionadas + pagoActividadesBD + pagoActividadesGerente;
                        
                        // Obtener días trabajados
                        let diasTrabajados = 0;
                        if (esGerente) {
                            // Para gerentes, buscar en los datos originales de PHP
                            const diasTrabajadosElement = fila.querySelector('td:nth-child(5)');
                            diasTrabajados = parseInt(diasTrabajadosElement?.textContent) || 5;
                        } else {
                            // Para empleados normales
                            const diasTrabajadosElement = fila.querySelector('td:nth-child(5)');
                            diasTrabajados = parseInt(diasTrabajadosElement?.textContent) || 5;
                        }
                        
                        nominaData[empleadoId] = {
                            sueldo_base: sueldoBase,
                            actividades_extras: actividadesExtrasTotales,
                            deducciones: descuentoRegistros,
                            total_pagar: totalPagar,
                            dias_trabajados: diasTrabajados,
                            es_gerente: esGerente
                        };
                        
                        // Recopilar actividades seleccionadas
                        const actividadesEmpleado = [];
                        document.querySelectorAll('.actividad-checkbox[data-empleado="' + empleadoId + '"]:checked').forEach(checkbox => {
                            actividadesEmpleado.push(checkbox.name.match(/\[(\d+)\]$/)[1]);
                        });
                        
                        actividadesData[empleadoId] = actividadesEmpleado;
                    }
                });
                
                // Validar que hay datos para guardar
                if (Object.keys(nominaData).length === 0) {
                    alert('No hay datos de nómina para guardar. Por favor, verifica que se haya procesado el archivo de asistencia.');
                    return;
                }
                
                // Asignar datos a los campos ocultos
                document.getElementById('nomina_data').value = JSON.stringify(nominaData);
                document.getElementById('actividades_data').value = JSON.stringify(actividadesData);
                
                // Mostrar confirmación
                if (confirm('¿Está seguro de guardar la nómina en la base de datos? Esta acción no se puede deshacer.')) {
                    this.submit();
                }

                const totalCalculado = parseFloat(document.getElementById('total-general').textContent) || 0;
                const totalResumen = parseFloat(document.getElementById('resumen-total').textContent) || 0;
                
                if (totalCalculado === 0) {
                    alert('Error: El total calculado es $0.00. Verifica los datos de la nómina.');
                    return;
                }
                
                if (Math.abs(totalCalculado - totalResumen) > 0.01) {
                    console.warn('Advertencia: Los totales no coinciden completamente', {
                        calculado: totalCalculado,
                        resumen: totalResumen
                    });
                }

            });

        });

        function calcularTotal(checkbox) {
            const empleadoId = checkbox.dataset.empleado;
            const valorActividad = parseFloat(checkbox.dataset.valor);
            const estaMarcado = checkbox.checked;
            
            // Obtener los elementos relevantes
            const totalActividadesElement = document.getElementById('total-actividades-' + empleadoId);
            const totalPagarElement = document.getElementById('total-pagar-' + empleadoId);
            const fila = document.getElementById('fila-' + empleadoId);
            
            // Obtener valores base
            let totalActividadesActual = parseFloat(totalActividadesElement.textContent) || 0;
            const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
            const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
            const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
            const esGerente = fila.dataset.esGerente === 'true';
            
            // Obtener descuento actual
            const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
            const descuentoActual = parseFloat(montoDescuentoElement?.textContent) || 0;
            
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
            
            // Calcular nuevo total a pagar (incluye actividades de BD y gerente según corresponda)
            let nuevoTotalPagar;
            if (esGerente) {
                // Para gerente: sueldo base + actividades de gerente + actividades seleccionadas
                nuevoTotalPagar = sueldoBase + pagoActividadesGerente + totalActividadesActual - descuentoActual;
            } else {
                // Para empleados normales: sueldo base + actividades BD + actividades seleccionadas - descuentos
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
            
            // Calcular nuevo descuento
            const diasConDescuento = diasSinRegistros - diasACondonar;
            const nuevoDescuento = diasConDescuento * descuentoPorDia;
            
            // Obtener elementos relevantes
            const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
            const totalPagarElement = document.getElementById('total-pagar-' + empleadoId);
            const fila = document.getElementById('fila-' + empleadoId);
            
            // Obtener valores base
            const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
            const totalActividades = parseFloat(document.getElementById('total-actividades-' + empleadoId).textContent) || 0;
            const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
            const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
            const esGerente = fila.dataset.esGerente === 'true';
            
            // Actualizar monto de descuento
            if (montoDescuentoElement) {
                montoDescuentoElement.textContent = nuevoDescuento.toFixed(2);
            }
            
            // Calcular nuevo total a pagar (incluye actividades según el tipo de empleado)
            let nuevoTotalPagar;
            if (esGerente) {
                nuevoTotalPagar = sueldoBase + pagoActividadesGerente + totalActividades - nuevoDescuento;
            } else {
                nuevoTotalPagar = sueldoBase + pagoActividadesBD + totalActividades - nuevoDescuento;
            }
            
            totalPagarElement.textContent = nuevoTotalPagar.toFixed(2);
            
            // Cambiar estilo visual si no hay descuento
            const descuentoElement = document.getElementById('descuento-' + empleadoId);
            if (descuentoElement) {
                if (nuevoDescuento === 0) {
                    descuentoElement.classList.add('descuento-condonado');
                } else {
                    descuentoElement.classList.remove('descuento-condonado');
                }
            }
            
            // Actualizar totales generales
            actualizarTotalesGenerales();
        }

        function actualizarTotalesGenerales() {
        let totalSueldoBase = 0;
        let totalActividadesSeleccionadas = 0;
        let totalActividadesBD = 0;
        let totalDescuentos = 0;
        let totalGeneral = 0;
        
        // Recalcular todos los totales desde cero
        document.querySelectorAll('[id^="fila-"]').forEach(fila => {
            const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
            const empleadoId = fila.id.replace('fila-', '');
            const totalActividadesElement = document.getElementById('total-actividades-' + empleadoId);
            const totalActividadesEmpleado = parseFloat(totalActividadesElement?.textContent) || 0;
            const pagoActividadesBD = parseFloat(fila.dataset.pagoActividadesBd) || 0;
            const pagoActividadesGerente = parseFloat(fila.dataset.pagoActividadesGerente) || 0;
            const montoDescuentoElement = document.getElementById('monto-descuento-' + empleadoId);
            const descuentoEmpleado = parseFloat(montoDescuentoElement?.textContent) || 0;
            const esGerente = fila.dataset.esGerente === 'true';
            
            totalSueldoBase += sueldoBase;
            totalActividadesSeleccionadas += totalActividadesEmpleado;
            totalActividadesBD += pagoActividadesBD + pagoActividadesGerente;
            totalDescuentos += descuentoEmpleado;
            
            // Calcular total según tipo de empleado
            if (esGerente) {
                totalGeneral += (sueldoBase + pagoActividadesGerente + totalActividadesEmpleado - descuentoEmpleado);
            } else {
                totalGeneral += (sueldoBase + pagoActividadesBD + totalActividadesEmpleado - descuentoEmpleado);
            }
        });
        
        // Actualizar displays de totales generales en la TABLA
        document.getElementById('total-sueldo-base').textContent = totalSueldoBase.toFixed(2);
        document.getElementById('total-actividades').textContent = (totalActividadesSeleccionadas + totalActividadesBD).toFixed(2);
        document.getElementById('total-descuentos').textContent = totalDescuentos.toFixed(2);
        document.getElementById('total-general').textContent = totalGeneral.toFixed(2);
        
        // ACTUALIZAR TAMBIÉN EL RESUMEN
        document.getElementById('resumen-sueldos').textContent = totalSueldoBase.toFixed(2);
        document.getElementById('resumen-actividades').textContent = (totalActividadesSeleccionadas + totalActividadesBD).toFixed(2);
        document.getElementById('resumen-deducciones').textContent = totalDescuentos.toFixed(2);
        document.getElementById('resumen-total').textContent = totalGeneral.toFixed(2);
        document.getElementById('debug-total').textContent = totalGeneral.toFixed(2);
        
        // Actualizar el objeto global
        window.totalesGenerales = {
        sueldoBase: totalSueldoBase,
        actividadesSeleccionadas: totalActividadesSeleccionadas,
        actividadesBD: totalActividadesBD,
                descuentos: totalDescuentos,
                general: totalGeneral
            };
            
            console.log('Totales actualizados:', window.totalesGenerales);
        }
        </script>

        <!-- El resto del código para las otras tablas permanece igual -->
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
                            
                            $clasif = $clasificacionEmpleados[$id];
                            $totalDias = 5;
                            $diasTrabajados = $totalDias - $clasif['dias_sin_registro'];
                            
                            // Determinar estado
                            if ($diasTrabajados == 0) {
                                $estado = "❌ Sin registros";
                                $color = "red";
                            } elseif ($clasif['registros_incompletos'] > 2) {
                                $estado = "⚠️ Registros incompletos";
                                $color = "orange";
                            } elseif ($clasif['turnos_completos'] >= 3) {
                                $estado = "✅ Turnos completos";
                                $color = "green";
                            } else {
                                $estado = "ℹ️ Patrón mixto";
                                $color = "blue";
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($id) ?></td>
                            <td><?= htmlspecialchars($empleados[$id]) ?></td>
                            <td><strong><?= number_format($horas, 2) ?></strong></td>
                            <td style="color: green;"><?= $clasif['turnos_completos'] ?></td>
                            <td style="color: blue;"><?= $clasif['turnos_simples'] ?></td>
                            <td style="color: orange;"><?= $clasif['registros_incompletos'] ?></td>
                            <td style="color: red;"><?= $clasif['dias_sin_registro'] ?></td>
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
            ?>
            <div class="employee-detail-section">
                <h3 style="margin-top: 0; color: #333;">
                    <?= htmlspecialchars($empleados[$id]) ?> (ID: <?= $id ?>)
                    <?php if (isset($nominaCompleta[$id]['puesto'])): ?>
                    - <?= htmlspecialchars($nominaCompleta[$id]['puesto']) ?>
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
                                <td><strong><?= $dia ?></strong></td>
                                <td><?= htmlspecialchars($info['registros']) ?></td>
                                <td><?= $info['entrada'] ?? '--:--' ?></td>
                                <td><?= $info['salida'] ?? '--:--' ?></td>
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
                                    $clasif = $clasificacionEmpleados[$id];
                                    echo "Completos: {$clasif['turnos_completos']} | ";
                                    echo "Simples: {$clasif['turnos_simples']} | ";
                                    echo "Incompletos: {$clasif['registros_incompletos']}";
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