<?php
// Habilitar CORS si es necesario
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';

try {
    // Obtener datos POST
    $id_cuenta = isset($_POST['id_cuenta']) ? $_POST['id_cuenta'] : '';
    $monto_requerido = isset($_POST['monto_requerido']) ? floatval($_POST['monto_requerido']) : 0;
    
    if (empty($id_cuenta)) {
        throw new Exception("ID de cuenta no proporcionado");
    }
    
    if ($monto_requerido <= 0) {
        throw new Exception("Monto requerido inválido");
    }
    
    // Crear instancia de Database y obtener conexión PDO
    $database = new Database();
    $pdo = $database->conectar();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Consultar saldo de la cuenta
    $stmt_cuenta = $pdo->prepare("
        SELECT saldo_actual
        FROM cuentas_bancarias
        WHERE id_cuenta = :id_cuenta AND activo = 1
    ");
    
    $stmt_cuenta->execute([
        ':id_cuenta' => $id_cuenta
    ]);
    
    $monto_cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);
    
    if (!$monto_cuenta) {
        throw new Exception("Cuenta no encontrada o inactiva");
    }
    
    $saldoCuenta = (float) $monto_cuenta['saldo_actual'];
    
    // Verificar si hay saldo suficiente
    if ($saldoCuenta <= 0 || $monto_requerido > $saldoCuenta) {
        echo json_encode([
            'success' => false,
            'message' => "La cuenta no tiene saldo suficiente. Saldo actual: $" . number_format($saldoCuenta, 2) . ", Monto requerido: $" . number_format($monto_requerido, 2)
        ]);
        exit;
    }
    
    // Saldo suficiente
    echo json_encode([
        'success' => true,
        'message' => "Saldo suficiente",
        'data' => [
            'saldo_actual' => $saldoCuenta,
            'monto_requerido' => $monto_requerido,
            'diferencia' => $saldoCuenta - $monto_requerido
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Error al verificar saldo: " . $e->getMessage()
    ]);
}