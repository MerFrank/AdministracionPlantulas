<?php
// 1. Cargar configuración y conexión a la base de datos
require_once __DIR__ . '/../../includes/config.php';

// 2. Verificar si la conexión a la base de datos está disponible
if (!class_exists('Database')) {
    die("Error: La clase Database no está definida en config.php");
}

// 3. Inicializar la conexión a la base de datos
try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// 4. Verificar permisos (si tu aplicación los usa)
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// 5. Obtener ID del empleado
$id_empleado = isset($_GET['id_empleado']) ? (int)$_GET['id_empleado'] : 0;

// 6. Obtener datos del empleado
$empleado = null;
try {
    $sql = "SELECT * FROM empleados WHERE id_empleado = :id_empleado";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_empleado', $id_empleado, PDO::PARAM_INT);
    $stmt->execute();
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos del empleado: " . $e->getMessage());
}

// 7. Procesar formulario si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos
    $datos = [
        'id_empleado' => $id_empleado,
        'nombre' => isset($_POST['nombre']) ? sanitizar($_POST['nombre']) : '',
        'apellido_paterno' => isset($_POST['apellido_paterno']) ? sanitizar($_POST['apellido_paterno']) : '',
        'apellido_materno' => isset($_POST['apellido_materno']) ? sanitizar($_POST['apellido_materno']) : '',
        'fecha_nacimiento' => isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
        'fecha_contratacion' => isset($_POST['fecha_contratacion']) ? $_POST['fecha_contratacion'] : date('Y-m-d'),
        'nivel_estudios' => isset($_POST['nivel_estudios']) ? sanitizar($_POST['nivel_estudios']) : '',
        'telefono' => isset($_POST['telefono']) ? preg_replace('/[^0-9]/', '', $_POST['telefono']) : '',
        'email' => isset($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : null,
        'direccion' => isset($_POST['direccion']) ? sanitizar($_POST['direccion']) : '',
        'contacto_emergencia_nombre' => isset($_POST['contacto_emergencia_nombre']) ? sanitizar($_POST['contacto_emergencia_nombre']) : '',
        'contacto_emergencia_parentesco' => isset($_POST['contacto_emergencia_parentesco']) ? sanitizar($_POST['contacto_emergencia_parentesco']) : '',
        'contacto_emergencia_telefono' => isset($_POST['contacto_emergencia_telefono']) ? preg_replace('/[^0-9]/', '', $_POST['contacto_emergencia_telefono']) : '',
        'hobbies' => isset($_POST['hobbies']) ? sanitizar($_POST['hobbies']) : '',
        'red_social' => isset($_POST['red_social']) ? sanitizar($_POST['red_social']) : '',
        'red_social_usuario' => isset($_POST['red_social_usuario']) ? sanitizar($_POST['red_social_usuario']) : '',
        'tipo_sangre' => isset($_POST['tipo_sangre']) ? sanitizar($_POST['tipo_sangre']) : '',
        'curp' => isset($_POST['curp']) ? strtoupper(sanitizar($_POST['curp'])) : '',
        'rfc' => isset($_POST['rfc']) ? strtoupper(sanitizar($_POST['rfc'])) : '',
        'nss' => isset($_POST['nss']) ? preg_replace('/[^0-9]/', '', $_POST['nss']) : '',
        'activo' => isset($_POST['activo']) ? 1 : 0,
        'id_checador' => isset($_POST['id_checador']) ? preg_replace('/[^0-9]/', '', $_POST['id_checador']) : '',
        'fecha_actualizacion' => date('Y-m-d H:i:s')
    ];

    try {
        $sql = "UPDATE empleados SET 
                nombre = :nombre,
                apellido_paterno = :apellido_paterno,
                apellido_materno = :apellido_materno,
                fecha_nacimiento = :fecha_nacimiento,
                fecha_contratacion = :fecha_contratacion,
                nivel_estudios = :nivel_estudios,
                telefono = :telefono,
                email = :email,
                direccion = :direccion,
                contacto_emergencia_nombre = :contacto_emergencia_nombre,
                contacto_emergencia_parentesco = :contacto_emergencia_parentesco,
                contacto_emergencia_telefono = :contacto_emergencia_telefono,
                hobbies = :hobbies,
                red_social = :red_social,
                red_social_usuario = :red_social_usuario,
                tipo_sangre = :tipo_sangre,
                curp = :curp,
                rfc = :rfc,
                nss = :nss,
                activo = :activo,
                id_checador = :id_checador,
                fecha_actualizacion = :fecha_actualizacion
                WHERE id_empleado = :id_empleado";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($datos);
        
        $_SESSION['mensaje'] = "Empleado actualizado correctamente";
        header('Location: lista_empleados.php');
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar empleado: " . $e->getMessage());
    }
}

// Configurar variables para la vista
$titulo = "Editar Empleado";
$encabezado = "Editar Información de Empleado";
$subtitulo = "Actualice la información del empleado seleccionado";
$ruta = "lista_empleados.php";
$texto_boton = "Regresar";


// Incluir cabecera
require_once __DIR__ . '/../../includes/header.php';

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
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-person-gear"></i> Editar Empleado</h2>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <?= $_SESSION['mensaje'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>
            
            <?php if ($empleado): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row g-3">
                        <!-- Sección Información Personal -->
                        <div class="col-md-6">
                            <h5><i class="bi bi-person-vcard"></i> Información Personal</h5>
                            
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre(s)</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required
                                       value="<?= htmlspecialchars($empleado['nombre']) ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="apellido_paterno" class="form-label">Apellido Paterno</label>
                                    <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required
                                           value="<?= htmlspecialchars($empleado['apellido_paterno']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellido_materno" class="form-label">Apellido Materno</label>
                                    <input type="text" class="form-control" id="apellido_materno" name="apellido_materno"
                                           value="<?= htmlspecialchars($empleado['apellido_materno']) ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="id_checador" class="form-label">ID checador</label>
                                    <input type="text" class="form-control" id="id_checador" name="id_checador" maxlength="11"
                                            value="<?= htmlspecialchars($empleado['id_checador']) ?>">
                                </div>

                            </div>
                            
                            <div class="mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                                       value="<?= htmlspecialchars($empleado['fecha_nacimiento']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="fecha_contratacion" class="form-label">Fecha de Contratación</label>
                                <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion" required
                                       value="<?= htmlspecialchars($empleado['fecha_contratacion']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="nivel_estudios" class="form-label">Nivel de Estudios</label>
                                <select class="form-select" id="nivel_estudios" name="nivel_estudios">
                                    <option value="">Seleccione un nivel</option>
                                    <?php foreach ($nivelesEstudio as $valor => $texto): ?>
                                        <option value="<?= htmlspecialchars($valor) ?>" <?= ($empleado['nivel_estudios'] === $valor) ? 'selected' : '' ?>>
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
                                        <option value="<?= htmlspecialchars($valor) ?>" <?= ($empleado['tipo_sangre'] === $valor) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($texto) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Sección Documentos y Contacto -->
                        <div class="col-md-6">
                            <h5><i class="bi bi-file-earmark-text"></i> Documentos</h5>
                            
                            <div class="mb-3">
                                <label for="curp" class="form-label">CURP</label>
                                <input type="text" class="form-control text-uppercase" id="curp" name="curp" maxlength="18"
                                       pattern="[A-Z0-9]{18}" title="18 caracteres alfanuméricos"
                                       value="<?= htmlspecialchars($empleado['curp']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control text-uppercase" id="rfc" name="rfc" maxlength="13"
                                       pattern="[A-Z0-9]{12,13}" title="12 o 13 caracteres alfanuméricos"
                                       value="<?= htmlspecialchars($empleado['rfc']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="nss" class="form-label">Número de Seguro Social (NSS)</label>
                                <input type="text" class="form-control" id="nss" name="nss" maxlength="11"
                                       pattern="[0-9]{11}" title="11 dígitos numéricos"
                                       value="<?= htmlspecialchars($empleado['nss']) ?>">
                            </div>
                            
                            <h5 class="mt-4"><i class="bi bi-telephone"></i> Contacto</h5>
                            
                            <div class="mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" required
                                       pattern="[0-9]{10,15}" title="10 a 15 dígitos"
                                       value="<?= htmlspecialchars($empleado['telefono']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($empleado['email']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?= htmlspecialchars($empleado['direccion']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección Redes Sociales -->
                    <div class="mt-4">
                        <h5><i class="bi bi-share"></i> Redes Sociales</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="red_social" class="form-label">Red Social Principal</label>
                                <select class="form-select" id="red_social" name="red_social">
                                    <option value="">Seleccione una red social</option>
                                    <?php foreach ($redesSociales as $valor => $texto): ?>
                                        <option value="<?= htmlspecialchars($valor) ?>" <?= ($empleado['red_social'] === $valor) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($texto) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="red_social_usuario" class="form-label">Usuario/Perfil</label>
                                <input type="text" class="form-control" id="red_social_usuario" name="red_social_usuario"
                                       value="<?= htmlspecialchars($empleado['red_social_usuario']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección Contacto de Emergencia -->
                    <div class="mt-4">
                        <h5><i class="bi bi-exclamation-triangle"></i> Contacto de Emergencia</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="contacto_emergencia_nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre"
                                       value="<?= htmlspecialchars($empleado['contacto_emergencia_nombre']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="contacto_emergencia_parentesco" class="form-label">Parentesco</label>
                                <input type="text" class="form-control" id="contacto_emergencia_parentesco" name="contacto_emergencia_parentesco"
                                       value="<?= htmlspecialchars($empleado['contacto_emergencia_parentesco']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="contacto_emergencia_telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="contacto_emergencia_telefono" name="contacto_emergencia_telefono"
                                       pattern="[0-9]{10,15}" title="10 a 15 dígitos"
                                       value="<?= htmlspecialchars($empleado['contacto_emergencia_telefono']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección Hobbies -->
                    <div class="mt-4">
                        <h5><i class="bi bi-heart"></i> Hobbies e Intereses</h5>
                        <div class="mb-3">
                            <label for="hobbies" class="form-label">Hobbies (separados por comas)</label>
                            <textarea class="form-control" id="hobbies" name="hobbies" rows="2"><?= htmlspecialchars($empleado['hobbies']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Estado del empleado -->
                    <div class="mb-3 form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" <?= $empleado['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Empleado activo</label>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                        <a href="lista_empleados.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">Empleado no encontrado</div>
                <a href="lista_empleados.php" class="btn btn-secondary">Volver a la lista</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Script para manejar mayúsculas y formatos -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Convertir a mayúsculas automáticamente
    document.getElementById('curp').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    document.getElementById('rfc').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Validar solo números para teléfonos y NSS
    document.getElementById('telefono').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    document.getElementById('contacto_emergencia_telefono').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    document.getElementById('nss').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});
</script>