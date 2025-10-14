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
$empleadosExcluir = ['16', '17'];

// Configuración
$jornadaCompletaHoras = 8;
$descuentoRegistrosIncompletos = 25; // $25 por día sin 4 registros

function calcularHorasTrabajadas($cadenaHoras) {
    if (empty($cadenaHoras) || trim($cadenaHoras) === '') {
        return ['horas' => 0, 'entrada' => null, 'salida' => null, 'tipo' => 'sin_registros'];
    }

    try {
        $cadenaHoras = preg_replace('/[^0-9:]/', '', trim($cadenaHoras));
        
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
        // Consulta corregida - sin referencia a tabla 'actividades'
        $stmt = $pdo->prepare("
            SELECT 
                e.id_empleado,
                CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, '')) as nombre_completo,
                ep.sueldo_diario,
                ep.hora_entrada,
                ep.hora_salida,
                ep.dias_laborales,
                p.nombre as puesto,
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
        error_log("Error al actividades extras: " . $e->getMessage());
        return false;
    }
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

// Lógica de procesamiento de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            foreach ($data as $numeroFila => $row) {
                // CORREGIDO: strpos necesita dos parámetros
                if (isset($row['A']) && strpos(trim($row['A']), 'ID:') !== false) {
                    // **COLUMNAS FIJAS: ID en C, Nombre en K**
                    $id_checador = isset($row['C']) ? trim($row['C']) : '';
                    $nombre = isset($row['K']) ? trim($row['K']) : '';
                    
                    if (empty($id_checador) || !is_numeric($id_checador)) {
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
                            $empleados[$id_checador] = $nombre;
                            $clasificacionEmpleados[$id_checador] = [
                                'turnos_completos' => 0,
                                'turnos_simples' => 0,
                                'registros_incompletos' => 0,
                                'otros_registros' => 0,
                                'dias_sin_registro' => 0
                            ];
                            $detalleDias[$id_checador] = [];
                        }

                        // Procesar cada día (Lunes a Viernes) - columnas A a E
                        $dias = ['A' => 'Lunes', 'B' => 'Martes', 'C' => 'Miércoles', 'D' => 'Jueves', 'E' => 'Viernes'];
                        foreach ($dias as $col => $nombreDia) {
                            $valorCelda = isset($horasFila[$col]) ? trim($horasFila[$col]) : '';
                            
                            if (!empty($valorCelda)) {
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
                                $detalleDias[$id_checador][$nombreDia] = [
                                    'horas' => $resultado['horas'],
                                    'tipo' => $resultado['tipo'],
                                    'registros' => $valorCelda,
                                    'entrada' => $resultado['entrada'],
                                    'salida' => $resultado['salida']
                                ];
                            } else {
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
                }
            }

            // CALCULAR NÓMINA COMPLETA
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

                    // Cálculos de nómina
                    $sueldoDiario = floatval($infoEmpleado['sueldo_diario'] ?? 0);
                    $valorHora = $sueldoDiario > 0 ? $sueldoDiario / $jornadaCompletaHoras : 0;
                    $valorMinuto = $valorHora > 0 ? $valorHora / 60 : 0;
                    
                    $diasTrabajados = 5 - ($clasificacionEmpleados[$id_checador]['dias_sin_registro'] ?? 5);
                    $sueldoBase = $sueldoDiario * $diasTrabajados;
                    
                    // Calcular descuentos por horarios
                    $descuentosHorarios = 0;

                    // Calcular descuento por registros incompletos
                    $diasSin4Registros = $clasificacionEmpleados[$id_checador]['turnos_simples'] + 
                                        $clasificacionEmpleados[$id_checador]['registros_incompletos'] + 
                                        $clasificacionEmpleados[$id_checador]['otros_registros'];
                    $descuentoRegistros = $diasSin4Registros * $descuentoRegistrosIncompletos;
                    
                    // Pago por actividades (de BD)
                    $pagoActividades = floatval($infoEmpleado['pago_actividades'] ?? 0);
                    
                    // Pago por actividades seleccionadas manualmente
                    $pagoActividadesSeleccionadas = $_SESSION['actividades_empleado'][$id_checador]['total'] ?? 0;
                    
                    // Total 
                    $totalPagar = $sueldoBase + $pagoActividadesSeleccionadas - $descuentoRegistros;

                    $nominaCompleta[$id_checador] = [
                        'nombre_completo' => $infoEmpleado['nombre_completo'],
                        'puesto' => $infoEmpleado['puesto'] ?? 'No asignado',
                        'sueldo_diario' => $sueldoDiario,
                        'dias_trabajados' => $diasTrabajados,
                        'sueldo_base' => $sueldoBase,
                        'pago_actividades_seleccionadas' => $pagoActividadesSeleccionadas,
                        'descuentos_horarios' => 0,
                        'descuento_registros' => $descuentoRegistros,
                        'total_pagar' => $totalPagar,
                        'horario_esperado' => ($infoEmpleado['hora_entrada'] ?? '--:--') . ' - ' . ($infoEmpleado['hora_salida'] ?? '--:--'),
                        'dias_sin_4_registros' => $diasSin4Registros
                    ];

                    // Acumular totales generales
                    $totalGeneralSueldoBase += $sueldoBase;
                    $totalGeneralActividadesSeleccionadas += $pagoActividadesSeleccionadas;
                    $totalGeneralDescuentos += $descuentoRegistros;
                    $totalGeneralPagar += $totalPagar;
                }
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
?>

<!-- HTML -->
<?php
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
        ?>

        <div class="form-container-nomina">
            <form class="form-nomina" action="generar_nomina.php" method="post" enctype="multipart/form-data">
                <div class="form-group-nomina">
                    <label for="asistencia_file">Selecciona el archivo de asistencia (XLS o XLSX):</label>
                    <input type="file" name="asistencia_file" id="asistencia_file" accept=".xls,.xlsx" required>
                </div>
                <button type="submit" class="btn-submit-nomina">Analizar y Generar Nómina</button>
            </form>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado)): ?>
        
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
                            <th>Descuento Registros*</th>
                            <th>Días sin 4 reg.</th>
                            <th>Total a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalGeneralSueldoBase = 0;
                        $totalGeneralActividadesSeleccionadas = 0;
                        $totalGeneralDescuentos = 0;
                        $totalGeneralPagar = 0;
                        
                        foreach ($nominaCompleta as $id_checador => $nomina): 
                            if (isset($nomina['error'])): 
                        ?>
                            <tr style="background-color: #ffe6e6;">
                                <td><?= $id_checador ?></td>
                                <td colspan="9" style="color: red;">
                                    <?= $nomina['nombre'] ?> - <?= $nomina['error'] ?>
                                </td>
                            </tr>
                            <?php else: 
                                // Obtener los IDs de actividades seleccionadas correctamente
                                $actividadesSeleccionadasIds = $_SESSION['actividades_empleado'][$id_checador]['actividades_ids'] ?? [];
                                
                                // Calcular totales para este empleado
                                $sueldoBase = $nomina['sueldo_base'];
                                $descuentoRegistros = $nomina['descuento_registros'];
                                $pagoActividadesSeleccionadas = $nomina['pago_actividades_seleccionadas'];
                                $totalPagar = $sueldoBase + $pagoActividadesSeleccionadas - $descuentoRegistros;
                                
                                // Acumular totales generales
                                $totalGeneralSueldoBase += $sueldoBase;
                                $totalGeneralActividadesSeleccionadas += $pagoActividadesSeleccionadas;
                                $totalGeneralDescuentos += $descuentoRegistros;
                                $totalGeneralPagar += $totalPagar;
                            ?>
                            <tr id="fila-<?= $id_checador ?>" 
                                data-sueldo-base="<?= $sueldoBase ?>" 
                                data-descuento-registros="<?= $descuentoRegistros ?>">
                                <td><?= $id_checador ?></td>
                                <td><?= htmlspecialchars($nomina['nombre_completo']) ?></td>
                                <td><?= htmlspecialchars($nomina['puesto']) ?></td>
                                <td>$<?= number_format($nomina['sueldo_diario'], 2) ?></td>
                                <td><?= $nomina['dias_trabajados'] ?></td>
                                <td>$<?= number_format($sueldoBase, 2) ?></td>
                                <td>
                                    <div class="actividades-container">
                                        <?php foreach ($actividadesExtras as $actividad): 
                                            // Verificar si la actividad está seleccionada
                                            $checked = in_array($actividad['id_actividad'], $actividadesSeleccionadasIds) ? 'checked' : '';
                                        ?>
                                            <div class="actividades-item">
                                                <input type="checkbox" 
                                                       class="actividad-checkbox"
                                                       data-empleado="<?= $id_checador ?>"
                                                       data-valor="<?= $actividad['pago_extra'] ?>"
                                                       id="act_<?= $id_checador ?>_<?= $actividad['id_actividad'] ?>"
                                                       <?= $checked ?>>
                                                <label for="act_<?= $id_checador ?>_<?= $actividad['id_actividad'] ?>" style="font-size: 12px;">
                                                    <?= htmlspecialchars($actividad['nombre']) ?> - $<?= number_format($actividad['pago_extra'], 2) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; font-weight: bold; text-align: center;">
                                        Total actividades: $<span id="total-actividades-<?= $id_checador ?>"><?= number_format($pagoActividadesSeleccionadas, 2) ?></span>
                                    </div>
                                </td>
                                <td class="negative-amount">-$<?= number_format($descuentoRegistros, 2) ?></td>
                                <td style="color: orange; font-weight: bold;"><?= $nomina['dias_sin_4_registros'] ?> días</td>
                                <td style="background-color: #e6ffe6; font-weight: bold;" class="positive-amount">
                                    $<span id="total-pagar-<?= $id_checador ?>"><?= number_format($totalPagar, 2) ?></span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Fila de totales generales -->
                        <tr class="total-row">
                            <td colspan="5" style="text-align: right; font-weight: bold;">TOTALES GENERALES:</td>
                            <td style="font-weight: bold;">$<span id="total-sueldo-base"><?= number_format($totalGeneralSueldoBase, 2) ?></span></td>
                            <td style="font-weight: bold;">$<span id="total-actividades"><?= number_format($totalGeneralActividadesSeleccionadas, 2) ?></span></td>
                            <td style="font-weight: bold;">-$<span id="total-descuentos"><?= number_format($totalGeneralDescuentos, 2) ?></span></td>
                            <td></td>
                            <td style="font-weight: bold; background-color: #d4edda;">$<span id="total-general"><?= number_format($totalGeneralPagar, 2) ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p><small>* Descuento de $25 por cada día sin 4 registros completos</small></p>
        </div>

        <!-- JavaScript para calcular totales en tiempo real -->
        <script>
        // JavaScript para calcular totales en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar totales desde PHP
            window.totalesGenerales = {
                sueldoBase: <?= $totalGeneralSueldoBase ?? 0 ?>,
                actividades: <?= $totalGeneralActividadesSeleccionadas ?? 0 ?>,
                descuentos: <?= $totalGeneralDescuentos ?? 0 ?>,
                general: <?= $totalGeneralPagar ?? 0 ?>
            };
            
            // Agregar event listeners a todos los checkboxes
            document.querySelectorAll('.actividad-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    calcularTotal(this);
                });
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
            const descuentoRegistros = parseFloat(fila.dataset.descuentoRegistros) || 0;
            
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
            const nuevoTotalPagar = sueldoBase + totalActividadesActual - descuentoRegistros;
            
            // Actualizar displays
            totalActividadesElement.textContent = totalActividadesActual.toFixed(2);
            totalPagarElement.textContent = nuevoTotalPagar.toFixed(2);
            
            // Actualizar totales generales
            actualizarTotalesGenerales();
        }

        function actualizarTotalesGenerales() {
            let totalSueldoBase = 0;
            let totalActividades = 0;
            let totalDescuentos = 0;
            let totalGeneral = 0;
            
            // Recalcular todos los totales desde cero
            document.querySelectorAll('[id^="fila-"]').forEach(fila => {
                const sueldoBase = parseFloat(fila.dataset.sueldoBase) || 0;
                const descuentoRegistros = parseFloat(fila.dataset.descuentoRegistros) || 0;
                const empleadoId = fila.id.replace('fila-', '');
                const totalActividadesElement = document.getElementById('total-actividades-' + empleadoId);
                const totalActividadesEmpleado = parseFloat(totalActividadesElement.textContent) || 0;
                
                totalSueldoBase += sueldoBase;
                totalActividades += totalActividadesEmpleado;
                totalDescuentos += descuentoRegistros;
                totalGeneral += (sueldoBase + totalActividadesEmpleado - descuentoRegistros);
            });
            
            // Actualizar displays de totales generales
            document.getElementById('total-sueldo-base').textContent = totalSueldoBase.toFixed(2);
            document.getElementById('total-actividades').textContent = totalActividades.toFixed(2);
            document.getElementById('total-descuentos').textContent = totalDescuentos.toFixed(2);
            document.getElementById('total-general').textContent = totalGeneral.toFixed(2);
            
            // Actualizar el objeto global
            window.totalesGenerales = {
                sueldoBase: totalSueldoBase,
                actividades: totalActividades,
                descuentos: totalDescuentos,
                general: totalGeneral
            };
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