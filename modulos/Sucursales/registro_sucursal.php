<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);



require_once __DIR__ . '/../../includes/config.php';

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
            'direccion' => htmlspecialchars(trim($_POST['direccion'])),
            'telefono' => !empty($_POST['telefono']) ? preg_replace('/[^0-9]/', '', $_POST['telefono']) : null,
            'responsable' => !empty($_POST['responsable']) ? htmlspecialchars(trim($_POST['responsable'])) : null
        ];

        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");

        $sql = "INSERT INTO sucursales (nombre, direccion, telefono, responsable) 
                VALUES (:nombre, :direccion, :telefono, :responsable)";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            $_SESSION['success_message'] = 'Sucursal registrada correctamente';
            header('Location: lista_sucursales.php');
            exit;
        } else {
            throw new Exception("Error al registrar sucursal");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$ruta = "dashboard_sucursales.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-shop"></i> Registrar Sucursal</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" name="direccion" rows="2" required></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Responsable</label>
                            <input type="text" class="form-control" name="responsable">
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_sucursales.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Registrar Sucursal
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>