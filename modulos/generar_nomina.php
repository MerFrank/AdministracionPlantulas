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
$empleadosExcluir = ['16', '17', '22'];

// Configuración
$toleranciaMinutos = 10;
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


// Lógica de procesamiento de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
                // Buscar filas que contengan "ID:" en columna A
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
                    
                    // Pago por actividades
                    $pagoActividades = floatval($infoEmpleado['pago_actividades'] ?? 0);
                    
                    // Total (incluyendo nuevo descuento)
                    $totalPagar = $sueldoBase + $pagoActividades - $descuentoRegistros;

                    $nominaCompleta[$id_checador] = [
                    'nombre_completo' => $infoEmpleado['nombre_completo'],
                    'puesto' => $infoEmpleado['puesto'] ?? 'No asignado',
                    'sueldo_diario' => $sueldoDiario,
                    'dias_trabajados' => $diasTrabajados,
                    'sueldo_base' => $sueldoBase,
                    'pago_actividades' => $pagoActividades,
                    'descuentos_horarios' => 0, // Siempre será 0
                    'descuento_registros' => $descuentoRegistros,
                    'total_pagar' => $totalPagar,
                    'horario_esperado' => ($horaEntradaEsperada ?? '--:--') . ' - ' . ($horaSalidaEsperada ?? '--:--'),
                    'dias_sin_4_registros' => $diasSin4Registros
                ];
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

<main>
    <div class="container">
        <h1>Generar Nómina</h1>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<p style="color:red;">' . $_SESSION['error_message'] . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form action="generar_nomina.php" method="post" enctype="multipart/form-data">
            <label for="asistencia_file">Selecciona el archivo de asistencia (XLS o XLSX):</label>
            <input type="file" name="asistencia_file" id="asistencia_file" accept=".xls,.xlsx" required>
            <button type="submit">Analizar y Generar Nómina</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado)): ?>
        
        <!-- Tabla de Nómina Completa -->
        <hr>
        <h2>Nómina Completa</h2>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Puesto</th>
                    <th>Sueldo Diario</th>
                    <th>Días Trab.</th>
                    <th>Sueldo Base</th>
                    <th>Actividades</th>
                    <th>Descuento Registros*</th>
                    <th>Total a Pagar</th>
                    <th>Días sin 4 reg.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nominaCompleta as $id_checador => $nomina): 
                    if (isset($nomina['error'])): ?>
                    <tr style="background-color: #ffe6e6;">
                        <td><?= $id_checador ?></td>
                        <td colspan="10" style="color: red;">
                            <?= $nomina['nombre'] ?> - <?= $nomina['error'] ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><?= $id_checador ?></td>
                        <td><?= htmlspecialchars($nomina['nombre_completo']) ?></td>
                        <td><?= htmlspecialchars($nomina['puesto']) ?></td>
                        <td>$<?= number_format($nomina['sueldo_diario'], 2) ?></td>
                        <td><?= $nomina['dias_trabajados'] ?></td>
                        <td>$<?= number_format($nomina['sueldo_base'], 2) ?></td>
                        <td>$<?= number_format($nomina['pago_actividades'], 2) ?></td>
                        <td style="color: red;">-$<?= number_format($nomina['descuento_registros'], 2) ?></td>
                        <td style="background-color: #e6ffe6; font-weight: bold;">
                            $<?= number_format($nomina['total_pagar'], 2) ?>
                        </td>
                        <td style="color: orange;"><?= $nomina['dias_sin_4_registros'] ?> días</td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><small>* Descuento de $25 por cada día sin 4 registros completos</small></p>

        <!-- Tabla de Asistencia Resumen -->
        <hr>
        <h2>Resumen de Asistencia</h2>
        <table border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f2f2f2;">
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

        <!-- Reporte Detallado de Asistencia por Persona -->
        <hr>
        <h2>Reporte Detallado de Asistencia por Empleado</h2>
        <?php foreach ($detalleDias as $id => $dias): 
            if (in_array($id, $empleadosExcluir)) continue;
        ?>
        <div style="margin-bottom: 30px; border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #333;">
                <?= htmlspecialchars($empleados[$id]) ?> (ID: <?= $id ?>)
                <?php if (isset($nominaCompleta[$id]['puesto'])): ?>
                - <?= htmlspecialchars($nominaCompleta[$id]['puesto']) ?>
                <?php endif; ?>
            </h3>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
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
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>