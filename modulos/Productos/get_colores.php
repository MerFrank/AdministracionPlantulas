<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php'; // Ajusta la ruta si es necesario

header('Content-Type: application/json');

try {
    if (!isset($_GET['especie']) || !is_numeric($_GET['especie'])) {
        http_response_code(400);
        echo json_encode([]);
        exit;
    }

    $idEspecie = (int)$_GET['especie'];

    $db = new Database();
    $conexion = $db->conectar();

    $stmt = $conexion->prepare("SELECT id_color, nombre_color FROM colores WHERE id_especie = :id_especie ORDER BY nombre_color");
    $stmt->bindParam(':id_especie', $idEspecie, PDO::PARAM_INT);
    $stmt->execute();

    $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($colores);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}
