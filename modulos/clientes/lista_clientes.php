<?php
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión para mensajes flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';

// Establecer variables para el header (solo para el título)
$titulo = 'Lista de Clientes - Plantulas';

// Instanciar base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar búsqueda
$busqueda = $_GET['busqueda'] ?? '';

if (!empty($busqueda)) {
    $busquedaLike = "%$busqueda%";
    $sql = "SELECT * FROM clientes WHERE activo = 1 
            AND (nombre_Cliente LIKE ? OR alias LIKE ? OR nombre_Empresa LIKE ? OR nombre_contacto LIKE ?)
            ORDER BY nombre_Cliente ASC";
    
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute(array_fill(0, 4, $busquedaLike));
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        die("Error al obtener clientes: " . $e->getMessage());
    }
} else {
    $sql = "SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre_Cliente ASC";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener clientes: " . $e->getMessage());
    }
}
// Para peticiones AJAX, devolver solo la tabla
if (isset($_GET['ajax'])) {
    include __DIR__ . '/tabla_clientes.php';
    exit;
}

?>


<?php
// Configuración de encabezado
$encabezado = "Lista los Clientes";
$subtitulo = "Muestra clientes registrados en el  sistema";

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "dashboard_clientes.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>


<main class="container mt-4 mb-5">
    <div class="card card-lista">
        <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Clientes</h2>
                    <div>
                    
                        <a href="registro_cliente.php" class="btn btn-success btn-sm ms-2">
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
                placeholder="Buscar clientes por nombre, alias, empresa o contacto..."
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

            <!-- Contenedor de la tabla -->
            <div class="table-responsive" id="tabla-clientes-container">
                <?php include __DIR__ . '/tabla_clientes.php'; ?>
            </div>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>
<!-- jQuery para AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Inicializar tooltips
        $('[title]').tooltip();
        
        // Búsqueda automática con AJAX
        let searchTimeout;
        $('#busqueda').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const searchTerm = $('#busqueda').val();
                $.get('lista_clientes.php', { 
                    busqueda: searchTerm, 
                    ajax: true 
                }, function(data) {
                    $('#tabla-clientes-container').html(data);
                    $('[title]').tooltip(); // Reinicializar tooltips
                });
            }, 300);
        });
        
        // Limpiar búsqueda
        $('#limpiar-busqueda').click(function() {
            $('#busqueda').val('');
            $.get('lista_clientes.php', { 
                busqueda: '', 
                ajax: true 
            }, function(data) {
                $('#tabla-clientes-container').html(data);
                $('[title]').tooltip();
            });
        });
    });
</script>