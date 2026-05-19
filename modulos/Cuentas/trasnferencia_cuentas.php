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


$database = new Database();
$pdo = $database->conectar();

try {
    if (!$pdo) {
        throw new Exception("No hay conexión a la base de datos");
    }
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}


$cuentas_bancarias = $pdo->query("
    SELECT id_cuenta, nombre, banco, numero 
    FROM cuentas_bancarias 
    WHERE activo = 1
    ORDER BY nombre
")->fetchAll();




$titulo = "Transferencia cuentas";
$encabezado = "Transferencia cuentas";
$subtitulo = "Realiza trasnferencias y depositos hacia en las cuentas";
$ruta = "dashboard_cuentas.php";
$texto_boton = "";
require_once __DIR__ . '/../../includes/header.php';
?>


<main class="container mt-4">
    <div class="container-transferencias-full">
        <h1 class="section-title-transferencias">Transferencia y depósitos de cuentas</h1>
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
                    
                    <!-- FORMULARIO DE DEPÓSITO -->
                    <div class="tab-pane fade show active" id="deposito" role="tabpanel">
                        <form id="formDeposito" method="POST" action="procesar_deposito.php">
                            <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Realizar Depósito a Cuenta</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Cuenta bancaria <span class="text-danger">*</span></label>
                                <select class="form-select" name="id_cuenta" required>
                                    <option value="">Seleccione una cuenta...</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="monto_deposito" class="form-label">Monto del Depósito *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="monto_deposito" name="monto" 
                                           placeholder="0.00" min="0.01" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="mb-4">
                                    <label for="concepto_deposito" class="form-label">Concepto</label>
                                    <input type="text" class="form-control" id="concepto_deposito" name="concepto" >
                                </div>
    
                                <div class="col-md-4">
                                    <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                    <select class="form-select" name="metodo_pago" required>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Tarjeta">Tarjeta</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="referencia" class="form-label">Referenia</label>
                                    <input type="text" class="form-control" id="referencia" name="referencia" >
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

                    <!-- FORMULARIO DE TRANSFERENCIA -->
                    <div class="tab-pane fade" id="transferencia" role="tabpanel">
                        <form id="formTransferencia" method="POST" action="procesar_transferencia.php">
                            <h5 class="mb-3"><i class="bi bi-send"></i> Realizar Transferencia entre Cuentas</h5>

                            <div class="mb-3">
                                <label class="cuenta_origen">Cuenta Origen *<span class="text-danger">*</span></label>
                                <select class="form-select" id="cuenta_origen" name="cuenta_origen" required>
                                    <option value="">Seleccione cuenta origen</option>
                                    <?php foreach ($cuentas_bancarias as $cuenta): ?>
                                        <option value="<?= $cuenta['id_cuenta'] ?>">
                                            <?= htmlspecialchars($cuenta['banco'] . ' - ' . $cuenta['nombre'] . ' (' . $cuenta['numero'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                            <div class="mb-3">
                                <label class="cuenta_destino_transferencia">Cuenta bancaria <span class="text-danger">*</span></label>
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

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="programar_transferencia" name="programar">
                                    <label class="form-check-label" for="programar_transferencia">
                                        Programar transferencia
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="fecha_programada_container" style="display: none;">
                                <label for="fecha_programada" class="form-label">Fecha Programada</label>
                                <input type="date" class="form-control" id="fecha_programada" name="fecha_programada">
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

        <!-- Historial de Movimientos Recientes -->
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
                                <th>Cuenta Origen</th>
                                <th>Cuenta Destino</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2">No hay movimientos recientes</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>



<?php require_once __DIR__ . '/../../includes/footer.php'; ?>