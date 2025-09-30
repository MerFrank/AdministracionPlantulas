<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// Variables para el procesamiento de datos
$asistenciaData = [];
$horasPorEmpleado = [];
$empleados = [];

function calcularHorasTrabajadas($cadenaHoras) {
    if (empty($cadenaHoras)) {
        return 0;
    }

    // Limpiar la cadena - más permisiva con formatos
    $cadenaHoras = preg_replace('/[^0-9:]/', '', trim($cadenaHoras));
    
    // Si es un solo horario (ej: "16:40" o "7:26"), retornar 0
    if (strlen($cadenaHoras) <= 5) {
        return 0;
    }

    // Extraer todos los bloques de horarios
    $horas = [];
    $i = 0;
    while ($i < strlen($cadenaHoras)) {
        // Buscar próximo bloque HH:MM
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

    // Debug: ver horarios extraídos
    error_log("Horas extraídas: " . implode(', ', $horas) . " de cadena: $cadenaHoras");

    if (count($horas) < 2) {
        return 0; // no hay par entrada-salida
    }

    try {
        // Calcular entre primera entrada y última salida
        $entrada = new DateTime($horas[0]);
        $salida = new DateTime(end($horas));
        
        // Si la salida es anterior a la entrada, sumar 1 día (turno nocturno)
        if ($salida < $entrada) {
            $salida->modify('+1 day');
        }
        
        $total = $entrada->diff($salida);
        $horasTrabajadas = $total->h + ($total->i / 60);

        // Si hay 4 registros, restar receso (segundo y tercer horario)
        if (count($horas) >= 4) {
            try {
                $salidaReceso = new DateTime($horas[1]);
                $entradaReceso = new DateTime($horas[2]);
                
                if ($entradaReceso < $salidaReceso) {
                    $entradaReceso->modify('+1 day');
                }
                
                $receso = $salidaReceso->diff($entradaReceso);
                $horasReceso = $receso->h + ($receso->i / 60);
                $horasTrabajadas -= $horasReceso;
                
                error_log("Receso: {$horas[1]} a {$horas[2]} = $horasReceso h");
            } catch (Exception $e) {
                error_log("Error calculando receso: " . $e->getMessage());
            }
        }

        error_log("Horas trabajadas: {$horas[0]} a " . end($horas) . " = $horasTrabajadas h");
        return max($horasTrabajadas, 0);
        
    } catch (Exception $e) {
        error_log("Error procesando horarios: " . $e->getMessage());
        return 0;
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

            // Buscar todas las filas que contienen "ID:"
            foreach ($data as $numeroFila => $row) {
                if (isset($row['A']) && strpos(trim($row['A']), 'ID:') !== false) {
                    // Esta fila contiene los datos del empleado
                    $id = isset($row['C']) ? trim($row['C']) : '';
                    $nombre = isset($row['K']) ? trim($row['K']) : '';
                    
                    if (empty($id)) continue;

                    // La siguiente fila contiene las horas
                    $filaHoras = $numeroFila + 1;
                    if (isset($data[$filaHoras])) {
                        $horasFila = $data[$filaHoras];
                        
                        if (!isset($horasPorEmpleado[$id])) {
                            $horasPorEmpleado[$id] = 0;
                            $empleados[$id] = $nombre;
                        }

                        error_log("Procesando empleado $id: $nombre");
                        
                        // Procesar horas de cada día (columnas A a E)
                        $dias = ['A', 'B', 'C', 'D', 'E'];
                        foreach ($dias as $col) {
                            if (isset($horasFila[$col]) && !empty(trim($horasFila[$col]))) {
                                $cadenaHoras = trim($horasFila[$col]);
                                $horasDia = calcularHorasTrabajadas($cadenaHoras);
                                $horasPorEmpleado[$id] += $horasDia;
                                
                                error_log("Día $col: '$cadenaHoras' = $horasDia horas");
                            }
                        }
                        
                        error_log("Total acumulado {$empleados[$id]}: {$horasPorEmpleado[$id]} horas");
                    }
                }
            }

        } elseif ($fileType === 'csv') {
            // [Mantener código CSV original...]
        } else {
            $_SESSION['error_message'] = "Error: Solo se permiten archivos de tipo CSV, XLS o XLSX.";
            header('Location: generar_nomina.php');
            exit;
        }

    } catch (ReaderException $e) {
        $_SESSION['error_message'] = "Error al leer el archivo de Excel: " . $e->getMessage();
        header('Location: generar_nomina.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error general: " . $e->getMessage();
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

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<p style="color:red;">' . $_SESSION['error_message'] . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form action="generar_nomina.php" method="post" enctype="multipart/form-data">
            <label for="asistencia_file">Selecciona el archivo de asistencia (CSV, XLS o XLSX):</label>
            <input type="file" name="asistencia_file" id="asistencia_file" required>
            <button type="submit">Analizar y Generar Nómina</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado)):
        ?>
        <hr>
        <h2>Resumen de la Nómina</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Total de Horas Trabajadas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horasPorEmpleado as $id => $horas): ?>
                <tr>
                    <td><?php echo htmlspecialchars($id); ?></td>
                    <td><?php echo htmlspecialchars($empleados[$id]); ?></td>
                    <td><?php echo number_format($horas, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>