<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Obtener datos de la cuenta a editar
$id_cuenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cuenta = null;

if ($id_cuenta > 0) {
    $stmt = $con->prepare("SELECT * FROM cuentas_bancarias WHERE id_cuenta = ?");
    $stmt->execute([$id_cuenta]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$cuenta) {
    $_SESSION['error_message'] = 'Cuenta no encontrada';
    header('Location: lista_cuentas.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $datos = [
            'id_cuenta' => $id_cuenta,
            'nombre' => htmlspecialchars(trim($_POST['nombre'])),
            'banco' => htmlspecialchars(trim($_POST['banco'])),
            'tipo_cuenta' => $_POST['tipo_cuenta'],
            'numero' => htmlspecialchars(trim($_POST['numero'])),
            'clabe' => !empty($_POST['clabe']) ? htmlspecialchars(trim($_POST['clabe'])) : null
        ];

        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");
        if (empty($datos['banco'])) throw new Exception("Banco requerido");
        if (empty($datos['numero'])) throw new Exception("Número de cuenta requerido");

        $sql = "UPDATE cuentas_bancarias SET 
                nombre = :nombre, 
                banco = :banco, 
                tipo_cuenta = :tipo_cuenta, 
                numero = :numero, 
                clabe = :clabe 
                WHERE id_cuenta = :id_cuenta";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            $_SESSION['success_message'] = 'Cuenta actualizada correctamente';
            header('Location: lista_cuentas.php');
            exit;
        } else {
            throw new Exception("Error al actualizar cuenta");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-bank"></i> Editar Cuenta Bancaria</h2>
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
                            <label class="form-label">Nombre de la Cuenta</label>
                            <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($cuenta['nombre']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Banco</label>
                            <input type="text" class="form-control" name="banco" value="<?= htmlspecialchars($cuenta['banco']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Cuenta</label>
                            <select class="form-select" name="tipo_cuenta" required>
                                <option value="Cheques" <?= $cuenta['tipo_cuenta'] === 'Cheques' ? 'selected' : '' ?>>Cuenta de Cheques</option>
                                <option value="Ahorros" <?= $cuenta['tipo_cuenta'] === 'Ahorros' ? 'selected' : '' ?>>Cuenta de Ahorros</option>
                                <option value="Inversión" <?= $cuenta['tipo_cuenta'] === 'Inversión' ? 'selected' : '' ?>>Cuenta de Inversión</option>
                                <option value="Nomina" <?= $cuenta['tipo_cuenta'] === 'Nomina' ? 'selected' : '' ?>>Cuenta Nómina</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Número de Cuenta</label>
                            <input type="text" class="form-control" name="numero" value="<?= htmlspecialchars($cuenta['numero']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CLABE Interbancaria</label>
                            <input type="text" class="form-control" name="clabe" value="<?= htmlspecialchars($cuenta['clabe']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Saldo Actual</label>
                            <input type="text" class="form-control" value="$<?= number_format($cuenta['saldo_actual'], 2) ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_cuentas.php" class="btn btn-secondary">
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

<?php require __DIR__ . '/../../includes/footer.php'; ?>

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