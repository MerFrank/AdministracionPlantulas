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

$id_cuenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_cuenta > 0) {
    try {
        // Verificar si la cuenta existe
        $stmt = $con->prepare("SELECT nombre FROM cuentas_bancarias WHERE id_cuenta = ?");
        $stmt->execute([$id_cuenta]);
        $nombre_cuenta = $stmt->fetchColumn();
        
        if (!$nombre_cuenta) {
            $_SESSION['error_message'] = 'La cuenta no existe';
            header('Location: lista_cuentas.php');
            exit;
        }

        // Siempre hacer borrado lógico (marcar como inactivo)
        $sql = "UPDATE cuentas_bancarias SET activo = 0 WHERE id_cuenta = ?";
        $stmt = $con->prepare($sql);
        
        if ($stmt->execute([$id_cuenta])) {
            $_SESSION['success_message'] = "Cuenta '$nombre_cuenta' marcada como inactiva";
            
            // Registrar acción en bitácora si existe
            if (function_exists('registrarAccion')) {
                registrarAccion($_SESSION['usuario_id'], "Desactivó cuenta: $nombre_cuenta (ID: $id_cuenta)");
            }
        } else {
            $_SESSION['error_message'] = 'Error al intentar desactivar la cuenta';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID de cuenta no válido';
}

header('Location: lista_cuentas.php');
exit;