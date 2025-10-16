<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';



// Obtener ID de la actividad a eliminar
$id_actividad = isset($_GET['id_actividad']) ? (int)$_GET['id_actividad'] : 0;

if ($id_actividad <= 0) {
    header('Location: actividades_extras.php');
    exit;
}

$error = '';
$success = '';

try {
    $db = new Database();
    $con = $db->conectar();
    
    // Obtener nombre de la actividad para el mensaje
    $stmt = $con->prepare("
        SELECT nombre 
        FROM actividades_extras 
        WHERE id_actividad = ?
    ");
    $stmt->execute([$id_actividad]);
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        throw new Exception("Actividad no encontrada");
    }
    
    // Verificar si la actividad está siendo usada en algún registro
    $stmt_verificar = $con->prepare("
        SELECT COUNT(*) as en_uso 
        FROM empleado_actividades 
        WHERE id_actividad = ?
    ");
    $stmt_verificar->execute([$id_actividad]);
    $uso = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if ($uso['en_uso'] > 0) {
        // Si está en uso, desactivar en lugar de eliminar
        $stmt = $con->prepare("
            UPDATE actividades_extras 
            SET activo = 0 
            WHERE id_actividad = ?
        ");
        $stmt->execute([$id_actividad]);
        
        $success = "La actividad '" . htmlspecialchars($actividad['nombre']) . "' estaba siendo utilizada, por lo que se ha desactivado en lugar de eliminar.";
    } else {
        // Si no está en uso, eliminar permanentemente
        $stmt = $con->prepare("
            DELETE FROM actividades_extras 
            WHERE id_actividad = ?
        ");
        $stmt->execute([$id_actividad]);
        
        $success = "Actividad '" . htmlspecialchars($actividad['nombre']) . "' eliminada correctamente";
    }
    
    // Redirigir inmediatamente
    header("Location: actividades_extras.php?success=" . urlencode($success));
    exit;
    
} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
    header("Location: actividades_extras.php?error=" . urlencode($error));
    exit;
} catch (Exception $e) {
    $error = $e->getMessage();
    header("Location: actividades_extras.php?error=" . urlencode($error));
    exit;
}