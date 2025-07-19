<?php
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $con = $db->conectar();

    $datos = json_decode(file_get_contents("php://input"), true);

    // Insertar en notaspedidos
    $stmt = $con->prepare("
        INSERT INTO notaspedidos (
            num_remision, fechaPedido, fecha_entrega, id_cliente, 
            tipo_pago, metodo_Pago, importe_letra, observaciones,
            num_pagare, fecha_validez, lugar_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $datos['numeroRemision'],
        $datos['fechaPedido'],
        $datos['fechaEntrega'],
        $datos['id_cliente'],
        $datos['tipo_pago'],
        $datos['metodo_Pago'],
        $datos['importe_letra'],
        $datos['observaciones'],
        $datos['num_pagare'],
        $datos['fecha_validez'],
        $datos['lugar_pago']
    ]);

    $idNota = $con->lastInsertId();

    // Insertar los productos en detallesnotapedido
    $stmtDetalle = $con->prepare("
        INSERT INTO detallesnotapedido (
            id_notaPedido, id_color, cantidad, precio_unitario, monto_total
        ) VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($datos['productos'] as $prod) {

        $stmtDetalle->execute([
            $idNota,
            $prod['id_color'], 
            $prod['cantidad'],
            $prod['costo'],
            $prod['subtotal']
        ]);
    }

    echo json_encode(["success" => true]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
