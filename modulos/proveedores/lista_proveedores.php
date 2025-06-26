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

// Título para la página (solo para el <title> del header)
$titulo = 'Lista de Proveedores - Plantulas';

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
    $sql = "SELECT * FROM proveedores WHERE activo = 1 
            AND (nombre_proveedor LIKE ? OR alias LIKE ? OR nombre_empresa LIKE ? OR nombre_contacto LIKE ?)
            ORDER BY nombre_proveedor ASC";
    
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute(array_fill(0, 4, $busquedaLike));
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Para peticiones AJAX, devolver solo la tabla
        if (isset($_GET['ajax'])) {
            include __DIR__ . '/tabla_proveedores.php';
            exit;
        }
    } catch (PDOException $e) {
        die("Error al obtener proveedores: " . $e->getMessage());
    }
} else {
    $sql = "SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre_proveedor ASC";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener proveedores: " . $e->getMessage());
    }
}

// Incluir el header principal (solo una vez)
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-3 mb-4">
    <div class="card">
        <div class="card-body">
            <!-- Barra de búsqueda y acciones -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="busqueda" 
                               placeholder="Buscar por nombre, alias o empresa..."
                               value="<?= htmlspecialchars($busqueda) ?>">
                        <button class="btn btn-primary" type="button" id="btn-buscar">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <?php if(!empty($busqueda)): ?>
                        <button class="btn btn-outline-secondary" type="button" id="limpiar-busqueda">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="registro_proveedor.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Nuevo Proveedor
                    </a>
                </div>
            </div>

            <!-- Mensajes flash -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-3">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Contenedor de la tabla -->
            <div id="tabla-container">
                <?php include __DIR__ . '/tabla_proveedores.php'; ?>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- JavaScript para búsqueda AJAX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscarProveedores = (termino) => {
        fetch(`lista_proveedores.php?busqueda=${encodeURIComponent(termino)}&ajax=1`)
            .then(response => {
                if (!response.ok) throw new Error('Error en la búsqueda');
                return response.text();
            })
            .then(html => {
                document.getElementById('tabla-container').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al realizar la búsqueda');
            });
    };

    // Eventos
    document.getElementById('btn-buscar').addEventListener('click', function() {
        buscarProveedores(document.getElementById('busqueda').value);
    });

    document.getElementById('limpiar-busqueda')?.addEventListener('click', function() {
        document.getElementById('busqueda').value = '';
        buscarProveedores('');
    });

    document.getElementById('busqueda').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            buscarProveedores(this.value);
        }
    });
});
</script>