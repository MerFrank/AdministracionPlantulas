<?php

// Incluye los archivos necesarios
// Estas rutas asumen que el archivo 'generar_nomina.php' está en /modulos/nomina/
// y los archivos de inclusión en /includes/ y /vendor/
require_once __DIR__ . '/../../includes/config.php';

// Incluye el autoloader de Composer con la ruta corregida
// La carpeta 'vendor' se encuentra en la raíz del proyecto 
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

    // Asegurar que la cadena solo tenga dígitos, :, y nada raro
    $cadenaHoras = preg_replace('/[^0-9:]/', '', $cadenaHoras);

    $horas = [];
    for ($i = 0; $i + 4 < strlen($cadenaHoras); $i += 5) {
        $bloque = substr($cadenaHoras, $i, 5);
        if (preg_match('/^\d{2}:\d{2}$/', $bloque)) {
            $horas[] = $bloque;
        }
    }

    if (count($horas) < 2) {
        return 0; // no se puede calcular
    }

    try {
        $entrada = new DateTime($horas[0]);
        $salida  = new DateTime(end($horas));
    } catch (Exception $e) {
        return 0; // por si hay hora mal formada
    }

    $total = $entrada->diff($salida);
    $horasTrabajadas = $total->h + ($total->i / 60);

    // Si hay 4 registros → descontar receso
    if (count($horas) >= 4) {
        try {
            $salidaReceso = new DateTime($horas[1]);
            $entradaReceso = new DateTime($horas[2]);
            $receso = $salidaReceso->diff($entradaReceso);
            $horasReceso = $receso->h + ($receso->i / 60);
            $horasTrabajadas -= $horasReceso;
        } catch (Exception $e) {
            // si falla, ignoramos el receso
        }
    }

    return max($horasTrabajadas, 0); // nunca negativo
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

            $isHeader = true;
            foreach ($data as $row) {
                if ($isHeader) { 
                    $isHeader = false; 
                    continue; 
                }

                $id = $row['A'];
                $nombre = $row['B'];
                $fecha = $row['C'];
                $cadenaHoras = trim($row['D']); // aquí vienen las horas concatenadas

                if (empty($id)) continue;

                if (!isset($horasPorEmpleado[$id])) {
                    $horasPorEmpleado[$id] = 0;
                    $empleados[$id] = $nombre;
                }

                // Calcular horas trabajadas
                $horasPorEmpleado[$id] += calcularHorasTrabajadas($cadenaHoras);
            }

        } elseif ($fileType === 'csv') {
            if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                fgetcsv($handle);
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $id = $data[0];
                    $nombre = $data[1];
                    $fecha = $data[2];
                    $hora = $data[3];
                    $tipoRegistro = strtolower($data[4]);

                    if (empty($id)) continue;

                    if (!isset($asistenciaData[$id])) {
                        $asistenciaData[$id] = [];
                        $empleados[$id] = $nombre;
                    }
                    if (!isset($asistenciaData[$id][$fecha])) {
                        $asistenciaData[$id][$fecha] = ['entradas' => [], 'salidas' => []];
                    }
                    if (strpos($tipoRegistro, 'entrada') !== false) {
                        $asistenciaData[$id][$fecha]['entradas'][] = $hora;
                    } elseif (strpos($tipoRegistro, 'salida') !== false) {
                        $asistenciaData[$id][$fecha]['salidas'][] = $hora;
                    }
                }
                fclose($handle);
            }
        } else {
            $_SESSION['error_message'] = "Error: Solo se permiten archivos de tipo CSV, XLS o XLSX.";
            header('Location: generar_nomina.php');
            exit;
        }

        foreach ($asistenciaData as $id => $dias) {
            $totalHorasEmpleado = 0;
            foreach ($dias as $fecha => $registros) {
                if (!empty($registros['entradas']) && !empty($registros['salidas'])) {
                    $entrada = new DateTime(min($registros['entradas']));
                    $salida = new DateTime(max($registros['salidas']));
                    $intervalo = $entrada->diff($salida);
                    $horas = $intervalo->h + ($intervalo->i / 60);
                    $totalHorasEmpleado += $horas;
                }
            }
            $horasPorEmpleado[$id] = $totalHorasEmpleado;
        }

    } catch (ReaderException $e) {
        $_SESSION['error_message'] = "Error al leer el archivo de Excel: " . $e->getMessage();
        header('Location: generar_nomina.php');
        exit;
    }
}

// Variables para el encabezado
$titulo = "Generar Nómina";
$encabezado = "Generar Nómina";
$subtitulo = "Subir y analizar el archivo de asistencia";
$active_page = "nomina";
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