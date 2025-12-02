<?php
/**
 * Funciones auxiliares para el sistema de nómina
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// Lista de empleados a excluir del reporte (debería estar en config)
$empleadosExcluir = ['16'];
$jornadaCompletaHoras = 8;
$descuentoRegistrosIncompletos = 25; // $25 por día sin 4 registros

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

/**
 * Validar y procesar archivo Excel
 */
function procesarArchivoExcel($fileTmpPath, $pdo, $empleadosExcluir) {
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
        
        $resultados = [
            'horasPorEmpleado' => [],
            'empleados' => [],
            'clasificacionEmpleados' => [],
            'detalleDias' => []
        ];
        
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
                
                if (!isset($resultados['horasPorEmpleado'][$id_checador])) {
                    $resultados['horasPorEmpleado'][$id_checador] = 0;
                    $resultados['empleados'][$id_checador] = $nombre ?: "Empleado $id_checador";
                    $resultados['clasificacionEmpleados'][$id_checador] = [
                        'turnos_completos' => 0,
                        'turnos_simples' => 0,
                        'registros_incompletos' => 0,
                        'otros_registros' => 0,
                        'dias_sin_registro' => 0
                    ];
                    $resultados['detalleDias'][$id_checador] = [];
                }
                
                // Procesar cada día (Lunes a Viernes)
                $diasProcesados = 0;
                $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                
                foreach ($horasFila as $col => $valorCelda) {
                    if ($diasProcesados >= 5) break;
                    
                    $valorCelda = $valorCelda !== null ? trim($valorCelda) : '';
                    if (!empty($valorCelda) && preg_match('/\d{1,2}:\d{2}/', $valorCelda)) {
                        $resultado = calcularHorasTrabajadas($valorCelda);
                        $resultados['horasPorEmpleado'][$id_checador] += $resultado['horas'];
                        
                        // Clasificar el tipo de registro
                        switch ($resultado['tipo']) {
                            case 'turno_completo':
                                $resultados['clasificacionEmpleados'][$id_checador]['turnos_completos']++;
                                break;
                            case 'turno_simple':
                                $resultados['clasificacionEmpleados'][$id_checador]['turnos_simples']++;
                                break;
                            case 'registro_incompleto':
                                $resultados['clasificacionEmpleados'][$id_checador]['registros_incompletos']++;
                                break;
                            default:
                                $resultados['clasificacionEmpleados'][$id_checador]['otros_registros']++;
                        }
                        
                        // Guardar detalle por día
                        $nombreDia = $dias[$diasProcesados];
                        $resultados['detalleDias'][$id_checador][$nombreDia] = [
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
                    $resultados['clasificacionEmpleados'][$id_checador]['dias_sin_registro']++;
                    $resultados['detalleDias'][$id_checador][$nombreDia] = [
                        'horas' => 0,
                        'tipo' => 'sin_registro',
                        'registros' => '',
                        'entrada' => null,
                        'salida' => null
                    ];
                }
            }
        }
        
        return $resultados;
        
    } catch (ReaderException $e) {
        throw new Exception("Error al leer el archivo de Excel: " . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception("Error procesando archivo Excel: " . $e->getMessage());
    }
}

/**
 * Calcular nómina completa
 */
function calcularNominaCompleta($horasPorEmpleado, $clasificacionEmpleados, $empleados, $pdo, $descuentoRegistrosIncompletos) {
    $nominaCompleta = [];
    $totalesGenerales = [
        'sueldoBase' => 0,
        'actividadesBD' => 0,
        'actividadesSeleccionadas' => 0,
        'descuentos' => 0,
        'totalPagar' => 0
    ];
    
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
    
    return [
        'nominaCompleta' => $nominaCompleta,
        'totalesGenerales' => $totalesGenerales,
        'actividadesExtras' => $actividadesExtras
    ];
}
?>