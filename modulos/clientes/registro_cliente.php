<?php
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión solo para mensajes flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/../../includes/config.php';

// Instanciar base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Generar token CSRF para protección del formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesamiento del formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        // Sanitización de datos
        $datos = [
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

        // Insertar en BD
        $sql = "INSERT INTO clientes (
                  alias, nombre_Cliente, nombre_Empresa, nombre_contacto, 
                  telefono, email, rfc, domicilio_fiscal
                ) VALUES (
                  :alias, :nombre_Cliente, :nombre_Empresa, :nombre_contacto, 
                  :telefono, :email, :rfc, :domicilio_fiscal
                )";

        $stmt = $con->prepare($sql);
        $stmt->execute($datos);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'Cliente registrado correctamente';
            
            // Redirección mejorada
            $ruta_lista = 'lista_clientes.php';
            $ruta_absoluta = __DIR__ . '/' . $ruta_lista;
            
            if (file_exists($ruta_absoluta)) {
                // Limpiar buffer de salida
                if (ob_get_length()) ob_end_clean();
                
                // Redirección relativa al directorio actual
                header('Location: ' . $ruta_lista);
                exit();
            } else {
                throw new Exception("El archivo lista_clientes.php no se encontró en: " . $ruta_absoluta);
            }
        } else {
            throw new Exception("Error al registrar el cliente");
        }

    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
        error_log("Error en registro_clientes.php: " . $e->getMessage());
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Error en registro_clientes.php: " . $e->getMessage());
    }
}

// Incluir la cabecera (ruta relativa al archivo actual)
$ruta = "dashboard_clientes.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>


<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex align-items-center">
                <h2 class="mb-0"><i class="bi bi-person-plus"></i> Registrar Nuevo Cliente</h2>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" id="clienteForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <!-- Sección Información Básica -->
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-info-circle"></i> Información Básica</h5>
                        
                        <div class="mb-3">
                            <label for="alias" class="form-label">Alias (Opcional)</label>
                            <input type="text" class="form-control" id="alias" name="alias" maxlength="255"
                                    placeholder="Nombre corto para identificar al cliente"
                                    value="<?= htmlspecialchars($_POST['alias'] ?? '') ?>">
                        
                        </div>
                        
                        <div class="mb-3">
                            <label for="nombre_Cliente" class="form-label required-field">Nombre/Razón Social</label>
                            <input type="text" class="form-control" id="nombre_Cliente" name="nombre_Cliente" required maxlength="255"
                                    placeholder="Nombre completo o razón social"
                                    value="<?= htmlspecialchars($_POST['nombre_Cliente'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre del cliente</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nombre_Empresa" class="form-label">Empresa (Opcional)</label>
                            <input type="text" class="form-control" id="nombre_Empresa" name="nombre_Empresa" maxlength="255"
                                    placeholder="Nombre de la empresa (si aplica)"
                                    value="<?= htmlspecialchars($_POST['nombre_Empresa'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Sección Contacto -->
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-person-lines-fill"></i> Datos de Contacto</h5>
                        
                        <div class="mb-3">
                            <label for="nombre_contacto" class="form-label required-field">Persona de Contacto</label>
                            <input type="text" class="form-control" id="nombre_contacto" name="nombre_contacto" required maxlength="255"
                                    placeholder="Nombre de la persona principal de contacto"
                                    value="<?= htmlspecialchars($_POST['nombre_contacto'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre de contacto</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label required-field">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required
                                    pattern="[0-9]{10,15}" title="Entre 10 y 15 dígitos numéricos"
                                    placeholder="10 a 15 dígitos sin espacios ni guiones"
                                    value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                            <div class="invalid-feedback">Ingrese un teléfono válido (10-15 dígitos)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label required-field">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                    placeholder="correo@ejemplo.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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
                                <?= (isset($_POST['opcion']) && $_POST['opcion'] === 'si') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="opcion-si">Sí</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="opcion" id="opcion-no" value="no"
                                <?= (!isset($_POST['opcion']) || $_POST['opcion'] === 'no') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="opcion-no">No</label>
                        </div>
                    </div>
                    
                    <div id="datos-fiscales" class="bg-light p-3 rounded <?= (isset($_POST['opcion']) && $_POST['opcion'] === 'si') ? 'show' : '' ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc" maxlength="14" 
                                        placeholder="XAXX010101000" pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
                                        value="<?= htmlspecialchars($_POST['rfc'] ?? '') ?>">
                                <div class="rfc-example">Ejemplo: XAXX010101000 (Personas físicas) o EKU900317SA7 (Personas morales)</div>
                                <div class="invalid-feedback">Formato de RFC no válido</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="domicilio_fiscal" class="form-label">Domicilio Fiscal</label>
                                <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2" maxlength="255"
                                            placeholder="Calle, número, colonia, código postal, ciudad, estado"><?= htmlspecialchars($_POST['domicilio_fiscal'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botón de acción -->
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="bi bi-save"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar datos fiscales con animación
    const opcionSi = document.getElementById('opcion-si');
    const opcionNo = document.getElementById('opcion-no');
    const datosFiscales = document.getElementById('datos-fiscales');
datosFiscales.style.display = opcionSi.checked ? 'block' : 'none';
    
    function toggleDatosFiscales() {
    if (opcionSi.checked) {
        datosFiscales.style.display = 'block';
    } else {
        datosFiscales.style.display = 'none';
        document.getElementById('rfc').value = '';
        document.getElementById('domicilio_fiscal').value = '';
    }
}
    opcionSi.addEventListener('change', toggleDatosFiscales);
    opcionNo.addEventListener('change', toggleDatosFiscales);
    
    // Validación del formulario
    const form = document.getElementById('clienteForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            // Mostrar loading al enviar
            loadingOverlay.style.display = 'flex';
            submitBtn.disabled = true;
        }
        form.classList.add('was-validated');
    }, false);
    
    // Validación y formato del RFC
    const rfcInput = document.getElementById('rfc');
    if (rfcInput) {
        rfcInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-ZÑ&0-9]/g, '');
        });
    }
    
    // Validación del teléfono (solo números)
    const telefonoInput = document.getElementById('telefono');
    if (telefonoInput) {
        telefonoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // Validación mientras se escribe
    const camposRequeridos = document.querySelectorAll('[required]');
    camposRequeridos.forEach(campo => {
        campo.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
            }
        });
    });
});
</script>
