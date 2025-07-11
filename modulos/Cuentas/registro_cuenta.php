<?php

// Incluir archivo de configuración de la base de datos
require_once __DIR__ . '/../../includes/config.php';

// Instanciar base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Generar token CSRF para protección del formulario
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
            'banco' => htmlspecialchars(trim($_POST['banco'])),
            'tipo_cuenta' => $_POST['tipo_cuenta'],
            'numero' => htmlspecialchars(trim($_POST['numero'])),
            'clabe' => !empty($_POST['clabe']) ? htmlspecialchars(trim($_POST['clabe'])) : null,
            'saldo_inicial' => (float)$_POST['saldo_inicial']
        ];

        if (empty($datos['nombre'])) throw new Exception("Nombre requerido");
        if (empty($datos['banco'])) throw new Exception("Banco requerido");
        if (empty($datos['numero'])) throw new Exception("Número de cuenta requerido");

        $sql = "INSERT INTO cuentas_bancarias (nombre, banco, tipo_cuenta, numero, clabe, saldo_inicial, saldo_actual) 
                VALUES (:nombre, :banco, :tipo_cuenta, :numero, :clabe, :saldo_inicial, :saldo_inicial)";
        
        $stmt = $con->prepare($sql);
        if ($stmt->execute($datos)) {
            $_SESSION['success_message'] = 'Cuenta registrada correctamente';
            header('Location: lista_cuentas.php');
            exit;
        } else {
            throw new Exception("Error al registrar cuenta");
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
            <h2><i class="bi bi-bank"></i> Registrar Cuenta Bancaria</h2>
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
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Banco</label>
                            <input type="text" class="form-control" name="banco" required>
                        </div>
                        
                         <div class="mb-3">
                            <label class="form-label">Tipo de Cuenta</label>
                            <select class="form-select" name="tipo_cuenta" required>
                                <option value="Cheques">Cuenta de Cheques</option>
                                <option value="Ahorros">Cuenta de Ahorros</option>
                                <option value="Inversión">Cuenta de Inversión</option>
                                <option value="Efectivo">Efectivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Número de Cuenta</label>
                            <input type="text" class="form-control" name="numero" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CLABE Interbancaria</label>
                            <input type="text" class="form-control" name="clabe">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Saldo Inicial</label>
                            <input type="number" class="form-control" name="saldo_inicial" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_cuentas.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Registrar Cuenta
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