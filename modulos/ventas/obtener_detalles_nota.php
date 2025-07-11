<?php
require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$db = new Database();
$conexion = $db->conectar();

$id_notaPedido = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_notaPedido) {
    echo json_encode(['error' => 'ID de nota inválido']);
    exit;
}

try {
    // Obtener información básica de la nota de pedido
    $sqlNota = "SELECT 
                    np.id_notaPedido, 
                    np.id_cliente, 
                    c.nombre_Cliente, 
                    np.fechaPedido, 
                    dnp.monto_total, 
                    COALESCE(SUM(sa.monto_pago), 0) AS total_abonado,
                    (dnp.monto_total - COALESCE(SUM(sa.monto_pago), 0)) AS saldo_pendiente
                FROM notaspedidos np
                JOIN detallesnotapedido dnp ON np.id_notaPedido = dnp.id_notaPedido
                JOIN clientes c ON np.id_cliente = c.id_cliente
                LEFT JOIN seguimientoanticipos sa ON np.id_notaPedido = sa.numero_venta
                WHERE np.id_notaPedido = ?
                GROUP BY np.id_notaPedido, np.id_cliente, c.nombre_Cliente, np.fechaPedido, dnp.monto_total";
    
    $stmt = $conexion->prepare($sqlNota);
    $stmt->execute([$id_notaPedido]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        echo json_encode(['error' => 'Nota de pedido no encontrada']);
        exit;
    }

    // Obtener información adicional del cliente
    $sqlCliente = "SELECT telefono, domicilio_fiscal FROM clientes WHERE id_cliente = ?";
    $stmt = $conexion->prepare($sqlCliente);
    $stmt->execute([$nota['id_cliente']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener historial de pagos con más información
    $sqlPagos = "SELECT 
                    fecha_pago, 
                    monto_pago, 
                    metodo_pago, 
                    comentarios
                 FROM seguimientoanticipos 
                 WHERE numero_venta = ? 
                 ORDER BY fecha_pago DESC";
    
    $stmt = $conexion->prepare($sqlPagos);
    $stmt->execute([$id_notaPedido]);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'nota' => $nota,
        'cliente' => $cliente,
        'pagos' => $pagos
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>
