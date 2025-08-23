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

// Obtener ID del pago
$id_pago = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pago <= 0) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener información del pago para redireccionar después
$sql_pago = $con->prepare("SELECT p.id_notaPedido, p.monto, p.id_cuenta 
                          FROM pagosventas p 
                          WHERE p.id_pago = ?");
$sql_pago->execute([$id_pago]);
$pago = $sql_pago->fetch(PDO::FETCH_ASSOC);

// Verificar si el pago existe
if (!$pago) {
    $_SESSION['error'] = "El pago no existe o ya ha sido eliminado";
    header('Location: lista_ventas.php');
    exit;
}

// Verificar si la venta permite eliminar pagos (dependiendo del estado)
$sql_estado = $con->prepare("SELECT estado FROM notaspedidos WHERE id_notaPedido = ?");
$sql_estado->execute([$pago['id_notaPedido']]);
$estado_result = $sql_estado->fetch(PDO::FETCH_ASSOC);

// Verificar si se obtuvo un resultado válido
if ($estado_result) {
    $estado = $estado_result['estado'];
    
    if ($estado == 'Facturado' || $estado == 'Cerrado') {
        $_SESSION['error'] = "No se pueden eliminar pagos de ventas " . $estado;
        header("Location: listar_pagosventa.php?id=" . $pago['id_notaPedido']);
        exit;
    }
}

// Eliminar el pago
try {
    // Iniciar transacción
    $con->beginTransaction();
    
    // 1. Verificar que la cuenta tenga suficiente saldo antes de revertir
    if ($pago['id_cuenta']) {
        $sql_saldo = $con->prepare("SELECT saldo_actual FROM cuentas_bancarias WHERE id_cuenta = ?");
        $sql_saldo->execute([$pago['id_cuenta']]);
        $saldo_result = $sql_saldo->fetch(PDO::FETCH_ASSOC);
        
        if ($saldo_result) {
            $saldo_actual = $saldo_result['saldo_actual'];
            
            if ($saldo_actual < $pago['monto']) {
                throw new Exception("La cuenta no tiene suficiente saldo para revertir este pago");
            }
            
            // 2. Revertir el monto en la cuenta bancaria
            $sql_revertir = $con->prepare("UPDATE cuentas_bancarias SET saldo_actual = saldo_actual - ? WHERE id_cuenta = ?");
            $sql_revertir->execute([$pago['monto'], $pago['id_cuenta']]);
        }
    }
    
    // 3. Eliminar el pago
    $sql_delete = $con->prepare("DELETE FROM pagosventas WHERE id_pago = ?");
    $sql_delete->execute([$id_pago]);
    
    // 4. Actualizar el estado de la venta
    actualizarEstadoVenta($pago['id_notaPedido'], $con);
    
    $con->commit();
    
    $_SESSION['success'] = "Pago eliminado correctamente";
} catch (PDOException $e) {
    $con->rollBack();
    $_SESSION['error'] = "Error al eliminar el pago: " . $e->getMessage();
} catch (Exception $e) {
    $con->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

// Redireccionar de vuelta a la página de pagos
header("Location: listar_pagosventa.php?id=" . $pago['id_notaPedido']);
exit;

// Función para actualizar el estado de la venta
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
    
    // Determinar nuevo estado
    if ($total_pagado >= $total_venta) {
        $nuevo_estado = 'Pagado';
    } elseif ($total_pagado > 0) {
        $nuevo_estado = 'Parcialmente Pagado';
    } else {
        $nuevo_estado = 'Pendiente';
    }
    
    // Actualizar estado
    $sql_update = $con->prepare("UPDATE notaspedidos SET estado = ? WHERE id_notaPedido = ?");
    $sql_update->execute([$nuevo_estado, $id_venta]);
}