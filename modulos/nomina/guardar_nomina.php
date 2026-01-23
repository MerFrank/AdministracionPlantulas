<?php
// Habilitar mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicio de sesión debe ir al principio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Crear instancia de Database y obtener conexión PDO
$database = new Database();
$pdo = $database->conectar();

// Verificar si hay conexión a la base de datos
try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
    // Test simple de conexión
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Obtener ID del operador (usuario logueado)
$id_operador = $_SESSION['ID_Operador'] ?? 0;
if ($id_operador == 0) {
    // Si no hay usuario logueado, usar un valor por defecto o redirigir
    $_SESSION['error_message'] = "No hay usuario autenticado. Por favor inicie sesión.";
    header('Location: ../login.php');
    exit;
}

