<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

if (!isset($_GET['id_nomina'])) {
    echo json_encode(['error' => 'ID de nómina no recibido']);
    exit;
}

$id_nomina = intval($_GET['id_nomina']);

$database = new Database();
$pdo = $database->conectar();

try {
    $stmt = $pdo->prepare("
        SELECT 
            nd.*,
            e.nombre AS nombre_empleado
        FROM nomina_detalle nd
        LEFT JOIN empleados e ON nd.id_empleado = e.id_empleado
        WHERE nd.id_nomina_general = ?
        ORDER BY nd.id_nomina_detalle DESC
    ");
    $stmt->execute([$id_nomina]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($detalles);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
