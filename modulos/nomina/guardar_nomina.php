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
            // VALIDACIÓN CRÍTICA: Asegurar que tenemos id_empleado
            if (empty($nomina['id_empleado']) || !is_numeric($nomina['id_empleado'])) {
                error_log("ERROR: Empleado con ID checador $id_checador no tiene id_empleado válido");
                
                // Intentar obtener el id_empleado desde la BD usando id_checador
                try {
                    $stmt_buscar = $pdo->prepare("
                        SELECT id_empleado 
                        FROM empleados 
                        WHERE id_checador = ? 
                        LIMIT 1
                    ");
                    $stmt_buscar->execute([$id_checador]);
                    $result = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['id_empleado'])) {
                        $nomina['id_empleado'] = $result['id_empleado'];
                        error_log("Corregido: ID empleado para $id_checador es " . $nomina['id_empleado']);
                    } else {
                        $errores_detalle[] = "Empleado {$nomina['nombre_completo']}: No se encontró en BD";
                        continue;
                    }
                } catch (Exception $e) {
                    $errores_detalle[] = "Empleado {$nomina['nombre_completo']}: Error buscando en BD";
                    continue;
                }
            }
            
            // Calcular actividades totales CORRECTAMENTE
            $actividades_totales_empleado = 
                (floatval($nomina['pago_actividades_bd'] ?? 0)) + 
                (floatval($nomina['pago_actividades_seleccionadas'] ?? 0)) + 
                (floatval($nomina['pago_actividades_gerente'] ?? 0));
            
            // Validar que total_pagar sea coherente
            $sueldo_base = floatval($nomina['sueldo_base'] ?? 0);
            $descuento = floatval($nomina['descuento_registros'] ?? 0);
            $total_calculado = $sueldo_base + $actividades_totales_empleado - $descuento;
            $total_recibido = floatval($nomina['total_pagar'] ?? 0);
            
            // Si hay discrepancia, usar el calculado
            if (abs($total_calculado - $total_recibido) > 0.01) {
                error_log("ADVERTENCIA: Discrepancia en total para {$nomina['nombre_completo']}: ");
                error_log("  Calculado: $total_calculado, Recibido: $total_recibido");
                error_log("  Usando calculado: $total_calculado");
                $nomina['total_pagar'] = $total_calculado;
            }
            
            // LOGGING para debug
            error_log("Insertando detalle para: " . $nomina['nombre_completo']);
            error_log("  ID Empleado: " . $nomina['id_empleado']);
            error_log("  Sueldo base: " . $sueldo_base);
            error_log("  Actividades totales: " . $actividades_totales_empleado);
            error_log("  Descuento: " . $descuento);
            error_log("  Total a pagar: " . $nomina['total_pagar']);
            
            try {
                $stmt_detalle->execute([
                    $id_nomina_general,
                    $nomina['id_empleado'],
                    $nomina['dias_trabajados'] ?? 0,
                    $sueldo_base,
                    $actividades_totales_empleado,
                    $descuento,
                    $nomina['total_pagar'],
                    $ID_Operador
                ]);
                
                error_log("  ✓ Insertado correctamente");
                
            } catch (Exception $e) {
                $errores_detalle[] = "Empleado {$nomina['nombre_completo']}: " . $e->getMessage();
                error_log("  ✗ Error: " . $e->getMessage());
                
                // Log detallado del error PDO
                if ($e instanceof PDOException) {
                    error_log("  PDO Error Info: " . print_r($stmt_detalle->errorInfo(), true));
                }
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