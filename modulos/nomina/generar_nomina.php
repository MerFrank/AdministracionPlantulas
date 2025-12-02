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

// Incluir funciones y vistas
require_once __DIR__ . '/funciones_nomina.php';
require_once __DIR__ . '/procesar_nomina.php';

// Determinar qué vista mostrar
$mostrarNomina = false;
$mostrarAsistencia = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($horasPorEmpleado) && !empty($horasPorEmpleado)) {
    $mostrarNomina = true;
    $mostrarAsistencia = true;
}

// Incluir header específico para nómina
require_once __DIR__ . '/includes/header_nomina.php';

// Mostrar formulario de carga
?>
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

        <?php if ($mostrarNomina): ?>
            <?php require_once __DIR__ . '/vista_nomina.php'; ?>
        <?php endif; ?>

        <?php if ($mostrarAsistencia): ?>
            <?php require_once __DIR__ . '/vista_asistencia.php'; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>