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

            foreach ($data as $numeroFila => $row) {
                $columnaA = isset($row['A']) ? trim($row['A']) : '';
                $columnaC = isset($row['C']) ? trim($row['C']) : '';

                // Buscar "ID" incluso si tiene espacios, dos puntos, etc.
                if (stripos($columnaA, 'ID') !== false && !empty($columnaC)) {
                    $id_checador = $columnaC;
                    error_log("ENCONTRADO - Fila $numeroFila: A='$columnaA', ID='$id_checador'");

                    $infoEmpleado = obtenerInformacionEmpleado($id_checador, $pdo);

                    if ($infoEmpleado) {
                        $infoEmpleado['id_checador'] = $id_checador;
                        $infoEmpleado['fila_excel'] = $numeroFila;
                        $registrosEmpleados[] = $infoEmpleado;
                        $idsEncontrados++;
                        error_log("✓ Empleado encontrado en BD: " . $infoEmpleado['nombre_completo']);
                    } else {
                        error_log("✗ ID $id_checador NO encontrado en BD");
                        $idsNoEnBD++;
                    }
                }
            }

            // DEPURACIÓN: Resumen
            error_log("=== RESUMEN ===");
            error_log("IDs encontrados en Excel: " . ($idsEncontrados + $idsNoEnBD));
            error_log("IDs encontrados en BD: $idsEncontrados");
            error_log("IDs NO en BD: $idsNoEnBD");

            if ($idsEncontrados > 0) {
                $_SESSION['success_message'] = "Archivo procesado. $idsEncontrados empleados encontrados.";
                if ($idsNoEnBD > 0) {
                    $_SESSION['warning_message'] = "$idsNoEnBD IDs no se encontraron en la base de datos.";
                }
            } else {
                if (($idsEncontrados + $idsNoEnBD) > 0) {
                    $_SESSION['error_message'] = "Se encontraron " . ($idsEncontrados + $idsNoEnBD) . " IDs en el archivo, pero NINGUNO está en la base de datos.";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar']) && isset($_FILES['asistencia_file'])) {
    procesarArchivo();
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
        <h1><?php echo $encabezado; ?></h1>
        <p class="lead"><?php echo $subtitulo; ?></p>

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
        <!-- FORMULARIO PARA SUBIR ARCHIVO -->
        <div class="form-container-nomina">
            <h3 class="section-title-nomina">Subir Archivo de Asistencia</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group-nomina">
                    <label for="asistencia_file">Seleccionar archivo Excel (.xls o .xlsx):</label>
                    <input type="file" name="asistencia_file" id="asistencia_file" accept=".xls,.xlsx" required
                        class="form-control">
                    <small class="form-text text-muted">
                        El archivo debe tener la etiqueta "ID" en la columna A y el número de ID en la columna C
                    </small>
                </div>

                <button type="submit" name="procesar" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i> Procesar Archivo
                </button>

                <?php if (!empty($registrosEmpleados)): ?>
                    <button type="button" class="btn btn-success ms-2" onclick="exportarDatos()">
                        <i class="fas fa-download me-2"></i> Exportar Datos
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- TABLA PARA MOSTRAR RESULTADOS -->
        <?php if (!empty($registrosEmpleados)): ?>
            <div class="employee-detail-section mt-4">
                <h3 class="section-title-nomina">
                    Empleados Encontrados
                    <span class="badge bg-primary"><?php echo count($registrosEmpleados); ?></span>
                </h3>

                <div class="table-responsive-nomina">
                    <table class="table-nomina">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID Checador</th>
                                <th>Nombre Completo</th>
                                <th>Puesto</th>
                                <th>Nivel Jerárquico</th>
                                <th>Sueldo Diario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrosEmpleados as $index => $empleado): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge bg-dark">
                                            <?php echo htmlspecialchars($empleado['id_checador'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($empleado['nombre_completo'] ?? 'No encontrado'); ?></td>
                                    <td><?php echo htmlspecialchars($empleado['puesto'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($empleado['nivel_jerarquico'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        $<?php echo number_format($empleado['sueldo_diario'] ?? 0, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- FILA DE TOTALES -->
                            <tr class="total-row">
                                <td colspan="5" class="text-end fw-bold">TOTAL EMPLEADOS:</td>
                                <td class="fw-bold text-primary">
                                    <?php echo count($registrosEmpleados); ?> empleados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- RESUMEN -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Resumen</h5>
                            <p class="mb-1">• Empleados procesados: <?php echo count($registrosEmpleados); ?></p>
                            <p class="mb-1">• Pago total actividades: $<?php 
                                $totalPago = 0;
                                foreach ($registrosEmpleados as $emp) {
                                    $totalPago += $emp['pago_actividades'] ?? 0;
                                }
                                echo number_format($totalPago, 2); 
                            ?></p>
                            <p class="mb-0">• Archivo: <?php 
                                echo htmlspecialchars(
                                    isset($_FILES['asistencia_file']['name']) ? 
                                    $_FILES['asistencia_file']['name'] : 
                                    'No procesado'
                                ); 
                            ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Procesado exitosamente</h5>
                            <p class="mb-0">
                                <strong><?php echo count($registrosEmpleados); ?></strong> empleados listos para nómina.
                                <?php if (isset($_SESSION['warning_message'])): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php 
                                            echo $_SESSION['warning_message'];
                                            unset($_SESSION['warning_message']);
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
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
    document.getElementById('asistencia_file').addEventListener('change', function (e) {
        const fileName = e.target.files[0]?.name || 'No seleccionado';
        const label = this.previousElementSibling;
        label.innerHTML = `Archivo seleccionado: <strong>${fileName}</strong>`;
    });

    function exportarDatos() {
        // Función para exportar datos a Excel/CSV
        alert('Función de exportación - Implementar según necesidad');
        // window.location.href = 'exportar.php?tipo=excel';
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>