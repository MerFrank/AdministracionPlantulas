<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos
if (function_exists('verificarRol')) {
    verificarRol('admin');
}

// Variables para el encabezado
$titulo = "Puestos";
$encabezado = "Editar Puesto";
$subtitulo = "Modifique la información del puesto";
$active_page = "puestos";

$texto_boton = "Regresar";
$ruta = "lista_puestos.php";

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$puesto = null;
$error = '';
$success = '';

// Obtener ID del puesto de la URL
$id_puesto = isset($_GET['id_puesto']) ? (int)$_GET['id_puesto'] : 0;
if ($id_puesto === 0) {
    header('Location: lista_puestos.php');
    exit();
}

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener datos del puesto
    $sql_puesto = "SELECT * FROM puestos WHERE id_puesto = ?";
    $stmt = $con->prepare($sql_puesto);
    $stmt->execute([$id_puesto]);
    $puesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$puesto) {
        $_SESSION['error_message'] = "Puesto no encontrado.";
        header('Location: lista_puestos.php');
        exit();
    }

} catch (PDOException $e) {
    die("Error al obtener el puesto: " . $e->getMessage());
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido.');
    }

    try {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $nivel_jerarquico = $_POST['nivel_jerarquico'];
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre)) {
            throw new Exception("El nombre del puesto es requerido.");
        }

        $sql_update = "UPDATE puestos 
                       SET nombre = :nombre, descripcion = :descripcion, 
                           nivel_jerarquico = :nivel_jerarquico, activo = :activo
                       WHERE id_puesto = :id_puesto";
        
        $stmt_update = $con->prepare($sql_update);
        $stmt_update->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'nivel_jerarquico' => $nivel_jerarquico,
            'activo' => $activo,
            'id_puesto' => $id_puesto
        ]);

        $_SESSION['success_message'] = 'Puesto actualizado correctamente.';
        header('Location: lista_puestos.php');
        exit();

    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-pencil-square"></i> Editar Puesto: <?= htmlspecialchars($puesto['nombre']) ?></h2>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="puestoForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-3">
                    <label for="nombre" class="form-label required-field">Nombre del Puesto</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100"
                           value="<?= htmlspecialchars($puesto['nombre']) ?>">
                    <div class="invalid-feedback">Por favor ingrese el nombre del puesto.</div>
                </div>
                
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($puesto['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="nivel_jerarquico" class="form-label required-field">Nivel Jerárquico</label>
                    <select class="form-select" id="nivel_jerarquico" name="nivel_jerarquico" required>
                        <option value="">Seleccione un nivel</option>
                        <option value="gerente_general" <?= ($puesto['nivel_jerarquico'] == 'gerente_general') ? 'selected' : '' ?>>Gerente General</option>
                        <option value="supervisor" <?= ($puesto['nivel_jerarquico'] == 'supervisor') ? 'selected' : '' ?>>Supervisor</option>
                        <option value="responsable" <?= ($puesto['nivel_jerarquico'] == 'responsable') ? 'selected' : '' ?>>Responsable</option>
                        <option value="operativo" <?= ($puesto['nivel_jerarquico'] == 'operativo') ? 'selected' : '' ?>>Operativo</option>
                    </select>
                    <div class="invalid-feedback">Seleccione un nivel jerárquico.</div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" <?= ($puesto['activo'] == 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Puesto activo</label>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Actualizar Puesto
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