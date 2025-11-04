<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión para mensajes flash y CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si se recibió un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de proveedor no especificado";
    header('Location: lista_proveedores.php');
    exit;
}

$id_proveedor = (int)$_GET['id'];

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/../../includes/config.php';

// Instanciar base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos actuales del proveedor
try {
    $sql = "SELECT * FROM proveedores WHERE id_proveedor = ?";
    $stmt = $con->prepare($sql);
    $stmt->execute([$id_proveedor]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proveedor) {
        $_SESSION['error_message'] = "Proveedor no encontrado";
        header('Location: lista_proveedores.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error al obtener proveedor: " . $e->getMessage());
}

// Procesamiento del formulario
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        // Sanitización de datos
        $datos = [
            'id_proveedor' => $id_proveedor,
            'alias' => isset($_POST['alias']) ? htmlspecialchars(trim($_POST['alias']), ENT_QUOTES, 'UTF-8') : null,
            'nombre_proveedor' => isset($_POST['nombre_proveedor']) ? htmlspecialchars(trim($_POST['nombre_proveedor']), ENT_QUOTES, 'UTF-8') : '',
            'nombre_empresa' => isset($_POST['nombre_empresa']) ? htmlspecialchars(trim($_POST['nombre_empresa']), ENT_QUOTES, 'UTF-8') : '',
            'nombre_contacto' => isset($_POST['nombre_contacto']) ? htmlspecialchars(trim($_POST['nombre_contacto']), ENT_QUOTES, 'UTF-8') : '',
            'telefono' => isset($_POST['telefono']) ? preg_replace('/[^0-9]/', '', $_POST['telefono']) : '',
            'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
            'rfc' => ($_POST['opcion'] === 'si' && isset($_POST['rfc'])) ? 
                     strtoupper(preg_replace('/[^A-ZÑ&0-9]/', '', $_POST['rfc'])) : null,
            'domicilio_fiscal' => ($_POST['opcion'] === 'si' && isset($_POST['domicilio_fiscal'])) ? 
                                htmlspecialchars(trim($_POST['domicilio_fiscal']), ENT_QUOTES, 'UTF-8') : null,
            'productos' => isset($_POST['productos']) ? htmlspecialchars(trim($_POST['productos']), ENT_QUOTES, 'UTF-8') : ''
        ];

        // Validaciones
        if (empty($datos['nombre_proveedor'])) {
            throw new Exception("El nombre del proveedor es requerido");
        }

        if (empty($datos['nombre_contacto'])) {
            throw new Exception("El nombre de contacto es requerido");
        }

        if (!preg_match('/^[0-9]{10,15}$/', $datos['telefono'])) {
            throw new Exception("Teléfono no válido. Debe contener entre 10 y 15 dígitos");
        }

        if (!$datos['email']) {
            throw new Exception("Correo electrónico no válido");
        }

        if ($datos['rfc'] !== null && !preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $datos['rfc'])) {
            throw new Exception("Formato de RFC no válido");
        }

        // Actualizar en BD
        $sql = "UPDATE proveedores SET
                  alias = :alias,
                  nombre_proveedor = :nombre_proveedor,
                  nombre_empresa = :nombre_empresa,
                  nombre_contacto = :nombre_contacto,
                  telefono = :telefono,
                  email = :email,
                  rfc = :rfc,
                  domicilio_fiscal = :domicilio_fiscal,
                  productos = :productos
                WHERE id_proveedor = :id_proveedor";

        $stmt = $con->prepare($sql);
        $result = $stmt->execute($datos);

        if ($result) {
            $_SESSION['success_message'] = 'Proveedor actualizado correctamente';
            header('Location: lista_proveedores.php');
            exit();
        } else {
            throw new Exception("Error al actualizar el proveedor");
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Incluir header
$titulo = "Editar de Proveedores";
$encabezado = "Panel Edición de Proveedores";
$subtitulo = "";
$ruta = "dashboard_proveedores.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Proveedor</h2>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <!-- Sección Información Básica -->
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-info-circle"></i> Información Básica</h5>
                        
                        <div class="mb-3">
                            <label for="alias" class="form-label">Alias (Opcional)</label>
                            <input type="text" class="form-control" id="alias" name="alias" 
                                    value="<?= htmlspecialchars($proveedor['alias']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="nombre_proveedor" class="form-label required-field">Nombre/Razón Social</label>
                            <input type="text" class="form-control" id="nombre_proveedor" name="nombre_proveedor" required
                                    value="<?= htmlspecialchars($proveedor['nombre_proveedor']) ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre del proveedor</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nombre_empresa" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa"
                                    value="<?= htmlspecialchars($proveedor['nombre_empresa']) ?>">
                        </div>
                    </div>
                    
                    <!-- Sección Contacto -->
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-person-lines-fill"></i> Datos de Contacto</h5>
                        
                        <div class="mb-3">
                            <label for="nombre_contacto" class="form-label required-field">Persona de Contacto</label>
                            <input type="text" class="form-control" id="nombre_contacto" name="nombre_contacto" required
                                    value="<?= htmlspecialchars($proveedor['nombre_contacto']) ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre de contacto</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label required-field">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required
                                    pattern="[0-9]{10,15}" 
                                    value="<?= htmlspecialchars($proveedor['telefono']) ?>">
                            <div class="invalid-feedback">Ingrese un teléfono válido (10-15 dígitos)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label required-field">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                    value="<?= htmlspecialchars($proveedor['email']) ?>">
                            <div class="invalid-feedback">Ingrese un correo electrónico válido</div>
                        </div>
                    </div>
                </div>
                
                <!-- Sección Productos -->
                <div class="form-section">
                    <h5><i class="bi bi-box-seam"></i> Productos</h5>
                    <div class="mb-3">
                        <label for="productos" class="form-label">Productos que provee</label>
                        <textarea class="form-control" id="productos" name="productos" rows="3"><?= htmlspecialchars($proveedor['productos']) ?></textarea>
                    </div>
                </div>
                
                <!-- Sección Facturación -->
                <div class="form-section">
                    <h5><i class="bi bi-receipt"></i> Datos de Facturación</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">¿Requiere facturación?</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="opcion" id="opcion-si" value="si" 
                                <?= !empty($proveedor['rfc']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="opcion-si">Sí</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="opcion" id="opcion-no" value="no"
                                <?= empty($proveedor['rfc']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="opcion-no">No</label>
                        </div>
                    </div>
                    
                    <div id="datos-fiscales" class="bg-light p-3 rounded <?= !empty($proveedor['rfc']) ? 'show' : '' ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc" 
                                        value="<?= htmlspecialchars($proveedor['rfc']) ?>">
                                <div class="rfc-example">Ejemplo: XAXX010101000 (Personas físicas) o EKU900317SA7 (Personas morales)</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="domicilio_fiscal" class="form-label">Domicilio Fiscal</label>
                                <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2"><?= htmlspecialchars($proveedor['domicilio_fiscal']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_proveedores.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar datos fiscales
    const opcionSi = document.getElementById('opcion-si');
    const opcionNo = document.getElementById('opcion-no');
    const datosFiscales = document.getElementById('datos-fiscales');
    
    function toggleDatosFiscales() {
        if (opcionSi.checked) {
            datosFiscales.classList.add('show');
        } else {
            datosFiscales.classList.remove('show');
        }
    }
    
    opcionSi.addEventListener('change', toggleDatosFiscales);
    opcionNo.addEventListener('change', toggleDatosFiscales);
    
    // Validación del formulario
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
</script>
