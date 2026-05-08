<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');
require_once __DIR__ . '/../../includes/config.php';
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new Database();
    $pdo = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$ingresos = $pdo->query(
    'SELECT
    FROM 
')->fetchAll();


$titulo = 'Reportes Ingresos';
$encabezado = 'Reportes Ingresos';


$ruta = "lista_reportes.php";
$texto_boton = "Regresar";
require_once __DIR__ . '/../../includes/header.php';


?>
<main>
    <h2>Hola aquí van los ingresos</h2>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>