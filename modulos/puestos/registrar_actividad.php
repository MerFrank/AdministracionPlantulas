<?php
require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos si es necesario
// if (!isset($_SESSION['usuario_id'])) {
//     header('Location: /login.php');
//     exit;
// }

// Procesar el formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $con = $db->conectar();
        
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
        
        // Insertar en la base de datos
        $stmt = $con->prepare("
            INSERT INTO actividades_extras (nombre, pago_extra, descripcion, activo)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$nombre, $pago_extra, $descripcion, $activo]);
        
        $success = "Actividad extra registrada correctamente";
        
        // Limpiar el formulario después de guardar
        $_POST = [];
        
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Variables para el encabezado
$titulo = "Actividades Extras";
$encabezado = "Registrar Nueva Actividad Extra";
$subtitulo = "Complete el formulario para registrar una nueva actividad";
$active_page = "actividades";
$texto_boton = "Regresar";
$ruta = "actividades_extras.php"; 

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Registrar Actividad Extra</h2>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" id="formActividad">
                        <div class="row g-3">
                            <!-- Nombre de la actividad -->
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la actividad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nombre" 
                                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
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
                                               value="<?= htmlspecialchars($_POST['pago_extra'] ?? '0.00') ?>" 
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
                                               id="activo" <?= isset($_POST['activo']) && $_POST['activo'] ? 'checked' : 'checked' ?>>
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
                                              placeholder="Descripción detallada de la actividad, condiciones, requisitos, etc."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                                    <div class="form-text">Información adicional sobre la actividad.</div>
                                </div>
                            </div>
                            
                            <!-- Botones -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="lista_actividades.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Registrar Actividad
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Validación adicional del formulario
document.getElementById('formActividad').addEventListener('submit', function(e) {
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