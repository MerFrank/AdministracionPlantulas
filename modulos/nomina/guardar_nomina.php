<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

$database = new Database();
$pdo = $database->conectar();

try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

//  USAR LA MISMA VARIABLE EN TODO EL ARCHIVO
$idOperador = $_SESSION['ID_Operador'] ?? 0;

if ($idOperador == 0) {
    $_SESSION['error_message'] = "No hay usuario autenticado.";
    header('Location: ../login.php');
    exit;
}

try {

    //  VALIDACIONES REALES
    if (
        empty($_POST['empleados']) ||
        !isset($_POST['total_sueldos']) ||
        !isset($_POST['total_pagar']) ||
        !isset($_POST['id_cuenta'])
    ) {
        throw new Exception("Datos incompletos recibidos desde el formulario.");
    }

    $pdo->beginTransaction();

    $empleados = $_POST['empleados'];

    $fechaInicio = $_POST['fecha_inicio'];
    $fechaFin = $_POST['fecha_fin'];

    $totalSueldos = $_POST['total_sueldos'];
    $totalActividades = $_POST['total_actividades'];
    $totalDeducciones = $_POST['total_deducciones'];
    $totalPagar = $_POST['total_pagar'];
    $empleadosPagados = $_POST['empleados_pagados'];
    $idCuenta = $_POST['id_cuenta'];

    // ================= INSERT MAESTRO =================
    $sqlGeneral = "
        INSERT INTO nomina_general (
            fecha_inicio,
            fecha_fin,
            empleados_pagados,
            total_sueldos,
            total_actividades_extras,
            total_deducciones,
            total_a_pagar,
            id_cuenta,
            id_operador,
            fecha_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmtGeneral = $pdo->prepare($sqlGeneral);
    $stmtGeneral->execute([
        $fechaInicio,
        $fechaFin,
        $empleadosPagados,
        $totalSueldos,
        $totalActividades,
        $totalDeducciones,
        $totalPagar,
        $idCuenta,
        $idOperador
    ]);

    $idNominaGeneral = $pdo->lastInsertId();

    // ================= INSERT DETALLE =================
    $sqlDetalle = "
        INSERT INTO nomina_detalle (
            id_nomina_general,
            id_empleado,
            dias_laborados,
            sueldo_base,
            actividades_extras,
            deducciones,
            total_pagar,
            id_operador,
            fecha_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmtDetalle = $pdo->prepare($sqlDetalle);

    foreach ($empleados as $emp) {
        $stmtDetalle->execute([
            $idNominaGeneral,
            $emp['id_empleado'],
            $emp['dias'],
            $emp['sueldo_base'],
            $emp['actividades'],
            $emp['descuentos'],
            $emp['total_pagar'],
            $idOperador
        ]);
    }

    $pdo->commit();

    header("Location: generar_nomina.php?guardado=ok");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "Error al guardar la nómina: " . $e->getMessage();
}
