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

// Obtener cuentas bancarias activas
$cuentas_bancarias = $con->query("
    SELECT id_cuenta, nombre, banco, numero 
    FROM cuentas_bancarias 
    WHERE activo = 1
    ORDER BY nombre
")->fetchAll();

$ventas_pendientes = $con->query("
    SELECT np.id_notaPedido, c.nombre_Cliente, np.saldo_pendiente
    FROM notaspedidos np
    JOIN clientes c ON np.id_cliente = c.id_cliente
    WHERE np.tipo_pago = 'credito'
      AND np.saldo_pendiente > 0
      AND np.estado IN ('pendiente', 'parcial')
    ORDER BY np.id_notaPedido DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario de abono
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $con->beginTransaction();
        
        // Validar datos
        if (empty($_POST['id_notaPedido'])) {
            throw new Exception("No se especificó la venta");
        }
        
        if (empty($_POST['monto_pago']) || $_POST['monto_pago'] <= 0) {
            throw new Exception("Ingrese un monto válido");
        }
        
        if (empty($_POST['metodo_pago'])) {
            throw new Exception("Seleccione un método de pago");
        }
        
        $metodo_pago = $_POST['metodo_pago'];
        
        // Validar cuenta bancaria (ahora siempre requerida)
        if (empty($_POST['id_cuenta'])) {
            throw new Exception("Seleccione una cuenta bancaria");
        }
        $id_cuenta = (int)$_POST['id_cuenta'];
        
        // Obtener información de la venta
        $stmt = $con->prepare("
            SELECT np.id_notaPedido, np.id_cliente, np.total, np.saldo_pendiente, c.nombre_Cliente
            FROM notaspedidos np
            JOIN clientes c ON np.id_cliente = c.id_cliente
            WHERE np.id_notaPedido = ? AND np.tipo_pago = 'credito'
        ");
        $stmt->execute([$_POST['id_notaPedido']]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$venta) {
            throw new Exception("Venta no encontrada o no es a crédito");
        }
        
        $monto_pago = (float)$_POST['monto_pago'];
        $nuevo_saldo = $venta['saldo_pendiente'] - $monto_pago;
        
        if ($nuevo_saldo < 0) {
            throw new Exception("El monto excede el saldo pendiente");
        }
        
        $stmt_update_cuenta = $con->prepare("
            UPDATE cuentas_bancarias 
            SET saldo_actual = saldo_actual + :monto 
            WHERE id_cuenta = :id_cuenta
        ");
        $stmt_update_cuenta->execute([
            ':monto' => $monto_pago,
            ':id_cuenta' => $id_cuenta
        ]);
        
        // Verificar que se actualizó correctamente
        if ($stmt_update_cuenta->rowCount() === 0) {
            throw new Exception("No se pudo actualizar el saldo de la cuenta bancaria.");
        }
        
        // Registrar el pago en PagosVentas (versión corregida sin id_cuenta)
        $stmt = $con->prepare("
            INSERT INTO pagosventas (
                id_notaPedido, monto, fecha, metodo_pago, 
                referencia, observaciones, id_empleado
            ) VALUES (
                ?, ?, NOW(), ?, ?, ?, ?
            )
        ");
        
        $stmt->execute([
            $venta['id_notaPedido'],
            $monto_pago,
            $metodo_pago,
            'Abono a cuenta', // referencia
            htmlspecialchars(trim($_POST['comentarios'] ?? '')),
            $_SESSION['id_empleado'] ?? null // ID del empleado desde sesión
        ]);
        
        // Registrar relación pago-cuenta en otra tabla si es necesario
        // (esto es opcional, dependiendo de tus requisitos)
        if(in_array($metodo_pago, ['transferencia', 'deposito'])) {
            $stmt = $con->prepare("
                INSERT INTO pagos_cuentas (
                    id_pago, id_cuenta
                ) VALUES (
                    LAST_INSERT_ID(), ?
                )
            ");
            $stmt->execute([$id_cuenta]);
        }
        
        // Actualizar saldo pendiente en la nota de pedido
        $estado = $nuevo_saldo > 0 ? 'parcial' : 'completado';
        
        $stmt = $con->prepare("
            UPDATE notaspedidos 
            SET saldo_pendiente = ?, estado = ?
            WHERE id_notaPedido = ?
        ");
        $stmt->execute([$nuevo_saldo, $estado, $venta['id_notaPedido']]);
        
        $con->commit();
        
        $success = "Pago registrado correctamente. Saldo pendiente actual: $" . number_format($nuevo_saldo, 2);
        
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

$titulo = 'Registrar Abono';
$ruta = "dashboard_ventas.php";
$texto_boton = "";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-cash-coin"></i> Registrar Abono</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post" id="abonoForm">
                <div class="row g-3">
                    <!-- Selección de venta -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Venta a crédito <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_notaPedido" id="selectVenta" required>
                                <option value="">Seleccione una venta...</option>
                                <?php foreach ($ventas_pendientes as $venta): ?>
                                    <option value="<?= $venta['id_notaPedido'] ?>" 
                                        data-saldo="<?= $venta['saldo_pendiente'] ?>">
                                        #<?= $venta['id_notaPedido'] ?> - <?= htmlspecialchars($venta['nombre_Cliente']) ?> 
                                        (Saldo: $<?= number_format($venta['saldo_pendiente'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Monto del pago -->
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Monto del pago <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="monto_pago" id="montoPago" 
                                       step="0.01" min="0.01" required>
                            </div>
                            <small id="saldoRestante" class="text-muted"></small>
                        </div>
                    </div>
                    
                    <!-- Método de pago -->
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Método de pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="metodo_pago" id="metodoPago" required>
                                <option value="">Seleccione...</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Cuenta bancaria (siempre visible) -->
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Cuenta bancaria <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_cuenta" required>
                                <option value="">Seleccione una cuenta...</option>
                                <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>">
                                        <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Comentarios -->
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Comentarios (opcional)</label>
                            <textarea class="form-control" name="comentarios" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="lista_ventas.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Registrar Abono
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const selectVenta = $('#selectVenta');
    const montoPago = $('#montoPago');
    const saldoRestante = $('#saldoRestante');
    
    // Actualizar máximo permitido para el pago
    selectVenta.on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const saldo = selectedOption.data('saldo');
        
        if (selectedOption.val()) {
            // Configurar máximo en el input de monto
            montoPago.attr('max', saldo);
            saldoRestante.text(`Saldo pendiente: $${saldo.toFixed(2)}`);
        } else {
            montoPago.attr('max', '');
            saldoRestante.text('');
        }
    });
    
    // Validar que el monto no exceda el saldo
    montoPago.on('input', function() {
        const max = parseFloat($(this).attr('max')) || Infinity;
        const value = parseFloat($(this).val()) || 0;
        
        if (value > max) {
            $(this).val(max.toFixed(2));
            saldoRestante.text('El monto no puede exceder el saldo pendiente');
            saldoRestante.removeClass('text-muted').addClass('text-danger');
        } else {
            const nuevoSaldo = max - value;
            saldoRestante.text(`Saldo después del pago: $${nuevoSaldo.toFixed(2)}`);
            saldoRestante.removeClass('text-danger').addClass('text-muted');
        }
    });
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>