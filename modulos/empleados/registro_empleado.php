<?php
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión para mensajes flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/../../includes/config.php';

// Generar token CSRF para protección del formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Niveles de estudio disponibles
$nivelesEstudio = [
    'Primaria' => 'Primaria',
    'Secundaria' => 'Secundaria',
    'Bachillerato' => 'Bachillerato',
    'Técnico' => 'Técnico',
    'Licenciatura' => 'Licenciatura',
    'Maestría' => 'Maestría',
    'Doctorado' => 'Doctorado',
    'Otro' => 'Otro'
];

// Tipos de sangre disponibles
$tiposSangre = [
    'A+' => 'A+',
    'A-' => 'A-',
    'B+' => 'B+',
    'B-' => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    'O+' => 'O+',
    'O-' => 'O-',
    'Desconocido' => 'Desconocido'
];

// Redes sociales disponibles
$redesSociales = [
    'Facebook' => 'Facebook',
    'Instagram' => 'Instagram',
    'Twitter' => 'Twitter',
    'LinkedIn' => 'LinkedIn',
    'TikTok' => 'TikTok',
    'Otra' => 'Otra'
];

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
            'nombre' => isset($_POST['nombre']) ? htmlspecialchars(trim($_POST['nombre']), ENT_QUOTES, 'UTF-8') : '',
            'apellido_paterno' => isset($_POST['apellido_paterno']) ? htmlspecialchars(trim($_POST['apellido_paterno']), ENT_QUOTES, 'UTF-8') : '',
            'apellido_materno' => isset($_POST['apellido_materno']) ? htmlspecialchars(trim($_POST['apellido_materno']), ENT_QUOTES, 'UTF-8') : '',
            'fecha_nacimiento' => isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
            'fecha_contratacion' => isset($_POST['fecha_contratacion']) ? $_POST['fecha_contratacion'] : date('Y-m-d'), // Valor por defecto: fecha actual
            'nivel_estudios' => isset($_POST['nivel_estudios']) ? htmlspecialchars(trim($_POST['nivel_estudios']), ENT_QUOTES, 'UTF-8') : '',
            'telefono' => isset($_POST['telefono']) ? preg_replace('/[^0-9]/', '', $_POST['telefono']) : '',
            'email' => isset($_POST['email']) && !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : null,
            'direccion' => isset($_POST['direccion']) ? htmlspecialchars(trim($_POST['direccion']), ENT_QUOTES, 'UTF-8') : '',
            'contacto_emergencia_nombre' => isset($_POST['contacto_emergencia_nombre']) ? htmlspecialchars(trim($_POST['contacto_emergencia_nombre']), ENT_QUOTES, 'UTF-8') : '',
            'contacto_emergencia_parentesco' => isset($_POST['contacto_emergencia_parentesco']) ? htmlspecialchars(trim($_POST['contacto_emergencia_parentesco']), ENT_QUOTES, 'UTF-8') : '',
            'contacto_emergencia_telefono' => isset($_POST['contacto_emergencia_telefono']) ? preg_replace('/[^0-9]/', '', $_POST['contacto_emergencia_telefono']) : '',
            'hobbies' => isset($_POST['hobbies']) ? htmlspecialchars(trim($_POST['hobbies']), ENT_QUOTES, 'UTF-8') : '',
            'red_social' => isset($_POST['red_social']) ? htmlspecialchars(trim($_POST['red_social']), ENT_QUOTES, 'UTF-8') : '',
            'red_social_usuario' => isset($_POST['red_social_usuario']) ? htmlspecialchars(trim($_POST['red_social_usuario']), ENT_QUOTES, 'UTF-8') : '',
            'tipo_sangre' => isset($_POST['tipo_sangre']) ? htmlspecialchars(trim($_POST['tipo_sangre']), ENT_QUOTES, 'UTF-8') : '',
            'curp' => isset($_POST['curp']) ? strtoupper(htmlspecialchars(trim($_POST['curp']), ENT_QUOTES, 'UTF-8')) : '',
            'rfc' => isset($_POST['rfc']) ? strtoupper(htmlspecialchars(trim($_POST['rfc']), ENT_QUOTES, 'UTF-8')) : '',
            'nss' => isset($_POST['nss']) ? preg_replace('/[^0-9]/', '', $_POST['nss']) : '',
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];

        // Validaciones
        if (empty($datos['nombre'])) {
            throw new Exception("El nombre del empleado es requerido");
        }

        if (empty($datos['apellido_paterno'])) {
            throw new Exception("El apellido paterno es requerido");
        }

        if (!preg_match('/^[0-9]{10,15}$/', $datos['telefono'])) {
            throw new Exception("Teléfono no válido. Debe contener entre 10 y 15 dígitos");
        }

        // Validar email solo si se proporcionó
        if ($datos['email'] === false) {
            throw new Exception("Correo electrónico no válido");
        }

        if (!empty($datos['contacto_emergencia_telefono']) && !preg_match('/^[0-9]{10,15}$/', $datos['contacto_emergencia_telefono'])) {
            throw new Exception("Teléfono de emergencia no válido. Debe contener entre 10 y 15 dígitos");
        }

        // Validar CURP (18 caracteres alfanuméricos)
        if (!empty($datos['curp']) && !preg_match('/^[A-Z0-9]{18}$/', $datos['curp'])) {
            throw new Exception("CURP no válido. Debe contener exactamente 18 caracteres alfanuméricos");
        }

        // Validar RFC (12 o 13 caracteres alfanuméricos)
        if (!empty($datos['rfc']) && !preg_match('/^[A-Z0-9]{12,13}$/', $datos['rfc'])) {
            throw new Exception("RFC no válido. Debe contener 12 o 13 caracteres alfanuméricos");
        }

        // Validar NSS (11 dígitos)
        if (!empty($datos['nss']) && !preg_match('/^[0-9]{11}$/', $datos['nss'])) {
            throw new Exception("NSS no válido. Debe contener exactamente 11 dígitos");
        }

        // Validar fechas
        if (!empty($datos['fecha_nacimiento']) && !DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento'])) {
            throw new Exception("Formato de fecha de nacimiento inválido");
        }

        if (!empty($datos['fecha_contratacion']) && !DateTime::createFromFormat('Y-m-d', $datos['fecha_contratacion'])) {
            throw new Exception("Formato de fecha de contratación inválido");
        }

        // Insertar en BD
        $sql = "INSERT INTO empleados (
                  nombre, apellido_paterno, apellido_materno, fecha_nacimiento, fecha_contratacion, nivel_estudios,
                  telefono, email, direccion, contacto_emergencia_nombre, 
                  contacto_emergencia_parentesco, contacto_emergencia_telefono, hobbies,
                  red_social, red_social_usuario, tipo_sangre, curp, rfc, nss, activo
                ) VALUES (
                  :nombre, :apellido_paterno, :apellido_materno, :fecha_nacimiento, :fecha_contratacion, :nivel_estudios,
                  :telefono, :email, :direccion, :contacto_emergencia_nombre,
                  :contacto_emergencia_parentesco, :contacto_emergencia_telefono, :hobbies,
                  :red_social, :red_social_usuario, :tipo_sangre, :curp, :rfc, :nss, :activo
                )";

        $db = new Database();
        $con = $db->conectar();
        $stmt = $con->prepare($sql);
        $stmt->execute($datos);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'Empleado registrado correctamente';
            header('Location: lista_empleados.php');
            exit();
        } else {
            throw new Exception("Error al registrar el empleado");
        }

    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
        error_log("Error en registro_empleados.php: " . $e->getMessage());
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Error en registro_empleados.php: " . $e->getMessage());
    }
}

// Configuración de encabezado
$encabezado = "Registrar Empleado";
$subtitulo = "Formulario para registrar nuevos empleados";

// Incluir la cabecera
$ruta = "dashboard_empleados.php";
$texto_boton = "";
require('../../includes/header.php');
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex align-items-center">
                <h2 class="mb-0"><i class="bi bi-person-plus"></i> Registrar Nuevo Empleado</h2>
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

            <form method="post" id="empleadoForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <!-- Sección Información Personal -->
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-person-vcard"></i> Información Personal</h5>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label required-field">Nombre(s)</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100"
                                    placeholder="Nombre(s) del empleado"
                                    value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre del empleado</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="apellido_paterno" class="form-label required-field">Apellido Paterno</label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required maxlength="50"
                                        placeholder="Apellido paterno"
                                        value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? '') ?>">
                                <div class="invalid-feedback">Por favor ingrese el apellido paterno</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido_materno" class="form-label">Apellido Materno</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" maxlength="50"
                                        placeholder="Apellido materno"
                                        value="<?= htmlspecialchars($_POST['apellido_materno'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                                    value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="fecha_contratacion" class="form-label required-field">Fecha de Contratación</label>
                            <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion" required
                                    value="<?= htmlspecialchars($_POST['fecha_contratacion'] ?? date('Y-m-d')) ?>">
                            <div class="invalid-feedback">Por favor ingrese la fecha de contratación</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="curp" class="form-label">CURP</label>
                            <input type="text" class="form-control text-uppercase" id="curp" name="curp" maxlength="18"
                                    placeholder="18 caracteres alfanuméricos"
                                    pattern="[A-Z0-9]{18}" title="18 caracteres alfanuméricos en mayúsculas"
                                    value="<?= htmlspecialchars($_POST['curp'] ?? '') ?>">
                            <div class="invalid-feedback">El CURP debe contener exactamente 18 caracteres alfanuméricos</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rfc" class="form-label">RFC</label>
                            <input type="text" class="form-control text-uppercase" id="rfc" name="rfc" maxlength="13"
                                    placeholder="12 o 13 caracteres alfanuméricos"
                                    pattern="[A-Z0-9]{12,13}" title="12 o 13 caracteres alfanuméricos en mayúsculas"
                                    value="<?= htmlspecialchars($_POST['rfc'] ?? '') ?>">
                            <div class="invalid-feedback">El RFC debe contener 12 o 13 caracteres alfanuméricos</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nss" class="form-label">Número de Seguro Social (NSS)</label>
                            <input type="text" class="form-control" id="nss" name="nss" maxlength="11"
                                    placeholder="11 dígitos"
                                    pattern="[0-9]{11}" title="11 dígitos numéricos"
                                    value="<?= htmlspecialchars($_POST['nss'] ?? '') ?>">
                            <div class="invalid-feedback">El NSS debe contener exactamente 11 dígitos</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 form-section">
                        <h5><i class="bi bi-person-vcard"></i> Información Adicional</h5>
                        
                        <div class="mb-3">
                            <label for="nivel_estudios" class="form-label">Nivel de Estudios</label>
                            <select class="form-select" id="nivel_estudios" name="nivel_estudios">
                                <option value="">Seleccione un nivel</option>
                                <?php foreach ($nivelesEstudio as $valor => $texto): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= (isset($_POST['nivel_estudios']) && $_POST['nivel_estudios'] === $valor) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($texto) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tipo_sangre" class="form-label">Tipo de Sangre</label>
                            <select class="form-select" id="tipo_sangre" name="tipo_sangre">
                                <option value="">Seleccione tipo de sangre</option>
                                <?php foreach ($tiposSangre as $valor => $texto): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= (isset($_POST['tipo_sangre']) && $_POST['tipo_sangre'] === $valor) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($texto) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <h5 class="mt-4"><i class="bi bi-telephone"></i> Información de Contacto</h5>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label required-field">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required
                                    pattern="[0-9]{10,15}" title="Entre 10 y 15 dígitos numéricos"
                                    placeholder="10 a 15 dígitos sin espacios ni guiones"
                                    value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                            <div class="invalid-feedback">Ingrese un teléfono válido (10-15 dígitos)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico (Opcional)</label>
                            <input type="email" class="form-control" id="email" name="email"
                                    placeholder="correo@ejemplo.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <div class="invalid-feedback">Ingrese un correo electrónico válido</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="red_social" class="form-label">Red Social Principal</label>
                            <select class="form-select" id="red_social" name="red_social">
                                <option value="">Seleccione una red social</option>
                                <?php foreach ($redesSociales as $valor => $texto): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= (isset($_POST['red_social']) && $_POST['red_social'] === $valor) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($texto) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="red_social_usuario" class="form-label">Usuario/Perfil en la Red Social</label>
                            <input type="text" class="form-control" id="red_social_usuario" name="red_social_usuario"
                                    placeholder="Ej: @miusuario o nombre.perfil"
                                    value="<?= htmlspecialchars($_POST['red_social_usuario'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2"
                                    placeholder="Calle, número, colonia, ciudad"><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Sección Contacto de Emergencia -->
                <div class="form-section mt-4">
                    <h5><i class="bi bi-exclamation-triangle"></i> Contacto de Emergencia</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="contacto_emergencia_nombre" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" maxlength="100"
                                    placeholder="Nombre del contacto de emergencia"
                                    value="<?= htmlspecialchars($_POST['contacto_emergencia_nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="contacto_emergencia_parentesco" class="form-label">Parentesco</label>
                            <input type="text" class="form-control" id="contacto_emergencia_parentesco" name="contacto_emergencia_parentesco" maxlength="50"
                                    placeholder="Parentesco (ej. Padre, Esposo, etc.)"
                                    value="<?= htmlspecialchars($_POST['contacto_emergencia_parentesco'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="contacto_emergencia_telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="contacto_emergencia_telefono" name="contacto_emergencia_telefono"
                                    pattern="[0-9]{10,15}" title="Entre 10 y 15 dígitos numéricos"
                                    placeholder="Teléfono de emergencia"
                                    value="<?= htmlspecialchars($_POST['contacto_emergencia_telefono'] ?? '') ?>">
                            <div class="invalid-feedback">Ingrese un teléfono válido (10-15 dígitos)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Sección Hobbies -->
                <div class="form-section mt-4">
                    <h5><i class="bi bi-heart"></i> Hobbies e Intereses</h5>
                    <div class="mb-3">
                        <label for="hobbies" class="form-label">Hobbies (separados por comas)</label>
                        <textarea class="form-control" id="hobbies" name="hobbies" rows="2"
                                placeholder="Ejemplo: Fotografía, lectura, deportes, viajar"><?= htmlspecialchars($_POST['hobbies'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="mb-3 form-check mt-3">
                    <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" checked>
                    <label class="form-check-label" for="activo">Empleado activo</label>
                </div>
                
                <!-- Botón de acción -->
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="bi bi-save"></i> Guardar Empleado
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
    // Validación del formulario
    const form = document.getElementById('empleadoForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
    
    // Validación del teléfono (solo números)
    const telefonoInput = document.getElementById('telefono');
    const telefonoEmergenciaInput = document.getElementById('contacto_emergencia_telefono');
    const nssInput = document.getElementById('nss');
    
    if (telefonoInput) {
        telefonoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    if (telefonoEmergenciaInput) {
        telefonoEmergenciaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    if (nssInput) {
        nssInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // Convertir CURP y RFC a mayúsculas automáticamente
    const curpInput = document.getElementById('curp');
    const rfcInput = document.getElementById('rfc');
    
    if (curpInput) {
        curpInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    if (rfcInput) {
        rfcInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
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