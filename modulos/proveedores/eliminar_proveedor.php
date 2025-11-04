<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión para mensajes flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';

// Verificar si se recibió el ID del proveedor
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No se proporcionó un ID de proveedor válido.";
    header("Location: lista_proveedores.php");
    exit();
}

$id_proveedor = $_GET['id'];

// Instanciar base de datos
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Realizar el borrado lógico (marcar como inactivo)
try {
    $sql = "UPDATE proveedores SET activo = 0 WHERE id_proveedor = ?";
    $stmt = $con->prepare($sql);
    $stmt->execute([$id_proveedor]);
    
    // Verificar si se afectó alguna fila
    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = "Proveedor eliminado correctamente (borrado lógico).";
    } else {
        $_SESSION['error_message'] = "No se encontró el proveedor con ID $id_proveedor o ya estaba eliminado.";
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error al eliminar el proveedor: " . $e->getMessage();
}

// Redirigir de vuelta a la lista de proveedores
header("Location: lista_proveedores.php");
exit();
?>