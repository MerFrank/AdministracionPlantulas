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

$id_sucursal = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_sucursal > 0) {
    try {
        // Verificar si la sucursal tiene registros relacionados antes de eliminar
        $stmt = $con->prepare("SELECT COUNT(*) FROM egresos WHERE id_sucursal = ?");
        $stmt->execute([$id_sucursal]);
        $tieneRegistros = $stmt->fetchColumn() > 0;

        if ($tieneRegistros) {
            // Marcamos como inactiva en lugar de eliminar (borrado lógico)
            $sql = "UPDATE sucursales SET activo = 0 WHERE id_sucursal = ?";
            $mensaje = 'Sucursal marcada como inactiva (tiene registros relacionados)';
        } else {
            // Eliminación física si no tiene registros relacionados
            $sql = "DELETE FROM sucursales WHERE id_sucursal = ?";
            $mensaje = 'Sucursal eliminada correctamente';
        }

        $stmt = $con->prepare($sql);
        if ($stmt->execute([$id_sucursal])) {
            $_SESSION['success_message'] = $mensaje;
        } else {
            $_SESSION['error_message'] = 'Error al intentar eliminar la sucursal';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID de sucursal no válido';
}

header('Location: lista_sucursales.php');
exit;