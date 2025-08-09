<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

// Verificar permisos y autenticación
// if (!isset($_SESSION['usuario_id'])) {
//     header('Location: /login.php');
//     exit;
// }

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener ID de la venta a editar
$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_venta <= 0) {
    header('Location: lista_ventas.php');
    exit;
}

// Obtener datos de la venta
$venta = $con->query("
    SELECT np.*, c.nombre_Cliente 
    FROM notaspedidos np
    LEFT JOIN clientes c ON np.id_cliente = c.id_cliente
    WHERE np.id_notaPedido = $id_venta
")->fetch();

if (!$venta) {
    $_SESSION['error_message'] = "Venta no encontrada";
    header('Location: lista_ventas.php');
    exit;
}

// Obtener detalles de la venta
$detalles_venta = $con->query("
    SELECT d.*, v.nombre_variedad, e.nombre as especie
    FROM detallesnotapedido d
    JOIN variedades v ON d.id_variedad = v.id_variedad
    JOIN especies e ON v.id_especie = e.id_especie
    WHERE d.id_notaPedido = $id_venta
")->fetchAll();

// Obtener abonos registrados
$abonos = $con->query("
    SELECT * FROM seguimientoanticipos
    WHERE numero_venta = $id_venta
    ORDER BY fecha_pago DESC
")->fetchAll();

// Calcular total abonado
$total_abonado = array_sum(array_column($abonos, 'monto_pago'));

// Obtener datos para formulario
$clientes = $con->query("SELECT id_cliente, nombre_Cliente FROM clientes WHERE activo = 1 ORDER BY nombre_Cliente")->fetchAll();
// falta v.precio
$variedades = $con->query("
    SELECT v.id_variedad, v.nombre_variedad, v.codigo, e.nombre as especie, c.nombre_color as color
    FROM variedades v
    JOIN especies e ON v.id_especie = e.id_especie
    JOIN colores c ON v.id_color = c.id_color
    ORDER BY e.nombre, v.nombre_variedad
")->fetchAll();

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario de edición
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $con->beginTransaction();
        
        // Validar datos básicos
        if (empty($_POST['id_cliente'])) {
            throw new Exception("Seleccione un cliente");
        }
        
        // Actualizar datos principales de la venta
        $stmt = $con->prepare("
            UPDATE notaspedidos SET
                id_cliente = ?,
                tipo_pago = ?,
                metodo_Pago = ?,
                observaciones = ?,
                estado = ?
            WHERE id_notaPedido = ?
        ");
        
        $stmt->execute([
            (int)$_POST['id_cliente'],
            $_POST['tipo_pago'],
            $_POST['metodo_pago'] ?? 'efectivo',
            htmlspecialchars(trim($_POST['observaciones'] ?? '')),
            $_POST['estado'],
            $id_venta
        ]);
        
        // Procesar nuevo abono si existe
        if (isset($_POST['nuevo_abono']) && $_POST['nuevo_abono'] > 0) {
            $monto_abono = (float)$_POST['nuevo_abono'];
            
            $stmt = $con->prepare("
                INSERT INTO seguimientoanticipos (
                    numero_venta, folio_anticipo, id_cliente, fecha_pago, 
                    monto_pago, metodo_pago, comentarios, estado_pago
                ) VALUES (
                    ?, CONCAT('ANT-', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) FROM seguimientoanticipos WHERE YEAR(fecha_pago) = YEAR(NOW())) + 1, 5, '0')), 
                    ?, NOW(), ?, ?, 'Abono registrado desde edición', 'completado'
                )
            ");
            
            $stmt->execute([
                $id_venta,
                (int)$_POST['id_cliente'],
                $monto_abono,
                $_POST['metodo_pago_abono'] ?? 'efectivo'
            ]);
            
            // Actualizar saldo pendiente
            $nuevo_saldo = max($venta['saldo_pendiente'] - $monto_abono, 0);
            
            // Actualizar estado según el saldo
            $nuevo_estado = ($nuevo_saldo <= 0) ? 'completado' : 'parcial';
            
            $con->query("
                UPDATE notaspedidos SET 
                    saldo_pendiente = $nuevo_saldo,
                    estado = '$nuevo_estado'
                WHERE id_notaPedido = $id_venta
            ");
        }
        
        $con->commit();
        
        $_SESSION['success_message'] = "Venta actualizada correctamente";
        header("Location: ver_venta.php?id=$id_venta");
        exit;
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

$titulo = 'Editar Venta #' . $venta['id_notaPedido'];
$ruta = "lista_ventas.php";
$texto_boton = "";
require __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-pencil-square"></i> Editar Venta #<?= $venta['id_notaPedido'] ?></h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post" id="editarVentaForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row g-3">
                    <!-- Datos básicos de la venta -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_cliente" required>
                                <option value="">Seleccione un cliente...</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id_cliente'] ?>" <?= ($venta['id_cliente'] == $cliente['id_cliente'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" name="estado" required>
                                <option value="pendiente" <?= ($venta['estado'] == 'pendiente' ? 'selected' : '') ?>>Pendiente</option>
                                <option value="parcial" <?= ($venta['estado'] == 'parcial' ? 'selected' : '') ?>>Parcial</option>
                                <option value="completado" <?= ($venta['estado'] == 'completado' ? 'selected' : '') ?>>Completado</option>
                                <option value="cancelado" <?= ($venta['estado'] == 'cancelado' ? 'selected' : '') ?>>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_pago" required>
                                <option value="contado" <?= ($venta['tipo_pago'] == 'contado' ? 'selected' : '') ?>>Contado</option>
                                <option value="credito" <?= ($venta['tipo_pago'] == 'credito' ? 'selected' : '') ?>>Crédito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago">
                                <option value="efectivo" <?= ($venta['metodo_Pago'] == 'efectivo' ? 'selected' : '') ?>>Efectivo</option>
                                <option value="transferencia" <?= ($venta['metodo_Pago'] == 'transferencia' ? 'selected' : '') ?>>Transferencia</option>
                                <option value="tarjeta" <?= ($venta['metodo_Pago'] == 'tarjeta' ? 'selected' : '') ?>>Tarjeta</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Resumen de la venta -->
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h3 class="h5 mb-0">Resumen de la Venta</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fechaPedido'])) ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong>Folio:</strong> <?= htmlspecialchars($venta['num_remision']) ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong>Total:</strong> $<?= number_format($venta['total'], 2) ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($venta['tipo_pago'] == 'credito'): ?>
                                <div class="row mt-2">
                                    <div class="col-md-4">
                                        <p><strong>Saldo Pendiente:</strong> $<?= number_format($venta['saldo_pendiente'], 2) ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong>Total Abonado:</strong> $<?= number_format($total_abonado, 2) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detalles de la venta -->
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h3 class="h5 mb-0">Productos Vendidos</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Precio Unitario</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detalles_venta as $detalle): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($detalle['especie'] . ' - ' . $detalle['nombre_variedad']) ?></td>
                                                <td><?= $detalle['cantidad'] ?></td>
                                                <td>$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                                                <td>$<?= number_format($detalle['monto_total'], 2) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Abonos (solo para ventas a crédito) -->
                    <?php if ($venta['tipo_pago'] == 'credito'): ?>
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h3 class="h5 mb-0">Registrar Nuevo Abono</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Monto del Abono</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" name="nuevo_abono" min="0" max="<?= $venta['saldo_pendiente'] ?>" step="0.01" value="0">
                                            </div>
                                            <small class="text-muted">Máximo: $<?= number_format($venta['saldo_pendiente'], 2) ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Método de Pago</label>
                                            <select class="form-select" name="metodo_pago_abono">
                                                <option value="efectivo">Efectivo</option>
                                                <option value="transferencia">Transferencia</option>
                                                <option value="tarjeta">Tarjeta</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Historial de abonos -->
                                <?php if (!empty($abonos)): ?>
                                <div class="mt-4">
                                    <h4 class="h6">Historial de Abonos</h4>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Folio</th>
                                                    <th>Monto</th>
                                                    <th>Método</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($abonos as $abono): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($abono['fecha_pago'])) ?></td>
                                                    <td><?= htmlspecialchars($abono['folio_anticipo']) ?></td>
                                                    <td>$<?= number_format($abono['monto_pago'], 2) ?></td>
                                                    <td><?= ucfirst($abono['metodo_pago']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Observaciones -->
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($venta['observaciones']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="ver_venta.php?id=<?= $id_venta ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>