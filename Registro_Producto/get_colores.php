<?php
include __DIR__ . '/../db/config.php';
$db = new Database();
$conexion = $db->conectar();

header('Content-Type: application/json');

$especieId = $_GET['especie'] ?? null;

if (!$especieId) {
    echo json_encode(['error' => 'ID de especie no proporcionado']);
    exit;
}

try {
    $sql = "SELECT id_color, nombre_color FROM Colores WHERE id_especie = :id_especie ORDER BY nombre_color";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id_especie', $especieId, PDO::PARAM_INT);
    $stmt->execute();
    
    $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($colores);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Error al cargar colores: ' . $e->getMessage()]);
}
?>