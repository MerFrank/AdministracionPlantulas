<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

// Funciones auxiliares para generar números secuenciales
function generarFolioNotaPedido($con) {
    $year = date('Y');
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM notaspedidos WHERE YEAR(fechaPedido) = ?");
    $stmt->execute([$year]);
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    return 'NP-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
}

function generarNumeroRemision($con) {
    $stmt = $con->query("SELECT MAX(num_remision) as max_num FROM notaspedidos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Convertir a entero antes de sumar
    $max_num = (int)($result['max_num'] ?? 0);
    return $max_num + 1;
}

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener cotizaciones disponibles
$cotizaciones = $con->query("
    SELECT c.id_cotizacion, c.folio, c.fecha, cl.nombre_Cliente as cliente, c.total
    FROM cotizaciones c
    LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
    WHERE c.estado = 'pendiente'
    ORDER BY c.fecha DESC
")->fetchAll();

// Procesar selección de cotización
$cotizacion_seleccionada = null;
$detalles_cotizacion = [];

$cuentas_bancarias = $con->query("SELECT id_cuenta, nombre, banco, numero FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cotizacion'])) {
    $id_cotizacion = (int)$_POST['id_cotizacion'];
    
    // Obtener información de la cotización
    $stmt = $con->prepare("
        SELECT c.*, cl.nombre_Cliente as cliente_nombre, cl.telefono, cl.domicilio_fiscal as direccion
        FROM cotizaciones c
        LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
        WHERE c.id_cotizacion = ?
    ");
    $stmt->execute([$id_cotizacion]);
    $cotizacion_seleccionada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de la cotización
    $stmt = $con->prepare("
        SELECT dc.*, v.nombre_variedad as variedad_nombre, 
               e.nombre as especie, col.nombre_color as color
        FROM detallescotizacion dc
        JOIN variedades v ON dc.id_variedad = v.id_variedad
        JOIN especies e ON v.id_especie = e.id_especie
        JOIN colores col ON v.id_color = col.id_color
        WHERE dc.id_cotizacion = ?
    ");
    $stmt->execute([$id_cotizacion]);
    $detalles_cotizacion = $stmt->fetchAll();
}

// Procesar creación de venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_venta'])) {
    try {
        $con->beginTransaction();
        
        $id_cotizacion = (int)$_POST['id_cotizacion'];
        $id_cliente = (int)$_POST['id_cliente'];
        $tipo_pago = $_POST['tipo_pago'];
        $anticipo = (float)$_POST['anticipo'];
        $observaciones = htmlspecialchars(trim($_POST['observaciones']));
        
        // Validaciones
        if ($tipo_pago === 'credito' && $anticipo <= 0) {
            throw new Exception("Para ventas a crédito debe especificar un anticipo");
        }
        
        // Generar números secuenciales
        $folio = generarFolioNotaPedido($con);
        $num_remision = generarNumeroRemision($con);
        $total = (float)$_POST['total'];
        $saldo_pendiente = $tipo_pago === 'contado' ? 0 : ($total - $anticipo);
        $estado = $tipo_pago === 'contado' ? 'completado' : ($anticipo > 0 ? 'parcial' : 'pendiente');
        
        
        $ID_Operador = $_SESSION['ID_Operador'] ?? null;
        $id_cuenta = isset($_POST['id_cuenta']) ? (int)$_POST['id_cuenta'] : null;
        // Insertar Nota de Pedido
        $stmt = $con->prepare("
            INSERT INTO notaspedidos (
                folio, num_remision, fechaPedido, id_cliente, id_cotizacion, tipo_pago, metodo_Pago,
                subtotal, descuento, total, saldo_pendiente, estado, 
                observaciones, num_pagare, fecha_validez, fecha_entrega, lugar_pago, id_cuenta, ID_Operador
            ) VALUES (
                ?, ?, NOW(), ?, ?, ?, ?,
                ?, 0, ?, ?, ?,
                ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Oficinas centrales', 
                :id_cuenta, :num_remision, :id_cuenta, :ID_Operador)
        ");
        
        $stmt->execute([
            $folio,
            $num_remision,
            $id_cliente,
            $id_cotizacion,
            $tipo_pago,
            $_POST['metodo_pago'] ?? 'efectivo',
            $total,
            $total,
            $saldo_pendiente,
            $estado,
            $observaciones,
            rand(1000, 9999),
            $id_cuenta,
            $ID_Operador
        ]);
        
        $id_notaPedido = $con->lastInsertId();
        
        // Copiar detalles de cotización a nota de pedido
        foreach ($detalles_cotizacion as $detalle) {
            $stmt = $con->prepare("
                INSERT INTO detallesnotapedido (
                    id_notaPedido, id_variedad, cantidad, precio_unitario, 
                    subtotal, monto_total, color
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $id_notaPedido,
                $detalle['id_variedad'],
                $detalle['cantidad'],
                $detalle['precio_unitario'],
                $detalle['precio_unitario'],
                $detalle['cantidad'] * $detalle['precio_unitario'],
                $detalle['color']
            ]);
        }
        
        // Actualizar estado de cotización
        $stmt = $con->prepare("UPDATE cotizaciones SET estado = 'completado' WHERE id_cotizacion = ?");
        $stmt->execute([$id_cotizacion]);
        
        // Registrar anticipo si hay (usando la nueva tabla de pagos)
        if ($anticipo > 0) {
            if (empty($_POST['id_cuenta'])) {
                throw new Exception("Debe seleccionar una cuenta bancaria para registrar el pago.");
            }

            
            $stmt = $con->prepare("
                INSERT INTO pagosventas (
                    id_notaPedido, monto, fecha, metodo_pago, referencia, 
                    observaciones, id_operador, id_cuenta
                ) VALUES (
                    ?, ?, NOW(), ?, NULL, 
                    ?, ?, ?
                )
            ");
            
            $observaciones_pago = "Anticipo de venta desde cotización #$id_cotizacion";
            
            $stmt->execute([
                $id_notaPedido,
                $anticipo,
                $_POST['metodo_pago'],
                $observaciones_pago,
                $ID_Operador,
                $_POST['id_cuenta']
            ]);
        }
        
        $con->commit();
        
        $_SESSION['success_message'] = "Venta creada correctamente con folio #$id_notaPedido";
        header("Location: lista_ventas.php");
        exit;
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

$titulo = 'Venta desde Cotización';
$subtitulo = "Panel de administración de cotizaciones";
$ruta = "dashboard_ventas.php";
$texto_boton = "Regresar";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-cart-plus"></i> Crear Venta desde Cotización</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Paso 1: Seleccionar cotización -->
            <?php if (empty($cotizacion_seleccionada)): ?>
                <form method="post">
                    <div class="mb-4">
                        <h4 class="mb-3">1. Seleccione una cotización</h4>
                        <?php if (empty($cotizaciones)): ?>
                            <div class="alert alert-warning">No hay cotizaciones pendientes disponibles</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Seleccionar</th>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cotizaciones as $cotizacion): ?>
                                        <tr>
                                            <td>
                                                <input type="radio" name="id_cotizacion" value="<?= $cotizacion['id_cotizacion'] ?>" required>
                                            </td>
                                            <td><?= htmlspecialchars($cotizacion['folio']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($cotizacion['cliente']) ?></td>
                                            <td>$<?= number_format($cotizacion['total'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="lista_ventas.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                        <?php if (!empty($cotizaciones)): ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-right"></i> Continuar
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <!-- Paso 2: Confirmar y completar venta -->
                <form method="post">
                    <input type="hidden" name="id_cotizacion" value="<?= $cotizacion_seleccionada['id_cotizacion'] ?>">
                    <input type="hidden" name="id_cliente" value="<?= $cotizacion_seleccionada['id_cliente'] ?>">
                    <input type="hidden" name="total" value="<?= $cotizacion_seleccionada['total'] ?>">
                    <input type="hidden" name="crear_venta" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb-3">Información del Cliente</h4>
                            <div class="mb-3">
                                <label class="form-label">Nombre:</label>
                                <p class="form-control-static"><?= htmlspecialchars($cotizacion_seleccionada['cliente_nombre']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono:</label>
                                <p class="form-control-static"><?= htmlspecialchars($cotizacion_seleccionada['telefono'] ?? 'No especificado') ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección:</label>
                                <p class="form-control-static"><?= htmlspecialchars($cotizacion_seleccionada['direccion'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h4 class="mb-3">Detalles de Pago</h4>
                            <div class="mb-3">
                                <label class="form-label">Total:</label>
                                <p class="form-control-static">$<?= number_format($cotizacion_seleccionada['total'], 2) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                                <select class="form-select" name="tipo_pago" id="tipoPago" required>
                                    <option value="contado">Contado</option>
                                    <option value="credito">Crédito</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="anticipoContainer">
                                <label class="form-label">Anticipo <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="anticipo" step="0.01" min="0" value="0">
                                </div>
                            </div>
                            
                            <div class="mb-3" id="metodoPagoContainer">
                                    <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                    <select class="form-select" name="metodo_pago">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="tarjeta">Tarjeta</option>
                                    </select>
                                </div>

                                <div class="md-3">
                                <label class="form-label">Cuenta Bancaria <span class="text-danger">*</span></label>
                                <select class="form-select" name="id_cuenta" required>
                                    <option value="">Seleccione una cuenta...</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars("{$cuenta['banco']} - {$cuenta['nombre']} ({$cuenta['numero']})") ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="mb-3">Productos</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Especie</th>
                                        <th>Variedad</th>
                                        <th>Color</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unitario</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles_cotizacion as $detalle): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detalle['especie']) ?></td>
                                        <td><?= htmlspecialchars($detalle['variedad_nombre']) ?></td>
                                        <td><?= htmlspecialchars($detalle['color']) ?></td>
                                        <td><?= $detalle['cantidad'] ?></td>
                                        <td>$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                                        <td>$<?= number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-active">
                                        <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                        <td><strong>$<?= number_format($cotizacion_seleccionada['total'], 2) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="2"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" onclick="history.back()">
                            <i class="bi bi-arrow-left"></i> Regresar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Confirmar Venta
                        </button>
                    </div>
                </form>
                
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>