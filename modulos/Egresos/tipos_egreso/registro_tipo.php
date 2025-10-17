<?php
require_once(__DIR__ . '/../../../includes/validacion_session.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);



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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $datos = [
            'nombre' => htmlspecialchars(trim($_POST['nombre'])),
            'descripcion' => !empty($_POST['descripcion']) ? htmlspecialchars(trim($_POST['descripcion'])) : null
        ];

        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");

        $sql = "INSERT INTO tipos_egreso (nombre, descripcion) VALUES (:nombre, :descripcion)";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            if (isset($_POST['redireccion'])) {
                header('Location: ' . $_POST['redireccion']);
            } else {
                $_SESSION['success_message'] = 'Tipo de egreso registrado correctamente';
                header('Location: lista_tipos.php');
            }
            exit;
        } else {
            throw new Exception("Error al registrar tipo de egreso");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$ruta = "../dashboard_egresos.php";
$texto_boton = "Regresar";
if (!isset($_GET['modal'])) {
    require __DIR__ . '/../../../includes/header.php';
}
?>

<main class="container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-tag"></i> Registrar Tipo de Egreso</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="formTipoEgreso">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <?php if (isset($_GET['redireccion'])): ?>
                    <input type="hidden" name="redireccion" value="<?= htmlspecialchars($_GET['redireccion']) ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label">Nombre del Tipo</label>
                    <input type="text" class="form-control" name="nombre" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"></textarea>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php if (!isset($_GET['modal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formTipoEgreso').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
});
</script>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
<?php endif; ?>