<?php
require_once __DIR__ . '/../../includes/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Lista de Empleados - Plantulas';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$busqueda = $_GET['busqueda'] ?? '';

if (!empty($busqueda)) {
    $busquedaLike = "%$busqueda%";
    $sql = "SELECT * FROM empleados 
            WHERE (nombre LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ? 
                  OR telefono LIKE ? OR email LIKE ? OR curp LIKE ? OR rfc LIKE ? OR nss LIKE ?)
            ORDER BY activo DESC, apellido_paterno, apellido_materno, nombre ASC";
    
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute(array_fill(0, 8, $busquedaLike));
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener empleados: " . $e->getMessage());
    }
} else {
    $sql = "SELECT * FROM empleados 
            ORDER BY activo DESC, apellido_paterno, apellido_materno, nombre ASC";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener empleados: " . $e->getMessage());
    }
}

if (isset($_GET['ajax'])) {
    include __DIR__ . '/tabla_empleados.php';
    exit;
}

$encabezado = "Lista de Empleados";
$subtitulo = "Muestra empleados registrados en el sistema (activos primero)";
$ruta = "dashboard_empleados.php";
$texto_boton = "";
require('../../includes/header.php');
?>

<main class="container mt-4 mb-5">
    <div class="card card-lista">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Empleados</h2>
                <div>
                    <a href="registro_empleado.php" class="btn btn-success btn-sm ms-2">
                        <i class="bi bi-plus-circle"></i> Nuevo
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <div class="input-group">
                <span class="input-group-text" style="background-color: var(--color-primary); color: white;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" id="busqueda" 
                       placeholder="Buscar empleados por nombre, teléfono, email, CURP, RFC o NSS..."
                       value="<?= htmlspecialchars($busqueda) ?>">
                <button class="btn btn-outline-secondary" type="button" id="limpiar-busqueda">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="table-responsive" id="tabla-empleados-container">
                <?php include __DIR__ . '/tabla_empleados.php'; ?>
            </div>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('[title]').tooltip();
    
    let searchTimeout;
    $('#busqueda').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            const searchTerm = $('#busqueda').val();
            $.get('lista_empleados.php', { 
                busqueda: searchTerm, 
                ajax: true 
            }, function(data) {
                $('#tabla-empleados-container').html(data);
                $('[title]').tooltip();
            });
        }, 300);
    });
    
    $('#limpiar-busqueda').click(function() {
        $('#busqueda').val('');
        $.get('lista_empleados.php', { 
            busqueda: '', 
            ajax: true 
        }, function(data) {
            $('#tabla-empleados-container').html(data);
            $('[title]').tooltip();
        });
    });
});
</script>