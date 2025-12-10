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
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        throw new Exception('Faltan las fechas del período');
    }
    
    if (empty($id_cuenta)) {
        throw new Exception('Debe seleccionar una cuenta de pago');
    }
    
    // 2. OBTENER Y DECODIFICAR DATOS DE NÓMINA
    $datos_nomina_json = $_POST['nomina_data_json'] ?? '{}';
    $totales_json = $_POST['totales_json'] ?? '{}';
    
    $datos_nomina = json_decode($datos_nomina_json, true);
    $totales = json_decode($totales_json, true);
    
    if (empty($datos_nomina) || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('No hay datos válidos de nómina para guardar');
    }
    
    // 3. CONVERTIR FECHAS AL FORMATO DE BD (YYYY-MM-DD)
    $fecha_inicio_bd = DateTime::createFromFormat('d/m/Y', $fecha_inicio)->format('Y-m-d');
    $fecha_fin_bd = DateTime::createFromFormat('d/m/Y', $fecha_fin)->format('Y-m-d');
    
    // 4. CONECTAR A BD
    $database = new Database();
    $pdo = $database->conectar();
    
    // 5. INICIAR TRANSACCIÓN
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
            $fecha_inicio_bd,
            $fecha_fin_bd,
            $totales['empleados_pagados'] ?? 0,
            $totales['total_sueldo_base'] ?? 0,
            $totales['total_actividades'] ?? 0,
            $totales['total_deducciones'] ?? 0,
            $totales['total_pagar'] ?? 0,
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
        
        $errores_detalle = [];
        foreach ($datos_nomina as $id_checador => $nomina) {
            // Saltar si hay algún error implícito
            if (empty($nomina['id_empleado'])) {
                continue;
            }
            
            // Calcular actividades totales para este empleado
            $actividades_totales_empleado = 
                ($nomina['pago_actividades_bd'] ?? 0) + 
                ($nomina['pago_actividades_seleccionadas'] ?? 0) + 
                ($nomina['pago_actividades_gerente'] ?? 0);
            
            try {
                $stmt_detalle->execute([
                    $id_nomina_general,
                    $nomina['id_empleado'],
                    $nomina['dias_trabajados'] ?? 0,
                    $nomina['sueldo_base'] ?? 0,
                    $actividades_totales_empleado,
                    $nomina['descuento_registros'] ?? 0,
                    $nomina['total_pagar'] ?? 0,
                    $ID_Operador
                ]);
            } catch (Exception $e) {
                $errores_detalle[] = "Empleado {$nomina['nombre_completo']}: " . $e->getMessage();
                error_log("Error guardando detalle para empleado {$id_checador}: " . $e->getMessage());
            }
        }
        
        // 9. CONFIRMAR TRANSACCIÓN
        $pdo->commit();
        
        // 10. GUARDAR EN SESIÓN PARA POSIBLE REVISIÓN
        $_SESSION['ultima_nomina_guardada'] = [
            'id' => $id_nomina_general,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'total' => $totales['total_pagar'] ?? 0
        ];
        
        // 11. MENSAJE DE ÉXITO
        $mensaje = "Nómina guardada exitosamente. ID: #$id_nomina_general";
        if (!empty($errores_detalle)) {
            $mensaje .= "<br><small>Nota: Hubo algunos errores en detalles: " . implode(', ', $errores_detalle) . "</small>";
        }
        
        $_SESSION['success_message'] = $mensaje;
        
        // 12. REDIRECCIÓN
        header('Location: generar_nomina.php?guardado=1');
        exit;
        
    } catch (Exception $e) {
        // REVERTIR EN CASO DE ERROR
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // MANEJO DE ERRORES
    $_SESSION['error_message'] = "Error al guardar nómina: " . $e->getMessage();
    error_log("Error guardar_nomina: " . $e->getMessage());
    
    // Volver al formulario
    header('Location: generar_nomina.php?error=1');
    exit;
}