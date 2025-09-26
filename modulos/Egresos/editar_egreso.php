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

// Obtener datos del egreso a editar
$id_egreso = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$egreso = null;

if ($id_egreso > 0) {
    $stmt = $con->prepare("SELECT * FROM egresos WHERE id_egreso = ?");
    $stmt->execute([$id_egreso]);
    $egreso = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$egreso) {
    $_SESSION['error_message'] = 'Egreso no encontrado';
    header('Location: lista_egresos.php');
    exit;
}

// Cargar datos para los select
$proveedores = $con->query("SELECT id_proveedor, nombre_proveedor AS nombre FROM proveedores WHERE activo = 1 ORDER BY nombre_proveedor")->fetchAll();
$sucursales = $con->query("SELECT id_sucursal, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$tiposEgreso = $con->query("SELECT id_tipo, nombre FROM tipos_egreso WHERE activo = 1 ORDER BY nombre")->fetchAll();
$cuentas = $con->query("SELECT id_cuenta, nombre, numero, saldo_actual FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $con->beginTransaction();

        $datos = [
            'id_egreso' => $id_egreso,
            'fecha' => $_POST['fecha'],
            'id_tipo_egreso' => (int)$_POST['id_tipo_egreso'],
            'id_proveedor' => ($_POST['id_proveedor'] != '') ? (int)$_POST['id_proveedor'] : null,
            'id_sucursal' => (int)$_POST['id_sucursal'],
            'id_cuenta' => ($_POST['metodo_pago'] === 'cuenta') ? (int)$_POST['id_cuenta'] : null,
            'monto' => (float)$_POST['monto'],
            'concepto' => htmlspecialchars(trim($_POST['concepto'])),
            'metodo_pago' => $_POST['metodo_pago'],
            'comprobante' => $_POST['comprobante'],
            'observaciones' => !empty($_POST['observaciones']) ? htmlspecialchars(trim($_POST['observaciones'])) : null
        ];

        // Validaciones
        if (empty($datos['fecha'])) throw new Exception("Fecha requerida");
        if ($datos['id_tipo_egreso'] <= 0) throw new Exception("Seleccione tipo de egreso");
        if ($datos['id_sucursal'] <= 0) throw new Exception("Seleccione sucursal");
        if ($datos['monto'] <= 0) throw new Exception("Monto debe ser positivo");
        if (empty($datos['concepto'])) throw new Exception("Concepto requerido");
        if ($datos['metodo_pago'] === 'cuenta' && empty($datos['id_cuenta'])) {
            throw new Exception("Debe seleccionar una cuenta bancaria para este método de pago");
        }

        // Obtener el egreso actual para comparar cambios
        $stmt = $con->prepare("SELECT * FROM egresos WHERE id_egreso = ?");
        $stmt->execute([$id_egreso]);
        $egreso_actual = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Actualizar el egreso
        $sql = "UPDATE egresos SET 
                fecha = :fecha,
                id_tipo_egreso = :id_tipo_egreso,
                id_proveedor = :id_proveedor,
                id_sucursal = :id_sucursal,
                id_cuenta = :id_cuenta,
                monto = :monto,
                concepto = :concepto,
                metodo_pago = :metodo_pago,
                comprobante = :comprobante,
                observaciones = :observaciones
                WHERE id_egreso = :id_egreso";
        
        $stmt = $con->prepare($sql);
        $stmt->execute($datos);

        // 2. Manejar cambios en el método de pago y montos
        if ($egreso_actual['metodo_pago'] === 'cuenta' && $egreso_actual['id_cuenta']) {
            // Revertir el monto anterior en la cuenta original
            $sqlRevertir = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual + :monto 
                           WHERE id_cuenta = :id_cuenta";
            $stmtRevertir = $con->prepare($sqlRevertir);
            $stmtRevertir->execute([
                'monto' => $egreso_actual['monto'],
                'id_cuenta' => $egreso_actual['id_cuenta']
            ]);
        }

        if ($datos['metodo_pago'] === 'cuenta' && $datos['id_cuenta']) {
            // Aplicar el nuevo monto a la cuenta seleccionada
            $sqlAplicar = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual - :monto 
                          WHERE id_cuenta = :id_cuenta";
            $stmtAplicar = $con->prepare($sqlAplicar);
            $stmtAplicar->execute([
                'monto' => $datos['monto'],
                'id_cuenta' => $datos['id_cuenta']
            ]);
        }

        $con->commit();

        $_SESSION['success_message'] = 'Egreso actualizado correctamente';
        header('Location: lista_egresos.php');
        exit;
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}
$ruta = "dashboard_egresos.php";
$texto_boton = "Regresar";
require __DIR__ . '/../../includes/header.php';
?>

<!-- Resto del código HTML permanece igual -->

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-cash-coin"></i> Editar Egreso</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <!-- Sección Datos Básicos -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($egreso['fecha']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Egreso</label>
                            <select class="form-select" name="id_tipo_egreso" id="tipoEgreso" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($tiposEgreso as $tipo): ?>
                                    <option value="<?= $tipo['id_tipo'] ?>" <?= $tipo['id_tipo'] == $egreso['id_tipo_egreso'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="proveedorContainer" style="<?= $egreso['id_proveedor'] ? 'display:block' : 'display:none' ?>">
                            <label class="form-label">Proveedor</label>
                            <select class="form-select" name="id_proveedor" id="selectProveedor">
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= $prov['id_proveedor'] ?>" <?= $prov['id_proveedor'] == $egreso['id_proveedor'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Sección Monto y Pago -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Sucursal</label>
                            <select class="form-select" name="id_sucursal" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= $suc['id_sucursal'] ?>" <?= $suc['id_sucursal'] == $egreso['id_sucursal'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($suc['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <input type="number" class="form-control" name="monto" step="0.01" min="0.01" value="<?= htmlspecialchars($egreso['monto']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago" id="metodoPago" required>
                                <option value="efectivo" <?= $egreso['metodo_pago'] == 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="caja_chica" <?= $egreso['metodo_pago'] == 'caja_chica' ? 'selected' : '' ?>>Caja Chica</option>
                                <option value="cuenta" <?= $egreso['metodo_pago'] == 'cuenta' ? 'selected' : '' ?>>Desde Cuenta</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="cuentaContainer" style="<?= $egreso['metodo_pago'] == 'cuenta' ? 'display:block' : 'display:none' ?>">
                            <label class="form-label">Cuenta Bancaria</label>
                            <select class="form-select" name="id_cuenta" id="selectCuenta">
                                <option value="">Seleccione...</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>" <?= $cuenta['id_cuenta'] == $egreso['id_cuenta'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cuenta['nombre']) ?> (<?= htmlspecialchars($cuenta['numero']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Sección Concepto y Comprobante -->
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Concepto</label>
                            <textarea class="form-control" name="concepto" rows="2" required><?= htmlspecialchars($egreso['concepto']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">N° Comprobante</label>
                            <input type="text" class="form-control" name="comprobante" value="<?= htmlspecialchars($egreso['comprobante']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2"><?= htmlspecialchars($egreso['observaciones']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_egresos.php" class="btn btn-secondary">
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
    // Mostrar/ocultar campo de proveedor según tipo de egreso
    const tipoEgreso = document.getElementById('tipoEgreso');
    const proveedorContainer = document.getElementById('proveedorContainer');
    
    tipoEgreso.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const requiereProveedor = selectedOption.getAttribute('data-requiere-proveedor') === '1';
        proveedorContainer.style.display = requiereProveedor ? 'block' : 'none';
        if (!requiereProveedor) {
            document.getElementById('selectProveedor').value = '';
        }
    });
    
    // Mostrar/ocultar campos de pago según método seleccionado
    const metodoPago = document.getElementById('metodoPago');
    const cuentaContainer = document.getElementById('cuentaContainer');
    
    metodoPago.addEventListener('change', function() {
        const esCuenta = this.value === 'cuenta';
        cuentaContainer.style.display = esCuenta ? 'block' : 'none';
        
        if (!esCuenta) {
            document.getElementById('selectCuenta').value = '';
        }
    });
    
    // Validación del formulario
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
    
    // Disparar eventos change al cargar la página
    tipoEgreso.dispatchEvent(new Event('change'));
    metodoPago.dispatchEvent(new Event('change'));
});
</script>