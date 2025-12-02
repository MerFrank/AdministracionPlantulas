<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// ---- 1. VALIDAR POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: generar_nomina.php');
    exit;
}

// ---- 2. VALIDAR LOGIN ----
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Debe iniciar sesión para guardar nóminas";
    header('Location: generar_nomina.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->conectar();

    // ---- 3. OBTENER DATOS ----
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin    = $_POST['fecha_fin'] ?? '';
    $idCuenta    = $_POST['id_cuenta'] ?? '';
    $idOperador  = $_POST['id_operador'] ?? '';

    // Los datos reales vienen de los POST
    $nominaCompleta   = json_decode($_POST['nomina_data'] ?? '{}', true);
    $actividadesData  = json_decode($_POST['actividades_data'] ?? '{}', true);

    // ---- 4. VALIDAR DATOS ----
    if (empty($fechaInicio) || empty($fechaFin) || empty($idCuenta)) {
        throw new Exception("Faltan datos requeridos para guardar la nómina");
    }

    if (empty($nominaCompleta)) {
        throw new Exception("No hay datos de nómina para guardar");
    }

    // ---- 5. INICIALIZAR ACUMULADORES ----
    $totalEmpleados       = 0;
    $totalSueldos         = 0.0;
    $totalActividadesExt  = 0.0;
    $totalDescuentos      = 0.0;
    $totalPagar           = 0.0;

    // ---- 6. PROCESAR EMPLEADOS ----
    foreach ($nominaCompleta as $idChecador => $nomina) {
        if (!isset($nomina['error'])) {

            // ---- 7. VALIDAR CLAVES REQUERIDAS ----
            if (!isset(
                    $nomina['sueldo_base'],
                    $nomina['pago_actividades_seleccionadas'],
                    $nomina['pago_actividades_bd'],
                    $nomina['pago_actividades_gerente'],
                    $nomina['descuento_registros'],
                    $nomina['total_pagar']
                )) {
                error_log("⚠ Empleado $idChecador omitido: datos incompletos");
                continue;
            }

            // ---- 8. SANEAR Y CONVERTIR A FLOAT ----
            $sueldo     = floatval($nomina['sueldo_base']);
            $actividad  = floatval($nomina['pago_actividades_seleccionadas'])
                        + floatval($nomina['pago_actividades_bd'])
                        + floatval($nomina['pago_actividades_gerente']);
            $descuento  = floatval($nomina['descuento_registros']);
            $pagoTotal  = floatval($nomina['total_pagar']);

            // ---- 9. SUMAR A TOTALES GENERALES ----
            $totalEmpleados++;
            $totalSueldos         += $sueldo;
            $totalActividadesExt  += $actividad;
            $totalDescuentos      += $descuento;
            $totalPagar           += $pagoTotal;

            // ---- 10. LOG DEBUG INDIVIDUAL ----
            error_log("👤 Emp:$idChecador | Sueldo:$sueldo | Actividad:$actividad | Descuento:$descuento | Pago:$pagoTotal");
        }
    }

    // ---- 11. LOG RESUMEN GENERAL ----
    error_log("=== RESUMEN GENERAL CALCULADO ===");
    error_log("Total Empleados: $totalEmpleados");
    error_log("Total Sueldos: $totalSueldos");
    error_log("Total Actividades Extras: $totalActividadesExt");
    error_log("Total Descuentos: $totalDescuentos");
    error_log("Total a Pagar: $totalPagar");

    // ---- 12. GUARDAR EN BD ----
    $resultado = guardarNominaEnBD($pdo, $nominaCompleta, $fechaInicio, $fechaFin, $idCuenta, $idOperador);

    if (!$resultado['success']) {
        throw new Exception("Error al guardar nómina: {$resultado['error']}");
    }

    // ---- 13. MENSAJE OK ----
    $_SESSION['success_message'] =
        "✅ Nómina guardada exitosamente. ID: {$resultado['id_nomina_general']}. Empleados procesados: {$resultado['empleados_insertados']}. Total a pagar: $" . number_format($totalPagar, 2);

    header('Location: generar_nomina.php');
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = "❌ " . $e->getMessage();
    error_log("Error en procesar_guardar_nomina: " . $e->getMessage());
}

header('Location: generar_nomina.php');
exit;
