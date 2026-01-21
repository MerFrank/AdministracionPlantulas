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

// Función para convertir fecha de DD/MM/AAAA a AAAA-MM-DD
function convertirFecha($fecha_dd_mm_aaaa) {
    $partes = explode('/', $fecha_dd_mm_aaaa);
    if (count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return date('Y-m-d'); // Fecha actual por defecto
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar que existan los datos necesarios
        if (!isset($_POST['general']) || !isset($_POST['detalle'])) {
            throw new Exception("Datos incompletos para guardar la nómina.");
        }
        
        $general = $_POST['general'];
        $detalle = $_POST['detalle'];
        
        // Validar datos generales
        if (empty($general['fecha_inicio']) || empty($general['fecha_fin']) || empty($general['id_cuenta'])) {
            throw new Exception("Faltan datos generales de la nómina.");
        }
        
        // Validar que haya detalles de empleados
        if (empty($detalle) || !is_array($detalle)) {
            throw new Exception("No hay datos de empleados para guardar.");
        }
        
        // Convertir fechas al formato de BD
        $fecha_inicio_db = convertirFecha($general['fecha_inicio']);
        $fecha_fin_db = convertirFecha($general['fecha_fin']);
        
        // Iniciar transacción para asegurar que todo se guarde o nada
        $pdo->beginTransaction();
        
        // ============================================
        // 1. INSERTAR EN NÓMINA GENERAL
        // ============================================
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
            ) VALUES (
                :fecha_inicio, 
                :fecha_fin, 
                :empleados_pagados, 
                :total_sueldos, 
                :total_actividades_extras, 
                :total_deducciones, 
                :total_a_pagar, 
                :id_cuenta, 
                :id_operador, 
                NOW()
            )
        ");
        
        // Asignar valores
        $stmt_general->bindValue(':fecha_inicio', $fecha_inicio_db);
        $stmt_general->bindValue(':fecha_fin', $fecha_fin_db);
        $stmt_general->bindValue(':empleados_pagados', intval($general['empleados_pagados'] ?? 0));
        $stmt_general->bindValue(':total_sueldos', floatval($general['total_sueldos'] ?? 0));
        $stmt_general->bindValue(':total_actividades_extras', floatval($general['total_actividades_extras'] ?? 0));
        $stmt_general->bindValue(':total_deducciones', floatval($general['total_deducciones'] ?? 0));
        $stmt_general->bindValue(':total_a_pagar', floatval($general['total_a_pagar'] ?? 0));
        $stmt_general->bindValue(':id_cuenta', intval($general['id_cuenta']));
        $stmt_general->bindValue(':id_operador', $id_operador);
        
        // Ejecutar inserción general
        if (!$stmt_general->execute()) {
            throw new Exception("Error al guardar la nómina general: " . implode(", ", $stmt_general->errorInfo()));
        }
        
        // Obtener el ID de la nómina general recién insertada
        $id_nomina_general = $pdo->lastInsertId();
        
        // ============================================
        // 2. INSERTAR DETALLES POR EMPLEADO
        // ============================================
        $empleados_procesados = 0;
        $errores_detalle = [];
        
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
            ) VALUES (
                :id_nomina_general, 
                :id_empleado, 
                :dias_laborados, 
                :sueldo_base, 
                :actividades_extras, 
                :deducciones, 
                :total_pagar, 
                :id_operador, 
                NOW()
            )
        ");
        
        // Procesar cada empleado
        foreach ($detalle as $index => $empleado) {
            try {
                // Validar datos del empleado
                if (empty($empleado['id_empleado_real']) || empty($empleado['nombre_completo'])) {
                    $errores_detalle[] = "Empleado #$index: Datos incompletos";
                    continue;
                }
                
                // Usar id_empleado_real (de BD) en lugar de id_checador
                $id_empleado_db = $empleado['id_empleado_real'];
                
                $stmt_detalle->bindValue(':id_nomina_general', $id_nomina_general);
                $stmt_detalle->bindValue(':id_empleado', $id_empleado_db);
                $stmt_detalle->bindValue(':dias_laborados', intval($empleado['dias_laborados'] ?? 0));
                $stmt_detalle->bindValue(':sueldo_base', floatval($empleado['sueldo_base'] ?? 0));
                $stmt_detalle->bindValue(':actividades_extras', floatval($empleado['actividades_extras'] ?? 0));
                $stmt_detalle->bindValue(':deducciones', floatval($empleado['deducciones'] ?? 0));
                $stmt_detalle->bindValue(':total_pagar', floatval($empleado['total_pagar'] ?? 0));
                $stmt_detalle->bindValue(':id_operador', $id_operador);
                
                if (!$stmt_detalle->execute()) {
                    $errores_detalle[] = "Empleado " . ($empleado['nombre_completo'] ?? "Desconocido") . ": Error en BD";
                } else {
                    $empleados_procesados++;
                }
                
            } catch (Exception $e) {
                $errores_detalle[] = "Empleado #$index: " . $e->getMessage();
            }
        }
        
        // Verificar si se procesaron empleados
        if ($empleados_procesados == 0) {
            throw new Exception("No se pudo guardar ningún empleado. Errores: " . implode("; ", $errores_detalle));
        }
        
        // ============================================
        // 3. CONFIRMAR TRANSACCIÓN
        // ============================================
        $pdo->commit();
        
        // ============================================
        // 4. PREPARAR MENSAJE DE ÉXITO
        // ============================================
        $mensaje_exito = "¡Nómina guardada exitosamente!<br>";
        $mensaje_exito .= "<strong>ID de Nómina:</strong> $id_nomina_general<br>";
        $mensaje_exito .= "<strong>Período:</strong> " . htmlspecialchars($general['fecha_inicio']) . " al " . htmlspecialchars($general['fecha_fin']) . "<br>";
        $mensaje_exito .= "<strong>Empleados procesados:</strong> $empleados_procesados<br>";
        $mensaje_exito .= "<strong>Total a pagar:</strong> $" . number_format(floatval($general['total_a_pagar'] ?? 0), 2);
        
        // Si hubo errores en algunos detalles, agregar advertencia
        if (!empty($errores_detalle)) {
            $mensaje_exito .= "<br><br><strong>Advertencias:</strong> Hubo problemas con " . count($errores_detalle) . " empleados.";
        }
        
        // Guardar mensaje en sesión para mostrar después
        $_SESSION['success_message'] = $mensaje_exito;
        $_SESSION['nomina_guardada_id'] = $id_nomina_general;
        
        // Redirigir a la página de éxito o de vuelta al generador
        header('Location: generar_nomina.php?guardado=exito&id=' . $id_nomina_general);
        exit;
        
    } catch (Exception $e) {
        // Si hay error, hacer rollback de la transacción
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Guardar error en sesión
        $_SESSION['error_message'] = "Error al guardar la nómina: " . $e->getMessage();
        
        // Redirigir de vuelta con error
        header('Location: generar_nomina.php');
        exit;
    }
} else {
    // Si alguien intenta acceder directamente sin POST, redirigir
    $_SESSION['error_message'] = "Acceso no válido.";
    header('Location: generar_nomina.php');
    exit;
}