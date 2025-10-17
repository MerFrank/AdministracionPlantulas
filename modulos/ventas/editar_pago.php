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

// Obtener ID del pago
$id_pago = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pago <= 0) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener información del pago
$sql_pago = $con->prepare("SELECT p.*, np.id_notaPedido 
                          FROM pagosventas p 
                          INNER JOIN notaspedidos np ON p.id_notaPedido = np.id_notaPedido 
                          WHERE p.id_pago = ?");
$sql_pago->execute([$id_pago]);
$pago = $sql_pago->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener lista de cuentas bancarias para el dropdown
$sql_cuentas = $con->prepare("SELECT id_cuenta, nombre, numero FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre");
$sql_cuentas->execute();
$cuentas = $sql_cuentas->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
    $metodo_pago = htmlspecialchars(trim($_POST['metodo_pago']));
    $referencia = htmlspecialchars(trim($_POST['referencia']));
    $observaciones = htmlspecialchars(trim($_POST['observaciones']));
    $id_cuenta = filter_input(INPUT_POST, 'id_cuenta', FILTER_VALIDATE_INT);
    
    if ($monto && $monto > 0 && $id_cuenta) {
        try {
            // Validar que el nuevo monto no exceda el total de la venta
            $sql_saldo = $con->prepare("SELECT SUM(monto) as total_pagado FROM pagosventas WHERE id_notaPedido = ? AND id_pago != ?");
            $sql_saldo->execute([$pago['id_notaPedido'], $id_pago]);
            $total_pagado_sin_este = $sql_saldo->fetch(PDO::FETCH_ASSOC)['total_pagado'];
            $total_pagado_sin_este = $total_pagado_sin_este ? $total_pagado_sin_este : 0;

            $sql_total_venta = $con->prepare("SELECT total FROM notaspedidos WHERE id_notaPedido = ?");
            $sql_total_venta->execute([$pago['id_notaPedido']]);
            $total_venta = $sql_total_venta->fetch(PDO::FETCH_ASSOC)['total'];

            $nuevo_total_pagado = $total_pagado_sin_este + $monto;

            if ($nuevo_total_pagado > $total_venta) {
                $error = "El monto excede el total de la venta. Saldo máximo permitido: $" . number_format($total_venta - $total_pagado_sin_este, 2);
            } else {
                // Iniciar transacción para asegurar consistencia
                $con->beginTransaction();
                
                // 1. Obtener información actual del pago
                $sql_old_data = $con->prepare("SELECT monto, id_cuenta FROM pagosventas WHERE id_pago = ?");
                $sql_old_data->execute([$id_pago]);
                $old_data = $sql_old_data->fetch(PDO::FETCH_ASSOC);
                
                // 2. Revertir el monto anterior en la cuenta bancaria original
                if ($old_data['id_cuenta']) {
                    $sql_revertir = $con->prepare("UPDATE cuentas_bancarias SET saldo_actual = saldo_actual - ? WHERE id_cuenta = ?");
                    $sql_revertir->execute([$old_data['monto'], $old_data['id_cuenta']]);
                }
                
                // 3. Aplicar el nuevo monto a la nueva cuenta
                $sql_aplicar = $con->prepare("UPDATE cuentas_bancarias SET saldo_actual = saldo_actual + ? WHERE id_cuenta = ?");
                $sql_aplicar->execute([$monto, $id_cuenta]);
                
                // 4. Actualizar el pago
                $sql_update = $con->prepare("UPDATE pagosventas 
                                            SET monto = ?, metodo_pago = ?, referencia = ?, 
                                            observaciones = ?, id_cuenta = ?
                                            WHERE id_pago = ?");
                $sql_update->execute([$monto, $metodo_pago, $referencia, $observaciones, $id_cuenta, $id_pago]);
                
                // 5. Actualizar el estado de la venta
                actualizarEstadoVenta($pago['id_notaPedido'], $con);
                
                $con->commit();
                
                $_SESSION['success'] = "Pago actualizado correctamente";
                header("Location: listar_pagosventa.php?id=" . $pago['id_notaPedido']);
                exit;
            }
        } catch (PDOException $e) {
            $con->rollBack();
            $error = "Error al actualizar el pago: " . $e->getMessage();
        }
    } else {
        $error = "Datos inválidos";
    }
}

function actualizarEstadoVenta($id_venta, $con) {
    // Calcular total pagado
    $sql_pagos = $con->prepare("SELECT SUM(monto) as total_pagado FROM pagosventas WHERE id_notaPedido = ?");
    $sql_pagos->execute([$id_venta]);
    $total_pagado_result = $sql_pagos->fetch(PDO::FETCH_ASSOC);
    $total_pagado = $total_pagado_result ? $total_pagado_result['total_pagado'] : 0;
    
    // Obtener total de la venta
    $sql_venta = $con->prepare("SELECT total FROM notaspedidos WHERE id_notaPedido = ?");
    $sql_venta->execute([$id_venta]);
    $total_venta_result = $sql_venta->fetch(PDO::FETCH_ASSOC);
    $total_venta = $total_venta_result ? $total_venta_result['total'] : 0;
    
    // Calcular saldo pendiente
    $saldo_pendiente = $total_venta - $total_pagado;
    
    // Determinar nuevo estado
    if ($total_pagado >= $total_venta) {
        $nuevo_estado = 'Pagado';
    } elseif ($total_pagado > 0) {
        $nuevo_estado = 'Pendiente';
    } else {
        $nuevo_estado = 'Pendiente';
    }
    
    // Actualizar estado y saldo pendiente
    $sql_update = $con->prepare("UPDATE notaspedidos SET estado = ?, saldo_pendiente = ? WHERE id_notaPedido = ?");
    $sql_update->execute([$nuevo_estado, $saldo_pendiente, $id_venta]);
}

$titulo = 'Editar Pago';
$ruta = "listar_pagosventa.php?id=" . $pago['id_notaPedido'] ;
$texto_boton = "Volver a Ventas";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="bi bi-pencil"></i> Editar Pago #<?= $pago['id_pago'] ?></h2>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Monto</label>
                                    <input type="number" class="form-control" name="monto" 
                                           step="0.01" min="0" required 
                                           value="<?= htmlspecialchars($pago['monto']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Método de Pago</label>
                                    <select class="form-select" name="metodo_pago" required>
                                        <option value="Efectivo" <?= $pago['metodo_pago'] == 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="Tarjeta" <?= $pago['metodo_pago'] == 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                                        <option value="Transferencia" <?= $pago['metodo_pago'] == 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                        <option value="Cheque" <?= $pago['metodo_pago'] == 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cuenta Bancaria</label>
                            <select class="form-select" name="id_cuenta" required>
                                <option value="">Seleccione una cuenta</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>" 
                                        <?= $cuenta['id_cuenta'] == $pago['id_cuenta'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cuenta['nombre']) ?> - <?= htmlspecialchars($cuenta['numero']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Referencia</label>
                            <input type="text" class="form-control" name="referencia" 
                                   value="<?= htmlspecialchars($pago['referencia']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($pago['observaciones']) ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar Pago</button>
                            <a href="listar_pagosventa.php?id=<?= $pago['id_notaPedido'] ?>" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>