<?php
require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$db = new Database();
$conexion = $db->conectar();

$especieId = $_GET['especie'] ?? null;

if (!$especieId) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT id_color, nombre_color FROM colores WHERE id_especie = :id_especie ORDER BY nombre_color";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id_especie', $especieId, PDO::PARAM_INT);
    $stmt->execute();
    $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($colores);
} catch(PDOException $e) {
    error_log('Error al obtener colores: ' . $e->getMessage());
    echo json_encode([]);
}