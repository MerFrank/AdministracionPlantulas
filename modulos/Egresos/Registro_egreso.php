<?php

require_once(__DIR__ . '/../../includes/validacion_session.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once __DIR__ . '/../../includes/config.php';

function generarNumeroComprobante($con) {
    $stmt = $con->query("SELECT MAX(id_egreso) as ultimo_id FROM egresos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimo_id = $result['ultimo_id'] ?? 0;
    return 'EG-' . str_pad($ultimo_id + 1, 6, '0', STR_PAD_LEFT);
}

try {
    $db = new Database();
    $con = $db->conectar();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Cargar datos para los select
$proveedores = $con->query("SELECT id_proveedor, nombre_proveedor AS nombre FROM proveedores WHERE activo = 1 ORDER BY nombre_proveedor")->fetchAll();
$sucursales = $con->query("SELECT id_sucursal, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$tiposEgreso = $con->query("SELECT id_tipo, nombre FROM tipos_egreso WHERE activo = 1 ORDER BY nombre")->fetchAll();
$cuentas = $con->query("SELECT id_cuenta, nombre, numero, saldo_actual FROM cuentas_bancarias WHERE activo = 1 ORDER BY nombre")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
$error = '';
$numero_comprobante = generarNumeroComprobante($con);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }

    try {
        $con->beginTransaction();

        // Validar campos requeridos
        $camposRequeridos = [
            'fecha' => 'Fecha',
            'id_sucursal' => 'Sucursal',
            'id_cuenta_origen' => 'Cuenta de origen',
            'monto' => 'Monto',
            'comprobante' => 'Número de comprobante',
            'concepto' => 'Concepto'
        ];

        foreach ($camposRequeridos as $campo => $nombre) {
            if (empty($_POST[$campo])) {
                throw new Exception("El campo $nombre es requerido");
            }
        }

        // Validaciones específicas
        if (!is_numeric($_POST['monto']) || $_POST['monto'] <= 0) {
            throw new Exception("El monto debe ser un número positivo");
        }

        if ($_POST['tipo_operacion'] === 'reembolso') {
            if (empty($_POST['id_cuenta_destino'])) {
                throw new Exception("Para reembolsos debe seleccionar una cuenta destino");
            }
            if ($_POST['id_cuenta_origen'] == $_POST['id_cuenta_destino']) {
                throw new Exception("La cuenta destino debe ser diferente a la cuenta origen en reembolsos");
            }
        }

        // Validar saldo en cuenta origen
        $id_cuenta_origen = (int)$_POST['id_cuenta_origen'];
        $monto = (float)$_POST['monto'];

        $stmtSaldo = $con->prepare("SELECT saldo_actual FROM cuentas_bancarias WHERE id_cuenta = ? FOR UPDATE");
        $stmtSaldo->execute([$id_cuenta_origen]);
        $saldoActual = $stmtSaldo->fetchColumn();

        if ($saldoActual < $monto) {
            throw new Exception("Saldo insuficiente en la cuenta seleccionada. Saldo disponible: $" . number_format($saldoActual, 2));
        }

        $stmtTipoCuenta = $con->prepare("SELECT tipo_cuenta FROM cuentas_bancarias WHERE id_cuenta = ?");
        $stmtTipoCuenta->execute([$id_cuenta_origen]);
        $tipoCuenta = $stmtTipoCuenta->fetchColumn();
        
        $metodo_pago = ($tipoCuenta === 'Efectivo') ? 'efectivo' : 'transferencia';

        // Preparar datos para inserción
        $datos = [
            'fecha' => $_POST['fecha'],
            'id_tipo_egreso' => ($_POST['tipo_operacion'] === 'reembolso') ? null : (int)$_POST['id_tipo_egreso'],
            'id_proveedor' => ($_POST['tipo_operacion'] === 'reembolso') ? null : (!empty($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : null),
            'id_sucursal' => (int)$_POST['id_sucursal'],
            'id_cuenta_origen' => $id_cuenta_origen,
            'id_cuenta_destino' => ($_POST['tipo_operacion'] === 'reembolso') ? (int)$_POST['id_cuenta_destino'] : null,
            'monto' => $monto,
            'comprobante' => $_POST['comprobante'],
            'concepto' => htmlspecialchars(trim($_POST['concepto'])),
            'tipo_operacion' => $_POST['tipo_operacion'],
            'metodo_pago' => $metodo_pago
        ];

        // Insertar el egreso
        $sql = "INSERT INTO egresos (
                    fecha, id_tipo_egreso, id_proveedor, id_sucursal, 
                    id_cuenta, monto, comprobante, concepto,
                    tipo_operacion, id_cuenta_destino, metodo_pago
                ) VALUES (
                    :fecha, :id_tipo_egreso, :id_proveedor, :id_sucursal, 
                    :id_cuenta_origen, :monto, :comprobante, :concepto,
                    :tipo_operacion, :id_cuenta_destino, :metodo_pago
                )";

        $stmt = $con->prepare($sql);
        $stmt->execute($datos);
        $id_egreso = $con->lastInsertId();

        // Actualizar saldos
        if ($datos['tipo_operacion'] === 'reembolso') {
            // Sumar a cuenta destino
            $sqlUpdate = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual + :monto 
                         WHERE id_cuenta = :id_cuenta";
            $stmtUpdate = $con->prepare($sqlUpdate);
            $stmtUpdate->execute([
                'monto' => $datos['monto'],
                'id_cuenta' => $datos['id_cuenta_destino']
            ]);
        }
        
        // Restar de cuenta origen
        $sqlUpdate = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual - :monto 
                     WHERE id_cuenta = :id_cuenta";
        $stmtUpdate = $con->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'monto' => $datos['monto'],
            'id_cuenta' => $datos['id_cuenta_origen']
        ]);

        $con->commit();

        $_SESSION['success_message'] = 'Operación registrada correctamente';
        header('Location: lista_egresos.php');
        exit;
    } catch (Exception $e) {
        $con->rollBack();
        $error = $e->getMessage();
    }
}

$ruta = "dashboard_egresos.php";
$texto_boton = "Regresar";
require('../../includes/header.php');
?>

<!-- Modal para nuevo tipo de egreso -->
<div class="modal fade" id="modalTipoEgreso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nuevo Tipo de Egreso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formTipoEgreso" action="./tipos_egreso/registro_tipo.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="mb-3">
            <label class="form-label">Nombre del Tipo</label>
            <input type="text" class="form-control" name="nombre" required>
          </div>
          <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"></textarea>
                </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<main class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="bi bi-cash-coin"></i> Registrar Egreso</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="egresoForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="tipo_operacion" id="tipoOperacion" value="egreso">
                <input type="hidden" name="metodo_pago" value="transferencia">
                
                <div class="row g-3">
                    <!-- Columna Izquierda -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha" required 
                                   value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Operación <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectTipoOperacion" required>
                                <option value="egreso" <?= (isset($_POST['tipo_operacion']) && $_POST['tipo_operacion'] === 'egreso') ? 'selected' : '' ?>>Egreso Normal</option>
                                <option value="reembolso" <?= (isset($_POST['tipo_operacion']) && $_POST['tipo_operacion'] === 'reembolso') ? 'selected' : '' ?>>Reembolso a Caja Chica</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="tipoEgresoContainer">
                            <label class="form-label">Tipo de Egreso <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_tipo_egreso" id="tipoEgreso" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($tiposEgreso as $tipo): ?>
                                    <option value="<?= $tipo['id_tipo'] ?>" <?= (isset($_POST['id_tipo_egreso']) && $_POST['id_tipo_egreso'] == $tipo['id_tipo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalTipoEgreso">
                                <i class="bi bi-plus"></i> Nuevo Tipo
                            </button>
                        </div>
                        
                        <div class="mb-3" id="proveedorContainer">
                            <label class="form-label">Proveedor</label>
                            <select class="form-select" name="id_proveedor" id="selectProveedor">
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= $prov['id_proveedor'] ?>" <?= (isset($_POST['id_proveedor']) && $_POST['id_proveedor'] == $prov['id_proveedor']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Columna Derecha -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Sucursal <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_sucursal" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= $suc['id_sucursal'] ?>" <?= (isset($_POST['id_sucursal']) && $_POST['id_sucursal'] == $suc['id_sucursal']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($suc['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">N° Comprobante <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="comprobante" value="<?= $numero_comprobante ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monto (MXN) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="monto" step="0.01" min="0.01" placeholder="0.00" required 
                                       value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos de Cuentas -->
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Cuenta de Origen <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_cuenta_origen" id="selectCuentaOrigen" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>" data-saldo="<?= $cuenta['saldo_actual'] ?>" <?= (isset($_POST['id_cuenta_origen']) && $_POST['id_cuenta_origen'] == $cuenta['id_cuenta']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cuenta['nombre']) ?> (<?= htmlspecialchars($cuenta['numero']) ?>) - Saldo: $<?= number_format($cuenta['saldo_actual'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Seleccione una cuenta con saldo suficiente</small>
                        </div>
                        
                        <div class="mb-3" id="cuentaDestinoContainer" style="display: none;">
                            <label class="form-label">Cuenta de Destino (Reembolso) <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_cuenta_destino" id="selectCuentaDestino">
                                <option value="">Seleccione...</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                    <option value="<?= $cuenta['id_cuenta'] ?>" <?= (isset($_POST['id_cuenta_destino']) && $_POST['id_cuenta_destino'] == $cuenta['id_cuenta']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cuenta['nombre']) ?> (<?= htmlspecialchars($cuenta['numero']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Concepto al final -->
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Concepto <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="concepto" rows="2" required><?= htmlspecialchars($_POST['concepto'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <a href="lista_egresos.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="btnRegistrar"> 
                        <i class="bi bi-save"></i> Registrar Operación
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require('../../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const tipoOperacion = document.getElementById('selectTipoOperacion');
    const tipoOperacionHidden = document.getElementById('tipoOperacion');
    const tipoEgresoContainer = document.getElementById('tipoEgresoContainer');
    const proveedorContainer = document.getElementById('proveedorContainer');
    const cuentaDestinoContainer = document.getElementById('cuentaDestinoContainer');
    const btnRegistrar = document.getElementById('btnRegistrar');
    const inputMonto = document.querySelector('input[name="monto"]');
    const selectCuentaOrigen = document.getElementById('selectCuentaOrigen');
    const selectCuentaDestino = document.getElementById('selectCuentaDestino');
    const form = document.getElementById('egresoForm');
    const tipoEgreso = document.getElementById('tipoEgreso');
    const selectProveedor = document.getElementById('selectProveedor');

    // Función para actualizar campos visibles
    function actualizarCampos() {
        const esReembolso = tipoOperacion.value === 'reembolso';
        tipoOperacionHidden.value = tipoOperacion.value;
        
        // Mostrar/ocultar campos según tipo de operación
        tipoEgresoContainer.style.display = esReembolso ? 'none' : 'block';
        cuentaDestinoContainer.style.display = esReembolso ? 'block' : 'none';
        
        
        // Para egresos normales, verificar si es tipo "proveedor"
        if (!esReembolso) {
            const selectedOption = tipoEgreso.options[tipoEgreso.selectedIndex];
            const esProveedor = selectedOption.text.toLowerCase().includes('proveedor');
            
            // Actualizar atributo required
            tipoEgreso.required = true;
            
            if (!esProveedor) {
                selectProveedor.value = '';
            }
            
            // Mostrar mensaje si es pago a proveedor en efectivo
            if (esProveedor && selectCuentaOrigen.value) {
                const tipoCuenta = selectCuentaOrigen.options[selectCuentaOrigen.selectedIndex].text;
                if (tipoCuenta.includes('Efectivo')) {
                    // Puedes mostrar un mensaje o realizar alguna acción específica
                    console.log("Este es un pago en efectivo a proveedor");
                }
            }
        } else {
            proveedorContainer.style.display = 'none';
            selectProveedor.value = '';
            
            // Quitar required para reembolsos
            tipoEgreso.required = false;
            selectProveedor.required = false;
        }
    }

    // Validar saldo en tiempo real
    function validarSaldo() {
        if (selectCuentaOrigen.value && inputMonto.value) {
            const saldo = parseFloat(selectCuentaOrigen.options[selectCuentaOrigen.selectedIndex].dataset.saldo);
            const monto = parseFloat(inputMonto.value);
            
            if (monto > saldo) {
                inputMonto.classList.add('is-invalid');
                const feedback = inputMonto.nextElementSibling || document.createElement('small');
                feedback.className = 'invalid-feedback';
                feedback.textContent = `Saldo insuficiente. Disponible: $${saldo.toFixed(2)}`;
                inputMonto.parentNode.appendChild(feedback);
                return false;
            } else {
                inputMonto.classList.remove('is-invalid');
                const feedback = inputMonto.nextElementSibling;
                if (feedback && feedback.className === 'invalid-feedback') {
                    feedback.remove();
                }
                return true;
            }
        }
        return true;
    }

    // Validar que cuenta destino sea diferente a origen
    function validarCuentasDiferentes() {
        if (tipoOperacion.value === 'reembolso') {
            if (!selectCuentaDestino.value) {
                selectCuentaDestino.classList.add('is-invalid');
                const feedback = selectCuentaDestino.nextElementSibling || document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'Seleccione una cuenta destino';
                selectCuentaDestino.parentNode.appendChild(feedback);
                return false;
            }
            
            if (selectCuentaOrigen.value && selectCuentaDestino.value && 
                selectCuentaOrigen.value === selectCuentaDestino.value) {
                selectCuentaDestino.classList.add('is-invalid');
                const feedback = selectCuentaDestino.nextElementSibling || document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'La cuenta destino debe ser diferente a la cuenta origen';
                selectCuentaDestino.parentNode.appendChild(feedback);
                return false;
            }
        }
        
        selectCuentaDestino.classList.remove('is-invalid');
        const feedback = selectCuentaDestino.nextElementSibling;
        if (feedback && feedback.className === 'invalid-feedback') {
            feedback.remove();
        }
        return true;
    }

    // Validar proveedor cuando es requerido
    function validarProveedor() {
        if (proveedorContainer.style.display === 'block' && !selectProveedor.value) {
            selectProveedor.classList.add('is-invalid');
            const feedback = selectProveedor.nextElementSibling || document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'Debe seleccionar un proveedor';
            selectProveedor.parentNode.appendChild(feedback);
            return false;
        }
        
        selectProveedor.classList.remove('is-invalid');
        const feedback = selectProveedor.nextElementSibling;
        if (feedback && feedback.className === 'invalid-feedback') {
            feedback.remove();
        }
        return true;
    }

    // Event listeners
    tipoOperacion.addEventListener('change', actualizarCampos);
    tipoEgreso.addEventListener('change', actualizarCampos);
    inputMonto.addEventListener('input', validarSaldo);
    selectCuentaOrigen.addEventListener('change', function() {
        validarSaldo();
        if (tipoOperacion.value === 'reembolso') {
            validarCuentasDiferentes();
        }
    });
    selectCuentaDestino.addEventListener('change', validarCuentasDiferentes);

    // Formatear monto
    inputMonto.addEventListener('blur', function() {
        if(this.value) {
            let valor = parseFloat(this.value);
            if(isNaN(valor) || valor <= 0) {
                this.value = '';
                return;
            }
            this.value = valor.toFixed(2);
            validarSaldo();
        }
    });

    // Validación de formulario al enviar
    form.addEventListener('submit', function(e) {
        // 1. Detener el envío para validar primero
        e.preventDefault();
        
        // 2. Actualizar campos visibles
        actualizarCampos();
        
        // 3. Validar todos los campos
        const esReembolso = tipoOperacion.value === 'reembolso';
        let valido = true;
        
        // Validar campos requeridos básicos
        const camposRequeridos = [
            {element: document.querySelector('[name="fecha"]'), message: 'La fecha es requerida'},
            {element: document.querySelector('[name="id_sucursal"]'), message: 'La sucursal es requerida'},
            {element: document.querySelector('[name="id_cuenta_origen"]'), message: 'La cuenta origen es requerida'},
            {element: document.querySelector('[name="monto"]'), message: 'El monto es requerido'},
            {element: document.querySelector('[name="concepto"]'), message: 'El concepto es requerido'}
        ];
        
        if (!esReembolso) {
            camposRequeridos.push(
                {element: tipoEgreso, message: 'El tipo de egreso es requerido'}
            );
        }
        
        camposRequeridos.forEach(campo => {
            if (!campo.element.value) {
                campo.element.classList.add('is-invalid');
                const feedback = campo.element.nextElementSibling || document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = campo.message;
                campo.element.parentNode.appendChild(feedback);
                valido = false;
            }
        });
        
        // Validaciones específicas
        if (!validarSaldo()) {
            valido = false;
        }
        
        if (esReembolso) {
            if (!validarCuentasDiferentes()) {
                valido = false;
            }
        } else {
            if (!validarProveedor()) {
                valido = false;
            }
        }
        
        // 4. Si todo es válido, enviar el formulario
        if (valido) {
            // Mostrar loader en el botón
            btnRegistrar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            btnRegistrar.disabled = true;
            
            // Enviar el formulario
            this.submit();
        }
    });
    
    // Disparar eventos change al cargar la página
    actualizarCampos();
});
</script>