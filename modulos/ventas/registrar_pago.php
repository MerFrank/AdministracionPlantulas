<?php
require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$db = new Database();
$conexion = $db->conectar();

// Validar y obtener datos del POST
$datos = json_decode(file_get_contents('php://input'), true);

$requiredFields = ['nota_id', 'id_cliente', 'fecha_pago', 'monto_pago', 'metodo_pago', 'folio_anticipo'];
foreach ($requiredFields as $field) {
    if (empty($datos[$field])) {
        echo json_encode(['error' => "El campo $field es obligatorio"]);
        exit;
    }
}

try {
    // Validar que la nota de pedido existe
    $sql = "SELECT monto_total, 
                   (SELECT COALESCE(SUM(monto_pago), 0) 
                    FROM seguimientoanticipos 
                    WHERE numero_venta = ?) AS total_abonado
            FROM detallesnotapedido 
            WHERE id_notaPedido = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$datos['nota_id'], $datos['nota_id']]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        echo json_encode(['error' => 'La nota de pedido no existe']);
        exit;
    }

    // Calcular saldo pendiente
    $saldo_pendiente = $nota['monto_total'] - $nota['total_abonado'];
    
    // Validar que el monto no exceda el saldo pendiente
    if ($datos['monto_pago'] > $saldo_pendiente) {
        echo json_encode(['error' => 'El monto excede el saldo pendiente']);
        exit;
    }

    // Insertar nuevo pago
    $sqlInsert = "INSERT INTO seguimientoanticipos 
        (folio_anticipo, numero_venta, id_cliente, fecha_pago, monto_pago, metodo_pago, comentarios)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    
    $stmt = $conexion->prepare($sqlInsert);
    $result = $stmt->execute([
        $datos['folio_anticipo'],
        $datos['nota_id'],
        $datos['id_cliente'],
        $datos['fecha_pago'],
        $datos['monto_pago'],
        $datos['metodo_pago'],
        $datos['comentarios'] ?? null
    ]);

    if ($result) {
        echo json_encode(['success' => 'Pago registrado correctamente']);
    } else {
        echo json_encode(['error' => 'Error al registrar el pago']);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>