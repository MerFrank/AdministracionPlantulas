<?php
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión para mensajes flash y CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si se recibió un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de cliente no especificado";
    header('Location: lista_clientes.php');
    exit;
}

$id_cliente = (int)$_GET['id'];

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

// Obtener datos actuales del cliente
try {
    $sql = "SELECT * FROM clientes WHERE id_cliente = ?";
    $stmt = $con->prepare($sql);
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $_SESSION['error_message'] = "Cliente no encontrado";
        header('Location: lista_clientes.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error al obtener cliente: " . $e->getMessage());
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
            'id_cliente' => $id_cliente,
            'alias' => isset($_POST['alias']) ? htmlspecialchars(trim($_POST['alias']), ENT_QUOTES, 'UTF-8') : null,
            'nombre_Cliente' => isset($_POST['nombre_Cliente']) ? htmlspecialchars(trim($_POST['nombre_Cliente']), ENT_QUOTES, 'UTF-8') : '',
            'nombre_Empresa' => isset($_POST['nombre_Empresa']) ? htmlspecialchars(trim($_POST['nombre_Empresa']), ENT_QUOTES, 'UTF-8') : null,
            'nombre_contacto' => isset($_POST['nombre_contacto']) ? htmlspecialchars(trim($_POST['nombre_contacto']), ENT_QUOTES, 'UTF-8') : '',
            'telefono' => isset($_POST['telefono']) ? preg_replace('/[^0-9]/', '', $_POST['telefono']) : '',
            'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
            'rfc' => ($_POST['opcion'] === 'si' && isset($_POST['rfc'])) ? 
                     strtoupper(preg_replace('/[^A-ZÑ&0-9]/', '', $_POST['rfc'])) : null,
            'domicilio_fiscal' => ($_POST['opcion'] === 'si' && isset($_POST['domicilio_fiscal'])) ? 
                                htmlspecialchars(trim($_POST['domicilio_fiscal']), ENT_QUOTES, 'UTF-8') : null
        ];

        // Validaciones
        if (empty($datos['nombre_Cliente'])) {
            throw new Exception("El nombre del cliente es requerido");
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
        $sql = "UPDATE clientes SET
                  alias = :alias,
                  nombre_Cliente = :nombre_Cliente,
                  nombre_Empresa = :nombre_Empresa,
                  nombre_contacto = :nombre_contacto,
                  telefono = :telefono,
                  email = :email,
                  rfc = :rfc,
                  domicilio_fiscal = :domicilio_fiscal
                WHERE id_cliente = :id_cliente";

        $stmt = $con->prepare($sql);
        $result = $stmt->execute($datos);

        if ($result) {
            $_SESSION['success_message'] = 'Cliente actualizado correctamente';
            header('Location: lista_clientes.php');
            exit();
        } else {
            throw new Exception("Error al actualizar el cliente");
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Plantulas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="/Plantulas/assets/css/style.css" rel="stylesheet">
    <style>
        .invalid-feedback { display: none; color: #dc3545; }
        .was-validated .form-control:invalid ~ .invalid-feedback { display: block; }
        #datos-fiscales { transition: all 0.3s ease; max-height: 0; overflow: hidden; }
        #datos-fiscales.show { max-height: 500px; }
        .form-section { margin-bottom: 1.5rem; }
        .form-section h5 { margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #45814d; color: #45814d; }
        .required-field::after { content: " *"; color: #dc3545; }
        .rfc-example { font-size: 0.85rem; color: #6c757d; font-style: italic; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    
    <main class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Cliente</h2>
                    <a href="lista_clientes.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Volver a la lista
                    </a>
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
                                       value="<?= htmlspecialchars($cliente['alias']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="nombre_Cliente" class="form-label required-field">Nombre/Razón Social</label>
                                <input type="text" class="form-control" id="nombre_Cliente" name="nombre_Cliente" required
                                       value="<?= htmlspecialchars($cliente['nombre_Cliente']) ?>">
                                <div class="invalid-feedback">Por favor ingrese el nombre del cliente</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nombre_Empresa" class="form-label">Empresa (Opcional)</label>
                                <input type="text" class="form-control" id="nombre_Empresa" name="nombre_Empresa"
                                       value="<?= htmlspecialchars($cliente['nombre_Empresa']) ?>">
                            </div>
                        </div>
                        
                        <!-- Sección Contacto -->
                        <div class="col-md-6 form-section">
                            <h5><i class="bi bi-person-lines-fill"></i> Datos de Contacto</h5>
                            
                            <div class="mb-3">
                                <label for="nombre_contacto" class="form-label required-field">Persona de Contacto</label>
                                <input type="text" class="form-control" id="nombre_contacto" name="nombre_contacto" required
                                       value="<?= htmlspecialchars($cliente['nombre_contacto']) ?>">
                                <div class="invalid-feedback">Por favor ingrese el nombre de contacto</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefono" class="form-label required-field">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" required
                                       pattern="[0-9]{10,15}" 
                                       value="<?= htmlspecialchars($cliente['telefono']) ?>">
                                <div class="invalid-feedback">Ingrese un teléfono válido (10-15 dígitos)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label required-field">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?= htmlspecialchars($cliente['email']) ?>">
                                <div class="invalid-feedback">Ingrese un correo electrónico válido</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección Facturación -->
                    <div class="form-section">
                        <h5><i class="bi bi-receipt"></i> Datos de Facturación</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">¿Requiere facturación?</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="opcion" id="opcion-si" value="si" 
                                    <?= !empty($cliente['rfc']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="opcion-si">Sí</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="opcion" id="opcion-no" value="no"
                                    <?= empty($cliente['rfc']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="opcion-no">No</label>
                            </div>
                        </div>
                        
                        <div id="datos-fiscales" class="bg-light p-3 rounded <?= !empty($cliente['rfc']) ? 'show' : '' ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="rfc" class="form-label">RFC</label>
                                    <input type="text" class="form-control" id="rfc" name="rfc" 
                                           value="<?= htmlspecialchars($cliente['rfc']) ?>">
                                    <div class="rfc-example">Ejemplo: XAXX010101000 (Personas físicas) o EKU900317SA7 (Personas morales)</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="domicilio_fiscal" class="form-label">Domicilio Fiscal</label>
                                    <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2"><?= htmlspecialchars($cliente['domicilio_fiscal']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="lista_clientes.php" class="btn btn-secondary">
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
</body>
</html>