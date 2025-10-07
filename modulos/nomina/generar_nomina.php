<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// Variables para el procesamiento de datos
$asistenciaData = [];
$horasPorEmpleado = [];
$empleados = [];
$clasificacionEmpleados = []; // Nueva: para clasificar tipos de registro
$detalleDias = []; // Nueva: para detalle por día

// Lista de empleados a excluir del reporte (por ID o nombre)
$empleadosExcluir = [
    '16', // Alicia.Marin
    '17' // Silvestre.Perez  
];

function clasificarRegistros($cadenaHoras) {
    if (empty($cadenaHoras)) {
        return 'sin_registros';
    }

    // Limpiar la cadena
    $cadenaHoras = preg_replace('/[^0-9:]/', '', trim($cadenaHoras));
    
    if (strlen($cadenaHoras) <= 5) {
        return 'registro_incompleto'; // Solo una hora
    }

    // Extraer todos los bloques de horarios
    $horas = [];
    $i = 0;
    while ($i < strlen($cadenaHoras)) {
        if (preg_match('/(\d{1,2}:\d{2})/', $cadenaHoras, $match, 0, $i)) {
            $horaStr = $match[1];
            if (strlen($horaStr) == 4) {
                $horaStr = '0' . $horaStr;
            }
            $horas[] = $horaStr;
            $i += strlen($match[1]);
        } else {
            $i++;
        }
    }

    // Clasificar según cantidad de registros
    switch (count($horas)) {
        case 2:
            return 'turno_simple';
        case 4:
            return 'turno_completo';
        case 3:
        case 5:
        case 6:
            return 'registros_extra';
        default:
            return 'formato_desconocido';
    }
}

function calcularHorasTrabajadas($cadenaHoras) {
    if (empty($cadenaHoras)) {
        return ['horas' => 0, 'tipo' => 'sin_registros'];
    }

    $cadenaHoras = preg_replace('/[^0-9:]/', '', trim($cadenaHoras));
    
    if (strlen($cadenaHoras) <= 5) {
        return ['horas' => 0, 'tipo' => 'registro_incompleto'];
    }

    // Extraer horarios (mismo código anterior)
    $horas = [];
    $i = 0;
    while ($i < strlen($cadenaHoras)) {
        if (preg_match('/(\d{1,2}:\d{2})/', $cadenaHoras, $match, 0, $i)) {
            $horaStr = $match[1];
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
        return ['horas' => 0, 'tipo' => 'registro_incompleto'];
    }

    try {
        $entrada = new DateTime($horas[0]);
        $salida = new DateTime(end($horas));
        
        if ($salida < $entrada) {
            $salida->modify('+1 day');
        }
        
        $total = $entrada->diff($salida);
        $horasTrabajadas = $total->h + ($total->i / 60);

        // Descontar receso solo si hay 4 registros exactos
        if (count($horas) == 4) {
            try {
                $salidaReceso = new DateTime($horas[1]);
                $entradaReceso = new DateTime($horas[2]);
                
                if ($entradaReceso < $salidaReceso) {
                    $entradaReceso->modify('+1 day');
                }
                
                $receso = $salidaReceso->diff($entradaReceso);
                $horasReceso = $receso->h + ($receso->i / 60);
                $horasTrabajadas -= $horasReceso;
                
            } catch (Exception $e) {
                // Error en cálculo de receso
            }
        }

        // Determinar tipo basado en cantidad de registros
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

        return ['horas' => max($horasTrabajadas, 0), 'tipo' => $tipo];
        
    } catch (Exception $e) {
        return ['horas' => 0, 'tipo' => 'error_calculo'];
    }
}

// Lógica de procesamiento de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['asistencia_file']) || $_FILES['asistencia_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Error al subir el archivo. Por favor, inténtalo de nuevo.";
        header('Location: generar_nomina.php');
        exit;
    }

    $fileTmpPath = $_FILES['asistencia_file']['tmp_name'];
    $fileName = $_FILES['asistencia_file']['name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    try {
        if ($fileType === 'xls' || $fileType === 'xlsx') {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            foreach ($data as $numeroFila => $row) {
                if (isset($row['A']) && strpos(trim($row['A']), 'ID:') !== false) {
                    $id = isset($row['C']) ? trim($row['C']) : '';
                    $nombre = isset($row['K']) ? trim($row['K']) : '';
                    
                    if (empty($id)) continue;

                    // Verificar si el empleado debe ser excluido
                    if (in_array($id, $empleadosExcluir) || in_array($nombre, $empleadosExcluir)) {
                        continue; // Saltar este empleado
                    }

                    $filaHoras = $numeroFila + 1;
                    if (isset($data[$filaHoras])) {
                        $horasFila = $data[$filaHoras];
                        
                        if (!isset($horasPorEmpleado[$id])) {
                            $horasPorEmpleado[$id] = 0;
                            $empleados[$id] = $nombre;
                            $clasificacionEmpleados[$id] = [
                                'turnos_completos' => 0,
                                'turnos_simples' => 0,
                                'registros_incompletos' => 0,
                                'otros_registros' => 0,
                                'dias_sin_registro' => 0
                            ];
                            $detalleDias[$id] = [];
                        }

                        // Procesar cada día
                        $dias = ['A' => 'Lunes', 'B' => 'Martes', 'C' => 'Miércoles', 'D' => 'Jueves', 'E' => 'Viernes'];
                        foreach ($dias as $col => $nombreDia) {
                            if (isset($horasFila[$col]) && !empty(trim($horasFila[$col]))) {
                                $cadenaHoras = trim($horasFila[$col]);
                                $resultado = calcularHorasTrabajadas($cadenaHoras);
                                
                                $horasPorEmpleado[$id] += $resultado['horas'];
                                
                                // Clasificar el tipo de registro
                                switch ($resultado['tipo']) {
                                    case 'turno_completo':
                                        $clasificacionEmpleados[$id]['turnos_completos']++;
                                        break;
                                    case 'turno_simple':
                                        $clasificacionEmpleados[$id]['turnos_simples']++;
                                        break;
                                    case 'registro_incompleto':
                                        $clasificacionEmpleados[$id]['registros_incompletos']++;
                                        break;
                                    default:
                                        $clasificacionEmpleados[$id]['otros_registros']++;
                                }
                                
                                // Guardar detalle por día
                                $detalleDias[$id][$nombreDia] = [
                                    'horas' => $resultado['horas'],
                                    'tipo' => $resultado['tipo'],
                                    'registros' => $cadenaHoras
                                ];
                            } else {
                                $clasificacionEmpleados[$id]['dias_sin_registro']++;
                                $detalleDias[$id][$nombreDia] = [
                                    'horas' => 0,
                                    'tipo' => 'sin_registro',
                                    'registros' => ''
                                ];
                            }
                        }
                    }
                }
            }

        } elseif ($fileType === 'csv') {
            // [Mantener código CSV original...]
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: generar_nomina.php');
        exit;
    }
}


// Variables para el encabezado
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

        <form action="generar_nomina.php" method="post" enctype="multipart/form-data">
            <label for="asistencia_file">Selecciona el archivo de asistencia (CSV, XLS o XLSX):</label>
            <input type="file" name="asistencia_file" id="asistencia_file" required>
            <button type="submit">Analizar y Generar Nómina</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado)): ?>
        
        <hr>
        <h2>Resumen de la Nómina</h2>
        
        <!-- Tabla Principal -->
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
                    $clasif = $clasificacionEmpleados[$id];
                    $totalDias = 5; // Lunes a Viernes
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

        <!-- Detalle por Empleado -->
        <hr>
        <h2>Detalle por Empleado</h2>
        <?php foreach ($detalleDias as $id => $dias): ?>
        <div style="margin-bottom: 20px; border: 1px solid #ccc; padding: 10px;">
            <h3><?= htmlspecialchars($empleados[$id]) ?> (ID: <?= $id ?>)</h3>
            <table border="1" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th>Día</th>
                        <th>Registros</th>
                        <th>Horas</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dias as $dia => $info): 
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
                        <td><?= number_format($info['horas'], 2) ?></td>
                        <td style="color: <?= $colorTipo ?>;">
                            <strong>
                                <?= match($info['tipo']) {
                                    'turno_completo' => '✅ Completo (4 reg)',
                                    'turno_simple' => '🔵 Simple (2 reg)',
                                    'registro_incompleto' => '⚠️ Incompleto',
                                    'sin_registro' => '❌ Sin registro',
                                    default => $info['tipo']
                                } ?>
                            </strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>