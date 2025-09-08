<?php
// ==============================================
// CONFIGURACIÓN INICIAL
// ==============================================
$titulo = "Registro de Venta";
$encabezado = "Registrar Nueva Venta";
$subtitulo = "Complete el formulario para registrar una nueva venta";
$active_page = "ventas";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

// ==============================================
// CONEXIÓN A LA BASE DE DATOS
// ==============================================
try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ==============================================
// MANEJO DE PETICIONES AJAX
// ==============================================
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax_action']) {
            case 'get_colores':
                $id_especie = (int)$_GET['id_especie'];
                $stmt = $con->prepare("SELECT id_color, nombre_color FROM Colores WHERE id_especie = ? ORDER BY nombre_color");
                $stmt->execute([$id_especie]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'get_variedades':
                $id_especie = (int)$_GET['id_especie'];
                $id_color = (int)$_GET['id_color'];
                $stmt = $con->prepare("SELECT id_variedad, nombre_variedad, codigo FROM Variedades WHERE id_especie = ? AND id_color = ? ORDER BY nombre_variedad");
                $stmt->execute([$id_especie, $id_color]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            default:
                echo json_encode(['error' => 'Acción AJAX no válida']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// OBTENER DATOS PARA EL FORMULARIO
// ==============================================
$clientes = $con->query("SELECT id_cliente, nombre_Cliente FROM Clientes WHERE activo = 1 ORDER BY nombre_Cliente")->fetchAll();
$especies = $con->query("SELECT id_especie, nombre FROM Especies ORDER BY nombre")->fetchAll();
$cuentas_bancarias = $con->query("SELECT id_cuenta, nombre, banco, numero FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==============================================
// PROCESAMIENTO DEL FORMULARIO
// ==============================================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $con->beginTransaction();
        
        // Validaciones básicas
        if (empty($_POST['id_cliente'])) throw new Exception("Seleccione un cliente");
        if (empty($_POST['items'])) throw new Exception("Seleccione al menos un producto");
        
        $items = json_decode($_POST['items'], true);
        if (empty($items)) throw new Exception("Seleccione al menos un producto");
        if (empty($_POST['fecha_entrega'])) throw new Exception("Seleccione una fecha de entrega");
        if (empty($_POST['id_cuenta'])) throw new Exception("Seleccione una cuenta bancaria");
        
        // Calcular totales
        $total = 0;
        $items_venta = [];
        
        foreach ($items as $item) {
            if (empty($item['id_variedad']) || empty($item['cantidad']) || $item['cantidad'] <= 0) continue;
            
            // Verificar que la variedad existe
            $stmt_check = $con->prepare("SELECT COUNT(*) FROM Variedades WHERE id_variedad = ?");
            $stmt_check->execute([$item['id_variedad']]);
            if ($stmt_check->fetchColumn() == 0) {
                throw new Exception("La variedad seleccionada no existe");
            }

            $subtotal_item = (float)$item['precio_unitario'] * (int)$item['cantidad'];
            $total += $subtotal_item;
            
            $items_venta[] = [
                'id_variedad' => (int)$item['id_variedad'],
                'cantidad' => (int)$item['cantidad'],
                'precio' => (float)$item['precio_unitario'],
                'subtotal' => $subtotal_item
            ];
        }
        
        if ($total <= 0) throw new Exception("El total debe ser mayor a cero");
        
        // Validar tipo de pago
        $tipo_pago = $_POST['tipo_pago'];
        $anticipo = (float)($_POST['anticipo'] ?? 0);
        $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
        $id_cuenta = (int)$_POST['id_cuenta'];
        
        if ($tipo_pago === 'contado') {
            if ($anticipo != $total) throw new Exception("El pago debe ser igual al total en ventas de contado");
            $saldo_pendiente = 0;
            $estado = 'completado';
        } else {
            if ($anticipo > $total) throw new Exception("El anticipo no puede ser mayor al total");
            $saldo_pendiente = $total - $anticipo;
            $estado = $anticipo > 0 ? 'parcial' : 'pendiente';
        }
        
        // Generar folio y número de remisión
        $folio = 'NP-' . date('Y') . '-' . str_pad($con->query("SELECT COUNT(*) as total FROM NotasPedidos WHERE YEAR(fechaPedido) = YEAR(NOW())")->fetch()['total'] + 1, 5, '0', STR_PAD_LEFT);
        $num_remision = 'REM-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insertar Nota de Pedido
        $stmt = $con->prepare("
            INSERT INTO NotasPedidos (
                folio, fechaPedido, id_cliente, tipo_pago, metodo_Pago,
                total, saldo_pendiente, estado, observaciones, 
                num_pagare, fecha_validez, fecha_entrega, lugar_pago, id_cuenta, num_remision
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, 'Oficinas centrales', ?, ?)
        ");
        
        $stmt->execute([
            $folio,
            (int)$_POST['id_cliente'],
            $tipo_pago,
            $metodo_pago,
            $total,
            $saldo_pendiente,
            $estado,
            htmlspecialchars(trim($_POST['observaciones'] ?? '')),
            rand(1000, 9999),
            $_POST['fecha_entrega'],
            $id_cuenta,
            $num_remision
        ]);
        
        $id_notaPedido = $con->lastInsertId();
        
        // Insertar detalles de la nota - SECCIÓN CORREGIDA
        foreach ($items_venta as $item) {
            // Obtener todos los detalles necesarios
            $stmt_detalles = $con->prepare("
                SELECT 
                    c.nombre_color,
                    v.nombre_variedad,
                    e.nombre as nombre_especie
                FROM Variedades v
                JOIN Colores c ON v.id_color = c.id_color
                JOIN Especies e ON v.id_especie = e.id_especie
                WHERE v.id_variedad = ?
            ");
            $stmt_detalles->execute([$item['id_variedad']]);
            $detalles = $stmt_detalles->fetch(PDO::FETCH_ASSOC);

            if (!$detalles) {
                throw new Exception("No se pudieron obtener los detalles para la variedad ID: {$item['id_variedad']}");
            }

            // Calcular valores con validación
            $precio = (float)$item['precio'];
            $cantidad = (int)$item['cantidad'];
            $subtotal = $precio * $cantidad;
            $monto_total = $subtotal;

            if ($subtotal <= 0) {
                throw new Exception("Subtotal inválido para la variedad ID: {$item['id_variedad']}");
            }

            // Insertar con todos los campos requeridos
            $stmt_insert = $con->prepare("
                INSERT INTO DetallesNotaPedido (
                    id_notaPedido,
                    id_variedad,
                    color,
                    variedad,
                    especie,
                    cantidad,
                    precio_unitario,
                    subtotal,
                    monto_total,
                    fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $resultado = $stmt_insert->execute([
                $id_notaPedido,
                $item['id_variedad'],
                $detalles['nombre_color'],
                $detalles['nombre_variedad'],
                $detalles['nombre_especie'],
                $cantidad,
                $precio,
                $subtotal,
                $monto_total
            ]);

            if (!$resultado) {
                $errorInfo = $stmt_insert->errorInfo();
                throw new Exception("Error al insertar detalle: " . $errorInfo[2]);
            }
        }
        
        // Registrar anticipo si existe
        if ($anticipo > 0) {
            $stmt = $con->prepare("
                INSERT INTO SeguimientoAnticipos (
                    numero_venta, folio_anticipo, id_cliente, fecha_pago, 
                    monto_pago, metodo_pago, id_cuenta, comentarios, estado_pago
                ) VALUES (?, CONCAT('ANT-', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) FROM SeguimientoAnticipos WHERE YEAR(fecha_pago) = YEAR(NOW())) + 1, 5, '0')), 
                ?, NOW(), ?, ?, ?, 'Pago de venta', 'completado')
            ");
            
            $stmt->execute([
                $id_notaPedido,
                (int)$_POST['id_cliente'],
                $anticipo,
                $metodo_pago,
                $id_cuenta
            ]);
        }
        
        $con->commit();
        $_SESSION['success_message'] = "Venta registrada correctamente con folio #$folio";
        header("Location: lista_ventas.php");
        exit;
        
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

// El resto del archivo (HTML, JavaScript) permanece igual...
require __DIR__ . '/../../includes/header.php';
?>

<!-- [EL RESTO DEL CÓDIGO HTML/JS PERMANECE IGUAL] -->

<?php require __DIR__ . '/../../includes/footer.php'; ?>