<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$id_egreso = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_egreso > 0) {
    try {
        // Verificar si el egreso está relacionado con otros registros
        // (aquí puedes agregar validaciones adicionales si es necesario)
        
        // Eliminar directamente (o implementar borrado lógico si prefieres)
        $sql = "DELETE FROM egresos WHERE id_egreso = ?";
        $stmt = $con->prepare($sql);
        
        if ($stmt->execute([$id_egreso])) {
            $_SESSION['success_message'] = 'Egreso eliminado correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al eliminar el egreso';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID de egreso no válido';
}

header('Location: lista_egresos.php');
exit;
