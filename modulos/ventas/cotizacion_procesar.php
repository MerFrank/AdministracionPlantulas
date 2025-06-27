<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../db/config.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // Validar datos recibidos
    $id_cliente = isset($_POST['id_clientes']) ? intval($_POST['id_clientes']) : null;
    $fecha_validez = isset($_POST['fechaValidez']) ? $_POST['fechaValidez'] : null;
    $productosJSON = isset($_POST['productosJSON']) ? $_POST['productosJSON'] : '[]';
    $nota = isset($_POST['nota']) ? $_POST['nota'] : '';

    if (!$id_cliente) throw new Exception("Cliente no seleccionado");
    if (empty($fecha_validez)) throw new Exception("Fecha de validez requerida");

    $productos = json_decode($productosJSON, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error en formato de productos: " . json_last_error_msg());
    }
    if (empty($productos)) throw new Exception("No hay productos en la cotización");

    $db = new Database();
    $conexion = $db->conectar();
    $conexion->beginTransaction();

    // 1. Insertar la cabecera de la cotización (con campo notas)
    $sqlCotizacion = "INSERT INTO Cotizaciones (
                        id_cliente,
                        fecha_validez,
                        notas
                      ) VALUES (
                        :id_cliente,
                        :fecha_validez,
                        :notas
                      )";

    $stmtCotizacion = $conexion->prepare($sqlCotizacion);
    $stmtCotizacion->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
    $stmtCotizacion->bindValue(':fecha_validez', $fecha_validez, PDO::PARAM_STR);
    $stmtCotizacion->bindValue(':notas', $nota, PDO::PARAM_STR);

    if (!$stmtCotizacion->execute()) {
        throw new Exception("Error al guardar la cotización");
    }

    $id_cotizacion = $conexion->lastInsertId();

    // 2. Insertar los detalles con notas
    $sqlDetalle = "INSERT INTO DetallesCotizacion (
                        id_cotizacion,
                        id_variedad,
                        cantidad,
                        precio_unitario,
                        notas
                  ) VALUES (
                        :id_cotizacion,
                        :id_variedad,
                        :cantidad,
                        :precio_unitario,
                        :notas
                  )";

    $stmtDetalle = $conexion->prepare($sqlDetalle);

    foreach ($productos as $producto) {
        if (empty($producto['variedad_id']) || empty($producto['cantidad']) || empty($producto['precioUnitario'])) {
            throw new Exception("Datos de producto incompletos");
        }

        $stmtDetalle->bindValue(':id_cotizacion', $id_cotizacion, PDO::PARAM_INT);
        $stmtDetalle->bindValue(':id_variedad', $producto['variedad_id'], PDO::PARAM_INT);
        $stmtDetalle->bindValue(':cantidad', $producto['cantidad'], PDO::PARAM_INT);
        $stmtDetalle->bindValue(':precio_unitario', $producto['precioUnitario'], PDO::PARAM_STR);
        $stmtDetalle->bindValue(':notas', $producto['notas'] ?? '', PDO::PARAM_STR);

        if (!$stmtDetalle->execute()) {
            throw new Exception("Error al guardar detalle de cotización");
        }
    }

    $conexion->commit();
    $response = [
        'success' => true,
        'message' => 'Cotización guardada correctamente',
        'id_cotizacion' => $id_cotizacion
    ];

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
