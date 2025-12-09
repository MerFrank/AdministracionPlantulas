<?php

session_start();
require_once __DIR__ . '/../../includes/config.php';

try {
    // 1. VERIFICAR DATOS RECIBIDOS
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $id_cuenta = $_POST['id_cuenta'] ?? 0;
    $ID_Operador = $_SESSION['ID_Operador'] ?? 0;
    
    // Validaciones básicas
    if (empty($fecha_inicio) || empty($fecha_fin) || empty($id_cuenta)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    // 2. OBTENER Y DECODIFICAR DATOS DE NÓMINA
    $datos_nomina = json_decode($_POST['datos_nomina'] ?? '[]', true);
    $actividades_seleccionadas = json_decode($_POST['actividades_seleccionadas'] ?? '[]', true);
    
    if (empty($datos_nomina)) {
        throw new Exception('No hay datos de nómina para guardar');
    }
    
    // 3. CONECTAR A BD
    $database = new Database();
    $pdo = $database->conectar();
    
    // 4. CALCULAR TOTALES (igual que en tu vista)
    $empleados_pagados = 0;
    $total_sueldos = 0;
    $total_actividades_extras = 0;
    $total_deducciones = 0;
    $total_a_pagar = 0;
    
    foreach ($datos_nomina as $id_checador => $nomina) {
        if (isset($nomina['error'])) continue;
        
        $empleados_pagados++;
        $total_sueldos += $nomina['sueldo_base'];
        
        // Sumar todas las actividades
        $actividades_totales = $nomina['pago_actividades_bd'] + 
                              $nomina['pago_actividades_seleccionadas'] + 
                              $nomina['pago_actividades_gerente'];
        $total_actividades_extras += $actividades_totales;
        
        $total_deducciones += $nomina['descuento_registros'];
        $total_a_pagar += $nomina['total_pagar'];
    }
    
    // 5. INICIAR TRANSACCIÓN (IMPORTANTE)
    $pdo->beginTransaction();
    
    try {
        // 6. INSERTAR EN NOMINA_GENERAL
        $stmt_general = $pdo->prepare("
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
        ");
        
        $stmt_general->execute([
            $fecha_inicio,
            $fecha_fin,
            $empleados_pagados,
            $total_sueldos,
            $total_actividades_extras,
            $total_deducciones,
            $total_a_pagar,
            $id_cuenta,
            $ID_Operador
        ]);
        
        // 7. OBTENER ID DE NÓMINA GENERADA
        $id_nomina_general = $pdo->lastInsertId();
        
        // 8. INSERTAR DETALLES (UNO POR EMPLEADO)
        $stmt_detalle = $pdo->prepare("
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
        ");
        
        foreach ($datos_nomina as $id_checador => $nomina) {
            if (isset($nomina['error'])) continue;
            
            // Calcular actividades totales para este empleado
            $actividades_totales_empleado = $nomina['pago_actividades_bd'] + 
                                          $nomina['pago_actividades_seleccionadas'] + 
                                          $nomina['pago_actividades_gerente'];
            
            $stmt_detalle->execute([
                $id_nomina_general,
                $nomina['id_empleado'], // Asegúrate que esto existe
                $nomina['dias_trabajados'],
                $nomina['sueldo_base'],
                $actividades_totales_empleado,
                $nomina['descuento_registros'],
                $nomina['total_pagar'],
                $ID_Operador
            ]);
        }
        
        // 9. CONFIRMAR TRANSACCIÓN
        $pdo->commit();
        
        // 10. MENSAJE DE ÉXITO Y REDIRECCIÓN
        $_SESSION['success_message'] = "Nómina guardada exitosamente. ID: #$id_nomina_general";
        
        // Puedes redirigir a una vista de resumen o volver
        header('Location: resumen_nomina.php?id=' . $id_nomina_general);
        exit;
        
    } catch (Exception $e) {
        // 11. REVERTIR EN CASO DE ERROR
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // 12. MANEJO DE ERRORES
    $_SESSION['error_message'] = "Error al guardar nómina: " . $e->getMessage();
    error_log("Error guardar_nomina: " . $e->getMessage());
    
    // Volver al formulario
    header('Location: generar_nomina.php');
    exit;
}