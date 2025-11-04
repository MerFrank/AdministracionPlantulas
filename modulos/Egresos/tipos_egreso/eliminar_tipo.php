<?php
require_once(__DIR__ . '/../../../includes/validacion_session.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);



require_once __DIR__ . '/../../../includes/config.php';

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$id_tipo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_tipo > 0) {
    try {
        // Verificar si el tipo tiene egresos asociados
        $stmt = $con->prepare("SELECT COUNT(*) FROM egresos WHERE id_tipo_egreso = ?");
        $stmt->execute([$id_tipo]);
        $tieneEgresos = $stmt->fetchColumn() > 0;

        if ($tieneEgresos) {
            // Marcamos como inactivo en lugar de eliminar (borrado lógico)
            $sql = "UPDATE tipos_egreso SET activo = 0 WHERE id_tipo = ?";
            $mensaje = 'Tipo de egreso marcado como inactivo (tiene egresos asociados)';
        } else {
            // Eliminación física si no tiene egresos asociados
            $sql = "DELETE FROM tipos_egreso WHERE id_tipo = ?";
            $mensaje = 'Tipo de egreso eliminado correctamente';
        }

        $stmt = $con->prepare($sql);
        if ($stmt->execute([$id_tipo])) {
            $_SESSION['success_message'] = $mensaje;
        } else {
            $_SESSION['error_message'] = 'Error al intentar eliminar el tipo de egreso';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID de tipo no válido';
}

header('Location: lista_tipos.php');
exit;