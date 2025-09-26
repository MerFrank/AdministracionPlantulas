<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos del tipo a editar
$id_tipo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = null;

if ($id_tipo > 0) {
    $stmt = $con->prepare("SELECT * FROM tipos_egreso WHERE id_tipo = ?");
    $stmt->execute([$id_tipo]);
    $tipo = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$tipo) {
    $_SESSION['error_message'] = 'Tipo de egreso no encontrado';
    header('Location: lista_tipos.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $datos = [
            'id_tipo' => $id_tipo,
            'nombre' => htmlspecialchars(trim($_POST['nombre'])),
            'descripcion' => !empty($_POST['descripcion']) ? htmlspecialchars(trim($_POST['descripcion'])) : null
        ];

        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");

        $sql = "UPDATE tipos_egreso SET 
                nombre = :nombre, 
                descripcion = :descripcion 
                WHERE id_tipo = :id_tipo";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            $_SESSION['success_message'] = 'Tipo de egreso actualizado correctamente';
            header('Location: lista_tipos.php');
            exit;
        } else {
            throw new Exception("Error al actualizar tipo de egreso");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$ruta = "../dashboard_egresos.php";
$texto_boton = "Regresar";
require __DIR__ . '/../../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-tags"></i> Editar Tipo de Egreso</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-3">
                    <label class="form-label">Nombre del Tipo</label>
                    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($tipo['nombre']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars($tipo['descripcion']??'') ?></textarea>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_tipos.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
});
</script>