<?php
// Habilitar mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicio de sesión debe ir al principio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reemplaza con la lógica para forzar inicio de sesión si es necesario
// $_SESSION['ID_Operador'] = 1; // Descomenta sólo para pruebas locales si no tienes login

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$database = new Database();
$pdo = $database->conectar();

try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

$success = null;
$error = null;

// ==========================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar sesión del operador primero
    if (!isset($_SESSION['ID_Operador'])) {
        $error = "Error: No ha iniciado sesión o su sesión ha expirado.";
    } else {
        $id_operador = $_SESSION['ID_Operador'];
        $accion = $_POST['accion'] ?? '';

        // --- PROCESAR DEPÓSITO ---
        if ($accion === 'registrar_deposito') {
            try {
                $pdo->beginTransaction();

                if (empty($_POST['id_cuenta']) || empty($_POST['monto_deposito']) || empty($_POST['metodo_pago'])) {
                    throw new Exception("Todos los campos marcados con asterisco (*) son obligatorios.");
                }

                $id_cuenta     = intval($_POST['id_cuenta']);
                $monto         = floatval($_POST['monto_deposito']);
                $metodo_pago   = $_POST['metodo_pago'];
                $concepto      = !empty($_POST['concepto']) ? $_POST['concepto'] : 'Depósito a cuenta';
                $observaciones = $_POST['observaciones'] ?? '';

                if ($monto <= 0) {
                    throw new Exception("El monto debe ser mayor a cero.");
                }

                // Insertar en tabla egresos como Depósito
                $sql_egreso = "INSERT INTO egresos (
                    fecha, id_cuenta, monto, concepto, metodo_pago, observaciones, tipo_operacion, id_tipo_egreso,
                    id_sucursal, activo, fecha_creacion
                ) VALUES (
                    CURDATE(), :id_cuenta, :monto, :concepto, :metodo_pago, :observaciones, 'Deposito', 9, 4, 1, NOW()
                )";
                $stmt_egreso = $pdo->prepare($sql_egreso);
                $stmt_egreso->execute([
                    ':id_cuenta'     => $id_cuenta,
                    ':monto'         => $monto,
                    ':concepto'      => $concepto,
                    ':metodo_pago'   => $metodo_pago,
                    ':observaciones' => $observaciones
                ]);

                // Actualizar saldo (Sumar)
                $sql_cuenta = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual + :monto WHERE id_cuenta = :id_cuenta AND activo = 1";
                $stmt_cuenta = $pdo->prepare($sql_cuenta);
                $stmt_cuenta->execute([':monto' => $monto, ':id_cuenta' => $id_cuenta]);

                $pdo->commit();
                $success = "Depósito registrado correctamente.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }

        // --- PROCESAR TRANSFERENCIA ---
        if ($accion === 'registrar_transferencia') {
            try {
                $pdo->beginTransaction();

                if (empty($_POST['cuenta_origen']) || empty($_POST['cuenta_destino']) || empty($_POST['monto'])) {
                    throw new Exception("Todos los campos obligatorios de la transferencia deben ser completados.");
                }

                $cuenta_origen  = intval($_POST['cuenta_origen']);
                $cuenta_destino = intval($_POST['cuenta_destino']);
                $monto          = floatval($_POST['monto']);
                $concepto       = !empty($_POST['concepto']) ? $_POST['concepto'] : 'Transferencia entre cuentas';

                if ($cuenta_origen === $cuenta_destino) {
                    throw new Exception("La cuenta de origen no puede ser la misma que la cuenta de destino.");
                }

                if ($monto <= 0) {
                    throw new Exception("El monto a transferir debe ser mayor a cero.");
                }

                // Verificar saldo origen
                $stmt_saldo = $pdo->prepare("SELECT saldo_actual FROM cuentas_bancarias WHERE id_cuenta = :id AND activo = 1");
                $stmt_saldo->execute([':id' => $cuenta_origen]);
                $res_origen = $stmt_saldo->fetch(PDO::FETCH_ASSOC);

                if (!$res_origen) {
                    throw new Exception("La cuenta origen no existe o está inactiva.");
                }
                if ($res_origen['saldo_actual'] < $monto) {
                    throw new Exception("Saldo insuficiente en la cuenta de origen. Disponible: $" . number_format($res_origen['saldo_actual'], 2));
                }

                // Insertar movimiento en tabla egresos
                $sql_egreso = "INSERT INTO egresos (
                    fecha, id_cuenta, id_cuenta_destino, monto, concepto, metodo_pago, tipo_operacion, id_tipo_egreso,
                    id_sucursal, activo, fecha_creacion
                ) VALUES (
                    CURDATE(), :id_cuenta, :id_cuenta_destino, :monto, :concepto, 'Transferencia', 'Transferencia',10 , 4, 1, NOW()
                )";
                $stmt_egreso = $pdo->prepare($sql_egreso);
                $stmt_egreso->execute([
                    ':id_cuenta'         => $cuenta_origen,
                    ':id_cuenta_destino' => $cuenta_destino,
                    ':monto'             => $monto,
                    ':concepto'          => $concepto
                ]);

                // Restar saldo origen
                $sql_restar = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual - :monto WHERE id_cuenta = :id";
                $stmt_restar = $pdo->prepare($sql_restar);
                $stmt_restar->execute([':monto' => $monto, ':id' => $cuenta_origen]);

                // Sumar saldo destino
                $sql_sumar = "UPDATE cuentas_bancarias SET saldo_actual = saldo_actual + :monto WHERE id_cuenta = :id";
                $stmt_sumar = $pdo->prepare($sql_sumar);
                $stmt_sumar->execute([':monto' => $monto, ':id' => $cuenta_destino]);

                $pdo->commit();
                $success = "Transferencia realizada con éxito.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// ==========================================
// CONSULTAS PARA LA VISTA (GET)
// ==========================================
// Obtener el listado de cuentas bancarias activas
$cuentas_bancarias = $pdo->query("
    SELECT id_cuenta, nombre, banco, numero, saldo_actual
    FROM cuentas_bancarias 
    WHERE activo = 1
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener los últimos 10 movimientos de egresos/depósitos/transferencias para el historial
$movimientos = $pdo->query("
    SELECT e.fecha, e.tipo_operacion, e.monto, e.concepto,
           cb1.nombre AS cuenta_origen, 
           cb2.nombre AS cuenta_destino
    FROM egresos e
    LEFT JOIN cuentas_bancarias cb1 ON e.id_cuenta = cb1.id_cuenta
    LEFT JOIN cuentas_bancarias cb2 ON e.id_cuenta_destino = cb2.id_cuenta
    ORDER BY e.fecha_creacion DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


$titulo = "Transferencia cuentas";
$encabezado = "Transferencia cuentas";
$subtitulo = "Realiza transferencias y depósitos en las cuentas";
$ruta = "dashboard_cuentas.php";
$texto_boton = "";

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="container-transferencias-full">
        <h1 class="section-title-transferencias">Transferencia y depósitos de cuentas</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <ul class="nav nav-tabs card-header-tabs" id="tabTransferencias" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="deposito-tab" data-bs-toggle="tab" data-bs-target="#deposito" type="button" role="tab">
                            <i class="bi bi-cash-coin"></i> Depósito
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transferencia-tab" data-bs-toggle="tab" data-bs-target="#transferencia" type="button" role="tab">
                            <i class="bi bi-arrow-left-right"></i> Transferencia
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="tabTransferenciasContent">
                    
                    <div class="tab-pane fade show active" id="deposito" role="tabpanel">
                        <form id="formDeposito" method="POST" action="">
                            <input type="hidden" name="accion" value="registrar_deposito">
                            
                            <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Realizar Depósito a Cuenta</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Cuenta bancaria <span class="text-danger">*</span></label>
                                <select class="form-select" name="id_cuenta" required>
                                    <option value="">Seleccione una cuenta...</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ') - Saldo: $' . number_format($cuenta['saldo_actual'], 2)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="monto_deposito" class="form-label">Monto del Depósito *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="monto_deposito" name="monto_deposito" 
                                           placeholder="0.00" min="0.01" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex gap-3">
                                    <div class="flex-grow-1">
                                        <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                        <select class="form-select" id="metodo_pago" name="metodo_pago" required>
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Transferencia">Transferencia</option>
                                            <option value="Tarjeta">Tarjeta</option>
                                        </select>
                                    </div>

                                    <div class="flex-grow-1">
                                        <label for="concepto_deposito" class="form-label">Concepto</label>
                                        <input type="text" class="form-control" id="concepto_deposito" name="concepto" placeholder="Ej. Depósito inicial">
                                    </div>

                                    <div class="flex-grow-1">
                                        <label for="referencia" class="form-label">Referencia</label>
                                        <input type="text" class="form-control" id="referencia" name="referencia">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <input type="text" class="form-control" id="observaciones" name="observaciones" >
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Realizar Depósito
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="transferencia" role="tabpanel">
                        <form id="formTransferencia" method="POST" action="">
                            <input type="hidden" name="accion" value="registrar_transferencia">

                            <h5 class="mb-3"><i class="bi bi-send"></i> Realizar Transferencia entre Cuentas</h5>

                            <div class="mb-3">
                                <label class="cuenta_origen">Cuenta Origen <span class="text-danger">*</span></label>
                                <select class="form-select" id="cuenta_origen" name="cuenta_origen" required>
                                    <option value="">Seleccione cuenta origen</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ') - Saldo: $' . number_format($cuenta['saldo_actual'], 2)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="cuenta_destino_transferencia">Cuenta bancaria Destino <span class="text-danger">*</span></label>
                                <select class="form-select" id="cuenta_destino_transferencia" name="cuenta_destino" required>
                                    <option value="">Seleccione cuenta destino</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="monto_transferencia" class="form-label">Monto a Transferir *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="monto_transferencia" name="monto" 
                                           placeholder="0.00" min="0.01" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="concepto_transferencia" class="form-label">Concepto</label>
                                <textarea class="form-control" id="concepto_transferencia" name="concepto" 
                                          rows="2" placeholder="Ej: Pago de servicios, Transferencia a ahorro..."></textarea>
                            </div>

                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle"></i> 
                                <strong>Importante:</strong> Verifique que los datos sean correctos antes de confirmar la transferencia.
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send-check"></i> Confirmar Transferencia
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Movimientos Recientes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cuenta Origen/Única</th>
                                <th>Cuenta Destino</th>
                                <th>Monto</th>
                                <th>Concepto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movimientos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mt-2">No hay movimientos recientes</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mov['fecha']) ?></td>
                                        <td>
                                            <span class="badge <?= $mov['tipo_operacion'] === 'Deposito' ? 'bg-success' : 'bg-primary' ?>">
                                                <?= htmlspecialchars($mov['tipo_operacion']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($mov['cuenta_origen'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($mov['cuenta_destino'] ?? 'N/A') ?></td>
                                        <td><strong>$<?= number_format($mov['monto'], 2) ?></strong></td>
                                        <td><?= htmlspecialchars($mov['concepto']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>