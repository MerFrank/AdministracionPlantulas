<?php

require_once(__DIR__ . '/../../includes/validacion_session.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificar si se recibió un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'ID de cotización no válido';
    header('Location: lista_cotizaciones.php');
    exit;
}

$id_cotizacion = (int)$_GET['id'];

// Verificar si la cotización existe
$stmt = $pdo->prepare("SELECT folio FROM cotizaciones WHERE id_cotizacion = ?");
$stmt->execute([$id_cotizacion]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    $_SESSION['error_message'] = 'Cotización no encontrada';
    header('Location: lista_cotizaciones.php');
    exit;
}

// Eliminar la cotización y sus items
try {
    $pdo->beginTransaction();
    
    // Eliminar items de la cotización
    $stmt = $pdo->prepare("DELETE FROM detallescotizacion WHERE id_cotizacion = ?");
    $stmt->execute([$id_cotizacion]);
    
    // Eliminar la cotización
    $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id_cotizacion = ?");
    $stmt->execute([$id_cotizacion]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = "Cotización {$cotizacion['folio']} eliminada correctamente";
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "Error al eliminar la cotización: " . $e->getMessage();
}

header('Location: lista_cotizaciones.php');
exit;
?>