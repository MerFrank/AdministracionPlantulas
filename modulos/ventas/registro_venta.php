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
                $stmt = $con->prepare("SELECT id_color, nombre_color FROM colores WHERE id_especie = ? ORDER BY nombre_color");
                $stmt->execute([$id_especie]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'get_variedades':
                $id_especie = (int)$_GET['id_especie'];
                $id_color = (int)$_GET['id_color'];
                $stmt = $con->prepare("SELECT id_variedad, nombre_variedad, codigo FROM variedades WHERE id_especie = ? AND id_color = ? ORDER BY nombre_variedad");
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
$clientes = $con->query("SELECT id_cliente, nombre_Cliente FROM clientes WHERE activo = 1 ORDER BY nombre_Cliente")->fetchAll();
$especies = $con->query("SELECT id_especie, nombre FROM especies ORDER BY nombre")->fetchAll();
$cuentas_bancarias = $con->query("SELECT id_cuenta, nombre, banco, numero FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==============================================
// PROCESAMIENTO DEL FORMULARIO
// ==============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF (opcional pero recomendable)
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Token CSRF inválido.");
        }

        // Leer campos principales (acepta metodo_Pago o metodo_pago por compatibilidad)
        $fecha_entrega  = $_POST['fechaPedido'] ?? null; // en tu formulario pusiste name="fechaPedido"
        $tipo_pago      = $_POST['tipo_pago'] ?? null;
        $metodo_Pago    = $_POST['metodo_Pago'] ?? ($_POST['metodo_pago'] ?? null);
        $observaciones  = trim($_POST['observaciones'] ?? '');
        $id_cliente     = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
        $id_cuenta      = isset($_POST['id_cuenta']) ? (int)$_POST['id_cuenta'] : null;
        $opcion_garantia = $_POST['opcion'] ?? 'no'; // Obtener opción de garantía

        if ($id_cuenta <= 0) {
            throw new Exception("Debe seleccionar una cuenta bancaria válida.");
        }

        // Decodificar items JSON que envía tu JS (hidden input name="items")
        $items_json = $_POST['items'] ?? '[]';
        $items = json_decode($items_json, true);
        if (!is_array($items) || count($items) === 0) {
            throw new Exception("No se recibieron productos. Agrega al menos un producto.");
        }

        // Calcular totales SERVER-SIDE (no confiar en lo que venga del cliente)
        $subtotal_general = 0.0;
        $items_venta = [];
        foreach ($items as $it) {
            $cant = isset($it['cantidad']) ? (int)$it['cantidad'] : 0;
            $precio = isset($it['precio_unitario']) ? (float)$it['precio_unitario'] : ((isset($it['precio']) ? (float)$it['precio'] : 0.0));
            $id_var = isset($it['id_variedad']) ? (int)$it['id_variedad'] : null;
            $color_text = $it['color'] ?? ($it['nombre_color'] ?? '');
            if (!$id_var || $cant <= 0 || $precio <= 0) continue;
            $subtotal_item = $precio * $cant;
            $subtotal_general += $subtotal_item;

            $items_venta[] = [
                'id_variedad' => $id_var,
                'color' => $color_text,
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal_item
            ];
        }

        if ($subtotal_general <= 0) {
            throw new Exception("El total de la venta debe ser mayor a cero.");
        }

        // Anticipo (tu formulario puede mandar 'anticipo' o 'subtotal' como monto pagado)
        $anticipo = isset($_POST['anticipo']) ? (float)$_POST['anticipo'] : (isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.0);

        // Validaciones básicas
        if (!$fecha_entrega || !$tipo_pago || !$metodo_Pago || !$id_cliente) {
            throw new Exception("Faltan datos obligatorios para registrar la nota de pedido.");
        }
        if ($tipo_pago === 'contado' && abs($anticipo - $subtotal_general) > 0.01) {
            throw new Exception("En ventas de contado el monto pagado debe ser igual al total.");
        }
        if ($anticipo > $subtotal_general) {
            throw new Exception("El anticipo no puede ser mayor al total.");
        }

        // Determinar estado y saldo
        if ($tipo_pago === 'contado') {
            $saldo_pendiente = 0.00;
            $estado = 'completado';
        } else {
            $saldo_pendiente = round($subtotal_general - $anticipo, 2);
            $estado = $anticipo > 0 ? 'parcial' : 'pendiente';
        }

        // Validar datos de garantía si se seleccionó la opción
        if ($opcion_garantia === 'si') {
            $nom_bien = $_POST['nom-bien'] ?? '';
            $num_regis = $_POST['num-regis'] ?? '';
            $nom_aval = $_POST['nom-aval'] ?? '';
            $monto_garantia = $_POST['monto'] ?? 0;
            
            // Validar que los campos de garantía estén completos
            if (empty($nom_bien)) {
                throw new Exception("El campo 'Bien de intercambio' es requerido para garantía.");
            }
            if (empty($num_regis)) {
                throw new Exception("El campo 'Número de registro' es requerido para garantía.");
            }
            if (empty($nom_aval)) {
                throw new Exception("El campo 'Nombre del aval' es requerido para garantía.");
            }
            if (empty($monto_garantia) || $monto_garantia <= 0) {
                throw new Exception("El monto de la garantía debe ser mayor a cero.");
            }
        }

        // Iniciar transacción
        $con->beginTransaction();

        if ($anticipo > 0) {
            $stmt_update_cuenta = $con->prepare("
                UPDATE cuentas_bancarias 
                SET saldo_actual = saldo_actual + :monto 
                WHERE id_cuenta = :id_cuenta
            ");
            $stmt_update_cuenta->execute([
                ':monto' => $anticipo,
                ':id_cuenta' => $id_cuenta
            ]);
            
            // Verificar que se actualizó correctamente
            if ($stmt_update_cuenta->rowCount() === 0) {
                throw new Exception("No se pudo actualizar el saldo de la cuenta bancaria.");
            }
        }

        // Generar folio y num_remision
        $countRow = (int)$con->query("SELECT COUNT(*) as total FROM notaspedidos WHERE YEAR(fechaPedido) = YEAR(NOW())")->fetch(PDO::FETCH_ASSOC)['total'];
        $folio = 'NP-' . date('Y') . '-' . str_pad($countRow + 1, 5, '0', STR_PAD_LEFT);
        $num_remision = 'REM-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insertar nota 
        $stmt = $con->prepare("
            INSERT INTO notaspedidos (
                folio, fechaPedido, id_cliente, tipo_pago, metodo_Pago,
                subtotal, total, saldo_pendiente, estado, observaciones,
                num_pagare, fecha_validez, fecha_entrega, lugar_pago, id_cuenta, num_remision, ID_Operador
            ) VALUES (:folio, NOW(), :id_cliente, :tipo_pago, :metodo_Pago, :subtotal, :total, :saldo_pendiente, :estado, :observaciones,
                      :num_pagare, DATE_ADD(NOW(), INTERVAL 30 DAY), :fecha_entrega, 'Oficinas centrales', :id_cuenta, :num_remision, :ID_Operador)
        ");

        // id_cuenta puede venir del formulario si existe; usar null si no
        $id_cuenta = isset($_POST['id_cuenta']) ? (int)$_POST['id_cuenta'] : null;
        // Agregar el operador a la consulta
        $ID_Operador = $_SESSION['ID_Operador'] ?? null;

        $stmt->execute([
            ':folio' => $folio,
            ':id_cliente' => $id_cliente,
            ':tipo_pago' => $tipo_pago,
            ':metodo_Pago' => $metodo_Pago,
            ':subtotal' => $subtotal_general,
            ':total' => $subtotal_general, // aquí no aplico impuestos en este ejemplo
            ':saldo_pendiente' => $saldo_pendiente,
            ':estado' => $estado,
            ':observaciones' => $observaciones,
            ':num_pagare' => rand(1000, 9999),
            ':fecha_entrega' => $fecha_entrega,
            ':id_cuenta' => $id_cuenta,
            ':ID_Operador' => $ID_Operador, //ID del operador 
            ':num_remision' => $num_remision
        ]);

        $id_notaPedido = $con->lastInsertId();

        // Insertar detalles
        $stmt_det = $con->prepare("
            INSERT INTO detallesnotapedido
            (id_notaPedido, id_variedad, color, cantidad, precio_unitario, subtotal, monto_total)
            VALUES (:id_notaPedido, :id_variedad, :color, :cantidad, :precio_unitario, :subtotal, :monto_total)
        ");

        foreach ($items_venta as $it) {
            $stmt_det->execute([
                ':id_notaPedido' => $id_notaPedido,
                ':id_variedad' => $it['id_variedad'],
                ':color' => $it['color'],
                ':cantidad' => $it['cantidad'],
                ':precio_unitario' => $it['precio_unitario'],
                ':subtotal' => $it['subtotal'],
                ':monto_total' => $it['subtotal']
            ]);
        }

        // Insertar datos de garantía si se seleccionó la opción
        if ($opcion_garantia === 'si') {
            $nom_bien = $_POST['nom-bien'] ?? '';
            $num_regis = $_POST['num-regis'] ?? '';
            $nom_aval = $_POST['nom-aval'] ?? '';
            $monto_garantia = $_POST['monto'] ?? 0;
            
            $stmt_garantia = $con->prepare("
                INSERT INTO datos_garantia (id_notaPedido, nom_bien, num_regis, nom_aval, monto)
                VALUES (:id_notaPedido, :nom_bien, :num_regis, :nom_aval, :monto)
            ");
            
            $stmt_garantia->execute([
                ':id_notaPedido' => $id_notaPedido,
                ':nom_bien' => $nom_bien,
                ':num_regis' => $num_regis,
                ':nom_aval' => $nom_aval,
                ':monto' => $monto_garantia
            ]);
        }

        // Registrar pago en pagosventas (reemplazando seguimientoanticipos)
        if ($anticipo > 0) {
            $stmt_pago = $con->prepare("
                INSERT INTO pagosventas (id_notaPedido, monto, fecha, metodo_pago, referencia, observaciones, id_cuenta, ID_Operador)
                VALUES (:id_notaPedido, :monto, NOW(), :metodo_pago, :referencia, :observaciones, :id_cuenta, :ID_Operador)
            ");
            
            $referencia = 'PAG-' . date('Ymd') . '-' . str_pad($con->query("SELECT COUNT(*) FROM pagosventas WHERE DATE(fecha)=CURDATE()")->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
            
            $stmt_pago->execute([
                ':id_notaPedido' => $id_notaPedido,
                ':monto' => $anticipo,
                ':metodo_pago' => $metodo_Pago,
                ':referencia' => $referencia,
                ':observaciones' => 'Anticipo de venta',
                ':id_cuenta' => $id_cuenta,
                ':ID_Operador' => $ID_Operador //ID del operador 
            ]);
        }

        $con->commit();

        // Mensaje de éxito y redirección
        $_SESSION['success_message'] = "Venta registrada correctamente con folio #{$folio}";
        header("Location: lista_ventas.php");
        exit;

    } catch (Exception $e) {
        if ($con && $con->inTransaction()) $con->rollBack();
        // Devuelve el mensaje de error (lo puedes mostrar en pantalla)
        $error = "Error al guardar la nota de pedido: " . $e->getMessage();
    }
}


$ruta = "dashboard_ventas.php";
$texto_boton = "Regresar";

require __DIR__ . '/../../includes/header.php';
?>

<!-- ==============================================
// CONTENIDO PRINCIPAL
// ============================================== -->
<main class="container-fluid mt-4 px-0">    
    <div class="card shadow border-0 rounded-0">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-cart-plus"></i> <?= $encabezado ?></h2>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" id="ventaForm" class="form-doble-columna">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="items" id="itemsVenta" value="">
            
            <!-- Sección Cliente y Fecha -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_cliente" required>
                            <option value="">Seleccione un cliente...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['id_cliente'] ?>">
                                    <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Fecha de Entrega <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="fechaPedido" required 
                                min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Sección Productos -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h3 class="h5 mb-0"><i class="bi bi-list-check"></i> Productos</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="formItem">
                        <div class="col-md-4">
                            <label class="form-label">Especie <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectEspecie" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($especies as $especie): ?>
                                    <option value="<?= $especie['id_especie'] ?>">
                                        <?= htmlspecialchars($especie['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Color <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectColor" disabled required>
                                <option value="">Seleccione especie primero</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Variedad <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectVariedad" disabled required>
                                <option value="">Seleccione color primero</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="inputCantidad" min="1" value="1" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Precio Unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="inputPrecio" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" id="btnAgregarItem">
                                <i class="bi bi-plus-circle"></i> Agregar
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="table-responsive">
                            <table class="table table-striped" id="tablaItems">
                                <thead>
                                    <tr>
                                        <th>Especie</th>
                                        <th>Color</th>
                                        <th>Variedad</th>
                                        <th>Cantidad</th>
                                        <th>P. Unitario</th>
                                        <th>Subtotal</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyItems">
                                    <!-- Items dinámicos -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="text-end">Total:</th>
                                        <th id="totalVenta">$0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sección Pagos -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h3 class="h5 mb-0"><i class="bi bi-credit-card"></i> Información de Pago</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_pago" id="tipoPago" required>
                                <option value="Contado">Contado</option>
                                <option value="Crédito">Crédito</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="metodo_pago" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
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
                        
                        <div class="col-md-6" id="montoPagoContainer">
                            <label class="form-label" id="labelMontoPago">Monto de Pago <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="subtotal" id="inputMontoPago" step="0.01" min="0" required>
                            </div>
                            <small class="text-muted" id="ayudaMontoPago">Ingrese el monto total a pagar</small>
                        </div>
                        
                        <div class="col-md-6" id="saldoContainer" style="display:none;">
                            <label class="form-label">Saldo Pendiente</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" name="total" id="inputSaldoPendiente" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="mb-4">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="2"></textarea>
            </div>
            
            <!-- Garantia -->
            <div class="form-section">
                <h5><i class="bi bi-receipt"></i> Datos de Garantia</h5>
                
                <div class="mb-3">
                    <label class="form-label">¿Requiere Garantia?</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="opcion" id="opcion-si" value="si" 
                            <?= (isset($_POST['opcion']) && $_POST['opcion'] === 'si') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="opcion-si">Sí</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="opcion" id="opcion-no" value="no"
                            <?= (!isset($_POST['opcion']) || $_POST['opcion'] === 'no') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="opcion-no">No</label>
                    </div>
                </div>
                
                <div id="datos-garantia" class="bg-light p-3 rounded <?= (isset($_POST['opcion']) && $_POST['opcion'] === 'si') ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        
                        <div class="col-md-6">
                            <label for="nom-bien" class="form-label">Bien de intercambio</label>
                            <input type="text" class="form-control" id="nom-bien" name="nom-bien" maxlength="14" 
                                    placeholder="Carro, terreno, inmueble"
                                    value="<?= htmlspecialchars($_POST['nom-bien'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="num-regis" class="form-label">Número de registro o de factura</label>
                            <textarea class="form-control" id="num-regis" name="num-regis" maxlength="255"
                                        placeholder="XAXX010101000"><?= htmlspecialchars($_POST['num-regis'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="nom-aval" class="form-label">Nombre del aval</label>
                            <input type="text" class="form-control" id="nom-aval" name="nom-aval" maxlength="14" 
                                    value="<?= htmlspecialchars($_POST['nom-aval'] ?? '') ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-between">
            <a href="lista_ventas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle"></i> Registrar Venta
            </button>
        </div>
        
        </form>
        
        
    </div>
</main>

<!-- ==============================================
// SCRIPTS
// ============================================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

document.querySelectorAll('input[name="opcion"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const datosGarantia = document.getElementById('datos-garantia');
        if (this.value === 'si') {
            datosGarantia.classList.remove('d-none');
        } else {
            datosGarantia.classList.add('d-none');
        }
    });
});

// Estado inicial
document.addEventListener('DOMContentLoaded', function() {
    const datosGarantia = document.getElementById('datos-garantia');
    if (!document.getElementById('opcion-si').checked) {
        datosGarantia.classList.add('d-none');
    }
});


$(document).ready(function() {
    const items = [];
    const tipoPago = $('#tipoPago');
    const inputMontoPago = $('#inputMontoPago');
    const inputSaldoPendiente = $('#inputSaldoPendiente');
    
    // Manejo de tipo de pago
    tipoPago.on('change', function() {
        const esCredito = $(this).val() === 'credito';
        $('#saldoContainer').toggle(esCredito);
        $('#labelMontoPago').text(esCredito ? 'Anticipo' : 'Monto de Pago');
        $('#ayudaMontoPago').text(esCredito ? 'Ingrese el anticipo (opcional)' : 'Ingrese el monto total');
        inputMontoPago.prop('required', !esCredito);
        actualizarSaldo();
    });
    
    function actualizarSaldo() {
        const total = parseFloat($('#totalVenta').text().replace('$', '')) || 0;
        const monto = parseFloat(inputMontoPago.val()) || 0;
        
        if (tipoPago.val() === 'credito') {
            inputSaldoPendiente.val((total - monto).toFixed(2));
        } else {
            inputMontoPago.val(total.toFixed(2));
            inputSaldoPendiente.val('0.00');
        }
    }
    
    // Carga de colores y variedades
    $('#selectEspecie').on('change', function() {
        const idEspecie = $(this).val();
        const $selectColor = $('#selectColor');
        const $selectVariedad = $('#selectVariedad');
        
        if (!idEspecie) {
            $selectColor.prop('disabled', true).html('<option value="">Seleccione especie primero</option>');
            $selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            return;
        }
        
        $selectColor.prop('disabled', true).html('<option value="">Cargando colores...</option>');
        
        $.ajax({
            url: 'registro_venta.php',
            type: 'GET',
            dataType: 'json',
            data: {
                ajax_action: 'get_colores',
                id_especie: idEspecie
            },
            success: function(data) {
                if (data && data.length > 0) {
                    let options = '<option value="">Seleccione color</option>';
                    $.each(data, function(index, color) {
                        options += '<option value="' + color.id_color + '">' + color.nombre_color + '</option>';
                    });
                    $selectColor.html(options).prop('disabled', false);
                } else {
                    $selectColor.html('<option value="">No hay colores disponibles</option>').prop('disabled', false);
                }
                $selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar colores:', error);
                $selectColor.html('<option value="">Error al cargar colores</option>');
            }
        });
    });
    
    $('#selectColor').on('change', function() {
        const idColor = $(this).val();
        const idEspecie = $('#selectEspecie').val();
        const $selectVariedad = $('#selectVariedad');
        
        if (!idColor) {
            $selectVariedad.prop('disabled', true).html('<option value="">Seleccione color primero</option>');
            return;
        }
        
        $selectVariedad.prop('disabled', true).html('<option value="">Cargando variedades...</option>');
        
        $.ajax({
            url: 'registro_venta.php',
            type: 'GET',
            dataType: 'json',
            data: {
                ajax_action: 'get_variedades',
                id_especie: idEspecie,
                id_color: idColor
            },
            success: function(data) {
                if (data && data.length > 0) {
                    let options = '<option value="">Seleccione variedad</option>';
                    $.each(data, function(index, variedad) {
                        const texto = variedad.codigo ? variedad.nombre_variedad + ' (' + variedad.codigo + ')' : variedad.nombre_variedad;
                        options += '<option value="' + variedad.id_variedad + '">' + texto + '</option>';
                    });
                    $selectVariedad.html(options).prop('disabled', false);
                } else {
                    $selectVariedad.html('<option value="">No hay variedades disponibles</option>').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar variedades:', error);
                $selectVariedad.html('<option value="">Error al cargar variedades</option>');
            }
        });
    });
    
    // Agregar items
    $('#btnAgregarItem').on('click', function() {
        const $especie = $('#selectEspecie');
        const $color = $('#selectColor');
        const $variedad = $('#selectVariedad');
        const $cantidad = $('#inputCantidad');
        const $precio = $('#inputPrecio');
        
        // Validaciones mejoradas
        if (!$especie.val()) {
            alert('Seleccione una especie');
            $especie.focus();
            return;
        }
        if (!$color.val()) {
            alert('Seleccione un color');
            $color.focus();
            return;
        }
        if (!$variedad.val()) {
            alert('Seleccione una variedad');
            $variedad.focus();
            return;
        }
        if (!$cantidad.val() || parseInt($cantidad.val()) <= 0) {
            alert('Ingrese una cantidad válida (mayor a cero)');
            $cantidad.focus();
            return;
        }
        if (!$precio.val() || isNaN(parseFloat($precio.val()))) {
            alert('Ingrese un precio válido');
            $precio.focus();
            return;
        }
        
        const precioValue = parseFloat($precio.val());
        if (precioValue <= 0) {
            alert('El precio debe ser mayor a cero');
            $precio.focus();
            return;
        }
        
        // Crear item
        const item = {
            id_variedad: $variedad.val(),
            id_especie: $especie.val(),
            id_color: $color.val(),
            especie: $especie.find('option:selected').text(),
            color: $color.find('option:selected').text(),
            variedad: $variedad.find('option:selected').text(),
            cantidad: parseInt($cantidad.val()),
            precio_unitario: precioValue,
            subtotal: precioValue * parseInt($cantidad.val())
        };
        
        items.push(item);
        actualizarTablaItems();
        
        // Resetear campos
        $cantidad.val('1');
        $precio.val('');
        $precio.focus();
    });
    
    // Actualizar tabla de items
    function actualizarTablaItems() {
        const $tbody = $('#tbodyItems');
        $tbody.empty();
        let total = 0;
        
        items.forEach((item, index) => {
            total += item.subtotal;
            $tbody.append(`
                <tr data-index="${index}">
                    <td>${item.especie}</td>
                    <td>${item.color}</td>
                    <td>${item.variedad}</td>
                    <td>${item.cantidad}</td>
                    <td>$${item.precio_unitario.toFixed(2)}</td>
                    <td>$${item.subtotal.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger btnEliminarItem">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
        
        $('#totalVenta').text(`$${total.toFixed(2)}`);
        $('#itemsVenta').val(JSON.stringify(items));
        actualizarSaldo();
    }
    
    // Eliminar items
    $('#tbodyItems').on('click', '.btnEliminarItem', function() {
        const index = $(this).closest('tr').data('index');
        if (confirm('¿Eliminar este item?')) {
            items.splice(index, 1);
            actualizarTablaItems();
        }
    });
    
    // Validar formulario
    $('#ventaForm').on('submit', function(e) {
        if (items.length === 0) {
            e.preventDefault();
            alert('Agregue al menos un producto');
            return;
        }
        
        const total = parseFloat($('#totalVenta').text().replace('$', ''));
        const monto = parseFloat(inputMontoPago.val()) || 0;
        
        if (tipoPago.val() === 'contado' && monto !== total) {
            e.preventDefault();
            alert('El pago debe ser igual al total en ventas de contado');
            return;
        }
        
        if (tipoPago.val() === 'credito' && monto > total) {
            e.preventDefault();
            alert('El anticipo no puede ser mayor al total');
            return;
        }
    });
    
    // Inicializar
    tipoPago.trigger('change');
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>