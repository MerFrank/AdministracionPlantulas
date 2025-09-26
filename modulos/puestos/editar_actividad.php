<?php
require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos si es necesario
// if (!isset($_SESSION['usuario_id'])) {
//     header('Location: /login.php');
//     exit;
// }

// Obtener ID de la actividad a editar
$id_actividad = isset($_GET['id_actividad']) ? (int)$_GET['id_actividad'] : 0;

if ($id_actividad <= 0) {
    header('Location: lista_actividades.php');
    exit;
}

$error = '';
$success = '';
$actividad = null;

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener datos de la actividad
    $stmt = $con->prepare("
        SELECT id_actividad, nombre, pago_extra, descripcion, activo
        FROM actividades_extras 
        WHERE id_actividad = ?
    ");
    $stmt->execute([$id_actividad]);
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        throw new Exception("Actividad no encontrada");
    }
    
    // Procesar el formulario de edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar datos
        if (empty($_POST['nombre'])) {
            throw new Exception("El nombre de la actividad es obligatorio");
        }
        
        $nombre = trim($_POST['nombre']);
        $pago_extra = isset($_POST['pago_extra']) ? (float)$_POST['pago_extra'] : 0.00;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validar que el pago extra no sea negativo
        if ($pago_extra < 0) {
            throw new Exception("El pago extra no puede ser negativo");
        }
        
        // Actualizar en la base de datos
        $stmt = $con->prepare("
            UPDATE actividades_extras 
            SET nombre = ?, pago_extra = ?, descripcion = ?, activo = ?
            WHERE id_actividad = ?
        ");
        
        $stmt->execute([$nombre, $pago_extra, $descripcion, $activo, $id_actividad]);
        
        $success = "Actividad actualizada correctamente";
        
        // Actualizar los datos locales
        $actividad['nombre'] = $nombre;
        $actividad['pago_extra'] = $pago_extra;
        $actividad['descripcion'] = $descripcion;
        $actividad['activo'] = $activo;
        
    }
    
} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Variables para el encabezado
$titulo = "Editar Actividad Extra";
$encabezado = "Editar Actividad: " . ($actividad['nombre'] ?? '');
$subtitulo = "Modifique los datos de la actividad extra";
$active_page = "actividades";
$texto_boton = "Regresar";
$ruta = "actividades_extras.php";

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-warning text-white">
                    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Actividad Extra</h2>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($actividad): ?>
                    <form method="post" id="formEditarActividad">
                        <div class="row g-3">
                            <!-- Nombre de la actividad -->
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la actividad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nombre" 
                                           value="<?= htmlspecialchars($actividad['nombre']) ?>" 
                                           required maxlength="100"
                                           placeholder="Ej: Horas extras, Trabajo en fin de semana, etc.">
                                    <div class="form-text">Nombre descriptivo de la actividad extra.</div>
                                </div>
                            </div>
                            
                            <!-- Pago extra -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Pago extra ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="pago_extra" 
                                               value="<?= htmlspecialchars($actividad['pago_extra']) ?>" 
                                               step="0.01" min="0" 
                                               placeholder="0.00">
                                    </div>
                                    <div class="form-text">Monto adicional por esta actividad.</div>
                                </div>
                            </div>
                            
                            <!-- Estado -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estado</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" 
                                               id="activo" <?= $actividad['activo'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="activo">
                                            Activo
                                        </label>
                                    </div>
                                    <div class="form-text">Desactive si no quiere que esta actividad esté disponible.</div>
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion" rows="3" 
                                              placeholder="Descripción detallada de la actividad, condiciones, requisitos, etc."><?= htmlspecialchars($actividad['descripcion']) ?></textarea>
                                    <div class="form-text">Información adicional sobre la actividad.</div>
                                </div>
                            </div>
                            
                            <!-- Botones -->
                            <div class="col-12">
                                <div class="d-flex justify-content-end">
                                    <a href="lista_actividades.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancelar
                                    </a>
                                    
                                        <a href="eliminar_actividad.php?id_actividad=<?= $id_actividad ?>" 
                                           class="btn btn-secondary"
                                           onclick="return confirm('¿Está seguro de eliminar esta actividad?')">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            No se pudo cargar la información de la actividad.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Validación adicional del formulario
document.getElementById('formEditarActividad').addEventListener('submit', function(e) {
    const pagoExtra = parseFloat(document.querySelector('input[name="pago_extra"]').value);
    
    if (pagoExtra < 0) {
        e.preventDefault();
        alert('El pago extra no puede ser negativo');
        return false;
    }
    
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
?>