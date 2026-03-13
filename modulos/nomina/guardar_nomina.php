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

$idOperador = $_SESSION['ID_Operador'] ?? 0;

if ($idOperador == 0) {
    $_SESSION['error_message'] = "No hay usuario autenticado.";
    header('Location: ../login.php');
    exit;
}

try {
    $camposRequeridos = [
        'empleados',
        'total_sueldos',
        'total_pagar',
        'id_cuenta',
        'fecha_inicio',
        'fecha_fin',
        'total_actividades',
        'total_deducciones',
        'empleados_pagados'
    ];
    
    $camposFaltantes = [];
    foreach ($camposRequeridos as $campo) {
        if (!isset($_POST[$campo]) || (empty($_POST[$campo]) && $_POST[$campo] !== '0')) {
            $camposFaltantes[] = $campo;
        }
    }
    
    if (!empty($camposFaltantes)) {
        throw new Exception("Datos incompletos. Campos faltantes: " . implode(', ', $camposFaltantes));
    }
    
    // Validar específicamente que empleados sea un array no vacío
    if (!is_array($_POST['empleados']) || count($_POST['empleados']) == 0) {
        throw new Exception("La lista de empleados está vacía o no es válida.");
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

    // Calcular total de préstamos de todos los empleados
    $totalPrestamos = 0;
    foreach ($empleados as $emp) {
        $totalPrestamos += isset($emp['descuento_prestamo']) ? floatval($emp['descuento_prestamo']) : 0;
    }

    $sqlGeneral = "
        INSERT INTO nomina_general (
            fecha_inicio,
            fecha_fin,
            empleados_pagados,
            total_sueldos,
            total_actividades_extras,
            total_deducciones,
            total_prestamos,
            total_a_pagar,
            id_cuenta,
            id_operador,
            fecha_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmtGeneral = $pdo->prepare($sqlGeneral);
    $stmtGeneral->execute([
        $fechaInicio,
        $fechaFin,
        $empleadosPagados,
        $totalSueldos,
        $totalActividades,
        $totalDeducciones,
        $totalPrestamos,
        $totalPagar,
        $idCuenta,
        $idOperador
    ]);

    $idNominaGeneral = $pdo->lastInsertId();

    $sqlDetalle = "
        INSERT INTO nomina_detalle (
            id_nomina_general,
            id_empleado,
            dias_laborados,
            sueldo_base,
            actividades_extras,
            deducciones,
            prestamo_descuento,
            total_pagar,
            id_operador,
            fecha_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmtDetalle = $pdo->prepare($sqlDetalle);

    foreach ($empleados as $emp) {
        // Asegurar que descuento_prestamo existe y es numérico
        $prestamoDescuento = isset($emp['descuento_prestamo']) ? floatval($emp['descuento_prestamo']) : 0;
        
        $stmtDetalle->execute([
            $idNominaGeneral,
            $emp['id_empleado'],
            $emp['dias'],
            $emp['sueldo_base'],
            $emp['actividades'],
            $emp['descuentos'],
            $prestamoDescuento,
            $emp['total_pagar'],
            $idOperador
        ]);
    }

    $stmt_update_cuenta = $pdo->prepare("
      UPDATE cuentas_bancarias 
      SET saldo_actual = saldo_actual - :monto 
      WHERE id_cuenta = :id_cuenta
    ");

    $stmt_update_cuenta->execute([
      ':monto' => $totalPagar,
      ':id_cuenta' => $idCuenta
    ]);

    $pdo->commit();

    header("Location: generar_nomina.php?guardado=ok");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "Error al guardar la nómina: " . $e->getMessage();
}
?>