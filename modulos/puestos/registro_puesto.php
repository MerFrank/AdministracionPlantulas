<?php

require_once __DIR__ . '/../../includes/config.php';
// Variables para el encabezado
$titulo = "Registrar Puesto";
$encabezado = "Registrar Puesto";
$subtitulo = "Registra nuevos puestos en la empresa.";

//Botón
$texto_boton = "";
$ruta = "dashboard_puestos.php";

require_once __DIR__ . '/../../includes/header.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Registrar Nuevo Puesto";
$subtitulo = "Complete el formulario para registrar un nuevo puesto";
$active_page = "puestos";

// Definir la variable $ruta para el botón de volver
$ruta = BASE_URL . '/vistas/puestos/dashboard_puestos.php';
$texto_boton = "Volver a Puestos";

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        // Sanitizar y validar datos
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $nivel_jerarquico = $_POST['nivel_jerarquico'];
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre)) {
            throw new Exception("El nombre del puesto es requerido");
        }

        // Insertar en BD
        $sql = "INSERT INTO puestos (nombre, descripcion, nivel_jerarquico, activo) 
                VALUES (:nombre, :descripcion, :nivel_jerarquico, :activo)";

        $db = new Database();
        $con = $db->conectar();
        $stmt = $con->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'nivel_jerarquico' => $nivel_jerarquico,
            'activo' => $activo
        ]);

        $_SESSION['success_message'] = 'Puesto registrado correctamente';
        header('Location: lista_puestos.php');
        exit();

    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-briefcase"></i> Registrar Nuevo Puesto</h2>
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

            <form method="post" id="puestoForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="nombre" class="form-label required-field">Nombre del Puesto</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100"
                                   placeholder="Ej: Gerente de Ventas, Asistente Administrativo"
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor ingrese el nombre del puesto</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                      placeholder="Descripción de las responsabilidades del puesto"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="nivel_jerarquico" class="form-label required-field">Nivel Jerárquico</label>
                            <select class="form-select" id="nivel_jerarquico" name="nivel_jerarquico" required>
                                <option value="">Seleccione un nivel</option>
                                <option value="gerente_general">Gerente General</option>
                                <option value="sub_gerente">Sub Gerente</option>
                                <option value="responsable_de_area">Responsable de area</option>
                                <option value="operativo">Operativo</option>
                            </select>
                            <div class="invalid-feedback">Seleccione un nivel jerárquico</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" checked>
                            <label class="form-check-label" for="activo">Puesto activo</label>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Guardar Puesto
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario
    const form = document.getElementById('puestoForm');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
</script>