<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Verificar que viene del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: generar_nomina.php');
    exit;
}

// Verificar que hay datos de nómina
if (!isset($_POST['nomina']) || empty($_POST['nomina'])) {
    $_SESSION['error_message'] = "No hay datos de nómina para guardar";
    header('Location: generar_nomina.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->conectar();
    
    // Obtener ID_Operador de la sesión
    $ID_Operador = $_SESSION['ID_Operador'] ?? null;
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Preparar statement
    $stmt = $pdo->prepare("
        INSERT INTO nomina_registrada (
            periodo_nomina, id_empleado, id_checador, nombre_empleado, puesto,
            sueldo_diario, dias_trabajados, sueldo_base, pago_actividades_extras, 
            dias_sin_4_registros, dias_condonados, descuento_registros, total_pagar, 
            actividades_seleccionadas_ids, usuario_registro
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    
    $periodo_nomina = $_POST['periodo_nomina'];
    $contador = 0;
    
    foreach ($_POST['nomina'] as $id_checador => $datos) {
        // Obtener días condonados
        $dias_condonados = $_POST['dias_condonados'][$id_checador] ?? 0;
        
        // Obtener IDs de actividades seleccionadas
        $actividades_ids = [];
        if (isset($_POST['actividades'][$id_checador])) {
            $actividades_ids = array_keys($_POST['actividades'][$id_checador]);
        }
        $actividades_ids_str = !empty($actividades_ids) ? implode(',', $actividades_ids) : '';
        
        $stmt->execute([
            $periodo_nomina,
            $datos['id_empleado'],
            $id_checador,
            $datos['nombre_completo'],
            $datos['puesto'],
            $datos['sueldo_diario'],
            $datos['dias_trabajados'],
            $datos['sueldo_base'],
            $datos['pago_actividades_seleccionadas'],
            $datos['dias_sin_4_registros'],
            $dias_condonados,
            $datos['descuento_registros'],
            $datos['total_pagar'],
            $actividades_ids_str,
            $ID_Operador
        ]);
        
        $contador++;
    }
    
    $pdo->commit();
    
    $_SESSION['success_message'] = "✅ Nómina guardada exitosamente. Se registraron $contador empleados.";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "❌ Error al guardar la nómina: " . $e->getMessage();
    error_log("Error guardando nómina: " . $e->getMessage());
}

header('Location: generar_nomina.php');
exit;
?>